<?php
/**
 * Theme setup for Aegis-Themes.
 */

define( 'AEGIS_THEMES_VERSION', '0.1.0' );

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );

    add_editor_style( 'assets/css/main.css' );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'aegis-font-noto-sans-sc',
        get_stylesheet_directory_uri() . '/assets/fonts/noto-sans-sc/noto-sans-sc.css',
        array(),
        AEGIS_THEMES_VERSION
    );
}, 5 );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'aegis-themes-style', get_theme_file_uri( 'assets/css/main.css' ), array(), AEGIS_THEMES_VERSION );
    wp_enqueue_script( 'aegis-themes-script', get_theme_file_uri( 'assets/js/main.js' ), array(), AEGIS_THEMES_VERSION, true );
} );
