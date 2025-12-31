<?php
/**
 * MU Plugin: Aegis Page Cache (HTML file cache for anonymous requests)
 *
 * Goal:
 * - Reduce TTFB by serving cached HTML before Elementor/Pro/The7 initialize.
 * - Keep Woo critical flows and any logged-in/session traffic BYPASS.
 *
 * Notes:
 * - This is an engineering tradeoff: prioritize speed for anonymous browsing.
 * - Not designed for long TTL; default TTL is short to reduce staleness risk.
 */

defined('ABSPATH') || exit;

// Master switch (override in wp-config.php).
if (!defined('AEGIS_PAGE_CACHE_ENABLE')) {
    define('AEGIS_PAGE_CACHE_ENABLE', true);
}

// Default TTL in seconds (override in wp-config.php).
if (!defined('AEGIS_PAGE_CACHE_TTL')) {
    define('AEGIS_PAGE_CACHE_TTL', 60);
}

// Debug header switch.
if (!defined('AEGIS_PAGE_CACHE_DEBUG_HEADER')) {
    define('AEGIS_PAGE_CACHE_DEBUG_HEADER', true);
}

// Do not run on CLI.
if ((PHP_SAPI === 'cli') || (defined('WP_CLI') && WP_CLI)) {
    return;
}

if (!AEGIS_PAGE_CACHE_ENABLE) {
    return;
}

/**
 * Safe header setter.
 */
function aegis_page_cache_header($name, $value) {
    if (!AEGIS_PAGE_CACHE_DEBUG_HEADER) {
        return;
    }
    if (!headers_sent()) {
        header($name . ': ' . $value);
    }
}

/**
 * Basic request context.
 */
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
$host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

// Only cache GET/HEAD.
if ($method !== 'GET' && $method !== 'HEAD') {
    aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:method');
    return;
}

// Do not cache if query string exists (conservative).
if (strpos($uri, '?') !== false) {
    aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:query');
    return;
}

$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}
$path_l = strtolower($path);

// Never cache admin/auth/cron/xmlrpc.
$bypass_prefixes = [
    '/wp-admin',
    '/wp-login.php',
    '/xmlrpc.php',
    '/wp-cron.php',
];
foreach ($bypass_prefixes as $p) {
    if (strpos($path_l, $p) === 0) {
        aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:core');
        return;
    }
}

// Never cache REST.
if (strpos($path_l, '/wp-json') !== false) {
    aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:rest');
    return;
}

// Woo critical paths should never be cached.
$woo_critical = '#(^|/index\.php/)(cart|checkout|my-account)(/|$)#i';
if (preg_match($woo_critical, $path)) {
    aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:woo');
    return;
}

// If Authorization header exists, bypass.
if (!empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:auth');
    return;
}

// Cookie-based bypass (conservative).
$cookie = isset($_SERVER['HTTP_COOKIE']) ? (string) $_SERVER['HTTP_COOKIE'] : '';
if ($cookie !== '') {
    // Logged-in or password-protected.
    if (strpos($cookie, 'wordpress_logged_in_') !== false || strpos($cookie, 'wp-postpass_') !== false) {
        aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:login');
        return;
    }
    // Woo cart/session cookies.
    if (strpos($cookie, 'woocommerce_items_in_cart') !== false ||
        strpos($cookie, 'woocommerce_cart_hash') !== false ||
        strpos($cookie, 'wp_woocommerce_session_') !== false) {
        aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:woo-cookie');
        return;
    }
    // Any other cookies: bypass for safety (reduce hit rate, maximize correctness).
    aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:cookie');
    return;
}

// Build cache paths.
$content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (ABSPATH . 'wp-content');
$cache_dir   = rtrim($content_dir, '/\\') . '/cache/aegis-page-cache';

// Key: host + path (scheme-insensitive, conservative).
$key        = md5($host . '|' . $path);
$cache_file = $cache_dir . '/' . $key . '.html';

