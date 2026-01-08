<?php
/**
 * Theme setup for Aegis-Themes.
 */

define( 'AEGIS_THEMES_VERSION', '0.1.0' );

require_once get_theme_file_path( 'inc/woocommerce-pdp.php' );
require_once get_theme_file_path( 'inc/woocommerce-pdp-block.php' );
require_once get_theme_file_path( 'inc/woocommerce-pdp-modules.php' );
require_once get_theme_file_path( 'inc/woocommerce-gallery-wall.php' );
require_once get_theme_file_path( 'inc/pdp-fields.php' );
require_once get_theme_file_path( 'inc/pdp-accordion.php' );
require_once get_theme_file_path( 'inc/size-guides.php' );
require_once get_theme_file_path( 'inc/faq-library.php' );
require_once get_theme_file_path( 'inc/tech-features.php' );
require_once get_theme_file_path( 'inc/certificates.php' );

add_action( 'init', function () {
    add_shortcode( 'aegis_pdp_details', 'aegis_pdp_details_shortcode' );
} );

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );

    add_editor_style( 'assets/css/main.css' );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'aegis-font-noto-sans-sc', get_stylesheet_directory_uri() . '/assets/fonts/noto-sans-sc/noto-sans-sc.css', array(), AEGIS_THEMES_VERSION );
}, 5 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }

    if ( in_array( $screen->post_type, array( 'product', 'aegis_certificate' ), true ) ) {
        wp_enqueue_script(
            'aegis-admin-faq-picker',
            get_theme_file_uri( 'assets/js/admin-faq-picker.js' ),
            array(),
            AEGIS_THEMES_VERSION,
            true
        );
    }

    if ( 'aegis_certificate' === $screen->post_type ) {
        wp_enqueue_media();
    }

}, 20 );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'aegis-themes-style', get_theme_file_uri( 'assets/css/main.css' ), array(), AEGIS_THEMES_VERSION );
    wp_enqueue_script( 'aegis-themes-script', get_theme_file_uri( 'assets/js/main.js' ), array(), AEGIS_THEMES_VERSION, true );
} );

/**
 * Enqueue theme assets.
 */
add_action( 'wp_enqueue_scripts', function () {
    // WooCommerce base styles (theme-owned). Only load on WooCommerce related pages.
    if ( function_exists( 'is_woocommerce' ) ) {
        $is_wc = is_woocommerce() || is_cart() || is_checkout() || is_account_page();
        if ( $is_wc ) {
            wp_enqueue_style(
                'aegis-themes-woocommerce',
                get_theme_file_uri( 'assets/css/woocommerce.css' ),
                array(),
                AEGIS_THEMES_VERSION
            );
        }
    }
}, 20 );

add_action( 'wp_enqueue_scripts', function () {
    if ( function_exists( 'is_product' ) && is_product() ) {
        wp_enqueue_style(
            'aegis-themes-woocommerce-pdp',
            get_theme_file_uri( 'assets/css/woocommerce-pdp.css' ),
            array( 'aegis-themes-woocommerce' ),
            AEGIS_THEMES_VERSION
        );

        wp_enqueue_script( 'wc-single-product' );

        wp_enqueue_script(
            'aegis-themes-woocommerce-pdp',
            get_theme_file_uri( 'assets/js/woocommerce-pdp.js' ),
            array(),
            AEGIS_THEMES_VERSION,
            true
        );

        $size_guide_id = aegis_get_product_size_guide_id();
        wp_localize_script(
            'aegis-themes-woocommerce-pdp',
            'AEGIS_SIZE_GUIDE',
            array(
                'guideId' => $size_guide_id,
                'restBase' => esc_url_raw( rest_url( 'aegis/v1/size-guide/' ) ),
            )
        );

        wp_localize_script(
            'aegis-themes-woocommerce-pdp',
            'AEGIS_TECH_FEATURES',
            array(
                'restBase' => esc_url_raw( rest_url( 'wp/v2/aegis_tech_feature/' ) ),
            )
        );

        wp_localize_script(
            'aegis-themes-woocommerce-pdp',
            'AEGIS_CERTIFICATES',
            array(
                'restBase' => esc_url_raw( rest_url( 'aegis/v1/certificate-file/' ) ),
            )
        );
    }
}, 25 );

add_action( 'wp_head', function () {
    if ( function_exists( 'is_product' ) && is_product() ) {
        echo "<!-- AEGIS_PDP_ACTIVE_HEAD -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}, 5 );

add_action( 'woocommerce_before_cart', function () {
    if ( function_exists( 'is_cart' ) && ! is_cart() ) {
        return;
    }

    echo '<div class="aegis-cart-layout"><div class="aegis-cart-main">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 5 );

add_action( 'woocommerce_before_cart_collaterals', function () {
    if ( function_exists( 'is_cart' ) && ! is_cart() ) {
        return;
    }

    echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 5 );

add_action( 'woocommerce_after_cart', function () {
    if ( function_exists( 'is_cart' ) && ! is_cart() ) {
        return;
    }

    echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 50 );
