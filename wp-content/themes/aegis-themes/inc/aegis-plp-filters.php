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

if ( ! defined( 'AEGIS_PLP_DEBUG' ) ) {
    define( 'AEGIS_PLP_DEBUG', false );
}

function aegis_plp_filters_debug_log( $label, $data ) {
    if ( ! AEGIS_PLP_DEBUG ) {
        return;
    }

    $payload = wp_json_encode( $data );
    error_log( sprintf( '[aegis-plp] %s: %s', $label, $payload ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

function aegis_plp_filters_clean_query_args( $raw ) {
    if ( ! is_array( $raw ) ) {
        return array();
    }

    $clean = array();
    foreach ( $raw as $key => $value ) {
        if ( is_array( $value ) ) {
            $filtered = array_filter( array_map( 'trim', $value ), function ( $item ) {
                return '' !== $item && ! preg_match( '/^,+$/', $item );
            } );
            if ( empty( $filtered ) ) {
                continue;
            }
            $clean[ $key ] = array_values( $filtered );
            continue;
        }

        $string_value = trim( (string) $value );
        if ( '' === $string_value || preg_match( '/^,+$/', $string_value ) ) {
            continue;
        }

        if ( 'orderby' === $key ) {
            $clean[ $key ] = $string_value;
            continue;
        }

        if ( in_array( $key, array( 'min_price', 'max_price' ), true ) ) {
            if ( is_numeric( $string_value ) ) {
                $clean[ $key ] = $string_value;
            }
            continue;
        }

        if ( 'temp_limit' === $key ) {
            $clean[ $key ] = $string_value;
            continue;
        }

        if ( 0 === strpos( $key, 'filter_' ) ) {
            $clean[ $key ] = $string_value;
            continue;
        }

        $clean[ $key ] = $string_value;
    }

    return $clean;
}

function aegis_plp_filters_parse_csv_values( $value, $sanitize_callback ) {
    if ( null === $value ) {
        return array();
    }

    $raw = is_array( $value ) ? implode( ',', $value ) : (string) $value;
    $raw = trim( $raw );

    if ( '' === $raw ) {
        return array();
    }

    $raw = trim( $raw, "," );
    if ( '' === $raw ) {
        return array();
    }

    $parts = array_map( 'trim', explode( ',', $raw ) );
    $parts = array_filter( $parts, function ( $part ) {
        return '' !== $part;
    } );

    if ( empty( $parts ) ) {
        return array();
    }

    if ( is_callable( $sanitize_callback ) ) {
        $parts = array_map( $sanitize_callback, $parts );
        $parts = array_filter( $parts, function ( $part ) {
            return '' !== $part && null !== $part;
        } );
    }

    return array_values( array_unique( $parts ) );
}

function aegis_plp_filters_filter_valid_terms( $taxonomy, $terms ) {
    if ( ! taxonomy_exists( $taxonomy ) || empty( $terms ) ) {
        return array();
    }

    $valid = array();
    foreach ( $terms as $term ) {
        if ( term_exists( $term, $taxonomy ) ) {
            $valid[] = $term;
        }
    }

    return array_values( array_unique( $valid ) );
}

function aegis_plp_filters_filter_key_from_taxonomy( $taxonomy ) {
    $slug = str_replace( 'pa_', '', (string) $taxonomy );
    return 'filter_' . str_replace( '-', '_', $slug );
}

function aegis_plp_filters_resolve_taxonomy_from_filter_key( $filter_key ) {
    $attr_slug = sanitize_key( substr( $filter_key, 7 ) );
    if ( '' === $attr_slug ) {
        return '';
    }

    $taxonomy = 'pa_' . $attr_slug;
    if ( taxonomy_exists( $taxonomy ) ) {
        return $taxonomy;
    }

    $alt_slug = str_replace( '_', '-', $attr_slug );
    if ( $alt_slug !== $attr_slug ) {
        $alt_taxonomy = 'pa_' . $alt_slug;
        if ( taxonomy_exists( $alt_taxonomy ) ) {
            return $alt_taxonomy;
        }
    }

    $alt_slug = str_replace( '-', '_', $attr_slug );
    if ( $alt_slug !== $attr_slug ) {
        $alt_taxonomy = 'pa_' . $alt_slug;
        if ( taxonomy_exists( $alt_taxonomy ) ) {
            return $alt_taxonomy;
        }
    }

    return '';
}

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

function aegis_plp_filters_is_other_product_cat_context() {
    if ( ! function_exists( 'is_tax' ) || ! is_tax( 'product_cat' ) ) {
        return false;
    }

    $term = get_queried_object();
    if ( ! $term || empty( $term->term_id ) ) {
        return false;
    }

    $root = get_term_by( 'slug', 'sleepingbags', 'product_cat' );
    if ( ! $root || is_wp_error( $root ) ) {
        return true;
    }

    if ( (int) $term->term_id === (int) $root->term_id ) {
        return false;
    }

    $ancestors = get_ancestors( (int) $term->term_id, 'product_cat' );
    return ! in_array( (int) $root->term_id, $ancestors, true );
}

function aegis_plp_filters_is_plp_enabled_context() {
    return aegis_plp_filters_is_sleepingbags_context() || aegis_plp_filters_is_other_product_cat_context();
}

function aegis_plp_filters_parse_request( $raw_args = null ) {
    $raw_args = is_array( $raw_args ) ? $raw_args : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $filters = array();
    $temp_buckets = array();
    $min_price = '';
    $max_price = '';

    foreach ( $raw_args as $key => $value ) {
        if ( 0 !== strpos( $key, 'filter_' ) ) {
            continue;
        }

        $taxonomy = aegis_plp_filters_resolve_taxonomy_from_filter_key( $key );
        if ( '' === $taxonomy ) {
            continue;
        }

        $parts = aegis_plp_filters_parse_csv_values( $value, 'sanitize_title' );
        if ( empty( $parts ) ) {
            continue;
        }

        $parts = aegis_plp_filters_filter_valid_terms( $taxonomy, $parts );
        if ( empty( $parts ) ) {
            continue;
        }

        $filters[ $taxonomy ] = $parts;
    }

    if ( isset( $raw_args['temp_limit'] ) ) {
        $temp_buckets = aegis_plp_filters_parse_csv_values( $raw_args['temp_limit'], 'sanitize_key' );
    }

    if ( isset( $raw_args['min_price'] ) ) {
        $raw_min = trim( (string) wp_unslash( $raw_args['min_price'] ) );
        if ( '' !== $raw_min && is_numeric( $raw_min ) ) {
            $min_price = wc_format_decimal( $raw_min );
        }
    }

    if ( isset( $raw_args['max_price'] ) ) {
        $raw_max = trim( (string) wp_unslash( $raw_args['max_price'] ) );
        if ( '' !== $raw_max && is_numeric( $raw_max ) ) {
            $max_price = wc_format_decimal( $raw_max );
        }
    }

    return array(
        'filters' => $filters,
        'temp_limit' => $temp_buckets,
        'min_price' => $min_price,
        'max_price' => $max_price,
    );
}

function aegis_plp_filters_parse_other_request() {
    $selected_categories = array();
    $selected_colors = array();
    $selected_sizes = array();

    $term = get_queried_object();
    if ( ! $term || empty( $term->term_id ) ) {
        return array(
            'filter_cat' => $selected_categories,
            'filter_color' => $selected_colors,
            'filter_size' => $selected_sizes,
            'category_children' => array(),
        );
    }

    $category_children = array();
    $child_terms = get_terms(
        array(
            'taxonomy' => 'product_cat',
            'parent' => (int) $term->term_id,
            'hide_empty' => false,
        )
    );

    if ( ! empty( $child_terms ) && ! is_wp_error( $child_terms ) ) {
        $category_children = $child_terms;
    }

    if ( isset( $_GET['filter_cat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw_value = is_array( $_GET['filter_cat'] ) ? implode( ',', $_GET['filter_cat'] ) : (string) $_GET['filter_cat']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_categories = array_filter( array_map( 'sanitize_title', explode( ',', $raw_value ) ) );
        if ( ! empty( $category_children ) ) {
            $child_slugs = wp_list_pluck( $category_children, 'slug' );
            $selected_categories = array_values( array_intersect( $selected_categories, $child_slugs ) );
        } else {
            $selected_categories = array();
        }
    }

    if ( taxonomy_exists( 'pa_color' ) && isset( $_GET['filter_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw_color = is_array( $_GET['filter_color'] ) ? implode( ',', $_GET['filter_color'] ) : (string) $_GET['filter_color']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_colors = array_filter( array_map( 'sanitize_title', explode( ',', $raw_color ) ) );
    }

    if ( taxonomy_exists( 'pa_size' ) && isset( $_GET['filter_size'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw_size = is_array( $_GET['filter_size'] ) ? implode( ',', $_GET['filter_size'] ) : (string) $_GET['filter_size']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_sizes = array_filter( array_map( 'sanitize_title', explode( ',', $raw_size ) ) );
    }

    return array(
        'filter_cat' => $selected_categories,
        'filter_color' => $selected_colors,
        'filter_size' => $selected_sizes,
        'category_children' => $category_children,
    );
}

function aegis_plp_filters_enqueue() {
    if ( ! aegis_plp_filters_is_plp_enabled_context() ) {
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

function aegis_plp_filters_body_class( $classes ) {
    if ( aegis_plp_filters_is_sleepingbags_context() ) {
        $classes[] = 'aegis-plp-sleepingbags';
    }

    if ( aegis_plp_filters_is_other_product_cat_context() ) {
        $classes[] = 'aegis-plp-catalog';
    }

    if ( aegis_plp_filters_is_plp_enabled_context() ) {
        $classes[] = 'aegis-plp-enabled';
    }

    return $classes;
}

function aegis_plp_filters_adjust_shop_loop() {
    if ( ! aegis_plp_filters_is_plp_enabled_context() ) {
        return;
    }

    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
    remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}

function aegis_plp_filters_render_toolbar() {
    if ( aegis_plp_filters_is_sleepingbags_context() ) {
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
        $current_orderby = '';
        if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $current_orderby = wc_clean( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        $filter_keys = array();

        foreach ( $taxonomy_groups as $group ) {
            if ( is_array( $group ) ) {
                foreach ( $group as $taxonomy ) {
                    if ( taxonomy_exists( $taxonomy ) ) {
                        $filter_keys[] = aegis_plp_filters_filter_key_from_taxonomy( $taxonomy );
                    }
                }
                continue;
            }

            if ( taxonomy_exists( $group ) ) {
                $filter_keys[] = aegis_plp_filters_filter_key_from_taxonomy( $group );
            }
        }

        $clear_url = esc_url( remove_query_arg( array_merge( $filter_keys, array( 'temp_limit', 'min_price', 'max_price' ) ) ) );
        ?>
        <div class="aegis-plp-filters" data-aegis-plp-filters>
            <div class="aegis-plp-filters__toolbar">
                <div class="aegis-plp-filters__buttons">
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="color">Color</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="temp">Temperature (°C)</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="price">Price</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="fill">Fill Type</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="use">Best Use</button>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="all">
                        <span class="aegis-plp-filters__label--desktop">More Filters</span>
                        <span class="aegis-plp-filters__label--mobile">All Filters</span>
                    </button>
                </div>
                <div class="aegis-plp-filters__meta">
                    <?php if ( function_exists( 'woocommerce_catalog_ordering' ) ) : ?>
                        <?php woocommerce_catalog_ordering(); ?>
                    <?php endif; ?>
                </div>
            </div>
            <form class="aegis-plp-filters__form" method="get" action="<?php echo $current_url; ?>">
                <input type="hidden" name="temp_limit" value="<?php echo esc_attr( implode( ',', $request['temp_limit'] ) ); ?>" data-filter-input="temp_limit" />
                <input type="hidden" name="orderby" value="<?php echo esc_attr( $current_orderby ); ?>" />
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
                                <?php $color_filter_key = aegis_plp_filters_filter_key_from_taxonomy( 'pa_sleepingbag-color' ); ?>
                                <div class="aegis-plp-filters__group" data-aegis-plp-section="color">
                                    <button type="button" class="aegis-plp-filters__group-toggle">Color</button>
                                    <div class="aegis-plp-filters__group-content">
                                        <?php foreach ( $terms as $term ) : ?>
                                            <label class="aegis-plp-filters__option">
                                                <input type="checkbox" data-filter-key="<?php echo esc_attr( $color_filter_key ); ?>" data-filter-label="<?php echo esc_attr( $term->name ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filters']['pa_sleepingbag-color'] ?? array(), true ) ); ?> />
                                                <span><?php echo esc_html( $term->name ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="aegis-plp-filters__group" data-aegis-plp-section="temp">
                            <button type="button" class="aegis-plp-filters__group-toggle">Temperature (°C)</button>
                            <div class="aegis-plp-filters__group-content">
                                <?php foreach ( $temp_buckets as $bucket_key => $bucket ) : ?>
                                    <label class="aegis-plp-filters__option">
                                        <input type="checkbox" data-filter-key="temp_limit" data-filter-label="<?php echo esc_attr( $bucket['label'] ); ?>" value="<?php echo esc_attr( $bucket_key ); ?>" <?php checked( in_array( $bucket_key, $request['temp_limit'], true ) ); ?> />
                                        <span><?php echo esc_html( $bucket['label'] ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="aegis-plp-filters__group" data-aegis-plp-section="price">
                            <button type="button" class="aegis-plp-filters__group-toggle">Price</button>
                            <div class="aegis-plp-filters__group-content">
                                <label class="aegis-plp-filters__option">
                                    <span>Min</span>
                                    <input type="number" name="min_price" min="0" step="1" value="<?php echo esc_attr( $request['min_price'] ); ?>" data-filter-input="min_price" data-filter-label="Min Price" />
                                </label>
                                <label class="aegis-plp-filters__option">
                                    <span>Max</span>
                                    <input type="number" name="max_price" min="0" step="1" value="<?php echo esc_attr( $request['max_price'] ); ?>" data-filter-input="max_price" data-filter-label="Max Price" />
                                </label>
                            </div>
                        </div>

                        <?php if ( taxonomy_exists( 'pa_sleepingbag_fill_type' ) ) : ?>
                            <?php $terms = get_terms( array( 'taxonomy' => 'pa_sleepingbag_fill_type', 'hide_empty' => false ) ); ?>
                            <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                                <?php $fill_filter_key = aegis_plp_filters_filter_key_from_taxonomy( 'pa_sleepingbag_fill_type' ); ?>
                                <div class="aegis-plp-filters__group" data-aegis-plp-section="fill">
                                    <button type="button" class="aegis-plp-filters__group-toggle">Fill Type</button>
                                    <div class="aegis-plp-filters__group-content">
                                        <?php foreach ( $terms as $term ) : ?>
                                            <label class="aegis-plp-filters__option">
                                                <input type="checkbox" data-filter-key="<?php echo esc_attr( $fill_filter_key ); ?>" data-filter-label="<?php echo esc_attr( $term->name ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filters']['pa_sleepingbag_fill_type'] ?? array(), true ) ); ?> />
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
                                <?php $activity_filter_key = aegis_plp_filters_filter_key_from_taxonomy( 'pa_sleepingbag_activity' ); ?>
                                <div class="aegis-plp-filters__group" data-aegis-plp-section="use">
                                    <button type="button" class="aegis-plp-filters__group-toggle">Best Use</button>
                                    <div class="aegis-plp-filters__group-content">
                                        <?php foreach ( $terms as $term ) : ?>
                                            <label class="aegis-plp-filters__option">
                                                <input type="checkbox" data-filter-key="<?php echo esc_attr( $activity_filter_key ); ?>" data-filter-label="<?php echo esc_attr( $term->name ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filters']['pa_sleepingbag_activity'] ?? array(), true ) ); ?> />
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
                            <div class="aegis-plp-filters__group" data-aegis-plp-section="more">
                                <button type="button" class="aegis-plp-filters__group-toggle">More Filters</button>
                                <div class="aegis-plp-filters__group-content">
                                    <?php foreach ( $more_taxonomies as $taxonomy ) : ?>
                                        <?php if ( taxonomy_exists( $taxonomy ) ) : ?>
                                            <?php $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ); ?>
                                            <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                                                <div class="aegis-plp-filters__subgroup">
                                                    <h4 class="aegis-plp-filters__subgroup-title"><?php echo esc_html( wc_attribute_label( $taxonomy ) ); ?></h4>
                                                    <?php foreach ( $terms as $term ) : ?>
                                                        <?php $filter_key = aegis_plp_filters_filter_key_from_taxonomy( $taxonomy ); ?>
                                                        <label class="aegis-plp-filters__option">
                                                            <input type="checkbox" data-filter-key="<?php echo esc_attr( $filter_key ); ?>" data-filter-label="<?php echo esc_attr( $term->name ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filters'][ $taxonomy ] ?? array(), true ) ); ?> />
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
                        <div class="aegis-plp-filters__selected-title">Selected</div>
                        <div class="aegis-plp-filters__selected" data-aegis-selected>
                            <span class="aegis-plp-filters__selected-empty">No filters selected</span>
                        </div>
                        <div class="aegis-plp-filters__footer-actions">
                            <button type="button" class="aegis-plp-filters__clear" data-aegis-clear>Clear</button>
                            <button type="submit" class="aegis-plp-filters__submit">View Results</button>
                        </div>
                    </div>
                </div>
                <div class="aegis-plp-filters__overlay" data-drawer-overlay></div>
            </form>
        </div>
        <?php
        return;
    }

    if ( ! aegis_plp_filters_is_other_product_cat_context() ) {
        return;
    }

    $request = aegis_plp_filters_parse_other_request();
    $category_children = $request['category_children'];
    $current_url = esc_url( add_query_arg( array() ) );
    $current_orderby = '';
    if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_orderby = wc_clean( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    $filter_keys = array( 'filter_cat', 'filter_color', 'filter_size' );
    $clear_url = esc_url( remove_query_arg( array_merge( $filter_keys, array( 'temp_limit', 'min_price', 'max_price' ) ) ) );
    $has_categories = ! empty( $category_children );
    $has_colors = taxonomy_exists( 'pa_color' );
    $has_sizes = taxonomy_exists( 'pa_size' );
    $color_terms = array();
    $size_terms = array();
    if ( $has_colors ) {
        $color_terms = get_terms( array( 'taxonomy' => 'pa_color', 'hide_empty' => true ) );
        $has_colors = ! empty( $color_terms ) && ! is_wp_error( $color_terms );
    }
    if ( $has_sizes ) {
        $size_terms = get_terms( array( 'taxonomy' => 'pa_size', 'hide_empty' => true ) );
        $has_sizes = ! empty( $size_terms ) && ! is_wp_error( $size_terms );
    }
    $has_filters = $has_categories || $has_colors || $has_sizes;
    ?>
    <div class="aegis-plp-filters" data-aegis-plp-filters>
        <div class="aegis-plp-filters__toolbar">
            <div class="aegis-plp-filters__buttons">
                <?php if ( $has_categories ) : ?>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="cat">Category</button>
                <?php endif; ?>
                <?php if ( $has_colors ) : ?>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="color">Color</button>
                <?php endif; ?>
                <?php if ( $has_sizes ) : ?>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="size">Size</button>
                <?php endif; ?>
                <?php if ( $has_filters ) : ?>
                    <button type="button" class="aegis-plp-filters__button" data-drawer-open data-aegis-plp-mode="all">
                        <span class="aegis-plp-filters__label--desktop">All Filters</span>
                        <span class="aegis-plp-filters__label--mobile">All Filters</span>
                    </button>
                <?php endif; ?>
            </div>
            <div class="aegis-plp-filters__meta">
                <?php if ( function_exists( 'woocommerce_catalog_ordering' ) ) : ?>
                    <?php woocommerce_catalog_ordering(); ?>
                <?php endif; ?>
            </div>
        </div>
        <form class="aegis-plp-filters__form" method="get" action="<?php echo $current_url; ?>">
            <input type="hidden" name="orderby" value="<?php echo esc_attr( $current_orderby ); ?>" />
            <input type="hidden" name="filter_cat" value="<?php echo esc_attr( implode( ',', $request['filter_cat'] ) ); ?>" data-filter-input="filter_cat" />
            <input type="hidden" name="filter_color" value="<?php echo esc_attr( implode( ',', $request['filter_color'] ) ); ?>" data-filter-input="filter_color" />
            <input type="hidden" name="filter_size" value="<?php echo esc_attr( implode( ',', $request['filter_size'] ) ); ?>" data-filter-input="filter_size" />

            <?php if ( ! empty( $request['filter_cat'] ) || ! empty( $request['filter_color'] ) || ! empty( $request['filter_size'] ) ) : ?>
                <div class="aegis-plp-filters__chips">
                    <span class="aegis-plp-filters__chips-label">Active Filters:</span>
                    <div class="aegis-plp-filters__chip-group">
                        <?php foreach ( $request['filter_cat'] as $term_slug ) : ?>
                            <?php $term_obj = get_term_by( 'slug', $term_slug, 'product_cat' ); ?>
                            <?php if ( $term_obj && ! is_wp_error( $term_obj ) ) : ?>
                                <span class="aegis-plp-filters__chip"><?php echo esc_html( $term_obj->name ); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ( $request['filter_color'] as $term_slug ) : ?>
                            <?php $term_obj = get_term_by( 'slug', $term_slug, 'pa_color' ); ?>
                            <?php if ( $term_obj && ! is_wp_error( $term_obj ) ) : ?>
                                <span class="aegis-plp-filters__chip"><?php echo esc_html( $term_obj->name ); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ( $request['filter_size'] as $term_slug ) : ?>
                            <?php $term_obj = get_term_by( 'slug', $term_slug, 'pa_size' ); ?>
                            <?php if ( $term_obj && ! is_wp_error( $term_obj ) ) : ?>
                                <span class="aegis-plp-filters__chip"><?php echo esc_html( $term_obj->name ); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
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
                    <?php if ( $has_categories ) : ?>
                        <div class="aegis-plp-filters__group" data-aegis-plp-section="cat">
                            <button type="button" class="aegis-plp-filters__group-toggle">Category</button>
                            <div class="aegis-plp-filters__group-content">
                                <?php foreach ( $category_children as $child_term ) : ?>
                                    <label class="aegis-plp-filters__option">
                                        <input type="checkbox" data-filter-key="filter_cat" data-filter-label="<?php echo esc_attr( $child_term->name ); ?>" value="<?php echo esc_attr( $child_term->slug ); ?>" <?php checked( in_array( $child_term->slug, $request['filter_cat'], true ) ); ?> />
                                        <span><?php echo esc_html( $child_term->name ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ( $has_colors ) : ?>
                        <div class="aegis-plp-filters__group" data-aegis-plp-section="color">
                            <button type="button" class="aegis-plp-filters__group-toggle">Color</button>
                            <div class="aegis-plp-filters__group-content">
                                <?php foreach ( $color_terms as $term ) : ?>
                                    <label class="aegis-plp-filters__option">
                                        <input type="checkbox" data-filter-key="filter_color" data-filter-label="<?php echo esc_attr( $term->name ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filter_color'], true ) ); ?> />
                                        <span><?php echo esc_html( $term->name ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ( $has_sizes ) : ?>
                        <div class="aegis-plp-filters__group" data-aegis-plp-section="size">
                            <button type="button" class="aegis-plp-filters__group-toggle">Size</button>
                            <div class="aegis-plp-filters__group-content">
                                <?php foreach ( $size_terms as $term ) : ?>
                                    <label class="aegis-plp-filters__option">
                                        <input type="checkbox" data-filter-key="filter_size" data-filter-label="<?php echo esc_attr( $term->name ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $request['filter_size'], true ) ); ?> />
                                        <span><?php echo esc_html( $term->name ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="aegis-plp-filters__drawer-footer">
                    <div class="aegis-plp-filters__selected-title">Selected</div>
                    <div class="aegis-plp-filters__selected" data-aegis-selected>
                        <span class="aegis-plp-filters__selected-empty">No filters selected</span>
                    </div>
                    <div class="aegis-plp-filters__footer-actions">
                        <button type="button" class="aegis-plp-filters__clear" data-aegis-clear>Clear</button>
                        <button type="submit" class="aegis-plp-filters__submit">View Results</button>
                    </div>
                </div>
            </div>
            <div class="aegis-plp-filters__overlay" data-drawer-overlay></div>
        </form>
    </div>
    <?php
}

function aegis_plp_filters_apply_query( $query ) {
    if ( aegis_plp_filters_is_sleepingbags_context() ) {
        $raw_args = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $clean = aegis_plp_filters_clean_query_args( $raw_args );

        if ( ! is_admin()
            && ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() )
            && ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST )
            && isset( $_SERVER['REQUEST_METHOD'] )
            && 'GET' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
            && $clean != $raw_args
        ) {
            $term = get_queried_object();
            $base_url = $term ? get_term_link( $term ) : '';
            if ( $base_url && ! is_wp_error( $base_url ) ) {
                wp_safe_redirect( add_query_arg( $clean, $base_url ), 302 );
                exit;
            }
        }

        $request = aegis_plp_filters_parse_request( $clean );
        $has_filter_params = false;
        foreach ( $clean as $key => $value ) {
            if ( 0 !== strpos( $key, 'filter_' ) ) {
                continue;
            }

            if ( '' !== $value ) {
                $has_filter_params = true;
                break;
            }
        }

        if ( ! $has_filter_params && isset( $clean['temp_limit'] ) ) {
            $temp_terms = aegis_plp_filters_parse_csv_values( $clean['temp_limit'], 'sanitize_key' );
            $has_filter_params = ! empty( $temp_terms );
        }

        if ( ! $has_filter_params ) {
            $has_filter_params = ( '' !== $request['min_price'] || '' !== $request['max_price'] );
        }

        if ( $has_filter_params ) {
            aegis_plp_filters_debug_log( 'query-start', array(
                'is_main_query' => $query->is_main_query(),
                'post_type' => $query->get( 'post_type' ),
                'product_cat' => $query->get( 'product_cat' ),
                'tax_query' => $query->get( 'tax_query' ),
                'meta_query' => $query->get( 'meta_query' ),
                'raw_get' => $raw_args,
                'clean_get' => $clean,
            ) );
        }

        $tax_query = $query->get( 'tax_query', array() );
        if ( ! is_array( $tax_query ) ) {
            $tax_query = array();
        }

        $new_tax_query = array();
        foreach ( $request['filters'] as $taxonomy => $terms ) {
            if ( ! taxonomy_exists( $taxonomy ) || empty( $terms ) ) {
                continue;
            }

            $new_tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $terms,
                'operator' => 'IN',
            );
        }

        if ( ! empty( $new_tax_query ) ) {
            $tax_query = array_merge( $tax_query, $new_tax_query );
            if ( ! isset( $tax_query['relation'] ) ) {
                $tax_query['relation'] = 'AND';
            }
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

        $applied_price = false;
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
            $applied_price = true;
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }

        if ( $has_filter_params ) {
            aegis_plp_filters_debug_log( 'query-applied', array(
                'parsed_request' => $request,
                'tax_query' => $query->get( 'tax_query' ),
                'meta_query' => $query->get( 'meta_query' ),
                'applied_price' => $applied_price,
            ) );
        }
        return;
    }

    if ( ! aegis_plp_filters_is_other_product_cat_context() ) {
        return;
    }

    $request = aegis_plp_filters_parse_other_request();
    $tax_query = $query->get( 'tax_query', array() );
    if ( ! is_array( $tax_query ) ) {
        $tax_query = array();
    }

    if ( ! empty( $request['filter_cat'] ) ) {
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => $request['filter_cat'],
            'operator' => 'IN',
        );
    }

    if ( ! empty( $request['filter_color'] ) ) {
        $tax_query[] = array(
            'taxonomy' => 'pa_color',
            'field' => 'slug',
            'terms' => $request['filter_color'],
            'operator' => 'IN',
        );
    }

    if ( ! empty( $request['filter_size'] ) ) {
        $tax_query[] = array(
            'taxonomy' => 'pa_size',
            'field' => 'slug',
            'terms' => $request['filter_size'],
            'operator' => 'IN',
        );
    }

    if ( count( $tax_query ) > 1 && ! isset( $tax_query['relation'] ) ) {
        $tax_query['relation'] = 'AND';
    }

    if ( ! empty( $tax_query ) ) {
        $query->set( 'tax_query', $tax_query );
    }
}
