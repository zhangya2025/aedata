<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aegis_Forms {
    const TABLE = 'aegis_forms_submissions';
    const OPTION_VERSION = 'aegis_forms_version';
    const RATE_LIMIT_MAX = 5;
    const RATE_LIMIT_TTL = 3600;
    private static $upload_ticket_no = '';

    public static function init() {
        add_action( 'admin_post_nopriv_aegis_forms_submit', array( __CLASS__, 'handle_submission' ) );
        add_action( 'admin_post_aegis_forms_submit', array( __CLASS__, 'handle_submission' ) );
        add_action( 'admin_post_aegis_forms_export', array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_post_aegis_forms_update', array( __CLASS__, 'handle_update' ) );
    }

    public static function activate() {
        self::create_table();
        update_option( self::OPTION_VERSION, AEGIS_FORMS_VERSION );
    }

    public static function create_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(32) NOT NULL,
            ticket_no VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'new',
            name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL,
            phone VARCHAR(64) NULL,
            country VARCHAR(64) NULL,
            subject VARCHAR(191) NULL,
            message LONGTEXT NULL,
            meta LONGTEXT NULL,
            attachments LONGTEXT NULL,
            admin_notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            ip VARCHAR(64) NULL,
            user_agent TEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_no (ticket_no),
            KEY type (type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function allowed_types() {
        return array( 'repair', 'dealer' );
    }

    public static function allowed_statuses() {
        return array( 'new', 'in_review', 'need_more_info', 'approved', 'rejected', 'closed' );
    }

    public static function capability() {
        return is_multisite() ? 'manage_network' : 'manage_options';
    }

    public static function must_have_access() {
        if ( is_multisite() && ! is_super_admin() ) {
            wp_die( esc_html__( 'Access denied.', 'aegis-forms' ) );
        }
        if ( ! current_user_can( self::capability() ) ) {
            wp_die( esc_html__( 'Access denied.', 'aegis-forms' ) );
        }
    }

    public static function handle_submission() {
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'aegis_forms_submit' ) ) {
            self::redirect_with_message( 'error', 'nonce' );
        }

        $form_type = isset( $_POST['form_type'] ) ? sanitize_text_field( wp_unslash( $_POST['form_type'] ) ) : '';
        if ( ! in_array( $form_type, self::allowed_types(), true ) ) {
            self::redirect_with_message( 'error', 'invalid' );
        }

        $honeypot = isset( $_POST['website'] ) ? trim( wp_unslash( $_POST['website'] ) ) : '';
        if ( $honeypot !== '' ) {
            self::redirect_with_message( 'error', 'invalid' );
        }

        $ip = self::get_ip_address();
        $rate_key = self::rate_limit_key( $ip, $form_type );
        $rate_count = (int) get_transient( $rate_key );
        if ( $rate_count >= self::RATE_LIMIT_MAX ) {
            self::redirect_with_message( 'error', 'rate' );
        }

        $data = self::sanitize_submission_data( $form_type );
        if ( is_wp_error( $data ) ) {
            self::redirect_with_message( 'error', 'required' );
        }

        global $wpdb;
        $table_name = self::table_name();
        $now = current_time( 'mysql' );

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'type' => $form_type,
                'ticket_no' => 'pending-' . wp_generate_password( 12, false, false ),
                'status' => 'new',
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => $data['country'],
                'subject' => $data['subject'],
                'message' => $data['message'],
                'meta' => $data['meta'],
                'attachments' => null,
                'admin_notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'ip' => $ip,
                'user_agent' => self::get_user_agent(),
            ),
            array(
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
            )
        );

        if ( false === $inserted ) {
            self::redirect_with_message( 'error', 'server' );
        }

        $insert_id = (int) $wpdb->insert_id;
        $ticket_no = self::generate_ticket_no( $form_type, $insert_id );
        $wpdb->update(
            $table_name,
            array(
                'ticket_no' => $ticket_no,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $insert_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        $attachments = self::handle_attachments( $ticket_no );
        if ( is_wp_error( $attachments ) ) {
            $wpdb->delete( $table_name, array( 'id' => $insert_id ), array( '%d' ) );
            self::redirect_with_message( 'error', 'upload' );
        }

        if ( ! empty( $attachments ) ) {
            $wpdb->update(
                $table_name,
                array(
                    'attachments' => wp_json_encode( $attachments ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $insert_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }

        set_transient( $rate_key, $rate_count + 1, self::RATE_LIMIT_TTL );

        self::send_notifications( $form_type, $ticket_no, $data, $attachments );

        self::redirect_with_message( 'submitted', 'ok', $ticket_no );
    }

    public static function handle_update() {
        self::must_have_access();

        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'aegis_forms_update' ) ) {
            wp_die( esc_html__( 'Invalid request.', 'aegis-forms' ) );
        }

        $submission_id = isset( $_POST['submission_id'] ) ? (int) $_POST['submission_id'] : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $admin_notes = isset( $_POST['admin_notes'] ) ? wp_kses_post( wp_unslash( $_POST['admin_notes'] ) ) : '';

        if ( ! in_array( $status, self::allowed_statuses(), true ) ) {
            wp_die( esc_html__( 'Invalid status.', 'aegis-forms' ) );
        }

        global $wpdb;
        $table_name = self::table_name();
        $wpdb->update(
            $table_name,
            array(
                'status' => $status,
                'admin_notes' => $admin_notes,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $submission_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        $redirect_url = self::admin_url( 'admin.php?page=aegis-forms-view&submission_id=' . $submission_id );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    public static function handle_export() {
        self::must_have_access();

        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'aegis_forms_export' ) ) {
            wp_die( esc_html__( 'Invalid request.', 'aegis-forms' ) );
        }

        $filters = self::sanitize_filters_from_request( $_POST );
        $submissions = self::get_submissions( $filters, 0, 0 );

        $filename = 'aegis-forms-' . gmdate( 'Ymd-His' ) . '.csv';
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ticket_no', 'type', 'status', 'name', 'email', 'phone', 'country', 'subject', 'created_at' ) );

        foreach ( $submissions as $submission ) {
            fputcsv(
                $output,
                array(
                    $submission->ticket_no,
                    $submission->type,
                    $submission->status,
                    $submission->name,
                    $submission->email,
                    $submission->phone,
                    $submission->country,
                    $submission->subject,
                    $submission->created_at,
                )
            );
        }

        fclose( $output );
        exit;
    }

    public static function sanitize_filters() {
        return self::sanitize_filters_from_request( $_GET );
    }

    public static function sanitize_filters_from_request( $request ) {
        $type = isset( $request['type'] ) ? sanitize_text_field( wp_unslash( $request['type'] ) ) : '';
        $status = isset( $request['status'] ) ? sanitize_text_field( wp_unslash( $request['status'] ) ) : '';
        $ticket = isset( $request['ticket'] ) ? sanitize_text_field( wp_unslash( $request['ticket'] ) ) : '';

        if ( ! in_array( $type, self::allowed_types(), true ) ) {
            $type = '';
        }

        if ( ! in_array( $status, self::allowed_statuses(), true ) ) {
            $status = '';
        }

        return array(
            'type' => $type,
            'status' => $status,
            'ticket' => $ticket,
        );
    }

    public static function get_submissions( array $filters, $limit = 50, $offset = 0 ) {
        global $wpdb;
        $table_name = self::table_name();

        $where = array();
        $params = array();

        if ( ! empty( $filters['type'] ) ) {
            $where[] = 'type = %s';
            $params[] = $filters['type'];
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if ( ! empty( $filters['ticket'] ) ) {
            $where[] = 'ticket_no = %s';
            $params[] = $filters['ticket'];
        }

        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        $limit_sql = '';
        if ( $limit > 0 ) {
            $limit_sql = $wpdb->prepare( 'LIMIT %d OFFSET %d', $limit, $offset );
        }

        $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY created_at DESC {$limit_sql}";
        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return $wpdb->get_results( $sql );
    }

    public static function count_submissions( array $filters ) {
        global $wpdb;
        $table_name = self::table_name();

        $where = array();
        $params = array();

        if ( ! empty( $filters['type'] ) ) {
            $where[] = 'type = %s';
            $params[] = $filters['type'];
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if ( ! empty( $filters['ticket'] ) ) {
            $where[] = 'ticket_no = %s';
            $params[] = $filters['ticket'];
        }

        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        $sql = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return (int) $wpdb->get_var( $sql );
    }

    private static function sanitize_submission_data( $form_type ) {
        if ( 'repair' === $form_type ) {
            $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
            $country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
            $order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
            $product_sku = isset( $_POST['product_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['product_sku'] ) ) : '';
            $message = isset( $_POST['issue_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['issue_description'] ) ) : '';

            if ( '' === $name || '' === $email || '' === $message ) {
                return new WP_Error( 'required', 'Missing required fields.' );
            }

            $meta = array(
                'order_number' => $order_number,
                'product_sku' => $product_sku,
            );

            return array(
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'country' => $country,
                'subject' => 'Repair Request',
                'message' => $message,
                'meta' => wp_json_encode( $meta ),
            );
        }

        $company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
        $contact_name = isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
        $website = isset( $_POST['website_social'] ) ? sanitize_text_field( wp_unslash( $_POST['website_social'] ) ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( '' === $company_name || '' === $contact_name || '' === $email || '' === $phone ) {
            return new WP_Error( 'required', 'Missing required fields.' );
        }

        $meta = array(
            'company_name' => $company_name,
            'website_social' => $website,
        );

        return array(
            'name' => $contact_name,
            'email' => $email,
            'phone' => $phone,
            'country' => $country,
            'subject' => 'Dealer Application',
            'message' => $message,
            'meta' => wp_json_encode( $meta ),
        );
    }

    private static function generate_ticket_no( $form_type, $insert_id ) {
        $date = wp_date( 'Ymd' );
        $prefix = 'repair' === $form_type ? 'RMA' : 'DLR';
        return sprintf( '%s-%s-%06d', $prefix, $date, $insert_id );
    }

    private static function handle_attachments( $ticket_no ) {
        if ( empty( $_FILES['attachments'] ) || empty( $_FILES['attachments']['name'] ) ) {
            return array();
        }

        $files = $_FILES['attachments'];
        $file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 0;
        if ( $file_count > 3 ) {
            return new WP_Error( 'upload_limit', 'Too many files.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $allowed_mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
        );

        $uploads = wp_upload_dir();
        $stored = array();

        for ( $index = 0; $index < $file_count; $index++ ) {
            if ( empty( $files['name'][ $index ] ) ) {
                continue;
            }

            if ( $files['error'][ $index ] !== UPLOAD_ERR_OK ) {
                return new WP_Error( 'upload_error', 'Upload failed.' );
            }

            if ( $files['size'][ $index ] > AEGIS_FORMS_MAX_FILE_SIZE ) {
                return new WP_Error( 'upload_size', 'File too large.' );
            }

            $file = array(
                'name' => $files['name'][ $index ],
                'type' => $files['type'][ $index ],
                'tmp_name' => $files['tmp_name'][ $index ],
                'error' => $files['error'][ $index ],
                'size' => $files['size'][ $index ],
            );

            $file_type = wp_check_filetype( $file['name'], $allowed_mimes );
            if ( empty( $file_type['type'] ) ) {
                return new WP_Error( 'upload_type', 'Invalid file type.' );
            }

            self::$upload_ticket_no = $ticket_no;
            add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

            $result = wp_handle_upload(
                $file,
                array(
                    'mimes' => $allowed_mimes,
                    'test_form' => false,
                )
            );

            remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

            if ( isset( $result['error'] ) ) {
                self::$upload_ticket_no = '';
                return new WP_Error( 'upload_error', $result['error'] );
            }

            $relative_path = ltrim( str_replace( $uploads['basedir'], '', $result['file'] ), '/' );
            $stored[] = $relative_path;
        }

        self::$upload_ticket_no = '';

        return $stored;
    }

    public static function filter_upload_dir( $dir ) {
        if ( ! self::$upload_ticket_no ) {
            return $dir;
        }

        $subdir = '/' . AEGIS_FORMS_UPLOAD_SUBDIR . '/' . self::$upload_ticket_no;
        $dir['subdir'] = $subdir;
        $dir['path'] = $dir['basedir'] . $subdir;
        $dir['url'] = $dir['baseurl'] . $subdir;
        wp_mkdir_p( $dir['path'] );

        return $dir;
    }

    private static function send_notifications( $form_type, $ticket_no, array $data, array $attachments ) {
        $user_subject = sprintf( '[AEGIS] %s request received: %s', ucfirst( $form_type ), $ticket_no );
        $user_body = "Thank you for your submission.\n\n";
        $user_body .= "Ticket: {$ticket_no}\n";
        $user_body .= "We have received your request and will follow up shortly.\n";

        wp_mail( $data['email'], $user_subject, $user_body );

        $admin_recipients = defined( 'AEGIS_FORMS_NOTIFY_TO' ) ? AEGIS_FORMS_NOTIFY_TO : get_option( 'admin_email' );
        $admin_subject = sprintf( '[AEGIS] %s submission: %s', ucfirst( $form_type ), $ticket_no );

        $admin_body_lines = array(
            "Ticket: {$ticket_no}",
            "Type: {$form_type}",
            "Name: {$data['name']}",
            "Email: {$data['email']}",
            "Phone: {$data['phone']}",
            "Country: {$data['country']}",
            "Subject: {$data['subject']}",
        );

        $meta = json_decode( $data['meta'], true );
        if ( is_array( $meta ) ) {
            foreach ( $meta as $key => $value ) {
                if ( '' !== $value ) {
                    $admin_body_lines[] = sprintf( '%s: %s', ucwords( str_replace( '_', ' ', $key ) ), $value );
                }
            }
        }

        if ( ! empty( $attachments ) ) {
            $admin_body_lines[] = 'Attachments: ' . count( $attachments );
        }

        $admin_body_lines[] = "Message:\n" . $data['message'];
        $admin_body_lines[] = "Admin: " . self::admin_url( 'admin.php?page=aegis-forms&ticket=' . rawurlencode( $ticket_no ) );

        wp_mail( $admin_recipients, $admin_subject, implode( "\n", $admin_body_lines ) );
    }

    private static function get_ip_address() {
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return '';
    }

    private static function get_user_agent() {
        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }

        return '';
    }

    private static function rate_limit_key( $ip, $form_type ) {
        return 'aegis_forms_rate_' . md5( $ip . '|' . $form_type );
    }

    private static function redirect_with_message( $state, $msg, $ticket_no = '' ) {
        $redirect = wp_get_referer();
        if ( ! $redirect ) {
            $redirect = home_url( '/' );
        }

        $args = array(
            'aegis_forms' => $state,
            'msg' => $msg,
        );

        if ( 'submitted' === $state && $ticket_no ) {
            $args['ticket'] = $ticket_no;
        }

        $redirect = add_query_arg( $args, $redirect );
        wp_safe_redirect( $redirect );
        exit;
    }

    public static function admin_url( $path ) {
        return is_network_admin() ? network_admin_url( $path ) : admin_url( $path );
    }
}
