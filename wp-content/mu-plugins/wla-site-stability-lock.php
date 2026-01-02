<?php
/**
 * Plugin Name: WLA Site Stability Lock (S1-lite)
 * Description: Locks down updates, installs, deletions, editors, and update-related outbound requests site-wide.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wla_ssl_default_config() {
    return [
        'enable' => true,
        'log' => false,
        'lock_file_mods' => true,
        'lock_caps' => true,
        'freeze_updates' => true,
        'disable_update_cron' => true,
        'block_update_hosts' => true,
        'update_deny_hosts' => [
            'api.wordpress.org',
            'downloads.wordpress.org',
            'plugins.svn.wordpress.org',
            'github.com',
            'api.github.com',
            'raw.githubusercontent.com',
        ],
        'allow_delete' => false,
        'allow_delete_until' => 0,
    ];
}

function wla_ssl_load_config() {
    static $config = null;

    if ( null !== $config ) {
        return $config;
    }

    $config = wla_ssl_default_config();
    $config_path = __DIR__ . '/wla-site-config.php';

    if ( file_exists( $config_path ) ) {
        $loaded = include $config_path;

        if ( is_array( $loaded ) ) {
            $config = array_merge( $config, $loaded );
        }
    }

    return $config;
}

function wla_ssl_cfg( $key, $default = null ) {
    $config = wla_ssl_load_config();

    if ( array_key_exists( $key, $config ) ) {
        return $config[ $key ];
    }

    return $default;
}

function wla_ssl_enabled() {
    return (bool) wla_ssl_cfg( 'enable', true );
}

function wla_ssl_allow_delete() {
    $allow_flag = (bool) wla_ssl_cfg( 'allow_delete', false );
    $allow_until = (int) wla_ssl_cfg( 'allow_delete_until', 0 );

    if ( $allow_until > 0 && time() <= $allow_until ) {
        return true;
    }

    return $allow_flag;
}

function wla_ssl_log( $message ) {
    if ( ! wla_ssl_cfg( 'log', false ) ) {
        return;
    }

    $upload_dir = wp_upload_dir();
    $dir        = trailingslashit( $upload_dir['basedir'] ) . 'wla-logs';

    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }

    $file = trailingslashit( $dir ) . 'site-stability-lock.log';
    $line = '[' . current_time( 'mysql' ) . '] ' . $message . PHP_EOL;

    file_put_contents( $file, $line, FILE_APPEND );
}

function wla_ssl_apply_file_mod_constants() {
    if ( ! wla_ssl_enabled() || ! wla_ssl_cfg( 'lock_file_mods', true ) ) {
        return;
    }

    if ( ! wla_ssl_allow_delete() && ! defined( 'DISALLOW_FILE_MODS' ) ) {
        define( 'DISALLOW_FILE_MODS', true );
    }

    if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
        define( 'DISALLOW_FILE_EDIT', true );
    }
}

wla_ssl_apply_file_mod_constants();

if ( ! wla_ssl_enabled() ) {
    return;
}

function wla_ssl_locked_caps() {
    $caps = [
        'update_plugins',
        'update_themes',
        'update_core',
        'install_plugins',
        'install_themes',
        'delete_plugins',
        'delete_themes',
        'edit_plugins',
        'edit_themes',
    ];

    if ( wla_ssl_allow_delete() ) {
        $caps = array_diff( $caps, [ 'delete_plugins', 'delete_themes' ] );
    }

    return $caps;
}

function wla_ssl_cap_filter( $allcaps ) {
    if ( ! wla_ssl_cfg( 'lock_caps', true ) ) {
        return $allcaps;
    }

    foreach ( wla_ssl_locked_caps() as $cap ) {
        if ( isset( $allcaps[ $cap ] ) ) {
            $allcaps[ $cap ] = false;
        }
    }

    return $allcaps;
}
add_filter( 'user_has_cap', 'wla_ssl_cap_filter', 20 );

function wla_ssl_map_meta_cap( $caps, $cap ) {
    if ( ! wla_ssl_cfg( 'lock_caps', true ) ) {
        return $caps;
    }

    if ( in_array( $cap, wla_ssl_locked_caps(), true ) ) {
        return [ 'do_not_allow' ];
    }

    return $caps;
}
add_filter( 'map_meta_cap', 'wla_ssl_map_meta_cap', 10, 2 );

function wla_ssl_empty_plugin_transient() {
    return (object) [
        'last_checked' => time(),
        'response' => [],
        'translations' => [],
    ];
}

function wla_ssl_empty_theme_transient() {
    return (object) [
        'last_checked' => time(),
        'response' => [],
        'translations' => [],
    ];
}

function wla_ssl_empty_core_transient() {
    return (object) [
        'last_checked' => time(),
        'version_checked' => get_bloginfo( 'version' ),
        'updates' => [],
        'translations' => [],
    ];
}

if ( wla_ssl_cfg( 'freeze_updates', true ) ) {
    add_filter( 'pre_set_site_transient_update_plugins', function () {
        wla_ssl_log( 'freeze plugin updates' );
        return wla_ssl_empty_plugin_transient();
    } );

    add_filter( 'pre_set_site_transient_update_themes', function () {
        wla_ssl_log( 'freeze theme updates' );
        return wla_ssl_empty_theme_transient();
    } );

    add_filter( 'pre_site_transient_update_core', function () {
        wla_ssl_log( 'freeze core updates' );
        return wla_ssl_empty_core_transient();
    } );

    add_filter( 'auto_update_plugin', '__return_false' );
    add_filter( 'auto_update_theme', '__return_false' );
    add_filter( 'auto_update_core', '__return_false' );
}

function wla_ssl_clear_update_crons() {
    if ( ! wla_ssl_cfg( 'disable_update_cron', true ) ) {
        return;
    }

    wp_clear_scheduled_hook( 'wp_update_plugins' );
    wp_clear_scheduled_hook( 'wp_update_themes' );
    wp_clear_scheduled_hook( 'wp_version_check' );
}
add_action( 'init', 'wla_ssl_clear_update_crons', 5 );

function wla_ssl_block_update_hosts( $preempt, $args, $url ) {
    if ( ! wla_ssl_cfg( 'block_update_hosts', true ) ) {
        return $preempt;
    }

    $host = parse_url( $url, PHP_URL_HOST );

    if ( ! $host ) {
        return $preempt;
    }

    $host = strtolower( $host );

    if ( in_array( $host, (array) wla_ssl_cfg( 'update_deny_hosts', [] ), true ) ) {
        wla_ssl_log( 'blocked update host: ' . $host );

        return [
            'headers' => [],
            'body' => '',
            'response' => [
                'code' => 200,
                'message' => 'OK',
            ],
        ];
    }

    return $preempt;
}
add_filter( 'pre_http_request', 'wla_ssl_block_update_hosts', 5, 3 );
