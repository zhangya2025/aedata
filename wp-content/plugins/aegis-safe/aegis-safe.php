<?php
/**
 * Plugin Name: Aegis Safe
 * Description: Enforce a private login entrance at /aegislogin.php with request-level guards.
 * Version: 4.0.0
 * Author: Aegis
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aegis_Safe {

    const LOGIN_SLUG  = 'aegislogin';
    const LEGACY_LOGIN_SLUG = 'windlogin';
    const GLOBAL_FLAG = '__aegis_safe_login_whitelist';

    private $is_whitelisted     = false;
    private $is_private_request = false;
    private $request_path       = '/';
    private $is_legacy_private_request = false;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'detect_private_entry' ], 0 );
        add_action( 'init', [ $this, 'guard_login_and_admin' ], 0 );
        add_action( 'template_redirect', [ $this, 'handle_private_login' ], 0 );

        add_filter( 'login_url', [ $this, 'filter_login_url' ], 10, 3 );
        add_filter( 'lostpassword_url', [ $this, 'filter_login_related_url' ], 10, 2 );
        add_filter( 'register_url', [ $this, 'filter_login_related_url' ], 10, 1 );
        add_filter( 'logout_url', [ $this, 'filter_logout_url' ], 10, 2 );
        add_filter( 'logout_redirect', [ $this, 'filter_logout_redirect' ], 10, 3 );

        add_filter( 'site_url', [ $this, 'rewrite_core_login_urls' ], 10, 4 );
        add_filter( 'network_site_url', [ $this, 'rewrite_core_login_urls' ], 10, 4 );
        add_filter( 'wp_redirect', [ $this, 'rewrite_login_redirect' ], 10, 2 );
    }

    /* ---------- detection ---------- */

    public function detect_private_entry() {
        $this->request_path = $this->current_path();

        if ( $this->is_private_path( $this->request_path ) ) {
            $this->is_whitelisted     = true;
            $this->is_private_request = true;
            $GLOBALS[ self::GLOBAL_FLAG ] = true;
        } elseif ( $this->is_legacy_private_path( $this->request_path ) ) {
            $this->is_private_request        = true;
            $this->is_legacy_private_request = true;
        }
    }

    /* ---------- guards ---------- */

    public function guard_login_and_admin() {
        if ( $this->should_bypass() || $this->is_recovery_mode() ) {
            return;
        }

        if ( $this->is_core_login_request() && ! $this->is_whitelisted ) {
            $this->deny_request();
        }

        if ( is_admin() && ! is_user_logged_in() && ! $this->is_whitelisted ) {
            $this->deny_request();
        }
    }

    /* ---------- main dispatcher ---------- */

    public function handle_private_login() {
        if ( $this->is_legacy_private_request ) {
            $this->redirect_legacy_private_request();
        }

        if ( $this->is_private_request && ! $this->is_whitelisted ) {
            $this->deny_request();
        }

        if ( ! $this->is_whitelisted || $this->should_bypass() ) {
            return;
        }

        if ( $this->maybe_delegate_logout() ) {
            return;
        }

        $this->delegate_to_core_login();
    }

    /* ---------- core delegation ---------- */

    private function delegate_to_core_login() {
        nocache_headers();
        status_header( 200 );

        if ( is_user_logged_in() ) {
            wp_safe_redirect( admin_url() );
            exit;
        }

        global $user_login, $error, $errors, $action;

        $user_login = $user_login ?? '';
        $error      = $error ?? '';
        $errors     = ( isset( $errors ) && $errors instanceof WP_Error ) ? $errors : new WP_Error();
        $action     = $action ?? 'login';

        $GLOBALS['pagenow']    = 'wp-login.php';
        $_SERVER['SCRIPT_NAME'] = '/wp-login.php';
        $_SERVER['PHP_SELF']    = '/wp-login.php';

        require ABSPATH . 'wp-login.php';
        exit;
    }

    private function maybe_delegate_logout() {
        if ( ! $this->is_logout_request() ) {
            return false;
        }

        if ( ! is_user_logged_in() ) {
            return false;
        }

        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'log-out' ) ) {
            return false;
        }

        if ( ! defined( 'AEGIS_SAFE_LOGOUT_PROXY' ) ) {
            define( 'AEGIS_SAFE_LOGOUT_PROXY', true );
        }

        $_GET['action']     = 'logout';
        $_REQUEST['action'] = 'logout';

        $redirect = $_REQUEST['redirect_to'] ?? '';
        if ( empty( $redirect ) ) {
            $_GET['redirect_to']     = $this->private_login_url();
            $_REQUEST['redirect_to'] = $this->private_login_url();
        }

        $GLOBALS['pagenow']     = 'wp-login.php';
        $_SERVER['SCRIPT_NAME'] = '/wp-login.php';
        $_SERVER['PHP_SELF']    = '/wp-login.php';

        require ABSPATH . 'wp-login.php';
        exit;
    }

    /* ---------- URL rewriting ---------- */

    public function filter_login_url( $login_url, $redirect, $force_reauth ) {
        $url = $this->private_login_url();

        if ( $redirect ) {
            $url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
        }
        if ( $force_reauth ) {
            $url = add_query_arg( 'reauth', '1', $url );
        }
        return $url;
    }

    public function filter_login_related_url( $url, $redirect = '' ) {
        $target = $this->private_login_url();
        if ( $redirect ) {
            $target = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $target );
        }
        return $target;
    }

    public function filter_logout_url( $logout_url, $redirect ) {
        $target = $this->private_login_url();

        $params = [];
        $parts  = wp_parse_url( $logout_url );
        if ( ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $params );
        }

        $params['action'] = 'logout';
        if ( ! empty( $redirect ) ) {
            $params['redirect_to'] = $redirect;
        }

        return add_query_arg( $params, $target );
    }

    public function filter_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
        return $this->private_login_url();
    }

    public function rewrite_core_login_urls( $url ) {
        return $this->is_private_context()
            ? $this->rewrite_login_target( $url )
            : $url;
    }

    public function rewrite_login_redirect( $location ) {
        return $this->is_private_context()
            ? $this->rewrite_login_target( $location )
            : $location;
    }

    /* ---------- helpers ---------- */

    private function rewrite_login_target( $url ) {
        $parts = wp_parse_url( $url );
        if ( empty( $parts['path'] ) || ! str_contains( $parts['path'], 'wp-login.php' ) ) {
            return $url;
        }

        $target = $this->private_login_url();
        if ( ! empty( $parts['query'] ) ) {
            $target .= '?' . $parts['query'];
        }
        return $target;
    }

    private function is_private_context() {
        return $this->is_whitelisted || $this->is_private_request;
    }

    private function private_login_url() {
        return home_url( '/' . self::LOGIN_SLUG . '.php' );
    }

    private function is_private_path( $path ) {
        $normalized = rtrim( $path, '/' );
        $slug = '/' . self::LOGIN_SLUG;

        return $normalized === $slug
            || $normalized === $slug . '.php'
            || str_ends_with( $normalized, $slug )
            || str_ends_with( $normalized, $slug . '.php' )
            || $this->looks_like_login_script( self::LOGIN_SLUG );
    }

    private function is_legacy_private_path( $path ) {
        $normalized = rtrim( $path, '/' );
        $slug       = '/' . self::LEGACY_LOGIN_SLUG;

        return $normalized === $slug
            || $normalized === $slug . '.php'
            || str_ends_with( $normalized, $slug )
            || str_ends_with( $normalized, $slug . '.php' )
            || $this->looks_like_login_script( self::LEGACY_LOGIN_SLUG );
    }

    private function looks_like_login_script( $slug ) {
        $script_name = basename( $_SERVER['SCRIPT_NAME'] ?? '' );
        $php_self    = basename( $_SERVER['PHP_SELF'] ?? '' );

        return in_array( $script_name, [ $slug . '.php' ], true )
            || in_array( $php_self, [ $slug . '.php' ], true );
    }

    private function is_core_login_request() {
        if ( defined( 'AEGIS_SAFE_LOGOUT_PROXY' ) && AEGIS_SAFE_LOGOUT_PROXY ) {
            return false;
        }

        return str_contains( $this->request_path, 'wp-login.php' )
            || ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' );
    }

    private function current_path() {
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url( $uri, PHP_URL_PATH );
        return $path ? '/' . ltrim( $path, '/' ) : '/';
    }

    private function is_logout_request() {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'logout' ) {
            return false;
        }

        return $this->is_private_path( $this->request_path );
    }

    private function is_recovery_mode() {
        if ( ! isset( $_GET['aegis_recover'] ) || $_GET['aegis_recover'] !== '1' ) {
            return false;
        }

        return $this->is_core_login_request();
    }

    private function should_bypass() {
        return ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
            || ( defined( 'DOING_CRON' ) && DOING_CRON );
    }

    private function deny_request() {
        nocache_headers();
        status_header( 404 );
        exit;
    }

    private function redirect_legacy_private_request() {
        $target = $this->private_login_url();

        if ( empty( $target ) ) {
            $this->deny_request();
        }

        wp_safe_redirect( $target, 301 );
        exit;
    }

    public static function on_activation(): void {
        $primary_path = ABSPATH . self::LOGIN_SLUG . '.php';
        $legacy_path  = ABSPATH . self::LEGACY_LOGIN_SLUG . '.php';

        $primary_created = self::ensure_entry_file( $primary_path, self::primary_entry_contents() );

        if ( ! $primary_created ) {
            add_action(
                'admin_notices',
                static function () use ( $primary_path ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Aegis Safe could not create the login entry file at %s. Please create it manually to avoid 404 errors.', 'aegis-safe' ), $primary_path ) ) . '</p></div>';
                }
            );
        }

        if ( ! file_exists( $legacy_path ) ) {
            self::ensure_entry_file( $legacy_path, self::legacy_entry_contents() );
        }
    }

    private static function ensure_entry_file( $path, $contents ) {
        if ( file_exists( $path ) ) {
            return true;
        }

        $written = @file_put_contents( $path, $contents );

        return $written !== false;
    }

    private static function primary_entry_contents() {
        return "<?php\n" .
            "define('WP_USE_THEMES', false);\n" .
            "require __DIR__ . '/wp-blog-header.php';\n";
    }

    private static function legacy_entry_contents() {
        $target = '/' . self::LOGIN_SLUG . '.php';

        return "<?php\n" .
            "header('Location: {$target}', true, 301);\n" .
            "exit;\n";
    }
}

register_activation_hook( __FILE__, [ 'Aegis_Safe', 'on_activation' ] );
new Aegis_Safe();
