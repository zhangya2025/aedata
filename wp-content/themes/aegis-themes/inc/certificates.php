<?php

function aegis_register_certificates_cpt() {
    $labels = array(
        'name'               => __( 'Certificates', 'aegis' ),
        'singular_name'      => __( 'Certificate', 'aegis' ),
        'menu_name'          => __( 'Certificates', 'aegis' ),
        'name_admin_bar'     => __( 'Certificate', 'aegis' ),
        'add_new'            => __( 'Add New', 'aegis' ),
        'add_new_item'       => __( 'Add New Certificate', 'aegis' ),
        'new_item'           => __( 'New Certificate', 'aegis' ),
        'edit_item'          => __( 'Edit Certificate', 'aegis' ),
        'view_item'          => __( 'View Certificate', 'aegis' ),
        'all_items'          => __( 'All Certificates', 'aegis' ),
        'search_items'       => __( 'Search Certificates', 'aegis' ),
        'not_found'          => __( 'No certificates found.', 'aegis' ),
        'not_found_in_trash' => __( 'No certificates found in Trash.', 'aegis' ),
        'item_published'     => __( 'Certificate published.', 'aegis' ),
        'item_updated'       => __( 'Certificate updated.', 'aegis' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 24,
        'menu_icon'          => 'dashicons-awards',
        'supports'           => array( 'title', 'editor' ),
        'show_in_rest'       => true,
        'publicly_queryable' => true,
        'exclude_from_search'=> true,
        'has_archive'        => true,
        'rewrite'            => array(
            'slug'       => 'certificates',
            'with_front' => false,
        ),
    );

    register_post_type( 'aegis_certificate', $args );
}
add_action( 'init', 'aegis_register_certificates_cpt' );

function aegis_seed_certificates() {
    if ( ! is_admin() || get_option( 'aegis_certificate_seeded' ) ) {
        return;
    }

    if ( ! post_type_exists( 'aegis_certificate' ) ) {
        return;
    }

    $seed_items = array(
        array(
            'title' => 'Down Fill Power Verification',
            'type'  => 'IDFL',
            'note'  => 'Verification report for premium down fill power performance.',
        ),
        array(
            'title' => 'Materials Safety Compliance',
            'type'  => 'SGS',
            'note'  => 'Independent lab report confirming material safety standards.',
        ),
        array(
            'title' => 'Quality Assurance Statement',
            'type'  => 'OTHER',
            'note'  => 'Manufacturer quality assurance documentation for production runs.',
        ),
    );

    foreach ( $seed_items as $item ) {
        $cert_id = wp_insert_post(
            array(
                'post_type'    => 'aegis_certificate',
                'post_title'   => $item['title'],
                'post_status'  => 'publish',
                'post_content' => $item['note'],
            )
        );

        if ( $cert_id && ! is_wp_error( $cert_id ) ) {
            update_post_meta( $cert_id, '_aegis_certificate_type', $item['type'] );
        }
    }

    update_option( 'aegis_certificate_seeded', 1 );
    set_transient( 'aegis_certificate_seed_notice', 1, MINUTE_IN_SECONDS * 2 );
}
add_action( 'admin_init', 'aegis_seed_certificates' );

function aegis_certificate_seed_notice() {
    if ( ! is_admin() || ! get_transient( 'aegis_certificate_seed_notice' ) ) {
        return;
    }

    delete_transient( 'aegis_certificate_seed_notice' );
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo esc_html__( 'Placeholder certificates have been generated. You can edit them in Certificates.', 'aegis' );
    echo '</p></div>';
}
add_action( 'admin_notices', 'aegis_certificate_seed_notice' );

function aegis_add_certificate_metaboxes() {
    add_meta_box(
        'aegis_certificate_details',
        __( 'Certificate Details', 'aegis' ),
        'aegis_render_certificate_details_metabox',
        'aegis_certificate',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes_aegis_certificate', 'aegis_add_certificate_metaboxes' );

function aegis_render_certificate_details_metabox( $post ) {
    wp_nonce_field( 'aegis_certificate_meta_save', 'aegis_certificate_meta_nonce' );

    $type = get_post_meta( $post->ID, '_aegis_certificate_type', true );
    $file_id = absint( get_post_meta( $post->ID, '_aegis_certificate_file_id', true ) );
    $file_name = $file_id ? basename( get_attached_file( $file_id ) ) : '';

    echo '<p><label for="aegis_certificate_type"><strong>' . esc_html__( 'Type', 'aegis' ) . '</strong></label></p>';
    echo '<select id="aegis_certificate_type" name="aegis_certificate_type">';
    foreach ( array( 'SGS', 'IDFL', 'OTHER' ) as $option ) {
        printf(
            '<option value="%1$s"%2$s>%3$s</option>',
            esc_attr( $option ),
            selected( $type, $option, false ),
            esc_html( $option )
        );
    }
    echo '</select>';

    echo '<hr style="margin:16px 0;">';
    echo '<p><strong>' . esc_html__( 'Certificate PDF', 'aegis' ) . '</strong></p>';
    echo '<input type="hidden" id="aegis_certificate_file_id" name="aegis_certificate_file_id" value="' . esc_attr( $file_id ) . '">';
    echo '<div class="aegis-certificate-file-name" style="margin-bottom:8px;">' . esc_html( $file_name ) . '</div>';
    echo '<button type="button" class="button" id="aegis_certificate_file_select">' . esc_html__( 'Select PDF', 'aegis' ) . '</button> ';
    echo '<button type="button" class="button button-link-delete" id="aegis_certificate_file_remove">' . esc_html__( 'Remove', 'aegis' ) . '</button>';

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectBtn = document.getElementById('aegis_certificate_file_select');
            const removeBtn = document.getElementById('aegis_certificate_file_remove');
            const fileInput = document.getElementById('aegis_certificate_file_id');
            const fileName = document.querySelector('.aegis-certificate-file-name');
            if (!selectBtn || !fileInput || !fileName) {
                return;
            }

            let frame = null;
            selectBtn.addEventListener('click', function (event) {
                event.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: 'Select certificate PDF',
                    button: { text: 'Use this PDF' },
                    library: { type: 'application/pdf' },
                    multiple: false,
                });

                frame.on('select', function () {
                    const attachment = frame.state().get('selection').first();
                    if (!attachment) {
                        return;
                    }
                    const data = attachment.toJSON();
                    fileInput.value = data.id || '';
                    fileName.textContent = data.filename || '';
                });

                frame.open();
            });

            if (removeBtn) {
                removeBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    fileInput.value = '';
                    fileName.textContent = '';
                });
            }
        });
    </script>
    <?php
}

