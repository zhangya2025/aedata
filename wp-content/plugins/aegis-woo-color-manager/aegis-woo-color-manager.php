<?php
/**
 * Plugin Name: Aegis Woo Color Manager
 * Description: Centralized color token overrides for WooCommerce frontend.
 * Version: 0.1.0
 * Author: Aegis
 * Text Domain: aegis-woo-color-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Default token values.
 *
 * @return array<string, string>
 */
function aegis_woo_color_manager_default_tokens() {
	return array(
		'aegis_fg'             => '#111111',
		'aegis_bg'             => '#ffffff',
		'aegis_surface'        => '#f2f2f2',
		'aegis_muted'          => '#6b7280',
		'aegis_border'         => '#d1d5db',
		'aegis_button_bg'      => '#111111',
		'aegis_button_fg'      => '#ffffff',
		'aegis_accent'         => '#111111',
		'aegis_link'           => '#111111',
		'aegis_success_bg'     => '#f2f2f2',
		'aegis_success_fg'     => '#111111',
		'aegis_success_border' => '#111111',
		'aegis_danger_bg'      => '#f2f2f2',
		'aegis_danger_fg'      => '#111111',
		'aegis_danger_border'  => '#111111',
		'aegis_warning_bg'     => '#f2f2f2',
		'aegis_warning_fg'     => '#111111',
		'aegis_warning_border' => '#111111',
		'aegis_info_bg'        => '#f2f2f2',
		'aegis_info_fg'        => '#111111',
		'aegis_info_border'    => '#111111',
	);
}

/**
 * Default scope.
 *
 * @return string
 */
function aegis_woo_color_manager_default_scope() {
	return 'woo_content_only';
}

/**
 * Get sanitized tokens merged with defaults.
 *
 * @return array<string, string>
 */
