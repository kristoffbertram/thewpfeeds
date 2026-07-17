<?php

declare(strict_types=1);

namespace TheWPFeeds\License;

/**
 * License backed by the remote license server. Behaves exactly like the free
 * tier until a key validates. Validation results are cached in a transient
 * (12h); a lapsed cache re-validates lazily and FAILS OPEN for one interval
 * on network errors — a hiccup at the license server must never downgrade a
 * paying customer's site.
 */
final class RemoteLicense implements LicenseInterface
{
    public const OPTION_KEY = 'thewpfeeds_license_key';
    private const CACHE_KEY = 'thewpfeeds_license_status';
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    private ?bool $valid = null;

    public function __construct(private readonly LicenseClient $client)
    {
    }

    public function isPro(): bool
    {
        return $this->valid ??= $this->resolve();
    }

    public function maxFeeds(): int
    {
        return $this->isPro() ? -1 : 1;
    }

    public function canCreateFeed(int $existingCount): bool
    {
        return $this->maxFeeds() === -1 || $existingCount < $this->maxFeeds();
    }

    public static function storedKey(): string
    {
        return (string) get_option(self::OPTION_KEY, '');
    }

    /** Force a fresh validation on next check (after activate/deactivate). */
    public static function bustCache(): void
    {
        delete_transient(self::CACHE_KEY);
    }

    private function resolve(): bool
    {
        $key = self::storedKey();

        if ($key === '') {
            return false;
        }

        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached) && ($cached['key'] ?? '') === $key) {
            return (bool) ($cached['valid'] ?? false);
        }

        $response = $this->client->validate($key, home_url());

        if (($response['error_code'] ?? '') === 'http_error') {
            // Server unreachable: keep the customer running, retry next interval.
            set_transient(self::CACHE_KEY, ['key' => $key, 'valid' => true], self::CACHE_TTL);

            return true;
        }

        $valid = ($response['success'] ?? false) && (bool) ($response['data']['valid'] ?? false);

        set_transient(self::CACHE_KEY, ['key' => $key, 'valid' => $valid], self::CACHE_TTL);

        return $valid;
    }
}
