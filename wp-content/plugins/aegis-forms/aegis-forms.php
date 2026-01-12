<?php
/**
 * Plugin Name: Aegis Forms
 * Description: Collects repair and dealer application submissions with admin review.
 * Version: 1.0.0
 * Author: Aegis
 * Text Domain: aegis-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AEGIS_FORMS_VERSION', '1.0.0' );
define( 'AEGIS_FORMS_PLUGIN_FILE', __FILE__ );
define( 'AEGIS_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AEGIS_FORMS_MAX_FILE_SIZE', 10 * 1024 * 1024 );

define( 'AEGIS_FORMS_UPLOAD_SUBDIR', 'aegis-forms' );

require_once AEGIS_FORMS_PLUGIN_DIR . 'includes/class-aegis-forms.php';
require_once AEGIS_FORMS_PLUGIN_DIR . 'includes/class-aegis-forms-frontend.php';
require_once AEGIS_FORMS_PLUGIN_DIR . 'includes/class-aegis-forms-admin.php';

register_activation_hook( __FILE__, array( 'Aegis_Forms', 'activate' ) );

Aegis_Forms::init();
Aegis_Forms_Frontend::init();
Aegis_Forms_Admin::init();
