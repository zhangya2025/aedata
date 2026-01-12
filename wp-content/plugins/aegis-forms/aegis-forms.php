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
define( 'AEGIS_FORMS_DB_VERSION', '1' );
define( 'AEGIS_FORMS_DB_VERSION_OPTION', 'aegis_forms_db_version' );
define( 'AEGIS_FORMS_INSTALL_ERROR_OPTION', 'aegis_forms_install_error' );

require_once AEGIS_FORMS_PLUGIN_PATH . 'includes/class-aegis-forms-schema.php';
require_once AEGIS_FORMS_PLUGIN_PATH . 'includes/class-aegis-forms-admin.php';
require_once AEGIS_FORMS_PLUGIN_PATH . 'includes/class-aegis-forms-frontend.php';

function aegis_forms_bootstrap() {
	Aegis_Forms_Admin::register();
	Aegis_Forms_Frontend::register();
}

add_action( 'plugins_loaded', 'aegis_forms_bootstrap' );

register_activation_hook( __FILE__, array( 'Aegis_Forms_Schema', 'install_schema' ) );

add_action(
	'admin_init',
	function() {
		if ( current_user_can( 'manage_options' ) ) {
			Aegis_Forms_Schema::maybe_install_schema();
		}
	},
	9
);
