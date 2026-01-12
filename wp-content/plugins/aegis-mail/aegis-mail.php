<?php
/**
 * Plugin Name: Aegis Mail
 * Plugin URI: https://example.com/aegis-mail
 * Description: Minimal SMTP channel plugin for routing WordPress mail through a single SMTP configuration.
 * Version: 1.0.0
 * Author: Aegis Mail
 * Text Domain: aegis-mail
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AEGIS_MAIL_VERSION' ) ) {
	define( 'AEGIS_MAIL_VERSION', '1.0.0' );
}

if ( ! defined( 'AEGIS_MAIL_PATH' ) ) {
	define( 'AEGIS_MAIL_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'AEGIS_MAIL_URL' ) ) {
	define( 'AEGIS_MAIL_URL', plugin_dir_url( __FILE__ ) );
}

require_once AEGIS_MAIL_PATH . 'includes/class-aegis-mail.php';

add_action(
	'plugins_loaded',
	static function () {
		$aegis_mail = new Aegis_Mail();
		$aegis_mail->register();

		if ( is_admin() ) {
			require_once AEGIS_MAIL_PATH . 'includes/class-aegis-mail-admin.php';
			$admin = new Aegis_Mail_Admin( $aegis_mail );
			$admin->register();
		}
	}
);
