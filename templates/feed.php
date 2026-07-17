<?php
/**
 * Feed wrapper template.
 *
 * Override: copy to {your-theme}/thewpfeeds/feed.php
 *
 * Available:
 *   $feed   \TheWPFeeds\Feed\Feed              Feed config (name, slug, settings).
 *   $items  \TheWPFeeds\Item\ItemCollection    Normalized items — iterate directly.
 *   $layout string                             Layout name; resolves layout-{$layout}.php.
 *   $args   array                              Render args (block passes wrapper_attributes).
 *
 * Item getters return RAW values — escape in templates (esc_html, esc_url).
 *
 * @package TheWPFeeds\Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$thewpfeeds_wrapper = $args['wrapper_attributes'] ?? sprintf(
    'class="thewpfeeds thewpfeeds--%s"',
    esc_attr(sanitize_html_class($feed->slug))
);
?>
<div <?php echo $thewpfeeds_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput -- built above / by get_block_wrapper_attributes(). ?>>
    <?php
    thewpfeeds_template('layout-' . $layout, [
        'feed' => $feed,
        'items' => $items,
        'layout' => $layout,
        'args' => $args,
    ]);
    ?>
</div>
