<?php
/**
 * Plugin Name: WLA Facebook Guard
 * Description: Adds China-friendly timeout capping and optional skipping for Elementor Pro Facebook App ID validation.
 * Author: WLA
 * Version: 1.0.0
 */

if ( defined( 'ABSPATH' ) && ABSPATH && ! function_exists( 'wla_facebook_guard_bootstrap' ) ) {
    /**
     * Initialize hooks.
     */
    function wla_facebook_guard_bootstrap() {
        $skip = defined( 'WLA_FACEBOOK_SKIP_VERIFY' ) ? (bool) WLA_FACEBOOK_SKIP_VERIFY : false;

        add_filter( 'http_request_args', 'wla_facebook_guard_request_args', 20, 2 );

        if ( $skip ) {
            add_filter( 'pre_http_request', 'wla_facebook_guard_skip_verify', 20, 3 );
        }
    }

    /**
     * Detect graph.facebook.com calls.
     */
    function wla_facebook_guard_is_graph( $url ) {
        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) ) {
            return false;
        }

        return 'graph.facebook.com' === strtolower( $parts['host'] );
    }

    /**
     * Cap timeout and redirection for graph.facebook.com requests.
     */
    function wla_facebook_guard_request_args( $args, $url ) {
        if ( ! wla_facebook_guard_is_graph( $url ) ) {
            return $args;
        }

        $max_timeout         = 4;
        $args['timeout']     = isset( $args['timeout'] ) ? min( $max_timeout, (float) $args['timeout'] ) : $max_timeout;
        $args['redirection'] = 0;

        wla_facebook_guard_log( 'timeout capped for graph.facebook.com request' );

        return $args;
    }

    /**
     * Optionally skip graph.facebook.com verification to avoid blocking.
     */
    function wla_facebook_guard_skip_verify( $preempt, $args, $url ) {
        if ( ! wla_facebook_guard_is_graph( $url ) ) {
            return $preempt;
        }

        $body = wp_json_encode( array() );

        wla_facebook_guard_log( 'graph.facebook.com request skipped via WLA_FACEBOOK_SKIP_VERIFY' );

        return array(
            'headers'  => array(),
            'body'     => $body,
            'response' => array(
                'code'    => 200,
                'message' => 'OK',
            ),
            'cookies'  => array(),
            'filename' => null,
        );
    }

    /**
     * Optional logging controlled via WLA_FACEBOOK_GUARD_LOG.
     */
    function wla_facebook_guard_log( $message ) {
        if ( ! defined( 'WLA_FACEBOOK_GUARD_LOG' ) || ! WLA_FACEBOOK_GUARD_LOG ) {
            return;
        }

        $upload_dir = wp_upload_dir();
        if ( empty( $upload_dir['basedir'] ) ) {
            return;
        }

        $log_dir = trailingslashit( $upload_dir['basedir'] ) . 'wla-logs/';
        if ( ! is_dir( $log_dir ) && ! wp_mkdir_p( $log_dir ) ) {
            return;
        }

        $log_file = $log_dir . 'facebook-guard.log';
        $line     = sprintf( '[%s] %s' . PHP_EOL, current_time( 'mysql' ), $message );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
        file_put_contents( $log_file, $line, FILE_APPEND );
    }

    add_action( 'plugins_loaded', 'wla_facebook_guard_bootstrap' );
}
