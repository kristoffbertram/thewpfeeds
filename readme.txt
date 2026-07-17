=== The WP Feeds ===
Contributors: kristoffbertram
Tags: feeds, linkedin, youtube, rss, bluesky
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Developer-first external feeds — LinkedIn pages, YouTube channels, RSS and Bluesky — rendered with templates your theme owns.

== Description ==

The WP Feeds displays external feeds inside WordPress the way developers wish every feed plugin worked: **your theme owns the markup**. No vendor styling panels, no iframes, no third-party JavaScript on your pages.

Every provider — LinkedIn company pages, RSS/Atom, YouTube channels, Bluesky profiles — normalizes into one item model and renders through one template chain, overridable WooCommerce-style from your theme.

**For developers**

* A loop API: `thewpfeeds( 'my-feed' )` returns normalized item objects; `thewpfeeds_render( 'my-feed' )` runs the full template chain.
* Template overrides: copy `item.php` into `{your-theme}/thewpfeeds/` and edit. An item hierarchy (`item-{feed-slug}.php` → `item-{provider}.php` → `item.php`) gives per-feed and per-type markup with clean fallbacks.
* Custom layouts by convention: drop `layout-carousel.php` in your theme, pass `carousel` — no registration.
* Hooks for everything: providers, template resolution, cache refresh events.

**Performance & privacy by design**

* Rendering never blocks on a remote API: items are cached server-side, stale content is served instantly while a background refresh runs.
* Feed images are stored locally in your uploads dir (LinkedIn image URLs expire by design — hotlinking them breaks).
* Visitors' browsers never contact the source platforms. Content lives in your DOM: real SEO, no consent baggage.

**Providers**

* **LinkedIn (company page)** — via LinkedIn's official Community Management API with your own LinkedIn developer app; you connect as an admin of your own page.
* **RSS / Atom** — any feed URL; also covers Mastodon, subreddits, podcasts.
* **YouTube (channel)** — keyless public channel feed, no API key required.
* **Bluesky (profile)** — public API, no authentication.

The free version runs one feed with every feature included. [The WP Feeds Pro](https://wp.kristoffbertram.be) removes the feed limit. Full developer documentation: [wp.kristoffbertram.be/docs](https://wp.kristoffbertram.be/docs).

== External services ==

This plugin talks to external services only to fetch the feed content you configure:

* **LinkedIn API** (api.linkedin.com, www.linkedin.com/oauth) — only for feeds you configure with your own LinkedIn developer app; OAuth happens as your page admin. [Terms](https://www.linkedin.com/legal/l/api-terms-of-use), [Privacy](https://www.linkedin.com/legal/privacy-policy).
* **YouTube feed** (www.youtube.com/feeds) — only for configured YouTube feeds. [Terms](https://www.youtube.com/t/terms), [Privacy](https://policies.google.com/privacy).
* **Bluesky public API** (public.api.bsky.app) — only for configured Bluesky feeds. [Terms](https://bsky.social/about/support/tos), [Privacy](https://bsky.social/about/support/privacy-policy).
* **Any RSS/Atom URL you configure** is fetched from your server on your cache schedule.
* **License server** (wp.kristoffbertram.be) — contacted only if you enter a Pro license key, to validate it. No data is sent otherwise. [Privacy](https://wp.kristoffbertram.be/privacy), [Terms](https://wp.kristoffbertram.be/terms).

All fetching happens server-side on your cache schedule; site visitors never contact these services.

== Installation ==

1. Install and activate the plugin.
2. Go to **Feeds → Add feed**, pick a provider, and configure it (a feed URL, channel ID, handle — or a LinkedIn connection).
3. Add the **Feed** block to a page, or call `thewpfeeds_render( 'your-feed-slug' )` in your theme.

== Frequently Asked Questions ==

= Can it show any LinkedIn page? =

No — and no plugin honestly can. LinkedIn's official API only allows a page **admin** to read their own page's posts. Services that show arbitrary pages scrape, which breaks routinely and violates LinkedIn's terms. The WP Feeds uses the official API only.

= Why is there no X (Twitter) provider? =

X has no free read API and no RSS. We don't build on scraping. If that changes, we'll add it.

= How do I change the markup? =

Copy any template from the plugin's `templates/` folder into `{your-theme}/thewpfeeds/` and edit it. See the [template docs](https://wp.kristoffbertram.be/docs/templates).

= Does it slow my site down? =

No. Feeds are fetched in the background and served from a local cache; pages never wait on a remote API. Images are served from your own uploads directory.

== Changelog ==

= 1.0.0 =
* Initial release: LinkedIn (Community Management API), RSS/Atom, YouTube, and Bluesky providers.
* Template override chain with item hierarchy, custom layouts, loop API.
* Server-rendered Feed block, stale-while-revalidate caching, local image storage.
* WP-CLI commands (`wp thewpfeeds fetch`, `wp thewpfeeds status`).
