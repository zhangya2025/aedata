<?php

class Aegis_RCM_Cap_Catalog {
	public static function get_catalog( $user = null, $search = '' ) {
		$roles = wp_roles();
		$capabilities = array();
		$role_keys = array();

		if ( $roles instanceof WP_Roles ) {
			$role_keys = array_keys( $roles->roles );
			foreach ( $roles->roles as $role ) {
				if ( empty( $role['capabilities'] ) ) {
					continue;
				}
				$capabilities = array_merge( $capabilities, array_keys( $role['capabilities'] ) );
			}
		}

		$core_caps = array(
			'activate_plugins',
			'add_users',
			'create_users',
			'delete_users',
			'edit_posts',
			'edit_pages',
			'edit_users',
			'install_plugins',
			'list_users',
			'manage_options',
			'manage_woocommerce',
			'moderate_comments',
			'promote_users',
			'publish_posts',
			'publish_pages',
			'remove_users',
			'upload_files',
			'update_plugins',
			'edit_theme_options',
		);

		$capabilities = array_merge( $capabilities, $core_caps );

		if ( $user instanceof WP_User ) {
			foreach ( $user->caps as $cap_key => $value ) {
				if ( in_array( $cap_key, $role_keys, true ) ) {
					continue;
				}
				$capabilities[] = $cap_key;
			}
		}

		$capabilities = array_unique( array_filter( $capabilities ) );
		sort( $capabilities, SORT_NATURAL | SORT_FLAG_CASE );

		if ( '' !== $search ) {
			$search = strtolower( $search );
			$capabilities = array_values(
				array_filter(
					$capabilities,
					static function ( $cap ) use ( $search ) {
						return false !== strpos( strtolower( $cap ), $search );
					}
				)
			);
		}

		return $capabilities;
	}
}
