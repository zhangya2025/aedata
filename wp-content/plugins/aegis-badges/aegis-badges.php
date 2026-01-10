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
		const OPTION_KEY         = 'aegis_badges_settings';
		const PRESETS_OPTION_KEY = 'aegis_badges_presets';
		const RULES_OPTION_KEY   = 'aegis_badges_rules';

		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		public function init() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			require_once __DIR__ . '/includes/admin-settings.php';
			require_once __DIR__ . '/includes/admin-presets.php';
			require_once __DIR__ . '/includes/renderer.php';
			require_once __DIR__ . '/includes/rules.php';
			require_once __DIR__ . '/includes/product-meta.php';

			add_action( 'init', array( $this, 'register_hooks' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

			public static function get_default_settings() {
				return array(
					'enable_badges'   => 'yes',
					'mode'            => 'replace',
					'display_strategy' => 'sale_all',
					'default_preset'  => 'preset_a',
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
				$settings['display_strategy'] = in_array( $settings['display_strategy'], array( 'sale_all', 'opt_in_only' ), true ) ? $settings['display_strategy'] : $defaults['display_strategy'];
				$settings['default_preset'] = self::normalize_preset_id( $settings['default_preset'] );
				$settings['default_text']   = is_string( $settings['default_text'] ) && $settings['default_text'] !== '' ? $settings['default_text'] : $defaults['default_text'];

			return $settings;
		}

		public static function normalize_preset_id( $preset_id ) {
			$valid_presets = array( 'preset_a', 'preset_b', 'preset_c' );

			if ( in_array( $preset_id, $valid_presets, true ) ) {
				return $preset_id;
			}

			if ( in_array( $preset_id, array( 'a', 'b', 'c' ), true ) ) {
				return 'preset_' . $preset_id;
			}

			return self::get_default_settings()['default_preset'];
		}

		public static function get_default_presets() {
			return array(
				'preset_a' => array(
					'label'    => 'Preset A',
					'template' => 'pill',
					'text'     => 'SALE',
					'vars'     => array(
						'bg'          => '#e02424',
						'fg'          => '#ffffff',
						'px'          => 14,
						'py'          => 6,
						'radius'      => 999,
						'font_size'   => 12,
						'font_weight' => 700,
						'top'         => 12,
						'right'       => 12,
					),
				),
				'preset_b' => array(
					'label'    => 'Preset B',
					'template' => 'ribbon',
					'text'     => 'SALE',
					'vars'     => array(
						'bg'          => '#1f8b4c',
						'fg'          => '#ffffff',
						'px'          => 12,
						'py'          => 6,
						'radius'      => 4,
						'font_size'   => 12,
						'font_weight' => 700,
						'top'         => 12,
						'right'       => 12,
					),
				),
				'preset_c' => array(
					'label'    => 'Preset C',
					'template' => 'corner',
					'text'     => 'SALE',
					'vars'     => array(
						'bg'          => '#3b82f6',
						'fg'          => '#ffffff',
						'px'          => 0,
						'py'          => 0,
						'radius'      => 12,
						'font_size'   => 12,
						'font_weight' => 700,
						'top'         => 0,
						'right'       => 0,
					),
				),
			);
		}

		public static function get_presets() {
			$defaults = self::get_default_presets();
			$presets  = get_option( self::PRESETS_OPTION_KEY, array() );
			if ( ! is_array( $presets ) ) {
				$presets = array();
			}

			return wp_parse_args( $presets, $defaults );
		}

		public static function get_rules() {
			$rules = get_option( self::RULES_OPTION_KEY, array() );
			if ( ! is_array( $rules ) ) {
				return array();
			}

			return $rules;
		}

			public function register_hooks() {
				$settings = self::get_settings();

				if ( $settings['enable_badges'] !== 'yes' ) {
					return;
				}

				if ( $settings['mode'] === 'default' ) {
					return;
				}

				if ( ! is_admin() ) {
					add_filter( 'render_block_woocommerce/product-sale-badge', 'aegis_badges_filter_wc_product_sale_badge_block', 9999, 3 );
				}

				remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
				remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );

			add_filter( 'woocommerce_blocks_product_grid_item_html', array( $this, 'filter_blocks_grid_item' ), 9999, 3 );

			if ( $settings['mode'] === 'hide' ) {
				add_filter( 'woocommerce_sale_flash', '__return_empty_string', 10, 3 );
				return;
			}

			add_filter( 'woocommerce_sale_flash', array( $this, 'filter_sale_flash' ), 10, 3 );
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

			$settings = self::get_settings();
			if ( $settings['enable_badges'] === 'yes' && $settings['mode'] === 'hide' ) {
				wp_add_inline_style(
					'aegis-badges',
					'.wc-block-components-product-sale-badge,' .
					'.wc-block-components-product-sale-badge__text,' .
					'.wc-block-grid__product-onsale,' .
					'.woocommerce span.onsale{display:none!important;}'
				);
			}
		}

		public function enqueue_admin_assets( $hook ) {
			if ( $hook !== 'woocommerce_page_wc-settings' ) {
				return;
			}

			if ( ! isset( $_GET['tab'] ) || sanitize_text_field( wp_unslash( $_GET['tab'] ) ) !== 'aegis_badges' ) {
				return;
			}

			wp_enqueue_style( 'wp-color-picker' );
			if ( function_exists( 'wc_enqueue_js' ) ) {
				wp_enqueue_style( 'woocommerce_admin_styles' );
				wp_enqueue_script( 'wc-enhanced-select' );
			}
			wp_enqueue_style(
				'aegis-badges-frontend',
				plugins_url( 'assets/badges.css', __FILE__ ),
				array(),
				'1.0.0'
			);
			wp_enqueue_style(
				'aegis-badges-admin',
				plugins_url( 'assets/admin-presets.css', __FILE__ ),
				array(),
				'1.0.0'
			);
			wp_enqueue_script(
				'aegis-badges-admin',
				plugins_url( 'assets/admin-presets.js', __FILE__ ),
				array( 'jquery', 'wp-color-picker' ),
				'1.0.0',
				true
			);
			if ( function_exists( 'wc_enqueue_js' ) ) {
				wc_enqueue_js( '' );
			}
		}

			public function render_badge() {
				global $product;

				if ( ! $product instanceof WC_Product ) {
					return;
				}

				if ( ! aegis_badges_should_render_badge( $product ) ) {
					return;
				}

				$badge_html = aegis_badges_render_badge_html( $product );
				if ( $badge_html === '' ) {
					return;
				}

			echo $badge_html;
		}

			public function filter_sale_flash( $html, $post, $product ) {
				if ( ! $product instanceof WC_Product ) {
					return $html;
				}

				if ( ! aegis_badges_should_render_badge( $product ) ) {
					return '';
				}

				$badge_html = aegis_badges_render_badge_html( $product );
				if ( $badge_html === '' ) {
					return '';
				}

			return $badge_html;
		}

		public function filter_blocks_grid_item( $html, $data, $product ) {
			$settings = self::get_settings();
			if ( $settings['enable_badges'] !== 'yes' ) {
				return $html;
			}

			if ( $settings['mode'] === 'hide' ) {
				return aegis_badges_strip_blocks_sale_badge( $html );
			}

			if ( ! $product instanceof WC_Product ) {
				return aegis_badges_strip_blocks_sale_badge( $html );
			}

			$clean_html = aegis_badges_strip_blocks_sale_badge( $html );

			if ( ! aegis_badges_should_render_badge( $product ) ) {
				return $clean_html;
			}

				$badge_html = aegis_badges_render_badge_html( $product );
				if ( $badge_html === '' ) {
					return $clean_html;
				}

			return aegis_badges_inject_badge_into_block_item( $clean_html, $badge_html );
		}
	}
}

new Aegis_Badges();
