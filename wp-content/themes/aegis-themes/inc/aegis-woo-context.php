<?php

function aegis_is_sleepingbags_or_descendant() {
    if ( ! function_exists( 'is_tax' ) || ! is_tax( 'product_cat' ) ) {
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
