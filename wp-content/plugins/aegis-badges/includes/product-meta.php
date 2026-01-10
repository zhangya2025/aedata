<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Aegis_Badges_Product_Meta' ) ) {
	class Aegis_Badges_Product_Meta {
		const META_BEHAVIOR = '_aegis_badge_behavior';
		const META_PRESET   = '_aegis_badge_preset';
		const META_TEXT     = '_aegis_badge_text';

		public function __construct() {
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
			add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_meta' ) );
		}

		public function add_tab( $tabs ) {
			$tabs['aegis_badges'] = array(
				'label'    => __( 'Badges', 'aegis-badges' ),
				'target'   => 'aegis_badges_panel',
				'class'    => array( 'show_if_simple', 'show_if_variable' ),
				'priority' => 70,
			);

			return $tabs;
		}

		public function render_panel() {
			global $post;

			$behavior = get_post_meta( $post->ID, self::META_BEHAVIOR, true );
			$preset   = get_post_meta( $post->ID, self::META_PRESET, true );
			$text     = get_post_meta( $post->ID, self::META_TEXT, true );
			?>
			<div id="aegis_badges_panel" class="panel woocommerce_options_panel">
				<div class="options_group">
					<?php wp_nonce_field( 'aegis_badges_meta_save', 'aegis_badges_meta_nonce' ); ?>
					<p class="form-field">
						<label for="aegis_badge_behavior"><?php esc_html_e( 'Badge behavior', 'aegis-badges' ); ?></label>
						<select id="aegis_badge_behavior" name="aegis_badge_behavior">
							<option value="inherit" <?php selected( $behavior, 'inherit' ); ?>><?php esc_html_e( 'Inherit', 'aegis-badges' ); ?></option>
							<option value="off" <?php selected( $behavior, 'off' ); ?>><?php esc_html_e( 'Off', 'aegis-badges' ); ?></option>
							<option value="on" <?php selected( $behavior, 'on' ); ?>><?php esc_html_e( 'On', 'aegis-badges' ); ?></option>
						</select>
					</p>
					<p class="form-field">
						<label for="aegis_badge_preset"><?php esc_html_e( 'Preset', 'aegis-badges' ); ?></label>
						<select id="aegis_badge_preset" name="aegis_badge_preset">
							<option value="inherit" <?php selected( $preset, 'inherit' ); ?>><?php esc_html_e( 'Inherit', 'aegis-badges' ); ?></option>
							<option value="a" <?php selected( $preset, 'a' ); ?>><?php esc_html_e( 'Preset A', 'aegis-badges' ); ?></option>
							<option value="b" <?php selected( $preset, 'b' ); ?>><?php esc_html_e( 'Preset B', 'aegis-badges' ); ?></option>
							<option value="c" <?php selected( $preset, 'c' ); ?>><?php esc_html_e( 'Preset C', 'aegis-badges' ); ?></option>
						</select>
					</p>
					<p class="form-field">
						<label for="aegis_badge_text"><?php esc_html_e( 'Text override', 'aegis-badges' ); ?></label>
						<input type="text" id="aegis_badge_text" name="aegis_badge_text" value="<?php echo esc_attr( $text ); ?>" />
					</p>
				</div>
			</div>
			<?php
		}

		public function save_meta( $product ) {
			if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( ! isset( $_POST['aegis_badges_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aegis_badges_meta_nonce'] ) ), 'aegis_badges_meta_save' ) ) {
				return;
			}

			$behavior = isset( $_POST['aegis_badge_behavior'] ) ? sanitize_text_field( wp_unslash( $_POST['aegis_badge_behavior'] ) ) : 'inherit';
			if ( ! in_array( $behavior, array( 'inherit', 'off', 'on' ), true ) ) {
				$behavior = 'inherit';
			}
			$product->update_meta_data( self::META_BEHAVIOR, $behavior );

			$preset = isset( $_POST['aegis_badge_preset'] ) ? sanitize_text_field( wp_unslash( $_POST['aegis_badge_preset'] ) ) : 'inherit';
			if ( ! in_array( $preset, array( 'inherit', 'a', 'b', 'c' ), true ) ) {
				$preset = 'inherit';
			}
			$product->update_meta_data( self::META_PRESET, $preset );

			$text = isset( $_POST['aegis_badge_text'] ) ? sanitize_text_field( wp_unslash( $_POST['aegis_badge_text'] ) ) : '';
			$product->update_meta_data( self::META_TEXT, $text );
		}

		public static function get_effective_badge_data( $product, $settings ) {
			$behavior = get_post_meta( $product->get_id(), self::META_BEHAVIOR, true );
			$preset   = get_post_meta( $product->get_id(), self::META_PRESET, true );
			$text     = get_post_meta( $product->get_id(), self::META_TEXT, true );

			$show = true;
			if ( $behavior === 'off' ) {
				$show = false;
			} elseif ( $behavior === 'on' ) {
				$show = true;
			} else {
				$show = $settings['enable_badges'] === 'yes';
			}

			if ( ! in_array( $preset, array( 'a', 'b', 'c' ), true ) ) {
				$preset = $settings['default_preset'];
			}

			if ( $text === '' ) {
				$text = $settings['default_text'];
			}

			return array(
				'show'   => $show,
				'preset' => $preset,
				'text'   => $text,
			);
		}
	}
}

new Aegis_Badges_Product_Meta();
