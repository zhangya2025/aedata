<?php
/**
 * Plugin Name: Aegis Maintenance
 * Description: Maintenance mode guard with scope control.
 * Version: PR-ROLLBACK-MINIMAL-02
 * Author: Aegis
 * Text Domain: windhard-maintenance
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AEGIS_MAINTENANCE_VERSION', 'PR-ROLLBACK-MINIMAL-02');
// MINIMAL_MAINTENANCE_BUILD=01
define('AEGIS_MAINTENANCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AEGIS_MAINTENANCE_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!defined('WINDHARD_MAINTENANCE_VERSION')) {
    define('WINDHARD_MAINTENANCE_VERSION', AEGIS_MAINTENANCE_VERSION);
}
if (!defined('WINDHARD_MAINTENANCE_PLUGIN_DIR')) {
    define('WINDHARD_MAINTENANCE_PLUGIN_DIR', AEGIS_MAINTENANCE_PLUGIN_DIR);
}
if (!defined('WINDHARD_MAINTENANCE_PLUGIN_URL')) {
    define('WINDHARD_MAINTENANCE_PLUGIN_URL', AEGIS_MAINTENANCE_PLUGIN_URL);
}

require_once AEGIS_MAINTENANCE_PLUGIN_DIR . 'includes/class-aegis-maintenance.php';
require_once AEGIS_MAINTENANCE_PLUGIN_DIR . 'includes/class-aegis-maintenance-admin.php';
require_once AEGIS_MAINTENANCE_PLUGIN_DIR . 'includes/class-aegis-maintenance-guard.php';

function aegis_maintenance_init() {
    $plugin = new Aegis_Maintenance();
    $plugin->run();
}
add_action('plugins_loaded', 'aegis_maintenance_init');

if (!function_exists('windhard_maintenance_init')) {
    function windhard_maintenance_init() {
        return aegis_maintenance_init();
    }
}
