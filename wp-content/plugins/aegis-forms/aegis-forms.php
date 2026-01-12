<?php
/**
 * Plugin Name: Aegis Forms
 * Description: Admin shell and health check page for Aegis Forms.
 * Version: 0.1.0
 * Author: Aegis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AEGIS_FORMS_VERSION', '0.1.0' );
define( 'AEGIS_FORMS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
require_once AEGIS_FORMS_PLUGIN_PATH . 'includes/class-aegis-forms-admin.php';

function aegis_forms_bootstrap() {
	Aegis_Forms_Admin::register();
}

add_action( 'plugins_loaded', 'aegis_forms_bootstrap' );
