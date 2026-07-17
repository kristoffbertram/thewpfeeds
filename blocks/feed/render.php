<?php
/**
 * Server render for the thewpfeeds/feed block — a thin skin over the same
 * thewpfeeds_render() path theme developers call directly.
 *
 * @var array{feedId: int, layout: string, count: int} $attributes
 */

if (!defined('ABSPATH')) {
    exit;
}

$thewpfeeds_feed = TheWPFeeds\Plugin::instance()->feeds()->find((int) $attributes['feedId']);

if ($thewpfeeds_feed === null) {
    return;
}

thewpfeeds_render($thewpfeeds_feed->slug, [
    'layout' => (string) $attributes['layout'],
    'count' => (int) $attributes['count'],
    'wrapper_attributes' => get_block_wrapper_attributes([
        'class' => 'thewpfeeds thewpfeeds--' . sanitize_html_class($thewpfeeds_feed->slug),
    ]),
]);
