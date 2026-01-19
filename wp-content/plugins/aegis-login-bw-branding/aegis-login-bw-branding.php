<?php
/**
 * Plugin Name: AEGIS Login Branding (B/W)
 * Description: Black/white minimalist branding for the WordPress login screen with configurable logo and visibility toggles.
 * Version: 1.0.0
 * Author: AEGIS
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AEGIS_LOGIN_BW_BRANDING_OPTION', 'aegis_login_bw_branding_options' );

define( 'AEGIS_LOGIN_BW_BRANDING_VERSION', '1.0.0' );

define( 'AEGIS_LOGIN_BW_BRANDING_DIR', plugin_dir_path( __FILE__ ) );

define( 'AEGIS_LOGIN_BW_BRANDING_URL', plugin_dir_url( __FILE__ ) );

/**
 * Defaults.
 *
 * @return array
 */
function aegis_login_bw_branding_defaults() {
	return array(
		'logo_id'                => 0,
		'logo_width'             => 240,
		'logo_height'            => 80,
		'form_width'             => 380,
		'hide_language_switcher' => 1,
		'hide_nav_links'         => 1,
		'hide_back_to_blog'      => 1,
		'hide_privacy_policy'    => 1,
		'hide_remember_me'       => 0,
	);
}

/**
 * Get options merged with defaults.
 *
 * @return array
 */
