<?php
/**
 * Plugin Name:       The WP Feeds
 * Plugin URI:        https://wp.kristoffbertram.be
 * Description:       Developer-first external feeds (LinkedIn first) with a shared item model and theme-overridable templates.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Kristoff Bertram
 * Author URI:        https://kristoffbertram.be
 * License:           GPL-2.0-or-later
 * Text Domain:       thewpfeeds
 * Domain Path:       /languages
 */

declare(strict_types=0); // Header file must load on old PHP to show the guard notice.

if (!defined('ABSPATH')) {
    exit;
}

define('THEWPFEEDS_VERSION', '1.0.0');
define('THEWPFEEDS_FILE', __FILE__);
define('THEWPFEEDS_DIR', plugin_dir_path(__FILE__));
define('THEWPFEEDS_URL', plugin_dir_url(__FILE__));

if (version_compare(PHP_VERSION, '8.2', '<')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %s: current PHP version */
                __('The WP Feeds requires PHP 8.2 or newer. This site runs PHP %s — the plugin is inactive.', 'thewpfeeds'),
                PHP_VERSION
            ))
        );
    });

    return;
}

if (!is_readable(THEWPFEEDS_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('The WP Feeds is missing its Composer autoloader. Run "composer install" in the plugin directory.', 'thewpfeeds')
        );
    });

    return;
}

require THEWPFEEDS_DIR . 'vendor/autoload.php';
require THEWPFEEDS_DIR . 'includes/functions.php';

TheWPFeeds\Plugin::boot();
