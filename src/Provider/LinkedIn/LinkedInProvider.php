<?php

declare(strict_types=1);

namespace TheWPFeeds\Provider\LinkedIn;

use TheWPFeeds\Connection\ConnectionRepository;
use TheWPFeeds\Feed\Feed;
use TheWPFeeds\Item\ItemAuthor;
use TheWPFeeds\Item\ItemCollection;
use TheWPFeeds\Provider\ProviderInterface;

/**
 * LinkedIn company-page posts: client fetch → image URN resolution → normalization.
 */
final class LinkedInProvider implements ProviderInterface
{
    public function __construct(
        private readonly LinkedInClientInterface $client,
        private readonly PostNormalizer $normalizer,
        private readonly ConnectionRepository $connections,
    ) {
    }

    public function id(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return __('LinkedIn (company page)', 'thewpfeeds');
    }

    public function fetch(Feed $feed): ItemCollection
    {
        $orgId = (string) $feed->setting('organization_id', '');
        $orgUrn = str_starts_with($orgId, 'urn:') ? $orgId : 'urn:li:organization:' . $orgId;

        $posts = $this->client()->getOrganizationPosts($feed, $orgUrn, $feed->count);

        $imageUrns = [];
        foreach ($posts as $post) {
            [$urn] = $this->normalizer->imageUrn($post);
            if ($urn !== null) {
                $imageUrns[] = $urn;
            }
        }

        $imageUrlMap = $this->client()->resolveImages($feed, array_values(array_unique($imageUrns)));

        $organization = null;
        $orgName = (string) $feed->setting('organization_name', '');
        if ($orgName !== '') {
            $orgSlug = (string) $feed->setting('organization_slug', '');

            $organization = new ItemAuthor(
                name: $orgName,
                url: $orgSlug !== '' ? 'https://www.linkedin.com/company/' . rawurlencode($orgSlug) : null,
            );
        }

        $items = array_map(
            fn (array $post) => $this->normalizer->normalize($post, $imageUrlMap, $organization),
            $posts
        );

        return (new ItemCollection($items))->take($feed->count);
    }

    public function settingsFields(): array
    {
        return [
            'connection_id' => [
                'label' => __('LinkedIn connection', 'thewpfeeds'),
                'type' => 'connection',
                'required' => true,
            ],
            'organization_id' => [
                'label' => __('Organization ID', 'thewpfeeds'),
                'type' => 'text',
                'help' => __('The numeric ID of your company page (from its admin URL), e.g. 1441.', 'thewpfeeds'),
                'required' => true,
            ],
            'organization_name' => [
                'label' => __('Organization name', 'thewpfeeds'),
                'type' => 'text',
                'help' => __('Shown as the item author in templates.', 'thewpfeeds'),
            ],
            'organization_slug' => [
                'label' => __('Organization slug', 'thewpfeeds'),
                'type' => 'text',
                'help' => __('The company page slug, e.g. "acme-corp" for linkedin.com/company/acme-corp.', 'thewpfeeds'),
            ],
        ];
    }

    /**
     * Proxy mode is opt-in via filter until the vendor service exists.
     */
    private function client(): LinkedInClientInterface
    {
        /**
         * Enable the (future) vendor proxy client.
         *
         * @param bool $enabled Default false.
         */
        if (apply_filters('thewpfeeds_enable_proxy', false)) {
            return new ProxyLinkedInClient();
        }

        return $this->client;
    }
}
