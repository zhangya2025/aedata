<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aegis_Forms_Admin {
	const MENU_SLUG = 'aegis-forms';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_menu_page(
			'Aegis Forms',
			'Aegis Forms',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-feedback',
			58
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$checks = self::run_checks();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Aegis Forms' ); ?></h1>
			<?php if ( '' !== $checks['db_install_error'] ) : ?>
				<div class="notice notice-error">
					<p><?php echo esc_html( $checks['db_install_error'] ); ?></p>
				</div>
			<?php endif; ?>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row">Manage options capability</th>
						<td><?php echo esc_html( $checks['can_manage_options'] ? 'true' : 'false' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Uploads base directory</th>
						<td><?php echo esc_html( $checks['uploads_basedir'] ); ?></td>
					</tr>
					<tr>
						<th scope="row">Uploads base URL</th>
						<td><?php echo esc_html( $checks['uploads_baseurl'] ); ?></td>
					</tr>
					<tr>
						<th scope="row">Uploads base directory exists</th>
						<td><?php echo esc_html( $checks['uploads_basedir_exists'] ? 'true' : 'false' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Uploads base directory writable</th>
						<td><?php echo esc_html( $checks['uploads_basedir_writable'] ? 'true' : 'false' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Aegis Forms upload directory</th>
						<td><?php echo esc_html( $checks['aegis_upload_dir'] ); ?></td>
					</tr>
					<tr>
						<th scope="row">Aegis Forms directory exists</th>
						<td><?php echo esc_html( $checks['aegis_upload_dir_exists'] ? 'true' : 'false' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Aegis Forms directory writable</th>
						<td><?php echo esc_html( $checks['aegis_upload_dir_writable'] ? 'true' : 'false' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Aegis Forms directory created</th>
						<td><?php echo esc_html( $checks['aegis_upload_dir_created'] ); ?></td>
					</tr>
					<?php if ( '' !== $checks['aegis_upload_dir_error'] ) : ?>
						<tr>
							<th scope="row">Aegis Forms directory creation error</th>
							<td><?php echo esc_html( $checks['aegis_upload_dir_error'] ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row">wp_mail available</th>
						<td><?php echo esc_html( $checks['has_wp_mail'] ? 'true' : 'false' ); ?></td>
					</tr>
					<tr>
						<th scope="row">DB table name</th>
						<td><?php echo esc_html( $checks['db_table_name'] ); ?></td>
					</tr>
					<tr>
						<th scope="row">DB table exists</th>
						<td><?php echo esc_html( $checks['db_table_exists'] ? 'true' : 'false' ); ?></td>
					</tr>
					<tr>
						<th scope="row">DB version installed</th>
						<td><?php echo esc_html( $checks['db_version_installed'] ); ?></td>
					</tr>
					<tr>
						<th scope="row">DB version expected</th>
						<td><?php echo esc_html( $checks['db_version_expected'] ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function run_checks() {
		$can_manage_options = current_user_can( 'manage_options' );
		$uploads = wp_upload_dir();
		$uploads_basedir = isset( $uploads['basedir'] ) ? $uploads['basedir'] : '';
		$uploads_baseurl = isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';
		$uploads_basedir_exists = $uploads_basedir && is_dir( $uploads_basedir );
		$uploads_basedir_writable = $uploads_basedir && is_writable( $uploads_basedir );

		$aegis_upload_dir = $uploads_basedir ? trailingslashit( $uploads_basedir ) . 'aegis-forms' : '';
		$aegis_upload_dir_exists = $aegis_upload_dir && is_dir( $aegis_upload_dir );
		$aegis_upload_dir_writable = $aegis_upload_dir && is_writable( $aegis_upload_dir );
		$aegis_upload_dir_created = 'n/a';
		$aegis_upload_dir_error = '';
		$db_table_name = Aegis_Forms_Schema::table_name();
		$db_table_exists = Aegis_Forms_Schema::table_exists();
		$db_version_installed = Aegis_Forms_Schema::get_installed_version();
		$db_version_expected = AEGIS_FORMS_DB_VERSION;
		$db_install_error = get_option( AEGIS_FORMS_INSTALL_ERROR_OPTION, '' );

		if ( $aegis_upload_dir && ! $aegis_upload_dir_exists ) {
			if ( wp_mkdir_p( $aegis_upload_dir ) ) {
				$aegis_upload_dir_created = 'success';
				$aegis_upload_dir_exists = is_dir( $aegis_upload_dir );
				$aegis_upload_dir_writable = is_writable( $aegis_upload_dir );
			} else {
				$aegis_upload_dir_created = 'fail';
				$last_error = error_get_last();
				if ( $last_error && isset( $last_error['message'] ) ) {
					$aegis_upload_dir_error = $last_error['message'];
				} else {
					$aegis_upload_dir_error = 'Unknown error while creating directory.';
				}
			}
		}

		return array(
			'can_manage_options' => $can_manage_options,
			'uploads_basedir' => $uploads_basedir,
			'uploads_baseurl' => $uploads_baseurl,
			'uploads_basedir_exists' => $uploads_basedir_exists,
			'uploads_basedir_writable' => $uploads_basedir_writable,
			'aegis_upload_dir' => $aegis_upload_dir,
			'aegis_upload_dir_exists' => $aegis_upload_dir_exists,
			'aegis_upload_dir_writable' => $aegis_upload_dir_writable,
			'aegis_upload_dir_created' => $aegis_upload_dir_created,
			'aegis_upload_dir_error' => $aegis_upload_dir_error,
			'has_wp_mail' => function_exists( 'wp_mail' ),
			'db_table_name' => $db_table_name,
			'db_table_exists' => $db_table_exists,
			'db_version_installed' => $db_version_installed,
			'db_version_expected' => $db_version_expected,
			'db_install_error' => $db_install_error,
		);
	}
}
