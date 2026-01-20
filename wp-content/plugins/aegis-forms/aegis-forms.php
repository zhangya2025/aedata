<?php
/**
 * Plugin Name: Aegis Forms
 * Description: Admin shell and health check page for Aegis Forms.
 * Version: 0.1.0
 * Text Domain: aegis-forms
 * Author: Aegis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AEGIS_FORMS_VERSION', '0.1.0' );
define( 'AEGIS_FORMS_PLUGIN_FILE', __FILE__ );
define( 'AEGIS_FORMS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AEGIS_FORMS_DB_VERSION', '1' );
define( 'AEGIS_FORMS_DB_VERSION_OPTION', 'aegis_forms_db_version' );
define( 'AEGIS_FORMS_INSTALL_ERROR_OPTION', 'aegis_forms_install_error' );
define( 'AEGIS_FORMS_CAP_VIEW', 'aegis_forms_view' );
define( 'AEGIS_FORMS_CAP_MANAGE_SETTINGS', 'aegis_forms_manage_settings' );
define( 'AEGIS_FORMS_CAP_EDIT_SUBMISSION', 'aegis_forms_edit_submission' );
define( 'AEGIS_FORMS_CAP_DELETE_SUBMISSION', 'aegis_forms_delete_submission' );
define( 'AEGIS_FORMS_CAP_RESTORE_SUBMISSION', 'aegis_forms_restore_submission' );
define( 'AEGIS_FORMS_CAP_EXPORT', 'aegis_forms_export' );

require_once AEGIS_FORMS_PLUGIN_PATH . 'includes/class-aegis-forms-schema.php';
require_once AEGIS_FORMS_PLUGIN_PATH . 'includes/class-aegis-forms-settings.php';
require_once AEGIS_FORMS_PLUGIN_PATH . 'includes/class-aegis-forms-admin.php';
require_once AEGIS_FORMS_PLUGIN_PATH . 'includes/class-aegis-forms-frontend.php';

function aegis_forms_bootstrap() {
	Aegis_Forms_Admin::register();
	Aegis_Forms_Frontend::register();
}

add_action( 'plugins_loaded', 'aegis_forms_bootstrap' );

register_activation_hook( __FILE__, array( 'Aegis_Forms_Schema', 'install_schema' ) );
register_activation_hook( __FILE__, 'aegis_forms_add_caps' );

function aegis_forms_get_capabilities() {
	return array(
		AEGIS_FORMS_CAP_VIEW,
		AEGIS_FORMS_CAP_MANAGE_SETTINGS,
		AEGIS_FORMS_CAP_EDIT_SUBMISSION,
		AEGIS_FORMS_CAP_DELETE_SUBMISSION,
		AEGIS_FORMS_CAP_RESTORE_SUBMISSION,
		AEGIS_FORMS_CAP_EXPORT,
	);
}

function aegis_forms_add_caps() {
	$role = get_role( 'administrator' );
	if ( ! $role ) {
		return;
	}

	foreach ( aegis_forms_get_capabilities() as $cap ) {
		$role->add_cap( $cap );
	}
}

add_filter(
	'user_has_cap',
	function( $allcaps ) {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			foreach ( aegis_forms_get_capabilities() as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}

		return $allcaps;
	}
);

add_action(
	'admin_init',
	function() {
		if ( current_user_can( AEGIS_FORMS_CAP_MANAGE_SETTINGS ) || current_user_can( 'manage_options' ) ) {
			Aegis_Forms_Schema::maybe_install_schema();
		}
	},
	9
);
