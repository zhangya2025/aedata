<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'aegis_forms_submissions';

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'aegis_forms_version'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aegis_forms_rate_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aegis_forms_rate_%'" );

if ( defined( 'AEGIS_FORMS_UNINSTALL_PURGE' ) && AEGIS_FORMS_UNINSTALL_PURGE ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload_dir = wp_upload_dir();
    $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'aegis-forms';

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( WP_Filesystem() ) {
        global $wp_filesystem;
        if ( $wp_filesystem && $wp_filesystem->exists( $target_dir ) ) {
            $wp_filesystem->delete( $target_dir, true );
        }
    }
}
