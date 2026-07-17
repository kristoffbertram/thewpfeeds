<?php

declare(strict_types=1);

namespace TheWPFeeds\License;

interface LicenseInterface
{
    public function isPro(): bool;

    /** Maximum number of feeds; -1 means unlimited. */
    public function maxFeeds(): int;

    public function canCreateFeed(int $existingCount): bool;
}
