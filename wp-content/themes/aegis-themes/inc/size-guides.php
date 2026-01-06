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
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'supports'           => array( 'title', 'editor' ),
        'capability_type'    => 'post',
        'rewrite'            => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-align-full-width',
        'publicly_queryable' => false,
    );

    register_post_type( 'aegis_size_guide', $args );
} );

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
