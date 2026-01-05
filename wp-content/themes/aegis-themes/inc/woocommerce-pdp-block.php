<?php
/**
 * PDP block helpers for injecting module markers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Inject data-aegis-module attribute into PDP module wrappers within blocks.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Block metadata.
 *
 * @return string
 */
function aegis_wc_pdp_inject_block_module_attributes( $block_content, $block ) {
    if ( is_admin() || empty( $block_content ) ) {
        return $block_content;
    }

    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return $block_content;
    }

    $modules = array( 'gallery', 'buybox', 'trust', 'highlights', 'details', 'reviews', 'qa', 'recommendations', 'sticky_bar' );

    foreach ( $modules as $module ) {
        if ( false === strpos( $block_content, 'aegis-wc-module--' . $module ) ) {
            continue;
        }

        if ( false !== strpos( $block_content, 'data-aegis-module=' ) ) {
            continue;
        }

        $pattern = "/(<[a-zA-Z0-9][^>]*class=(\"|\')[^\"']*aegis-wc-module--" . preg_quote( $module, "/" ) . "[^\"']*\2)([^>]*>)/";

        if ( preg_match( $pattern, $block_content ) ) {
            $replacement   = '$1 data-aegis-module="' . $module . '"$3';
            $block_content = preg_replace( $pattern, $replacement, 1 );
        }
    }

    return $block_content;
}
add_filter( 'render_block', 'aegis_wc_pdp_inject_block_module_attributes', 20, 2 );
