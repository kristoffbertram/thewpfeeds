<?php

declare(strict_types=1);

namespace FreshetFeeds\Provider\LinkedIn;

use FreshetFeeds\Feed\Feed;
use FreshetFeeds\Provider\FetchException;

/**
 * Future vendor proxy: the plugin will call api.freshet-feeds.com (which holds
 * the approved LinkedIn app) authenticated by the site's license key, so
 * customers skip the LinkedIn developer-app dance entirely.
 *
 * v1 ships this as a stub to prove the LinkedInClientInterface seam. It is
 * only selectable when the `freshet_feeds_enable_proxy` filter returns true.
 */
final class ProxyLinkedInClient implements LinkedInClientInterface
{
    public function getOrganizationPosts(Feed $feed, string $orgUrn, int $count): array
    {
        throw new FetchException(
            esc_html__('Freshet Feeds proxy service is not available yet — use a bring-your-own LinkedIn app connection.', 'freshet-feeds')
        );
    }

    public function resolveImages(Feed $feed, array $imageUrns): array
    {
        return [];
    }
}