function aegis_login_bw_branding_get_options() {
	$defaults = aegis_login_bw_branding_defaults();
	$options  = get_option( AEGIS_LOGIN_BW_BRANDING_OPTION, array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	return wp_parse_args( $options, $defaults );
}

/**
 * Sanitize options.
 *
 * @param array $input Input array.
 * @return array
 */
function aegis_login_bw_branding_sanitize_options( $input ) {
	$defaults = aegis_login_bw_branding_defaults();
	$input    = is_array( $input ) ? $input : array();

	$sanitized = array();
	$sanitized['logo_id']    = absint( $input['logo_id'] ?? $defaults['logo_id'] );
	$sanitized['logo_width'] = absint( $input['logo_width'] ?? $defaults['logo_width'] );
	$sanitized['logo_height'] = absint( $input['logo_height'] ?? $defaults['logo_height'] );
	$sanitized['form_width'] = absint( $input['form_width'] ?? $defaults['form_width'] );

	$sanitized['logo_width']  = min( max( $sanitized['logo_width'], 40 ), 600 );
	$sanitized['logo_height'] = min( max( $sanitized['logo_height'], 40 ), 300 );
	$sanitized['form_width']  = min( max( $sanitized['form_width'], 280 ), 520 );

	$checkboxes = array(
		'hide_language_switcher',
		'hide_nav_links',
		'hide_back_to_blog',
		'hide_privacy_policy',
		'hide_remember_me',
	);

	foreach ( $checkboxes as $checkbox ) {
		$sanitized[ $checkbox ] = empty( $input[ $checkbox ] ) ? 0 : 1;
	}

	return $sanitized;
}

/**
 * Register settings.
 */
function aegis_login_bw_branding_register_settings() {
	register_setting(
		'aegis_login_bw_branding_settings',
		AEGIS_LOGIN_BW_BRANDING_OPTION,
		array(
			'sanitize_callback' => 'aegis_login_bw_branding_sanitize_options',
		)
	);
}
add_action( 'admin_init', 'aegis_login_bw_branding_register_settings' );

/**
 * Add settings page.
 */
function aegis_login_bw_branding_add_settings_page() {
	add_options_page(
		__( 'Login Branding (B/W)', 'aegis-login-bw-branding' ),
		__( 'Login Branding (B/W)', 'aegis-login-bw-branding' ),
		'manage_options',
		'aegis-login-bw-branding',
		'aegis_login_bw_branding_render_settings_page'
	);
}
add_action( 'admin_menu', 'aegis_login_bw_branding_add_settings_page' );

/**
 * Enqueue admin assets.
 *
 * @param string $hook Hook suffix.
 */
function aegis_login_bw_branding_admin_assets( $hook ) {
	if ( 'settings_page_aegis-login-bw-branding' !== $hook ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_style(
		'aegis-login-bw-branding-admin',
		AEGIS_LOGIN_BW_BRANDING_URL . 'assets/admin.css',
		array(),
		AEGIS_LOGIN_BW_BRANDING_VERSION
	);
	wp_enqueue_script(
		'aegis-login-bw-branding-admin',
		AEGIS_LOGIN_BW_BRANDING_URL . 'assets/admin.js',
		array( 'jquery' ),
		AEGIS_LOGIN_BW_BRANDING_VERSION,
		true
	);

	wp_localize_script(
		'aegis-login-bw-branding-admin',
		'aegisLoginBwBranding',
		array(
			'placeholder' => AEGIS_LOGIN_BW_BRANDING_URL . 'assets/logo-placeholder.svg',
			'title'       => __( 'Select or Upload Logo', 'aegis-login-bw-branding' ),
			'button'      => __( 'Use this logo', 'aegis-login-bw-branding' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'aegis_login_bw_branding_admin_assets' );

/**
 * Render settings page.
 */
function aegis_login_bw_branding_render_settings_page() {
	$options = aegis_login_bw_branding_get_options();
	$logo_id = absint( $options['logo_id'] );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	if ( empty( $logo_url ) ) {
		$logo_url = AEGIS_LOGIN_BW_BRANDING_URL . 'assets/logo-placeholder.svg';
	}
	?>
	<div class="wrap aegis-login-bw-branding">
		<h1><?php esc_html_e( 'Login Branding (B/W)', 'aegis-login-bw-branding' ); ?></h1>
		<form action="options.php" method="post">
			<?php settings_fields( 'aegis_login_bw_branding_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Logo', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<div class="aegis-login-bw-branding__logo-row">
							<img class="aegis-login-bw-branding__logo-preview" src="<?php echo esc_url( $logo_url ); ?>" alt="" />
							<input type="hidden" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[logo_id]" value="<?php echo esc_attr( $logo_id ); ?>" />
							<button type="button" class="button aegis-login-bw-branding__upload"><?php esc_html_e( 'Select Logo', 'aegis-login-bw-branding' ); ?></button>
							<button type="button" class="button button-secondary aegis-login-bw-branding__remove"><?php esc_html_e( 'Remove', 'aegis-login-bw-branding' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Upload a logo from the media library. Leave empty to use the placeholder.', 'aegis-login-bw-branding' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Logo Width (px)', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<input type="number" min="40" max="600" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[logo_width]" value="<?php echo esc_attr( $options['logo_width'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Logo Height (px)', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<input type="number" min="40" max="300" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[logo_height]" value="<?php echo esc_attr( $options['logo_height'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Login Form Width (px)', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<input type="number" min="280" max="520" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[form_width]" value="<?php echo esc_attr( $options['form_width'] ); ?>" />
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Hide Elements', 'aegis-login-bw-branding' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Language Switcher', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[hide_language_switcher]" value="1" <?php checked( $options['hide_language_switcher'], 1 ); ?> />
							<?php esc_html_e( 'Hide language switcher', 'aegis-login-bw-branding' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Navigation Links', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[hide_nav_links]" value="1" <?php checked( $options['hide_nav_links'], 1 ); ?> />
							<?php esc_html_e( 'Hide navigation links', 'aegis-login-bw-branding' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Back to Blog', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[hide_back_to_blog]" value="1" <?php checked( $options['hide_back_to_blog'], 1 ); ?> />
							<?php esc_html_e( 'Hide back to blog', 'aegis-login-bw-branding' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Privacy Policy Link', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[hide_privacy_policy]" value="1" <?php checked( $options['hide_privacy_policy'], 1 ); ?> />
							<?php esc_html_e( 'Hide privacy policy link', 'aegis-login-bw-branding' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Remember Me', 'aegis-login-bw-branding' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( AEGIS_LOGIN_BW_BRANDING_OPTION ); ?>[hide_remember_me]" value="1" <?php checked( $options['hide_remember_me'], 1 ); ?> />
							<?php esc_html_e( 'Hide remember me', 'aegis-login-bw-branding' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Login styles.
 */
function aegis_login_bw_branding_login_assets() {
	$options = aegis_login_bw_branding_get_options();

	wp_enqueue_style(
		'aegis-login-bw-branding-login',
		AEGIS_LOGIN_BW_BRANDING_URL . 'assets/login.css',
		array(),
		AEGIS_LOGIN_BW_BRANDING_VERSION
	);

	$logo_url = '';
	if ( ! empty( $options['logo_id'] ) ) {
		$logo_url = wp_get_attachment_image_url( absint( $options['logo_id'] ), 'full' );
	}
	if ( empty( $logo_url ) ) {
		$logo_url = AEGIS_LOGIN_BW_BRANDING_URL . 'assets/logo-placeholder.svg';
	}

	$custom_css  = '#login h1 a {';
	$custom_css .= 'background-image: url("' . esc_url( $logo_url ) . '");';
	$custom_css .= 'width:' . absint( $options['logo_width'] ) . 'px;';
	$custom_css .= 'height:' . absint( $options['logo_height'] ) . 'px;';
	$custom_css .= 'background-size: contain;';
	$custom_css .= '}
';
	$custom_css .= '#login {width:' . absint( $options['form_width'] ) . 'px;}
';

	if ( ! empty( $options['hide_language_switcher'] ) ) {
		$custom_css .= '.login .language-switcher {display:none;}
';
	}
	if ( ! empty( $options['hide_nav_links'] ) ) {
		$custom_css .= '.login #nav {display:none;}
';
	}
	if ( ! empty( $options['hide_back_to_blog'] ) ) {
		$custom_css .= '.login #backtoblog {display:none;}
';
	}
	if ( ! empty( $options['hide_privacy_policy'] ) ) {
		$custom_css .= '.login .privacy-policy-page-link {display:none;}
';
	}
	if ( ! empty( $options['hide_remember_me'] ) ) {
		$custom_css .= '.login .forgetmenot {display:none;}
';
	}

	wp_add_inline_style( 'aegis-login-bw-branding-login', $custom_css );
}
add_action( 'login_enqueue_scripts', 'aegis_login_bw_branding_login_assets' );

/**
 * Login header url.
 *
 * @return string
 */
function aegis_login_bw_branding_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'aegis_login_bw_branding_login_header_url' );

/**
 * Login header text.
 *
 * @return string
 */
function aegis_login_bw_branding_login_header_text() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'aegis_login_bw_branding_login_header_text' );
