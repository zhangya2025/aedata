<?php
/**
 * Plugin Name: Aegis Perf - The7 Modules Slimmer
 * Description: Slim down The7 modules on frontend and REST requests to reduce initialization overhead.
 */

if (!defined('AEGIS_THE7_FRONT_MODULE_SLIM')) {
    define('AEGIS_THE7_FRONT_MODULE_SLIM', true);
}

add_filter(
    'the7_active_modules',
    function ($modules) {
        if (is_admin()) {
            return $modules;
        }

        if (!AEGIS_THE7_FRONT_MODULE_SLIM) {
            return $modules;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return $modules;
        }

        // Keep backend unchanged; apply to frontend and REST requests.
        $is_rest_request = defined('REST_REQUEST') && REST_REQUEST;

        $modules_to_remove = [
            'demo-content',
            'bundled-content',
            'dev-mode',
            'dev-tools',
        ];

        if (!is_array($modules)) {
            return $modules;
        }

        return array_values(array_diff($modules, $modules_to_remove));
    },
    9999
);
