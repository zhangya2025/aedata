<?php
/**
 * PDP gallery wall renderer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render a two-column gallery wall for the current product.
 *
 * @return string
 */
function aegis_wc_gallery_wall_shortcode() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return '';
    }

    global $product;

    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( get_the_ID() );
    }

    if ( ! $product instanceof WC_Product ) {
        return '';
    }

    $image_ids = array();

    $featured_id = $product->get_image_id();
    if ( $featured_id ) {
        $image_ids[] = $featured_id;
    }

    $gallery_ids = $product->get_gallery_image_ids();
    if ( is_array( $gallery_ids ) ) {
        $image_ids = array_merge( $image_ids, $gallery_ids );
    }

    $image_ids = array_values( array_unique( array_filter( $image_ids ) ) );

    if ( empty( $image_ids ) ) {
        return '';
    }

    ob_start();
    ?>
    <div class="aegis-gallery-wall" data-aegis-gallery="wall">
        <?php foreach ( $image_ids as $index => $image_id ) :
            $full_url = wp_get_attachment_image_url( $image_id, 'full' );

            if ( ! $full_url ) {
                continue;
            }

            $classes   = array( 'aegis-gallery-wall__item' );
            $classes[] = 0 === $index ? 'aegis-gallery-wall__item--primary' : 'aegis-gallery-wall__item--standard';
            ?>
            <a class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" href="<?php echo esc_url( $full_url ); ?>" target="_blank" rel="noopener">
                <?php
                echo wp_get_attachment_image(
                    $image_id,
                    'large',
                    false,
                    array(
                        'loading'  => 0 === $index ? 'eager' : 'lazy',
                        'decoding' => 'async',
                        'sizes'    => '(min-width: 1024px) 50vw, 100vw',
                    )
                );
                ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

add_action( 'init', function () {
    add_shortcode( 'aegis_wc_gallery_wall', 'aegis_wc_gallery_wall_shortcode' );
} );
