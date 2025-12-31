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
`wp-content/cache/aegis-page-cache/<md5(path)>.html` (path-only key to avoid host/port splits)

## Debug header
Response includes `X-Aegis-Page-Cache: HIT|MISS|BYPASS:<reason>`.

## Verification commands (use GET)
1) Home should HIT on second request (GET, not `-I`):
```bash
curl -s -D - https://www.aegismax.com/ -o /dev/null | grep -i X-Aegis-Page-Cache
curl -s -D - https://www.aegismax.com/ -o /dev/null | grep -i X-Aegis-Page-Cache
```

2) Woo critical paths must always BYPASS:
```bash
curl -s -D - https://www.aegismax.com/cart/ -o /dev/null | grep -i X-Aegis-Page-Cache
curl -s -D - https://www.aegismax.com/checkout/ -o /dev/null | grep -i X-Aegis-Page-Cache
curl -s -D - https://www.aegismax.com/my-account/ -o /dev/null | grep -i X-Aegis-Page-Cache
```

3) REST must always BYPASS:
```bash
curl -s -D - https://www.aegismax.com/index.php/wp-json/ -o /dev/null | grep -i X-Aegis-Page-Cache
```

4) Timing validation (nginx timing.json):
- Before: MISS upstream_response_time ~3.5–4.3s (prior observations).
- Previous HIT still went upstream ~0.39–0.46s; target for HIT is now “well below 0.4s” (ideally tens of milliseconds). Use timing.json samples (>=3 HIT entries) to confirm.
- Document measured MISS/HIT averages after deployment.

## Rollback
- Set `AEGIS_PAGE_CACHE_ENABLE` to false, or delete `wp-content/mu-plugins/aegis-page-cache.php`.
- Optionally delete `wp-content/cache/aegis-page-cache/` directory.

## Optional: Nginx static short-circuit (not auto-enabled)
If server config changes are allowed, you can bypass PHP for cached hits. Example with lua module to mirror the md5(path) key:
```nginx
lua_shared_dict dummy 1k;
location / {
    set $aegis_cache_dir /path/to/site/wp-content/cache/aegis-page-cache;
    set_by_lua_block $aegis_cache_key { return ngx.md5(ngx.var.uri); }
    set $aegis_cache_file $aegis_cache_dir/$aegis_cache_key.html;

    if (-f $aegis_cache_file) {
        add_header X-Aegis-Page-Cache HIT;
        return 200 $aegis_cache_file;
    }

    try_files $uri $uri/ /index.php?$args;
}
```
Ensure Woo/login/REST bypass conditions mirror the MU plugin if enabling this.
