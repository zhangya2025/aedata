<?php
/**
 * The7 FSE Font Manager.
 *
 * @package The7
 */

namespace The7\Mods\Compatibility\Gutenberg\Block_Theme;

use WP_Error;
use WP_Font_Face;
use WP_Font_Face_Resolver;
use WP_Font_Utils;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * This class is responsible for downloading fonts from the theme JSON files.
 */
class The7_FSE_Font_Manager {

	const FONTS_OPTION_NAME = 'the7_fse_fonts_to_download';

	/**
	 * @var The7_FSE_Font_Manager
	 */
	public static $instance;

	/**
	 * The7_FSE_Font_Manager constructor.
	 *
	 * @return The7_FSE_Font_Manager
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize all necessary hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->should_download_fonts() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'download' ], 100 );
			add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
		}

		// Rewrite the font faces printing functions to replace the font URLs.
		add_action(
			'admin_print_styles',
			function () {
				remove_action( 'admin_print_styles', 'wp_print_font_faces_from_style_variations', 50 );
				remove_action( 'admin_print_styles', 'wp_print_font_faces', 50 );
			}
		);

		add_filter(
			'block_editor_settings_all',
			function ( $settings ) {
				if ( isset( $settings['__unstableResolvedAssets']['styles'] ) ) {
					$settings['__unstableResolvedAssets']['styles'] = $this->replace_font_src( $settings['__unstableResolvedAssets']['styles'] );
				}

				return $settings;
			}
		);

		add_action( 'admin_print_styles', [ $this, 'print_font_faces_from_style_variations' ], 50 );
		add_action( 'admin_print_styles', [ $this, 'print_font_faces' ], 50 );

		remove_action( 'wp_head', 'wp_print_font_faces', 50 );
		add_action( 'wp_head', [ $this, 'print_font_faces' ], 50 );
		// End of rewrite the font faces printing functions.
	}

	/**
	 * Get the list of fonts from the theme JSON files.
	 *
	 * @return array
	 */
	public function get_fonts_from_json() {
		$fonts = [];

		$files = glob( get_template_directory() . '/styles/typography/*.json' );

		foreach ( $files as $file ) {
			$typography = json_decode( file_get_contents( $file ), true );

			if ( ! $typography || ! isset( $typography['settings']['typography']['fontFamilies'] ) ) {
				continue;
			}

			$font_families = $typography['settings']['typography']['fontFamilies'];
			foreach ( $font_families as $font_family ) {
				if ( empty( $font_family['fontFace'] ) || ! is_array( $font_family['fontFace'] ) ) {
					continue;
				}

				$font_faces = array_filter(
					$font_family['fontFace'],
					static function ( $ff ) {
						$src = $ff['src'] ?? '';
						$src = is_array( $src ) ? array_values( $src )[0] : $src;

						return strpos( $src, 'load__local__font' ) !== false;
					}
				);

				$fonts = array_merge( $fonts, $font_faces );
			}
		}

		return $fonts;
	}

	/**
	 * Check if the fonts list is already installed.
	 * Download the fonts list if it is not installed.
	 */
	public function download() {
		$this->enqueue_admin_scripts();
		wp_add_inline_script( 'the7-fse-fonts-downloader', 'window.the7_fse_should_run = true;' );
	}

	/**
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		$fonts = $this->get_fonts_from_json();

		if ( empty( $fonts ) ) {
			$this->set_fonts_to_download( [] );

			return;
		}

		$fonts_to_download = $this->get_fonts_to_download();
		if ( $fonts_to_download === false || empty( $fonts_to_download ) ) {
			$existing_fonts    = $this->fetch_existing_fonts();
			$fonts_to_download = array_filter(
				$fonts,
				static function ( $font ) use ( $existing_fonts ) {
					$filename = basename( $font['src'] );

					return ! in_array( $filename, $existing_fonts, true );
				}
			);
			// Get only the font file name.
			$fonts_to_download = array_map(
				static function ( $font ) {
					return basename( $font['src'] );
				},
				$fonts_to_download
			);
			$this->set_fonts_to_download( $fonts_to_download );
			if ( empty( $fonts_to_download ) ) {
				return;
			}
		}

		$fonts = array_filter(
			$fonts,
			static function ( $font ) use ( $fonts_to_download ) {
				$filename = basename( $font['src'] );

				return in_array( $filename, $fonts_to_download, true );
			}
		);
		wp_enqueue_script( 'the7-fse-fonts-downloader' );
		wp_localize_script( 'the7-fse-fonts-downloader', 'the7_fse_fonts', array_values( $fonts ) );
	}

	/**
	 * Create REST API endpoint to download fonts.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			'the7/v1',
			'/fse-font',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'download_font' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Print font faces from style variations.
	 *
	 * @return void
	 */
	public function print_font_faces_from_style_variations() {
		$fonts = WP_Font_Face_Resolver::get_fonts_from_style_variations();

		if ( empty( $fonts ) ) {
			return;
		}
		$this->print_font_faces( $fonts );
	}

	/**
	 * Reads existing font files from the font folder.
	 *
	 * @return array List of existing font files.
	 */
	public function fetch_existing_fonts() {
		$font_dir   = untrailingslashit( wp_get_font_dir()['basedir'] );
		$font_files = array_merge(
			glob( $font_dir . '/*.woff' ) ?: [],
			glob( $font_dir . '/*.woff2' ) ?: [],
			glob( $font_dir . '/*.ttf' ) ?: [],
			glob( $font_dir . '/*.otf' ) ?: []
		);

		return array_map( 'basename', $font_files );
	}

