<?php
/**
 * Uninstall cleanup — only removes data when the site owner opted in
 * via the `thewpfeeds_delete_data_on_uninstall` option.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!get_option('thewpfeeds_delete_data_on_uninstall')) {
    return;
}

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

// Options: connections + per-connection token blobs.
$thewpfeeds_connections = get_option('thewpfeeds_connections', []);

if (is_array($thewpfeeds_connections)) {
    foreach ($thewpfeeds_connections as $thewpfeeds_connection) {
        if (is_array($thewpfeeds_connection) && isset($thewpfeeds_connection['id'])) {
            delete_option('thewpfeeds_tokens_' . sanitize_key((string) $thewpfeeds_connection['id']));
        }
    }
}

delete_option('thewpfeeds_connections');
delete_option('thewpfeeds_delete_data_on_uninstall');

// Cron events.
wp_clear_scheduled_hook('thewpfeeds_refresh');

// Localized images: uploads/thewpfeeds/.
$thewpfeeds_upload = wp_upload_dir();
$thewpfeeds_dir = trailingslashit($thewpfeeds_upload['basedir']) . 'thewpfeeds';

if (is_dir($thewpfeeds_dir)) {
    foreach (glob($thewpfeeds_dir . '/*/*') ?: [] as $thewpfeeds_file) {
        @unlink($thewpfeeds_file);
    }
    foreach (glob($thewpfeeds_dir . '/*', GLOB_ONLYDIR) ?: [] as $thewpfeeds_subdir) {
        @rmdir($thewpfeeds_subdir);
    }
    @rmdir($thewpfeeds_dir);
}
