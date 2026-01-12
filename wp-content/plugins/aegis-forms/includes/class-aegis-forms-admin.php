<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aegis_Forms_Admin {
    public static function init() {
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', array( __CLASS__, 'register_menu' ) );
        } else {
            add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        }
    }

    public static function register_menu() {
        if ( is_multisite() && ! is_super_admin() ) {
            return;
        }

        $capability = Aegis_Forms::capability();
        $parent_slug = self::find_aegis_parent();

        if ( $parent_slug ) {
            add_submenu_page(
                $parent_slug,
                __( 'Aegis Forms', 'aegis-forms' ),
                __( 'Aegis Forms', 'aegis-forms' ),
                $capability,
                'aegis-forms',
                array( __CLASS__, 'render_list_page' )
            );
        } elseif ( is_network_admin() ) {
            add_submenu_page(
                'settings.php',
                __( 'Aegis Forms', 'aegis-forms' ),
                __( 'Aegis Forms', 'aegis-forms' ),
                $capability,
                'aegis-forms',
                array( __CLASS__, 'render_list_page' )
            );
        } else {
            add_management_page(
                __( 'Aegis Forms', 'aegis-forms' ),
                __( 'Aegis Forms', 'aegis-forms' ),
                $capability,
                'aegis-forms',
                array( __CLASS__, 'render_list_page' )
            );
        }

        add_submenu_page(
            null,
            __( 'View Submission', 'aegis-forms' ),
            __( 'View Submission', 'aegis-forms' ),
            $capability,
            'aegis-forms-view',
            array( __CLASS__, 'render_view_page' )
        );
    }

    private static function find_aegis_parent() {
        global $menu;
        $candidates = array( 'aegis-system', 'aegis-hero' );

        foreach ( $menu as $menu_item ) {
            if ( ! isset( $menu_item[2] ) ) {
                continue;
            }
            if ( in_array( $menu_item[2], $candidates, true ) ) {
                return $menu_item[2];
            }
        }

        return '';
    }

    public static function render_list_page() {
        Aegis_Forms::must_have_access();

        $filters = Aegis_Forms::sanitize_filters();
        $per_page = 50;
        $paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
        $offset = ( $paged - 1 ) * $per_page;

        Aegis_Forms::maybe_install_schema();
        $table_ready = Aegis_Forms::table_exists();

        if ( ! $table_ready ) {
            $error_details = get_option( Aegis_Forms::OPTION_INSTALL_ERROR );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Aegis Forms Submissions', 'aegis-forms' ); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e( 'The submissions table is not ready. Please re-activate the plugin or check installation errors.', 'aegis-forms' ); ?></p>
                    <?php if ( $error_details ) : ?>
                        <p><?php echo esc_html( $error_details ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return;
        }

        $total = Aegis_Forms::count_submissions( $filters );
        $submissions = Aegis_Forms::get_submissions( $filters, $per_page, $offset );

        if ( self::find_aegis_parent() ) {
            $base_url = Aegis_Forms::admin_url( 'admin.php?page=aegis-forms' );
        } elseif ( is_network_admin() ) {
            $base_url = Aegis_Forms::admin_url( 'settings.php?page=aegis-forms' );
        } else {
            $base_url = Aegis_Forms::admin_url( 'tools.php?page=aegis-forms' );
        }
        $export_url = Aegis_Forms::admin_url( 'admin-post.php' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Aegis Forms Submissions', 'aegis-forms' ); ?></h1>

            <form method="get" action="<?php echo esc_url( $base_url ); ?>">
                <input type="hidden" name="page" value="aegis-forms" />
                <?php if ( ! empty( $filters['ticket'] ) ) : ?>
                    <input type="hidden" name="ticket" value="<?php echo esc_attr( $filters['ticket'] ); ?>" />
                <?php endif; ?>
                <label for="aegis-forms-type"><?php esc_html_e( 'Type', 'aegis-forms' ); ?></label>
                <select name="type" id="aegis-forms-type">
                    <option value=""><?php esc_html_e( 'All', 'aegis-forms' ); ?></option>
                    <?php foreach ( Aegis_Forms::allowed_types() as $type ) : ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['type'], $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="aegis-forms-status"><?php esc_html_e( 'Status', 'aegis-forms' ); ?></label>
                <select name="status" id="aegis-forms-status">
                    <option value=""><?php esc_html_e( 'All', 'aegis-forms' ); ?></option>
                    <?php foreach ( Aegis_Forms::allowed_statuses() as $status ) : ?>
                        <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filters['status'], $status ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="button"><?php esc_html_e( 'Filter', 'aegis-forms' ); ?></button>
            </form>

            <form method="post" action="<?php echo esc_url( $export_url ); ?>" style="margin-top:16px;">
                <?php wp_nonce_field( 'aegis_forms_export' ); ?>
                <input type="hidden" name="action" value="aegis_forms_export" />
                <input type="hidden" name="type" value="<?php echo esc_attr( $filters['type'] ); ?>" />
                <input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
                <input type="hidden" name="ticket" value="<?php echo esc_attr( $filters['ticket'] ); ?>" />
                <button class="button button-secondary"><?php esc_html_e( 'Export CSV', 'aegis-forms' ); ?></button>
            </form>

            <table class="widefat striped" style="margin-top:16px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Ticket', 'aegis-forms' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'aegis-forms' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'aegis-forms' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'aegis-forms' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'aegis-forms' ); ?></th>
                        <th><?php esc_html_e( 'Created At', 'aegis-forms' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'aegis-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $submissions ) ) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No submissions found.', 'aegis-forms' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $submissions as $submission ) : ?>
                            <tr>
                                <td><?php echo esc_html( $submission->ticket_no ); ?></td>
                                <td><?php echo esc_html( ucfirst( $submission->type ) ); ?></td>
                                <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $submission->status ) ) ); ?></td>
                                <td><?php echo esc_html( $submission->name ); ?></td>
                                <td><?php echo esc_html( $submission->email ); ?></td>
                                <td><?php echo esc_html( $submission->created_at ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( Aegis_Forms::admin_url( 'admin.php?page=aegis-forms-view&submission_id=' . (int) $submission->id ) ); ?>">
                                        <?php esc_html_e( 'View', 'aegis-forms' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $total_pages = (int) ceil( $total / $per_page );
            if ( $total_pages > 1 ) :
                $page_links = paginate_links(
                    array(
                        'base' => add_query_arg( 'paged', '%#%', $base_url ),
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'add_args' => array_filter(
                            array(
                                'type' => $filters['type'],
                                'status' => $filters['status'],
                                'ticket' => $filters['ticket'],
                            )
                        ),
                    )
                );
                ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php echo wp_kses_post( $page_links ); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_view_page() {
        Aegis_Forms::must_have_access();

        $submission_id = isset( $_GET['submission_id'] ) ? (int) $_GET['submission_id'] : 0;
        if ( ! $submission_id ) {
            wp_die( esc_html__( 'Missing submission ID.', 'aegis-forms' ) );
        }

        global $wpdb;
        $table_name = Aegis_Forms::table_name();
        $submission = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $submission_id ) );

        if ( ! $submission ) {
            wp_die( esc_html__( 'Submission not found.', 'aegis-forms' ) );
        }

        $attachments = array();
        if ( $submission->attachments ) {
            $decoded = json_decode( $submission->attachments, true );
            if ( is_array( $decoded ) ) {
                $attachments = $decoded;
            }
        }

        $upload_dir = wp_upload_dir();
        $meta = $submission->meta ? json_decode( $submission->meta, true ) : array();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $submission->ticket_no ); ?></h1>

            <table class="widefat striped" style="max-width:800px;">
                <tbody>
                    <tr><th><?php esc_html_e( 'Type', 'aegis-forms' ); ?></th><td><?php echo esc_html( ucfirst( $submission->type ) ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Status', 'aegis-forms' ); ?></th><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $submission->status ) ) ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Name', 'aegis-forms' ); ?></th><td><?php echo esc_html( $submission->name ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Email', 'aegis-forms' ); ?></th><td><?php echo esc_html( $submission->email ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Phone', 'aegis-forms' ); ?></th><td><?php echo esc_html( $submission->phone ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Country', 'aegis-forms' ); ?></th><td><?php echo esc_html( $submission->country ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Subject', 'aegis-forms' ); ?></th><td><?php echo esc_html( $submission->subject ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Message', 'aegis-forms' ); ?></th><td><?php echo nl2br( esc_html( $submission->message ) ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Meta', 'aegis-forms' ); ?></th><td><?php echo esc_html( wp_json_encode( $meta ) ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Created At', 'aegis-forms' ); ?></th><td><?php echo esc_html( $submission->created_at ); ?></td></tr>
                    <tr><th><?php esc_html_e( 'Updated At', 'aegis-forms' ); ?></th><td><?php echo esc_html( $submission->updated_at ); ?></td></tr>
                </tbody>
            </table>

            <h2 style="margin-top:24px;"><?php esc_html_e( 'Attachments', 'aegis-forms' ); ?></h2>
            <?php if ( empty( $attachments ) ) : ?>
                <p><?php esc_html_e( 'No attachments.', 'aegis-forms' ); ?></p>
            <?php else : ?>
                <ul>
                    <?php foreach ( $attachments as $path ) : ?>
                        <li>
                            <a href="<?php echo esc_url( $upload_dir['baseurl'] . '/' . ltrim( $path, '/' ) ); ?>">
                                <?php echo esc_html( basename( $path ) ); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h2 style="margin-top:24px;"><?php esc_html_e( 'Update Status', 'aegis-forms' ); ?></h2>
            <form method="post" action="<?php echo esc_url( Aegis_Forms::admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'aegis_forms_update' ); ?>
                <input type="hidden" name="action" value="aegis_forms_update" />
                <input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission_id ); ?>" />

                <p>
                    <label for="aegis-forms-status-update"><?php esc_html_e( 'Status', 'aegis-forms' ); ?></label>
                    <select name="status" id="aegis-forms-status-update">
                        <?php foreach ( Aegis_Forms::allowed_statuses() as $status ) : ?>
                            <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $submission->status, $status ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="aegis-forms-admin-notes"><?php esc_html_e( 'Admin Notes', 'aegis-forms' ); ?></label>
                    <textarea name="admin_notes" id="aegis-forms-admin-notes" rows="5" class="large-text"><?php echo esc_textarea( $submission->admin_notes ); ?></textarea>
                </p>
                <p>
                    <button class="button button-primary"><?php esc_html_e( 'Save', 'aegis-forms' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
}