function aegis_woo_color_manager_get_tokens() {
	$defaults = aegis_woo_color_manager_default_tokens();
	$saved    = get_option( 'aegis_woo_color_tokens', array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	foreach ( $defaults as $key => $value ) {
		if ( isset( $saved[ $key ] ) ) {
			$sanitized = sanitize_hex_color( $saved[ $key ] );
			if ( $sanitized ) {
				$defaults[ $key ] = $sanitized;
			}
		}
	}

	return $defaults;
}

/**
 * Get scope setting.
 *
 * @return string
 */
function aegis_woo_color_manager_get_scope() {
	$scope = get_option( 'aegis_woo_color_scope', aegis_woo_color_manager_default_scope() );

	return 'global' === $scope ? 'global' : 'woo_content_only';
}

/**
 * Render token CSS for frontend.
 *
 * @return string
 */
function aegis_woo_color_manager_tokens_css() {
	$tokens   = aegis_woo_color_manager_get_tokens();
	$scope    = aegis_woo_color_manager_get_scope();
	$selector = ':root';

	if ( 'woo_content_only' === $scope ) {
		$selector = implode(
			",\n",
			array(
				'.woocommerce',
				'body.woocommerce main',
				'body.woocommerce-page main',
				'body.post-type-archive-product main',
				'body.single-product main',
				'body.woocommerce-cart main',
				'body.woocommerce-checkout main',
				'body.woocommerce-account main',
				'.aegis-mini-cart__drawer',
			)
		);
	}

	$lines = array(
		"$selector {",
		"\t--aegis-fg: {$tokens['aegis_fg']};",
		"\t--aegis-bg: {$tokens['aegis_bg']};",
		"\t--aegis-surface: {$tokens['aegis_surface']};",
		"\t--aegis-muted: {$tokens['aegis_muted']};",
		"\t--aegis-border: {$tokens['aegis_border']};",
		"\t--aegis-button-bg: {$tokens['aegis_button_bg']};",
		"\t--aegis-button-fg: {$tokens['aegis_button_fg']};",
		"\t--aegis-accent: {$tokens['aegis_accent']};",
		"\t--aegis-link: {$tokens['aegis_link']};",
		"\t--aegis-success-bg: {$tokens['aegis_success_bg']};",
		"\t--aegis-success-fg: {$tokens['aegis_success_fg']};",
		"\t--aegis-success-border: {$tokens['aegis_success_border']};",
		"\t--aegis-danger-bg: {$tokens['aegis_danger_bg']};",
		"\t--aegis-danger-fg: {$tokens['aegis_danger_fg']};",
		"\t--aegis-danger-border: {$tokens['aegis_danger_border']};",
		"\t--aegis-warning-bg: {$tokens['aegis_warning_bg']};",
		"\t--aegis-warning-fg: {$tokens['aegis_warning_fg']};",
		"\t--aegis-warning-border: {$tokens['aegis_warning_border']};",
		"\t--aegis-info-bg: {$tokens['aegis_info_bg']};",
		"\t--aegis-info-fg: {$tokens['aegis_info_fg']};",
		"\t--aegis-info-border: {$tokens['aegis_info_border']};",
		'}',
	);

	return implode( "\n", $lines );
}

/**
 * Enqueue frontend color overrides for WooCommerce pages.
 */
function aegis_woo_color_manager_enqueue_styles() {
	if ( is_admin() ) {
		return;
	}

	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}

	if ( ! ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
		return;
	}

	$base_dir = plugin_dir_path( __FILE__ );
	$base_url = plugin_dir_url( __FILE__ );
	$deps     = array();

	$maybe_deps = array(
		'woocommerce-general',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'wc-blocks-style',
		'wc-blocks-style-css',
		'wc-blocks-vendors-style',
	);

	foreach ( $maybe_deps as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) ) {
			$deps[] = $handle;
		}
	}

	$styles = array(
		'aegis-woo-color-manager-tokens'   => 'assets/css/00-tokens.css',
		'aegis-woo-color-manager-woo-vars' => 'assets/css/10-woo-vars.css',
		'aegis-woo-color-manager-notices'  => 'assets/css/20-notices.css',
		'aegis-woo-color-manager-buttons'  => 'assets/css/21-buttons.css',
		'aegis-woo-color-manager-mini'     => 'assets/css/30-mini-cart.css',
		'aegis-woo-color-manager-cart'     => 'assets/css/31-cart-page.css',
	);

	$previous_handle = '';

	foreach ( $styles as $handle => $relative_path ) {
		$path    = $base_dir . $relative_path;
		$url     = $base_url . $relative_path;
		$version = file_exists( $path ) ? filemtime( $path ) : '0.1.0';
		$handles = $deps;

		if ( $previous_handle ) {
			$handles[] = $previous_handle;
		}

		wp_enqueue_style( $handle, $url, $handles, $version );
		$previous_handle = $handle;
	}

	if ( isset( $styles['aegis-woo-color-manager-tokens'] ) ) {
		wp_add_inline_style( 'aegis-woo-color-manager-tokens', aegis_woo_color_manager_tokens_css() );
	}
}
add_action( 'wp_enqueue_scripts', 'aegis_woo_color_manager_enqueue_styles', 20 );

/**
 * Register plugin settings.
 */
function aegis_woo_color_manager_register_settings() {
	register_setting(
		'aegis_woo_color_manager',
		'aegis_woo_color_tokens',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'aegis_woo_color_manager_sanitize_tokens',
			'default'           => aegis_woo_color_manager_default_tokens(),
		)
	);

	register_setting(
		'aegis_woo_color_manager',
		'aegis_woo_color_scope',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'aegis_woo_color_manager_sanitize_scope',
			'default'           => aegis_woo_color_manager_default_scope(),
		)
	);

	$sections = aegis_woo_color_manager_token_sections();
	foreach ( $sections as $section_id => $section_label ) {
		add_settings_section(
			$section_id,
			$section_label,
			'__return_false',
			'aegis-woo-color-manager'
		);
	}

	add_settings_field(
		'aegis_woo_color_scope',
		esc_html__( '作用范围', 'aegis-woo-color-manager' ),
		'aegis_woo_color_manager_render_scope_field',
		'aegis-woo-color-manager',
		'aegis_woo_color_scope_section'
	);

	foreach ( aegis_woo_color_manager_token_fields() as $field ) {
		add_settings_field(
			$field['key'],
			esc_html( $field['label'] ),
			'aegis_woo_color_manager_render_field',
			'aegis-woo-color-manager',
			$field['section'],
			$field
		);
	}
}
add_action( 'admin_init', 'aegis_woo_color_manager_register_settings' );

