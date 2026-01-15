<?php

function aegis_is_term_or_descendant( $taxonomy, $slug ) {
    if ( ! function_exists( 'is_tax' ) || ! is_tax( $taxonomy ) ) {
        return false;
    }

    $term = get_queried_object();
    if ( ! $term || empty( $term->term_id ) ) {
        return false;
    }

    $root = get_term_by( 'slug', $slug, $taxonomy );
    if ( ! $root || is_wp_error( $root ) ) {
        return false;
    }

    if ( (int) $term->term_id === (int) $root->term_id ) {
        return true;
    }

    $ancestors = get_ancestors( (int) $term->term_id, $taxonomy );
    return in_array( (int) $root->term_id, $ancestors, true );
}

function aegis_is_sleepingbags_or_descendant() {
    return aegis_is_term_or_descendant( 'product_cat', 'sleepingbags' );
}
