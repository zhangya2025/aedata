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
// Deprecated in favor of front-end JS injection to avoid render_block dependency.
