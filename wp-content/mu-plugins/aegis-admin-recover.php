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

function aegis_admin_recover_ensure_admin_cap()
{
    if (!is_admin()) {
        return;
    }

    $role = get_role('administrator');
    if ($role && !$role->has_cap('manage_options')) {
        $role->add_cap('manage_options');
    }
}

add_action('init', 'aegis_admin_recover_ensure_admin_cap');

add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
    if (!is_admin() || $cap !== 'manage_options') {
        return $caps;
    }

    if (!aegis_admin_recover_is_admin_user($user_id)) {
        return $caps;
    }

    return ['manage_options'];
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

add_action('admin_notices', function () {
    if (!is_admin()) {
        return;
    }

    if (!isset($_GET['aegis_recover_check']) || $_GET['aegis_recover_check'] !== '1') {
        return;
    }

    $user = wp_get_current_user();
    $user_id = $user instanceof WP_User ? (int) $user->ID : 0;
    $roles = $user instanceof WP_User ? implode(',', (array) $user->roles) : '';
    $can_manage = current_user_can('manage_options') ? 'yes' : 'no';

    printf(
        '<div class="notice notice-info"><p>Aegis admin recover check: uid=%d roles=%s manage_options=%s</p></div>',
        $user_id,
        esc_html($roles),
        esc_html($can_manage)
    );
});
