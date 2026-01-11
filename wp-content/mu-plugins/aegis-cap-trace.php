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
        'manage_options_traces' => [],
        'cap_checks' => [],
        'last_cap' => null,
        'access_denied' => null,
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
    add_action('admin_page_access_denied', 'aegis_cap_trace_access_denied', PHP_INT_MAX);
    add_action('admin_notices', 'aegis_cap_trace_admin_notice');
}

add_action('init', 'aegis_cap_trace_boot');

function aegis_cap_trace_map_meta_cap($caps, $cap, $user_id, $args)
{
    if (!aegis_cap_trace_enabled()) {
        return $caps;
    }

    $GLOBALS['aegis_cap_trace']['last_cap'] = $cap;
    aegis_cap_trace_record_cap_check([
        'hook' => 'map_meta_cap',
        'cap' => $cap,
        'mapped_caps' => $caps,
    ]);

    if ($cap !== 'manage_options') {
        return $caps;
    }

    aegis_cap_trace_record_manage_options_map([
        'caps' => $caps,
        'has_do_not_allow' => in_array('do_not_allow', $caps, true) ? 'yes' : 'no',
        'backtrace' => aegis_cap_trace_format_backtrace(20),
    ]);

    return $caps;
}

function aegis_cap_trace_user_has_cap($allcaps, $caps, $args, $user)
{
    if (!aegis_cap_trace_enabled()) {
        return $allcaps;
    }

    $cap_name = '';
    if (is_array($args) && !empty($args[0])) {
        $cap_name = (string) $args[0];
    } elseif (is_array($caps) && !empty($caps[0])) {
        $cap_name = (string) $caps[0];
    }

    if ($cap_name !== '') {
        $GLOBALS['aegis_cap_trace']['last_cap'] = $cap_name;
        aegis_cap_trace_record_cap_check([
            'hook' => 'user_has_cap',
            'cap' => $cap_name,
            'result' => !empty($allcaps[$cap_name]) ? 'true' : 'false',
        ]);
    }

    if (!is_array($caps) || !in_array('manage_options', $caps, true)) {
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

function aegis_cap_trace_record_cap_check($entry)
{
    if (!aegis_cap_trace_enabled()) {
        return;
    }

    if (empty($GLOBALS['aegis_cap_trace']['cap_checks'])) {
        $GLOBALS['aegis_cap_trace']['cap_checks'] = [];
    }

    $GLOBALS['aegis_cap_trace']['cap_checks'][] = $entry;
    if (count($GLOBALS['aegis_cap_trace']['cap_checks']) > 30) {
        $GLOBALS['aegis_cap_trace']['cap_checks'] = array_slice($GLOBALS['aegis_cap_trace']['cap_checks'], -30);
    }
}

function aegis_cap_trace_record_manage_options_map($entry)
{
    if (!aegis_cap_trace_enabled()) {
        return;
    }

    if (empty($GLOBALS['aegis_cap_trace']['manage_options_traces'])) {
        $GLOBALS['aegis_cap_trace']['manage_options_traces'] = [];
    }

    $GLOBALS['aegis_cap_trace']['manage_options_traces'][] = $entry;
    if (count($GLOBALS['aegis_cap_trace']['manage_options_traces']) > 30) {
        $GLOBALS['aegis_cap_trace']['manage_options_traces'] = array_slice(
            $GLOBALS['aegis_cap_trace']['manage_options_traces'],
            -30
        );
    }
}

function aegis_cap_trace_format_backtrace($limit = 30)
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $lines = [];
    $base_paths = [
        wp_normalize_path(WP_CONTENT_DIR),
        wp_normalize_path(ABSPATH),
    ];

    foreach ($trace as $index => $frame) {
        if ($index === 0) {
            continue;
        }
        $file = isset($frame['file']) ? wp_normalize_path($frame['file']) : '';
        $line = isset($frame['line']) ? $frame['line'] : '';
        $function = $frame['function'] ?? '';
        $class = $frame['class'] ?? '';
        $type = $frame['type'] ?? '';
        $location = $file !== '' ? str_replace($base_paths, '', $file) : '[internal]';
        $entry = sprintf('%s:%s %s%s%s', $location, $line, $class, $type, $function);
        $lines[] = trim($entry);
        if (count($lines) >= $limit) {
            break;
        }
    }

    return $lines;
}

function aegis_cap_trace_access_denied()
{
    if (!aegis_cap_trace_enabled()) {
        return;
    }

    $GLOBALS['aegis_cap_trace']['access_denied'] = [
        'pagenow' => isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '',
        'uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
        'can_manage_options' => current_user_can('manage_options') ? 'yes' : 'no',
        'can_activate_plugins' => current_user_can('activate_plugins') ? 'yes' : 'no',
        'last_cap' => $GLOBALS['aegis_cap_trace']['last_cap'],
        'backtrace' => aegis_cap_trace_format_backtrace(30),
    ];
}

function aegis_cap_trace_render($extra = [])
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

    $lines[] = 'Recent cap checks (last 30):';
    if (!empty($data['cap_checks'])) {
        foreach ($data['cap_checks'] as $check) {
            $cap_label = $check['cap'] ?? '';
            $hook = $check['hook'] ?? '';
            if (isset($check['mapped_caps'])) {
                $mapped = implode(', ', (array) $check['mapped_caps']);
                $lines[] = sprintf('  - %s %s => [%s]', $hook, $cap_label, $mapped);
                continue;
            }
            $result = $check['result'] ?? '';
            $lines[] = sprintf('  - %s %s => %s', $hook, $cap_label, $result);
        }
    } else {
        $lines[] = '  (no cap checks captured)';
    }

    $lines[] = '';
    $lines[] = 'map_meta_cap manage_options traces (last 30):';
    if (!empty($data['manage_options_traces'])) {
        foreach ((array) $data['manage_options_traces'] as $trace) {
            $lines[] = '  caps: ' . implode(', ', (array) ($trace['caps'] ?? []));
            $lines[] = '  has do_not_allow: ' . ($trace['has_do_not_allow'] ?? '');
            $lines[] = '  backtrace:';
            foreach ((array) ($trace['backtrace'] ?? []) as $trace_line) {
                $lines[] = '    - ' . $trace_line;
            }
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

    $lines[] = '';
    $lines[] = 'admin_page_access_denied:';
    if (!empty($data['access_denied'])) {
        $lines[] = '  pagenow: ' . ($data['access_denied']['pagenow'] ?? '');
        $lines[] = '  uri: ' . ($data['access_denied']['uri'] ?? '');
        $lines[] = '  current_user_can(manage_options): ' . ($data['access_denied']['can_manage_options'] ?? '');
        $lines[] = '  current_user_can(activate_plugins): ' . ($data['access_denied']['can_activate_plugins'] ?? '');
        $lines[] = '  last cap checked: ' . ($data['access_denied']['last_cap'] ?? '');
        $lines[] = '  backtrace:';
        foreach ((array) ($data['access_denied']['backtrace'] ?? []) as $trace_line) {
            $lines[] = '    - ' . $trace_line;
        }
    } else {
        $lines[] = '  (admin_page_access_denied not triggered)';
    }

    if (!empty($extra['wp_die'])) {
        $lines[] = '';
        $lines[] = 'wp_die backtrace:';
        foreach ((array) $extra['wp_die'] as $trace_line) {
            $lines[] = '    - ' . $trace_line;
        }
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
    echo aegis_cap_trace_render([
        'wp_die' => aegis_cap_trace_format_backtrace(30),
    ]);

    if (function_exists('_default_wp_die_handler')) {
        _default_wp_die_handler($message, $title, $args);
        return;
    }

    wp_die($message, $title, $args);
}
