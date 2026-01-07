<?php

class Aegis_RCM_Guards {
	public static function must_be_super_admin() {
		if ( is_multisite() ) {
			if ( ! is_super_admin() ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'aegis-rcm' ), 403 );
			}
			return;
		}

		if ( ! ( current_user_can( 'manage_options' ) && current_user_can( 'edit_users' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aegis-rcm' ), 403 );
		}
	}

	public static function is_target_protected( $target_user_id ) {
		$target_user_id = (int) $target_user_id;
		if ( ! $target_user_id ) {
			return true;
		}
		if ( $target_user_id === (int) get_current_user_id() ) {
			return true;
		}
		if ( is_multisite() && is_super_admin( $target_user_id ) ) {
			return true;
		}

		return false;
	}
}
