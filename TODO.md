# TODO / decisions

## ADR: Paid tier = managed LinkedIn pipeline, not feed count (2026-07-22)

Feeds are unlimited in BOTH builds. What a validating license key buys is
`LicenseInterface::canUseProxy()` — routing LinkedIn fetches through the
vendor proxy (our approved LinkedIn app), so customers skip registering a
developer app. Decisions taken:

- `maxFeeds()`/`canCreateFeed()` dropped from `LicenseInterface` entirely
  (no back-compat pinning) — zero customers pre-launch, and `FreeLicense`
  deleted as dead code. The `freshet_feeds_license` filter contract changed
  with it.
- `UnlimitedLicense` (wp.org build) is `isPro() = true` but
  `canUseProxy() = false` — the proxy is paid and runs on our LinkedIn app
  quota; it must never leak into the directory build.
- `freshet_feeds_enable_proxy` filter default is now the license entitlement;
  the filter stays as a manual override in either direction.
- Proxy failures hard-fail (`FetchException` → provider serves stale cache).
  No automatic BYO fallback in v1.
- Proxy resolves image URNs to signed (expiring) URLs; the plugin keeps
  localising via `ImageStore` — never hotlink.
- `ProxyLinkedInClient` reads the license key via the option name literal
  (`freshet_feeds_license_key`), not `RemoteLicense::OPTION_KEY` — that class
  is stripped from the wp.org build and a filter-forced proxy there must not
  fatal.

## Proxy service (Part B server side — not built yet)

Blocked on LinkedIn app approval. Planned home: freshet.studio Symfony app,
served on `api.freshet.studio` (reuses its license repository for key
validation). Contract the plugin already speaks (same `{success, data?,
error?, error_code?}` envelope as the license server; key + site_url in the
JSON body):

- `POST /api/v1/linkedin/posts` `{key, site_url, organization, count}` →
  `data.elements` = raw LinkedIn `/rest/posts` elements (shape in
  `data/fixtures/linkedin-posts.json`).
- `POST /api/v1/linkedin/images` `{key, site_url, urns}` → `data.images` =
  map urn → `{url, width?, height?}` (signed URLs).
- Base URL overridable via the `FRESHET_FEEDS_PROXY_URL` constant
  (test/staging).

Open server-side decisions (brief's Decision 4): org↔license mapping — does a
key authorise any org, or only URN(s) recorded at onboarding? Define the
onboarding step before building the endpoints. Also: rate-limit per key.

Smoke test post-approval: `wp freshet-feeds fetch <slug> --force` against the
live proxy.
