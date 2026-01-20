<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aegis_Forms_Admin {
	const MENU_SLUG = 'aegis-forms';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_view_page' ) );
		add_action( 'admin_post_aegis_forms_update', array( __CLASS__, 'handle_update_submission' ) );
		add_action( 'admin_post_aegis_forms_settings', array( __CLASS__, 'handle_settings_save' ) );
		add_action( 'admin_post_aegis_forms_delete_submission', array( __CLASS__, 'handle_delete_submission' ) );
		add_action( 'admin_post_aegis_forms_restore_submission', array( __CLASS__, 'handle_restore_submission' ) );
		add_action( 'admin_post_aegis_forms_export_csv', array( __CLASS__, 'handle_export_csv' ) );
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

	public static function register_view_page() {
		add_submenu_page(
			null,
			'Aegis Forms - View',
			'Aegis Forms - View',
			'manage_options',
			'aegis-forms-view',
			array( __CLASS__, 'render_view_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$db_install_error = get_option( AEGIS_FORMS_INSTALL_ERROR_OPTION, '' );
		$filters = self::parse_filters_from_request( $_GET );
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'submissions';
		if ( ! in_array( $tab, array( 'submissions', 'settings' ), true ) ) {
			$tab = 'submissions';
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Aegis Forms' ); ?></h1>
			<?php if ( '' !== $db_install_error ) : ?>
				<div class="notice notice-error">
					<p><?php echo esc_html( $db_install_error ); ?></p>
				</div>
			<?php endif; ?>
			<?php
			self::render_tab_navigation( $tab );

			if ( 'settings' === $tab ) {
				self::render_settings_section();
			} else {
				self::render_submissions_section( $filters );
			}
			?>
		</div>
		<?php
	}

	private static function render_tab_navigation( $active_tab ) {
		$base_url = add_query_arg( 'page', self::MENU_SLUG, admin_url( 'admin.php' ) );
		$tabs = array(
			'submissions' => __( 'Submissions' ),
			'settings' => __( 'Settings' ),
		);
		?>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $tab => $label ) : ?>
				<?php
				$url = add_query_arg( 'tab', $tab, $base_url );
				$classes = $active_tab === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>
		<?php
	}

	private static function render_submissions_section( $filters ) {
		if ( isset( $_GET['aegis_forms_notice'] ) ) {
			$notice = sanitize_key( wp_unslash( $_GET['aegis_forms_notice'] ) );
			if ( 'deleted' === $notice ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Submission deleted.' ) . '</p></div>';
			} elseif ( 'restored' === $notice ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Submission restored.' ) . '</p></div>';
			}
		}
		?>
		<hr />
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
			<input type="hidden" name="tab" value="submissions" />
			<label for="aegis-forms-filter-type"><?php echo esc_html__( 'Type' ); ?></label>
			<select id="aegis-forms-filter-type" name="type">
				<option value=""><?php echo esc_html__( 'All' ); ?></option>
				<option value="repair" <?php selected( $filters['type'], 'repair' ); ?>><?php echo esc_html__( 'repair' ); ?></option>
				<option value="dealer" <?php selected( $filters['type'], 'dealer' ); ?>><?php echo esc_html__( 'dealer' ); ?></option>
				<option value="contact" <?php selected( $filters['type'], 'contact' ); ?>><?php echo esc_html__( 'Contact' ); ?></option>
				<option value="sponsorship" <?php selected( $filters['type'], 'sponsorship' ); ?>><?php echo esc_html__( 'Sponsorship' ); ?></option>
				<option value="customization" <?php selected( $filters['type'], 'customization' ); ?>><?php echo esc_html__( 'Customization' ); ?></option>
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
				<option value="deleted" <?php selected( $filters['status'], 'deleted' ); ?>><?php echo esc_html__( 'Trash' ); ?></option>
			</select>
			<button class="button"><?php echo esc_html__( 'Filter' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
			<?php wp_nonce_field( 'aegis_forms_export_csv' ); ?>
			<input type="hidden" name="action" value="aegis_forms_export_csv" />
			<input type="hidden" name="type" value="<?php echo esc_attr( $filters['type'] ); ?>" />
			<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
			<button class="button"><?php echo esc_html__( 'Export CSV' ); ?></button>
		</form>
		<?php

		if ( ! Aegis_Forms_Schema::table_exists() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'DB table not ready. Please complete the plugin setup and try again.' ) . '</p></div>';
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
					<th scope="col"><?php echo esc_html__( 'Actions' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $result['items'] ) ) : ?>
					<tr>
						<td colspan="7"><?php echo esc_html__( 'No submissions found.' ); ?></td>
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
							<td>
								<?php if ( $item['ticket_no'] ) : ?>
									<?php
									$view_url = add_query_arg(
										array(
											'page' => 'aegis-forms-view',
											'ticket' => rawurlencode( $item['ticket_no'] ),
										),
										admin_url( 'admin.php' )
									);
									$action_base = admin_url( 'admin-post.php' );
									$redirect_url = self::get_list_redirect_url( $filters );
									?>
									<a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html__( 'View' ); ?></a>
									<?php if ( 'deleted' !== $item['status'] ) : ?>
										<?php
										$delete_url = add_query_arg(
											array(
												'action' => 'aegis_forms_delete_submission',
												'ticket' => rawurlencode( $item['ticket_no'] ),
												'redirect_to' => rawurlencode( $redirect_url ),
											),
											$action_base
										);
										$delete_url = wp_nonce_url( $delete_url, 'aegis_forms_delete_' . $item['ticket_no'] );
										?>
										<span class="separator"> | </span>
										<a href="<?php echo esc_url( $delete_url ); ?>"><?php echo esc_html__( 'Delete' ); ?></a>
									<?php else : ?>
										<?php
										$restore_url = add_query_arg(
											array(
												'action' => 'aegis_forms_restore_submission',
												'ticket' => rawurlencode( $item['ticket_no'] ),
												'redirect_to' => rawurlencode( $redirect_url ),
											),
											$action_base
										);
										$restore_url = wp_nonce_url( $restore_url, 'aegis_forms_restore_' . $item['ticket_no'] );
										?>
										<span class="separator"> | </span>
										<a href="<?php echo esc_url( $restore_url ); ?>"><?php echo esc_html__( 'Restore' ); ?></a>
									<?php endif; ?>
								<?php else : ?>
									<span style="color:#999;"><?php echo esc_html__( 'View' ); ?></span>
								<?php endif; ?>
							</td>
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

	private static function parse_filters_from_request( $request ) {
		$type = isset( $request['type'] ) ? sanitize_text_field( wp_unslash( $request['type'] ) ) : '';
		$status = isset( $request['status'] ) ? sanitize_text_field( wp_unslash( $request['status'] ) ) : '';
		$paged = isset( $request['paged'] ) ? absint( $request['paged'] ) : 1;

		$allowed_types = array( 'repair', 'dealer', 'contact', 'sponsorship', 'customization' );
		$allowed_statuses = array( 'new', 'in_review', 'need_more_info', 'approved', 'rejected', 'closed', 'deleted' );

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

	private static function build_where_sql( $filters ) {
		$where_clauses = array();
		$where_values = array();

		if ( $filters['type'] ) {
			$where_clauses[] = 'type = %s';
			$where_values[] = $filters['type'];
		}

		if ( $filters['status'] ) {
			$where_clauses[] = 'status = %s';
			$where_values[] = $filters['status'];
		} else {
			$where_clauses[] = 'status <> %s';
			$where_values[] = 'deleted';
		}

		$where_sql = '';
		if ( $where_clauses ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		return array( $where_sql, $where_values );
	}

	private static function query_submissions( $filters ) {
		global $wpdb;

		$table_name = Aegis_Forms_Schema::table_name();
		list( $where_sql, $where_values ) = self::build_where_sql( $filters );

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

	public static function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ), 403 );
		}

		check_admin_referer( 'aegis_forms_export_csv' );

		if ( ! Aegis_Forms_Schema::table_exists() ) {
			wp_die( esc_html__( 'DB table not ready.' ), 500 );
		}

		$filters = self::parse_filters_from_request( $_POST );
		list( $where_sql, $where_values ) = self::build_where_sql( $filters );

		$type_label = $filters['type'] ? $filters['type'] : 'all';
		$status_label = $filters['status'] ? $filters['status'] : 'all';
		$timestamp = wp_date( 'Ymd-Hi', current_time( 'timestamp' ) );
		$filename = sprintf( 'aegis-forms-%s-%s-%s.csv', $type_label, $status_label, $timestamp );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo "\xEF\xBB\xBF";

		$fh = fopen( 'php://output', 'w' );
		$headers = array(
			'ticket_no',
			'type',
			'status',
			'name',
			'email',
			'phone',
			'country',
			'subject',
			'message',
			'meta',
			'attachments',
			'admin_notes',
			'created_at',
			'updated_at',
		);
		fputcsv( $fh, $headers );

		global $wpdb;
		$table_name = Aegis_Forms_Schema::table_name();
		$limit = 500;
		$offset = 0;

		do {
			$query = "SELECT " . implode( ',', $headers ) . " FROM {$table_name} {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
			$args = array_merge( $where_values, array( $limit, $offset ) );
			$sql = $wpdb->prepare( $query, $args );
			$rows = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( $rows as $row ) {
				$line = array();
				foreach ( $headers as $key ) {
					$value = isset( $row[ $key ] ) ? $row[ $key ] : '';
					$line[] = is_null( $value ) ? '' : (string) $value;
				}
				fputcsv( $fh, $line );
			}

			$offset += $limit;
		} while ( count( $rows ) === $limit );

		fclose( $fh );
		exit;
	}

	private static function get_list_redirect_url( $filters ) {
		return add_query_arg(
			array(
				'page' => self::MENU_SLUG,
				'tab' => 'submissions',
				'type' => $filters['type'],
				'status' => $filters['status'],
				'paged' => $filters['paged'],
			),
			admin_url( 'admin.php' )
		);
	}

	public static function render_view_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$ticket = isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';
		$ticket = substr( $ticket, 0, 64 );
		$return_url = add_query_arg( 'page', self::MENU_SLUG, admin_url( 'admin.php' ) );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Aegis Forms - View' ); ?></h1>
			<?php
			if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved.' ) . '</p></div>';
			}

			if ( ! Aegis_Forms_Schema::table_exists() ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'DB table not ready.' ) . '</p></div>';
				echo '<p><a href="' . esc_url( $return_url ) . '">' . esc_html__( 'Back to list' ) . '</a></p>';
				echo '</div>';
				return;
			}

			if ( '' === $ticket ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Ticket not provided.' ) . '</p></div>';
				echo '<p><a href="' . esc_url( $return_url ) . '">' . esc_html__( 'Back to list' ) . '</a></p>';
				echo '</div>';
				return;
			}

			global $wpdb;
			$table_name = Aegis_Forms_Schema::table_name();
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE ticket_no = %s LIMIT 1",
					$ticket
				),
				ARRAY_A
			);

			if ( ! $row ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Submission not found.' ) . '</p></div>';
				echo '<p><a href="' . esc_url( $return_url ) . '">' . esc_html__( 'Back to list' ) . '</a></p>';
				echo '</div>';
				return;
			}

			$allowed_statuses = array( 'new', 'in_review', 'need_more_info', 'approved', 'rejected', 'closed', 'deleted' );
			$meta_value = isset( $row['meta'] ) ? $row['meta'] : '';
			$attachments_value = isset( $row['attachments'] ) ? $row['attachments'] : '';
			$admin_notes_value = isset( $row['admin_notes'] ) ? $row['admin_notes'] : '';
			$created_at = isset( $row['created_at'] ) ? $row['created_at'] : '';
			$updated_at = isset( $row['updated_at'] ) ? $row['updated_at'] : '';
			$attachments_list = array();
			if ( $attachments_value ) {
				$decoded = json_decode( $attachments_value, true );
				if ( is_array( $decoded ) ) {
					$attachments_list = $decoded;
				}
			}
			$uploads = wp_upload_dir();
			?>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Ticket' ); ?></th>
						<td><?php echo esc_html( $row['ticket_no'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Type' ); ?></th>
						<td><?php echo esc_html( $row['type'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Status' ); ?></th>
						<td><?php echo esc_html( $row['status'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Name' ); ?></th>
						<td><?php echo esc_html( $row['name'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Email' ); ?></th>
						<td><?php echo esc_html( $row['email'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Created At' ); ?></th>
						<td><?php echo esc_html( $created_at ? wp_date( 'Y-m-d H:i', strtotime( $created_at ) ) : '' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Updated At' ); ?></th>
						<td><?php echo esc_html( $updated_at ? wp_date( 'Y-m-d H:i', strtotime( $updated_at ) ) : '' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Subject' ); ?></th>
						<td><?php echo esc_html( $row['subject'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Message' ); ?></th>
						<td><?php echo esc_html( $row['message'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Meta' ); ?></th>
						<td><pre><?php echo esc_html( $meta_value ); ?></pre></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Attachments' ); ?></th>
						<td>
							<?php if ( empty( $attachments_list ) ) : ?>
								<?php echo esc_html__( 'No attachments.' ); ?>
							<?php else : ?>
								<ul>
									<?php foreach ( $attachments_list as $attachment ) : ?>
										<?php
										$url = $uploads['baseurl'] . '/' . ltrim( $attachment, '/' );
										?>
										<li>
											<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( basename( $attachment ) ); ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Admin Notes' ); ?></th>
						<td><?php echo esc_html( $admin_notes_value ); ?></td>
					</tr>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Update Submission' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'aegis_forms_update_' . $row['ticket_no'] ); ?>
				<input type="hidden" name="action" value="aegis_forms_update" />
				<input type="hidden" name="ticket" value="<?php echo esc_attr( $row['ticket_no'] ); ?>" />
				<table class="form-table">
					<tr>
						<th scope="row"><label for="aegis-forms-status"><?php echo esc_html__( 'Status' ); ?></label></th>
						<td>
							<select id="aegis-forms-status" name="status">
								<?php foreach ( $allowed_statuses as $status ) : ?>
									<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $row['status'], $status ); ?>>
										<?php echo esc_html( $status ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aegis-forms-admin-notes"><?php echo esc_html__( 'Admin Notes' ); ?></label></th>
						<td>
							<textarea id="aegis-forms-admin-notes" name="admin_notes" rows="6" class="large-text"><?php echo esc_textarea( $admin_notes_value ); ?></textarea>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save' ) ); ?>
			</form>
			<?php if ( 'deleted' === $row['status'] ) : ?>
				<?php
				$restore_url = add_query_arg(
					array(
						'action' => 'aegis_forms_restore_submission',
						'ticket' => rawurlencode( $row['ticket_no'] ),
						'redirect_to' => rawurlencode(
							add_query_arg(
								array(
									'page' => 'aegis-forms-view',
									'ticket' => rawurlencode( $row['ticket_no'] ),
								),
								admin_url( 'admin.php' )
							)
						),
					),
					admin_url( 'admin-post.php' )
				);
				$restore_url = wp_nonce_url( $restore_url, 'aegis_forms_restore_' . $row['ticket_no'] );
				?>
				<p>
					<a class="button" href="<?php echo esc_url( $restore_url ); ?>"><?php echo esc_html__( 'Restore' ); ?></a>
				</p>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( $return_url ); ?>"><?php echo esc_html__( 'Back to list' ); ?></a></p>
		</div>
		<?php
	}

	public static function handle_update_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$ticket = isset( $_POST['ticket'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket'] ) ) : '';
		$ticket = substr( $ticket, 0, 64 );
		check_admin_referer( 'aegis_forms_update_' . $ticket );

		$allowed_statuses = array( 'new', 'in_review', 'need_more_info', 'approved', 'rejected', 'closed', 'deleted' );
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$admin_notes = isset( $_POST['admin_notes'] ) ? wp_unslash( $_POST['admin_notes'] ) : '';

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			wp_die( esc_html__( 'Invalid status.' ) );
		}

		global $wpdb;
		$table_name = Aegis_Forms_Schema::table_name();

		$wpdb->update(
			$table_name,
			array(
				'status' => $status,
				'admin_notes' => $admin_notes,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'ticket_no' => $ticket ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);

		$redirect_url = add_query_arg(
			array(
				'page' => 'aegis-forms-view',
				'ticket' => rawurlencode( $ticket ),
				'updated' => '1',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public static function handle_settings_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		check_admin_referer( 'aegis_forms_settings' );

		Aegis_Forms_Settings::update_settings( $_POST );

		$redirect = add_query_arg(
			array(
				'page' => self::MENU_SLUG,
				'tab' => 'settings',
				'settings-updated' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	private static function render_settings_section() {
		if ( isset( $_GET['settings-updated'] ) && '1' === $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.' ) . '</p></div>';
		}

		$settings = Aegis_Forms_Settings::get_settings();
		$form_types = Aegis_Forms_Settings::get_form_types();
		?>
		<hr />
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'aegis_forms_settings' ); ?>
			<input type="hidden" name="action" value="aegis_forms_settings" />
			<table class="form-table">
				<tbody>
					<?php foreach ( $form_types as $type => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="guest_allowed[<?php echo esc_attr( $type ); ?>]" value="1" <?php checked( ! empty( $settings['guest_allowed'][ $type ] ) ); ?> />
									<?php echo esc_html__( 'Allow guest submissions' ); ?>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><label for="aegis-forms-login-required-message"><?php echo esc_html__( 'Login required message' ); ?></label></th>
						<td>
							<textarea id="aegis-forms-login-required-message" name="messages[login_required]" rows="3" class="large-text"><?php echo esc_textarea( $settings['messages']['login_required'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aegis-forms-submit-denied-message"><?php echo esc_html__( 'Submit denied message' ); ?></label></th>
						<td>
							<textarea id="aegis-forms-submit-denied-message" name="messages[submit_denied]" rows="3" class="large-text"><?php echo esc_textarea( $settings['messages']['submit_denied'] ); ?></textarea>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( __( 'Save' ) ); ?>
		</form>
		<?php
	}

	public static function handle_delete_submission() {
		self::handle_trash_action( 'deleted', 'aegis_forms_delete_', 'deleted' );
	}

	public static function handle_restore_submission() {
		self::handle_trash_action( 'new', 'aegis_forms_restore_', 'restored' );
	}

	private static function handle_trash_action( $status, $nonce_prefix, $notice ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$ticket = isset( $_REQUEST['ticket'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ticket'] ) ) : '';
		$ticket = substr( $ticket, 0, 64 );
		check_admin_referer( $nonce_prefix . $ticket );

		global $wpdb;
		$table_name = Aegis_Forms_Schema::table_name();
		$wpdb->update(
			$table_name,
			array(
				'status' => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'ticket_no' => $ticket ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';
		if ( $redirect_to ) {
			$redirect_to = wp_validate_redirect( $redirect_to, admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		} else {
			$redirect_to = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		}
		$redirect_to = add_query_arg( 'aegis_forms_notice', $notice, $redirect_to );
		wp_safe_redirect( $redirect_to );
		exit;
	}

}
