<?php

declare(strict_types=1);

namespace TheWPFeeds\Provider\LinkedIn;

use TheWPFeeds\Feed\Feed;
use TheWPFeeds\Provider\FetchException;

/**
 * Future vendor proxy: the plugin will call api.thewpfeeds.com (which holds
 * the approved LinkedIn app) authenticated by the site's license key, so
 * customers skip the LinkedIn developer-app dance entirely.
 *
 * v1 ships this as a stub to prove the LinkedInClientInterface seam. It is
 * only selectable when the `thewpfeeds_enable_proxy` filter returns true.
 */
final class ProxyLinkedInClient implements LinkedInClientInterface
{
    public function getOrganizationPosts(Feed $feed, string $orgUrn, int $count): array
    {
        throw new FetchException(
            esc_html__('The WP Feeds proxy service is not available yet — use a bring-your-own LinkedIn app connection.', 'thewpfeeds')
        );
    }

    public function resolveImages(Feed $feed, array $imageUrns): array
    {
        return [];
    }
}
