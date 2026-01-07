<?php
/**
 * Plugin Name: Aegis Rewrite Doctor
 * Description: Diagnostic and minimal safety fixes for index.php-based WooCommerce product permalinks.
 * Version: 0.1.0
 * Author: Aegis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Aegis_Rewrite_Doctor {
	private const OPTION_KEY = 'aegis_rewrite_doctor_settings';
	private static $notices = array();
	private static $quick_check = null;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'parse_request', array( __CLASS__, 'maybe_apply_product_fallback' ) );
		add_filter( 'post_type_link', array( __CLASS__, 'maybe_normalize_product_permalink' ), 10, 2 );
	}

	public static function register_menu(): void {
		add_management_page(
			'Aegis Rewrite Doctor',
			'Aegis Rewrite Doctor',
			'manage_options',
			'aegis-rewrite-doctor',
			array( __CLASS__, 'render_page' )
		);
	}

	private static function current_user_is_allowed(): bool {
		if ( is_multisite() ) {
			return is_super_admin();
		}

		return current_user_can( 'manage_options' );
	}

	private static function get_settings(): array {
		$defaults = array(
			'fallback_enabled'  => false,
			'normalize_enabled' => false,
		);

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( $defaults, $stored );
	}

	public static function handle_actions(): void {
		if ( ! self::current_user_is_allowed() ) {
			return;
		}

		if ( empty( $_POST['aegis_rewrite_doctor_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['aegis_rewrite_doctor_action'] ) );

		if ( 'flush_rewrite' === $action ) {
			check_admin_referer( 'aegis_rewrite_doctor_flush' );
			flush_rewrite_rules();
			self::$notices[] = array(
				'type'    => 'success',
				'message' => '已执行 flush_rewrite_rules()。请勿频繁操作。',
			);
			return;
		}

		if ( 'normalize_product_base' === $action ) {
			check_admin_referer( 'aegis_rewrite_doctor_normalize_product_base' );
			$permalinks = get_option( 'woocommerce_permalinks', array() );
			if ( is_array( $permalinks ) && isset( $permalinks['product_base'] ) ) {
				$original = (string) $permalinks['product_base'];
				$normalized = trim( $original, '/' );
				$permalinks['product_base'] = $normalized;
				update_option( 'woocommerce_permalinks', $permalinks );
				self::$notices[] = array(
					'type'    => 'success',
					'message' => sprintf(
						'已规范化 product_base："%s" → "%s"。建议手动再 Flush 一次。',
						esc_html( $original ),
						esc_html( $normalized )
					),
				);
			} else {
				self::$notices[] = array(
					'type'    => 'error',
					'message' => '未找到 woocommerce_permalinks 或 product_base。',
				);
			}
			return;
		}

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'aegis_rewrite_doctor_save_settings' );
			$settings = self::get_settings();
			$settings['fallback_enabled']  = ! empty( $_POST['fallback_enabled'] );
			$settings['normalize_enabled'] = ! empty( $_POST['normalize_enabled'] );
			update_option( self::OPTION_KEY, $settings );
			self::$notices[] = array(
				'type'    => 'success',
				'message' => '设置已保存。',
			);
			return;
		}

		if ( 'quick_check' === $action ) {
			check_admin_referer( 'aegis_rewrite_doctor_quick_check' );
			$input = isset( $_POST['product_identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['product_identifier'] ) ) : '';
			self::$quick_check = self::run_quick_check( $input );
			return;
		}
	}

	private static function run_quick_check( string $input ): array {
		$result = array(
			'input'         => $input,
			'found'         => false,
			'product_id'    => null,
			'product_slug'  => null,
			'permalink'     => null,
			'query_url'     => null,
			'has_double'    => null,
			'error_message' => null,
		);

		if ( '' === $input ) {
			$result['error_message'] = '请输入产品 ID 或 slug。';
			return $result;
		}

		$product = null;
		if ( ctype_digit( $input ) ) {
			$product = get_post( (int) $input );
			if ( $product && 'product' !== $product->post_type ) {
				$product = null;
			}
		} else {
			$product = get_page_by_path( $input, OBJECT, 'product' );
		}

		if ( ! $product || 'publish' !== $product->post_status ) {
			$result['error_message'] = '未找到已发布的产品。';
			return $result;
		}

		$slug = $product->post_name;
		$permalink = get_permalink( $product );
		$query_url = add_query_arg( 'product', $slug, home_url( '/index.php' ) );

		$result['found'] = true;
		$result['product_id'] = $product->ID;
		$result['product_slug'] = $slug;
		$result['permalink'] = $permalink;
		$result['query_url'] = $query_url;
		$result['has_double'] = ( false !== strpos( $permalink, '/index.php//product/' ) );

		return $result;
	}

	public static function maybe_apply_product_fallback( WP $wp ): void {
		$settings = self::get_settings();
		if ( empty( $settings['fallback_enabled'] ) ) {
			return;
		}

		if ( ! self::request_allows_index_php_fallback() ) {
			return;
		}

		if ( ! empty( $wp->query_vars['product'] ) || ! empty( $wp->query_vars['post_type'] ) ) {
			return;
		}

		$slug = self::extract_product_slug_from_request();
		if ( ! $slug ) {
			return;
		}

		$product = get_page_by_path( $slug, OBJECT, 'product' );
		if ( ! $product || 'publish' !== $product->post_status ) {
			return;
		}

		$wp->query_vars['product'] = $slug;
		$wp->query_vars['post_type'] = 'product';
		$wp->query_vars['name'] = $slug;
	}

	private static function request_allows_index_php_fallback(): bool {
		$permalink_structure = (string) get_option( 'permalink_structure', '' );
		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}

		if ( false !== strpos( $path, '/index.php' ) ) {
			return true;
		}

		return false !== strpos( $permalink_structure, 'index.php' );
	}

	private static function extract_product_slug_from_request(): ?string {
		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( ! $path ) {
			return null;
		}

		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		if ( empty( $segments ) ) {
			return null;
		}

		$index_position = array_search( 'index.php', $segments, true );
		if ( false !== $index_position ) {
			$segments = array_slice( $segments, $index_position + 1 );
		}

		if ( 2 !== count( $segments ) ) {
			return null;
		}

		if ( 'product' !== $segments[0] || '' === $segments[1] ) {
			return null;
		}

		return sanitize_title( $segments[1] );
	}

	public static function maybe_normalize_product_permalink( string $post_link, WP_Post $post ): string {
		$settings = self::get_settings();
		if ( empty( $settings['normalize_enabled'] ) ) {
			return $post_link;
		}

		if ( 'product' !== $post->post_type ) {
			return $post_link;
		}

		return str_replace( '/index.php//product/', '/index.php/product/', $post_link );
	}

	public static function render_page(): void {
		if ( ! self::current_user_is_allowed() ) {
			wp_die( esc_html__( 'Access denied.' ) );
		}

		$settings = self::get_settings();
		$permalink_structure = (string) get_option( 'permalink_structure', '' );
		$rewrite_rules = get_option( 'rewrite_rules' );
		$rule_count = is_array( $rewrite_rules ) ? count( $rewrite_rules ) : 0;
		$has_product_rule = false;
		if ( is_array( $rewrite_rules ) ) {
			foreach ( array_keys( $rewrite_rules ) as $rule_key ) {
				if ( false !== strpos( $rule_key, 'product' ) ) {
					$has_product_rule = true;
					break;
				}
			}
		}

		$woocommerce_active = class_exists( 'WooCommerce' );
		$woocommerce_permalinks = get_option( 'woocommerce_permalinks', array() );
		$product_rewrite = null;
		$product_post_type = get_post_type_object( 'product' );
		if ( $product_post_type ) {
			$product_rewrite = $product_post_type->rewrite;
		}

		$quick_check = self::$quick_check;
		?>
		<div class="wrap">
			<h1>Aegis Rewrite Doctor</h1>

			<?php foreach ( self::$notices as $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo wp_kses_post( $notice['message'] ); ?></p>
				</div>
			<?php endforeach; ?>

			<h2>环境与固定链接状态</h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<td><strong>home_url()</strong></td>
						<td><?php echo esc_html( home_url() ); ?></td>
					</tr>
					<tr>
						<td><strong>site_url()</strong></td>
						<td><?php echo esc_html( site_url() ); ?></td>
					</tr>
					<tr>
						<td><strong>permalink_structure</strong></td>
						<td><?php echo esc_html( $permalink_structure ); ?></td>
					</tr>
					<tr>
						<td><strong>rewrite_rules 规则条目数</strong></td>
						<td><?php echo esc_html( (string) $rule_count ); ?></td>
					</tr>
					<tr>
						<td><strong>包含 product 规则</strong></td>
						<td><?php echo $has_product_rule ? '是' : '否'; ?></td>
					</tr>
					<tr>
						<td><strong>multisite</strong></td>
						<td><?php echo is_multisite() ? '是' : '否'; ?></td>
					</tr>
					<tr>
						<td><strong>当前用户权限</strong></td>
						<td>
							<?php
							echo esc_html(
								is_multisite()
									? ( is_super_admin() ? 'super admin' : 'not super admin' )
									: ( current_user_can( 'manage_options' ) ? 'manage_options' : 'no manage_options' )
							);
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<h2>WooCommerce 相关</h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<td><strong>Woo 是否激活</strong></td>
						<td><?php echo $woocommerce_active ? '是' : '否'; ?></td>
					</tr>
					<tr>
						<td><strong>woocommerce_permalinks</strong></td>
						<td>
							<?php
							if ( is_array( $woocommerce_permalinks ) ) {
								$keys = array( 'product_base', 'category_base', 'tag_base', 'attribute_base', 'use_verbose_page_rules' );
								$lines = array();
								foreach ( $keys as $key ) {
									if ( array_key_exists( $key, $woocommerce_permalinks ) ) {
										$lines[] = sprintf( '%s: %s', $key, wp_json_encode( $woocommerce_permalinks[ $key ] ) );
									}
								}
								echo $lines ? esc_html( implode( ' | ', $lines ) ) : '未设置';
							} else {
								echo '未设置';
							}
							?>
						</td>
					</tr>
					<tr>
						<td><strong>product rewrite</strong></td>
						<td>
							<?php
							if ( false === $product_rewrite ) {
								echo 'rewrite=false';
							} elseif ( is_array( $product_rewrite ) ) {
								$fields = array(
									'slug'       => $product_rewrite['slug'] ?? null,
									'with_front' => $product_rewrite['with_front'] ?? null,
									'feeds'      => $product_rewrite['feeds'] ?? null,
									'pages'      => $product_rewrite['pages'] ?? null,
								);
								echo esc_html( wp_json_encode( $fields ) );
							} else {
								echo '未找到 product post type';
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<h2>快速验证区</h2>
			<form method="post">
				<?php wp_nonce_field( 'aegis_rewrite_doctor_quick_check' ); ?>
				<input type="hidden" name="aegis_rewrite_doctor_action" value="quick_check" />
				<label for="product_identifier">产品 ID 或 slug：</label>
				<input type="text" name="product_identifier" id="product_identifier" value="" class="regular-text" />
				<button class="button">检查</button>
			</form>

			<?php if ( is_array( $quick_check ) ) : ?>
				<div class="notice notice-info" style="margin-top: 12px;">
					<?php if ( $quick_check['found'] ) : ?>
						<p>产品 ID：<?php echo esc_html( (string) $quick_check['product_id'] ); ?></p>
						<p>产品 slug：<?php echo esc_html( $quick_check['product_slug'] ); ?></p>
						<p>get_permalink：<?php echo esc_html( $quick_check['permalink'] ); ?></p>
						<p>index.php query URL：<?php echo esc_html( $quick_check['query_url'] ); ?></p>
						<p>双斜杠检测：<?php echo $quick_check['has_double'] ? '存在' : '不存在'; ?></p>
					<?php else : ?>
						<p><?php echo esc_html( $quick_check['error_message'] ?? '未知错误' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<h2>管理员手动动作</h2>
			<form method="post" style="margin-bottom: 16px;">
				<?php wp_nonce_field( 'aegis_rewrite_doctor_flush' ); ?>
				<input type="hidden" name="aegis_rewrite_doctor_action" value="flush_rewrite" />
				<button class="button button-primary">Flush rewrite</button>
				<p class="description">仅用于确认 rewrite_rules 是否能落地更新，请勿频繁点击。</p>
			</form>

			<?php if ( isset( $woocommerce_permalinks['product_base'] ) ) : ?>
				<form method="post" style="margin-bottom: 16px;">
					<?php wp_nonce_field( 'aegis_rewrite_doctor_normalize_product_base' ); ?>
					<input type="hidden" name="aegis_rewrite_doctor_action" value="normalize_product_base" />
					<button class="button">规范化 Woo product_base</button>
					<p class="description">将 product_base 去掉首尾斜杠，仅在点击时写入。写入后建议手动 Flush。</p>
				</form>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'aegis_rewrite_doctor_save_settings' ); ?>
				<input type="hidden" name="aegis_rewrite_doctor_action" value="save_settings" />
				<label>
					<input type="checkbox" name="fallback_enabled" value="1" <?php checked( $settings['fallback_enabled'] ); ?> />
					启用 product 请求兜底（仅 index.php 模式）
				</label>
				<p class="description">仅对 product/{slug} 生效，且确认产品存在且已发布后改写为 ?product={slug}。</p>
				<label>
					<input type="checkbox" name="normalize_enabled" value="1" <?php checked( $settings['normalize_enabled'] ); ?> />
					仅对 product permalink 输出做 index.php//product 归一化
				</label>
				<p class="description">仅替换 /index.php//product/ → /index.php/product/，默认关闭。</p>
				<p>
					<button class="button button-primary">保存开关设置</button>
				</p>
			</form>
		</div>
		<?php
	}
}

Aegis_Rewrite_Doctor::init();
