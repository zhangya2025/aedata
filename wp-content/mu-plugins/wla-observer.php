<?php
/**
 * Plugin Name: WLA Observer
 * Description: 条件启用的观测插件，用于记录外部资源与 HTTP 请求耗时。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wla_should_observe() {
    if ( defined( 'WLA_OBSERVE' ) && WLA_OBSERVE ) {
        return true;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return true;
    }

    return isset( $_GET['wla_observe'] ) && '1' === $_GET['wla_observe'];
}

if ( ! wla_should_observe() ) {
    return;
}

$wla_asset_logs = [];
$wla_http_events = [];

function wla_is_external_url( $url ) {
    $host = parse_url( $url, PHP_URL_HOST );

    if ( ! $host ) {
        return false;
    }

    $site_host = parse_url( home_url(), PHP_URL_HOST );

    return $site_host && ! hash_equals( $site_host, $host );
}

function wla_resolve_src( $src, $base_url ) {
    if ( empty( $src ) ) {
        return '';
    }

    if ( preg_match( '#^https?://#i', $src ) ) {
        return $src;
    }

    if ( 0 === strpos( $src, '//' ) ) {
        $scheme = is_ssl() ? 'https:' : 'http:';
        return $scheme . $src;
    }

    return rtrim( $base_url, '/' ) . '/' . ltrim( $src, '/' );
}

function wla_collect_handles( $deps, $type ) {
    $items = [];

    foreach ( $deps->queue as $handle ) {
        if ( ! isset( $deps->registered[ $handle ] ) ) {
            continue;
        }

        $obj = $deps->registered[ $handle ];
        $resolved_src = wla_resolve_src( $obj->src, $deps->base_url );

        $items[] = [
            'handle'   => $handle,
            'src'      => $resolved_src,
            'external' => wla_is_external_url( $resolved_src ),
            'type'     => $type,
        ];
    }

    return $items;
}

function wla_store_assets( $type, $stage ) {
    global $wla_asset_logs;

    if ( 'scripts' === $type ) {
        $deps = wp_scripts();
    } else {
        $deps = wp_styles();
    }

    if ( ! $deps ) {
        return;
    }

    $wla_asset_logs[] = [
        'type'  => $type,
        'stage' => $stage,
        'items' => wla_collect_handles( $deps, $type ),
    ];
}

add_action( 'wp_print_scripts', function () {
    wla_store_assets( 'scripts', 'before_print' );
}, 5 );

add_action( 'wp_print_scripts', function () {
    wla_store_assets( 'scripts', 'after_print' );
}, 999 );

add_action( 'wp_print_styles', function () {
    wla_store_assets( 'styles', 'before_print' );
}, 5 );

add_action( 'wp_print_styles', function () {
    wla_store_assets( 'styles', 'after_print' );
}, 999 );

add_filter( 'http_request_args', function ( $args, $url ) {
    $args['wla_trace'] = microtime( true );
    return $args;
}, 10, 2 );

add_action( 'http_api_debug', function ( $response, $type, $class, $args, $url ) {
    if ( 'response' !== $type ) {
        return;
    }

    global $wla_http_events;

    $duration = null;
    if ( isset( $args['wla_trace'] ) ) {
        $duration = round( ( microtime( true ) - $args['wla_trace'] ) * 1000, 2 );
    }

    $success = ! is_wp_error( $response ) && isset( $response['response'] );
    $code    = $success ? ( $response['response']['code'] ?? null ) : null;

    $wla_http_events[] = [
        'url'      => $url,
        'duration' => $duration,
        'success'  => $success,
        'code'     => $code,
    ];
}, 10, 5 );

function wla_get_log_path() {
    $upload_dir = wp_upload_dir();
    $log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'wla-logs/';

    if ( ! file_exists( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
    }

    return $log_dir . 'observe.log';
}

add_action( 'shutdown', function () {
    global $wla_asset_logs, $wla_http_events;

    if ( empty( $wla_asset_logs ) && empty( $wla_http_events ) ) {
        return;
    }

    $log_path = wla_get_log_path();

    $lines   = [];
    $lines[] = str_repeat( '=', 40 );
    $lines[] = sprintf( 'Time: %s', date_i18n( 'Y-m-d H:i:s' ) );
    $lines[] = sprintf( 'Request: %s', esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' ) );

    if ( ! empty( $wla_asset_logs ) ) {
        $lines[] = 'Assets:';
        foreach ( $wla_asset_logs as $log ) {
            $lines[] = sprintf( '  [%s][%s]', $log['type'], $log['stage'] );
            foreach ( $log['items'] as $item ) {
                $lines[] = sprintf( '    - %s (%s)%s', $item['handle'], $item['src'], $item['external'] ? ' [external]' : '' );
            }
        }
    }

    if ( ! empty( $wla_http_events ) ) {
        $lines[] = 'HTTP requests:';
        foreach ( $wla_http_events as $event ) {
            $lines[] = sprintf(
                '  - %s | %s | %s ms | code: %s',
                $event['url'],
                $event['success'] ? 'OK' : 'FAILED',
                null === $event['duration'] ? 'n/a' : $event['duration'],
                $event['code'] ?? 'n/a'
            );
        }
    }

    $lines[] = '';

    file_put_contents( $log_path, implode( "\n", $lines ), FILE_APPEND );
} );

