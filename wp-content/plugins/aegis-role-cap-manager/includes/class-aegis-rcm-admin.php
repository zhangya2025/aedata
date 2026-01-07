<?php

class Aegis_RCM_Admin {
	const PAGE_SLUG = 'aegis-rcm';

	public static function register_menu() {
		if ( is_multisite() ) {
			if ( ! is_super_admin() ) {
				return;
			}
		} else {
			if ( ! ( current_user_can( 'manage_options' ) && current_user_can( 'edit_users' ) ) ) {
				return;
			}
		}

		$capability = is_multisite() ? 'manage_network_users' : 'manage_options';
		$parent_slug = is_multisite() ? 'users.php' : 'users.php';

		if ( is_multisite() ) {
			add_submenu_page(
				$parent_slug,
				__( 'Role & Capability Manager', 'aegis-rcm' ),
				__( 'Role & Capability Manager', 'aegis-rcm' ),
				$capability,
				self::PAGE_SLUG,
				array( __CLASS__, 'render_page' )
			);
			return;
		}

		add_submenu_page(
			$parent_slug,
			__( 'Role & Capability Manager', 'aegis-rcm' ),
			__( 'Role & Capability Manager', 'aegis-rcm' ),
			$capability,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( $hook_suffix ) {
		$target_hook = is_multisite()
			? 'users_page_' . self::PAGE_SLUG
			: 'users_page_' . self::PAGE_SLUG;

		if ( $hook_suffix !== $target_hook ) {
			return;
		}

		wp_enqueue_style(
			'aegis-rcm-admin',
			AEGIS_RCM_URL . 'assets/admin.css',
			array(),
			AEGIS_RCM_VERSION
		);
		wp_enqueue_script(
			'aegis-rcm-admin',
			AEGIS_RCM_URL . 'assets/admin.js',
			array( 'jquery' ),
			AEGIS_RCM_VERSION,
			true
		);
		$roles_list = array();
		foreach ( wp_roles()->roles as $role_key => $role_data ) {
			$roles_list[] = array(
				'key' => $role_key,
				'label' => translate_user_role( $role_data['name'] ),
			);
		}
		wp_localize_script(
			'aegis-rcm-admin',
			'aegisRcmData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'aegis_rcm_ajax' ),
				'roles' => $roles_list,
			)
		);
	}

	public static function render_page() {
		Aegis_RCM_Guards::must_be_super_admin();

		$selected_user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page = 20;

		$args = array(
			'number' => $per_page,
			'paged' => $paged,
			'search' => $search ? '*' . $search . '*' : '',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'orderby' => 'user_login',
			'order' => 'ASC',
		);

		$user_query = new WP_User_Query( $args );
		$users = $user_query->get_results();
		$total_users = $user_query->get_total();
		$total_pages = (int) ceil( $total_users / $per_page );

		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$message = '';
		if ( 'success' === $status ) {
			$message = __( 'Changes saved.', 'aegis-rcm' );
		} elseif ( 'error' === $status ) {
			$message = __( 'Unable to save changes.', 'aegis-rcm' );
		}

		$roles = wp_roles();
		$role_items = $roles instanceof WP_Roles ? $roles->roles : array();
		?>
		<div class="wrap aegis-rcm">
			<h1><?php esc_html_e( 'Role & Capability Manager', 'aegis-rcm' ); ?></h1>
			<?php if ( $message ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>
			<div class="aegis-rcm-layout">
				<div class="aegis-rcm-users">
					<form method="get" class="aegis-rcm-search">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
						<?php if ( is_multisite() ) : ?>
							<input type="hidden" name="network_admin" value="1" />
						<?php endif; ?>
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search users...', 'aegis-rcm' ); ?>" />
						<button class="button"><?php esc_html_e( 'Search', 'aegis-rcm' ); ?></button>
					</form>
					<ul class="aegis-rcm-user-list">
						<?php foreach ( $users as $user ) : ?>
							<?php
							$is_super_admin = is_multisite() && is_super_admin( $user->ID );
							$item_class = $selected_user_id === (int) $user->ID ? 'is-selected' : '';
							?>
							<li class="aegis-rcm-user <?php echo esc_attr( $item_class ); ?>" data-user-id="<?php echo esc_attr( $user->ID ); ?>">
								<div class="aegis-rcm-user-name">
									<?php echo esc_html( $user->display_name ); ?>
								</div>
								<div class="aegis-rcm-user-meta">
									<?php echo esc_html( $user->user_login ); ?>
									<?php if ( $is_super_admin ) : ?>
										<span class="aegis-rcm-badge"><?php esc_html_e( 'Super Admin (read-only)', 'aegis-rcm' ); ?></span>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $total_pages > 1 ) : ?>
						<div class="aegis-rcm-pagination">
							<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
								<?php
								$url = add_query_arg(
									array(
										'page' => self::PAGE_SLUG,
										'paged' => $i,
										's' => $search,
										'user_id' => $selected_user_id,
									),
									is_multisite() ? network_admin_url( 'users.php' ) : admin_url( 'users.php' )
								);
								?>
								<a class="<?php echo esc_attr( $i === $paged ? 'is-current' : '' ); ?>" href="<?php echo esc_url( $url ); ?>">
									<?php echo esc_html( $i ); ?>
								</a>
							<?php endfor; ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="aegis-rcm-details" data-selected-user="<?php echo esc_attr( $selected_user_id ); ?>">
					<div class="aegis-rcm-panel">
						<p class="description">
							<?php esc_html_e( 'Select a user to view and edit roles and capabilities.', 'aegis-rcm' ); ?>
						</p>
					</div>
					<form class="aegis-rcm-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'aegis_rcm_save', 'aegis_rcm_nonce' ); ?>
						<input type="hidden" name="action" value="aegis_rcm_save" />
						<input type="hidden" name="target_user_id" value="<?php echo esc_attr( $selected_user_id ); ?>" />
						<div class="aegis-rcm-readonly notice notice-warning hidden">
							<p><?php esc_html_e( 'Super Admin accounts are read-only here. Use native WordPress user or network management pages.', 'aegis-rcm' ); ?></p>
						</div>
						<div class="aegis-rcm-section">
							<h2><?php esc_html_e( 'Roles', 'aegis-rcm' ); ?></h2>
							<div class="aegis-rcm-checkboxes" data-section="roles">
								<?php foreach ( $role_items as $role_key => $role_data ) : ?>
									<label>
										<input type="checkbox" name="roles[]" value="<?php echo esc_attr( $role_key ); ?>" />
										<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="aegis-rcm-section">
							<h2><?php esc_html_e( 'User Capability Overrides', 'aegis-rcm' ); ?></h2>
							<p class="description"><?php esc_html_e( 'These toggle user-level capabilities without changing role definitions.', 'aegis-rcm' ); ?></p>
							<input type="search" class="aegis-rcm-cap-search" placeholder="<?php esc_attr_e( 'Filter capabilities...', 'aegis-rcm' ); ?>" />
							<div class="aegis-rcm-checkboxes" data-section="overrides"></div>
						</div>
						<div class="aegis-rcm-section">
							<h2><?php esc_html_e( 'Effective Capabilities', 'aegis-rcm' ); ?></h2>
							<p class="description aegis-rcm-effective-note"></p>
							<div class="aegis-rcm-checkboxes aegis-rcm-effective" data-section="effective"></div>
						</div>
						<p>
							<button type="submit" class="button button-primary aegis-rcm-save" disabled><?php esc_html_e( 'Save Changes', 'aegis-rcm' ); ?></button>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_save() {
		Aegis_RCM_Guards::must_be_super_admin();

		check_admin_referer( 'aegis_rcm_save', 'aegis_rcm_nonce' );

		$target_user_id = isset( $_POST['target_user_id'] ) ? (int) $_POST['target_user_id'] : 0;
		if ( Aegis_RCM_Guards::is_target_protected( $target_user_id ) ) {
			wp_safe_redirect( self::build_redirect_url( 'error', $target_user_id ) );
			exit;
		}

		$user = new WP_User( $target_user_id );
		if ( ! $user->exists() ) {
			wp_safe_redirect( self::build_redirect_url( 'error', 0 ) );
			exit;
		}

		$target_roles = isset( $_POST['roles'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['roles'] ) ) : array();
		$target_roles = array_values( array_unique( $target_roles ) );

		$current_roles = (array) $user->roles;
		$roles_to_remove = array_diff( $current_roles, $target_roles );
		$roles_to_add = array_diff( $target_roles, $current_roles );

		foreach ( $roles_to_remove as $role ) {
			$user->remove_role( $role );
		}
		foreach ( $roles_to_add as $role ) {
			$user->add_role( $role );
		}

		$role_keys = array_keys( wp_roles()->roles );
		$current_overrides = array();
		foreach ( $user->caps as $cap_key => $value ) {
			if ( in_array( $cap_key, $role_keys, true ) ) {
				continue;
			}
			$current_overrides[] = $cap_key;
		}

		$target_overrides = isset( $_POST['caps_overrides'] ) ? array_keys( (array) wp_unslash( $_POST['caps_overrides'] ) ) : array();
		$target_overrides = array_map( 'sanitize_text_field', $target_overrides );

		$overrides_to_remove = array_diff( $current_overrides, $target_overrides );
		$overrides_to_add = array_diff( $target_overrides, $current_overrides );

		foreach ( $overrides_to_remove as $cap ) {
			$user->remove_cap( $cap );
		}
		foreach ( $overrides_to_add as $cap ) {
			$user->add_cap( $cap, true );
		}

		wp_safe_redirect( self::build_redirect_url( 'success', $target_user_id ) );
		exit;
	}

	public static function ajax_get_user_detail() {
		Aegis_RCM_Guards::must_be_super_admin();

		check_ajax_referer( 'aegis_rcm_ajax', 'nonce' );

		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Missing user id.' ), 400 );
		}

		$user = new WP_User( $user_id );
		if ( ! $user->exists() ) {
			wp_send_json_error( array( 'message' => 'User not found.' ), 404 );
		}

		$is_protected = Aegis_RCM_Guards::is_target_protected( $user_id );
		$role_keys = array_keys( wp_roles()->roles );
		$overrides = array();
		foreach ( $user->caps as $cap_key => $value ) {
			if ( in_array( $cap_key, $role_keys, true ) ) {
				continue;
			}
			$overrides[] = $cap_key;
		}

		$catalog = Aegis_RCM_Cap_Catalog::get_catalog( $user );
		$effective = array_keys( $user->allcaps );

		if ( is_multisite() && is_super_admin( $user_id ) ) {
			$effective = $catalog;
		}

		wp_send_json_success(
			array(
				'user' => array(
					'id' => $user->ID,
					'login' => $user->user_login,
					'name' => $user->display_name,
					'email' => $user->user_email,
				),
				'isProtected' => $is_protected,
				'roles' => $user->roles,
				'overrides' => $overrides,
				'effective' => $effective,
				'catalog' => $catalog,
				'isSuperAdmin' => is_multisite() && is_super_admin( $user_id ),
			)
		);
	}

	public static function ajax_get_cap_catalog() {
		Aegis_RCM_Guards::must_be_super_admin();

		check_ajax_referer( 'aegis_rcm_ajax', 'nonce' );
		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$user = $user_id ? new WP_User( $user_id ) : null;

		$catalog = Aegis_RCM_Cap_Catalog::get_catalog( $user, $search );
		wp_send_json_success( array( 'catalog' => $catalog ) );
	}

	private static function build_redirect_url( $status, $user_id ) {
		$base = is_multisite() ? network_admin_url( 'users.php' ) : admin_url( 'users.php' );
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'status' => $status,
				'user_id' => $user_id,
			),
			$base
		);
	}
}
