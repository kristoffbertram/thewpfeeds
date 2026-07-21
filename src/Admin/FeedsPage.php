<?php

declare(strict_types=1);

namespace TheWPFeeds\Admin;

use TheWPFeeds\Auth\LinkedInOAuth;
use TheWPFeeds\Cache\ItemCache;
use TheWPFeeds\Connection\ConnectionRepository;
use TheWPFeeds\Connection\LinkedInConnection;
use TheWPFeeds\Feed\Feed;
use TheWPFeeds\Feed\FeedRepository;
use TheWPFeeds\Fetch\FeedRunner;
use TheWPFeeds\License\LicenseInterface;
use TheWPFeeds\Provider\ProviderRegistry;
use Throwable;

/**
 * The single admin screen: feed list, feed add/edit form, LinkedIn connections.
 * Plain WP admin markup — no build step, no React.
 */
final class FeedsPage
{
    public const SLUG = 'thewpfeeds';
    private const CAP = 'manage_options';

    public function __construct(
        private readonly FeedRepository $feeds,
        private readonly ProviderRegistry $providers,
        private readonly ConnectionRepository $connections,
        private readonly ItemCache $cache,
        private readonly FeedRunner $runner,
        private readonly LicenseInterface $license,
        private readonly ?LicenseSection $licenseSection = null,
    ) {
    }

    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyle']);
        add_action('admin_post_thewpfeeds_save_feed', [$this, 'saveFeed']);
        add_action('admin_post_thewpfeeds_delete_feed', [$this, 'deleteFeed']);
        add_action('admin_post_thewpfeeds_refresh_feed', [$this, 'refreshFeed']);
        add_action('admin_post_thewpfeeds_save_connection', [$this, 'saveConnection']);
        add_action('admin_post_thewpfeeds_delete_connection', [$this, 'deleteConnection']);
        add_action('admin_notices', [$this, 'renderNotice']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('The WP Feeds', 'thewpfeeds'),
            __('Feeds', 'thewpfeeds'),
            self::CAP,
            self::SLUG,
            [$this, 'renderPage'],
            'dashicons-rss',
            58
        );
    }

    public function renderNotice(): void
    {
        $type = sanitize_key(wp_unslash($_GET['thewpfeeds_notice'] ?? ''));

        if ($type === '' || !current_user_can(self::CAP)) {
            return;
        }

        $message = sanitize_text_field(rawurldecode(wp_unslash($_GET['thewpfeeds_message'] ?? ''))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- sanitized after decode; display-only notice.

        [$class, $text] = match ($type) {
            'connected' => ['notice-success', sprintf(
                /* translators: %s: connection label */
                __('LinkedIn connection “%s” authorized.', 'thewpfeeds'),
                $message
            )],
            'saved' => ['notice-success', __('Saved.', 'thewpfeeds')],
            'deleted' => ['notice-success', __('Deleted.', 'thewpfeeds')],
            'refreshed' => ['notice-success', __('Feed refreshed.', 'thewpfeeds')],
            default => ['notice-error', $message !== '' ? $message : __('Something went wrong.', 'thewpfeeds')],
        };

        printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($text));
    }

    // ---------------------------------------------------------------- actions

    public function saveFeed(): void
    {
        $this->authorize('thewpfeeds_save_feed');

        $id = (int) ($_POST['feed_id'] ?? 0); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- int cast sanitizes; nonce verified in authorize().
        $providerId = sanitize_key(wp_unslash($_POST['provider'] ?? 'mock'));
        $provider = $this->providers->get($providerId);

        $settings = [];
        foreach (array_keys($provider?->settingsFields() ?? []) as $key) {
            $settings[$key] = sanitize_text_field(wp_unslash($_POST['settings'][$key] ?? ''));
        }

        $feed = new Feed(
            id: $id,
            name: sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            slug: sanitize_title(wp_unslash($_POST['slug'] ?? '')),
            providerId: $providerId,
            settings: $settings,
            count: min(50, max(1, (int) ($_POST['count'] ?? Feed::DEFAULT_COUNT))),
            ttl: min(WEEK_IN_SECONDS, max(5 * MINUTE_IN_SECONDS, (int) ($_POST['ttl'] ?? Feed::DEFAULT_TTL))),
            defaultLayout: sanitize_key(wp_unslash($_POST['layout'] ?? 'grid')),
        );

        try {
            $saved = $this->feeds->save($feed);
            $this->runner->run($saved, force: true);
            $this->back('saved');
        } catch (Throwable $e) {
            $this->back('error', $e->getMessage());
        }
    }

    public function deleteFeed(): void
    {
        $this->authorize('thewpfeeds_delete_feed');

        $id = (int) ($_GET['feed'] ?? 0);
        (new \TheWPFeeds\Cache\ImageStore())->deleteForFeed($id);
        $this->feeds->delete($id);
        $this->back('deleted');
    }

    public function refreshFeed(): void
    {
        $this->authorize('thewpfeeds_refresh_feed');

        $feed = $this->feeds->find((int) ($_GET['feed'] ?? 0));

        if ($feed === null) {
            $this->back('error', __('Unknown feed.', 'thewpfeeds'));
        }

        $items = $this->runner->run($feed, force: true);

        if ($items === null) {
            $this->back('error', $this->cache->lastError($feed) ?? __('Fetch failed.', 'thewpfeeds'));
        }

        $this->back('refreshed');
    }

    public function saveConnection(): void
    {
        $this->authorize('thewpfeeds_save_connection');

        $id = sanitize_key(wp_unslash($_POST['connection_id'] ?? ''));
        $isNew = $id === '';

        if ($isNew) {
            $id = 'li_' . substr(md5(uniqid('', true)), 0, 8);
        }

        $existing = $this->connections->find($id);

        $connection = new LinkedInConnection(
            id: $id,
            label: sanitize_text_field(wp_unslash($_POST['label'] ?? '')),
            mode: LinkedInConnection::MODE_BYO,
            clientId: sanitize_text_field(wp_unslash($_POST['client_id'] ?? '')),
            tokenExpiresAt: $existing?->tokenExpiresAt ?? 0,
            refreshTokenExpiresAt: $existing?->refreshTokenExpiresAt ?? 0,
            needsReauth: $existing?->needsReauth ?? false,
        );

        $this->connections->save($connection);

        // Keep existing tokens; only overwrite the secret when a new one is entered.
        $secret = (string) wp_unslash($_POST['client_secret'] ?? '');
        $secrets = $this->connections->tokens()->get($id);

        if ($secret !== '') {
            $secrets['client_secret'] = $secret;
        }

        $this->connections->tokens()->save($id, $secrets);

        $this->back('saved', tab: 'connections');
    }

    public function deleteConnection(): void
    {
        $this->authorize('thewpfeeds_delete_connection');

        $this->connections->delete(sanitize_key(wp_unslash($_GET['connection'] ?? '')));
        $this->back('deleted', tab: 'connections');
    }

    // --------------------------------------------------------------- rendering

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            return;
        }

        $tab = $this->currentTab();

        $this->renderHeader($tab);

        echo '<div class="wrap" style="margin-top:1.5em;">';

        if ($tab === 'connections') {
            $this->renderConnections();
        } elseif ($tab === 'license') {
            $this->licenseSection?->render();
        } else {
            $editId = (int) ($_GET['edit'] ?? 0);
            $editFeed = $editId > 0 ? $this->feeds->find($editId) : null;

            if (isset($_GET['add']) || $editFeed !== null) {
                $this->renderFeedForm($editFeed);
            } else {
                $this->renderFeedList();
            }
        }

        echo '</div>';
    }

    private function currentTab(): string
    {
        $tab = sanitize_key(wp_unslash($_GET['tab'] ?? ''));
        $tabs = $this->licenseSection !== null ? ['connections', 'license'] : ['connections'];

        return in_array($tab, $tabs, true) ? $tab : 'feeds';
    }

    /**
     * Slim brand strip + native nav-tabs. Scoped to this page only — the rest
     * of wp-admin is never touched. Tabs use core .nav-tab classes so the
     * user's admin color scheme applies.
     */
    /** Registered on admin_enqueue_scripts; loads only on this screen. */
    public function enqueueAdminStyle(string $hookSuffix): void
    {
        if ($hookSuffix !== 'toplevel_page_' . self::SLUG) {
            return;
        }

        wp_register_style('thewpfeeds-admin', false, [], THEWPFEEDS_VERSION);
        wp_enqueue_style('thewpfeeds-admin');
        wp_add_inline_style('thewpfeeds-admin', '
            .twpf-header { background: #fff; border-bottom: 1px solid #dcdcde; margin: 0 0 0 -20px; padding: 16px 20px 0; }
            .twpf-header__row { display: flex; align-items: center; gap: 10px; padding-bottom: 14px; }
            .twpf-header__mark { width: 28px; height: 28px; flex: none; }
            .twpf-header__title { font-size: 16px; font-weight: 600; color: #1d2327; margin: 0; padding: 0; }
            .twpf-header__version { font-size: 11px; color: #646970; background: #f0f0f1; border-radius: 10px; padding: 2px 8px; }
            .twpf-header__meta { margin-left: auto; display: flex; align-items: center; gap: 14px; font-size: 13px; }
            .twpf-header__pill { border-radius: 12px; padding: 3px 10px; font-weight: 600; font-size: 12px; }
            .twpf-header__pill--pro { background: #edfaef; color: #00832a; }
            .twpf-header__pill--free { background: #f0f0f1; color: #50575e; }
            .twpf-header .nav-tab-wrapper { border-bottom: 0; padding: 0; margin: 0; }
        ');
    }

    private function renderHeader(string $activeTab): void
    {
        $tabs = [
            'feeds' => __('Feeds', 'thewpfeeds'),
            'connections' => __('Connections', 'thewpfeeds'),
        ];

        if ($this->licenseSection !== null) {
            $tabs['license'] = __('License', 'thewpfeeds');
        }

        ?>
        <div class="twpf-header">
            <div class="twpf-header__row">
                <svg class="twpf-header__mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                    <rect width="32" height="32" rx="7" fill="#1122ff"/>
                    <circle cx="10" cy="22" r="3" fill="#fff"/>
                    <path d="M7 13a12 12 0 0 1 12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                    <path d="M7 6a19 19 0 0 1 19 19" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".55"/>
                </svg>
                <h1 class="twpf-header__title"><?php esc_html_e('The WP Feeds', 'thewpfeeds'); ?></h1>
                <span class="twpf-header__version"><?php echo esc_html('v' . THEWPFEEDS_VERSION); ?></span>
                <div class="twpf-header__meta">
                    <?php if ($this->licenseSection !== null) : ?>
                        <?php if ($this->license->isPro()) : ?>
                            <span class="twpf-header__pill twpf-header__pill--pro"><?php esc_html_e('Pro', 'thewpfeeds'); ?></span>
                        <?php else : ?>
                            <span class="twpf-header__pill twpf-header__pill--free"><?php esc_html_e('Free · 1 feed', 'thewpfeeds'); ?></span>
                            <a href="https://wp.kristoffbertram.be" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade', 'thewpfeeds'); ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="https://wp.kristoffbertram.be/docs" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Docs', 'thewpfeeds'); ?></a>
                    <a href="mailto:plugins@kristoffbertram.be"><?php esc_html_e('Support', 'thewpfeeds'); ?></a>
                </div>
            </div>
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $slug => $label) : ?>
                    <a class="nav-tab<?php echo $slug === $activeTab ? ' nav-tab-active' : ''; ?>"
                       href="<?php echo esc_url(add_query_arg(array_filter(['page' => self::SLUG, 'tab' => $slug !== 'feeds' ? $slug : null]), admin_url('admin.php'))); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php
    }

    private function renderFeedList(): void
    {
        $feeds = $this->feeds->all();
        $canAdd = $this->license->canCreateFeed($this->feeds->countBillable());

        if ($canAdd || $this->licenseSection === null) {
            printf(
                '<a href="%s" class="page-title-action">%s</a>',
                esc_url(add_query_arg(['page' => self::SLUG, 'add' => 1], admin_url('admin.php'))),
                esc_html__('Add feed', 'thewpfeeds')
            );
        } else {
            printf(
                '<p><strong>%s</strong></p>',
                esc_html__('Free version: 1 feed. Upgrade to Pro for unlimited feeds.', 'thewpfeeds')
            );
        }

        echo '<table class="widefat striped" style="margin-top:1em;"><thead><tr>';

        foreach ([__('Name', 'thewpfeeds'), __('Slug', 'thewpfeeds'), __('Provider', 'thewpfeeds'), __('Last fetched', 'thewpfeeds'), __('Status', 'thewpfeeds'), ''] as $col) {
            echo '<th>' . esc_html($col) . '</th>';
        }

        echo '</tr></thead><tbody>';

        if ($feeds === []) {
            echo '<tr><td colspan="6">' . esc_html__('No feeds yet.', 'thewpfeeds') . '</td></tr>';
        }

        foreach ($feeds as $feed) {
            $fetchedAt = $this->cache->fetchedAt($feed);
            $error = $this->cache->lastError($feed);

            $editUrl = add_query_arg(['page' => self::SLUG, 'edit' => $feed->id], admin_url('admin.php'));
            $refreshUrl = wp_nonce_url(
                add_query_arg(['action' => 'thewpfeeds_refresh_feed', 'feed' => $feed->id], admin_url('admin-post.php')),
                'thewpfeeds_refresh_feed'
            );
            $deleteUrl = wp_nonce_url(
                add_query_arg(['action' => 'thewpfeeds_delete_feed', 'feed' => $feed->id], admin_url('admin-post.php')),
                'thewpfeeds_delete_feed'
            );

            echo '<tr>';
            echo '<td><strong>' . esc_html($feed->name) . '</strong></td>';
            echo '<td><code>' . esc_html($feed->slug) . '</code></td>';
            echo '<td>' . esc_html($this->providers->get($feed->providerId)?->label() ?? $feed->providerId) . '</td>';
            echo '<td>' . esc_html($fetchedAt > 0
                ? sprintf(/* translators: %s: human time diff */ __('%s ago', 'thewpfeeds'), human_time_diff($fetchedAt))
                : __('never', 'thewpfeeds')) . '</td>';
            echo '<td>' . ($error !== null
                ? '<span style="color:#b32d2e;">' . esc_html($error) . '</span>'
                : '<span style="color:#00a32a;">OK</span>') . '</td>';
            printf(
                '<td><a href="%s">%s</a> | <a href="%s">%s</a> | <a href="%s" onclick="return confirm(%s);">%s</a></td>',
                esc_url($editUrl),
                esc_html__('Edit', 'thewpfeeds'),
                esc_url($refreshUrl),
                esc_html__('Refresh', 'thewpfeeds'),
                esc_url($deleteUrl),
                esc_attr(wp_json_encode(__('Delete this feed?', 'thewpfeeds'))),
                esc_html__('Delete', 'thewpfeeds')
            );
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p style="margin-top:1em;color:#646970;">';
        printf(
            /* translators: %s: PHP code example */
            esc_html__('Render a feed in your theme with %s or the “Feed” block.', 'thewpfeeds'),
            '<code>thewpfeeds_render( \'feed-slug\' )</code>'
        );
        echo '</p>';
    }

    private function renderFeedForm(?Feed $feed): void
    {
        $isNew = $feed === null;
        $backUrl = add_query_arg(['page' => self::SLUG], admin_url('admin.php'));

        echo '<h2>' . esc_html($isNew ? __('Add feed', 'thewpfeeds') : __('Edit feed', 'thewpfeeds')) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('thewpfeeds_save_feed');
        echo '<input type="hidden" name="action" value="thewpfeeds_save_feed">';
        echo '<input type="hidden" name="feed_id" value="' . esc_attr((string) ($feed->id ?? 0)) . '">';

        echo '<table class="form-table" role="presentation">';

        $this->row(
            __('Name', 'thewpfeeds'),
            sprintf('<input type="text" name="name" class="regular-text" required value="%s">', esc_attr($feed->name ?? ''))
        );
        $this->row(
            __('Slug', 'thewpfeeds'),
            sprintf(
                '<input type="text" name="slug" class="regular-text" value="%s"><p class="description">%s</p>',
                esc_attr($feed->slug ?? ''),
                esc_html__('Used in code: thewpfeeds_render( \'slug\' ). Leave empty to derive from the name.', 'thewpfeeds')
            )
        );

        $providerSelect = '<select name="provider" id="thewpfeeds-provider">';
        foreach ($this->providers->all() as $provider) {
            if ($provider->id() === 'mock' && wp_get_environment_type() === 'production') {
                continue;
            }

            $providerSelect .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($provider->id()),
                selected($feed->providerId ?? 'linkedin', $provider->id(), false),
                esc_html($provider->label())
            );
        }
        $providerSelect .= '</select>';
        $this->row(__('Provider', 'thewpfeeds'), $providerSelect);

        foreach ($this->providers->all() as $provider) {
            foreach ($provider->settingsFields() as $key => $field) {
                $value = (string) ($feed?->setting($key, '') ?? '');
                $name = sprintf('settings[%s]', esc_attr($key));

                if (($field['type'] ?? 'text') === 'connection') {
                    $input = '<select name="' . $name . '">';
                    $input .= '<option value="">' . esc_html__('— select —', 'thewpfeeds') . '</option>';
                    foreach ($this->connections->all() as $connection) {
                        $input .= sprintf(
                            '<option value="%s"%s>%s%s</option>',
                            esc_attr($connection->id),
                            selected($value, $connection->id, false),
                            esc_html($connection->label),
                            $connection->isConnected() ? '' : esc_html__(' (not connected)', 'thewpfeeds')
                        );
                    }
                    $input .= '</select>';
                } else {
                    $input = sprintf('<input type="text" name="%s" class="regular-text" value="%s">', $name, esc_attr($value));
                }

                if (isset($field['help'])) {
                    $input .= '<p class="description">' . esc_html($field['help']) . '</p>';
                }

                $this->row(sprintf('%s (%s)', $field['label'], $provider->label()), $input);
            }
        }

        $this->row(
            __('Items', 'thewpfeeds'),
            sprintf('<input type="number" name="count" min="1" max="50" value="%s">', esc_attr((string) ($feed->count ?? Feed::DEFAULT_COUNT)))
        );
        $this->row(
            __('Cache TTL (seconds)', 'thewpfeeds'),
            sprintf('<input type="number" name="ttl" min="300" step="60" value="%s">', esc_attr((string) ($feed->ttl ?? Feed::DEFAULT_TTL)))
        );

        $layout = $feed->defaultLayout ?? 'grid';
        $this->row(
            __('Default layout', 'thewpfeeds'),
            sprintf(
                '<select name="layout"><option value="grid"%s>%s</option><option value="list"%s>%s</option></select>',
                selected($layout, 'grid', false),
                esc_html__('Grid', 'thewpfeeds'),
                selected($layout, 'list', false),
                esc_html__('List', 'thewpfeeds')
            )
        );

        echo '</table>';

        submit_button($isNew ? __('Create feed', 'thewpfeeds') : __('Save feed', 'thewpfeeds'));
        printf('<a href="%s">%s</a>', esc_url($backUrl), esc_html__('Back to list', 'thewpfeeds'));
        echo '</form>';
    }

    private function renderConnections(): void
    {
        echo '<h2>' . esc_html__('LinkedIn connections', 'thewpfeeds') . '</h2>';
        echo '<p class="description">';
        printf(
            /* translators: %s: OAuth redirect URI */
            esc_html__('Create a LinkedIn developer app with Community Management API access and register this redirect URL: %s', 'thewpfeeds'),
            '<code>' . esc_html(LinkedInOAuth::redirectUri()) . '</code>'
        );
        echo '</p>';

        $connections = $this->connections->all();

        if ($connections !== []) {
            echo '<table class="widefat striped" style="max-width:800px;"><tbody>';

            foreach ($connections as $connection) {
                $connectUrl = wp_nonce_url(
                    add_query_arg(['action' => 'thewpfeeds_oauth_start', 'connection' => $connection->id], admin_url('admin-post.php')),
                    'thewpfeeds_oauth_start'
                );
                $deleteUrl = wp_nonce_url(
                    add_query_arg(['action' => 'thewpfeeds_delete_connection', 'connection' => $connection->id], admin_url('admin-post.php')),
                    'thewpfeeds_delete_connection'
                );

                $status = $connection->isConnected()
                    ? '<span style="color:#00a32a;">' . esc_html__('Connected', 'thewpfeeds') . '</span>'
                    : '<span style="color:#b32d2e;">' . esc_html($connection->needsReauth
                        ? __('Reauthorization needed', 'thewpfeeds')
                        : __('Not connected', 'thewpfeeds')) . '</span>';

                printf(
                    '<tr><td><strong>%s</strong></td><td>%s</td><td>%s</td><td><a href="%s">%s</a> | <a href="%s" onclick="return confirm(%s);">%s</a></td></tr>',
                    esc_html($connection->label),
                    esc_html($connection->clientId),
                    $status, // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
                    esc_url($connectUrl),
                    esc_html__('Connect / reauthorize', 'thewpfeeds'),
                    esc_url($deleteUrl),
                    esc_attr(wp_json_encode(__('Delete this connection?', 'thewpfeeds'))),
                    esc_html__('Delete', 'thewpfeeds')
                );
            }

            echo '</tbody></table>';
        }

        echo '<h3>' . esc_html__('Add connection', 'thewpfeeds') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:800px;">';
        wp_nonce_field('thewpfeeds_save_connection');
        echo '<input type="hidden" name="action" value="thewpfeeds_save_connection">';
        echo '<table class="form-table" role="presentation">';
        $this->row(__('Label', 'thewpfeeds'), '<input type="text" name="label" class="regular-text" required>');
        $this->row(__('Client ID', 'thewpfeeds'), '<input type="text" name="client_id" class="regular-text" required>');
        $this->row(__('Client secret', 'thewpfeeds'), '<input type="password" name="client_secret" class="regular-text" autocomplete="new-password" required>');
        echo '</table>';
        submit_button(__('Add connection', 'thewpfeeds'));
        echo '</form>';
    }

    private function row(string $label, string $controlHtml): void
    {
        printf(
            '<tr><th scope="row">%s</th><td>%s</td></tr>',
            esc_html($label),
            $controlHtml // phpcs:ignore WordPress.Security.EscapeOutput -- control HTML escaped by builders above.
        );
    }

    private function authorize(string $nonceAction): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('You are not allowed to manage feeds.', 'thewpfeeds'));
        }

        check_admin_referer($nonceAction);
    }

    private function back(string $notice, string $message = '', string $tab = ''): never
    {
        wp_safe_redirect(add_query_arg(array_filter([
            'page' => self::SLUG,
            'tab' => $tab !== '' ? $tab : null,
            'thewpfeeds_notice' => $notice,
            'thewpfeeds_message' => $message !== '' ? rawurlencode($message) : null,
        ]), admin_url('admin.php')));

        exit;
    }
}
