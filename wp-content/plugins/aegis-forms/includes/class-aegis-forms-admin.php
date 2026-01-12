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
		$filters = self::parse_filters();
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
			<?php self::render_submissions_section( $filters ); ?>
		</div>
		<?php
	}

	private static function render_submissions_section( $filters ) {
		?>
		<hr />
		<h2><?php echo esc_html__( 'Submissions' ); ?></h2>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
			<label for="aegis-forms-filter-type"><?php echo esc_html__( 'Type' ); ?></label>
			<select id="aegis-forms-filter-type" name="type">
				<option value=""><?php echo esc_html__( 'All' ); ?></option>
				<option value="repair" <?php selected( $filters['type'], 'repair' ); ?>><?php echo esc_html__( 'repair' ); ?></option>
				<option value="dealer" <?php selected( $filters['type'], 'dealer' ); ?>><?php echo esc_html__( 'dealer' ); ?></option>
			</select>
			<label for="aegis-forms-filter-status"><?php echo esc_html__( 'Status' ); ?></label>
			<select id="aegis-forms-filter-status" name="status">
				<option value=""><?php echo esc_html__( 'All' ); ?></option>
				<option value="new" <?php selected( $filters['status'], 'new' ); ?>><?php echo esc_html__( 'new' ); ?></option>
				<option value="in_review" <?php selected( $filters['status'], 'in_review' ); ?>><?php echo esc_html__( 'in_review' ); ?></option>
				<option value="need_more_info" <?php selected( $filters['status'], 'need_more_info' ); ?>><?php echo esc_html__( 'need_more_info' ); ?></option>
				<option value="approved" <?php selected( $filters['status'], 'approved' ); ?>><?php echo esc_html__( 'approved' ); ?></option>
				<option value="rejected" <?php selected( $filters['status'], 'rejected' ); ?>><?php echo esc_html__( 'rejected' ); ?></option>
				<option value="closed" <?php selected( $filters['status'], 'closed' ); ?>><?php echo esc_html__( 'closed' ); ?></option>
			</select>
			<button class="button"><?php echo esc_html__( 'Filter' ); ?></button>
		</form>
		<?php

		if ( ! Aegis_Forms_Schema::table_exists() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'DB table not ready. Please review the health check section above.' ) . '</p></div>';
			return;
		}

		$result = self::query_submissions( $filters );
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Ticket' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Type' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Status' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Name' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Email' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Created At' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $result['items'] ) ) : ?>
					<tr>
						<td colspan="6"><?php echo esc_html__( 'No submissions found.' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $result['items'] as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['ticket_no'] ? $item['ticket_no'] : '(pending)' ); ?></td>
							<td><?php echo esc_html( $item['type'] ); ?></td>
							<td><?php echo esc_html( $item['status'] ); ?></td>
							<td><?php echo esc_html( $item['name'] ); ?></td>
							<td><?php echo esc_html( $item['email'] ); ?></td>
							<td><?php echo esc_html( $item['created_at_formatted'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		if ( $result['total'] > $result['per_page'] ) {
			$base_url = add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'type' => $filters['type'],
					'status' => $filters['status'],
					'paged' => '%#%',
				),
				admin_url( 'admin.php' )
			);
			$links = paginate_links(
				array(
					'base' => $base_url,
					'format' => '',
					'current' => $result['page'],
					'total' => max( 1, (int) ceil( $result['total'] / $result['per_page'] ) ),
					'type' => 'array',
				)
			);

			if ( ! empty( $links ) ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post( implode( ' ', $links ) );
				echo '</div></div>';
			}
		}
	}

	private static function parse_filters() {
		$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		$allowed_types = array( 'repair', 'dealer' );
		$allowed_statuses = array( 'new', 'in_review', 'need_more_info', 'approved', 'rejected', 'closed' );

		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type = '';
		}

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = '';
		}

		if ( $paged < 1 ) {
			$paged = 1;
		}

		return array(
			'type' => $type,
			'status' => $status,
			'paged' => $paged,
		);
	}

	private static function query_submissions( $filters ) {
		global $wpdb;

		$table_name = Aegis_Forms_Schema::table_name();
		$where_clauses = array();
		$where_values = array();

		if ( $filters['type'] ) {
			$where_clauses[] = 'type = %s';
			$where_values[] = $filters['type'];
		}

		if ( $filters['status'] ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $filters['status'];
		}

		$where_sql = '';
		if ( $where_clauses ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		$count_sql = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
		$count_query = $where_values ? $wpdb->prepare( $count_sql, $where_values ) : $count_sql;
		$total = (int) $wpdb->get_var( $count_query );

		$per_page = 50;
		$page = (int) $filters['paged'];
		$offset = ( $page - 1 ) * $per_page;

		$list_sql = "SELECT ticket_no, type, status, name, email, created_at FROM {$table_name} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$list_values = array_merge( $where_values, array( $per_page, $offset ) );
		$list_query = $wpdb->prepare( $list_sql, $list_values );
		$rows = $wpdb->get_results( $list_query, ARRAY_A );

		$items = array();
		foreach ( $rows as $row ) {
			$created_at = isset( $row['created_at'] ) ? $row['created_at'] : '';
			$items[] = array(
				'ticket_no' => $row['ticket_no'],
				'type' => $row['type'],
				'status' => $row['status'],
				'name' => $row['name'],
				'email' => $row['email'],
				'created_at_formatted' => $created_at ? wp_date( 'Y-m-d H:i', strtotime( $created_at ) ) : '',
			);
		}

		return array(
			'items' => $items,
			'total' => $total,
			'per_page' => $per_page,
			'page' => $page,
		);
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