	/**
	 * Print font faces.
	 *
	 * @param array $fonts List of fonts to print.
	 *
	 * @return void
	 */
	public function print_font_faces( $fonts = [] ) {
		if ( empty( $fonts ) ) {
			$fonts = WP_Font_Face_Resolver::get_fonts_from_theme_json();
		}

		if ( empty( $fonts ) ) {
			return;
		}

		$wp_font_face = new WP_Font_Face();

		$this->replace_font_src_recursive( $fonts );

		$wp_font_face->generate_and_print( $fonts );
	}

	/**
	 * Replace the font URLs with the local font URLs.
	 *
	 * @param array $fonts_array   Array to replace the URLs.
	 *
	 * @return void
	 */
	public function replace_font_src_recursive( &$fonts_array ) {
		foreach ( $fonts_array as &$value ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['src'] ) && is_array( $value['src'] ) ) {
					foreach ( $value['src'] as &$src_val ) {
						if ( is_string( $src_val ) ) {
							$src_val = $this->replace_font_src( $src_val );
						}
					}
					unset( $src_val );
				}
				$this->replace_font_src_recursive( $value );
			}
		}
	}

	/**
	 * Replace the font URL with the local font URL.
	 *
	 * @param string $font Font URL.
	 *
	 * @return string
	 */
	protected function replace_font_src( $font ) {
		return str_replace(
			get_template_directory_uri() . '/load__local__font/',
			trailingslashit( wp_get_font_dir()['baseurl'] ),
			$font
		);
	}

	/**
	 * Check if the user has permission to download fonts.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'switch_themes' );
	}

	/**
	 * Download fonts.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|array
	 */
	public function download_font( WP_REST_Request $request ) {
		$file_params = $request->get_file_params();

		if ( empty( $file_params['font'] ) ) {
			return new WP_Error( 'no_font', 'No font to download', [ 'status' => 400 ] );
		}

		$font     = $file_params['font'];
		$filename = $font['name'];
		$font_dir = untrailingslashit( wp_get_font_dir()['basedir'] );

		if ( file_exists( $font_dir . '/' . $filename ) ) {
			$this->font_was_downloaded( $filename );

			return [
				'info' => 'Font already exists',
			];
		}

		$downloaded_font = $this->handle_font_file_upload( $font );
		if ( ! is_wp_error( $downloaded_font ) ) {
			$this->font_was_downloaded( $filename );
		}

		return $downloaded_font;
	}

	/**
	 * Handles the upload of a font file using wp_handle_upload().
	 *
	 * @param array $file Single file item from $_FILES.
	 *
	 * @return array|WP_Error Array containing uploaded file attributes on success, or WP_Error object on failure.
	 */
	protected function handle_font_file_upload( $file ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$mimes_hook_name = 'upload_' . 'mimes'; // phpcs:ignore Generic.Strings.UnnecessaryStringConcat
		add_filter( $mimes_hook_name, [ 'WP_Font_Utils', 'get_allowed_font_mime_types' ] );
		// Filter the upload directory to return the fonts directory.
		add_filter( 'upload_dir', '_wp_filter_font_directory' );

		$overrides = [
			'upload_error_handler' => [ $this, 'handle_font_file_upload_error' ],
			// Not testing a form submission.
			'test_form'            => false,
			// Only allow uploading font files for this request.
			'mimes'                => WP_Font_Utils::get_allowed_font_mime_types(),
		];

		$uploaded_file = wp_handle_upload( $file, $overrides );

		remove_filter( 'upload_dir', '_wp_filter_font_directory' );
		remove_filter( $mimes_hook_name, [ 'WP_Font_Utils', 'get_allowed_font_mime_types' ] );

		return $uploaded_file;
	}

	/**
	 * Handles file upload error.
	 *
	 * @param array  $file    File upload data.
	 * @param string $message Error message from wp_handle_upload().
	 *
	 * @return WP_Error WP_Error object.
	 */
	public function handle_font_file_upload_error( $file, $message ) {
		$status = 500;
		$code   = 'rest_font_upload_unknown_error';

		if ( __( 'Sorry, you are not allowed to upload this file type.' ) === $message ) {
			$status = 400;
			$code   = 'rest_font_upload_invalid_file_type';
		}

		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}

	/**
	 * Get the list of fonts to download.
	 *
	 * @return array or false   List of fonts to download. Or false if the list is not set.
	 */
	public function get_fonts_to_download() {
		return get_transient( self::FONTS_OPTION_NAME );
	}

	/**
	 * Reset the list of fonts to download.
	 *
	 * @return void
	 */
	public function reset_fonts_to_download() {
		delete_transient( self::FONTS_OPTION_NAME );
	}

	/**
	 * Set the list of fonts to download.
	 *
	 * @param array $fonts_list List of fonts to download.
	 */
	public function set_fonts_to_download( $fonts_list ) {
		set_transient( self::FONTS_OPTION_NAME, $fonts_list, HOUR_IN_SECONDS * 12 );
	}

	/**
	 * Remove the font from the list of fonts to download.
	 *
	 * @param string $font Font file name.
	 */
	public function font_was_downloaded( $font ) {
		$fonts_to_download = $this->get_fonts_to_download();

		// Ensure $fonts_to_download is an array.
		if ( ! is_array( $fonts_to_download ) ) {
			$fonts_to_download = [];
		}

		$downloaded_fonts = array_filter(
			$fonts_to_download,
			static function ( $filename ) use ( $font ) {
				return $filename !== $font;
			}
		);

		$this->set_fonts_to_download( $downloaded_fonts );
	}

	/**
	 * Check if the fonts should be downloaded.
	 *
	 * @return bool
	 */
	public function should_download_fonts() {
		$fonts = $this->get_fonts_to_download();

		return $this->check_permission() && ( $fonts === false || ! empty( $fonts ) );
	}
}
