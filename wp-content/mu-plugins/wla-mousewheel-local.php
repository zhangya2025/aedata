<?php
/**
 * Plugin Name: WLA Mousewheel Local
 * Description: Load a local jquery-mousewheel script early on the frontend to prevent The7 custom scrollbar from requesting cdnjs.cloudflare.com.
 * Version: 1.0.0
 * Author: Wind Local Assets
 * License: MIT (jquery-mousewheel by brandonaaron/jquery-mousewheel)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( is_admin() ) {
    return;
}

add_action( 'wp_enqueue_scripts', 'wla_register_local_mousewheel', 5 );
function wla_register_local_mousewheel() {
    $src = plugin_dir_url( __FILE__ ) . 'vendor/jquery-mousewheel/jquery.mousewheel.min.js';

    wp_register_script( 'wla-jquery-mousewheel', $src, [ 'jquery' ], '3.1.13', false );
    wp_enqueue_script( 'wla-jquery-mousewheel' );
}

add_action( 'wp_enqueue_scripts', 'wla_add_mousewheel_dependency', 15 );
function wla_add_mousewheel_dependency() {
    $scripts = wp_scripts();

    if ( ! $scripts ) {
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
