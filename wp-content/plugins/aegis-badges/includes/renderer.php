<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function aegis_badges_get_effective_preset_id( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return Aegis_Badges::get_default_settings()['default_preset'];
	}

	$settings      = Aegis_Badges::get_settings();
	$raw_preset    = get_post_meta( $product->get_id(), Aegis_Badges_Product_Meta::META_PRESET, true );
	$raw_preset    = $raw_preset !== '' ? $raw_preset : 'inherit';
	$normalized    = Aegis_Badges::normalize_preset_id( $raw_preset );
	$valid_presets = array_keys( Aegis_Badges::get_presets() );

	if ( $raw_preset !== 'inherit' && in_array( $normalized, $valid_presets, true ) ) {
		return $normalized;
	}

	$rule_preset = aegis_badges_get_matching_rule_preset( $product );
	if ( $rule_preset !== '' ) {
		return $rule_preset;
	}

	return $settings['default_preset'];
}

function aegis_badges_is_opted_in( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$behavior = get_post_meta( $product->get_id(), Aegis_Badges_Product_Meta::META_BEHAVIOR, true );
	$behavior = $behavior !== '' ? $behavior : 'inherit';

	if ( $behavior === 'off' ) {
		return false;
	}

	if ( $behavior === 'on' ) {
		return true;
	}

	$preset_meta = get_post_meta( $product->get_id(), Aegis_Badges_Product_Meta::META_PRESET, true );
	$preset_meta = $preset_meta !== '' ? $preset_meta : 'inherit';
	if ( $preset_meta !== 'inherit' ) {
		$preset_id = Aegis_Badges::normalize_preset_id( $preset_meta );
		if ( in_array( $preset_id, array_keys( Aegis_Badges::get_presets() ), true ) ) {
			return true;
		}
	}

	$rule_preset = aegis_badges_get_matching_rule_preset( $product );

	return $rule_preset !== '';
}

function aegis_badges_should_render_badge( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$settings = Aegis_Badges::get_settings();
	if ( $settings['mode'] !== 'replace' ) {
		return false;
	}

	$behavior = get_post_meta( $product->get_id(), Aegis_Badges_Product_Meta::META_BEHAVIOR, true );
	$behavior = $behavior !== '' ? $behavior : 'inherit';
	if ( $behavior === 'off' ) {
		return false;
	}

	if ( $settings['display_strategy'] === 'opt_in_only' ) {
		if ( ! aegis_badges_is_opted_in( $product ) ) {
			return false;
		}

		return $product->is_on_sale();
	}

	return $product->is_on_sale();
}

function aegis_badges_render_badge_html( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	if ( ! aegis_badges_should_render_badge( $product ) ) {
		return '';
	}

	$settings = Aegis_Badges::get_settings();
	$behavior = get_post_meta( $product->get_id(), Aegis_Badges_Product_Meta::META_BEHAVIOR, true );
	$behavior = $behavior !== '' ? $behavior : 'inherit';

	if ( $behavior === 'off' ) {
		return '';
	}

	if ( ! in_array( $behavior, array( 'inherit', 'on' ), true ) ) {
		$behavior = 'inherit';
	}

	if ( $behavior !== 'on' && $settings['enable_badges'] !== 'yes' ) {
		return '';
	}

	$preset_id = aegis_badges_get_effective_preset_id( $product );
	$presets   = Aegis_Badges::get_presets();
	$defaults  = Aegis_Badges::get_default_presets();
	$preset    = isset( $presets[ $preset_id ] ) ? $presets[ $preset_id ] : $defaults['preset_a'];
	$template  = isset( $preset['template'] ) && in_array( $preset['template'], array( 'pill', 'ribbon', 'corner' ), true ) ? $preset['template'] : 'pill';
	$vars      = isset( $preset['vars'] ) && is_array( $preset['vars'] ) ? $preset['vars'] : array();
	$base_vars = isset( $defaults[ $preset_id ]['vars'] ) ? $defaults[ $preset_id ]['vars'] : $defaults['preset_a']['vars'];
	$vars      = wp_parse_args( $vars, $base_vars );

	$text_override = get_post_meta( $product->get_id(), Aegis_Badges_Product_Meta::META_TEXT, true );
	$text_override = is_string( $text_override ) ? $text_override : '';
	$preset_text   = isset( $preset['text'] ) ? $preset['text'] : '';
	$text          = $text_override !== '' ? $text_override : $preset_text;
	$text          = $text !== '' ? $text : $settings['default_text'];

	if ( $text === '' ) {
		return '';
	}

	$style = aegis_badges_build_inline_style( $vars );

	return '<span class="aegis-badge aegis-badge--' . esc_attr( $template ) . '" data-preset="' . esc_attr( $preset_id ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $text ) . '</span>';
}

function aegis_badges_build_inline_style( $vars ) {
	$bg          = isset( $vars['bg'] ) ? sanitize_hex_color( $vars['bg'] ) : '';
	$fg          = isset( $vars['fg'] ) ? sanitize_hex_color( $vars['fg'] ) : '';
	$px          = isset( $vars['px'] ) ? floatval( $vars['px'] ) : 0;
	$py          = isset( $vars['py'] ) ? floatval( $vars['py'] ) : 0;
	$radius      = isset( $vars['radius'] ) ? floatval( $vars['radius'] ) : 0;
	$font_size   = isset( $vars['font_size'] ) ? floatval( $vars['font_size'] ) : 12;
	$font_weight = isset( $vars['font_weight'] ) ? floatval( $vars['font_weight'] ) : 700;
	$top         = isset( $vars['top'] ) ? floatval( $vars['top'] ) : 0;
	$right       = isset( $vars['right'] ) ? floatval( $vars['right'] ) : 0;

	$style = array();

	if ( $bg ) {
		$style[] = '--bg:' . $bg;
	}
	if ( $fg ) {
		$style[] = '--fg:' . $fg;
	}

	$style[] = '--px:' . $px . 'px';
	$style[] = '--py:' . $py . 'px';
	$style[] = '--r:' . $radius . 'px';
	$style[] = '--fs:' . $font_size . 'px';
	$style[] = '--fw:' . $font_weight;
	$style[] = '--top:' . $top . 'px';
	$style[] = '--right:' . $right . 'px';

	return implode( ';', $style );
}

function aegis_badges_filter_wc_product_sale_badge_block( $block_content, $block, $instance ) {
	$settings = Aegis_Badges::get_settings();
	if ( $settings['enable_badges'] !== 'yes' ) {
		return $block_content;
	}

	if ( $settings['mode'] === 'default' ) {
		return $block_content;
	}

	if ( $settings['mode'] === 'hide' ) {
		return '';
	}

	$product_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;
	$product    = $product_id ? wc_get_product( $product_id ) : null;
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	if ( ! aegis_badges_should_render_badge( $product ) ) {
		return '';
	}

	return aegis_badges_render_badge_html( $product );
}
