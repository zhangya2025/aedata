<?php
/**
 * Plugin Name: Aegis Badges
 * Description: Custom WooCommerce sale badges with presets and per-product overrides.
 * Version: 1.0.0
 * Author: Aegis
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Aegis_Badges' ) ) {
	class Aegis_Badges {
		const OPTION_KEY = 'aegis_badges_settings';

		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		public function init() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			require_once __DIR__ . '/includes/admin-settings.php';
			require_once __DIR__ . '/includes/product-meta.php';

			add_action( 'init', array( $this, 'register_hooks' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		public static function get_default_settings() {
			return array(
				'enable_badges'   => 'yes',
				'mode'            => 'replace',
				'default_preset'  => 'a',
				'default_text'    => 'SALE',
			);
		}

		public static function get_settings() {
			$defaults = self::get_default_settings();
			$settings = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}

			$settings = wp_parse_args( $settings, $defaults );
			$settings['enable_badges']  = $settings['enable_badges'] === 'yes' ? 'yes' : 'no';
			$settings['mode']           = in_array( $settings['mode'], array( 'replace', 'hide', 'default' ), true ) ? $settings['mode'] : $defaults['mode'];
			$settings['default_preset'] = in_array( $settings['default_preset'], array( 'a', 'b', 'c' ), true ) ? $settings['default_preset'] : $defaults['default_preset'];
			$settings['default_text']   = is_string( $settings['default_text'] ) && $settings['default_text'] !== '' ? $settings['default_text'] : $defaults['default_text'];

			return $settings;
		}

		public function register_hooks() {
			$settings = self::get_settings();

			if ( $settings['enable_badges'] !== 'yes' ) {
				return;
			}

			if ( $settings['mode'] === 'default' ) {
				return;
			}

			remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
			remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );

			if ( $settings['mode'] === 'hide' ) {
				return;
			}

			add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'render_badge' ), 10 );
			add_action( 'woocommerce_before_single_product_summary', array( $this, 'render_badge' ), 10 );
		}

		public function enqueue_assets() {
			if ( ! function_exists( 'is_shop' ) ) {
				return;
			}

			if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_product() ) ) {
				return;
			}

			wp_enqueue_style(
				'aegis-badges',
				plugins_url( 'assets/badges.css', __FILE__ ),
				array(),
				'1.0.0'
			);
		}

		public function render_badge() {
			global $product;

			if ( ! $product instanceof WC_Product ) {
				return;
			}

			if ( ! $product->is_on_sale() ) {
				return;
			}

			$settings = self::get_settings();
			$data     = Aegis_Badges_Product_Meta::get_effective_badge_data( $product, $settings );

			if ( ! $data['show'] ) {
				return;
			}

			$label = $data['text'];
			$preset = $data['preset'];

			if ( $label === '' ) {
				return;
			}

			echo '<span class="aegis-badge aegis-badge--preset-' . esc_attr( $preset ) . '" data-preset="' . esc_attr( $preset ) . '">' . esc_html( $label ) . '</span>';
		}
	}
}

new Aegis_Badges();
