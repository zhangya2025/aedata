<?php

if (!defined('ABSPATH')) {
    exit;
}

class Aegis_Maintenance_Admin
{
    public function init()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings_page()
    {
        add_options_page(
            __('维护设置', 'aegis-maintenance'),
            __('Aegis Maintenance', 'aegis-maintenance'),
            'manage_options',
            'aegis-maintenance',
            [$this, 'render_page']
        );
    }

    public function register_settings()
    {
        register_setting(
            'aegis_maintenance_group',
            Aegis_Maintenance::OPTION_KEY,
            ['sanitize_callback' => [$this, 'sanitize_options']]
        );

        add_settings_section(
            'aegis_maintenance_section',
            __('维护模式设置', 'aegis-maintenance'),
            '__return_false',
            'aegis-maintenance'
        );

        add_settings_field(
            'enabled',
            __('启用维护模式', 'aegis-maintenance'),
            [$this, 'render_enabled_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'title_text',
            __('标题文本', 'aegis-maintenance'),
            [$this, 'render_title_text_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'title_size_px',
            __('标题字号(px)', 'aegis-maintenance'),
            [$this, 'render_title_size_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'title_color',
            __('标题颜色', 'aegis-maintenance'),
            [$this, 'render_title_color_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'reason_text',
            __('原因文本', 'aegis-maintenance'),
            [$this, 'render_reason_text_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'reason_size_px',
            __('原因字号(px)', 'aegis-maintenance'),
            [$this, 'render_reason_size_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'reason_color',
            __('原因颜色', 'aegis-maintenance'),
            [$this, 'render_reason_color_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'allow_roles',
            __('角色豁免', 'aegis-maintenance'),
            [$this, 'render_allow_roles_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'allow_paths',
            __('页面豁免', 'aegis-maintenance'),
            [$this, 'render_allow_paths_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'send_503',
            __('返回 503 状态码', 'aegis-maintenance'),
            [$this, 'render_send_503_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'retry_after_minutes',
            __('Retry-After (分钟)', 'aegis-maintenance'),
            [$this, 'render_retry_after_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );

        add_settings_field(
            'noindex',
            __('Robots Noindex', 'aegis-maintenance'),
            [$this, 'render_noindex_field'],
            'aegis-maintenance',
            'aegis_maintenance_section'
        );
    }

    public function sanitize_options($input)
    {
        $defaults = Aegis_Maintenance::get_default_options();
        if (!is_array($input)) {
            $input = [];
        }

        $clean = [];
        $clean['enabled'] = !empty($input['enabled']);
        $clean['title_text'] = isset($input['title_text']) ? sanitize_text_field($input['title_text']) : $defaults['title_text'];

        $clean['title_size_px'] = isset($input['title_size_px']) ? intval($input['title_size_px']) : $defaults['title_size_px'];
        $clean['title_size_px'] = max(10, min(120, $clean['title_size_px']));

        $clean['title_color'] = isset($input['title_color']) ? sanitize_hex_color($input['title_color']) : $defaults['title_color'];
        if (empty($clean['title_color'])) {
            $clean['title_color'] = $defaults['title_color'];
        }

        $clean['reason_text'] = isset($input['reason_text']) ? sanitize_textarea_field($input['reason_text']) : $defaults['reason_text'];

        $clean['reason_size_px'] = isset($input['reason_size_px']) ? intval($input['reason_size_px']) : $defaults['reason_size_px'];
        $clean['reason_size_px'] = max(10, min(60, $clean['reason_size_px']));

        $clean['reason_color'] = isset($input['reason_color']) ? sanitize_hex_color($input['reason_color']) : $defaults['reason_color'];
        if (empty($clean['reason_color'])) {
            $clean['reason_color'] = $defaults['reason_color'];
        }

        $clean['allow_roles'] = [];
        if (!empty($input['allow_roles']) && is_array($input['allow_roles'])) {
            global $wp_roles;
            $valid_roles = array_keys($wp_roles->roles);
            foreach ($input['allow_roles'] as $role) {
                $role_key = sanitize_text_field($role);
                if (in_array($role_key, $valid_roles, true)) {
                    $clean['allow_roles'][] = $role_key;
                }
            }
        }
        if (empty($clean['allow_roles'])) {
            $clean['allow_roles'] = ['administrator'];
        }

        $clean['allow_paths'] = '';
        if (isset($input['allow_paths'])) {
            $lines = preg_split('/\r?\n/', $input['allow_paths']);
            $normalized = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if ($line[0] !== '/') {
                    $line = '/' . ltrim($line, '/');
                }
                $normalized[$line] = true;
            }
            if (!empty($normalized)) {
                $clean['allow_paths'] = implode("\n", array_keys($normalized));
            }
        }

        $clean['send_503'] = !empty($input['send_503']);

        if (isset($input['retry_after_minutes']) && $input['retry_after_minutes'] !== '') {
            $clean['retry_after_minutes'] = max(0, intval($input['retry_after_minutes']));
        } else {
            $clean['retry_after_minutes'] = null;
        }

        $clean['noindex'] = !empty($input['noindex']);

        return wp_parse_args($clean, $defaults);
    }

    public function render_page()
    {
        $options = Aegis_Maintenance::get_options();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('维护设置', 'aegis-maintenance'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('aegis_maintenance_group');
                do_settings_sections('aegis-maintenance');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function get_option($key)
    {
        $options = Aegis_Maintenance::get_options();
        return isset($options[$key]) ? $options[$key] : null;
    }

    public function render_enabled_field()
    {
        $value = $this->get_option('enabled');
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[enabled]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('启用维护模式', 'aegis-maintenance'); ?>
        </label>
        <?php
    }

    public function render_title_text_field()
    {
        $value = $this->get_option('title_text');
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[title_text]" value="<?php echo esc_attr($value); ?>">
        <?php
    }

    public function render_title_size_field()
    {
        $value = intval($this->get_option('title_size_px'));
        ?>
        <input type="number" min="10" max="120" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[title_size_px]" value="<?php echo esc_attr($value); ?>">
        <?php
    }

    public function render_title_color_field()
    {
        $value = $this->get_option('title_color');
        ?>
        <input type="color" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[title_color]" value="<?php echo esc_attr($value); ?>">
        <?php
    }

    public function render_reason_text_field()
    {
        $value = $this->get_option('reason_text');
        ?>
        <textarea name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[reason_text]" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php
    }

    public function render_reason_size_field()
    {
        $value = intval($this->get_option('reason_size_px'));
        ?>
        <input type="number" min="10" max="60" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[reason_size_px]" value="<?php echo esc_attr($value); ?>">
        <?php
    }

    public function render_reason_color_field()
    {
        $value = $this->get_option('reason_color');
        ?>
        <input type="color" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[reason_color]" value="<?php echo esc_attr($value); ?>">
        <?php
    }

    public function render_allow_roles_field()
    {
        global $wp_roles;
        $value = $this->get_option('allow_roles');
        foreach ($wp_roles->roles as $role_key => $role) {
            ?>
            <label style="display:block; margin-bottom:4px;">
                <input type="checkbox" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[allow_roles][]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $value, true)); ?>>
                <?php echo esc_html($role['name']); ?>
            </label>
            <?php
        }
    }

    public function render_allow_paths_field()
    {
        $value = $this->get_option('allow_paths');
        ?>
        <textarea name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[allow_paths]" rows="5" class="large-text" placeholder="/wp-json/*&#10;/special-page"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('每行一个路径模式，必须以 / 开头，支持 * 通配单段。', 'aegis-maintenance'); ?></p>
        <?php
    }

    public function render_send_503_field()
    {
        $value = $this->get_option('send_503');
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[send_503]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('返回 503 状态码', 'aegis-maintenance'); ?>
        </label>
        <?php
    }

    public function render_retry_after_field()
    {
        $value = $this->get_option('retry_after_minutes');
        ?>
        <input type="number" min="0" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[retry_after_minutes]" value="<?php echo esc_attr($value); ?>">
        <p class="description"><?php esc_html_e('可选，单位分钟。留空表示不发送 Retry-After。', 'aegis-maintenance'); ?></p>
        <?php
    }

    public function render_noindex_field()
    {
        $value = $this->get_option('noindex');
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(Aegis_Maintenance::OPTION_KEY); ?>[noindex]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('在维护页添加 noindex', 'aegis-maintenance'); ?>
        </label>
        <?php
    }
}
