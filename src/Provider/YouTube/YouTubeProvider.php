<?php

declare(strict_types=1);

namespace TheWPFeeds\Provider\YouTube;

use TheWPFeeds\Feed\Feed;
use TheWPFeeds\Item\ItemCollection;
use TheWPFeeds\Provider\FetchException;
use TheWPFeeds\Provider\ProviderInterface;
use TheWPFeeds\Provider\Rss\RssNormalizer;

/**
 * YouTube channel uploads via the keyless public Atom feed
 * (youtube.com/feeds/videos.xml). No API key, no quota, ~15 latest videos.
 * The Atom parsing is the shared RssNormalizer; thumbnails come from the
 * media:group entries (stable i.ytimg.com URLs).
 */
final class YouTubeProvider implements ProviderInterface
{
    public function __construct(private readonly RssNormalizer $normalizer)
    {
    }

    public function id(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return __('YouTube (channel)', 'thewpfeeds');
    }

    public function fetch(Feed $feed): ItemCollection
    {
        $channelId = trim((string) $feed->setting('channel_id', ''));

        if ($channelId === '') {
            throw new FetchException(esc_html__('YouTube channel ID is missing.', 'thewpfeeds'));
        }

        $url = 'https://www.youtube.com/feeds/videos.xml?' . http_build_query(
            str_starts_with($channelId, 'PL')
                ? ['playlist_id' => $channelId]
                : ['channel_id' => $channelId]
        );

        $response = wp_remote_get($url, ['timeout' => 15, 'limit_response_size' => 2 * MB_IN_BYTES]);

        if (is_wp_error($response)) {
            throw new FetchException(esc_html($response->get_error_message()));
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            throw new FetchException(esc_html__('YouTube feed not found — check the channel ID (it starts with "UC").', 'thewpfeeds'));
        }

        try {
            $items = $this->normalizer->normalize(wp_remote_retrieve_body($response), 'youtube');
        } catch (\RuntimeException $e) {
            throw new FetchException(esc_html($e->getMessage()));
        }

        return (new ItemCollection($items))->take($feed->count);
    }

    public function settingsFields(): array
    {
        return [
            'channel_id' => [
                'label' => __('Channel ID', 'thewpfeeds'),
                'type' => 'text',
                'help' => __('The UC… channel ID (youtube.com/channel/UC…), or a PL… playlist ID. Not the @handle.', 'thewpfeeds'),
                'required' => true,
            ],
        ];
    }
}
