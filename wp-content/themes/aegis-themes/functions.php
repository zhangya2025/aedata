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
require_once get_theme_file_path( 'inc/aegis-plp-filters.php' );

add_action( 'init', function () {
    add_shortcode( 'aegis_pdp_details', 'aegis_pdp_details_shortcode' );
} );

function aegis_info_sidebar_get_nav_items( $current_id ) {
    if ( ! $current_id ) {
        return array();
    }

    $root_id   = $current_id;
    $ancestors = get_post_ancestors( $current_id );
    if ( ! empty( $ancestors ) ) {
        $root_id = (int) end( $ancestors );
    }

    $root_page = get_post( $root_id );
    if ( ! $root_page || 'publish' !== $root_page->post_status ) {
        return array();
    }

    $children = get_pages(
        array(
            'parent'      => $root_id,
            'sort_column' => 'menu_order,post_title',
            'sort_order'  => 'ASC',
            'post_status' => 'publish',
        )
    );

    $items = array( $root_page );
    foreach ( $children as $child ) {
        if ( (int) $child->ID === (int) $root_id ) {
            continue;
        }
        $items[] = $child;
    }

    return $items;
}

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );

    add_editor_style( 'assets/css/main.css' );
} );

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

add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_page_template( 'page-templates/template-info-sidebar.php' ) ) {
        return;
    }

    wp_enqueue_style(
        'aegis-info-sidebar',
        get_theme_file_uri( 'assets/css/aegis-info-sidebar.css' ),
        array( 'aegis-themes-style' ),
        AEGIS_THEMES_VERSION
    );

    wp_enqueue_script(
        'aegis-info-sidebar',
        get_theme_file_uri( 'assets/js/aegis-info-sidebar.js' ),
        array(),
        AEGIS_THEMES_VERSION,
        true
    );
}, 12 );

add_action( 'wp_enqueue_scripts', 'aegis_plp_filters_enqueue', 15 );

add_filter( 'body_class', 'aegis_plp_filters_body_class' );

add_action( 'wp', 'aegis_plp_filters_adjust_shop_loop', 20 );

add_action( 'woocommerce_before_shop_loop', 'aegis_plp_filters_render_toolbar', 15 );

add_action( 'woocommerce_product_query', 'aegis_plp_filters_apply_query' );

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

add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }

    $can_enqueue_modules = function_exists( 'wp_enqueue_script_module' );

    if ( $can_enqueue_modules ) {
        wp_enqueue_script_module( '@wordpress/interactivity' );
        wp_enqueue_script_module( '@woocommerce/stores/store-notices' );
        wp_enqueue_script_module( '@woocommerce/stores/woocommerce/cart' );
        return;
    }

    add_filter( 'render_block', function ( $block_content, $block ) {
        if ( ! function_exists( 'woocommerce_output_all_notices' ) ) {
            return $block_content;
        }

        if ( empty( $block['blockName'] ) || 'woocommerce/store-notices' !== $block['blockName'] ) {
            return $block_content;
        }

        ob_start();
        woocommerce_output_all_notices();
        return ob_get_clean();
    }, 10, 2 );
}, 30 );

add_action( 'wp_head', function () {
    if ( function_exists( 'is_product' ) && is_product() ) {
        echo "<!-- AEGIS_PDP_ACTIVE_HEAD -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}, 5 );

add_filter( 'the_title', function ( $title, $post_id ) {
    if ( is_admin() || ! function_exists( 'is_cart' ) || ! is_cart() ) {
        return $title;
    }

    if ( ! in_the_loop() || ! is_main_query() ) {
        return $title;
    }

    if ( (int) $post_id !== (int) get_queried_object_id() ) {
        return $title;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return $title;
    }

    $count = WC()->cart->get_cart_contents_count();

    return sprintf( '%s (%d)', esc_html__( 'Shopping Cart', 'aegis-themes' ), (int) $count );
}, 20, 2 );
