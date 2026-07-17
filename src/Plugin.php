<?php

declare(strict_types=1);

namespace TheWPFeeds;

use TheWPFeeds\Admin\FeedsPage;
use TheWPFeeds\Admin\OAuthController;
use TheWPFeeds\Auth\LinkedInOAuth;
use TheWPFeeds\Blocks\FeedBlock;
use TheWPFeeds\Cache\ImageStore;
use TheWPFeeds\Cache\ItemCache;
use TheWPFeeds\Cli\FetchCommand;
use TheWPFeeds\Connection\ConnectionRepository;
use TheWPFeeds\Connection\TokenStore;
use TheWPFeeds\Feed\Feed;
use TheWPFeeds\Feed\FeedRepository;
use TheWPFeeds\Fetch\Cron;
use TheWPFeeds\Fetch\FeedRunner;
use TheWPFeeds\Fetch\FetchLock;
use TheWPFeeds\Admin\LicenseSection;
use TheWPFeeds\Item\ItemCollection;
use TheWPFeeds\License\LicenseClient;
use TheWPFeeds\License\LicenseInterface;
use TheWPFeeds\License\RemoteLicense;
use TheWPFeeds\License\UpdateChecker;
use TheWPFeeds\Provider\LinkedIn\ByoLinkedInClient;
use TheWPFeeds\Provider\LinkedIn\LinkedInProvider;
use TheWPFeeds\Provider\LinkedIn\PostNormalizer;
use TheWPFeeds\Provider\MockProvider;
use TheWPFeeds\Provider\ProviderRegistry;
use TheWPFeeds\Rest\FeedsController;
use TheWPFeeds\Template\TemplateLoader;

final class Plugin
{
    private static ?self $instance = null;

    private LicenseInterface $license;
    private FeedRepository $feeds;
    private ProviderRegistry $providers;
    private ItemCache $cache;
    private FeedRunner $runner;
    private Cron $cron;
    private TemplateLoader $templates;
    private ConnectionRepository $connections;
    private LinkedInOAuth $oauth;

    public static function boot(): self
    {
        return self::$instance ??= new self();
    }

    public static function instance(): self
    {
        return self::boot();
    }

    private function __construct()
    {
        $licenseClient = new LicenseClient();

        /**
         * Filter the active license implementation. RemoteLicense behaves as
         * the free tier (1 feed) until a key is entered and validates against
         * the license server.
         *
         * @param LicenseInterface $license
         */
        $this->license = apply_filters('thewpfeeds_license', new RemoteLicense($licenseClient));

        $this->feeds = new FeedRepository($this->license);
        $this->cache = new ItemCache();
        $this->providers = new ProviderRegistry();
        $this->connections = new ConnectionRepository(new TokenStore());
        $this->oauth = new LinkedInOAuth($this->connections);
        $this->runner = new FeedRunner($this->providers, $this->cache, new ImageStore(), new FetchLock());
        $this->cron = new Cron($this->feeds, $this->cache, $this->runner);
        $this->templates = new TemplateLoader(THEWPFEEDS_DIR . 'templates');

        add_action('init', [$this, 'onInit']);
        add_action('rest_api_init', fn () => (new FeedsController($this->feeds))->registerRoutes());

        $licenseSection = new LicenseSection($licenseClient, $this->license);

        $this->cron->hooks();
        (new FeedsPage($this->feeds, $this->providers, $this->connections, $this->cache, $this->runner, $this->license, $licenseSection))->hooks();
        (new OAuthController($this->oauth))->hooks();
        $licenseSection->hooks();
        (new UpdateChecker($licenseClient))->hooks();

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('thewpfeeds', new FetchCommand($this->feeds, $this->runner, $this->cache));
        }
    }

    public function onInit(): void
    {
        FeedRepository::registerPostType();
        $this->registerProviders();
        (new FeedBlock())->register();
    }

    private function registerProviders(): void
    {
        $normalizer = new PostNormalizer();
        $rssNormalizer = new \TheWPFeeds\Provider\Rss\RssNormalizer();

        $this->providers->register(new LinkedInProvider(
            new ByoLinkedInClient($this->connections),
            $normalizer,
            $this->connections,
        ));

        $this->providers->register(new \TheWPFeeds\Provider\Rss\RssProvider($rssNormalizer));
        $this->providers->register(new \TheWPFeeds\Provider\YouTube\YouTubeProvider($rssNormalizer));
        $this->providers->register(new \TheWPFeeds\Provider\Bluesky\BlueskyProvider(
            new \TheWPFeeds\Provider\Bluesky\BlueskyNormalizer(),
        ));

        $this->providers->register(new MockProvider(
            $normalizer,
            THEWPFEEDS_DIR . 'data/fixtures/linkedin-posts.json',
        ));

        /**
         * Register additional feed providers.
         *
         * @param ProviderRegistry $registry
         */
        do_action('thewpfeeds_register_providers', $this->providers);
    }

    /**
     * Loop API backing: serve cached items (stale is fine — a background
     * refresh gets queued), fetching synchronously only on a cold cache.
     */
    public function items(string $feedSlug): ItemCollection
    {
        $feed = $this->feeds->findBySlug($feedSlug);

        if ($feed === null) {
            return new ItemCollection();
        }

        $cached = $this->cache->get($feed);

        if ($cached === null) {
            // Cold start: the only path that blocks on the remote API.
            return ($this->runner->run($feed) ?? new ItemCollection())->take($feed->count);
        }

        if (!$this->cache->isFresh($feed)) {
            $this->cron->scheduleRefresh($feed->id);
        }

        return $cached->take($feed->count);
    }

    /** @param array{layout?: string, count?: int, wrapper_attributes?: string} $args */
    public function render(string $feedSlug, array $args = []): void
    {
        $feed = $this->feeds->findBySlug($feedSlug);

        if ($feed === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                printf(
                    '<!-- thewpfeeds: unknown feed "%s" -->',
                    esc_html($feedSlug)
                );
            }

            return;
        }

        $items = $this->items($feedSlug);

        if (isset($args['count']) && $args['count'] > 0) {
            $items = $items->take($args['count']);
        }

        $layout = $args['layout'] ?? '';
        $layout = $layout !== '' ? $layout : $feed->defaultLayout;

        $template = $items->isEmpty() ? 'empty' : 'feed';

        $this->templates->render($template, [
            'feed' => $feed,
            'items' => $items,
            'layout' => $layout,
            'args' => $args,
        ]);
    }

    public function templates(): TemplateLoader
    {
        return $this->templates;
    }

    public function feeds(): FeedRepository
    {
        return $this->feeds;
    }

    public function providerRegistry(): ProviderRegistry
    {
        return $this->providers;
    }

    public function itemCache(): ItemCache
    {
        return $this->cache;
    }

    public function feedRunner(): FeedRunner
    {
        return $this->runner;
    }

    public function license(): LicenseInterface
    {
        return $this->license;
    }

    public function connections(): ConnectionRepository
    {
        return $this->connections;
    }

    public function oauth(): LinkedInOAuth
    {
        return $this->oauth;
    }
}
