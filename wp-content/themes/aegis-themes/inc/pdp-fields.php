<?php
/**
 * PDP custom meta fields for features and specifications.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retrieve PDP meta with fallback keys for backward compatibility.
 *
 * @param int    $post_id       Post ID.
 * @param string $primary_key   Primary meta key.
 * @param array  $fallback_keys Optional fallback keys to check if the primary key is empty.
 *
 * @return string Meta value.
 */
function aegis_pdp_get_meta_value( $post_id, $primary_key, $fallback_keys = array() ) {
    $value = get_post_meta( $post_id, $primary_key, true );

    if ( '' === $value && ! empty( $fallback_keys ) ) {
        foreach ( $fallback_keys as $fallback_key ) {
            $value = get_post_meta( $post_id, $fallback_key, true );

            if ( '' !== $value && null !== $value ) {
                break;
            }
        }
    }

    return is_string( $value ) ? $value : '';
}

/**
 * Register product meta fields for PDP content.
 */
function aegis_pdp_register_meta() {
    register_post_meta(
        'product',
        '_aegis_pdp_features',
        array(
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'auth_callback'     => function () {
                return current_user_can( 'edit_products' );
            },
        )
    );

    register_post_meta(
        'product',
        '_aegis_pdp_specs',
        array(
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'auth_callback'     => function () {
                return current_user_can( 'edit_products' );
            },
        )
    );
}
add_action( 'init', 'aegis_pdp_register_meta' );

/**
 * Add PDP fields meta box to the product edit screen.
 */
function aegis_pdp_fields_add_meta_box() {
    add_meta_box(
        'aegis-pdp-fields',
        __( 'PDP Details', 'aegis-themes' ),
        'aegis_pdp_fields_render_meta_box',
        'product',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'aegis_pdp_fields_add_meta_box' );

/**
 * Render the PDP fields meta box.
 *
 * @param WP_Post $post Current post object.
 */
function aegis_pdp_fields_render_meta_box( $post ) {
    $features = aegis_pdp_get_meta_value( $post->ID, '_aegis_pdp_features', array( 'aegis_pdp_features' ) );
    $specs    = aegis_pdp_get_meta_value( $post->ID, '_aegis_pdp_specs', array( 'aegis_pdp_specs' ) );

    wp_nonce_field( 'aegis_pdp_fields_nonce', 'aegis_pdp_fields_nonce' );
    ?>
    <p>
        <label for="aegis-pdp-features"><strong><?php esc_html_e( 'Features', 'aegis-themes' ); ?></strong></label><br />
        <textarea id="aegis-pdp-features" name="aegis_pdp_features" rows="5" style="width:100%;"><?php echo esc_textarea( $features ); ?></textarea>
        <em><?php esc_html_e( 'Enter one feature per line.', 'aegis-themes' ); ?></em>
    </p>
    <p>
        <label for="aegis-pdp-specs"><strong><?php esc_html_e( 'Specifications', 'aegis-themes' ); ?></strong></label><br />
        <textarea id="aegis-pdp-specs" name="aegis_pdp_specs" rows="5" style="width:100%;"><?php echo esc_textarea( $specs ); ?></textarea>
        <em><?php esc_html_e( 'Use "Key: Value" per line.', 'aegis-themes' ); ?></em>
    </p>
    <?php
}

/**
 * Save PDP meta fields.
 *
 * @param int $post_id Post ID.
 */
function aegis_pdp_fields_save( $post_id ) {
    if ( ! isset( $_POST['aegis_pdp_fields_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['aegis_pdp_fields_nonce'] ), 'aegis_pdp_fields_nonce' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['aegis_pdp_features'] ) ) {
        $features = sanitize_textarea_field( wp_unslash( $_POST['aegis_pdp_features'] ) );
        update_post_meta( $post_id, '_aegis_pdp_features', $features );
    }

    if ( isset( $_POST['aegis_pdp_specs'] ) ) {
        $specs = sanitize_textarea_field( wp_unslash( $_POST['aegis_pdp_specs'] ) );
        update_post_meta( $post_id, '_aegis_pdp_specs', $specs );
    }
}
add_action( 'save_post_product', 'aegis_pdp_fields_save' );
