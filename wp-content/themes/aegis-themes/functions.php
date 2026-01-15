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
require_once get_theme_file_path( 'inc/aegis-woo-context.php' );
require_once get_theme_file_path( 'inc/aegis-plp-filters.php' );
add_action( 'init', function () {
    add_shortcode( 'aegis_pdp_details', 'aegis_pdp_details_shortcode' );
    add_shortcode( 'aegis_info_sidebar_nav', 'aegis_info_sidebar_nav_shortcode' );
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
if ( function_exists( 'aegis_plp_debug_enabled' ) && aegis_plp_debug_enabled() ) {
    add_action( 'pre_get_posts', 'aegis_plp_filters_log_final_query_vars', 9999 );
    add_filter( 'posts_clauses', 'aegis_plp_filters_log_final_sql', 9999, 2 );
}

add_filter( 'loop_shop_per_page', function ( $per_page ) {
    if ( is_admin() ) {
        return $per_page;
    }

    return 12;
}, 20 );

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

function aegis_info_pages_seed_data() {
    return array(
        'parents'  => array(
            'about'     => 'About Us',
            'legal'     => 'Legal & Compliance',
            'resources' => 'Resources',
            'support'   => 'Customer Service',
        ),
        'children' => array(
            'about'     => array(
                array( 'slug' => 'brand-story', 'title' => '品牌故事', 'menu_order' => 10 ),
                array( 'slug' => 'mission-vision', 'title' => '使命愿景', 'menu_order' => 20 ),
                array( 'slug' => 'team', 'title' => '团队', 'menu_order' => 30 ),
            ),
            'legal'     => array(
                array( 'slug' => 'privacy-policy', 'title' => '隐私政策', 'menu_order' => 10 ),
                array( 'slug' => 'terms', 'title' => '条款与条件', 'menu_order' => 20 ),
                array( 'slug' => 'returns-refunds', 'title' => '退换货政策', 'menu_order' => 30 ),
                array( 'slug' => 'shipping', 'title' => '运输政策', 'menu_order' => 40 ),
            ),
            'resources' => array(
                array( 'slug' => 'faqs', 'title' => 'FAQs', 'menu_order' => 10 ),
                array( 'slug' => 'size-fit', 'title' => 'Size & Fit Guides', 'menu_order' => 20 ),
                array( 'slug' => 'laundering', 'title' => 'Laundering Instructions', 'menu_order' => 30 ),
                array( 'slug' => 'technical', 'title' => '技术说明', 'menu_order' => 40 ),
            ),
            'support'   => array(
                array( 'slug' => 'contact', 'title' => '联系我们', 'menu_order' => 10 ),
                array( 'slug' => 'repair', 'title' => '维修申请', 'menu_order' => 20 ),
                array( 'slug' => 'dealer', 'title' => '加盟经销', 'menu_order' => 30 ),
                array( 'slug' => 'customization', 'title' => '定制服务', 'menu_order' => 40 ),
                array( 'slug' => 'sponsorship', 'title' => '赞助服务', 'menu_order' => 50 ),
            ),
        ),
    );
}

function aegis_info_pages_seed_placeholder( $title, $include_support_note ) {
    $content = sprintf( '<p>待完善/Content pending：%s 页面内容待补充。</p>', esc_html( $title ) );
    if ( $include_support_note ) {
        $content .= '<p>表单区域占位：后续替换为表单区块/短代码。</p>';
    }
    return $content;
}

add_filter( 'pre_get_block_template', 'aegis_shop_override_block_template', 10, 3 );

function aegis_shop_override_block_template( $template, $id, $template_type ) {
    if ( 'wp_template' !== $template_type ) {
        return $template;
    }

    if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
        return $template;
    }

    if ( false === strpos( $id, '//archive-product' ) ) {
        return $template;
    }

    $theme = get_stylesheet();
    $block_template = new WP_Block_Template();
    $block_template->id = $theme . '//archive-product';
    $block_template->slug = 'archive-product';
    $block_template->theme = $theme;
    $block_template->type = 'wp_template';
    $block_template->title = 'AEGIS Shop (Hero only)';
    $block_template->description = 'Shop template overridden to show AEGIS Hero only.';
    $block_template->source = 'theme';
    $block_template->content = '<!-- AEGIS SHOP BLOCK TEMPLATE HIT -->'
        . '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
        . '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->'
        . '<div class="wp-block-group">'
        . '<!-- wp:shortcode -->[aegis_hero preset="shop"]<!-- /wp:shortcode -->'
        . '</div>'
        . '<!-- /wp:group -->'
        . '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';

    return $block_template;
}
