<?php
/**
 * Plugin Name: Aegis Capability Trace
 * Description: Diagnostic tracer for manage_options capability mapping in wp-admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

function aegis_cap_trace_enabled()
{
    return is_admin()
        && isset($_GET['aegis_cap_trace'])
        && $_GET['aegis_cap_trace'] === '1';
}

function aegis_cap_trace_boot()
{
    if (!aegis_cap_trace_enabled()) {
        return;
    }

    $GLOBALS['aegis_cap_trace'] = [
        'user' => null,
        'request' => null,
        'map_meta_cap' => null,
        'user_has_cap' => null,
    ];

    $user = wp_get_current_user();
    $user_id = $user instanceof WP_User ? (int) $user->ID : 0;
    $roles = $user instanceof WP_User ? (array) $user->roles : [];

    $GLOBALS['aegis_cap_trace']['user'] = [
        'id' => $user_id,
        'roles' => $roles,
        'can_manage_options' => current_user_can('manage_options') ? 'yes' : 'no',
        'can_activate_plugins' => current_user_can('activate_plugins') ? 'yes' : 'no',
    ];

    $GLOBALS['aegis_cap_trace']['request'] = [
        'pagenow' => isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '',
        'uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
    ];

    add_filter('map_meta_cap', 'aegis_cap_trace_map_meta_cap', PHP_INT_MAX, 4);
    add_filter('user_has_cap', 'aegis_cap_trace_user_has_cap', PHP_INT_MAX, 4);
    add_filter('wp_die_handler', 'aegis_cap_trace_wp_die_handler');
    add_action('admin_notices', 'aegis_cap_trace_admin_notice');
}

add_action('init', 'aegis_cap_trace_boot');

function aegis_cap_trace_map_meta_cap($caps, $cap, $user_id, $args)
{
    if (!aegis_cap_trace_enabled()) {
        return $caps;
    }

    if ($cap !== 'manage_options') {
        return $caps;
    }

    if (!empty($GLOBALS['aegis_cap_trace']['map_meta_cap'])) {
        return $caps;
    }

    $backtrace = wp_debug_backtrace_summary(null, 0, false);
    $backtrace = is_array($backtrace) ? array_slice(array_reverse($backtrace), 0, 10) : [$backtrace];

    $GLOBALS['aegis_cap_trace']['map_meta_cap'] = [
        'caps' => $caps,
        'has_do_not_allow' => in_array('do_not_allow', $caps, true) ? 'yes' : 'no',
        'backtrace' => $backtrace,
    ];

    return $caps;
}

function aegis_cap_trace_user_has_cap($allcaps, $caps, $args, $user)
{
    if (!aegis_cap_trace_enabled()) {
        return $allcaps;
    }

    if (!is_array($caps) || !in_array('manage_options', $caps, true)) {
        return $allcaps;
    }

    if (!empty($GLOBALS['aegis_cap_trace']['user_has_cap'])) {
        return $allcaps;
    }

    $backtrace = wp_debug_backtrace_summary(null, 0, false);
    $backtrace = is_array($backtrace) ? array_slice(array_reverse($backtrace), 0, 10) : [$backtrace];

    $GLOBALS['aegis_cap_trace']['user_has_cap'] = [
        'manage_options' => !empty($allcaps['manage_options']) ? 'true' : 'false',
        'backtrace' => $backtrace,
    ];

    return $allcaps;
}

function aegis_cap_trace_render()
{
    if (empty($GLOBALS['aegis_cap_trace'])) {
        return '';
    }

    $data = $GLOBALS['aegis_cap_trace'];

    $lines = [];
    $lines[] = 'AEGIS Capability Trace (aegis_cap_trace=1)';
    $lines[] = '';
    $lines[] = 'User:';
    $lines[] = '  ID: ' . ($data['user']['id'] ?? 0);
    $lines[] = '  Roles: ' . implode(',', $data['user']['roles'] ?? []);
    $lines[] = '  current_user_can(manage_options): ' . ($data['user']['can_manage_options'] ?? '');
    $lines[] = '  current_user_can(activate_plugins): ' . ($data['user']['can_activate_plugins'] ?? '');
    $lines[] = '';
    $lines[] = 'Request:';
    $lines[] = '  pagenow: ' . ($data['request']['pagenow'] ?? '');
    $lines[] = '  uri: ' . ($data['request']['uri'] ?? '');
    $lines[] = '';

    $lines[] = 'map_meta_cap manage_options:';
    if (!empty($data['map_meta_cap'])) {
        $lines[] = '  caps: ' . implode(', ', (array) $data['map_meta_cap']['caps']);
        $lines[] = '  has do_not_allow: ' . ($data['map_meta_cap']['has_do_not_allow'] ?? '');
        $lines[] = '  backtrace:';
        foreach ((array) ($data['map_meta_cap']['backtrace'] ?? []) as $trace_line) {
            $lines[] = '    - ' . $trace_line;
        }
    } else {
        $lines[] = '  (no manage_options map_meta_cap trace captured)';
    }

    $lines[] = '';
    $lines[] = 'user_has_cap manage_options:';
    if (!empty($data['user_has_cap'])) {
        $lines[] = '  manage_options in allcaps: ' . ($data['user_has_cap']['manage_options'] ?? '');
        $lines[] = '  backtrace:';
        foreach ((array) ($data['user_has_cap']['backtrace'] ?? []) as $trace_line) {
            $lines[] = '    - ' . $trace_line;
        }
    } else {
        $lines[] = '  (no manage_options user_has_cap trace captured)';
    }

    $output = implode("\n", $lines);

    return '<pre style="white-space: pre-wrap;">' . esc_html($output) . '</pre>';
}

function aegis_cap_trace_admin_notice()
{
    if (!aegis_cap_trace_enabled()) {
        return;
    }

    echo '<div class="notice notice-info">' . aegis_cap_trace_render() . '</div>';
}

function aegis_cap_trace_wp_die_handler($handler)
{
    if (!aegis_cap_trace_enabled()) {
        return $handler;
    }

    return 'aegis_cap_trace_handle_wp_die';
}

function aegis_cap_trace_handle_wp_die($message, $title = '', $args = [])
{
    echo aegis_cap_trace_render();

    if (function_exists('_default_wp_die_handler')) {
        _default_wp_die_handler($message, $title, $args);
        return;
    }

    wp_die($message, $title, $args);
}
