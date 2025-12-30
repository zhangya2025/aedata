<?php
/**
 * Private login bootstrap for aegis-safe plugin.
 * Simplified to load only the core login flow to avoid double-loading frontend stacks.
 */

define( 'WP_USE_THEMES', false );

require __DIR__ . '/wp-load.php';
require ABSPATH . 'wp-login.php';
