# External Governance (P2)

This plugin provides a governance layer for external assets with whitelisting, logging, and optional safe enforcement/mirroring.

## Features
- Domain whitelist with three modes: off, log, enforce (default log).
- Integration with wla-observer: shows recent external domains seen in `wp-content/uploads/wla-logs/observe.log`.
- Mirroring framework for JS/CSS: add remote URLs, download to `uploads/wla-external-governance/`, and rewrite enqueued asset URLs to the cached copies when present.
- Enforce mode safely replaces non-whitelisted, non-critical analytics domains with a blank data URL to prevent outbound requests without breaking core rendering; other domains are logged and left untouched unless mirrored.
- Logging to `wp-content/uploads/wla-logs/external-governance.log` when non-whitelisted assets are encountered.

## Admin location
Tools → External Governance.

## Settings
- **Mode**
  - Off: no action.
  - Log: only log non-whitelisted assets.
  - Enforce: attempt to serve mirrored assets; otherwise log, and block only non-critical analytics domains with a harmless stub.
- **Whitelist**: one domain per line. The site host is added automatically when empty.
- **Mirrors**: add remote JS/CSS URLs, resync, or clear cache. Files are stored in uploads/wla-external-governance/.

## Usage
1. Go to Tools → External Governance and review observed domains from wla-observer logs.
2. Add domains to the whitelist as needed and save.
3. (Optional) Add JS/CSS URLs to mirror locally and click **Add & Sync**. Use **Resync all** to refresh downloads or **Clear cache** to remove local copies.
4. Switch mode to **Enforce** when ready. In enforce mode, mirrored assets are served locally; analytics domains like Google Analytics/Tag Manager are stubbed to avoid outbound requests.

## Acceptance
- Admin page lists recent observed external domains.
- Log mode records new non-whitelisted assets to `external-governance.log` without altering page output.
- Enforce mode replaces at least one non-whitelisted external resource (analytics or mirrored asset) without breaking the page.

## Rollback
Deactivate the **WLA External Governance** plugin (no MU plugin is used). Cached files remain in uploads/wla-external-governance/ and can be removed manually if desired.
