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
        'freeze_all_updates' => true,
        'freeze_updates' => true,
        'freeze_themes'  => true,
        'freeze_core'    => true,
        'silence_notices'=> true,
        'block_remote'   => true,
        'hide_plugin_row_upsell' => true,
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
            'api.wordpress.org',
            'downloads.wordpress.org',
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

function wla_esg_elementor_plugins() {
    return [
        'elementor/elementor.php',
        'elementor-pro/elementor-pro.php',
        'pro-elements/pro-elements.php',
    ];
}

function wla_esg_empty_json_body( $url ) {
    $path = parse_url( $url, PHP_URL_PATH );

    if ( $path && false !== stripos( $path, 'notifications' ) ) {
        return '[]';
    }

    return '{}';
}

function wla_esg_fake_wporg_body( $url ) {
    $path = parse_url( $url, PHP_URL_PATH );

    if ( $path && false !== strpos( $path, '/plugins/update-check' ) ) {
        return maybe_serialize( (object) [
            'plugins'      => [],
            'translations' => [],
        ] );
    }

    if ( $path && false !== strpos( $path, '/themes/update-check' ) ) {
        return maybe_serialize( (object) [
            'themes'       => [],
            'translations' => [],
        ] );
    }

    if ( $path && false !== strpos( $path, '/core/version-check' ) ) {
        $version = get_bloginfo( 'version' );
        return wp_json_encode( [
            'offers'          => [],
            'translations'    => [],
            'version_checked' => $version,
        ] );
    }

    return '{}';
}

function wla_esg_fake_http_response( $body ) {
    return [
        'headers'  => [],
        'body'     => $body,
        'response' => [ 'code' => 200, 'message' => 'OK' ],
        'cookies'  => [],
        'filename' => null,
    ];
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
        if ( wla_esg_is_host_allowed( $host ) ) {
            return $pre;
        }

        wla_esg_log( 'Blocked fonts host: ' . $url );

        return new WP_Error( 'wla_esg_fonts_blocked', __( 'Remote fonts blocked', 'wla-esg' ) );
    }

    if ( ! wla_esg_is_host_denied( $host ) ) {
        return $pre;
    }

    // Assets endpoints get empty JSON to avoid repeated retries.
    if ( 'assets.elementor.com' === $host ) {
        $body = wla_esg_empty_json_body( $url );
        wla_esg_log( 'Blocked assets endpoint with empty JSON: ' . $url );

        return wla_esg_fake_http_response( $body );
    }

    if ( 'api.wordpress.org' === $host ) {
        $body = wla_esg_fake_wporg_body( $url );
        wla_esg_log( 'Blocked api.wordpress.org with fake body: ' . $url );

        return wla_esg_fake_http_response( $body );
    }

    // Block Elementor/Pro cloud APIs and other hosts with an error or empty response for downloads.
    if ( 'downloads.wordpress.org' === $host ) {
        wla_esg_log( 'Blocked downloads.wordpress.org request: ' . $url );

        return new WP_Error( 'wla_esg_download_blocked', __( 'Download blocked by Elementor Static Guard', 'wla-esg' ) );
    }

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
    if ( ! wla_esg_is_enabled() || ( ! wla_esg_cfg( 'freeze_updates' ) && ! wla_esg_cfg( 'freeze_all_updates' ) ) ) {
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

    $plugins = get_plugins();

    if ( wla_esg_cfg( 'freeze_all_updates' ) ) {
        $transient->response = [];

        foreach ( $plugins as $basename => $data ) {
            $transient->checked[ $basename ]   = $data['Version'];
            $transient->no_update[ $basename ] = (object) [
                'slug'        => sanitize_title( $data['Name'] ),
                'plugin'      => $basename,
                'new_version' => $data['Version'],
                'package'     => '',
                'icons'       => [],
            ];
        }

        return $transient;
    }

    $elementors = wla_esg_elementor_plugins();

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

function wla_esg_freeze_theme_transient( $transient ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'freeze_themes' ) ) {
        return $transient;
    }

    if ( ! is_object( $transient ) ) {
        $transient = new stdClass();
    }

    if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
        $transient->response = [];
    }

    if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
        $transient->checked = [];
    }

    if ( ! function_exists( 'wp_get_themes' ) ) {
        require_once ABSPATH . 'wp-includes/theme.php';
    }

    $themes = wp_get_themes();

    foreach ( $themes as $slug => $theme ) {
        $transient->checked[ $slug ] = $theme->get( 'Version' );
    }

    return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'wla_esg_freeze_theme_transient' );
add_filter( 'site_transient_update_themes', 'wla_esg_freeze_theme_transient' );

function wla_esg_freeze_core_update( $value ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'freeze_core' ) ) {
        return $value;
    }

    $version = get_bloginfo( 'version' );
    $empty   = new stdClass();
    $empty->updates          = [];
    $empty->version_checked  = $version;
    $empty->translations     = [];

    return $empty;
}
add_filter( 'pre_site_transient_update_core', 'wla_esg_freeze_core_update' );
add_filter( 'site_transient_update_core', 'wla_esg_freeze_core_update' );

function wla_esg_disable_core_auto_updates( $value ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'freeze_core' ) ) {
        return $value;
    }

    return false;
}
add_filter( 'allow_major_auto_core_updates', 'wla_esg_disable_core_auto_updates' );
add_filter( 'allow_minor_auto_core_updates', 'wla_esg_disable_core_auto_updates' );
add_filter( 'auto_update_core', 'wla_esg_disable_core_auto_updates' );

