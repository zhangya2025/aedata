<?php
/**
 * WooCommerce PDP customizations for Aegis Themes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Apply PDP-specific tweaks only on single product pages.
 */
function aegis_wc_pdp_bootstrap() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }

    // Remove default upsell output and ensure only one recommendations section is rendered.
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

    // Keep details tabs focused on product content only.
    add_filter( 'woocommerce_product_tabs', 'aegis_wc_pdp_filter_tabs', 20 );

    // Provide default renderers for custom PDP modules.
    add_action( 'aegis_wc_pdp_trust', 'aegis_wc_pdp_default_trust' );
    add_action( 'aegis_wc_pdp_highlights', 'aegis_wc_pdp_default_highlights' );
    add_action( 'aegis_wc_pdp_qa', 'aegis_wc_pdp_default_qa' );
}
add_action( 'wp', 'aegis_wc_pdp_bootstrap' );

/**
 * Remove the reviews tab so that reviews can live in a dedicated module.
 *
 * @param array $tabs Default product tabs.
 *
 * @return array
 */
function aegis_wc_pdp_filter_tabs( $tabs ) {
    if ( isset( $tabs['reviews'] ) ) {
        unset( $tabs['reviews'] );
    }

    return $tabs;
}

/**
 * Basic trust badges placeholder.
 */
function aegis_wc_pdp_default_trust() {
    echo '<div class="aegis-wc-trust__badges">';
    echo '<span class="aegis-wc-trust__badge">' . esc_html__( 'Secure checkout', 'aegis-themes' ) . '</span>';
    echo '<span class="aegis-wc-trust__badge">' . esc_html__( 'Free returns', 'aegis-themes' ) . '</span>';
    echo '<span class="aegis-wc-trust__badge">' . esc_html__( '24/7 support', 'aegis-themes' ) . '</span>';
    echo '</div>';
}

/**
 * Highlights defaults to the product short description if available.
 */
function aegis_wc_pdp_default_highlights() {
    global $post;

    if ( has_excerpt( $post ) ) {
        echo '<div class="aegis-wc-highlights__content">';
        echo apply_filters( 'woocommerce_short_description', $post->post_excerpt );
        echo '</div>';
        return;
    }

    echo '<div class="aegis-wc-highlights__content">' . esc_html__( 'Product highlights will appear here.', 'aegis-themes' ) . '</div>';
}

/**
 * QA placeholder for future integration.
 */
function aegis_wc_pdp_default_qa() {
    echo '<div class="aegis-wc-qa__placeholder">' . esc_html__( 'Customer Q&A coming soon.', 'aegis-themes' ) . '</div>';
}
