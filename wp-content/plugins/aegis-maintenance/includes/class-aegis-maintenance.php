<?php

if (!defined('ABSPATH')) {
    exit;
}

class Aegis_Maintenance
{
    const OPTION_KEY = 'aegis_maintenance_settings';

    public static function get_default_options()
    {
        return [
            'enabled' => true,
            'title_text' => '网站维护中',
            'title_size_px' => 40,
            'title_color' => '#111111',
            'reason_text' => '请稍后再试',
            'reason_size_px' => 16,
            'reason_color' => '#333333',
            'allow_roles' => ['administrator'],
            'allow_paths' => '',
            'send_503' => true,
            'retry_after_minutes' => null,
            'noindex' => true,
        ];
    }

    public static function get_options()
    {
        $defaults = self::get_default_options();
        $options = get_option(self::OPTION_KEY, []);

        if (!is_array($options)) {
            $options = [];
        }

        return wp_parse_args($options, $defaults);
    }

    public static function activate()
    {
        $existing = get_option(self::OPTION_KEY);
        if (!is_array($existing)) {
            add_option(self::OPTION_KEY, self::get_default_options());
            return;
        }

        $defaults = self::get_default_options();
        if (!isset($existing['enabled'])) {
            $existing['enabled'] = true;
        }
        update_option(self::OPTION_KEY, wp_parse_args($existing, $defaults));
    }

    public function run()
    {
        if (is_admin()) {
            $admin = new Aegis_Maintenance_Admin();
            $admin->init();
        }

        if (!is_admin()) {
            $guard = new Aegis_Maintenance_Guard();
            $guard->init();
        }
    }
}
