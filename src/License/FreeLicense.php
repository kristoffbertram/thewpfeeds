<?php

declare(strict_types=1);

namespace TheWPFeeds\License;

/**
 * The free tier: one feed. The future remote license client (EDD/LemonSqueezy)
 * replaces this via the `thewpfeeds_license` filter — no call sites change.
 */
final class FreeLicense implements LicenseInterface
{
    public function isPro(): bool
    {
        return false;
    }

    public function maxFeeds(): int
    {
        return 1;
    }

    public function canCreateFeed(int $existingCount): bool
    {
        return $existingCount < $this->maxFeeds();
    }
}