/**
 * Define token sections.
 *
 * @return array<string, string>
 */
function aegis_woo_color_manager_token_sections() {
	return array(
		'aegis_woo_color_scope_section'  => __( '作用范围', 'aegis-woo-color-manager' ),
		'aegis_woo_color_base_section'   => __( '基础', 'aegis-woo-color-manager' ),
		'aegis_woo_color_button_section' => __( '按钮', 'aegis-woo-color-manager' ),
		'aegis_woo_color_link_section'   => __( '链接', 'aegis-woo-color-manager' ),
		'aegis_woo_color_notice_section' => __( '提示条（Notices）', 'aegis-woo-color-manager' ),
	);
}

/**
 * Define token fields.
 *
 * @return array<int, array<string, string>>
 */
function aegis_woo_color_manager_token_fields() {
	return array(
		array(
			'key'         => 'aegis_fg',
			'label'       => '--aegis-fg',
			'section'     => 'aegis_woo_color_base_section',
			'description' => '主要文字色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_bg',
			'label'       => '--aegis-bg',
			'section'     => 'aegis_woo_color_base_section',
			'description' => 'Woo 内容区背景色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_surface',
			'label'       => '--aegis-surface',
			'section'     => 'aegis_woo_color_base_section',
			'description' => '卡片/面板底色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_muted',
			'label'       => '--aegis-muted',
			'section'     => 'aegis_woo_color_base_section',
			'description' => '次要文字色（说明/辅助文本）。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_border',
			'label'       => '--aegis-border',
			'section'     => 'aegis_woo_color_base_section',
			'description' => '分割线/边框。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_button_bg',
			'label'       => '--aegis-button-bg',
			'section'     => 'aegis_woo_color_button_section',
			'description' => 'Woo 实心按钮背景（加入购物车/选择选项/返回商店/去结算）。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_button_fg',
			'label'       => '--aegis-button-fg',
			'section'     => 'aegis_woo_color_button_section',
			'description' => 'Woo 实心按钮文字色（与按钮背景反色）。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_accent',
			'label'       => '--aegis-accent',
			'section'     => 'aegis_woo_color_link_section',
			'description' => '强调色（如价格高亮/少量强调场景）。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_link',
			'label'       => '--aegis-link',
			'section'     => 'aegis_woo_color_link_section',
			'description' => 'Woo 页面链接色（例如“更换地址”等）。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_success_bg',
			'label'       => '--aegis-success-bg',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '成功提示条背景（购物车更新成功提示）。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_success_fg',
			'label'       => '--aegis-success-fg',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '成功提示条文字/图标色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_success_border',
			'label'       => '--aegis-success-border',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '成功提示条边框色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_danger_bg',
			'label'       => '--aegis-danger-bg',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '错误提示条背景。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_danger_fg',
			'label'       => '--aegis-danger-fg',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '错误提示条文字/图标色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_danger_border',
			'label'       => '--aegis-danger-border',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '错误提示条边框色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_warning_bg',
			'label'       => '--aegis-warning-bg',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '警告提示条背景。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_warning_fg',
			'label'       => '--aegis-warning-fg',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '警告提示条文字/图标色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_warning_border',
			'label'       => '--aegis-warning-border',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '警告提示条边框色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_info_bg',
			'label'       => '--aegis-info-bg',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '信息提示条背景。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_info_fg',
			'label'       => '--aegis-info-fg',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '信息提示条文字/图标色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
		array(
			'key'         => 'aegis_info_border',
			'label'       => '--aegis-info-border',
			'section'     => 'aegis_woo_color_notice_section',
			'description' => '信息提示条边框色。作用范围：Woo 内容区 + mini cart（默认）/ 全站（开启全站模式才生效）。',
		),
	);
}

/**
 * Sanitize tokens.
 *
 * @param array<string, string> $input Input tokens.
 * @return array<string, string>
 */
function aegis_woo_color_manager_sanitize_tokens( $input ) {
	$defaults = aegis_woo_color_manager_default_tokens();
	$output   = array();

	if ( ! is_array( $input ) ) {
		return $defaults;
	}

	foreach ( $defaults as $key => $value ) {
		$sanitized = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
		$output[ $key ] = $sanitized ? $sanitized : $value;
	}

	return $output;
}

/**
 * Sanitize scope.
 *
 * @param string $input Input scope.
 * @return string
 */
function aegis_woo_color_manager_sanitize_scope( $input ) {
	return 'global' === $input ? 'global' : 'woo_content_only';
}

/**
 * Render a color field.
 *
 * @param array<string, string> $field Field data.
 */
function aegis_woo_color_manager_render_field( $field ) {
	$tokens = aegis_woo_color_manager_get_tokens();
	$key    = $field['key'];
	$value  = isset( $tokens[ $key ] ) ? $tokens[ $key ] : '';

	printf(
		'<input type="text" class="aegis-color-field" name="aegis_woo_color_tokens[%1$s]" value="%2$s" data-default-color="%3$s" />',
		esc_attr( $key ),
		esc_attr( $value ),
		esc_attr( aegis_woo_color_manager_default_tokens()[ $key ] )
	);

	if ( ! empty( $field['description'] ) ) {
		printf(
			'<p class="description">%s</p>',
			esc_html( $field['description'] )
		);
	}
}

/**
 * Render scope field.
 */
function aegis_woo_color_manager_render_scope_field() {
	$scope = aegis_woo_color_manager_get_scope();
	?>
	<select name="aegis_woo_color_scope">
			<option value="woo_content_only" <?php selected( $scope, 'woo_content_only' ); ?>>
				<?php esc_html_e( '仅 Woo 主内容区 + mini cart（推荐）', 'aegis-woo-color-manager' ); ?>
			</option>
			<option value="global" <?php selected( $scope, 'global' ); ?>>
				<?php esc_html_e( '全站（会影响 header/footer）', 'aegis-woo-color-manager' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( '默认仅影响 Woo 主内容区与 mini cart，不影响页眉页脚；全站模式会影响包括 footer 在内的全站元素。', 'aegis-woo-color-manager' ); ?>
		</p>
	<?php
}

/**
 * Register admin menu.
 */
function aegis_woo_color_manager_admin_menu() {
	$capability = 'manage_options';
	$slug       = 'aegis-woo-color-manager';

	if ( class_exists( 'WooCommerce' ) ) {
		add_submenu_page(
			'woocommerce',
			__( 'Aegis Color Manager', 'aegis-woo-color-manager' ),
			__( 'Aegis Color Manager', 'aegis-woo-color-manager' ),
			$capability,
			$slug,
			'aegis_woo_color_manager_render_settings_page'
		);
	} else {
		add_options_page(
			__( 'Aegis Color Manager', 'aegis-woo-color-manager' ),
			__( 'Aegis Color Manager', 'aegis-woo-color-manager' ),
			$capability,
			$slug,
			'aegis_woo_color_manager_render_settings_page'
		);
	}
}
add_action( 'admin_menu', 'aegis_woo_color_manager_admin_menu' );

/**
 * Enqueue admin assets.
 *
 * @param string $hook Hook suffix.
 */
function aegis_woo_color_manager_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'aegis-woo-color-manager' ) ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script(
		'aegis-woo-color-manager-admin',
		plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
		array( 'wp-color-picker' ),
		'0.1.0',
		true
	);
}
add_action( 'admin_enqueue_scripts', 'aegis_woo_color_manager_admin_assets' );

