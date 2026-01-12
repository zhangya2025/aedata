<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aegis_Forms_Schema {
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'aegis_forms_submissions';
	}

	public static function table_exists() {
		global $wpdb;

		$table_name = self::table_name();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return $found === $table_name;
	}

	public static function get_installed_version() {
		return get_option( AEGIS_FORMS_DB_VERSION_OPTION, '' );
	}

	public static function needs_install() {
		return ! self::table_exists() || self::get_installed_version() !== AEGIS_FORMS_DB_VERSION;
	}

	public static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(32) NOT NULL,
			ticket_no VARCHAR(32) NOT NULL DEFAULT '',
			status VARCHAR(32) NOT NULL DEFAULT 'new',
			name VARCHAR(191) NOT NULL,
			email VARCHAR(191) NOT NULL,
			phone VARCHAR(64) NULL,
			country VARCHAR(64) NULL,
			subject VARCHAR(191) NULL,
			message LONGTEXT NULL,
			meta LONGTEXT NULL,
			attachments LONGTEXT NULL,
			admin_notes LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			ip VARCHAR(64) NULL,
			user_agent TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ticket_no (ticket_no),
			KEY type (type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( AEGIS_FORMS_DB_VERSION_OPTION, AEGIS_FORMS_DB_VERSION );

		if ( self::table_exists() ) {
			delete_option( AEGIS_FORMS_INSTALL_ERROR_OPTION );
			return;
		}

		$error = $wpdb->last_error;
		if ( ! $error ) {
			$error = 'Unknown error while creating table.';
		}

		update_option( AEGIS_FORMS_INSTALL_ERROR_OPTION, $error );
	}

	public static function maybe_install_schema() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::needs_install() ) {
			self::install_schema();
		}
	}
}
