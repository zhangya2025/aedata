<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aegis_Forms_Frontend {
	const ACTION_SUBMIT = 'aegis_forms_submit';

	public static function register() {
		add_shortcode( 'aegis_repair_form', array( __CLASS__, 'render_repair_form' ) );
		add_shortcode( 'aegis_dealer_form', array( __CLASS__, 'render_dealer_form' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
	}

	public static function render_repair_form() {
		return self::render_form( 'repair' );
	}

	public static function render_dealer_form() {
		return self::render_form( 'dealer' );
	}

	private static function render_form( $type ) {
		$type = $type === 'dealer' ? 'dealer' : 'repair';
		$notice = self::render_notice( $type );
		$nonce_action = 'aegis_forms_submit_' . $type;
		$action_url = admin_url( 'admin-post.php' );
		$honeypot_name = 'website';

		ob_start();
		?>
		<?php echo $notice; ?>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SUBMIT ); ?>" />
			<input type="hidden" name="form_type" value="<?php echo esc_attr( $type ); ?>" />
			<?php wp_nonce_field( $nonce_action ); ?>
			<div style="position:absolute;left:-9999px;" aria-hidden="true">
				<label><?php echo esc_html__( 'Website' ); ?></label>
				<input type="text" name="<?php echo esc_attr( $honeypot_name ); ?>" value="" autocomplete="off" />
			</div>
			<?php if ( 'repair' === $type ) : ?>
				<p>
					<label for="aegis-repair-name"><?php echo esc_html__( 'Name' ); ?></label><br />
					<input id="aegis-repair-name" type="text" name="name" required />
				</p>
				<p>
					<label for="aegis-repair-email"><?php echo esc_html__( 'Email' ); ?></label><br />
					<input id="aegis-repair-email" type="email" name="email" required />
				</p>
				<p>
					<label for="aegis-repair-phone"><?php echo esc_html__( 'Phone' ); ?></label><br />
					<input id="aegis-repair-phone" type="text" name="phone" />
				</p>
				<p>
					<label for="aegis-repair-country"><?php echo esc_html__( 'Country' ); ?></label><br />
					<input id="aegis-repair-country" type="text" name="country" />
				</p>
				<p>
					<label for="aegis-repair-order-number"><?php echo esc_html__( 'Order Number' ); ?></label><br />
					<input id="aegis-repair-order-number" type="text" name="order_number" />
				</p>
				<p>
					<label for="aegis-repair-product-sku"><?php echo esc_html__( 'Product SKU' ); ?></label><br />
					<input id="aegis-repair-product-sku" type="text" name="product_sku" />
				</p>
				<p>
					<label for="aegis-repair-message"><?php echo esc_html__( 'Message' ); ?></label><br />
					<textarea id="aegis-repair-message" name="message" rows="6" required></textarea>
				</p>
			<?php else : ?>
				<p>
					<label for="aegis-dealer-company-name"><?php echo esc_html__( 'Company Name' ); ?></label><br />
					<input id="aegis-dealer-company-name" type="text" name="company_name" required />
				</p>
				<p>
					<label for="aegis-dealer-contact-name"><?php echo esc_html__( 'Contact Name' ); ?></label><br />
					<input id="aegis-dealer-contact-name" type="text" name="contact_name" required />
				</p>
				<p>
					<label for="aegis-dealer-email"><?php echo esc_html__( 'Email' ); ?></label><br />
					<input id="aegis-dealer-email" type="email" name="email" required />
				</p>
				<p>
					<label for="aegis-dealer-phone"><?php echo esc_html__( 'Phone' ); ?></label><br />
					<input id="aegis-dealer-phone" type="text" name="phone" />
				</p>
				<p>
					<label for="aegis-dealer-country"><?php echo esc_html__( 'Country' ); ?></label><br />
					<input id="aegis-dealer-country" type="text" name="country" />
				</p>
				<p>
					<label for="aegis-dealer-website"><?php echo esc_html__( 'Website or Social' ); ?></label><br />
					<input id="aegis-dealer-website" type="text" name="website" />
				</p>
				<p>
					<label for="aegis-dealer-message"><?php echo esc_html__( 'Message' ); ?></label><br />
					<textarea id="aegis-dealer-message" name="message" rows="6"></textarea>
				</p>
			<?php endif; ?>
			<p>
				<button type="submit"><?php echo esc_html__( 'Submit' ); ?></button>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function render_notice( $type ) {
		if ( ! isset( $_GET['aegis_forms'] ) ) {
			return '';
		}

		$status = sanitize_text_field( wp_unslash( $_GET['aegis_forms'] ) );
		$ticket = isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';
		$reason = isset( $_GET['reason'] ) ? sanitize_text_field( wp_unslash( $_GET['reason'] ) ) : '';

		if ( 'submitted' === $status && $ticket ) {
			$message = sprintf(
				/* translators: %s: ticket number */
				esc_html__( 'Thank you. Your request has been submitted. Ticket: %s' ),
				esc_html( $ticket )
			);
			return '<div class="notice notice-success"><p>' . $message . '</p></div>';
		}

		if ( 'error' === $status ) {
			$messages = array(
				'invalid_nonce' => esc_html__( 'Security check failed. Please try again.' ),
				'invalid_input' => esc_html__( 'Please check the required fields and try again.' ),
				'rate_limited' => esc_html__( 'Too many submissions. Please try again later.' ),
				'server_error' => esc_html__( 'Submission failed. Please try again later.' ),
			);
			$text = isset( $messages[ $reason ] ) ? $messages[ $reason ] : $messages['server_error'];
			return '<div class="notice notice-error"><p>' . $text . '</p></div>';
		}

		return '';
	}

	public static function handle_submit() {
		$form_type = isset( $_POST['form_type'] ) ? sanitize_text_field( wp_unslash( $_POST['form_type'] ) ) : '';
		if ( ! in_array( $form_type, array( 'repair', 'dealer' ), true ) ) {
			self::redirect_with_error( 'invalid_input' );
		}

		$nonce_action = 'aegis_forms_submit_' . $form_type;
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $nonce_action ) ) {
			self::redirect_with_error( 'invalid_nonce' );
		}

		$honeypot = isset( $_POST['website'] ) ? sanitize_text_field( wp_unslash( $_POST['website'] ) ) : '';
		if ( '' !== $honeypot ) {
			self::redirect_with_error( 'invalid_input' );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rate_key = 'aegis_forms_rate_' . md5( $ip . '|' . $form_type );
		$count = (int) get_transient( $rate_key );
		if ( $count >= 5 ) {
			self::redirect_with_error( 'rate_limited' );
		}
		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		if ( ! Aegis_Forms_Schema::table_exists() ) {
			self::redirect_with_error( 'server_error' );
		}

		$now = current_time( 'mysql' );
		$name = '';
		$email = '';
		$phone = '';
		$country = '';
		$message = '';
		$meta = array();

		if ( 'repair' === $form_type ) {
			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
			$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
			$order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
			$product_sku = isset( $_POST['product_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['product_sku'] ) ) : '';
			if ( $order_number ) {
				$meta['order_number'] = $order_number;
			}
			if ( $product_sku ) {
				$meta['product_sku'] = $product_sku;
			}

			if ( '' === $name || '' === $email || '' === $message || ! is_email( $email ) ) {
				self::redirect_with_error( 'invalid_input' );
			}
		} else {
			$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
			$contact_name = isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
			$website = isset( $_POST['website'] ) ? sanitize_text_field( wp_unslash( $_POST['website'] ) ) : '';
			$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

			if ( $company_name ) {
				$meta['company_name'] = $company_name;
			}
			if ( $website ) {
				$meta['website'] = $website;
			}

			if ( '' === $contact_name || '' === $email || ! is_email( $email ) ) {
				self::redirect_with_error( 'invalid_input' );
			}

			$name = $contact_name;
		}

		$ip_value = $ip ? substr( $ip, 0, 64 ) : null;
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$user_agent = $user_agent ? substr( $user_agent, 0, 255 ) : null;

		$phone = $phone ? $phone : null;
		$country = $country ? $country : null;
		$message = $message ? $message : null;

		$data = array(
			'type' => $form_type,
			'ticket_no' => '',
			'status' => 'new',
			'name' => $name,
			'email' => $email,
			'phone' => $phone,
			'country' => $country,
			'subject' => null,
			'message' => $message,
			'meta' => wp_json_encode( (object) $meta ),
			'attachments' => '[]',
			'admin_notes' => null,
			'created_at' => $now,
			'updated_at' => $now,
			'ip' => $ip_value,
			'user_agent' => $user_agent,
		);

		$formats = array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		);

		global $wpdb;
		$table_name = Aegis_Forms_Schema::table_name();
		$inserted = $wpdb->insert( $table_name, $data, $formats );
		if ( false === $inserted ) {
			self::redirect_with_error( 'server_error' );
		}

		$insert_id = (int) $wpdb->insert_id;
		if ( $insert_id <= 0 ) {
			self::redirect_with_error( 'server_error' );
		}

		$prefix = 'repair' === $form_type ? 'RMA' : 'DLR';
		$date_part = wp_date( 'Ymd', current_time( 'timestamp' ) );
		$sequence = str_pad( (string) $insert_id, 6, '0', STR_PAD_LEFT );
		$ticket_no = $prefix . '-' . $date_part . '-' . $sequence;

		$updated = $wpdb->update(
			$table_name,
			array( 'ticket_no' => $ticket_no ),
			array( 'id' => $insert_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			$ticket_no = $ticket_no . '-' . $insert_id;
			$wpdb->update(
				$table_name,
				array( 'ticket_no' => $ticket_no ),
				array( 'id' => $insert_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		self::send_notifications( $form_type, $ticket_no, $name, $email, $phone, $country, $meta, $message );
		self::redirect_with_success( $ticket_no );
	}

	private static function send_notifications( $form_type, $ticket_no, $name, $email, $phone, $country, $meta, $message ) {
		$admin_to = get_option( 'admin_email' );
		if ( defined( 'AEGIS_FORMS_NOTIFY_TO' ) && AEGIS_FORMS_NOTIFY_TO ) {
			$admin_to = AEGIS_FORMS_NOTIFY_TO;
		}

		$subject_admin = 'repair' === $form_type
			? sprintf( '[AEGIS] New Repair Request: %s', $ticket_no )
			: sprintf( '[AEGIS] New Dealer Application: %s', $ticket_no );

		$admin_body_lines = array(
			'Ticket: ' . $ticket_no,
			'Type: ' . $form_type,
			'Name: ' . $name,
			'Email: ' . $email,
			'Phone: ' . ( $phone ? $phone : '-' ),
			'Country: ' . ( $country ? $country : '-' ),
		);

		if ( ! empty( $meta ) ) {
			foreach ( $meta as $key => $value ) {
				$admin_body_lines[] = ucfirst( str_replace( '_', ' ', $key ) ) . ': ' . $value;
			}
		}

		if ( $message ) {
			$admin_body_lines[] = 'Message: ' . $message;
		}

		$admin_body_lines[] = 'View: ' . admin_url( 'admin.php?page=aegis-forms-view&ticket=' . rawurlencode( $ticket_no ) );
		wp_mail( $admin_to, $subject_admin, implode( "\n", $admin_body_lines ) );

		$subject_user = sprintf( 'We received your request: %s', $ticket_no );
		$user_body = implode(
			"\n",
			array(
				'Hello,',
				'',
				'Thank you for reaching out. We have received your request.',
				'Ticket: ' . $ticket_no,
				'We will contact you if we need more information.',
			)
		);
		wp_mail( $email, $subject_user, $user_body );
	}

	private static function redirect_with_success( $ticket_no ) {
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}
		$redirect = add_query_arg(
			array(
				'aegis_forms' => 'submitted',
				'ticket' => rawurlencode( $ticket_no ),
			),
			$redirect
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	private static function redirect_with_error( $reason ) {
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}
		$redirect = add_query_arg(
			array(
				'aegis_forms' => 'error',
				'reason' => rawurlencode( $reason ),
			),
			$redirect
		);
		wp_safe_redirect( $redirect );
		exit;
	}
}
