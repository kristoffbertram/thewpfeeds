<?php

declare(strict_types=1);

namespace TheWPFeeds\Fetch;

use TheWPFeeds\Cache\ItemCache;
use TheWPFeeds\Feed\FeedRepository;

/**
 * Prefetch scheduling: a 15-minute sweep refreshes feeds past their TTL
 * (respecting failure backoff), plus one-off events for stale-while-revalidate.
 */
final class Cron
{
    public const HOOK_REFRESH_ALL = 'thewpfeeds_refresh';
    public const HOOK_REFRESH_ONE = 'thewpfeeds_refresh_feed';
    private const SCHEDULE = 'thewpfeeds_15min';

    public function __construct(
        private readonly FeedRepository $feeds,
        private readonly ItemCache $cache,
        private readonly FeedRunner $runner,
    ) {
    }

    public function hooks(): void
    {
        add_filter('cron_schedules', [$this, 'registerSchedule']);
        add_action(self::HOOK_REFRESH_ALL, [$this, 'refreshDueFeeds']);
        add_action(self::HOOK_REFRESH_ONE, [$this, 'refreshFeed']);

        if (!wp_next_scheduled(self::HOOK_REFRESH_ALL)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK_REFRESH_ALL);
        }
    }

    /** @param array<string, array{interval: int, display: string}> $schedules */
    public function registerSchedule(array $schedules): array
    {
        $schedules[self::SCHEDULE] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 minutes (The WP Feeds)', 'thewpfeeds'),
        ];

        return $schedules;
    }

    public function refreshDueFeeds(): void
    {
        foreach ($this->feeds->all() as $feed) {
            if ($this->cache->isDue($feed)) {
                $this->runner->run($feed);
            }
        }
    }

    public function refreshFeed(int $feedId): void
    {
        $feed = $this->feeds->find($feedId);

        if ($feed !== null) {
            $this->runner->run($feed);
        }
    }

    /** Queue a background refresh for a stale feed (stale-while-revalidate). */
    public function scheduleRefresh(int $feedId): void
    {
        if (!wp_next_scheduled(self::HOOK_REFRESH_ONE, [$feedId])) {
            wp_schedule_single_event(time(), self::HOOK_REFRESH_ONE, [$feedId]);

            if (!defined('DOING_CRON') || !DOING_CRON) {
                spawn_cron();
            }
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK_REFRESH_ALL);
    }
}
