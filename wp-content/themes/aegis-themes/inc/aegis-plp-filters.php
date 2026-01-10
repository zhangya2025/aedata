<?php

define( 'AEGIS_PLP_FILTERS_TEMP_BUCKETS', array(
    'le_minus_15' => array(
        'label' => '≤ -15°C',
        'min' => null,
        'max' => -15,
    ),
    'minus_15_to_minus_10' => array(
        'label' => '-15°C to -10°C',
        'min' => -15,
        'max' => -10,
    ),
    'minus_10_to_minus_5' => array(
        'label' => '-10°C to -5°C',
        'min' => -10,
        'max' => -5,
    ),
    'minus_5_to_0' => array(
        'label' => '-5°C to 0°C',
        'min' => -5,
        'max' => 0,
    ),
    'zero_to_5' => array(
        'label' => '0°C to 5°C',
        'min' => 0,
        'max' => 5,
    ),
    'ge_5' => array(
        'label' => '≥ 5°C',
        'min' => 5,
        'max' => null,
    ),
) );

function aegis_plp_filters_is_sleepingbags_context() {
    if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
        return false;
    }

    $term = get_queried_object();
    if ( ! $term || empty( $term->term_id ) ) {
        return false;
    }

    $root = get_term_by( 'slug', 'sleepingbags', 'product_cat' );
    if ( ! $root || is_wp_error( $root ) ) {
        return false;
    }

    if ( (int) $term->term_id === (int) $root->term_id ) {
        return true;
    }

    $ancestors = get_ancestors( (int) $term->term_id, 'product_cat' );
    return in_array( (int) $root->term_id, $ancestors, true );
}

