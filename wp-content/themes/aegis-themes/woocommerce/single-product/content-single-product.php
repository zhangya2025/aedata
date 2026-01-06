<?php
/**
 * The template for displaying product content in the single-product.php template.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
    return;
}
?>

<!-- AEGIS_PDP_ACTIVE -->

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'aegis-wc-product', $product ); ?>>
    <?php
    /**
     * Hook: woocommerce_before_single_product.
     *
     * @hooked wc_print_notices - 10
     */
    do_action( 'woocommerce_before_single_product' );

    if ( post_password_required() ) {
        echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }
    ?>

    <div class="aegis-wc-module aegis-wc-module--top" data-aegis-module="top">
        <div class="aegis-wc-top__gallery aegis-wc-module aegis-wc-module--gallery" data-aegis-module="gallery">
            <?php
            /**
             * Hook: woocommerce_before_single_product_summary.
             *
             * @hooked woocommerce_show_product_sale_flash - 10
             * @hooked woocommerce_show_product_images - 20
             */
            do_action( 'woocommerce_before_single_product_summary' );
            ?>
        </div>

        <div class="aegis-wc-top__buybox aegis-wc-module aegis-wc-module--buybox" data-aegis-module="buybox">
            <div class="aegis-wc-buybox__inner">
                <?php
                /**
                 * Hook: woocommerce_single_product_summary.
                 *
                 * @hooked woocommerce_template_single_title - 5
                 * @hooked woocommerce_template_single_rating - 10
                 * @hooked woocommerce_template_single_price - 10
                 * @hooked woocommerce_template_single_excerpt - 20
                 * @hooked woocommerce_template_single_add_to_cart - 30
                 * @hooked woocommerce_template_single_meta - 40
                 * @hooked woocommerce_template_single_sharing - 50
                 * @hooked WC_Structured_Data::generate_product_data() - 60
                 */
                do_action( 'woocommerce_single_product_summary' );
                ?>
            </div>
        </div>
    </div>

    <div class="aegis-wc-module aegis-wc-module--trust" data-aegis-module="trust">
        <?php do_action( 'aegis_wc_pdp_trust' ); ?>
    </div>

    <div class="aegis-wc-module aegis-wc-module--highlights" data-aegis-module="highlights">
        <?php do_action( 'aegis_wc_pdp_highlights' ); ?>
    </div>

    <div class="aegis-wc-module aegis-wc-module--details" data-aegis-module="details">
        <?php woocommerce_output_product_data_tabs(); ?>
    </div>

    <div class="aegis-wc-module aegis-wc-module--reviews" data-aegis-module="reviews">
        <?php comments_template(); ?>
    </div>

    <div class="aegis-wc-module aegis-wc-module--qa" data-aegis-module="qa">
        <?php do_action( 'aegis_wc_pdp_qa' ); ?>
    </div>

    <div class="aegis-wc-module aegis-wc-module--recommendations" data-aegis-module="recommendations">
        <?php woocommerce_output_related_products(); ?>
    </div>

    <div class="aegis-wc-module aegis-wc-module--sticky_bar" data-aegis-module="sticky_bar">
        <div class="aegis-wc-sticky-bar">
            <div class="aegis-wc-sticky-bar__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
            <button type="button" class="aegis-wc-sticky-bar__cta" data-scroll-target=".aegis-wc-module--buybox">
                <?php esc_html_e( 'View options', 'aegis-themes' ); ?>
            </button>
        </div>
    </div>

    <?php do_action( 'woocommerce_after_single_product' ); ?>
</div>
