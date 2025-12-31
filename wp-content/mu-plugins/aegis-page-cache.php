<?php
/**
 * MU plugin: Simple HTML page cache for anonymous requests.
 */

if (defined('AEGIS_PAGE_CACHE_ENABLE') && AEGIS_PAGE_CACHE_ENABLE === false) {
    return;
}

if (!function_exists('add_action')) {
    return;
}

// Default TTL is 60 seconds unless overridden via constant.
if (!defined('AEGIS_PAGE_CACHE_TTL')) {
    define('AEGIS_PAGE_CACHE_TTL', 60);
}

$__aegis_cache_dir = WP_CONTENT_DIR . '/cache/aegis-page-cache';
$__aegis_cache_header_sent = false;

/**
 * Determine whether the current request should bypass caching.
 */
function aegis_page_cache_should_bypass(): bool
{
    // Only cache GET/HEAD requests.
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return true;
    }

    // Skip admin/REST/login/XML-RPC and requests with query strings.
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (is_admin() || str_starts_with($uri, '/wp-admin') || str_starts_with($uri, '/wp-login')) {
        return true;
    }
    if (str_contains($uri, '/wp-json') || str_contains($uri, '/xmlrpc.php')) {
        return true;
    }
    if (str_contains($uri, '?')) {
        return true;
    }

    // WooCommerce sensitive paths.
    $woo_paths = ['/cart', '/checkout', '/my-account', '/index.php/cart', '/index.php/checkout', '/index.php/my-account'];
    foreach ($woo_paths as $path) {
        if (str_starts_with($uri, $path)) {
            return true;
        }
    }

    // Logged-in or protected content cookies.
    $cookie_names = array_keys($_COOKIE ?? []);
    foreach ($cookie_names as $name) {
        if (str_starts_with($name, 'wordpress_logged_in_') || str_starts_with($name, 'wp-postpass_')) {
            return true;
        }
        if ($name === 'woocommerce_items_in_cart' || $name === 'woocommerce_cart_hash' || str_starts_with($name, 'wp_woocommerce_session_')) {
            return true;
        }
    }

    return false;
}

/**
 * Compute cache file path.
 */
function aegis_page_cache_path(string $cache_dir): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $key = md5($host . $uri);

    return trailingslashit($cache_dir) . $key . '.html';
}

/**
 * Send cache header only once.
 */
function aegis_page_cache_header(string $value): void
{
    global $__aegis_cache_header_sent;
    if ($__aegis_cache_header_sent) {
        return;
    }

    header('X-Aegis-Page-Cache: ' . $value);
    $__aegis_cache_header_sent = true;
}

/**
 * Serve cached response if available.
 */
function aegis_page_cache_try_serve(string $cache_dir): void
{
    $cache_file = aegis_page_cache_path($cache_dir);
    if (!file_exists($cache_file)) {
        return;
    }

    $ttl = (int) AEGIS_PAGE_CACHE_TTL;
    $expires_at = filemtime($cache_file) + $ttl;
    if ($expires_at < time()) {
        return;
    }

    aegis_page_cache_header('HIT');

    // Serve cached content and exit early.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }

    readfile($cache_file);
    exit;
}

/**
 * Register hooks to flush cache directory on content changes.
 */
function aegis_page_cache_register_flush_hooks(string $cache_dir): void
{
    $flush = static function () use ($cache_dir): void {
        aegis_page_cache_header('BYPASS');
        aegis_page_cache_clear_dir($cache_dir);
    };

    $hooks = [
        'save_post',
        'deleted_post',
        'edit_terms',
        'wp_update_nav_menu',
        'switch_theme',
        'activated_plugin',
        'deactivated_plugin',
    ];

    foreach ($hooks as $hook) {
        add_action($hook, $flush, 10, 0);
    }
}

/**
 * Recursively clear cache directory.
 */
function aegis_page_cache_clear_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            aegis_page_cache_clear_dir($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

// Early exit if cache is not applicable.
if (aegis_page_cache_should_bypass()) {
    aegis_page_cache_header('BYPASS');
    return;
}

// Prepare cache directory.
if (!is_dir($__aegis_cache_dir)) {
    wp_mkdir_p($__aegis_cache_dir);
}

// Attempt to serve cached file.
aegis_page_cache_try_serve($__aegis_cache_dir);

aegis_page_cache_header('MISS');

aegis_page_cache_register_flush_hooks($__aegis_cache_dir);

// Capture output for caching on shutdown.
$__aegis_cache_buffer = '';
ob_start(function ($buffer) use (&$__aegis_cache_buffer) {
    $__aegis_cache_buffer .= $buffer;
    return $buffer;
});

register_shutdown_function(function () use (&$__aegis_cache_buffer, $__aegis_cache_dir): void {
    if (empty($__aegis_cache_buffer)) {
        return;
    }

    // Only cache successful HTML responses for GET requests.
    if (http_response_code() !== 200) {
        return;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET') {
        return;
    }

    $content_type = '';
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            $content_type = trim(substr($header, strlen('Content-Type:')));
            break;
        }
    }

    if ($content_type && stripos($content_type, 'text/html') !== 0) {
        return;
    }

    $cache_file = aegis_page_cache_path($__aegis_cache_dir);
    if (!is_dir($__aegis_cache_dir)) {
        wp_mkdir_p($__aegis_cache_dir);
    }

    file_put_contents($cache_file, $__aegis_cache_buffer, LOCK_EX);
});