function aegis_plp_filters_parse_request() {
    $filters = array();
    $temp_buckets = array();
    $min_price = '';
    $max_price = '';

    foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 0 !== strpos( $key, 'filter_' ) ) {
            continue;
        }

        $attr_slug = sanitize_key( substr( $key, 7 ) );
        if ( '' === $attr_slug ) {
            continue;
        }

        $taxonomy = 'pa_' . $attr_slug;
        if ( ! taxonomy_exists( $taxonomy ) ) {
            continue;
        }

        $raw_value = is_array( $value ) ? implode( ',', $value ) : (string) $value;
        $parts = array_filter( array_map( 'sanitize_title', explode( ',', $raw_value ) ) );
        if ( empty( $parts ) ) {
            continue;
        }

        $filters[ $taxonomy ] = array_values( array_unique( $parts ) );
    }

    if ( isset( $_GET['temp_limit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw_temp = is_array( $_GET['temp_limit'] ) ? implode( ',', $_GET['temp_limit'] ) : (string) $_GET['temp_limit']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $temp_buckets = array_filter( array_map( 'sanitize_key', explode( ',', $raw_temp ) ) );
    }

    if ( isset( $_GET['min_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $min_price = wc_format_decimal( wp_unslash( $_GET['min_price'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    if ( isset( $_GET['max_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $max_price = wc_format_decimal( wp_unslash( $_GET['max_price'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    return array(
        'filters' => $filters,
        'temp_limit' => $temp_buckets,
        'min_price' => $min_price,
        'max_price' => $max_price,
    );
}

function aegis_plp_filters_enqueue() {
    if ( ! aegis_plp_filters_is_sleepingbags_context() ) {
        return;
    }

    wp_enqueue_style(
        'aegis-plp-filters',
        get_theme_file_uri( 'assets/css/aegis-plp-filters.css' ),
        array(),
        AEGIS_THEMES_VERSION
    );

    wp_enqueue_script(
        'aegis-plp-filters',
        get_theme_file_uri( 'assets/js/aegis-plp-filters.js' ),
        array(),
        AEGIS_THEMES_VERSION,
        true
    );
}

function aegis_plp_filters_adjust_shop_loop() {
    if ( ! aegis_plp_filters_is_sleepingbags_context() ) {
        return;
    }

    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
}

function aegis_plp_filters_render_toolbar() {
    if ( ! aegis_plp_filters_is_sleepingbags_context() ) {
        return;
    }

    $request = aegis_plp_filters_parse_request();
    $temp_buckets = AEGIS_PLP_FILTERS_TEMP_BUCKETS;

    $taxonomy_groups = array(
        'Color' => 'pa_sleepingbag-color',
        'Fill Type' => 'pa_sleepingbag_fill_type',
        'Best Use' => 'pa_sleepingbag_activity',
        'More' => array(
            'pa_sleepingbag_fp',
            'pa_sleepingbag_shape',
            'pa_sleepingbag_fit',
            'pa_sleepingbag_fabric_denier',
            'pa_sleepingbag_zip_side',
            'pa_sleepingbag_zipper_count',
            'pa_sleepingbag-size',
            'pa_sleepingbag_model',
            'pa_sleeping-bag-type',
        ),
    );

    $current_url = esc_url( add_query_arg( array() ) );
    $filter_keys = array();

    foreach ( $taxonomy_groups as $group ) {
        if ( is_array( $group ) ) {
            foreach ( $group as $taxonomy ) {
                if ( taxonomy_exists( $taxonomy ) ) {
                    $filter_keys[] = 'filter_' . str_replace( 'pa_', '', $taxonomy );
                }
            }
            continue;
        }

        if ( taxonomy_exists( $group ) ) {
            $filter_keys[] = 'filter_' . str_replace( 'pa_', '', $group );
        }
    }

    $clear_url = esc_url( remove_query_arg( array_merge( $filter_keys, array( 'temp_limit', 'min_price', 'max_price' ) ) ) );
    ?>
    <div class="aegis-plp-filters" data-aegis-plp-filters>
        <form class="aegis-plp-filters__form" method="get" action="<?php echo $current_url; ?>">
            <input type="hidden" name="temp_limit" value="<?php echo esc_attr( implode( ',', $request['temp_limit'] ) ); ?>" data-filter-input="temp_limit" />
            <?php foreach ( $filter_keys as $filter_key ) : ?>
            <?php
            $filter_value = '';
            if ( isset( $_GET[ $filter_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $raw_value = wp_unslash( $_GET[ $filter_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $filter_value = is_array( $raw_value ) ? implode( ',', $raw_value ) : (string) $raw_value;
            }
            ?>
            <input type="hidden" name="<?php echo esc_attr( $filter_key ); ?>" value="<?php echo esc_attr( $filter_value ); ?>" data-filter-input="<?php echo esc_attr( $filter_key ); ?>" />
            <?php endforeach; ?>
            <div class="aegis-plp-filters__toolbar">
                <div class="aegis-plp-filters__buttons">
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open>Color</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open>Temperature (°C)</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open>Price</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open>Fill Type</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open>Best Use</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open>More Filters</button>
                </div>
                <div class="aegis-plp-filters__meta">
                    <?php if ( function_exists( 'woocommerce_result_count' ) ) : ?>
                        <?php woocommerce_result_count(); ?>
                    <?php endif; ?>
                    <?php if ( function_exists( 'woocommerce_catalog_ordering' ) ) : ?>
                        <?php woocommerce_catalog_ordering(); ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! empty( $request['filters'] ) || ! empty( $request['temp_limit'] ) || '' !== $request['min_price'] || '' !== $request['max_price'] ) : ?>
                <div class="aegis-plp-filters__chips">
                    <span class="aegis-plp-filters__chips-label">Active Filters:</span>
                    <div class="aegis-plp-filters__chip-group">
                        <?php foreach ( $request['filters'] as $taxonomy => $terms ) : ?>
                            <?php foreach ( $terms as $term_slug ) : ?>
                                <?php $term_obj = get_term_by( 'slug', $term_slug, $taxonomy ); ?>
                                <?php if ( $term_obj && ! is_wp_error( $term_obj ) ) : ?>
                                    <span class="aegis-plp-filters__chip"><?php echo esc_html( $term_obj->name ); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php foreach ( $request['temp_limit'] as $bucket_key ) : ?>
                            <?php if ( isset( $temp_buckets[ $bucket_key ] ) ) : ?>
                                <span class="aegis-plp-filters__chip"><?php echo esc_html( $temp_buckets[ $bucket_key ]['label'] ); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ( '' !== $request['min_price'] || '' !== $request['max_price'] ) : ?>
                            <span class="aegis-plp-filters__chip">
                                <?php echo esc_html( sprintf( 'Price: %s - %s', $request['min_price'] !== '' ? $request['min_price'] : 'Any', $request['max_price'] !== '' ? $request['max_price'] : 'Any' ) ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <a class="aegis-plp-filters__clear" href="<?php echo $clear_url; ?>">Clear all</a>
                </div>
            <?php endif; ?>

            <div class="aegis-plp-filters__drawer" data-aegis-plp-drawer>
                <div class="aegis-plp-filters__drawer-header">
                    <span class="aegis-plp-filters__drawer-title">Filter By</span>
                    <button type="button" class="aegis-plp-filters__drawer-close" data-drawer-close aria-label="Close filters">×</button>
                </div>
                <div class="aegis-plp-filters__drawer-body">
                    <?php if ( taxonomy_exists( 'pa_sleepingbag-color' ) ) : ?>
                        <?php $terms = get_terms( array( 'taxonomy' => 'pa_sleepingbag-color', 'hide_empty' => false ) ); ?>
                        <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                            <div class="aegis-plp-filters__group">
                                <button type="button" class="aegis-plp-filters__group-toggle">Color</button>
                                <div class="aegis-plp-filters__group-content">
                                    <?php foreach ( $terms as $term ) : ?>
                                        <label class="aegis-plp-filters__option">
                                            <input type="checkbox" data-filter-key="filter_sleepingbag-color" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filters']['pa_sleepingbag-color'] ?? array(), true ) ); ?> />
                                            <span><?php echo esc_html( $term->name ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="aegis-plp-filters__group">
                        <button type="button" class="aegis-plp-filters__group-toggle">Temperature (°C)</button>
                        <div class="aegis-plp-filters__group-content">
                            <?php foreach ( $temp_buckets as $bucket_key => $bucket ) : ?>
                                <label class="aegis-plp-filters__option">
                                    <input type="checkbox" data-filter-key="temp_limit" value="<?php echo esc_attr( $bucket_key ); ?>" <?php checked( in_array( $bucket_key, $request['temp_limit'], true ) ); ?> />
                                    <span><?php echo esc_html( $bucket['label'] ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="aegis-plp-filters__group">
                        <button type="button" class="aegis-plp-filters__group-toggle">Price</button>
                        <div class="aegis-plp-filters__group-content">
                            <label class="aegis-plp-filters__option">
                                <span>Min</span>
                                <input type="number" name="min_price" min="0" step="1" value="<?php echo esc_attr( $request['min_price'] ); ?>" />
                            </label>
                            <label class="aegis-plp-filters__option">
                                <span>Max</span>
                                <input type="number" name="max_price" min="0" step="1" value="<?php echo esc_attr( $request['max_price'] ); ?>" />
                            </label>
                        </div>
                    </div>

                    <?php if ( taxonomy_exists( 'pa_sleepingbag_fill_type' ) ) : ?>
                        <?php $terms = get_terms( array( 'taxonomy' => 'pa_sleepingbag_fill_type', 'hide_empty' => false ) ); ?>
                        <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                            <div class="aegis-plp-filters__group">
                                <button type="button" class="aegis-plp-filters__group-toggle">Fill Type</button>
                                <div class="aegis-plp-filters__group-content">
                                    <?php foreach ( $terms as $term ) : ?>
                                        <label class="aegis-plp-filters__option">
                                            <input type="checkbox" data-filter-key="filter_sleepingbag_fill_type" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filters']['pa_sleepingbag_fill_type'] ?? array(), true ) ); ?> />
                                            <span><?php echo esc_html( $term->name ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ( taxonomy_exists( 'pa_sleepingbag_activity' ) ) : ?>
                        <?php $terms = get_terms( array( 'taxonomy' => 'pa_sleepingbag_activity', 'hide_empty' => false ) ); ?>
                        <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                            <div class="aegis-plp-filters__group">
                                <button type="button" class="aegis-plp-filters__group-toggle">Best Use</button>
                                <div class="aegis-plp-filters__group-content">
                                    <?php foreach ( $terms as $term ) : ?>
                                        <label class="aegis-plp-filters__option">
                                            <input type="checkbox" data-filter-key="filter_sleepingbag_activity" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filters']['pa_sleepingbag_activity'] ?? array(), true ) ); ?> />
                                            <span><?php echo esc_html( $term->name ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php
                    $more_taxonomies = $taxonomy_groups['More'];
                    $has_more = false;
                    foreach ( $more_taxonomies as $taxonomy ) {
                        if ( taxonomy_exists( $taxonomy ) ) {
                            $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
                            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                                $has_more = true;
                                break;
                            }
                        }
                    }
                    ?>
                    <?php if ( $has_more ) : ?>
                        <div class="aegis-plp-filters__group">
                            <button type="button" class="aegis-plp-filters__group-toggle">More Filters</button>
                            <div class="aegis-plp-filters__group-content">
                                <?php foreach ( $more_taxonomies as $taxonomy ) : ?>
                                    <?php if ( taxonomy_exists( $taxonomy ) ) : ?>
                                        <?php $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ); ?>
                                        <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                                            <div class="aegis-plp-filters__subgroup">
                                                <h4 class="aegis-plp-filters__subgroup-title"><?php echo esc_html( wc_attribute_label( $taxonomy ) ); ?></h4>
                                                <?php foreach ( $terms as $term ) : ?>
                                                    <label class="aegis-plp-filters__option">
                                                        <input type="checkbox" data-filter-key="filter_<?php echo esc_attr( str_replace( 'pa_', '', $taxonomy ) ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filters'][ $taxonomy ] ?? array(), true ) ); ?> />
                                                        <span><?php echo esc_html( $term->name ); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="aegis-plp-filters__drawer-footer">
                    <a class="aegis-plp-filters__clear" href="<?php echo $clear_url; ?>">Clear</a>
                    <button type="submit" class="aegis-plp-filters__submit">View Results</button>
                </div>
            </div>
            <div class="aegis-plp-filters__overlay" data-drawer-overlay></div>
        </form>
    </div>
    <?php
}

function aegis_plp_filters_apply_query( $query ) {
    if ( ! aegis_plp_filters_is_sleepingbags_context() ) {
        return;
    }

    $request = aegis_plp_filters_parse_request();

    $tax_query = $query->get( 'tax_query', array() );
    if ( ! is_array( $tax_query ) ) {
        $tax_query = array();
    }

    foreach ( $request['filters'] as $taxonomy => $terms ) {
        $tax_query[] = array(
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => $terms,
            'operator' => 'IN',
        );
    }

    if ( ! empty( $tax_query ) ) {
        $query->set( 'tax_query', $tax_query );
    }

    $meta_query = $query->get( 'meta_query', array() );
    if ( ! is_array( $meta_query ) ) {
        $meta_query = array();
    }

    if ( ! empty( $request['temp_limit'] ) ) {
        $temp_query = array( 'relation' => 'OR' );
        foreach ( $request['temp_limit'] as $bucket_key ) {
            if ( ! isset( AEGIS_PLP_FILTERS_TEMP_BUCKETS[ $bucket_key ] ) ) {
                continue;
            }

            $bucket = AEGIS_PLP_FILTERS_TEMP_BUCKETS[ $bucket_key ];
            $clauses = array( 'relation' => 'AND' );
            if ( null !== $bucket['min'] ) {
                $clauses[] = array(
                    'key' => 'sleepingbag_limit_c',
                    'value' => $bucket['min'],
                    'compare' => '>=',
                    'type' => 'NUMERIC',
                );
            }
            if ( null !== $bucket['max'] ) {
                $clauses[] = array(
                    'key' => 'sleepingbag_limit_c',
                    'value' => $bucket['max'],
                    'compare' => '<',
                    'type' => 'NUMERIC',
                );
            }
            $temp_query[] = $clauses;
        }

        if ( count( $temp_query ) > 1 ) {
            $meta_query[] = $temp_query;
        }
    }

    if ( '' !== $request['min_price'] || '' !== $request['max_price'] ) {
        $price_clause = array(
            'key' => '_price',
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC',
        );

        $min = '' !== $request['min_price'] ? (float) $request['min_price'] : 0;
        $max = '' !== $request['max_price'] ? (float) $request['max_price'] : PHP_INT_MAX;
        $price_clause['value'] = array( $min, $max );

        $meta_query[] = $price_clause;
    }

    if ( ! empty( $meta_query ) ) {
        $query->set( 'meta_query', $meta_query );
    }
}
