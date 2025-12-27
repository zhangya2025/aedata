<?php
/**
 * Plugin Name: WLA CN Guard
 * Description: Adds China-friendly fallbacks: lazy-load Google Maps on the frontend and tighten Elementor external request timeouts with optional stubbing.
 * Version: 1.0.0
 * Author: Wind Local Assets
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Determine if the guard is enabled.
 */
function wla_cn_guard_enabled() {
    if ( defined( 'WLA_CN_GUARD_ENABLED' ) ) {
        return (bool) WLA_CN_GUARD_ENABLED;
    }

    return true;
}

/**
 * Google Maps output buffering and replacement.
 */
function wla_cn_guard_template_buffer() {
    if ( is_admin() || ! wla_cn_guard_enabled() ) {
        return;
    }

    if ( is_feed() || is_robots() ) {
        return;
    }

    add_action( 'shutdown', 'wla_cn_guard_end_buffer', 0 );
    ob_start( 'wla_cn_guard_buffer_callback' );
}
add_action( 'template_redirect', 'wla_cn_guard_template_buffer' );

/**
 * End output buffering safely.
 */
function wla_cn_guard_end_buffer() {
    if ( ob_get_level() > 0 ) {
        ob_end_flush();
    }
}

/**
 * Replace Google Maps script tags with lazy loader or blocker.
 *
 * @param string $html Page HTML.
 * @return string
 */
function wla_cn_guard_buffer_callback( $html ) {
    if ( stripos( $html, '<html' ) === false || stripos( $html, 'maps.googleapis.com/maps/api/js' ) === false ) {
        return $html;
    }

    $mode = defined( 'WLA_CN_GUARD_MAPS_MODE' ) ? strtolower( (string) WLA_CN_GUARD_MAPS_MODE ) : 'lazy';

    if ( ! in_array( $mode, [ 'off', 'lazy', 'block' ], true ) ) {
        $mode = 'lazy';
    }

    if ( 'off' === $mode ) {
        return $html;
    }

    $pattern = '#<script[^>]+src=["\']([^"\']*maps\.googleapis\.com/maps/api/js[^"\']*)["\'][^>]*>\s*</script>#i';

    $html = preg_replace_callback(
        $pattern,
        function ( $matches ) use ( $mode ) {
            $src = $matches[1];

            if ( 'block' === $mode ) {
                return '<!-- WLA CN Guard blocked Google Maps -->';
            }

            $placeholder_id = 'wla-cn-guard-map-' . substr( sha1( $src . mt_rand() ), 0, 8 );
            $button_label   = esc_html__( '加载地图', 'wla-cn-guard' );
            $src_attr       = esc_attr( $src );

            $script = "<div class=\"wla-cn-guard-map-placeholder\" id=\"{$placeholder_id}\"><button type=\"button\" data-map-src=\"{$src_attr}\">{$button_label}</button></div>";
            $script .= '<script>(function(){var p=document.getElementById("' . $placeholder_id . '");if(!p)return;var b=p.querySelector("button");if(!b)return;var loaded=false;b.addEventListener("click",function(){if(loaded)return;loaded=true;var s=document.createElement("script");s.src=b.getAttribute("data-map-src");s.async=true;document.head.appendChild(s);});})();</script>';

            return $script;
        },
        $html
    );

    return $html;
}

/**
 * Tighten Elementor remote requests for China networks.
 */
function wla_cn_guard_http_args( $args, $url ) {
    if ( ! wla_cn_guard_enabled() ) {
        return $args;
    }

    $host = wla_cn_guard_parse_host( $url );

    if ( ! $host || ! wla_cn_guard_is_elementor_host( $host ) ) {
        return $args;
    }

    $timeout_limit              = defined( 'WLA_CN_GUARD_HTTP_TIMEOUT' ) ? (float) WLA_CN_GUARD_HTTP_TIMEOUT : 4.0;
    $args['timeout']            = min( $timeout_limit, isset( $args['timeout'] ) ? (float) $args['timeout'] : $timeout_limit );
    $args['redirection']        = isset( $args['redirection'] ) ? min( (int) $args['redirection'], 1 ) : 0;
    $args['reject_unsafe_urls'] = true;

    return $args;
}
add_filter( 'http_request_args', 'wla_cn_guard_http_args', 10, 2 );

/**
 * Optional short-circuit for Elementor hosts to avoid long waits.
 */
function wla_cn_guard_pre_http_request( $preempt, $args, $url ) {
    if ( ! wla_cn_guard_enabled() ) {
        return $preempt;
    }

    $host = wla_cn_guard_parse_host( $url );

    if ( ! $host || ! wla_cn_guard_is_elementor_host( $host ) ) {
        return $preempt;
    }

    $mode = defined( 'WLA_CN_GUARD_ELEMENTOR_MODE' ) ? strtolower( (string) WLA_CN_GUARD_ELEMENTOR_MODE ) : 'throttle';

    if ( ! in_array( $mode, [ 'off', 'throttle', 'stub', 'block' ], true ) ) {
        $mode = 'throttle';
    }

    if ( 'off' === $mode ) {
        return $preempt;
    }

    if ( 'block' === $mode ) {
        return new WP_Error( 'wla_cn_guard_blocked', __( 'Request blocked by WLA CN Guard.', 'wla-cn-guard' ) );
    }

    if ( 'stub' === $mode && 'assets.elementor.com' === $host && 'GET' === strtoupper( $args['method'] ?? 'GET' ) ) {
        return [
            'headers'  => [],
            'body'     => '',
            'response' => [
                'code'    => 204,
                'message' => 'No Content',
            ],
            'cookies'  => [],
            'filename' => null,
        ];
    }

    return $preempt;
}
add_filter( 'pre_http_request', 'wla_cn_guard_pre_http_request', 10, 3 );

/**
 * Parse host from URL.
 *
 * @param string $url
 * @return string|null
 */
function wla_cn_guard_parse_host( $url ) {
    $parts = wp_parse_url( $url );

    if ( empty( $parts['host'] ) ) {
        return null;
    }

    return strtolower( $parts['host'] );
}

/**
 * Check if a host belongs to Elementor endpoints we want to protect.
 *
 * @param string $host
 * @return bool
 */
function wla_cn_guard_is_elementor_host( $host ) {
    $hosts = [ 'assets.elementor.com', 'my.elementor.com' ];

    return in_array( $host, $hosts, true );
}