/**
 * Render settings page.
 */
function aegis_woo_color_manager_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tokens = aegis_woo_color_manager_get_tokens();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Aegis Woo Color Manager', 'aegis-woo-color-manager' ); ?></h1>
		<div class="notice notice-info">
			<p><?php esc_html_e( '已覆盖场景（简版）：Notices（blocks + classic）、Buttons（产品列表/返回商店）、Links（Woo 页面链接）、Remove（mini cart 删除按钮/小×，逐步补齐）、Price（价格文字，逐步补齐）。', 'aegis-woo-color-manager' ); ?></p>
		</div>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'aegis_woo_color_manager' );
			do_settings_sections( 'aegis-woo-color-manager' );
			submit_button();
			?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Export Tokens', 'aegis-woo-color-manager' ); ?></h2>
		<p><?php esc_html_e( 'Download current token settings as JSON.', 'aegis-woo-color-manager' ); ?></p>
		<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aegis_woo_color_export' ), 'aegis_woo_color_export' ) ); ?>">
			<?php esc_html_e( 'Download JSON', 'aegis-woo-color-manager' ); ?>
		</a>

		<h2><?php esc_html_e( 'Import Tokens', 'aegis-woo-color-manager' ); ?></h2>
		<p><?php esc_html_e( 'Paste token JSON to overwrite current settings.', 'aegis-woo-color-manager' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'aegis_woo_color_import', 'aegis_woo_color_import_nonce' ); ?>
			<input type="hidden" name="action" value="aegis_woo_color_import" />
			<textarea name="aegis_woo_color_import_json" rows="8" class="large-text code"></textarea>
			<?php submit_button( __( 'Import JSON', 'aegis-woo-color-manager' ), 'secondary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Current Tokens (Read-only)', 'aegis-woo-color-manager' ); ?></h2>
		<textarea readonly rows="8" class="large-text code"><?php echo esc_textarea( wp_json_encode( $tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
	</div>
	<?php
}

/**
 * Export tokens as JSON.
 */
function aegis_woo_color_manager_export_tokens() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized.', 'aegis-woo-color-manager' ) );
	}

	check_admin_referer( 'aegis_woo_color_export' );

	$tokens = aegis_woo_color_manager_get_tokens();
	$json   = wp_json_encode( $tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="aegis-woo-color-tokens.json"' );
	echo $json;
	exit;
}
add_action( 'admin_post_aegis_woo_color_export', 'aegis_woo_color_manager_export_tokens' );

