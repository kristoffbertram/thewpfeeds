<?php
/**
 * The WP Feeds — public developer API.
 *
 * @package TheWPFeeds
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use TheWPFeeds\Feed\Feed;
use TheWPFeeds\Item\Item;
use TheWPFeeds\Item\ItemCollection;
use TheWPFeeds\Plugin;

if (!function_exists('thewpfeeds')) {
    /**
     * The loop API: cached items for a feed, ready to iterate in any template.
     *
     *     foreach ( thewpfeeds( 'linkedin-main' ) as $item ) {
     *         echo esc_html( $item->title( 'Untitled' ) );
     *     }
     *
     * Never blocks on the remote API (except a feed's very first fetch):
     * stale items are served immediately and a background refresh is queued.
     */
    function thewpfeeds(string $feed_slug): ItemCollection
    {
        return Plugin::instance()->items($feed_slug);
    }
}

if (!function_exists('thewpfeeds_render')) {
    /**
     * Render a feed through the template chain (feed.php → layout-*.php → item.php).
     *
     * @param array{layout?: string, count?: int, wrapper_attributes?: string} $args
     */
    function thewpfeeds_render(string $feed_slug, array $args = []): void
    {
        Plugin::instance()->render($feed_slug, $args);
    }
}

if (!function_exists('thewpfeeds_item')) {
    /**
     * Render one item through the item template hierarchy — most specific wins:
     *
     *   {theme}/thewpfeeds/item-{feed-slug}.php   e.g. item-linkedin-main.php
     *   {theme}/thewpfeeds/item-{provider}.php    e.g. item-youtube.php
     *   {theme}/thewpfeeds/item.php
     *   (each name also falls back to the plugin's templates/ dir)
     *
     * Layouts call this per item; call it yourself in custom layouts.
     */
    function thewpfeeds_item(Item $item, Feed $feed): void
    {
        Plugin::instance()->templates()->renderFirst(
            [
                'item-' . $feed->slug,
                'item-' . $item->provider,
                'item',
            ],
            ['item' => $item, 'feed' => $feed]
        );
    }
}

if (!function_exists('thewpfeeds_template')) {
    /**
     * get_template_part() equivalent for The WP Feeds templates — resolves
     * through child theme → parent theme → plugin. For use inside overrides.
     *
     * @param array<string, mixed> $vars Extracted into the template's scope.
     */
    function thewpfeeds_template(string $name, array $vars = []): void
    {
        Plugin::instance()->templates()->render($name, $vars);
    }
}
