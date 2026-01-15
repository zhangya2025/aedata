<?php
/**
 * Shop product archive template.
 *
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

/**
 * Hook: woocommerce_before_main_content.
 *
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @hooked woocommerce_breadcrumb - 20
 * @hooked WC_Structured_Data::generate_website_data() - 30
 */
do_action( 'woocommerce_before_main_content' );

?>
<div class="aegis-shop-hero">
	<?php
	$hero_preset = get_page_by_path( 'shop', OBJECT, 'aegis_hero' );
	$hero_id = $hero_preset ? (int) $hero_preset->ID : 0;
	$hero_markup = '';

	if ( $hero_id && function_exists( 'aegis_hero_render_embed_block' ) ) {
		$hero_markup = aegis_hero_render_embed_block( array( 'heroId' => $hero_id ) );
	} elseif ( $hero_id && function_exists( 'render_block' ) ) {
		$hero_markup = render_block(
			array(
				'blockName' => 'aegis/hero-embed',
				'attrs' => array(
					'heroId' => $hero_id,
				),
				'innerBlocks' => array(),
				'innerHTML' => '',
				'innerContent' => array(),
			)
		);
	}

	if ( '' !== $hero_markup ) {
		echo $hero_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo '<!-- AEGIS Hero preset "shop" not found or unavailable. -->';
	}
	?>
</div>
<?php

/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action( 'woocommerce_after_main_content' );

get_footer( 'shop' );
