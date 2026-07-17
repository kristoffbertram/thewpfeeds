<?php
/**
 * Rendered when a feed has no items (not yet fetched, fetch failed, or genuinely empty).
 *
 * Override: copy to {your-theme}/thewpfeeds/empty.php
 *
 * Available:
 *   $feed  \TheWPFeeds\Feed\Feed
 *   $items \TheWPFeeds\Item\ItemCollection  (empty)
 *   $args  array
 *
 * @package TheWPFeeds\Templates
 * @version 1.0.0
 */

use TheWPFeeds\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

// Error details are for site managers only; visitors see nothing.
if (!current_user_can('manage_options')) {
    return;
}

$error = Plugin::instance()->itemCache()->lastError($feed);
?>
<div class="thewpfeeds thewpfeeds--empty">
    <p>
        <?php
        printf(
            /* translators: %s: feed name */
            esc_html__('The WP Feeds: no items to show for “%s”.', 'thewpfeeds'),
            esc_html($feed->name)
        );
        ?>
        <?php if ($error !== null) : ?>
            <br><code><?php echo esc_html($error); ?></code>
        <?php endif; ?>
        <em><?php esc_html_e('(Only administrators see this notice.)', 'thewpfeeds'); ?></em>
    </p>
</div>
