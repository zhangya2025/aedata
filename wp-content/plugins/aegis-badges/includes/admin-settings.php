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
			$section  = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : 'general';
			$settings = Aegis_Badges::get_settings();
			?>
			<h2><?php esc_html_e( 'Aegis Badges', 'aegis-badges' ); ?></h2>
			<?php
			echo '<nav class="nav-tab-wrapper wc-nav-tab-wrapper">';
			printf(
				'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=aegis_badges&section=general' ) ),
				$section === 'general' ? 'nav-tab-active' : '',
				esc_html__( 'General', 'aegis-badges' )
			);
			printf(
				'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=aegis_badges&section=presets' ) ),
				$section === 'presets' ? 'nav-tab-active' : '',
				esc_html__( 'Presets', 'aegis-badges' )
			);
			echo '</nav>';

			if ( $section === 'presets' ) {
				Aegis_Badges_Admin_Presets::render_settings();
				return;
			}
			?>
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
			<th scope="row"><?php esc_html_e( 'Display strategy', 'aegis-badges' ); ?></th>
			<td>
				<select name="aegis_badges_settings[display_strategy]">
					<option value="sale_all" <?php selected( $settings['display_strategy'], 'sale_all' ); ?>><?php esc_html_e( 'Show for all on-sale products', 'aegis-badges' ); ?></option>
					<option value="opt_in_only" <?php selected( $settings['display_strategy'], 'opt_in_only' ); ?>><?php esc_html_e( 'Opt-in only: show only when matched', 'aegis-badges' ); ?></option>
				</select>
			</td>
		</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default preset', 'aegis-badges' ); ?></th>
						<td>
							<select name="aegis_badges_settings[default_preset]">
								<option value="preset_a" <?php selected( $settings['default_preset'], 'preset_a' ); ?>><?php esc_html_e( 'Preset A', 'aegis-badges' ); ?></option>
								<option value="preset_b" <?php selected( $settings['default_preset'], 'preset_b' ); ?>><?php esc_html_e( 'Preset B', 'aegis-badges' ); ?></option>
								<option value="preset_c" <?php selected( $settings['default_preset'], 'preset_c' ); ?>><?php esc_html_e( 'Preset C', 'aegis-badges' ); ?></option>
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
			$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : 'general';

			if ( $section === 'presets' ) {
				Aegis_Badges_Admin_Presets::save_settings();
				return;
			}

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

			$display_strategy = isset( $raw_settings['display_strategy'] ) ? sanitize_text_field( $raw_settings['display_strategy'] ) : 'sale_all';
			$settings['display_strategy'] = in_array( $display_strategy, array( 'sale_all', 'opt_in_only' ), true ) ? $display_strategy : 'sale_all';

			$preset = isset( $raw_settings['default_preset'] ) ? sanitize_text_field( $raw_settings['default_preset'] ) : 'preset_a';
			$settings['default_preset'] = in_array( $preset, array( 'preset_a', 'preset_b', 'preset_c', 'a', 'b', 'c' ), true ) ? Aegis_Badges::normalize_preset_id( $preset ) : 'preset_a';

			$text = isset( $raw_settings['default_text'] ) ? sanitize_text_field( $raw_settings['default_text'] ) : '';
			$settings['default_text'] = $text !== '' ? $text : 'SALE';

			update_option( Aegis_Badges::OPTION_KEY, $settings );
		}
	}
}

new Aegis_Badges_Admin_Settings();
