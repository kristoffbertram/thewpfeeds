<?php

declare(strict_types=1);

namespace TheWPFeeds\Fetch;

use TheWPFeeds\Feed\Feed;

/**
 * Per-feed transient lock: prevents fetch stampedes (concurrent page loads,
 * cron + request overlap) and doubles as the per-feed API-call rate floor.
 */
final class FetchLock
{
    private const TTL = 5 * MINUTE_IN_SECONDS;

    public function acquire(Feed $feed): bool
    {
        $key = $this->key($feed);

        if (get_transient($key) !== false) {
            return false;
        }

        set_transient($key, 1, self::TTL);

        return true;
    }

    public function release(Feed $feed): void
    {
        delete_transient($this->key($feed));
    }

    private function key(Feed $feed): string
    {
        return 'thewpfeeds_lock_' . $feed->id;
    }
}
