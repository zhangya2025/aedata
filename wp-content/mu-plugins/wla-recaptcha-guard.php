<?php
/**
 * Plugin Name: WLA reCAPTCHA Guard
 * Description: Adds China-friendly resilience for Elementor Pro reCAPTCHA validations without altering Elementor core files.
 * Author: WLA
 * Version: 1.0.0
 */

if ( defined( 'ABSPATH' ) && ABSPATH && ! function_exists( 'wla_recaptcha_guard_bootstrap' ) ) {
/**
 * Bootstrap hooks for the guard.
 */
function wla_recaptcha_guard_bootstrap() {
$mode = defined( 'WLA_RECAPTCHA_MODE' ) ? strtolower( WLA_RECAPTCHA_MODE ) : 'strict';
$mode = in_array( $mode, array( 'off', 'strict', 'soft' ), true ) ? $mode : 'strict';

if ( 'off' === $mode ) {
return;
}

// Only run on front-end contexts or AJAX/REST submissions.
if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
return;
}

define( 'WLA_RECAPTCHA_ACTIVE_MODE', $mode );

add_filter( 'http_request_args', 'wla_recaptcha_guard_request_args', 20, 2 );
add_filter( 'http_response', 'wla_recaptcha_guard_response', 20, 3 );
}

/**
 * Detect the Google reCAPTCHA siteverify endpoint.
 */
function wla_recaptcha_guard_is_siteverify( $url ) {
$parts = wp_parse_url( $url );
if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
return false;
}

$host = strtolower( $parts['host'] );
$path = trim( $parts['path'] );

if ( 'www.google.com' !== $host ) {
return false;
}

return '/recaptcha/api/siteverify' === $path;
}

/**
 * Cap timeout/redirection for the siteverify call.
 */
function wla_recaptcha_guard_request_args( $args, $url ) {
if ( ! wla_recaptcha_guard_is_siteverify( $url ) ) {
return $args;
}

$max_timeout         = 4;
$args['timeout']     = isset( $args['timeout'] ) ? min( $max_timeout, (float) $args['timeout'] ) : $max_timeout;
$args['redirection'] = 0;

return $args;
}

/**
 * Provide a soft fallback response when siteverify is unreachable.
 */
function wla_recaptcha_guard_response( $response, $args, $url ) {
if ( ! wla_recaptcha_guard_is_siteverify( $url ) ) {
return $response;
}

$mode = defined( 'WLA_RECAPTCHA_ACTIVE_MODE' ) ? WLA_RECAPTCHA_ACTIVE_MODE : 'strict';

if ( 'soft' !== $mode ) {
return $response;
}

// If the request succeeded (non-5xx, non-error), keep it.
if ( ! is_wp_error( $response ) && isset( $response['response']['code'] ) && (int) $response['response']['code'] < 500 ) {
return $response;
}

$fallback_body = wp_json_encode(
array(
'success'      => true,
'wla_bypass'   => true,
'hostname'     => isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '',
'challenge_ts' => current_time( 'mysql' ),
)
);

$log_message = 'recaptcha soft bypass triggered';
if ( is_wp_error( $response ) ) {
$log_message .= ' error=' . $response->get_error_message();
} elseif ( isset( $response['response']['code'] ) ) {
$log_message .= ' code=' . (int) $response['response']['code'];
}
wla_recaptcha_guard_log( $log_message );

return array(
'headers'  => array( 'content-type' => 'application/json' ),
'body'     => $fallback_body,
'response' => array(
'code'    => 200,
'message' => 'OK',
),
'cookies'  => array(),
'filename' => null,
);
}

/**
 * Optional logging controlled via WLA_RECAPTCHA_GUARD_LOG.
 */
function wla_recaptcha_guard_log( $message ) {
if ( ! defined( 'WLA_RECAPTCHA_GUARD_LOG' ) || ! WLA_RECAPTCHA_GUARD_LOG ) {
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

$log_file = $log_dir . 'recaptcha-guard.log';
$line     = sprintf( '[%s] %s' . PHP_EOL, current_time( 'mysql' ), $message );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
file_put_contents( $log_file, $line, FILE_APPEND );
}

add_action( 'plugins_loaded', 'wla_recaptcha_guard_bootstrap' );
}
