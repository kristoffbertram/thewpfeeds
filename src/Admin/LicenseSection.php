<?php

declare(strict_types=1);

namespace TheWPFeeds\Admin;

use TheWPFeeds\License\LicenseClient;
use TheWPFeeds\License\LicenseInterface;
use TheWPFeeds\License\RemoteLicense;

/**
 * License block on the Feeds admin page: enter key → activate; deactivate;
 * status display. Server-side gating stays in FeedRepository — this is UI.
 */
final class LicenseSection
{
    public function __construct(
        private readonly LicenseClient $client,
        private readonly LicenseInterface $license,
    ) {
    }

    public function hooks(): void
    {
        add_action('admin_post_thewpfeeds_activate_license', [$this, 'activate']);
        add_action('admin_post_thewpfeeds_deactivate_license', [$this, 'deactivate']);
    }

    public function activate(): void
    {
        $this->authorize('thewpfeeds_activate_license');

        $key = sanitize_text_field(wp_unslash($_POST['license_key'] ?? ''));

        if ($key === '') {
            $this->back('error', __('Enter a license key.', 'thewpfeeds'));
        }

        $response = $this->client->activate($key, home_url());

        if (!($response['success'] ?? false)) {
            $this->back('error', (string) ($response['error'] ?? __('Activation failed.', 'thewpfeeds')));
        }

        update_option(RemoteLicense::OPTION_KEY, $key, false);
        RemoteLicense::bustCache();

        $this->back('saved');
    }

    public function deactivate(): void
    {
        $this->authorize('thewpfeeds_deactivate_license');

        $key = RemoteLicense::storedKey();

        if ($key !== '') {
            // Best effort: free the seat server-side, but always clear locally.
            $this->client->deactivate($key, home_url());
        }

        delete_option(RemoteLicense::OPTION_KEY);
        RemoteLicense::bustCache();

        $this->back('deleted');
    }

    public function render(): void
    {
        $key = RemoteLicense::storedKey();
        $isPro = $this->license->isPro();

        echo '<h2>' . esc_html__('License', 'thewpfeeds') . '</h2>';

        if ($key !== '') {
            printf(
                '<p>%s <code>%s…%s</code> — %s</p>',
                esc_html__('Key:', 'thewpfeeds'),
                esc_html(substr($key, 0, 6)),
                esc_html(substr($key, -4)),
                $isPro
                    ? '<strong style="color:#00a32a;">' . esc_html__('Pro active — unlimited feeds', 'thewpfeeds') . '</strong>'
                    : '<strong style="color:#b32d2e;">' . esc_html__('Invalid or expired — free tier (1 feed)', 'thewpfeeds') . '</strong>'
            );

            $deactivateUrl = wp_nonce_url(
                add_query_arg(['action' => 'thewpfeeds_deactivate_license'], admin_url('admin-post.php')),
                'thewpfeeds_deactivate_license'
            );

            printf(
                '<p><a href="%s" class="button">%s</a></p>',
                esc_url($deactivateUrl),
                esc_html__('Deactivate license on this site', 'thewpfeeds')
            );

            return;
        }

        echo '<p class="description">';
        printf(
            /* translators: %s: linked store URL */
            esc_html__('The free version supports 1 feed. Get unlimited feeds with %s.', 'thewpfeeds'),
            '<a href="https://wp.kristoffbertram.be" target="_blank" rel="noopener noreferrer">The WP Feeds Pro</a>'
        );
        echo '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:600px;">';
        wp_nonce_field('thewpfeeds_activate_license');
        echo '<input type="hidden" name="action" value="thewpfeeds_activate_license">';
        printf(
            '<p><input type="text" name="license_key" class="regular-text" placeholder="%s" required> ',
            esc_attr__('License key', 'thewpfeeds')
        );
        printf('<button type="submit" class="button button-primary">%s</button></p>', esc_html__('Activate', 'thewpfeeds'));
        echo '</form>';
    }

    private function authorize(string $nonceAction): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage the license.', 'thewpfeeds'));
        }

        check_admin_referer($nonceAction);
    }

    private function back(string $notice, string $message = ''): never
    {
        wp_safe_redirect(add_query_arg(array_filter([
            'page' => FeedsPage::SLUG,
            'tab' => 'license',
            'thewpfeeds_notice' => $notice,
            'thewpfeeds_message' => $message !== '' ? rawurlencode($message) : null,
        ]), admin_url('admin.php')));

        exit;
    }
}