function aegis_save_certificate_meta( $post_id ) {
    if ( ! isset( $_POST['aegis_certificate_meta_nonce'] ) || ! wp_verify_nonce( $_POST['aegis_certificate_meta_nonce'], 'aegis_certificate_meta_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $type = isset( $_POST['aegis_certificate_type'] ) ? sanitize_text_field( wp_unslash( $_POST['aegis_certificate_type'] ) ) : '';
    $allowed_types = array( 'SGS', 'IDFL', 'OTHER' );
    if ( ! in_array( $type, $allowed_types, true ) ) {
        $type = 'OTHER';
    }
    update_post_meta( $post_id, '_aegis_certificate_type', $type );

    $file_id = isset( $_POST['aegis_certificate_file_id'] ) ? absint( $_POST['aegis_certificate_file_id'] ) : 0;
    if ( $file_id ) {
        update_post_meta( $post_id, '_aegis_certificate_file_id', $file_id );
    } else {
        delete_post_meta( $post_id, '_aegis_certificate_file_id' );
    }
}
add_action( 'save_post_aegis_certificate', 'aegis_save_certificate_meta' );

function aegis_add_product_certificate_metabox() {
    add_meta_box(
        'aegis_certificate_selector',
        __( 'Certificates (optional)', 'aegis' ),
        'aegis_render_product_certificate_metabox',
        'product',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'aegis_add_product_certificate_metabox' );

function aegis_render_product_certificate_metabox( $post ) {
    wp_nonce_field( 'aegis_certificate_product_meta_save', 'aegis_certificate_product_meta_nonce' );

    $selected = get_post_meta( $post->ID, '_aegis_certificate_ids', true );
    if ( ! is_array( $selected ) ) {
        $selected = array_filter( array_map( 'absint', explode( ',', (string) $selected ) ) );
    }

    $selected = array_values( array_unique( array_map( 'absint', $selected ) ) );
    $certificates = get_posts(
        array(
            'post_type'      => 'aegis_certificate',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $selected_count = count( $selected );

    echo '<p>' . esc_html__( 'Select certificates to display on the product page.', 'aegis' ) . '</p>';
    echo '<div class="aegis-certificate-picker" data-selected-count="' . esc_attr( $selected_count ) . '">';
    echo '<input type="search" class="aegis-certificate-picker__search" placeholder="' . esc_attr__( 'Search certificates…', 'aegis' ) . '" aria-label="' . esc_attr__( 'Search certificates', 'aegis' ) . '" style="width:100%; margin: 6px 0 10px;">';
    echo '<div class="aegis-certificate-picker__count" style="margin-bottom: 8px;">' . esc_html__( 'Selected:', 'aegis' ) . ' <span>' . esc_html( (string) $selected_count ) . '</span></div>';
    echo '<div class="aegis-certificate-picker__list" role="list" style="max-height: 280px; overflow-y: auto; border: 1px solid #d1d5db; padding: 8px;">';

    foreach ( $certificates as $certificate ) {
        $is_checked = in_array( $certificate->ID, $selected, true );
        $title = trim( wp_strip_all_tags( get_the_title( $certificate ) ) );
        $type = get_post_meta( $certificate->ID, '_aegis_certificate_type', true );
        $label = trim( sprintf( '%s - %s', $type ? $type : 'OTHER', $title ) );
        printf(
            '<label class="aegis-certificate-picker__item" role="listitem"><input type="checkbox" name="aegis_certificate_ids[]" value="%1$d"%2$s> <span class="aegis-certificate-picker__label">%3$s</span></label>',
            absint( $certificate->ID ),
            checked( $is_checked, true, false ),
            esc_html( $label )
        );
    }

    echo '</div>';
    echo '</div>';
}

function aegis_save_product_certificate_meta( $post_id ) {
    if ( ! isset( $_POST['aegis_certificate_product_meta_nonce'] ) || ! wp_verify_nonce( $_POST['aegis_certificate_product_meta_nonce'], 'aegis_certificate_product_meta_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_product', $post_id ) ) {
        return;
    }

    $certificate_ids = array();
    if ( isset( $_POST['aegis_certificate_ids'] ) && is_array( $_POST['aegis_certificate_ids'] ) ) {
        $certificate_ids = array_values( array_filter( array_map( 'absint', $_POST['aegis_certificate_ids'] ) ) );
    }

    if ( empty( $certificate_ids ) ) {
        delete_post_meta( $post_id, '_aegis_certificate_ids' );
        return;
    }

    update_post_meta( $post_id, '_aegis_certificate_ids', $certificate_ids );
}
add_action( 'save_post_product', 'aegis_save_product_certificate_meta' );

function aegis_pdp_certificates_shortcode() {
    if ( ! is_singular( 'product' ) ) {
        return '';
    }

    $product_id = get_the_ID();
    if ( ! $product_id ) {
        return '';
    }

    $certificate_ids = get_post_meta( $product_id, '_aegis_certificate_ids', true );
    if ( empty( $certificate_ids ) ) {
        return '';
    }

    $certificate_ids = array_values( array_filter( array_map( 'absint', (array) $certificate_ids ) ) );
    if ( empty( $certificate_ids ) ) {
        return '';
    }

    $certificates = get_posts(
        array(
            'post_type'      => 'aegis_certificate',
            'posts_per_page' => -1,
            'post__in'       => $certificate_ids,
            'orderby'        => 'post__in',
        )
    );

    if ( empty( $certificates ) ) {
        return '';
    }

    $rows = '';
    foreach ( $certificates as $certificate ) {
        $title = trim( wp_strip_all_tags( get_the_title( $certificate ) ) );
        $type = get_post_meta( $certificate->ID, '_aegis_certificate_type', true );
        $type = $type ? $type : 'OTHER';
        $rows .= sprintf(
            '<div class="aegis-certificate-row"><span class="aegis-certificate-row__type">%1$s</span><span class="aegis-certificate-row__title">%2$s</span><button type="button" class="aegis-certificate-row__view" data-cert-id="%3$d">%4$s</button></div>',
            esc_html( $type ),
            esc_html( $title ),
            absint( $certificate->ID ),
            esc_html__( 'View', 'aegis' )
        );
    }

    return sprintf(
        '<div class="aegis-wc-module aegis-wc-module--certificates"><h3>%1$s</h3><div class="aegis-certificates">%2$s</div></div>',
        esc_html__( 'Certificates', 'aegis' ),
        $rows
    );
}
add_shortcode( 'aegis_pdp_certificates', 'aegis_pdp_certificates_shortcode' );

function aegis_register_certificate_rest_routes() {
    register_rest_route(
        'aegis/v1',
        '/certificate-file/(?P<id>\d+)',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'aegis_stream_certificate_file',
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'validate_callback' => 'is_numeric',
                ),
            ),
        )
    );
}
add_action( 'rest_api_init', 'aegis_register_certificate_rest_routes' );

function aegis_stream_certificate_file( WP_REST_Request $request ) {
    $cert_id = absint( $request['id'] );
    if ( ! $cert_id || 'aegis_certificate' !== get_post_type( $cert_id ) ) {
        return new WP_Error( 'aegis_certificate_not_found', __( 'Certificate not found.', 'aegis' ), array( 'status' => 404 ) );
    }

    $file_id = absint( get_post_meta( $cert_id, '_aegis_certificate_file_id', true ) );
    if ( ! $file_id ) {
        return new WP_Error( 'aegis_certificate_missing', __( 'Certificate file not available.', 'aegis' ), array( 'status' => 404 ) );
    }

    $file_path = get_attached_file( $file_id );
    if ( ! $file_path || ! file_exists( $file_path ) ) {
        return new WP_Error( 'aegis_certificate_missing', __( 'Certificate file not available.', 'aegis' ), array( 'status' => 404 ) );
    }

    if ( ob_get_length() ) {
        ob_end_clean();
    }

    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="certificate.pdf"' );
    header( 'X-Robots-Tag: noindex, nofollow', true );
    header( 'Content-Length: ' . filesize( $file_path ) );
    readfile( $file_path );
    exit;
}
