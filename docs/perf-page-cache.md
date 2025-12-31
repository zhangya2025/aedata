# Aegis Page Cache (MU) - Anonymous HTML File Cache

## Goal
Reduce TTFB by serving cached HTML for anonymous browsing before Elementor/Pro/The7 initialize.

Engineering tradeoff:
- Prioritize speed for anonymous browsing.
- Keep login/session/cart traffic uncached.

## Files
- `wp-content/mu-plugins/aegis-page-cache.php`

## Enable / Disable
Default enabled.

Override in `wp-config.php`:
```php
define('AEGIS_PAGE_CACHE_ENABLE', true); // set false to disable
define('AEGIS_PAGE_CACHE_TTL', 600);     // seconds
```

## Cache rules (high level)
Cache only when ALL are true:
- Method is GET/HEAD
- No query string
- No sensitive cookies (`wordpress_logged_in_*`, `wp-postpass_*`, `wp_woocommerce_session_*`, `woocommerce_items_in_cart`, `woocommerce_cart_hash`)
- Not REST (`/wp-json`)
- Not admin/auth/cron/xmlrpc
- Not Woo critical paths: cart/checkout/my-account (and their index.php variants)
- No Authorization header

Cache is stored at:
`wp-content/cache/aegis-page-cache/<md5(host|path)>.html` (path-only key to maximize hits across host aliases)

## Debug header
Response includes `X-Aegis-Page-Cache: HIT|MISS|BYPASS:<reason>`.

## Verification commands
1) Home should HIT on second request:
```bash
curl -I https://www.aegismax.com/ | grep -i X-Aegis-Page-Cache
curl -I https://www.aegismax.com/ | grep -i X-Aegis-Page-Cache
```

2) Woo critical paths must always BYPASS:
```bash
curl -I https://www.aegismax.com/cart/ | grep -i X-Aegis-Page-Cache
curl -I https://www.aegismax.com/checkout/ | grep -i X-Aegis-Page-Cache
curl -I https://www.aegismax.com/my-account/ | grep -i X-Aegis-Page-Cache
```

3) REST must always BYPASS:
```bash
curl -I https://www.aegismax.com/index.php/wp-json/ | grep -i X-Aegis-Page-Cache
```

4) Timing validation (nginx timing.json):
- Before: MISS upstream_response_time ~3.5–4.3s (prior observations).
- Previous HIT still went upstream ~0.39–0.46s; new target for HIT is sub-50ms.
- After deploy: sample `/` HIT entries (>=3 samples) should show upstream_response_time near-zero and clearly below 0.39s; document the measured HIT/MISS averages here.

## Rollback
- Set `AEGIS_PAGE_CACHE_ENABLE` to false, or delete `wp-content/mu-plugins/aegis-page-cache.php`.
- Optionally delete `wp-content/cache/aegis-page-cache/` directory.
