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
}
add_action( 'wp_enqueue_scripts', 'aegis_woo_color_manager_enqueue_styles', 20 );

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
