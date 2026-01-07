<?php
/**
 * Plugin Name: Aegis WooCommerce Pages
 * Description: Creates required WooCommerce pages on activation.
 * Version: 1.0.0
 * Author: Aegis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AEGIS_WC_PAGES_NOTICE_OPTION = 'aegis_wc_pages_notice';

/**
 * Create WooCommerce pages on plugin activation.
 */
function aegis_wc_pages_activate() {
	$pages = array(
		array(
			'slug'    => 'cart',
			'title'   => 'Cart',
			'content' => '[woocommerce_cart]',
		),
		array(
			'slug'    => 'checkout',
			'title'   => 'Checkout',
			'content' => '[woocommerce_checkout]',
		),
		array(
			'slug'    => 'my-account',
			'title'   => 'My Account',
			'content' => '[woocommerce_my_account]',
		),
		array(
			'slug'    => 'shop',
			'title'   => 'Shop',
			'content' => '',
		),
		array(
			'slug'    => 'terms',
			'title'   => 'Terms',
			'content' => '',
		),
	);

	$notice_items = array();

	foreach ( $pages as $page_data ) {
		$slug    = $page_data['slug'];
		$title   = $page_data['title'];
		$content = $page_data['content'];

		$page = get_page_by_path( $slug );

		if ( $page ) {
			if ( 'trash' === $page->post_status ) {
				wp_untrash_post( $page->ID );
				wp_update_post(
					array(
						'ID'          => $page->ID,
						'post_status' => 'publish',
					)
				);
				$notice_items[] = sprintf( '%s (restored)', $slug );
			}

			if ( '' === trim( $page->post_content ) && '' !== $content ) {
				wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => $content,
					)
				);
			}

			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( ! is_wp_error( $post_id ) ) {
			$notice_items[] = sprintf( '%s (created)', $slug );
		}
	}

	if ( ! empty( $notice_items ) ) {
		update_option( AEGIS_WC_PAGES_NOTICE_OPTION, $notice_items, false );
	}
}

register_activation_hook( __FILE__, 'aegis_wc_pages_activate' );

/**
 * Show an admin notice after activation.
 */
function aegis_wc_pages_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice_items = get_option( AEGIS_WC_PAGES_NOTICE_OPTION );

	if ( empty( $notice_items ) || ! is_array( $notice_items ) ) {
		return;
	}

	delete_option( AEGIS_WC_PAGES_NOTICE_OPTION );

	$message = sprintf(
		'WooCommerce pages processed: %s.',
		implode( ', ', array_map( 'esc_html', $notice_items ) )
	);

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( $message )
	);
}
add_action( 'admin_notices', 'aegis_wc_pages_admin_notice' );
