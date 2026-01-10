<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function aegis_badges_get_matching_rule_preset( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$rules = Aegis_Badges::get_rules();
	if ( empty( $rules ) ) {
		return '';
	}

	usort(
		$rules,
		static function ( $first, $second ) {
			$first_priority  = isset( $first['priority'] ) ? intval( $first['priority'] ) : 0;
			$second_priority = isset( $second['priority'] ) ? intval( $second['priority'] ) : 0;

			return $second_priority <=> $first_priority;
		}
	);

	foreach ( $rules as $rule ) {
		if ( ! isset( $rule['preset_id'] ) ) {
			continue;
		}

		$preset_id = Aegis_Badges::normalize_preset_id( $rule['preset_id'] );
		if ( ! aegis_badges_rule_matches_product( $product, $rule ) ) {
			continue;
		}

		return $preset_id;
	}

	return '';
}

function aegis_badges_rule_matches_product( $product, $rule ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$has_criteria = false;

	$product_ids = isset( $rule['product_ids'] ) && is_array( $rule['product_ids'] ) ? array_map( 'intval', $rule['product_ids'] ) : array();
	if ( ! empty( $product_ids ) ) {
		$has_criteria = true;
		if ( ! in_array( $product->get_id(), $product_ids, true ) ) {
			return false;
		}
	}

	$cat_ids = isset( $rule['product_cat_ids'] ) && is_array( $rule['product_cat_ids'] ) ? array_map( 'intval', $rule['product_cat_ids'] ) : array();
	if ( ! empty( $cat_ids ) ) {
		$has_criteria = true;
		if ( ! has_term( $cat_ids, 'product_cat', $product->get_id() ) ) {
			return false;
		}
	}

	$tag_ids = isset( $rule['product_tag_ids'] ) && is_array( $rule['product_tag_ids'] ) ? array_map( 'intval', $rule['product_tag_ids'] ) : array();
	if ( ! empty( $tag_ids ) ) {
		$has_criteria = true;
		if ( ! has_term( $tag_ids, 'product_tag', $product->get_id() ) ) {
			return false;
		}
	}

	$attribute_terms = isset( $rule['attribute_terms'] ) && is_array( $rule['attribute_terms'] ) ? $rule['attribute_terms'] : array();
	if ( ! empty( $attribute_terms ) ) {
		foreach ( $attribute_terms as $attribute_rule ) {
			if ( empty( $attribute_rule['taxonomy'] ) || empty( $attribute_rule['term_ids'] ) ) {
				continue;
			}

			$has_criteria = true;
			$taxonomy = sanitize_text_field( $attribute_rule['taxonomy'] );
			$term_ids = array_map( 'intval', $attribute_rule['term_ids'] );
			if ( empty( $term_ids ) ) {
				continue;
			}

			if ( ! has_term( $term_ids, $taxonomy, $product->get_id() ) ) {
				return false;
			}
		}
	}

	return $has_criteria;
}

function aegis_badges_strip_blocks_sale_badge( $html ) {
	$patterns = array(
		'/<[^>]*class="[^"]*wc-block-components-product-sale-badge[^"]*"[^>]*>.*?<\/[^>]+>/is',
		'/<[^>]*class="[^"]*wc-block-components-product-sale-badge__text[^"]*"[^>]*>.*?<\/[^>]+>/is',
		'/<[^>]*class="[^"]*wc-block-grid__product-onsale[^"]*"[^>]*>.*?<\/[^>]+>/is',
	);

	return preg_replace( $patterns, '', $html );
}

function aegis_badges_inject_badge_into_block_item( $html, $badge_html ) {
	if ( preg_match( '/<li[^>]*>/', $html ) ) {
		return preg_replace( '/(<li[^>]*>)/', '$1' . $badge_html, $html, 1 );
	}

	return $html . $badge_html;
}
