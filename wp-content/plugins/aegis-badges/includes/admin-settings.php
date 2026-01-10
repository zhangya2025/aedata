<?php

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Aegis_Badges_Admin_Settings' ) ) {
	class Aegis_Badges_Admin_Settings {
		public function __construct() {
			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_tab' ), 50 );
			add_action( 'woocommerce_settings_tabs_aegis_badges', array( $this, 'render_settings' ) );
			add_action( 'woocommerce_update_options_aegis_badges', array( $this, 'save_settings' ) );
		}

		public function add_tab( $tabs ) {
			$tabs['aegis_badges'] = __( 'Aegis Badges', 'aegis-badges' );
			return $tabs;
		}

		public function render_settings() {
			$settings = Aegis_Badges::get_settings();
			?>
			<h2><?php esc_html_e( 'Aegis Badges', 'aegis-badges' ); ?></h2>
			<?php wp_nonce_field( 'aegis_badges_settings_save', 'aegis_badges_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable badges', 'aegis-badges' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="aegis_badges_settings[enable_badges]" value="yes" <?php checked( $settings['enable_badges'], 'yes' ); ?> />
							<?php esc_html_e( 'Enable custom badge handling', 'aegis-badges' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mode', 'aegis-badges' ); ?></th>
					<td>
						<select name="aegis_badges_settings[mode]">
							<option value="replace" <?php selected( $settings['mode'], 'replace' ); ?>><?php esc_html_e( 'Replace Woo Sale badge', 'aegis-badges' ); ?></option>
							<option value="hide" <?php selected( $settings['mode'], 'hide' ); ?>><?php esc_html_e( 'Hide all sale badges', 'aegis-badges' ); ?></option>
							<option value="default" <?php selected( $settings['mode'], 'default' ); ?>><?php esc_html_e( 'Use Woo default', 'aegis-badges' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default preset', 'aegis-badges' ); ?></th>
					<td>
						<select name="aegis_badges_settings[default_preset]">
							<option value="a" <?php selected( $settings['default_preset'], 'a' ); ?>><?php esc_html_e( 'Preset A', 'aegis-badges' ); ?></option>
							<option value="b" <?php selected( $settings['default_preset'], 'b' ); ?>><?php esc_html_e( 'Preset B', 'aegis-badges' ); ?></option>
							<option value="c" <?php selected( $settings['default_preset'], 'c' ); ?>><?php esc_html_e( 'Preset C', 'aegis-badges' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default text', 'aegis-badges' ); ?></th>
					<td>
						<input type="text" name="aegis_badges_settings[default_text]" value="<?php echo esc_attr( $settings['default_text'] ); ?>" class="regular-text" />
					</td>
				</tr>
			</table>
			<?php
		}

		public function save_settings() {
			if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( ! isset( $_POST['aegis_badges_settings'] ) ) {
				return;
			}

			if ( ! isset( $_POST['aegis_badges_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aegis_badges_settings_nonce'] ) ), 'aegis_badges_settings_save' ) ) {
				return;
			}

			$raw_settings = wp_unslash( $_POST['aegis_badges_settings'] );
			$settings     = array();

			$settings['enable_badges'] = isset( $raw_settings['enable_badges'] ) && $raw_settings['enable_badges'] === 'yes' ? 'yes' : 'no';

			$mode = isset( $raw_settings['mode'] ) ? sanitize_text_field( $raw_settings['mode'] ) : 'replace';
			$settings['mode'] = in_array( $mode, array( 'replace', 'hide', 'default' ), true ) ? $mode : 'replace';

			$preset = isset( $raw_settings['default_preset'] ) ? sanitize_text_field( $raw_settings['default_preset'] ) : 'a';
			$settings['default_preset'] = in_array( $preset, array( 'a', 'b', 'c' ), true ) ? $preset : 'a';

			$text = isset( $raw_settings['default_text'] ) ? sanitize_text_field( $raw_settings['default_text'] ) : '';
			$settings['default_text'] = $text !== '' ? $text : 'SALE';

			update_option( Aegis_Badges::OPTION_KEY, $settings );
		}
	}
}

new Aegis_Badges_Admin_Settings();
