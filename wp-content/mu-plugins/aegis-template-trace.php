<?php
/**
 * Plugin Name: Aegis Template Trace
 * Description: Runtime tracing for template resolution, Woo template parts, PDP hooks, and shortcodes.
 */

if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
    return;
}

if ( ! function_exists( 'aegis_template_trace_log' ) ) {
    function aegis_template_trace_log( $message ) {
        error_log( '[aegis-template-trace] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}

if ( ! function_exists( 'aegis_template_trace_request_context' ) ) {
    function aegis_template_trace_request_context() {
        return sprintf(
            'url=%s is_product=%d is_shop=%d is_page=%d is_singular=%d',
            isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
            function_exists( 'is_product' ) && is_product() ? 1 : 0,
            function_exists( 'is_shop' ) && is_shop() ? 1 : 0,
            function_exists( 'is_page' ) && is_page() ? 1 : 0,
            function_exists( 'is_singular' ) && is_singular() ? 1 : 0
        );
    }
}

add_action( 'wp', function () {
    aegis_template_trace_log( 'request ' . aegis_template_trace_request_context() );
    aegis_template_trace_log( 'wp_debug_log=' . ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? '1' : '0' ) );

    $shortcode_exists = shortcode_exists( 'aegis_info_sidebar_nav' ) ? 1 : 0;
    $callback_exists = function_exists( 'aegis_info_sidebar_nav_shortcode' ) ? 1 : 0;
    aegis_template_trace_log( sprintf( 'shortcode aegis_info_sidebar_nav exists=%d callback_exists=%d', $shortcode_exists, $callback_exists ) );
} );

add_filter( 'template_include', function ( $template ) {
    aegis_template_trace_log( sprintf( 'template_include path=%s %s', $template, aegis_template_trace_request_context() ) );
    return $template;
}, 999 );

add_filter( 'wc_get_template_part', function ( $template, $slug, $name ) {
    if ( $template ) {
        aegis_template_trace_log( sprintf( 'wc_get_template_part slug=%s name=%s path=%s', $slug, $name, $template ) );
    }
    return $template;
}, 10, 3 );

add_filter( 'wc_get_template', function ( $template, $template_name, $args, $template_path, $default_path ) {
    if ( $template ) {
        aegis_template_trace_log( sprintf( 'wc_get_template name=%s path=%s', $template_name, $template ) );
    }
    return $template;
}, 10, 5 );

add_filter( 'do_shortcode_tag', function ( $output, $tag, $attr ) {
    static $shortcode_counts = array();
    $watch = array(
        'aegis_wc_gallery_wall' => true,
        'aegis_pdp_details' => true,
        'aegis_pdp_tech_features' => true,
        'aegis_pdp_faq' => true,
        'aegis_pdp_certificates' => true,
        'aegis_info_sidebar_nav' => true,
    );

    if ( ! isset( $watch[ $tag ] ) ) {
        return $output;
    }

    if ( ! isset( $shortcode_counts[ $tag ] ) ) {
        $shortcode_counts[ $tag ] = 0;
    }
    $shortcode_counts[ $tag ]++;

    $trace = function_exists( 'wp_debug_backtrace_summary' )
        ? wp_debug_backtrace_summary( null, 5, false )
        : '';
    $message = sprintf( 'shortcode %s count=%d trace=%s', $tag, $shortcode_counts[ $tag ], $trace );
    aegis_template_trace_log( $message );

    return $output;
}, 10, 3 );

add_action( 'woocommerce_before_single_product_summary', function () {
    static $count = 0;
    $count++;
    aegis_template_trace_log( 'hook woocommerce_before_single_product_summary count=' . $count );
} );

add_action( 'woocommerce_single_product_summary', function () {
    static $count = 0;
    $count++;
    aegis_template_trace_log( 'hook woocommerce_single_product_summary count=' . $count );
} );

add_action( 'woocommerce_after_single_product_summary', function () {
    static $count = 0;
    $count++;
    aegis_template_trace_log( 'hook woocommerce_after_single_product_summary count=' . $count );
} );
