<?php
/**
 * Plugin Name: WLA CN Guard
 * Description: Adds China-friendly fallbacks: lazy-load Google Maps on the frontend and tighten Elementor external request timeouts with caching/stubbing safeguards.
 * Version: 1.1.0
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
 * Whether to write debug logs.
 *
 * @return bool
 */
function wla_cn_guard_logging_enabled() {
    return defined( 'WLA_CN_GUARD_LOG' ) && WLA_CN_GUARD_LOG;
}

/**
 * Append a message to the cn-guard log inside uploads.
 *
 * @param string $message
 */
function wla_cn_guard_log( $message ) {
    if ( ! wla_cn_guard_logging_enabled() ) {
        return;
    }

    $upload_dir = wp_upload_dir();

    if ( empty( $upload_dir['basedir'] ) ) {
        return;
    }

    $log_dir = trailingslashit( $upload_dir['basedir'] ) . 'wla-logs/';

    if ( ! wp_mkdir_p( $log_dir ) ) {
        return;
    }

    if ( ! is_writable( $log_dir ) ) {
        return;
    }

    $log_file = $log_dir . 'cn-guard.log';
    $line     = sprintf( "[%s] %s\n", gmdate( 'c' ), $message );

    file_put_contents( $log_file, $line, FILE_APPEND );
}

/**
 * Google Maps output buffering and replacement.
 */
function wla_cn_guard_template_buffer() {
    if ( is_admin() || ! wla_cn_guard_enabled() ) {
        return;
    }

    if ( is_feed() || is_robots() || is_trackback() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
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
    $count   = 0;

    $html = preg_replace_callback(
        $pattern,
        function ( $matches ) use ( $mode, &$count ) {
            $src = $matches[1];
            $count++;

            if ( 'block' === $mode ) {
                wla_cn_guard_log( 'Blocked Google Maps script: ' . $src );

                return '<!-- WLA CN Guard blocked Google Maps -->';
            }

            $placeholder_id = 'wla-cn-guard-map-' . substr( sha1( $src . mt_rand() ), 0, 8 );
            $button_label   = esc_html__( '加载地图', 'wla-cn-guard' );
            $src_attr       = esc_attr( $src );

            $script  = '<div class="wla-cn-guard-map-placeholder" id="' . $placeholder_id . '"><button type="button" data-map-src="' . $src_attr . '">' . $button_label . '</button></div>';
            $script .= '<script>(function(){var p=document.getElementById("' . $placeholder_id . '");if(!p)return;var b=p.querySelector("button");if(!b)return;var loaded=false;b.addEventListener("click",function(){if(loaded)return;loaded=true;var s=document.createElement("script");s.src=b.getAttribute("data-map-src");s.async=true;document.head.appendChild(s);});})();</script>';

            wla_cn_guard_log( 'Lazy Google Maps script: ' . $src );

            return $script;
        },
        $html
    );

    if ( $count > 0 ) {
        wla_cn_guard_log( 'Processed ' . $count . ' Google Maps script tag(s) in lazy mode.' );
    }

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

    $timeout_limit       = defined( 'WLA_CN_GUARD_HTTP_TIMEOUT' ) ? (float) WLA_CN_GUARD_HTTP_TIMEOUT : 4.0;
    $existing_timeout    = isset( $args['timeout'] ) ? (float) $args['timeout'] : $timeout_limit;
    $args['timeout']     = min( $timeout_limit, $existing_timeout );
    $args['redirection'] = isset( $args['redirection'] ) ? min( (int) $args['redirection'], 1 ) : 0;

    wla_cn_guard_log( sprintf( 'Tightened request to %s with timeout=%s redirection=%s', $host, $args['timeout'], $args['redirection'] ) );

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

    $is_mixpanel = wla_cn_guard_is_mixpanel_url( $url, $host );
    $cache       = $is_mixpanel ? get_transient( 'wla_mixpanel_json' ) : false;

    if ( $is_mixpanel && $cache ) {
        wla_cn_guard_log( 'Serving cached mixpanel.json without remote request.' );

        return wla_cn_guard_build_response( $cache );
    }

    $skip = defined( 'WLA_CN_GUARD_SKIP_ELEMENTOR_REMOTE' ) && WLA_CN_GUARD_SKIP_ELEMENTOR_REMOTE;

    if ( $skip ) {
        if ( $is_mixpanel ) {
            wla_cn_guard_log( 'Skipping mixpanel.json request due to skip flag.' );

            if ( $cache ) {
                return wla_cn_guard_build_response( $cache );
            }

            return wla_cn_guard_build_response( '{}' );
        }

        wla_cn_guard_log( 'Skipping Elementor remote request: ' . $url );

        return new WP_Error( 'wla_cn_guard_skipped', __( 'Elementor remote request skipped by CN Guard.', 'wla-cn-guard' ) );
    }

    return $preempt;
}
add_filter( 'pre_http_request', 'wla_cn_guard_pre_http_request', 10, 3 );

/**
 * Cache mixpanel.json on success for later offline reuse.
 */
function wla_cn_guard_http_api_debug( $response, $type, $class, $args, $url ) {
    if ( ! wla_cn_guard_enabled() ) {
        return;
    }

    $host = wla_cn_guard_parse_host( $url );

    if ( ! $host || ! wla_cn_guard_is_mixpanel_url( $url, $host ) ) {
        return;
    }

    if ( is_wp_error( $response ) ) {
        if ( get_transient( 'wla_mixpanel_json' ) ) {
            wla_cn_guard_log( 'Mixpanel request failed; cached response will be reused next time.' );
        } else {
            wla_cn_guard_log( 'Mixpanel request failed with no cache available.' );
        }

        return;
    }

    if ( is_array( $response ) && isset( $response['response']['code'] ) && 200 === (int) $response['response']['code'] ) {
        $body = $response['body'] ?? '';

        if ( $body ) {
            set_transient( 'wla_mixpanel_json', $body, DAY_IN_SECONDS );
            wla_cn_guard_log( 'Cached mixpanel.json body for reuse.' );
        }
    }
}
add_action( 'http_api_debug', 'wla_cn_guard_http_api_debug', 10, 5 );

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

/**
 * Check whether the URL is the mixpanel JSON payload Elementor uses.
 *
 * @param string      $url
 * @param string|null $host
 *
 * @return bool
 */
function wla_cn_guard_is_mixpanel_url( $url, $host = null ) {
    $parsed_host = $host ?: wla_cn_guard_parse_host( $url );

    if ( 'assets.elementor.com' !== $parsed_host ) {
        return false;
    }

    $parts = wp_parse_url( $url );

    return ! empty( $parts['path'] ) && false !== strpos( $parts['path'], '/mixpanel/v1/mixpanel.json' );
}

/**
 * Build a WP style response array.
 *
 * @param string $body
 * @param int    $code
 *
 * @return array
 */
function wla_cn_guard_build_response( $body, $code = 200 ) {
    return [
        'headers'  => [],
        'body'     => $body,
        'response' => [
            'code'    => $code,
            'message' => get_status_header_desc( $code ),
        ],
        'cookies'  => [],
        'filename' => null,
    ];
}
