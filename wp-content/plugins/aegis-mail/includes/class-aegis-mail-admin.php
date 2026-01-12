<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aegis_Mail_Admin {
	const SAVE_ACTION = 'aegis_mail_save';
	const TEST_ACTION = 'aegis_mail_test';

	private $core;
	private $test_error = '';

	public function __construct( Aegis_Mail $core ) {
		$this->core = $core;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'register_network_menu' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'handle_test' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'network_admin_notices', array( $this, 'render_notices' ) );
	}

	public function register_menu() {
		if ( is_multisite() ) {
			return;
		}

		add_options_page(
			__( 'Aegis Mail', 'aegis-mail' ),
			__( 'Aegis Mail', 'aegis-mail' ),
			'manage_options',
			'aegis-mail',
			array( $this, 'render_page' )
		);
	}

	public function register_network_menu() {
		if ( ! is_multisite() ) {
			return;
		}

		add_submenu_page(
			'settings.php',
			__( 'Aegis Mail', 'aegis-mail' ),
			__( 'Aegis Mail', 'aegis-mail' ),
			'manage_network_options',
			'aegis-mail',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! $this->current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aegis-mail' ) );
		}

		$settings      = $this->core->get_settings();
		$stored        = $this->core->get_option();
		$last_result   = $this->core->get_last_test_result();
		$password_set  = ! empty( $stored['smtp_password'] );
		$using_constant = defined( 'AEGIS_MAIL_SMTP_PASSWORD' );

		$admin_email = get_option( 'admin_email' );

		$save_url = admin_url( 'admin-post.php' );
		$test_url = admin_url( 'admin-post.php' );
		if ( is_multisite() ) {
			$save_url = network_admin_url( 'admin-post.php' );
			$test_url = network_admin_url( 'admin-post.php' );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Aegis Mail', 'aegis-mail' ); ?></h1>
			<form method="post" action="<?php echo esc_url( $save_url ); ?>">
				<?php wp_nonce_field( 'aegis_mail_save', 'aegis_mail_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Enable SMTP', 'aegis-mail' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?>>
									<?php echo esc_html__( 'Route all mail through this SMTP configuration.', 'aegis-mail' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'SMTP Host', 'aegis-mail' ); ?></th>
							<td><input type="text" class="regular-text" name="smtp_host" value="<?php echo esc_attr( $settings['smtp_host'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'SMTP Port', 'aegis-mail' ); ?></th>
							<td><input type="number" class="small-text" name="smtp_port" value="<?php echo esc_attr( $settings['smtp_port'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Encryption', 'aegis-mail' ); ?></th>
							<td>
								<select name="smtp_encryption">
									<option value="none" <?php selected( $settings['smtp_encryption'], 'none' ); ?>><?php echo esc_html__( 'None', 'aegis-mail' ); ?></option>
									<option value="ssl" <?php selected( $settings['smtp_encryption'], 'ssl' ); ?>><?php echo esc_html__( 'SSL', 'aegis-mail' ); ?></option>
									<option value="tls" <?php selected( $settings['smtp_encryption'], 'tls' ); ?>><?php echo esc_html__( 'TLS/STARTTLS', 'aegis-mail' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'AutoTLS', 'aegis-mail' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="smtp_autotls" value="1" <?php checked( $settings['smtp_autotls'], 1 ); ?>>
									<?php echo esc_html__( 'Enable SMTPAutoTLS when available.', 'aegis-mail' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'SMTP Username', 'aegis-mail' ); ?></th>
							<td><input type="text" class="regular-text" name="smtp_username" value="<?php echo esc_attr( $settings['smtp_username'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'SMTP Password', 'aegis-mail' ); ?></th>
							<td>
								<?php if ( $using_constant ) : ?>
									<p class="description">
										<?php echo esc_html__( 'Password is managed via the AEGIS_MAIL_SMTP_PASSWORD constant.', 'aegis-mail' ); ?>
									</p>
								<?php else : ?>
									<input type="password" class="regular-text" name="smtp_password" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $password_set ? __( 'Saved in database', 'aegis-mail' ) : '' ); ?>">
									<p class="description">
										<?php echo esc_html__( 'Leave blank to keep the existing password.', 'aegis-mail' ); ?>
									</p>
									<label>
										<input type="checkbox" name="clear_password" value="1">
										<?php echo esc_html__( 'Clear saved password', 'aegis-mail' ); ?>
									</label>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'From Email', 'aegis-mail' ); ?></th>
							<td><input type="email" class="regular-text" name="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'From Name', 'aegis-mail' ); ?></th>
							<td><input type="text" class="regular-text" name="from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Force From', 'aegis-mail' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="force_from" value="1" <?php checked( $settings['force_from'], 1 ); ?>>
									<?php echo esc_html__( 'Force From email/name for all outgoing mail.', 'aegis-mail' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Changes', 'aegis-mail' ) ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Send Test Email', 'aegis-mail' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $test_url ); ?>">
				<?php wp_nonce_field( 'aegis_mail_test', 'aegis_mail_test_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Recipient', 'aegis-mail' ); ?></th>
							<td>
								<input type="email" class="regular-text" name="test_recipient" value="<?php echo esc_attr( $admin_email ); ?>">
								<p class="description"><?php echo esc_html__( 'Send a simple plaintext email to verify SMTP delivery.', 'aegis-mail' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Send Test Email', 'aegis-mail' ), 'secondary' ); ?>
			</form>

			<?php if ( is_array( $last_result ) ) : ?>
				<h2><?php echo esc_html__( 'Last Test Result', 'aegis-mail' ); ?></h2>
				<p>
					<?php
					$time = isset( $last_result['time'] ) ? (int) $last_result['time'] : 0;
					$to   = isset( $last_result['to'] ) ? $last_result['to'] : '';
					$success = ! empty( $last_result['success'] );
					$error = isset( $last_result['error'] ) ? $last_result['error'] : '';

					printf(
						/* translators: 1: time, 2: recipient */
						esc_html__( 'Recipient: %1$s | Time: %2$s', 'aegis-mail' ),
						esc_html( $to ),
						esc_html( $time ? wp_date( 'Y-m-d H:i:s', $time ) : '-' )
					);
					?>
				</p>
				<p>
					<?php
					if ( $success ) {
						echo esc_html__( 'Status: Success', 'aegis-mail' );
					} else {
						echo esc_html__( 'Status: Failed', 'aegis-mail' );
						if ( $error ) {
							echo ' - ' . esc_html( $error );
						}
					}
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_save() {
		if ( ! $this->current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aegis-mail' ) );
		}

		check_admin_referer( 'aegis_mail_save', 'aegis_mail_nonce' );

		$current = $this->core->get_option();
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$settings = array(
			'enabled'         => isset( $_POST['enabled'] ) ? 1 : 0,
			'smtp_host'       => isset( $_POST['smtp_host'] ) ? sanitize_text_field( wp_unslash( $_POST['smtp_host'] ) ) : '',
			'smtp_port'       => isset( $_POST['smtp_port'] ) ? (int) $_POST['smtp_port'] : 587,
			'smtp_encryption' => isset( $_POST['smtp_encryption'] ) ? sanitize_text_field( wp_unslash( $_POST['smtp_encryption'] ) ) : 'none',
			'smtp_autotls'    => isset( $_POST['smtp_autotls'] ) ? 1 : 0,
			'smtp_username'   => isset( $_POST['smtp_username'] ) ? sanitize_text_field( wp_unslash( $_POST['smtp_username'] ) ) : '',
			'from_email'      => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
			'from_name'       => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
			'force_from'      => isset( $_POST['force_from'] ) ? 1 : 0,
		);

		$allowed_encryption = array( 'ssl', 'tls', 'none' );
		if ( ! in_array( $settings['smtp_encryption'], $allowed_encryption, true ) ) {
			$settings['smtp_encryption'] = 'none';
		}

		if ( defined( 'AEGIS_MAIL_SMTP_PASSWORD' ) ) {
			$settings['smtp_password'] = '';
		} else {
			if ( isset( $_POST['clear_password'] ) ) {
				$settings['smtp_password'] = '';
			} else {
				$password = isset( $_POST['smtp_password'] ) ? trim( wp_unslash( $_POST['smtp_password'] ) ) : '';
				if ( '' !== $password ) {
					$settings['smtp_password'] = $password;
				} else {
					$settings['smtp_password'] = isset( $current['smtp_password'] ) ? $current['smtp_password'] : '';
				}
			}
		}

		$this->core->update_option( $settings );

		$redirect = $this->get_settings_page_url( array( 'aegis_mail_status' => 'saved' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_test() {
		if ( ! $this->current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aegis-mail' ) );
		}

		check_admin_referer( 'aegis_mail_test', 'aegis_mail_test_nonce' );

		$recipient = isset( $_POST['test_recipient'] ) ? sanitize_email( wp_unslash( $_POST['test_recipient'] ) ) : '';
		if ( empty( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		$this->test_error = '';
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_failed' ), 999, 1 );

		$sent = wp_mail(
			$recipient,
			__( 'Aegis Mail Test', 'aegis-mail' ),
			__( 'This is a test email sent by the Aegis Mail plugin. Please verify delivery in your inbox.', 'aegis-mail' )
		);

		remove_action( 'wp_mail_failed', array( $this, 'capture_mail_failed' ), 999 );

		$success = (bool) $sent && '' === $this->test_error;
		if ( ! $success && '' === $this->test_error ) {
			$this->test_error = __( 'Unknown error. Check your SMTP settings and server logs.', 'aegis-mail' );
		}

		$result = array(
			'success' => $success ? 1 : 0,
			'time'    => time(),
			'to'      => $recipient,
			'error'   => $success ? '' : $this->test_error,
		);

		$this->core->set_last_test_result( $result );

		$status = $success ? 'test_success' : 'test_failed';
		$redirect = $this->get_settings_page_url( array( 'aegis_mail_status' => $status ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	public function capture_mail_failed( $error ) {
		if ( $error instanceof WP_Error ) {
			$this->test_error = $error->get_error_message();
		}
	}

	public function render_notices() {
		if ( ! $this->current_user_can_manage() ) {
			return;
		}

		$status = isset( $_GET['aegis_mail_status'] ) ? sanitize_text_field( wp_unslash( $_GET['aegis_mail_status'] ) ) : '';
		if ( ! $status ) {
			return;
		}

		if ( 'saved' === $status ) {
			$class = 'notice notice-success';
			$message = __( 'Settings saved.', 'aegis-mail' );
		} elseif ( 'test_success' === $status ) {
			$class = 'notice notice-success';
			$message = __( 'Test email sent. Please confirm delivery in your inbox.', 'aegis-mail' );
		} elseif ( 'test_failed' === $status ) {
			$class = 'notice notice-error';
			$message = __( 'Test email failed to send. Check the SMTP settings and try again.', 'aegis-mail' );
		} else {
			return;
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	private function current_user_can_manage() {
		if ( is_multisite() ) {
			return is_network_admin() && is_super_admin() && current_user_can( 'manage_network_options' );
		}

		return current_user_can( 'manage_options' );
	}

	private function get_settings_page_url( $args = array() ) {
		$base = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );

		$args = wp_parse_args(
			$args,
			array(
				'page' => 'aegis-mail',
			)
		);

		return add_query_arg( $args, $base );
	}
}
