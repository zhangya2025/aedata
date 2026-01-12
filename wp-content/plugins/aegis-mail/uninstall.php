<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$option_key = 'aegis_mail_settings';
$transient_key = 'aegis_mail_last_test';

if ( is_multisite() ) {
	delete_site_option( $option_key );
	delete_site_transient( $transient_key );
} else {
	delete_option( $option_key );
	delete_transient( $transient_key );
}
