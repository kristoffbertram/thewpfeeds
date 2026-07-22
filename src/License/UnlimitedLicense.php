<?php

declare(strict_types=1);

namespace FreshetFeeds\License;

/**
 * The wordpress.org build: fully functional, unlimited feeds, no license
 * checks — per directory Guideline 5 (no locked features). Used automatically
 * when the remote-license stack is not present (it is stripped from the
 * directory build; see bin/build-release.sh).
 */
final class UnlimitedLicense implements LicenseInterface
{
    public function isPro(): bool
    {
        return true;
    }

    public function maxFeeds(): int
    {
        return -1;
    }

    public function canCreateFeed(int $existingCount): bool
    {
        return true;
    }
}
