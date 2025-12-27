<?php
/**
 * Plugin Name: WLA Mousewheel Local
 * Description: Load a local jquery-mousewheel script early on the frontend to prevent The7 custom scrollbar from requesting cdnjs.cloudflare.com.
 * Version: 1.0.1
 * Author: Wind Local Assets
 * License: MIT (jquery-mousewheel by brandonaaron/jquery-mousewheel)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
    return;
}

add_action( 'wp_enqueue_scripts', 'wla_register_local_mousewheel', 1 );
function wla_register_local_mousewheel() {
    $path = plugin_dir_path( __FILE__ ) . 'vendor/jquery-mousewheel/jquery.mousewheel.min.js';

    if ( ! file_exists( $path ) ) {
        return;
    }

    $src = plugin_dir_url( __FILE__ ) . 'vendor/jquery-mousewheel/jquery.mousewheel.min.js';

    wp_register_script( 'wla-jquery-mousewheel', $src, [ 'jquery' ], '3.1.13', false );
    wp_enqueue_script( 'wla-jquery-mousewheel' );
}

add_action( 'wp_enqueue_scripts', 'wla_add_mousewheel_dependency', 50 );
function wla_add_mousewheel_dependency() {
    $scripts = wp_scripts();

    if ( ! $scripts || ! wp_script_is( 'wla-jquery-mousewheel', 'enqueued' ) ) {
        return;
    }

    $handle = 'the7-custom-scrollbar';

    if ( empty( $scripts->registered[ $handle ] ) ) {
        return;
    }

    $deps = $scripts->registered[ $handle ]->deps;

    if ( ! in_array( 'wla-jquery-mousewheel', $deps, true ) ) {
        $deps[] = 'wla-jquery-mousewheel';
        $scripts->registered[ $handle ]->deps = array_values( array_unique( $deps ) );
    }
}
