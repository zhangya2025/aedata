# Aegis Page Cache (MU) - Anonymous HTML File Cache

## Goal
Reduce TTFB by serving cached HTML for anonymous browsing before Elementor/Pro/The7 initialize.

This is designed as an engineering tradeoff:
- Prioritize speed for anonymous browsing.
- Do not cache any request that may be personalized (login/session/cart/cookies).

## Files
- `wp-content/mu-plugins/aegis-page-cache.php`

## Enable / Disable
Default is enabled.

You can override in `wp-config.php`:
```php
define('AEGIS_PAGE_CACHE_ENABLE', true); // set false to disable
define('AEGIS_PAGE_CACHE_TTL', 60);      // seconds
```

## Cache rules (high level)
Cache only when ALL are true:
- Method is GET/HEAD
- No query string
- No cookies at all (conservative)
- Not REST (`/wp-json`)
- Not admin/auth/cron/xmlrpc
- Not Woo critical paths: cart/checkout/my-account
- No Authorization header

Cache is stored at:
`wp-content/cache/aegis-page-cache/<md5(host|path)>.html`

## Debug header
Response will include:
- `X-Aegis-Page-Cache: HIT|MISS|BYPASS:<reason>`

## Verification commands
1) Home should become HIT on second request:
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

4) Timing validation (using existing nginx timing.json):
- Compare `/` upstream_response_time on HIT vs MISS.

## Rollback
- Set `AEGIS_PAGE_CACHE_ENABLE` to false, or delete `wp-content/mu-plugins/aegis-page-cache.php`
- Optionally delete `wp-content/cache/aegis-page-cache/` directory.
