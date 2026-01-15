<?php
/**
 * Shop-only product archive template.
 *
 * @package WooCommerce\Templates
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_main_content' );

echo "\n<!-- AEGIS SHOP LOOP DISABLED (template redirect) -->\n";

do_action( 'woocommerce_archive_description' );

do_action( 'woocommerce_after_main_content' );
return;
