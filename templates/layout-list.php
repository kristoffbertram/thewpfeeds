<?php
/**
 * List layout: stacked rows.
 *
 * Override: copy to {your-theme}/thewpfeeds/layout-list.php
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
<div class="thewpfeeds__list">
    <?php foreach ($items as $item) : ?>
        <?php thewpfeeds_item($item, $feed); ?>
    <?php endforeach; ?>
</div>
