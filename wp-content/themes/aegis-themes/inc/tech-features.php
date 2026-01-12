<?php

function aegis_register_tech_features_cpt() {
    $labels = array(
        'name'               => __( 'Tech Features', 'aegis' ),
        'singular_name'      => __( 'Tech Feature', 'aegis' ),
        'menu_name'          => __( 'Tech Features', 'aegis' ),
        'name_admin_bar'     => __( 'Tech Feature', 'aegis' ),
        'add_new'            => __( 'Add New', 'aegis' ),
        'add_new_item'       => __( 'Add New Tech Feature', 'aegis' ),
        'new_item'           => __( 'New Tech Feature', 'aegis' ),
        'edit_item'          => __( 'Edit Tech Feature', 'aegis' ),
        'view_item'          => __( 'View Tech Feature', 'aegis' ),
        'all_items'          => __( 'All Tech Features', 'aegis' ),
        'search_items'       => __( 'Search Tech Features', 'aegis' ),
        'not_found'          => __( 'No tech features found.', 'aegis' ),
        'not_found_in_trash' => __( 'No tech features found in Trash.', 'aegis' ),
        'item_published'     => __( 'Tech Feature published.', 'aegis' ),
        'item_updated'       => __( 'Tech Feature updated.', 'aegis' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 23,
        'menu_icon'          => 'dashicons-admin-tools',
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
        'show_in_rest'       => true,
        'publicly_queryable' => true,
        'exclude_from_search'=> true,
        'has_archive'        => true,
        'rewrite'            => array(
            'slug'       => 'tech-features',
            'with_front' => false,
        ),
    );

    register_post_type( 'aegis_tech_feature', $args );
}
add_action( 'init', 'aegis_register_tech_features_cpt' );

function aegis_seed_tech_features() {
    if ( ! is_admin() || get_option( 'aegis_tech_seeded' ) ) {
        return;
    }

    if ( ! post_type_exists( 'aegis_tech_feature' ) ) {
        return;
    }

    $seed_items = array(
        array(
            'title'  => '800FP Down',
            'answer' => 'Premium 800 fill-power down delivers exceptional warmth-to-weight performance for cold nights.',
        ),
        array(
            'title'  => 'Water Repellent Shell',
            'answer' => 'Durable water-repellent shell sheds light moisture and helps keep insulation dry.',
        ),
        array(
            'title'  => 'YKK Zipper',
            'answer' => 'Smooth-gliding YKK zipper resists snags for easy entry and exit.',
        ),
        array(
            'title'  => 'Draft Collar',
            'answer' => 'Insulated draft collar seals in heat and blocks cold air while you sleep.',
        ),
        array(
            'title'  => 'Compression Sack',
            'answer' => 'Included compression sack helps reduce pack size for travel or backpacking.',
        ),
        array(
            'title'  => 'Temperature Rating',
            'answer' => 'Tested temperature rating guides which conditions this bag is built to handle.',
        ),
    );

    foreach ( $seed_items as $item ) {
        wp_insert_post(
            array(
                'post_type'    => 'aegis_tech_feature',
                'post_title'   => $item['title'],
                'post_status'  => 'publish',
                'post_content' => $item['answer'],
            )
        );
    }

    update_option( 'aegis_tech_seeded', 1 );
    set_transient( 'aegis_tech_seed_notice', 1, MINUTE_IN_SECONDS * 2 );
}
add_action( 'admin_init', 'aegis_seed_tech_features' );

function aegis_tech_seed_notice() {
    if ( ! is_admin() || ! get_transient( 'aegis_tech_seed_notice' ) ) {
        return;
    }

    delete_transient( 'aegis_tech_seed_notice' );
    echo '<div class="notice notice-success is-dismissible"><p>';
    echo esc_html__( 'Placeholder tech features have been generated. You can edit them in Tech Features.', 'aegis' );
    echo '</p></div>';
}
add_action( 'admin_notices', 'aegis_tech_seed_notice' );

function aegis_add_product_tech_feature_metabox() {
    add_meta_box(
        'aegis_tech_features_selector',
        __( 'Technical Features (optional)', 'aegis' ),
        'aegis_render_product_tech_feature_metabox',
        'product',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'aegis_add_product_tech_feature_metabox' );

function aegis_render_product_tech_feature_metabox( $post ) {
    wp_nonce_field( 'aegis_tech_feature_meta_save', 'aegis_tech_feature_meta_nonce' );

    $selected = get_post_meta( $post->ID, '_aegis_tech_feature_ids', true );
    if ( ! is_array( $selected ) ) {
        $selected = array_filter( array_map( 'absint', explode( ',', (string) $selected ) ) );
    }

    $selected = array_values( array_unique( array_map( 'absint', $selected ) ) );
    $features = get_posts(
        array(
            'post_type'      => 'aegis_tech_feature',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $selected_count = count( $selected );

    echo '<p>' . esc_html__( 'Select technical features to display on the product page.', 'aegis' ) . '</p>';
    echo '<div class="aegis-tech-picker" data-selected-count="' . esc_attr( $selected_count ) . '">';
    echo '<input type="search" class="aegis-tech-picker__search" placeholder="' . esc_attr__( 'Search technical features…', 'aegis' ) . '" aria-label="' . esc_attr__( 'Search technical features', 'aegis' ) . '" style="width:100%; margin: 6px 0 10px;">';
    echo '<div class="aegis-tech-picker__count" style="margin-bottom: 8px;">' . esc_html__( 'Selected:', 'aegis' ) . ' <span>' . esc_html( (string) $selected_count ) . '</span></div>';
    echo '<div class="aegis-tech-picker__list" role="list" style="max-height: 280px; overflow-y: auto; border: 1px solid #d1d5db; padding: 8px;">';

    foreach ( $features as $feature ) {
        $is_checked = in_array( $feature->ID, $selected, true );
        $title = trim( wp_strip_all_tags( get_the_title( $feature ) ) );
        printf(
            '<label class="aegis-tech-picker__item" role="listitem"><input type="checkbox" name="aegis_tech_feature_ids[]" value="%1$d"%2$s> <span class="aegis-tech-picker__label">%3$s</span></label>',
            absint( $feature->ID ),
            checked( $is_checked, true, false ),
            esc_html( $title )
        );
    }

    echo '</div>';
    echo '</div>';
}

function aegis_save_product_tech_feature_meta( $post_id ) {
    if ( ! isset( $_POST['aegis_tech_feature_meta_nonce'] ) || ! wp_verify_nonce( $_POST['aegis_tech_feature_meta_nonce'], 'aegis_tech_feature_meta_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_product', $post_id ) ) {
        return;
    }

    $feature_ids = array();
    if ( isset( $_POST['aegis_tech_feature_ids'] ) && is_array( $_POST['aegis_tech_feature_ids'] ) ) {
        $feature_ids = array_values( array_filter( array_map( 'absint', $_POST['aegis_tech_feature_ids'] ) ) );
    }

    if ( empty( $feature_ids ) ) {
        delete_post_meta( $post_id, '_aegis_tech_feature_ids' );
        return;
    }

    update_post_meta( $post_id, '_aegis_tech_feature_ids', $feature_ids );
}
add_action( 'save_post_product', 'aegis_save_product_tech_feature_meta' );

function aegis_pdp_tech_features_shortcode() {
    if ( ! is_singular( 'product' ) ) {
        return '';
    }

    $product_id = get_the_ID();
    if ( ! $product_id ) {
        return '';
    }

    $feature_ids = get_post_meta( $product_id, '_aegis_tech_feature_ids', true );
    if ( empty( $feature_ids ) ) {
        return '';
    }

    $feature_ids = array_values( array_filter( array_map( 'absint', (array) $feature_ids ) ) );
    if ( empty( $feature_ids ) ) {
        return '';
    }

    $features = get_posts(
        array(
            'post_type'      => 'aegis_tech_feature',
            'posts_per_page' => -1,
            'post__in'       => $feature_ids,
            'orderby'        => 'post__in',
        )
    );

    if ( empty( $features ) ) {
        return '';
    }

    $items_markup = '';

    foreach ( $features as $feature ) {
        $title = trim( wp_strip_all_tags( get_the_title( $feature ) ) );
        $media = has_post_thumbnail( $feature )
            ? get_the_post_thumbnail( $feature, 'medium' )
            : '<div class="aegis-tech-feature-card__placeholder"></div>';

        $items_markup .= sprintf(
            '<a class="aegis-tech-feature-card" data-tech-id="%1$d" href="%2$s"><div class="aegis-tech-feature-card__media">%3$s</div><div class="aegis-tech-feature-card__title">%4$s</div></a>',
            absint( $feature->ID ),
            esc_url( get_permalink( $feature ) ),
            $media,
            esc_html( $title )
        );
    }

    $items_markup = preg_replace( '/<(br|p)([^>]*)>\\s*<\\/\\1>/', '', $items_markup );
    $items_markup = str_replace( '<br />', '', $items_markup );
    $items_markup = str_replace( '<br>', '', $items_markup );

    $output = sprintf(
        '<div class="aegis-wc-module aegis-wc-module--tech-features"><h3>%1$s</h3><div class="aegis-tech-features-grid">%2$s</div></div>',
        esc_html__( 'Technical features', 'aegis' ),
        $items_markup
    );

    return preg_replace( '/<p>(?:\\s|&nbsp;|\\xc2\\xa0|<!--.*?-->)*<\\/p>/i', '', $output );
}
add_shortcode( 'aegis_pdp_tech_features', 'aegis_pdp_tech_features_shortcode' );
