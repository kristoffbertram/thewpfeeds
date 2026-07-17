<?php
/**
 * Uninstall cleanup — only removes data when the site owner opted in
 * via the `thewpfeeds_delete_data_on_uninstall` option.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// OAuth secrets, license key, and cron events are ALWAYS removed on uninstall —
// orphaned tokens are a liability regardless of the content-removal preference.
$thewpfeeds_connections = get_option('thewpfeeds_connections', []);

if (is_array($thewpfeeds_connections)) {
    foreach ($thewpfeeds_connections as $thewpfeeds_connection) {
        if (is_array($thewpfeeds_connection) && isset($thewpfeeds_connection['id'])) {
            delete_option('thewpfeeds_tokens_' . sanitize_key((string) $thewpfeeds_connection['id']));
        }
    }
}

delete_option('thewpfeeds_connections');
delete_option('thewpfeeds_license_key');
delete_option('thewpfeeds_license_last_ok');
wp_unschedule_hook('thewpfeeds_refresh');
wp_unschedule_hook('thewpfeeds_refresh_feed');

// Content (feeds + cached items + localized images) only goes when opted in.
if (!get_option('thewpfeeds_delete_data_on_uninstall')) {
    return;
}

delete_option('thewpfeeds_delete_data_on_uninstall');

// Feed posts + their meta (config, cached items).
$thewpfeeds_posts = get_posts([
    'post_type' => 'thewpfeeds_feed',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids',
]);

foreach ($thewpfeeds_posts as $thewpfeeds_post_id) {
    wp_delete_post($thewpfeeds_post_id, true);
}

// Localized images: uploads/thewpfeeds/.
$thewpfeeds_upload = wp_upload_dir();
$thewpfeeds_dir = trailingslashit($thewpfeeds_upload['basedir']) . 'thewpfeeds';

if (is_dir($thewpfeeds_dir)) {
    foreach (glob($thewpfeeds_dir . '/*/*') ?: [] as $thewpfeeds_file) {
        @unlink($thewpfeeds_file); // phpcs:ignore WordPress.WP.AlternativeFunctions -- uninstall cleanup of our own uploads/thewpfeeds dir.
    }
    foreach (glob($thewpfeeds_dir . '/*', GLOB_ONLYDIR) ?: [] as $thewpfeeds_subdir) {
        @rmdir($thewpfeeds_subdir); // phpcs:ignore WordPress.WP.AlternativeFunctions
    }
    @rmdir($thewpfeeds_dir); // phpcs:ignore WordPress.WP.AlternativeFunctions
}
