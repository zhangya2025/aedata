<?php
/**
 * Plugin Name: Aegis Role & Capability Manager
 * Description: Super-admin-only role and capability manager with read-only protection for any Super Admin.
 * Version: 1.0.0
 * Author: Aegis
 * Network: true
 */

define( 'AEGIS_RCM_VERSION', '1.0.0' );

define( 'AEGIS_RCM_DIR', plugin_dir_path( __FILE__ ) );

define( 'AEGIS_RCM_URL', plugin_dir_url( __FILE__ ) );

require_once AEGIS_RCM_DIR . 'includes/class-aegis-rcm-guards.php';
require_once AEGIS_RCM_DIR . 'includes/class-aegis-rcm-cap-catalog.php';
require_once AEGIS_RCM_DIR . 'includes/class-aegis-rcm-admin.php';

add_action( 'admin_post_aegis_rcm_save', array( 'Aegis_RCM_Admin', 'handle_save' ) );
add_action( 'wp_ajax_aegis_rcm_get_user_detail', array( 'Aegis_RCM_Admin', 'ajax_get_user_detail' ) );
add_action( 'wp_ajax_aegis_rcm_get_cap_catalog', array( 'Aegis_RCM_Admin', 'ajax_get_cap_catalog' ) );

function aegis_rcm_register_menu() {
	if ( is_multisite() ) {
		add_action( 'network_admin_menu', array( 'Aegis_RCM_Admin', 'register_menu' ) );
	} else {
		add_action( 'admin_menu', array( 'Aegis_RCM_Admin', 'register_menu' ) );
	}
}
add_action( 'plugins_loaded', 'aegis_rcm_register_menu' );

function aegis_rcm_enqueue_assets( $hook_suffix ) {
	Aegis_RCM_Admin::enqueue_assets( $hook_suffix );
}
add_action( 'admin_enqueue_scripts', 'aegis_rcm_enqueue_assets' );