// HIT path.
if (is_file($cache_file)) {
    $age = time() - (int) @filemtime($cache_file);
    if ($age >= 0 && $age <= (int) AEGIS_PAGE_CACHE_TTL) {
        aegis_page_cache_header('X-Aegis-Page-Cache', 'HIT');
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        if ($method === 'HEAD') {
            exit;
        }
        // Read and output.
        $fp = @fopen($cache_file, 'rb');
        if ($fp) {
            fpassthru($fp);
            fclose($fp);
        } else {
            // Fallback.
            echo (string) @file_get_contents($cache_file);
        }
        exit;
    }
}

// MISS: start buffering and cache at shutdown if eligible.
aegis_page_cache_header('X-Aegis-Page-Cache', 'MISS');

// Ensure cache dir exists (do not fail request if it cannot be created).
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0775, true);
}

// Capture output early; write at shutdown.
if (!defined('AEGIS_PAGE_CACHE_OB_STARTED')) {
    define('AEGIS_PAGE_CACHE_OB_STARTED', true);
    ob_start();
}

/**
 * Determine if response is cacheable based on headers/status.
 */
function aegis_page_cache_is_cacheable_response() {
    $code = function_exists('http_response_code') ? (int) http_response_code() : 200;
    if ($code !== 200) {
        return false;
    }
    if (headers_sent()) {
        // Still may be cacheable, but if headers already sent we cannot reliably check Set-Cookie.
        // Be conservative.
        return false;
    }
    $headers = headers_list();
    $has_html = false;
    foreach ($headers as $h) {
        $hl = strtolower($h);
        if (strpos($hl, 'set-cookie:') === 0) {
            return false;
        }
        if (strpos($hl, 'content-type:') === 0 && strpos($hl, 'text/html') !== false) {
            $has_html = true;
        }
        if (strpos($hl, 'cache-control:') === 0 && strpos($hl, 'private') !== false) {
            return false;
        }
    }
    // If no explicit Content-Type header, WordPress will usually set it; be conservative and require it.
    return $has_html;
}

register_shutdown_function(function () use ($cache_file, $cache_dir) {
    // Only write if buffering is still available.
    if (!defined('AEGIS_PAGE_CACHE_OB_STARTED') || !AEGIS_PAGE_CACHE_OB_STARTED) {
        return;
    }

    // If response not cacheable, do nothing.
    if (!aegis_page_cache_is_cacheable_response()) {
        return;
    }

    $html = ob_get_contents();
    if (!is_string($html) || $html === '') {
        return;
    }

    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0775, true);
    }

    // Atomic write: temp file + rename.
    $tmp = $cache_file . '.tmp.' . getmypid();
    $ok = @file_put_contents($tmp, $html, LOCK_EX);
    if ($ok !== false) {
        @rename($tmp, $cache_file);
        @chmod($cache_file, 0664);
    } else {
        @unlink($tmp);
    }
});

/**
 * Flush cache directory (simple full flush).
 */
function aegis_page_cache_flush_all() {
    $content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (ABSPATH . 'wp-content');
    $dir = rtrim($content_dir, '/\\') . '/cache/aegis-page-cache';
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $p = $file->getPathname();
        if ($file->isDir()) {
            @rmdir($p);
        } else {
            @unlink($p);
        }
    }
}

// Conservative invalidation hooks.
add_action('save_post', 'aegis_page_cache_flush_all', 10);
add_action('deleted_post', 'aegis_page_cache_flush_all', 10);
add_action('trashed_post', 'aegis_page_cache_flush_all', 10);
add_action('edited_terms', 'aegis_page_cache_flush_all', 10);
add_action('delete_term', 'aegis_page_cache_flush_all', 10);
add_action('wp_update_nav_menu', 'aegis_page_cache_flush_all', 10);
add_action('switch_theme', 'aegis_page_cache_flush_all', 10);
add_action('activated_plugin', 'aegis_page_cache_flush_all', 10);
add_action('deactivated_plugin', 'aegis_page_cache_flush_all', 10);