function wla_esg_plugins_api_block( $result, $action, $args ) {
    if ( ! wla_esg_is_enabled() || ( ! wla_esg_cfg( 'freeze_updates' ) && ! wla_esg_cfg( 'freeze_all_updates' ) ) ) {
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
    if ( ! wla_esg_is_enabled() || ( ! wla_esg_cfg( 'freeze_updates' ) && ! wla_esg_cfg( 'freeze_all_updates' ) ) ) {
        return $update;
    }

    if ( wla_esg_cfg( 'freeze_all_updates' ) ) {
        wla_esg_log( 'Prevented auto-update globally for: ' . $item->plugin );

        return false;
    }

    $protected = wla_esg_elementor_plugins();

    if ( in_array( $item->plugin, $protected, true ) ) {
        wla_esg_log( 'Prevented auto-update for: ' . $item->plugin );

        return false;
    }

    return $update;
}
add_filter( 'auto_update_plugin', 'wla_esg_prevent_auto_update', 10, 2 );

function wla_esg_strip_upsell_links( $links ) {
    $keywords = [ 'pro', 'upgrade', 'get pro', 'purchase', 'buy', 'renew', 'go pro' ];

    foreach ( $links as $key => $link ) {
        $text = wp_strip_all_tags( $link );
        foreach ( $keywords as $keyword ) {
            if ( false !== stripos( $text, $keyword ) ) {
                unset( $links[ $key ] );
                break;
            }
        }
    }

    return $links;
}

function wla_esg_plugin_action_links( $links, $plugin_file ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'hide_plugin_row_upsell' ) ) {
        return $links;
    }

    if ( ! in_array( $plugin_file, wla_esg_elementor_plugins(), true ) ) {
        return $links;
    }

    if ( function_exists( 'get_current_screen' ) ) {
        $screen = get_current_screen();
        if ( $screen && ! in_array( $screen->base, [ 'plugins', 'plugins-network' ], true ) ) {
            return $links;
        }
    }

    $filtered = wla_esg_strip_upsell_links( $links );

    return array_values( $filtered );
}
add_filter( 'plugin_action_links', 'wla_esg_plugin_action_links', 20, 2 );
add_filter( 'plugin_action_links_' . 'elementor/elementor.php', 'wla_esg_plugin_action_links', 20, 2 );
add_filter( 'plugin_action_links_' . 'elementor-pro/elementor-pro.php', 'wla_esg_plugin_action_links', 20, 2 );
add_filter( 'plugin_action_links_' . 'pro-elements/pro-elements.php', 'wla_esg_plugin_action_links', 20, 2 );

function wla_esg_plugin_row_meta( $links, $plugin_file ) {
    if ( ! wla_esg_is_enabled() || ! wla_esg_cfg( 'hide_plugin_row_upsell' ) ) {
        return $links;
    }

    if ( ! in_array( $plugin_file, wla_esg_elementor_plugins(), true ) ) {
        return $links;
    }

    if ( function_exists( 'get_current_screen' ) ) {
        $screen = get_current_screen();
        if ( $screen && ! in_array( $screen->base, [ 'plugins', 'plugins-network' ], true ) ) {
            return $links;
        }
    }

    $filtered = wla_esg_strip_upsell_links( $links );

    return array_values( $filtered );
}
add_filter( 'plugin_row_meta', 'wla_esg_plugin_row_meta', 20, 2 );

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

    if ( $notice_component ) {
        $priority = has_action( 'admin_notices', [ $notice_component, 'admin_notices' ] );
        if ( is_numeric( $priority ) ) {
            remove_action( 'admin_notices', [ $notice_component, 'admin_notices' ], (int) $priority );
            wla_esg_log( 'Removed Elementor admin_notices output at priority ' . $priority . '.' );
        }
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

function wla_esg_strip_font_tag( $tag, $handle, $src ) {
    if ( ! wla_esg_is_enabled() ) {
        return $tag;
    }

    if ( empty( $src ) ) {
        return $tag;
    }

    $host = parse_url( $src, PHP_URL_HOST );

    if ( ! $host ) {
        return $tag;
    }

    $host = strtolower( $host );

    if ( ( 'fonts.googleapis.com' === $host || 'fonts.gstatic.com' === $host ) && ( 'system' === wla_esg_cfg( 'fonts_mode' ) || wla_esg_cfg( 'block_remote' ) ) && ! wla_esg_is_host_allowed( $host ) ) {
        wla_esg_log( 'Removed font tag for: ' . $src );

        return '';
    }

    return $tag;
}
add_filter( 'style_loader_tag', 'wla_esg_strip_font_tag', 10, 3 );
add_filter( 'script_loader_tag', 'wla_esg_strip_font_tag', 10, 3 );

function wla_esg_clear_update_crons() {
    if ( ! wla_esg_is_enabled() ) {
        return;
    }

    if ( wla_esg_cfg( 'freeze_all_updates' ) || wla_esg_cfg( 'freeze_updates' ) ) {
        wp_clear_scheduled_hook( 'wp_update_plugins' );
    }

    if ( wla_esg_cfg( 'freeze_themes' ) ) {
        wp_clear_scheduled_hook( 'wp_update_themes' );
    }

    if ( wla_esg_cfg( 'freeze_core' ) ) {
        wp_clear_scheduled_hook( 'wp_version_check' );
    }
}
add_action( 'init', 'wla_esg_clear_update_crons', 20 );

