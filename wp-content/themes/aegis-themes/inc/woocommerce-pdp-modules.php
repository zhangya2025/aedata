<?php
/**
 * PDP module toggles (phase 1: hardcoded list).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return an array of module ids to disable on PDP pages.
 *
 * @return array
 */
function aegis_wc_pdp_disabled_modules() {
    // Phase 1: hardcoded list to validate module hiding. Adjust as needed.
    return array( 'qa' );
}

/**
 * Output the disabled modules list to the front end for JS consumption.
 */
function aegis_wc_pdp_output_disabled_modules() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }

    $disabled = aegis_wc_pdp_disabled_modules();
    if ( ! is_array( $disabled ) ) {
        $disabled = array();
    }

    $json = wp_json_encode( array_values( $disabled ) );
    if ( ! $json ) {
        $json = '[]';
    }

    echo '<script>window.AEGIS_WC_PDP_DISABLED = ' . $json . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'aegis_wc_pdp_output_disabled_modules', 8 );
