<?php
/**
 * Plugin Name: WLA Elementor Static Guard
 * Description: Freeze Elementor/Pro updates and silence external calls/marketing for static deployments.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Defaults if config file missing or invalid.
function wla_esg_default_config() {
    return [
        'enable'         => true,
        'log'            => false,
        'freeze_updates' => true,
        'silence_notices'=> true,
        'block_remote'   => true,
        'fonts_mode'     => 'system',
        'deny_hosts'     => [
            'assets.elementor.com',
            'my.elementor.com',
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'plugins.svn.wordpress.org',
            'github.com',
            'api.github.com',
            'raw.githubusercontent.com',
            'go.elementor.com',
        ],
        'allow_hosts'    => [],
    ];
}

function wla_esg_load_config() {
    static $config;

    if ( null !== $config ) {
        return $config;
    }

    $config = wla_esg_default_config();
    $config_path = __DIR__ . '/wla-config.php';

    if ( file_exists( $config_path ) ) {
        $loaded = include $config_path;

        if ( is_array( $loaded ) ) {
            $config = array_merge( $config, $loaded );
        }
    }

    return $config;
}

function wla_esg_cfg( $key, $default = null ) {
    $config = wla_esg_load_config();

    if ( array_key_exists( $key, $config ) ) {
        return $config[ $key ];
    }

    return $default;
}

function wla_esg_log( $message ) {
    if ( ! wla_esg_cfg( 'log' ) ) {
        return;
    }

    $upload_dir = wp_upload_dir();
    $dir        = trailingslashit( $upload_dir['basedir'] ) . 'wla-logs';

    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }

    $file = trailingslashit( $dir ) . 'elementor-static-guard.log';
    $line = '[' . current_time( 'mysql' ) . '] ' . $message . PHP_EOL;

    file_put_contents( $file, $line, FILE_APPEND );
}

function wla_esg_is_enabled() {
    return (bool) wla_esg_cfg( 'enable', true );
}

function wla_esg_is_host_allowed( $host ) {
    $host = strtolower( $host );

    if ( in_array( $host, (array) wla_esg_cfg( 'allow_hosts', [] ), true ) ) {
        return true;
    }

    if ( 'allow-google' === wla_esg_cfg( 'fonts_mode' ) && ( 'fonts.googleapis.com' === $host || 'fonts.gstatic.com' === $host ) ) {
        return true;
    }

    return false;
}

function wla_esg_is_host_denied( $host ) {
    $host = strtolower( $host );

    if ( wla_esg_is_host_allowed( $host ) ) {
        return false;
    }

    return in_array( $host, (array) wla_esg_cfg( 'deny_hosts', [] ), true );
}

function wla_esg_empty_json_body( $url ) {
    $body = '{}';

    $path = parse_url( $url, PHP_URL_PATH );
    if ( preg_match( '#notifications|promotions|experiments|mixpanel#i', $url ) || ( $path && '.json' === substr( $path, -5 ) ) ) {
        $body = '[]';
    }

    return $body;
}

function wla_esg_pre_http_request( $pre, $r, $url ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'block_remote' ) ) {
        return $pre;
    }

    $host = parse_url( $url, PHP_URL_HOST );

    if ( ! $host ) {
        return $pre;
    }

    $host = strtolower( $host );

    // Fonts are blocked unless explicitly allowed.
    if ( 'system' === wla_esg_cfg( 'fonts_mode' ) && ( 'fonts.googleapis.com' === $host || 'fonts.gstatic.com' === $host ) ) {
        wla_esg_log( 'Blocked fonts host: ' . $url );

        return new WP_Error( 'wla_esg_fonts_blocked', __( 'Remote fonts blocked', 'wla-esg' ) );
    }

    if ( ! wla_esg_is_host_denied( $host ) ) {
        return $pre;
    }

    // Assets endpoints get empty JSON to avoid repeated retries.
    if ( 'assets.elementor.com' === $host ) {
        wla_esg_log( 'Blocked assets endpoint with empty JSON: ' . $url );

        return [
            'headers'  => [],
            'body'     => wla_esg_empty_json_body( $url ),
            'response' => [ 'code' => 200, 'message' => 'OK' ],
            'cookies'  => [],
            'filename' => null,
        ];
    }

    // Block Elementor/Pro cloud APIs and other hosts with an error.
    wla_esg_log( 'Blocked remote request: ' . $url );

    return new WP_Error( 'wla_esg_remote_blocked', __( 'Remote request blocked by Elementor Static Guard', 'wla-esg' ) );
}
add_filter( 'pre_http_request', 'wla_esg_pre_http_request', 10, 3 );

function wla_esg_http_request_args( $args, $url ) {
    if ( ! wla_esg_is_enabled() ) {
        return $args;
    }

    $host = parse_url( $url, PHP_URL_HOST );
    if ( ! $host ) {
        return $args;
    }

    $host = strtolower( $host );
    if ( wla_esg_is_host_denied( $host ) || in_array( $host, [ 'fonts.googleapis.com', 'fonts.gstatic.com' ], true ) ) {
        $args['timeout'] = min( absint( $args['timeout'] ?? 5 ), 5 );
        wla_esg_log( 'Tightened timeout for host: ' . $url );
    }

    return $args;
}
add_filter( 'http_request_args', 'wla_esg_http_request_args', 10, 2 );

function wla_esg_freeze_update_transient( $transient ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'freeze_updates' ) ) {
        return $transient;
    }

    if ( ! is_object( $transient ) ) {
        $transient = new stdClass();
    }

    if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
        $transient->response = [];
    }

    if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
        $transient->no_update = [];
    }

    if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
        $transient->checked = [];
    }

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins    = get_plugins();
    $elementors = [
        'elementor/elementor.php',
        'elementor-pro/elementor-pro.php',
        'pro-elements/pro-elements.php',
    ];

    foreach ( $elementors as $basename ) {
        if ( isset( $transient->response[ $basename ] ) ) {
            unset( $transient->response[ $basename ] );
        }

        if ( isset( $plugins[ $basename ] ) ) {
            $data = $plugins[ $basename ];
            $transient->checked[ $basename ]   = $data['Version'];
            $transient->no_update[ $basename ] = (object) [
                'slug'        => sanitize_title( $data['Name'] ),
                'plugin'      => $basename,
                'new_version' => $data['Version'],
                'package'     => '',
                'icons'       => [],
            ];
        }
    }

    return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'wla_esg_freeze_update_transient' );
add_filter( 'site_transient_update_plugins', 'wla_esg_freeze_update_transient' );

function wla_esg_plugins_api_block( $result, $action, $args ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'freeze_updates' ) ) {
        return $result;
    }

    $targets = [ 'elementor', 'elementor-pro', 'pro-elements' ];
    $slug    = $args->slug ?? '';

    if ( in_array( $slug, $targets, true ) ) {
        wla_esg_log( 'Blocked plugins_api call for: ' . $slug );

        if ( 'plugin_information' === $action ) {
            return new WP_Error( 'wla_esg_plugins_api_blocked', __( 'Elementor updates are frozen by Static Guard.', 'wla-esg' ) );
        }

        return $result;
    }

    return $result;
}
add_filter( 'plugins_api', 'wla_esg_plugins_api_block', 10, 3 );

function wla_esg_prevent_auto_update( $update, $item ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'freeze_updates' ) ) {
        return $update;
    }

    $protected = [ 'elementor/elementor.php', 'elementor-pro/elementor-pro.php', 'pro-elements/pro-elements.php' ];

    if ( in_array( $item->plugin, $protected, true ) ) {
        wla_esg_log( 'Prevented auto-update for: ' . $item->plugin );

        return false;
    }

    return $update;
}
add_filter( 'auto_update_plugin', 'wla_esg_prevent_auto_update', 10, 2 );

function wla_esg_filter_notifications( $notifications ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'silence_notices' ) ) {
        return $notifications;
    }

    wla_esg_log( 'Silenced Elementor admin notifications.' );

    return [];
}
add_filter( 'elementor/core/admin/notifications', 'wla_esg_filter_notifications' );

function wla_esg_remove_admin_notices() {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'silence_notices' ) ) {
        return;
    }

    if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
        return;
    }

    $plugin = \Elementor\Plugin::$instance;

    if ( ! isset( $plugin->admin ) || ! method_exists( $plugin->admin, 'get_component' ) ) {
        return;
    }

    $notice_component = $plugin->admin->get_component( 'admin-notices' );

    if ( $notice_component && has_action( 'admin_notices', [ $notice_component, 'admin_notices' ] ) ) {
        remove_action( 'admin_notices', [ $notice_component, 'admin_notices' ], 20 );
        wla_esg_log( 'Removed Elementor admin_notices output.' );
    }
}
add_action( 'plugins_loaded', 'wla_esg_remove_admin_notices', 25 );

function wla_esg_block_tracker() {
    if ( ! wla_esg_is_enabled() ) {
        return null;
    }

    if ( wla_esg_cfg( 'block_remote' ) || wla_esg_cfg( 'silence_notices' ) ) {
        wla_esg_log( 'Blocked Elementor tracker send.' );

        return false;
    }

    return null;
}
add_filter( 'elementor/tracker/send_override', 'wla_esg_block_tracker' );

function wla_esg_disable_google_fonts( $should_print ) {
    if ( ! wla_esg_is_enabled() ) {
        return $should_print;
    }

    if ( 'system' === wla_esg_cfg( 'fonts_mode' ) || wla_esg_cfg( 'block_remote' ) ) {
        return false;
    }

    return $should_print;
}
add_filter( 'elementor/frontend/print_google_fonts', 'wla_esg_disable_google_fonts' );

function wla_esg_strip_font_src( $src ) {
    if ( ! wla_esg_is_enabled() ) {
        return $src;
    }

    if ( empty( $src ) ) {
        return $src;
    }

    $host = parse_url( $src, PHP_URL_HOST );

    if ( ! $host ) {
        return $src;
    }

    $host = strtolower( $host );

    if ( ( 'fonts.googleapis.com' === $host || 'fonts.gstatic.com' === $host ) && ( 'system' === wla_esg_cfg( 'fonts_mode' ) || wla_esg_cfg( 'block_remote' ) ) && ! wla_esg_is_host_allowed( $host ) ) {
        wla_esg_log( 'Stripped font asset: ' . $src );

        return '';
    }

    return $src;
}
add_filter( 'style_loader_src', 'wla_esg_strip_font_src', 10 );
add_filter( 'script_loader_src', 'wla_esg_strip_font_src', 10 );

