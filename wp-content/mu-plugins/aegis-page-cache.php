<?php
/**
 * MU Plugin: Aegis Page Cache (HTML file cache for anonymous requests)
 *
 * Goal:
 * - Reduce TTFB by serving cached HTML before Elementor/Pro/The7 initialize.
 * - Keep Woo critical flows and any logged-in/session traffic BYPASS.
 */

defined('ABSPATH') || exit;

// Master switch (override in wp-config.php).
if (!defined('AEGIS_PAGE_CACHE_ENABLE')) {
    define('AEGIS_PAGE_CACHE_ENABLE', true);
}

// Default TTL in seconds (override in wp-config.php).
if (!defined('AEGIS_PAGE_CACHE_TTL')) {
    define('AEGIS_PAGE_CACHE_TTL', 600);
}

// Debug header switch.
if (!defined('AEGIS_PAGE_CACHE_DEBUG_HEADER')) {
    define('AEGIS_PAGE_CACHE_DEBUG_HEADER', true);
}

// Do not run on CLI or when disabled.
if ((PHP_SAPI === 'cli') || (defined('WP_CLI') && WP_CLI) || !AEGIS_PAGE_CACHE_ENABLE) {
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

// Basic request context.
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
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

// Cookie-based bypass: only block known sensitive cookies.
$cookie = isset($_SERVER['HTTP_COOKIE']) ? (string) $_SERVER['HTTP_COOKIE'] : '';
if ($cookie !== '') {
    $sensitive_cookies = [
        'wordpress_logged_in_',
        'wp-postpass_',
        'wp_woocommerce_session_',
        'woocommerce_items_in_cart',
        'woocommerce_cart_hash',
    ];
    foreach ($sensitive_cookies as $needle) {
        if (strpos($cookie, $needle) !== false) {
            aegis_page_cache_header('X-Aegis-Page-Cache', 'BYPASS:cookie');
            return;
        }
    }
}

// Build cache paths.
$content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (ABSPATH . 'wp-content');
$cache_dir   = rtrim($content_dir, '/\\') . '/cache/aegis-page-cache';

// Key: path-only to avoid host/port splits.
$key        = md5($path);
$cache_file = $cache_dir . '/' . $key . '.html';

// HIT path (must exit early, before any WP init).
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
        @readfile($cache_file);
        exit;
    }
}

// MISS path: start buffering and write cache from handler.
aegis_page_cache_header('X-Aegis-Page-Cache', 'MISS');
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0775, true);
}

if (!defined('AEGIS_PAGE_CACHE_OB_STARTED')) {
    define('AEGIS_PAGE_CACHE_OB_STARTED', true);

    ob_start(function ($buffer) use ($cache_file, $cache_dir) {
        static $written = false;

        if ($written || !is_string($buffer) || $buffer === '') {
            return $buffer;
        }

        $code = function_exists('http_response_code') ? (int) http_response_code() : 200;
        if ($code !== 200) {
            return $buffer;
        }

        $headers          = headers_list();
        $has_content_type = false;
        $is_html          = false;
        foreach ($headers as $h) {
            $hl = strtolower($h);
            if (strpos($hl, 'set-cookie:') === 0) {
                return $buffer; // personalized response
            }
            if (strpos($hl, 'content-type:') === 0) {
                $has_content_type = true;
                if (strpos($hl, 'text/html') !== false) {
                    $is_html = true;
                }
            }
            if (strpos($hl, 'cache-control:') === 0 && strpos($hl, 'private') !== false) {
                return $buffer;
            }
        }

        if ($has_content_type && !$is_html) {
            return $buffer; // not HTML
        }

        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0775, true);
        }

        $tmp = $cache_file . '.tmp.' . getmypid();
        $ok  = @file_put_contents($tmp, $buffer, LOCK_EX);
        if ($ok !== false) {
            @rename($tmp, $cache_file);
            @chmod($cache_file, 0664);
            $written = true;
        } else {
            @unlink($tmp);
        }

        return $buffer;
    });
}

/**
 * Flush cache directory (simple full flush).
 */
function aegis_page_cache_flush_all() {
    $content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (ABSPATH . 'wp-content');
    $dir         = rtrim($content_dir, '/\\') . '/cache/aegis-page-cache';
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
