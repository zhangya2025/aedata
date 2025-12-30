<?php
/**
 * Plugin Name: Aegis Maintenance
 * Description: Maintenance mode with role and path exemptions compatible with Aegis Safe.
 * Version: 1.0.0
 * Author: Aegis
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AEGIS_MAINTENANCE_VERSION')) {
    define('AEGIS_MAINTENANCE_VERSION', '1.0.0');
}

if (!defined('AEGIS_MAINTENANCE_PLUGIN_DIR')) {
    define('AEGIS_MAINTENANCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

require_once AEGIS_MAINTENANCE_PLUGIN_DIR . 'includes/class-aegis-maintenance.php';
require_once AEGIS_MAINTENANCE_PLUGIN_DIR . 'includes/class-aegis-maintenance-admin.php';
require_once AEGIS_MAINTENANCE_PLUGIN_DIR . 'includes/class-aegis-maintenance-guard.php';

add_action('plugins_loaded', static function () {
    $plugin = new Aegis_Maintenance();
    $plugin->run();
});

register_activation_hook(__FILE__, ['Aegis_Maintenance', 'activate']);
