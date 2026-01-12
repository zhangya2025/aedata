<?php

function aegis_register_faq_library_cpt() {
    $labels = array(
        'name'                  => __( 'FAQ Library', 'aegis' ),
        'singular_name'         => __( 'FAQ', 'aegis' ),
        'menu_name'             => __( 'FAQ Library', 'aegis' ),
        'name_admin_bar'        => __( 'FAQ', 'aegis' ),
        'add_new'               => __( 'Add New', 'aegis' ),
        'add_new_item'          => __( 'Add New FAQ', 'aegis' ),
        'new_item'              => __( 'New FAQ', 'aegis' ),
        'edit_item'             => __( 'Edit FAQ', 'aegis' ),
        'view_item'             => __( 'View FAQ', 'aegis' ),
        'all_items'             => __( 'All FAQs', 'aegis' ),
        'search_items'          => __( 'Search FAQs', 'aegis' ),
        'not_found'             => __( 'No FAQs found.', 'aegis' ),
        'not_found_in_trash'    => __( 'No FAQs found in Trash.', 'aegis' ),
        'item_published'        => __( 'FAQ published.', 'aegis' ),
        'item_updated'          => __( 'FAQ updated.', 'aegis' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 22,
        'menu_icon'          => 'dashicons-editor-help',
        'supports'           => array( 'title', 'editor' ),
        'show_in_rest'       => true,
        'publicly_queryable' => true,
        'exclude_from_search'=> true,
        'has_archive'        => true,
        'rewrite'            => array(
            'slug'       => 'faq-library',
            'with_front' => false,
        ),
    );

    register_post_type( 'aegis_faq', $args );
}
add_action( 'init', 'aegis_register_faq_library_cpt' );

function aegis_seed_faq_library() {
    if ( ! is_admin() || get_option( 'aegis_faq_seeded' ) ) {
        return;
    }

    if ( ! post_type_exists( 'aegis_faq' ) ) {
        return;
    }

    $seed_items = array(
        array(
            'title'   => 'What temperatures is this sleeping bag rated for?',
            'answer'  => 'Our sleeping bags are designed for three-season adventures and are tested to keep you warm on cool spring and fall nights. Always layer with appropriate sleepwear for extra warmth.',
        ),
        array(
            'title'   => 'Can I wash the sleeping bag in a machine?',
            'answer'  => 'Yes. Use a gentle cycle with cold water and mild detergent. Tumble dry on low with clean tennis balls to restore loft.',
        ),
        array(
            'title'   => 'Is the sleeping bag good for backpacking?',
            'answer'  => 'The bag packs down compactly and is lightweight enough for backpacking trips where space and weight matter most.',
        ),
        array(
            'title'   => 'What size sleeping bag should I choose?',
            'answer'  => 'Choose the size that matches your height and preferred fit. If you want extra room to move, size up for a more relaxed feel.',
        ),
        array(
            'title'   => 'Does the sleeping bag come with a stuff sack?',
            'answer'  => 'Every sleeping bag ships with a compression stuff sack to make packing easy.',
        ),
        array(
            'title'   => 'How do I store the sleeping bag between trips?',
            'answer'  => 'Store the bag uncompressed in a breathable storage sack to preserve the insulation loft.',
        ),
    );

    foreach ( $seed_items as $item ) {
        wp_insert_post(
            array(
                'post_type'   => 'aegis_faq',
                'post_title'  => $item['title'],
                'post_status' => 'publish',
                'post_content'=> $item['answer'],
            )
        );
    }

    update_option( 'aegis_faq_seeded', 1 );
    set_transient( 'aegis_faq_seed_notice', 1, MINUTE_IN_SECONDS * 2 );
}
add_action( 'admin_init', 'aegis_seed_faq_library' );

function aegis_faq_seed_notice() {
    if ( ! is_admin() || ! get_transient( 'aegis_faq_seed_notice' ) ) {
        return;
    }

    delete_transient( 'aegis_faq_seed_notice' );
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo esc_html__( 'Placeholder FAQs have been generated. You can edit them in FAQ Library.', 'aegis' );
    echo '</p></div>';
}
add_action( 'admin_notices', 'aegis_faq_seed_notice' );

function aegis_add_product_faq_metabox() {
    add_meta_box(
        'aegis_faq_selector',
        __( 'FAQ (optional)', 'aegis' ),
        'aegis_render_product_faq_metabox',
        'product',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'aegis_add_product_faq_metabox' );

function aegis_render_product_faq_metabox( $post ) {
    wp_nonce_field( 'aegis_faq_meta_save', 'aegis_faq_meta_nonce' );

    $selected = get_post_meta( $post->ID, '_aegis_faq_ids', true );
    if ( ! is_array( $selected ) ) {
        $selected = array_filter( array_map( 'absint', explode( ',', (string) $selected ) ) );
    }

    $selected = array_values( array_unique( array_map( 'absint', $selected ) ) );
    $faqs = get_posts(
        array(
            'post_type'      => 'aegis_faq',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $selected_count = count( $selected );

    echo '<p>' . esc_html__( 'Select FAQs to display on the product page.', 'aegis' ) . '</p>';
    echo '<div class="aegis-faq-picker" data-selected-count="' . esc_attr( $selected_count ) . '">';
    echo '<input type="search" class="aegis-faq-picker__search" placeholder="' . esc_attr__( 'Search FAQs…', 'aegis' ) . '" aria-label="' . esc_attr__( 'Search FAQs', 'aegis' ) . '" style="width:100%; margin: 6px 0 10px;">';
    echo '<div class="aegis-faq-picker__count" style="margin-bottom: 8px;">' . esc_html__( 'Selected:', 'aegis' ) . ' <span>' . esc_html( (string) $selected_count ) . '</span></div>';
    echo '<div class="aegis-faq-picker__list" role="list" style="max-height: 280px; overflow-y: auto; border: 1px solid #d1d5db; padding: 8px;">';

    foreach ( $faqs as $faq ) {
        $is_checked = in_array( $faq->ID, $selected, true );
        printf(
            '<label class="aegis-faq-picker__item" role="listitem"><input type="checkbox" name="aegis_faq_ids[]" value="%1$d"%2$s> <span class="aegis-faq-picker__label">%3$s</span></label>',
            absint( $faq->ID ),
            checked( $is_checked, true, false ),
            esc_html( get_the_title( $faq ) )
        );
    }

    echo '</div>';
    echo '</div>';
}

function aegis_save_product_faq_meta( $post_id ) {
    if ( ! isset( $_POST['aegis_faq_meta_nonce'] ) || ! wp_verify_nonce( $_POST['aegis_faq_meta_nonce'], 'aegis_faq_meta_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_product', $post_id ) ) {
        return;
    }

    $faq_ids = array();
    if ( isset( $_POST['aegis_faq_ids'] ) && is_array( $_POST['aegis_faq_ids'] ) ) {
        $faq_ids = array_values( array_filter( array_map( 'absint', $_POST['aegis_faq_ids'] ) ) );
    }

    if ( empty( $faq_ids ) ) {
        delete_post_meta( $post_id, '_aegis_faq_ids' );
        return;
    }

    update_post_meta( $post_id, '_aegis_faq_ids', $faq_ids );
}
add_action( 'save_post_product', 'aegis_save_product_faq_meta' );

function aegis_pdp_faq_shortcode() {
    if ( ! is_singular( 'product' ) ) {
        return '';
    }

    $product_id = get_the_ID();
    if ( ! $product_id ) {
        return '';
    }

    $faq_ids = get_post_meta( $product_id, '_aegis_faq_ids', true );
    if ( empty( $faq_ids ) ) {
        return '';
    }

    $faq_ids = array_values( array_filter( array_map( 'absint', (array) $faq_ids ) ) );
    if ( empty( $faq_ids ) ) {
        return '';
    }

    $faqs = get_posts(
        array(
            'post_type'      => 'aegis_faq',
            'posts_per_page' => -1,
            'post__in'       => $faq_ids,
            'orderby'        => 'post__in',
        )
    );

    if ( empty( $faqs ) ) {
        return '';
    }

    ob_start();
    ?>
    <div class="aegis-wc-module aegis-wc-module--faq">
        <h3><?php echo esc_html__( 'FAQ', 'aegis' ); ?></h3>
        <div class="aegis-wc-faq">
            <?php foreach ( $faqs as $faq ) : ?>
                <details class="aegis-wc-faq__item">
                    <summary><?php echo esc_html( get_the_title( $faq ) ); ?></summary>
                    <div class="aegis-wc-faq__answer">
                        <?php echo wp_kses_post( apply_filters( 'the_content', $faq->post_content ) ); ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'aegis_pdp_faq', 'aegis_pdp_faq_shortcode' );
