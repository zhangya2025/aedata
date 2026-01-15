<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aegis_Forms_Frontend {
	const ACTION_SUBMIT = 'aegis_forms_submit';
	private static $current_lock_key = '';
	private static $current_token_key = '';

	public static function register() {
		add_shortcode( 'aegis_repair_form', array( __CLASS__, 'render_repair_form' ) );
		add_shortcode( 'aegis_dealer_form', array( __CLASS__, 'render_dealer_form' ) );
		add_shortcode( 'aegis_contact_form', array( __CLASS__, 'render_contact_form' ) );
		add_shortcode( 'aegis_sponsorship_form', array( __CLASS__, 'render_sponsorship_form' ) );
		add_shortcode( 'aegis_customization_form', array( __CLASS__, 'render_customization_form' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'handle_public_submit' ) );
	}

	public static function render_repair_form() {
		return self::render_form( 'repair' );
	}

	public static function render_dealer_form() {
		return self::render_form( 'dealer' );
	}

	public static function render_contact_form() {
		return self::render_form( 'contact' );
	}

	public static function render_sponsorship_form() {
		return self::render_form( 'sponsorship' );
	}

	public static function render_customization_form() {
		return self::render_form( 'customization' );
	}

	private static function render_form( $type ) {
		$type = in_array( $type, array( 'repair', 'dealer', 'contact', 'sponsorship', 'customization' ), true ) ? $type : 'repair';
		$notice = self::render_notice( $type );
		$nonce_action = 'aegis_forms_submit_' . $type;
		$honeypot_name = 'website';
		$token = wp_generate_uuid4();
		$token_key = 'aegis_forms_token:' . $token;
		set_transient( $token_key, 'new', 10 * MINUTE_IN_SECONDS );
		$allow_attachments = 'contact' !== $type;
		$attachment_required = in_array( $type, array( 'sponsorship', 'customization' ), true );

		ob_start();
		?>
		<?php echo $notice; ?>
		<form method="post" action=""<?php echo $allow_attachments ? ' enctype="multipart/form-data"' : ''; ?> data-aegis-forms="true">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SUBMIT ); ?>" />
			<input type="hidden" name="aegis_forms_public_submit" value="1" />
			<input type="hidden" name="form_type" value="<?php echo esc_attr( $type ); ?>" />
			<input type="hidden" name="request_token" value="<?php echo esc_attr( $token ); ?>" />
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
			<?php elseif ( 'dealer' === $type ) : ?>
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
			<?php elseif ( 'contact' === $type ) : ?>
				<p>
					<label for="aegis-contact-name"><?php echo esc_html__( 'Name' ); ?></label><br />
					<input id="aegis-contact-name" type="text" name="name" required />
				</p>
				<p>
					<label for="aegis-contact-email"><?php echo esc_html__( 'Email' ); ?></label><br />
					<input id="aegis-contact-email" type="email" name="email" required />
				</p>
				<p>
					<label for="aegis-contact-phone"><?php echo esc_html__( 'Phone' ); ?></label><br />
					<input id="aegis-contact-phone" type="text" name="phone" />
				</p>
				<p>
					<label for="aegis-contact-country"><?php echo esc_html__( 'Country' ); ?></label><br />
					<input id="aegis-contact-country" type="text" name="country" />
				</p>
				<p>
					<label for="aegis-contact-subject"><?php echo esc_html__( 'Subject' ); ?></label><br />
					<input id="aegis-contact-subject" type="text" name="subject" />
				</p>
				<p>
					<label for="aegis-contact-message"><?php echo esc_html__( 'Message' ); ?></label><br />
					<textarea id="aegis-contact-message" name="message" rows="6" required></textarea>
				</p>
			<?php else : ?>
				<p>
					<label for="aegis-special-name"><?php echo esc_html__( 'Name' ); ?></label><br />
					<input id="aegis-special-name" type="text" name="name" required />
				</p>
				<p>
					<label for="aegis-special-email"><?php echo esc_html__( 'Email' ); ?></label><br />
					<input id="aegis-special-email" type="email" name="email" required />
				</p>
				<p>
					<label for="aegis-special-subject"><?php echo esc_html__( 'Subject' ); ?></label><br />
					<input id="aegis-special-subject" type="text" name="subject" />
				</p>
				<p>
					<label for="aegis-special-message"><?php echo esc_html__( 'Message' ); ?></label><br />
					<textarea id="aegis-special-message" name="message" rows="6" required></textarea>
				</p>
			<?php endif; ?>
			<p>
				<button type="submit" class="aegis-forms-submit"><?php echo esc_html__( 'Submit' ); ?></button>
			</p>
			<?php if ( $allow_attachments ) : ?>
				<p>
					<label for="aegis-forms-attachment">
						<?php
						echo esc_html(
							$attachment_required ? __( 'Attachment required' ) : __( 'Attachment (optional)' )
						);
						?>
					</label><br />
					<input id="aegis-forms-attachment" type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" <?php echo $attachment_required ? 'required' : ''; ?> />
					<br />
					<small><?php echo esc_html__( 'Up to 1 file. Max 10MB. JPG/PNG/PDF only.' ); ?></small>
				</p>
			<?php endif; ?>
			<script>
				(function() {
					var forms = document.querySelectorAll('form[data-aegis-forms]');
					forms.forEach(function(form) {
						form.addEventListener('submit', function(event) {
							if (form.dataset.submitted === 'true') {
								event.preventDefault();
								return;
							}
							form.dataset.submitted = 'true';
							var button = form.querySelector('.aegis-forms-submit');
							if (button) {
								button.disabled = true;
								button.textContent = 'Submitting...';
							}
						});
					});
				})();
			</script>
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
					'attachment_required' => esc_html__( 'Please attach a file before submitting.' ),
					'too_many_files' => esc_html__( 'You can upload up to 1 file.' ),
					'file_too_large' => esc_html__( 'Each file must be 10MB or smaller.' ),
					'invalid_file' => esc_html__( 'Only JPG, PNG, or PDF files are allowed.' ),
				'upload_failed' => esc_html__( 'File upload failed. Please try again.' ),
				'invalid_token' => esc_html__( 'Submission token missing. Please refresh and try again.' ),
				'expired_token' => esc_html__( 'Submission token expired. Please refresh and try again.' ),
				'busy' => esc_html__( 'Submission already in progress. Please try again.' ),
				'server_error' => esc_html__( 'Submission failed. Please try again later.' ),
			);
			$text = isset( $messages[ $reason ] ) ? $messages[ $reason ] : $messages['server_error'];
			return '<div class="notice notice-error"><p>' . $text . '</p></div>';
		}

		return '';
	}

	public static function handle_submit() {
		self::process_submission();
	}

	public static function handle_public_submit() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( empty( $_POST['aegis_forms_public_submit'] ) ) {
			return;
		}

		self::process_submission( 303 );
	}

	private static function process_submission( $redirect_status = 302 ) {
		$token = isset( $_POST['request_token'] ) ? sanitize_text_field( wp_unslash( $_POST['request_token'] ) ) : '';
		if ( '' === $token || strlen( $token ) < 16 ) {
			self::redirect_with_error( 'invalid_token', $redirect_status );
		}

		$token_key = 'aegis_forms_token:' . $token;
		$token_state = get_transient( $token_key );
		if ( ! $token_state ) {
			self::redirect_with_error( 'expired_token', $redirect_status );
		}

		if ( is_string( $token_state ) && 0 === strpos( $token_state, 'done:' ) ) {
			$ticket_no = substr( $token_state, 5 );
			self::redirect_with_success( $ticket_no, $redirect_status );
		}

		$lock_key = 'aegis_forms_lock_' . md5( $token );
		if ( ! add_option( $lock_key, time(), '', 'no' ) ) {
			for ( $i = 0; $i < 5; $i++ ) {
				usleep( 150000 );
				$token_state = get_transient( $token_key );
				if ( is_string( $token_state ) && 0 === strpos( $token_state, 'done:' ) ) {
					$ticket_no = substr( $token_state, 5 );
					self::redirect_with_success( $ticket_no, $redirect_status );
				}
			}

			self::redirect_with_error( 'busy', $redirect_status );
		}

		self::$current_lock_key = $lock_key;
		self::$current_token_key = $token_key;

		$form_type = isset( $_POST['form_type'] ) ? sanitize_text_field( wp_unslash( $_POST['form_type'] ) ) : '';
		if ( ! in_array( $form_type, array( 'repair', 'dealer', 'contact', 'sponsorship', 'customization' ), true ) ) {
			self::redirect_with_error( 'invalid_input', $redirect_status );
		}

		$nonce_action = 'aegis_forms_submit_' . $form_type;
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $nonce_action ) ) {
			self::redirect_with_error( 'invalid_nonce', $redirect_status );
		}

		$honeypot = isset( $_POST['website'] ) ? sanitize_text_field( wp_unslash( $_POST['website'] ) ) : '';
		if ( '' !== $honeypot ) {
			self::redirect_with_error( 'invalid_input', $redirect_status );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rate_key = 'aegis_forms_rate_' . md5( $ip . '|' . $form_type );
		$count = (int) get_transient( $rate_key );
		if ( $count >= 5 ) {
			self::redirect_with_error( 'rate_limited', $redirect_status );
		}
		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		if ( ! Aegis_Forms_Schema::table_exists() ) {
			self::redirect_with_error( 'server_error', $redirect_status );
		}

		$now = current_time( 'mysql' );
		$name = '';
		$email = '';
		$phone = '';
		$country = '';
		$message = '';
		$meta = array();

		$subject = '';

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
				self::redirect_with_error( 'invalid_input', $redirect_status );
			}
		} elseif ( 'dealer' === $form_type ) {
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
				self::redirect_with_error( 'invalid_input', $redirect_status );
			}

			$name = $contact_name;
		} else {
			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
			$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

			$referer = wp_get_referer();
			if ( $referer ) {
				$meta['source_url'] = $referer;
			}

			if ( '' === $name || '' === $email || '' === $message || ! is_email( $email ) ) {
				self::redirect_with_error( 'invalid_input', $redirect_status );
			}
		}

		$attachment_required = in_array( $form_type, array( 'sponsorship', 'customization' ), true );
		if ( $attachment_required ) {
			$files = self::gather_uploaded_files();
			if ( empty( $files ) ) {
				self::redirect_with_error( 'attachment_required', $redirect_status );
			}
			$first = reset( $files );
			if ( empty( $first['name'] ) || 0 === (int) $first['size'] ) {
				self::redirect_with_error( 'attachment_required', $redirect_status );
			}
		}

		$ip_value = $ip ? substr( $ip, 0, 64 ) : null;
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$user_agent = $user_agent ? substr( $user_agent, 0, 255 ) : null;

		$phone = $phone ? $phone : null;
		$country = $country ? $country : null;
		$message = $message ? $message : null;

		$subject_value = in_array( $form_type, array( 'contact', 'sponsorship', 'customization' ), true ) ? $subject : null;

		$data = array(
			'type' => $form_type,
			'ticket_no' => '',
			'status' => 'new',
			'name' => $name,
			'email' => $email,
			'phone' => $phone,
			'country' => $country,
			'subject' => $subject_value,
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
			self::redirect_with_error( 'server_error', $redirect_status );
		}

		$insert_id = (int) $wpdb->insert_id;
		if ( $insert_id <= 0 ) {
			self::redirect_with_error( 'server_error', $redirect_status );
		}

		if ( 'repair' === $form_type ) {
			$prefix = 'RMA';
		} elseif ( 'dealer' === $form_type ) {
			$prefix = 'DLR';
		} elseif ( 'sponsorship' === $form_type ) {
			$prefix = 'SPN';
		} elseif ( 'customization' === $form_type ) {
			$prefix = 'CST';
		} else {
			$prefix = 'CNT';
		}
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

		if ( 'contact' !== $form_type ) {
			$attachments = self::handle_attachments( $ticket_no, $insert_id, $redirect_status );
			if ( $attachments ) {
				$wpdb->update(
					$table_name,
					array( 'attachments' => wp_json_encode( $attachments ) ),
					array( 'id' => $insert_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}

		self::send_notifications( $form_type, $ticket_no, $name, $email, $phone, $country, $subject, $meta, $message );
		set_transient( $token_key, 'done:' . $ticket_no, 10 * MINUTE_IN_SECONDS );
		delete_option( $lock_key );
		self::$current_lock_key = '';
		self::$current_token_key = '';
		self::redirect_with_success( $ticket_no, $redirect_status );
	}

	private static function handle_attachments( $ticket_no, $insert_id, $redirect_status = 302 ) {
		$files = self::gather_uploaded_files();
		if ( empty( $files ) ) {
			return array();
		}

		if ( count( $files ) > 1 ) {
			self::delete_submission( $insert_id );
			self::redirect_with_error( 'too_many_files', $redirect_status );
		}

		$allowed_mimes = array(
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'pdf' => 'application/pdf',
		);

		$uploads = wp_upload_dir();
		$subdir = '/aegis-forms/' . $ticket_no;
		$filter = function( $dirs ) use ( $uploads, $subdir ) {
			$dirs['subdir'] = $subdir;
			$dirs['path'] = $uploads['basedir'] . $subdir;
			$dirs['url'] = $uploads['baseurl'] . $subdir;
			return $dirs;
		};

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$stored = array();
		$uploaded_files = array();

		add_filter( 'upload_dir', $filter, 999 );
		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== $file['error'] ) {
				$stored = self::rollback_uploads( $stored, $uploads['basedir'] . $subdir );
				remove_filter( 'upload_dir', $filter, 999 );
				self::delete_submission( $insert_id );
				self::redirect_with_error( 'upload_failed', $redirect_status );
			}

			if ( $file['size'] > 10 * 1024 * 1024 ) {
				$stored = self::rollback_uploads( $stored, $uploads['basedir'] . $subdir );
				remove_filter( 'upload_dir', $filter, 999 );
				self::delete_submission( $insert_id );
				self::redirect_with_error( 'file_too_large', $redirect_status );
			}

			$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
			if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
				$stored = self::rollback_uploads( $stored, $uploads['basedir'] . $subdir );
				remove_filter( 'upload_dir', $filter, 999 );
				self::delete_submission( $insert_id );
				self::redirect_with_error( 'invalid_file', $redirect_status );
			}

			$result = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes' => $allowed_mimes,
				)
			);

			if ( ! empty( $result['error'] ) || empty( $result['file'] ) ) {
				$stored = self::rollback_uploads( $stored, $uploads['basedir'] . $subdir );
				remove_filter( 'upload_dir', $filter, 999 );
				self::delete_submission( $insert_id );
				self::redirect_with_error( 'upload_failed', $redirect_status );
			}

			$stored[] = $result['file'];
			$relative = ltrim( str_replace( $uploads['basedir'], '', $result['file'] ), '/' );
			$uploaded_files[] = $relative;
		}
		remove_filter( 'upload_dir', $filter, 999 );

		return $uploaded_files;
	}

	private static function normalize_files( $files ) {
		$normalized = array();
		if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
			return $normalized;
		}

		$count = count( $files['name'] );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( empty( $files['name'][ $i ] ) && 0 === (int) $files['size'][ $i ] ) {
				continue;
			}
			$normalized[] = array(
				'name' => $files['name'][ $i ],
				'type' => $files['type'][ $i ],
				'tmp_name' => $files['tmp_name'][ $i ],
				'error' => $files['error'][ $i ],
				'size' => (int) $files['size'][ $i ],
			);
		}

		return $normalized;
	}

	private static function gather_uploaded_files() {
		$files = array();

		if ( isset( $_FILES['attachment'] ) && is_array( $_FILES['attachment'] ) ) {
			$single = self::normalize_single_file( $_FILES['attachment'] );
			if ( $single ) {
				$files[] = $single;
			}
		}

		if ( isset( $_FILES['attachments'] ) && is_array( $_FILES['attachments'] ) ) {
			$files = array_merge( $files, self::normalize_files( $_FILES['attachments'] ) );
		}

		return $files;
	}

	private static function normalize_single_file( $file ) {
		if ( empty( $file['name'] ) && 0 === (int) $file['size'] ) {
			return null;
		}

		return array(
			'name' => $file['name'],
			'type' => $file['type'],
			'tmp_name' => $file['tmp_name'],
			'error' => $file['error'],
			'size' => (int) $file['size'],
		);
	}

	private static function rollback_uploads( $files, $dir ) {
		foreach ( $files as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}

		if ( is_dir( $dir ) ) {
			$remaining = glob( trailingslashit( $dir ) . '*' );
			if ( empty( $remaining ) ) {
				rmdir( $dir );
			}
		}

		return array();
	}

	private static function delete_submission( $insert_id ) {
		global $wpdb;
		$table_name = Aegis_Forms_Schema::table_name();
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table_name} WHERE id = %d LIMIT 1", $insert_id )
		);
	}

	private static function send_notifications( $form_type, $ticket_no, $name, $email, $phone, $country, $subject, $meta, $message ) {
		$admin_to = get_option( 'admin_email' );
		if ( defined( 'AEGIS_FORMS_NOTIFY_TO' ) && AEGIS_FORMS_NOTIFY_TO ) {
			$admin_to = AEGIS_FORMS_NOTIFY_TO;
		}

		if ( 'repair' === $form_type ) {
			$subject_admin = sprintf( '[AEGIS] New Repair Request: %s', $ticket_no );
		} elseif ( 'dealer' === $form_type ) {
			$subject_admin = sprintf( '[AEGIS] New Dealer Application: %s', $ticket_no );
		} elseif ( 'sponsorship' === $form_type ) {
			$subject_admin = sprintf( '[AEGIS] New Sponsorship Request: %s', $ticket_no );
		} elseif ( 'customization' === $form_type ) {
			$subject_admin = sprintf( '[AEGIS] New Customization Request: %s', $ticket_no );
		} else {
			$subject_admin = sprintf( '[AEGIS] New Contact Message: %s', $ticket_no );
		}

		$admin_body_lines = array(
			'Ticket: ' . $ticket_no,
			'Type: ' . $form_type,
			'Name: ' . $name,
			'Email: ' . $email,
			'Phone: ' . ( $phone ? $phone : '-' ),
			'Country: ' . ( $country ? $country : '-' ),
		);

		if ( in_array( $form_type, array( 'contact', 'sponsorship', 'customization' ), true ) ) {
			$admin_body_lines[] = 'Subject: ' . ( $subject ? $subject : '-' );
		}

		if ( in_array( $form_type, array( 'sponsorship', 'customization' ), true ) ) {
			$admin_body_lines[] = 'Attachment: received (1 file)';
		}

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

		if ( 'contact' === $form_type ) {
			$subject_user = sprintf( 'We received your message: %s', $ticket_no );
			$user_body = implode(
				"\n",
				array(
					'Hello,',
					'',
					'Thank you for your message. We have received it.',
					'Ticket: ' . $ticket_no,
					'We will follow up if we need more information.',
				)
			);
		} elseif ( in_array( $form_type, array( 'sponsorship', 'customization' ), true ) ) {
			$subject_user = sprintf( 'We received your request: %s', $ticket_no );
			$user_body = implode(
				"\n",
				array(
					'Hello,',
					'',
					'Thank you for your request. We have received it.',
					'Ticket: ' . $ticket_no,
					'Attachment received.',
				)
			);
		} else {
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
		}
		wp_mail( $email, $subject_user, $user_body );
	}

	private static function redirect_with_success( $ticket_no, $redirect_status = 302 ) {
		if ( 303 === $redirect_status ) {
			$redirect = self::get_public_redirect_base();
		} else {
			$redirect = wp_get_referer();
			if ( ! $redirect ) {
				$redirect = home_url( '/' );
			}
		}
		$redirect = add_query_arg(
			array(
				'aegis_forms' => 'submitted',
				'ticket' => rawurlencode( $ticket_no ),
			),
			$redirect
		);
		wp_safe_redirect( $redirect, $redirect_status );
		exit;
	}

	private static function redirect_with_error( $reason, $redirect_status = 302 ) {
		if ( self::$current_lock_key ) {
			delete_option( self::$current_lock_key );
			if ( self::$current_token_key ) {
				set_transient( self::$current_token_key, 'new', 2 * MINUTE_IN_SECONDS );
			}
			self::$current_lock_key = '';
			self::$current_token_key = '';
		}

		if ( 303 === $redirect_status ) {
			$redirect = self::get_public_redirect_base();
		} else {
			$redirect = wp_get_referer();
			if ( ! $redirect ) {
				$redirect = home_url( '/' );
			}
		}
		$redirect = add_query_arg(
			array(
				'aegis_forms' => 'error',
				'reason' => rawurlencode( $reason ),
			),
			$redirect
		);
		wp_safe_redirect( $redirect, $redirect_status );
		exit;
	}

	private static function get_public_redirect_base() {
		$base = wp_get_raw_referer();
		if ( ! $base ) {
			$base = wp_get_referer();
		}

		if ( ! $base ) {
			$base = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		$base = remove_query_arg( array( 'aegis_forms', 'ticket', 'reason' ), $base );
		$validated = wp_validate_redirect( $base, '' );
		if ( ! $validated ) {
			$validated = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$validated = remove_query_arg( array( 'aegis_forms', 'ticket', 'reason' ), $validated );
		}

		return $validated;
	}
}
