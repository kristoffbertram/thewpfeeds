<?php
/**
 * Grid layout: responsive card grid.
 *
 * Override: copy to {your-theme}/thewpfeeds/layout-grid.php
 * Custom layouts: add {your-theme}/thewpfeeds/layout-{name}.php and pass
 * that name via the block's layout setting or thewpfeeds_render() args.
 *
 * Available: $feed, $items, $layout, $args (see feed.php).
 *
 * Items resolve through the item hierarchy:
 * item-{feed-slug}.php → item-{provider}.php → item.php
 *
 * @package TheWPFeeds\Templates
 * @version 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="thewpfeeds__grid">
    <?php foreach ($items as $item) : ?>
        <?php thewpfeeds_item($item, $feed); ?>
    <?php endforeach; ?>
</div>