/**
 * Import tokens from JSON.
 */
function aegis_woo_color_manager_import_tokens() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized.', 'aegis-woo-color-manager' ) );
	}

	check_admin_referer( 'aegis_woo_color_import', 'aegis_woo_color_import_nonce' );

	$raw = isset( $_POST['aegis_woo_color_import_json'] ) ? wp_unslash( $_POST['aegis_woo_color_import_json'] ) : '';
	if ( ! $raw ) {
		wp_safe_redirect( admin_url( 'admin.php?page=aegis-woo-color-manager' ) );
		exit;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=aegis-woo-color-manager' ) );
		exit;
	}

	$defaults = aegis_woo_color_manager_default_tokens();
	$tokens   = array();

	foreach ( $defaults as $key => $value ) {
		if ( isset( $data[ $key ] ) ) {
			$sanitized = sanitize_hex_color( $data[ $key ] );
			if ( $sanitized ) {
				$tokens[ $key ] = $sanitized;
				continue;
			}
		}
		$tokens[ $key ] = $value;
	}

	update_option( 'aegis_woo_color_tokens', $tokens );

	wp_safe_redirect( admin_url( 'admin.php?page=aegis-woo-color-manager' ) );
	exit;
}
add_action( 'admin_post_aegis_woo_color_import', 'aegis_woo_color_manager_import_tokens' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Scan codebase for color values and output an index.
	 */
	class Aegis_Woo_Color_Manager_CLI {
		/**
		 * Scan for color values and write a JSON/CSV index.
		 *
		 * ## OPTIONS
		 *
		 * [--paths=<paths>]
		 * : Comma-separated list of paths to scan.
		 *
		 * [--include-woocommerce]
		 * : Include WooCommerce core assets in scan.
		 *
		 * [--format=<format>]
		 * : Output format: json or csv. Default: json.
		 *
		 * [--output=<output>]
		 * : Output file path. Defaults to uploads/aegis-woo-color-manager/color-index.json.
		 *
		 * ## EXAMPLES
		 *
		 *     wp aegis-woo-color scan
		 *     wp aegis-woo-color scan --format=csv
		 *     wp aegis-woo-color scan --paths=wp-content/themes/aegis-themes,wp-content/plugins/aegis-*
		 */
		public function __invoke( $args, $assoc_args ) {
			$paths_arg = isset( $assoc_args['paths'] ) ? $assoc_args['paths'] : '';
			$format    = isset( $assoc_args['format'] ) ? strtolower( $assoc_args['format'] ) : 'json';
			$output    = isset( $assoc_args['output'] ) ? $assoc_args['output'] : '';
			$paths     = array();

			if ( $paths_arg ) {
				$paths = array_map( 'trim', explode( ',', $paths_arg ) );
			} else {
				$paths = array(
					'wp-content/themes/aegis-themes',
					'wp-content/plugins/aegis-*',
				);

				if ( isset( $assoc_args['include-woocommerce'] ) ) {
					$paths[] = 'wp-content/plugins/woocommerce/assets';
				}
			}

			$allowed_extensions = array( 'css', 'js', 'php', 'svg', 'json' );
			$color_map          = array();
			$regex              = '/#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b|rgba?\([^\\)]+\\)|hsla?\([^\\)]+\\)/';

			foreach ( $paths as $path_pattern ) {
				$matched_paths = glob( $path_pattern, GLOB_BRACE );
				if ( empty( $matched_paths ) ) {
					continue;
				}

				foreach ( $matched_paths as $matched_path ) {
					if ( is_file( $matched_path ) ) {
						$this->scan_file( $matched_path, $allowed_extensions, $regex, $color_map );
						continue;
					}

					$iterator = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator( $matched_path, FilesystemIterator::SKIP_DOTS )
					);

					foreach ( $iterator as $file ) {
						if ( ! $file->isFile() ) {
							continue;
						}

						$this->scan_file( $file->getPathname(), $allowed_extensions, $regex, $color_map );
					}
				}
			}

			$payload = array();
			foreach ( $color_map as $color => $occurrences ) {
				$payload[ $color ] = array(
					'count'       => count( $occurrences ),
					'occurrences' => $occurrences,
				);
			}

			if ( ! $output ) {
				$upload_dir = wp_upload_dir();
				$dir        = trailingslashit( $upload_dir['basedir'] ) . 'aegis-woo-color-manager';
				wp_mkdir_p( $dir );
				$extension = 'json' === $format ? 'json' : 'csv';
				$output    = trailingslashit( $dir ) . 'color-index.' . $extension;
			}

			if ( 'csv' === $format ) {
				$csv = $this->render_csv( $payload );
				file_put_contents( $output, $csv );
				WP_CLI::line( $csv );
			} else {
				$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
				file_put_contents( $output, $json );
				WP_CLI::line( $json );
			}

			WP_CLI::success( sprintf( 'Color index written to %s', $output ) );
		}

		private function scan_file( $path, $allowed_extensions, $regex, &$color_map ) {
			$extension = pathinfo( $path, PATHINFO_EXTENSION );
			if ( ! in_array( $extension, $allowed_extensions, true ) ) {
				return;
			}

			$contents = file_get_contents( $path );
			if ( false === $contents ) {
				return;
			}

			$lines = preg_split( '/\\r\\n|\\r|\\n/', $contents );
			foreach ( $lines as $index => $line ) {
				if ( ! preg_match_all( $regex, $line, $matches ) ) {
					continue;
				}

				foreach ( $matches[0] as $color ) {
					if ( ! isset( $color_map[ $color ] ) ) {
						$color_map[ $color ] = array();
					}

					$color_map[ $color ][] = array(
						'file'    => $path,
						'line'    => $index + 1,
						'snippet' => trim( mb_substr( $line, 0, 240 ) ),
					);
				}
			}
		}

		private function render_csv( $payload ) {
			$rows   = array();
			$rows[] = array( 'color', 'count', 'file', 'line', 'snippet' );

			foreach ( $payload as $color => $data ) {
				foreach ( $data['occurrences'] as $occurrence ) {
					$rows[] = array(
						$color,
						$data['count'],
						$occurrence['file'],
						$occurrence['line'],
						$occurrence['snippet'],
					);
				}
			}

			$handle = fopen( 'php://temp', 'w+' );
			foreach ( $rows as $row ) {
				fputcsv( $handle, $row );
			}
			rewind( $handle );
			$csv = stream_get_contents( $handle );
			fclose( $handle );

			return $csv;
		}
	}

	WP_CLI::add_command( 'aegis-woo-color scan', 'Aegis_Woo_Color_Manager_CLI' );
}
