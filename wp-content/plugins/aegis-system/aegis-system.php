<?php
/**
 * Plugin Name: AEGIS System
 * Description: AEGIS 系统骨架插件，提供模块管理功能。
 * Version: 0.1.0
 * Author: AEGIS
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AEGIS_SYSTEM_VERSION', '0.1.0');
define('AEGIS_SYSTEM_PATH', plugin_dir_path(__FILE__));
define('AEGIS_SYSTEM_URL', plugin_dir_url(__FILE__));
define('AEGIS_SYSTEM_PLUGIN_FILE', __FILE__);

require_once AEGIS_SYSTEM_PATH . 'includes/core/class-system.php';
require_once AEGIS_SYSTEM_PATH . 'includes/core/class-roles.php';
require_once AEGIS_SYSTEM_PATH . 'includes/core/class-access-audit.php';
require_once AEGIS_SYSTEM_PATH . 'includes/core/class-portal.php';
require_once AEGIS_SYSTEM_PATH . 'includes/core/class-schema.php';
require_once AEGIS_SYSTEM_PATH . 'includes/core/class-assets-media.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-access-audit.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-pricing.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-sku.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-sales.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-warehouse-master.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-dealer.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-codes.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-inbound.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-orders.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-shipments.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-returns.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-reports.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-monitoring.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-public-query.php';
require_once AEGIS_SYSTEM_PATH . 'includes/modules/class-reset-b.php';

register_activation_hook(__FILE__, ['AEGIS_System', 'activate']);
new AEGIS_System();
