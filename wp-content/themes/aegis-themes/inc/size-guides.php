<?php

/**
 * Size & Fit Guides: CPT, REST output, and product linkage.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Size Guide custom post type.
 */
add_action( 'init', function () {
    $labels = array(
        'name'          => __( 'Size Guides', 'aegis-themes' ),
        'singular_name' => __( 'Size Guide', 'aegis-themes' ),
        'add_new_item'  => __( 'Add New Size Guide', 'aegis-themes' ),
        'edit_item'     => __( 'Edit Size Guide', 'aegis-themes' ),
        'view_item'     => __( 'View Size Guide', 'aegis-themes' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'supports'           => array( 'title', 'editor' ),
        'capability_type'    => 'post',
        'rewrite'            => array(
            'slug'       => 'size-guide',
            'with_front' => false,
        ),
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-align-full-width',
        'publicly_queryable' => true,
        'has_archive'        => true,
    );

    register_post_type( 'aegis_size_guide', $args );
} );

/**
 * Seed a sample Size Guide once and surface an admin notice.
 */
function aegis_seed_sample_size_guide() {
    if ( ! is_admin() ) {
        return;
    }

    if ( get_option( 'aegis_size_guide_seeded' ) ) {
        return;
    }

    $content = <<<HTML
<h2>Size Chart</h2>
<table>
    <thead>
        <tr>
            <th>Size</th>
            <th>Fits Height</th>
            <th>Shoulder Girth</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Regular</td>
            <td>Up to 183 cm</td>
            <td>160 cm</td>
        </tr>
        <tr>
            <td>Long</td>
            <td>Up to 198 cm</td>
            <td>168 cm</td>
        </tr>
        <tr>
            <td>Wide</td>
            <td>Up to 188 cm</td>
            <td>178 cm</td>
        </tr>
    </tbody>
</table>

<h2>Fit Chart</h2>
<p>[Illustration placeholder showing how the bag should fit around the shoulders, hips, and feet]</p>

<h2>Notes</h2>
<ul>
    <li>Measure with your base layers on to mirror real-world use.</li>
    <li>Choose the roomier option if you prefer extra layers or a looser feel.</li>
    <li>Compressible insulation will settle over time; retest if the bag feels tight.</li>
</ul>

<p><strong>Close hint:</strong> Click the overlay or press Esc to close this guide.</p>
HTML;

    $guide_id = wp_insert_post(
        array(
            'post_title'   => 'Sample: Sleeping Bag Size & Fit Guide',
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'aegis_size_guide',
        )
    );

    if ( $guide_id && ! is_wp_error( $guide_id ) ) {
        update_option( 'aegis_size_guide_seeded', $guide_id );
        update_option( 'aegis_size_guide_seed_notice', (int) $guide_id );
    }
}

add_action( 'admin_init', 'aegis_seed_sample_size_guide' );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    $guide_id = (int) get_option( 'aegis_size_guide_seed_notice' );

    if ( ! $guide_id ) {
        return;
    }

    $edit_link = get_edit_post_link( $guide_id );

    if ( ! $edit_link ) {
        delete_option( 'aegis_size_guide_seed_notice' );
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>';
    printf(
        /* translators: %s: edit link */
        esc_html__( 'A sample Size & Fit Guide was created. %s', 'aegis-themes' ),
        '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit the guide', 'aegis-themes' ) . '</a>'
    );
    echo '</p></div>';

    delete_option( 'aegis_size_guide_seed_notice' );
} );

add_action( 'init', function () {
    if ( get_option( 'aegis_size_guide_rewrite_flushed' ) ) {
        return;
    }

    flush_rewrite_rules( false );
    update_option( 'aegis_size_guide_rewrite_flushed', 1 );
}, 20 );

/**
 * Render the meta box for selecting a Size Guide.
 */
function aegis_render_size_guide_meta_box( $post ) {
    wp_nonce_field( 'aegis_size_guide_nonce', 'aegis_size_guide_nonce_field' );

    $current = (int) get_post_meta( $post->ID, '_aegis_size_guide_id', true );
    $guides  = get_posts(
        array(
            'post_type'      => 'aegis_size_guide',
            'posts_per_page' => 100,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        )
    );

    echo '<label for="aegis-size-guide-select">' . esc_html__( 'Size & Fit Guide', 'aegis-themes' ) . '</label><br />';
    echo '<select id="aegis-size-guide-select" name="aegis_size_guide_id" style="min-width:240px">';
    echo '<option value="">' . esc_html__( 'None', 'aegis-themes' ) . '</option>';

    foreach ( $guides as $guide ) {
        $selected = selected( $current, $guide->ID, false );
        printf(
            '<option value="%1$d" %2$s>%3$s</option>',
            absint( $guide->ID ),
            $selected,
            esc_html( get_the_title( $guide ) )
        );
    }

    echo '</select>';
}

add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'aegis-size-guide-meta',
        __( 'Size & Fit Guide', 'aegis-themes' ),
        'aegis_render_size_guide_meta_box',
        'product',
        'side',
        'default'
    );
} );

/**
 * Save the selected Size Guide ID on product save.
 */
function aegis_save_size_guide_meta( $post_id ) {
    if ( ! isset( $_POST['aegis_size_guide_nonce_field'] ) || ! wp_verify_nonce( wp_unslash( $_POST['aegis_size_guide_nonce_field'] ), 'aegis_size_guide_nonce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( isset( $_POST['post_type'] ) && 'product' !== $_POST['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $guide_id = isset( $_POST['aegis_size_guide_id'] ) ? absint( wp_unslash( $_POST['aegis_size_guide_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

    if ( $guide_id > 0 ) {
        update_post_meta( $post_id, '_aegis_size_guide_id', $guide_id );
    } else {
        delete_post_meta( $post_id, '_aegis_size_guide_id' );
    }
}

add_action( 'save_post_product', 'aegis_save_size_guide_meta', 10, 1 );

/**
 * Helper to read the linked Size Guide ID for the current product page.
 */
function aegis_get_product_size_guide_id( $product_id = 0 ) {
    $product_id = $product_id ? absint( $product_id ) : (int) get_the_ID();

    if ( ! $product_id ) {
        return 0;
    }

    return (int) get_post_meta( $product_id, '_aegis_size_guide_id', true );
}

/**
 * REST endpoint to retrieve a guide's rendered content.
 */
add_action( 'rest_api_init', function () {
    register_rest_route(
        'aegis/v1',
        '/size-guide/(?P<id>\d+)',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => function ( WP_REST_Request $request ) {
                $guide_id = (int) $request['id'];
                $post     = get_post( $guide_id );

                if ( ! $post || 'aegis_size_guide' !== $post->post_type || 'publish' !== $post->post_status ) {
                    return new WP_Error( 'aegis_size_guide_not_found', __( 'Guide not found.', 'aegis-themes' ), array( 'status' => 404 ) );
                }

                $content = apply_filters( 'the_content', $post->post_content );

                return rest_ensure_response(
                    array(
                        'id'      => $post->ID,
                        'title'   => get_the_title( $post ),
                        'content' => wp_kses_post( $content ),
                    )
                );
            },
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'description' => __( 'Size guide ID.', 'aegis-themes' ),
                    'type'        => 'integer',
                    'required'    => true,
                    'minimum'     => 1,
                ),
            ),
        )
    );
} );
