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

        // NOTE: Do NOT remove 'demo-content'. The7 Elementor page-settings includes
        // the7-demo-content.php and requires The7_Demo_Content_Meta_Box class from demo-content module.
        $modules_to_remove = [
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
