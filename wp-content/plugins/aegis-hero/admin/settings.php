<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'aegis_hero_register_settings_page');
add_action('admin_init', 'aegis_hero_register_settings');

/**
 * Register settings page under Settings menu.
 */
function aegis_hero_register_settings_page()
{
    add_menu_page(
        __('Aegis Hero', 'aegis-hero'),
        __('Aegis Hero', 'aegis-hero'),
        'manage_options',
        'aegis-hero',
        'aegis_hero_render_settings_page',
        'dashicons-format-gallery',
        56
    );

    add_submenu_page(
        'aegis-hero',
        __('Settings', 'aegis-hero'),
        __('Settings', 'aegis-hero'),
        'manage_options',
        'aegis-hero',
        'aegis_hero_render_settings_page'
    );

    add_submenu_page(
        'aegis-hero',
        __('Heroes', 'aegis-hero'),
        __('Heroes', 'aegis-hero'),
        'manage_options',
        'edit.php?post_type=aegis_hero'
    );

    add_submenu_page(
        'aegis-hero',
        __('Add New', 'aegis-hero'),
        __('Add New', 'aegis-hero'),
        'manage_options',
        'post-new.php?post_type=aegis_hero'
    );

    add_submenu_page(
        'options-general.php',
        __('Aegis Hero', 'aegis-hero'),
        __('Aegis Hero', 'aegis-hero'),
        'manage_options',
        'aegis-hero',
        'aegis_hero_render_settings_page'
    );
}

/**
 * Register plugin settings.
 */
function aegis_hero_register_settings()
{
    register_setting(
        'aegis_hero_options',
        'aegis_hero_allow_external',
        [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function ($value) {
                return (bool) $value;
            },
        ]
    );

    register_setting(
        'aegis_hero_options',
        'aegis_hero_allowlist',
        [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'aegis_hero_sanitize_allowlist',
        ]
    );
}

/**
 * Render settings page content.
 */
function aegis_hero_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Aegis Hero', 'aegis-hero'); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('aegis_hero_options'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Allow external embeds', 'aegis-hero'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aegis_hero_allow_external" value="1" <?php checked(get_option('aegis_hero_allow_external', false)); ?>>
                                <?php esc_html_e('Enable embedding external videos (YouTube)', 'aegis-hero'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('When disabled, external slides only show the poster without loading iframes.', 'aegis-hero'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('External embed allowlist', 'aegis-hero'); ?></th>
                        <td>
                            <textarea name="aegis_hero_allowlist" rows="4" cols="50" class="large-text code"><?php echo esc_textarea(get_option('aegis_hero_allowlist', '')); ?></textarea>
                            <p class="description"><?php esc_html_e('One domain per line. Only youtube.com, youtu.be, youtube-nocookie.com are supported in v1.', 'aegis-hero'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Sanitize allowlist value.
 */
function aegis_hero_sanitize_allowlist($value)
{
    $lines = array_filter(array_map('trim', explode("\n", (string) $value)));
    $allowed_hosts = ['youtube.com', 'youtu.be', 'youtube-nocookie.com'];
    $clean = [];
    foreach ($lines as $line) {
        $host = strtolower($line);
        if (in_array($host, $allowed_hosts, true)) {
            $clean[] = $host;
        }
    }

    return implode("\n", array_unique($clean));
}
