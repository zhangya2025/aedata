<?php
/**
 * Plugin Name: Wind Local Assets
 * Description: Localizes Google Fonts and ensures critical assets are served locally for improved performance in China.
 * Version: 0.1.0
 * Author: CODEX
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Wind_Local_Assets {
        const OPTION_KEY       = 'windla_options';
        const LAST_SYNC_OPTION = 'windla_last_synced';
        const LOCK_PREFIX      = 'windla_lock_';

        private static $instance;

        /**
         * @return Wind_Local_Assets
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        private function __construct() {
                add_action( 'admin_menu', [ $this, 'register_tools_page' ] );
                add_action( 'admin_init', [ $this, 'handle_admin_actions' ] );
                add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_mousewheel' ], 5 );
                add_filter( 'style_loader_src', [ $this, 'localize_google_fonts' ], 10, 2 );
        }

        /**
         * Register the Tools -> Local Assets page.
         */
        public function register_tools_page() {
                add_management_page(
                        __( 'Local Assets', 'wind-local-assets' ),
                        __( 'Local Assets', 'wind-local-assets' ),
                        'manage_options',
                        'windla-local-assets',
                        [ $this, 'render_tools_page' ]
                );
        }

        /**
         * Handle admin form submissions for settings, resync, and cleanup.
         */
        public function handle_admin_actions() {
                if ( empty( $_POST['windla_action'] ) || ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                check_admin_referer( 'windla_admin_action', 'windla_nonce' );

                $options = $this->get_options();

                switch ( sanitize_text_field( wp_unslash( $_POST['windla_action'] ) ) ) {
                        case 'save_settings':
                                $options['enable_fonts_localization'] = ! empty( $_POST['enable_fonts_localization'] );
                                update_option( self::OPTION_KEY, $options );
                                add_settings_error( 'windla', 'settings_saved', __( 'Settings saved.', 'wind-local-assets' ), 'updated' );
                                break;
                        case 'clear_cache':
                                $this->clear_cache();
                                add_settings_error( 'windla', 'cache_cleared', __( 'Cache cleared.', 'wind-local-assets' ), 'updated' );
                                break;
                        case 'resync':
                                $this->clear_cache();
                                add_settings_error( 'windla', 'resync_triggered', __( 'Re-sync will occur on next font request.', 'wind-local-assets' ), 'updated' );
                                break;
                        default:
                                break;
                }
        }

        /**
         * Render the tools page.
         */
        public function render_tools_page() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                $options = $this->get_options();

                settings_errors( 'windla' );

                $stats = $this->get_cache_stats();

                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'Local Assets', 'wind-local-assets' ); ?></h1>
                        <form method="post">
                                <?php wp_nonce_field( 'windla_admin_action', 'windla_nonce' ); ?>
                                <input type="hidden" name="windla_action" value="save_settings" />
                                <table class="form-table" role="presentation">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Localize Google Fonts', 'wind-local-assets' ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="enable_fonts_localization" value="1" <?php checked( $options['enable_fonts_localization'] ); ?> />
                                                                <?php esc_html_e( 'Enable Google Fonts localization on the frontend (default on).', 'wind-local-assets' ); ?>
                                                        </label>
                                                </td>
                                        </tr>
                                </table>
                                <?php submit_button( __( 'Save Changes', 'wind-local-assets' ) ); ?>
                        </form>

                        <h2><?php esc_html_e( 'Cache status', 'wind-local-assets' ); ?></h2>
                        <p>
                                <?php
                                printf(
                                        /* translators: 1: css count 2: font count 3: size 4: last sync */
                                        esc_html__( 'CSS files: %1$s, font files: %2$s, total size: %3$s, last sync: %4$s', 'wind-local-assets' ),
                                        intval( $stats['css_count'] ),
                                        intval( $stats['font_count'] ),
                                        size_format( $stats['total_size'] ),
                                        $stats['last_sync'] ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $stats['last_sync'] ) ) : esc_html__( 'Never', 'wind-local-assets' )
                                );
                                ?>
                        </p>

                        <form method="post" style="margin-right: 12px; display: inline-block;">
                                <?php wp_nonce_field( 'windla_admin_action', 'windla_nonce' ); ?>
                                <input type="hidden" name="windla_action" value="clear_cache" />
                                <?php submit_button( __( 'Clear Cache', 'wind-local-assets' ), 'secondary', 'submit', false ); ?>
                        </form>
                        <form method="post" style="display: inline-block;">
                                <?php wp_nonce_field( 'windla_admin_action', 'windla_nonce' ); ?>
                                <input type="hidden" name="windla_action" value="resync" />
                                <?php submit_button( __( 'Re-sync', 'wind-local-assets' ), 'secondary', 'submit', false ); ?>
                        </form>
                </div>
                <?php
        }

        /**
         * Ensure mousewheel is available before The7 custom scrollbar executes to avoid CDN fallback.
         */
        public function enqueue_mousewheel() {
                if ( is_admin() ) {
                        return;
                }

                $handle = 'jquery-mousewheel';
                $scripts = wp_scripts();
                $theme_path = trailingslashit( get_template_directory() ) . 'lib/jquery-mousewheel/jquery-mousewheel.min.js';
                $theme_url  = trailingslashit( get_template_directory_uri() ) . 'lib/jquery-mousewheel/jquery-mousewheel.min.js';

                $src = file_exists( $theme_path ) ? $theme_url : plugin_dir_url( __FILE__ ) . 'vendor/jquery.mousewheel.min.js';

                if ( ! wp_script_is( $handle, 'registered' ) ) {
                        wp_register_script( $handle, $src, [ 'jquery' ], '3.1.12', true );
                }

                wp_enqueue_script( $handle );

                // Ensure custom scrollbar depends on mousewheel if registered.
                $custom_handle = 'the7-custom-scrollbar';
                if ( isset( $scripts->registered[ $custom_handle ] ) ) {
                        $deps = $scripts->registered[ $custom_handle ]->deps;
                        if ( ! in_array( $handle, $deps, true ) ) {
                                $scripts->registered[ $custom_handle ]->deps[] = $handle;
                        }
                }
        }

        /**
         * Filter style sources to replace Google Fonts with localized copies.
         *
         * @param string $src
         * @param string $handle
         *
         * @return string
         */
        public function localize_google_fonts( $src, $handle ) {
                if ( is_admin() ) {
                        return $src;
                }

                if ( $this->is_elementor_editor_request() ) {
                        return $src;
                }

                $options = $this->get_options();
                if ( empty( $options['enable_fonts_localization'] ) ) {
                        return $src;
                }

                $normalized = $this->normalize_google_css_url( $src );
                if ( ! $normalized ) {
                        return $src;
                }

                $local = $this->build_local_css( $normalized );
                if ( $local ) {
                        return $local;
                }

                return $src;
        }

        /**
         * Normalize Google Fonts CSS URLs (removing ver param, restricting host).
         *
         * @param string $src
         * @return string
         */
        private function normalize_google_css_url( $src ) {
                $parts = wp_parse_url( $src );
                if ( empty( $parts['host'] ) ) {
                        return '';
                }

                $host = strtolower( $parts['host'] );
                if ( 'fonts.googleapis.com' !== $host ) {
                        return '';
                }

                $scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : 'https://';
                $path   = isset( $parts['path'] ) ? $parts['path'] : '';
                $query  = '';

                if ( ! empty( $parts['query'] ) ) {
                        parse_str( $parts['query'], $params );
                        unset( $params['ver'] );
                        ksort( $params );
                        if ( $params ) {
                                $query = '?' . http_build_query( $params );
                        }
                }

                return $scheme . $host . $path . $query;
        }

        /**
         * Build or fetch the localized CSS file.
         *
         * @param string $normalized_url
         * @return string|false
         */
        private function build_local_css( $normalized_url ) {
                $hash          = sha1( $normalized_url );
                $paths         = $this->get_paths();
                $local_css     = $paths['css_dir'] . $hash . '.css';
                $local_css_url = $paths['css_url'] . $hash . '.css';

                if ( file_exists( $local_css ) ) {
                        return $local_css_url;
                }

                if ( $this->is_locked( $hash ) ) {
                        return false;
                }

                $this->lock( $hash );

                $response = wp_remote_get( $normalized_url, [
                        'timeout' => 10,
                ] );

                if ( is_wp_error( $response ) ) {
                        $this->unlock( $hash );
                        return false;
                }

                $code = wp_remote_retrieve_response_code( $response );
                if ( $code < 200 || $code >= 300 ) {
                        $this->unlock( $hash );
                        return false;
                }

                $body = wp_remote_retrieve_body( $response );
                if ( empty( $body ) ) {
                        $this->unlock( $hash );
                        return false;
                }

                $localized = $this->rewrite_font_urls( $body, $paths );
                if ( ! $localized ) {
                        $this->unlock( $hash );
                        return false;
                }

                if ( ! wp_mkdir_p( $paths['css_dir'] ) ) {
                        $this->unlock( $hash );
                        return false;
                }

                file_put_contents( $local_css, $localized );
                update_option( self::LAST_SYNC_OPTION, time() );
                $this->unlock( $hash );

                return $local_css_url;
        }

        /**
         * Replace Google-hosted font URLs with local copies.
         *
         * @param string $css
         * @param array  $paths
         * @return string|false
         */
        private function rewrite_font_urls( $css, $paths ) {
                $pattern = '/url\(([^)]+)\)/i';
                $matches = [];
                preg_match_all( $pattern, $css, $matches, PREG_SET_ORDER );

                foreach ( $matches as $match ) {
                        $raw_url = trim( $match[1], " \"'" );

                        $font_url = $this->normalize_font_url( $raw_url );
                        if ( ! $font_url ) {
                                continue;
                        }

                        $local_font = $this->download_font( $font_url, $paths );
                        if ( ! $local_font ) {
                                return false;
                        }

                        $css = str_replace( $raw_url, $local_font, $css );
                }

                return $css;
        }

        /**
         * Normalize font URL (host + allowed extensions).
         *
         * @param string $url
         * @return string
         */
        private function normalize_font_url( $url ) {
                $parts = wp_parse_url( $url );
                if ( empty( $parts['host'] ) ) {
                        return '';
                }

                $host = strtolower( $parts['host'] );
                if ( 'fonts.gstatic.com' !== $host ) {
                        return '';
                }

                $path = isset( $parts['path'] ) ? $parts['path'] : '';
                $extension = pathinfo( $path, PATHINFO_EXTENSION );
                if ( ! in_array( strtolower( $extension ), [ 'woff2', 'woff' ], true ) ) {
                        return '';
                }

                $scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : 'https://';

                return $scheme . $host . $path;
        }

        /**
         * Download a font asset locally.
         *
         * @param string $font_url
         * @param array  $paths
         * @return string|false Local URL on success.
         */
        private function download_font( $font_url, $paths ) {
                $hash         = sha1( $font_url );
                $extension    = pathinfo( wp_parse_url( $font_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
                $local_font   = $paths['font_dir'] . $hash . '.' . $extension;
                $local_font_url = $paths['font_url'] . $hash . '.' . $extension;

                if ( file_exists( $local_font ) ) {
                        return $local_font_url;
                }

                if ( $this->is_locked( $hash ) ) {
                        return false;
                }

                $this->lock( $hash );

                $response = wp_remote_get( $font_url, [
                        'timeout' => 15,
                ] );

                if ( is_wp_error( $response ) ) {
                        $this->unlock( $hash );
                        return false;
                }

                $code = wp_remote_retrieve_response_code( $response );
                if ( $code < 200 || $code >= 300 ) {
                        $this->unlock( $hash );
                        return false;
                }

                $body = wp_remote_retrieve_body( $response );
                if ( empty( $body ) ) {
                        $this->unlock( $hash );
                        return false;
                }

                // Basic size guard (~4MB).
                if ( strlen( $body ) > 4 * 1024 * 1024 ) {
                        $this->unlock( $hash );
                        return false;
                }

                if ( ! wp_mkdir_p( $paths['font_dir'] ) ) {
                        $this->unlock( $hash );
                        return false;
                }

                file_put_contents( $local_font, $body );
                $this->unlock( $hash );

                return $local_font_url;
        }

        /**
         * Get plugin options with defaults.
         *
         * @return array
         */
        private function get_options() {
                $defaults = [
                        'enable_fonts_localization' => true,
                ];

                $stored = get_option( self::OPTION_KEY, [] );

                return wp_parse_args( $stored, $defaults );
        }

        /**
         * Determine if the request should skip localization because Elementor editor is active.
         *
         * @return bool
         */
        private function is_elementor_editor_request() {
                if ( defined( 'ELEMENTOR_EDIT_MODE' ) && ELEMENTOR_EDIT_MODE ) {
                        return true;
                }

                if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        return true;
                }

                return false;
        }

        /**
         * Return upload paths for assets.
         *
         * @return array
         */
        private function get_paths() {
                $uploads = wp_upload_dir();

                $base_dir = trailingslashit( $uploads['basedir'] ) . 'wla-assets/';
                $base_url = trailingslashit( $uploads['baseurl'] ) . 'wla-assets/';

                return [
                        'base_dir' => $base_dir,
                        'base_url' => $base_url,
                        'css_dir'  => $base_dir . 'css/',
                        'css_url'  => $base_url . 'css/',
                        'font_dir' => $base_dir . 'fonts/',
                        'font_url' => $base_url . 'fonts/',
                ];
        }

        /**
         * Get cache statistics for display.
         *
         * @return array
         */
        private function get_cache_stats() {
                $paths   = $this->get_paths();
                $stats   = [
                        'css_count' => 0,
                        'font_count' => 0,
                        'total_size' => 0,
                        'last_sync'  => (int) get_option( self::LAST_SYNC_OPTION, 0 ),
                ];

                foreach ( [ 'css_dir' => 'css_count', 'font_dir' => 'font_count' ] as $dir_key => $count_key ) {
                        $dir = $paths[ $dir_key ];
                        if ( ! is_dir( $dir ) ) {
                                continue;
                        }

                        $files = glob( trailingslashit( $dir ) . '*' );
                        if ( $files ) {
                                $stats[ $count_key ] = count( $files );
                                foreach ( $files as $file ) {
                                        $stats['total_size'] += filesize( $file );
                                }
                        }
                }

                return $stats;
        }

        /**
         * Clear cached CSS and fonts.
         */
        private function clear_cache() {
                $paths = $this->get_paths();
                foreach ( [ $paths['css_dir'], $paths['font_dir'] ] as $dir ) {
                        if ( is_dir( $dir ) ) {
                                foreach ( glob( trailingslashit( $dir ) . '*' ) as $file ) {
                                        wp_delete_file( $file );
                                }
                        }
                }
        }

        /**
         * Determine if hash is locked (for concurrent downloads).
         *
         * @param string $hash
         * @return bool
         */
        private function is_locked( $hash ) {
                return (bool) get_transient( self::LOCK_PREFIX . $hash );
        }

        /**
         * Lock a hash to prevent concurrent downloads.
         *
         * @param string $hash
         */
        private function lock( $hash ) {
                set_transient( self::LOCK_PREFIX . $hash, 1, MINUTE_IN_SECONDS );
        }

        /**
         * Unlock a hash.
         *
         * @param string $hash
         */
        private function unlock( $hash ) {
                delete_transient( self::LOCK_PREFIX . $hash );
        }
}

Wind_Local_Assets::instance();
