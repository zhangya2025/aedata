<?php
/**
 * Plugin Name: Aegis Admin Recover
 * Description: Restore manage_options for administrators in wp-admin to prevent Settings menu loss.
 */

if (!defined('ABSPATH')) {
    exit;
}

function aegis_admin_recover_is_admin_user($user_id)
{
    if (is_multisite() && is_super_admin($user_id)) {
        return true;
    }

    $user = get_user_by('id', $user_id);
    if (!$user instanceof WP_User) {
        return false;
    }

    return in_array('administrator', (array) $user->roles, true);
}

add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
    if (!is_admin() || $cap !== 'manage_options') {
        return $caps;
    }

    if (!aegis_admin_recover_is_admin_user($user_id)) {
        return $caps;
    }

    $caps = array_diff($caps, ['do_not_allow']);
    $caps[] = 'manage_options';

    return array_values(array_unique($caps));
}, PHP_INT_MAX, 4);

add_filter('user_has_cap', function ($allcaps, $caps, $args, $user) {
    if (!is_admin()) {
        return $allcaps;
    }

    if (!($user instanceof WP_User)) {
        return $allcaps;
    }

    if (!aegis_admin_recover_is_admin_user($user->ID)) {
        return $allcaps;
    }

    $allcaps['manage_options'] = true;

    return $allcaps;
}, PHP_INT_MAX, 4);
