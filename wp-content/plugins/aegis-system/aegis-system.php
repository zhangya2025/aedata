<?php
/**
 * Plugin Name: AEGIS System
 * Description: AEGIS 系统骨架插件，提供模块管理功能。
 * Version: 0.1.0
 * Author: AEGIS
 */

if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_System {
    const OPTION_KEY = 'aegis_system_modules';

    /**
     * 预置模块注册表。
     *
     * @return array
     */
    public static function get_registered_modules() {
        return [
            'core_manager'   => ['label' => '核心管理', 'default' => true],
            'access_audit'   => ['label' => '访问审计', 'default' => false],
            'assets_media'   => ['label' => '资产与媒体', 'default' => false],
            'sku'            => ['label' => 'SKU', 'default' => false],
            'dealer_master'  => ['label' => '经销商主数据', 'default' => false],
            'codes'          => ['label' => '编码管理', 'default' => false],
            'shipments'      => ['label' => '出货管理', 'default' => false],
            'public_query'   => ['label' => '公开查询', 'default' => false],
            'reset_b'        => ['label' => '重置 B', 'default' => false],
            'orders'         => ['label' => '订单', 'default' => false],
            'payments'       => ['label' => '支付', 'default' => false],
            'reports'        => ['label' => '报表', 'default' => false],
            'monitoring'     => ['label' => '监控', 'default' => false],
        ];
    }

    /**
     * 初始化钩子。
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
    }

    /**
     * 激活时初始化模块状态。
     */
    public static function activate() {
        $stored = get_option(self::OPTION_KEY);
        if (!is_array($stored)) {
            $stored = [];
        }

        $modules = self::get_registered_modules();
        foreach ($modules as $slug => $module) {
            if (!isset($stored[$slug])) {
                $stored[$slug] = !empty($module['default']);
            }
        }

        update_option(self::OPTION_KEY, $stored, true);
    }

    /**
     * 后台菜单注册。
     */
    public function register_admin_menu() {
        add_menu_page(
            'AEGIS-SYSTEM',
            'AEGIS-SYSTEM',
            'manage_options',
            'aegis-system',
            [$this, 'render_module_manager'],
            'dashicons-admin-generic',
            56
        );

        // 将来根据模块启用状态挂载子菜单，可在此预留。
        $states = $this->get_module_states();
        foreach (self::get_registered_modules() as $slug => $module) {
            if ($slug === 'core_manager') {
                continue; // 核心管理仅用于驱动，不暴露额外菜单。
            }
            if (!empty($states[$slug])) {
                // 占位：模块启用后可在此添加对应的菜单项。
                // add_submenu_page(...);
            }
        }
    }

    /**
     * 模块管理页渲染。
     */
    public function render_module_manager() {
        if (!current_user_can('manage_options')) {
            wp_die(__('您无权访问该页面。'));
        }

        $modules = self::get_registered_modules();
        $states = $this->get_module_states();

        if (isset($_POST['aegis_system_nonce']) && wp_verify_nonce($_POST['aegis_system_nonce'], 'aegis_system_save_modules')) {
            $new_states = [];
            foreach ($modules as $slug => $module) {
                if ($slug === 'core_manager') {
                    $new_states[$slug] = true;
                    continue;
                }
                $new_states[$slug] = isset($_POST['modules'][$slug]) ? true : false;
            }
            $this->save_module_states($new_states);
            $states = $this->get_module_states();
            echo '<div class="updated"><p>模块配置已保存。</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>模块管理</h1>';
        echo '<form method="post">';
        wp_nonce_field('aegis_system_save_modules', 'aegis_system_nonce');
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead><tr><th>模块</th><th>启用</th><th>说明</th></tr></thead>';
        echo '<tbody>';

        foreach ($modules as $slug => $module) {
            $enabled = !empty($states[$slug]);
            $disabled_attr = $slug === 'core_manager' ? 'disabled="disabled"' : '';
            $checked_attr = $enabled ? 'checked="checked"' : '';
            $label = isset($module['label']) ? $module['label'] : $slug;
            echo '<tr>';
            echo '<td><strong>' . esc_html($label) . '</strong><br /><code>' . esc_html($slug) . '</code></td>';
            echo '<td style="width:120px;">';
            echo '<input type="checkbox" name="modules[' . esc_attr($slug) . ']" ' . $checked_attr . ' ' . $disabled_attr . ' />';
            if ($slug === 'core_manager') {
                echo '<p style="margin:4px 0 0;color:#666;">核心模块始终启用</p>';
            }
            echo '</td>';
            echo '<td>—</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        submit_button('保存配置');
        echo '</form>';
        echo '</div>';
    }

    /**
     * 读取模块状态。
     *
     * @return array
     */
    public function get_module_states() {
        $states = get_option(self::OPTION_KEY, []);
        if (!is_array($states)) {
            $states = [];
        }

        $modules = self::get_registered_modules();
        foreach ($modules as $slug => $module) {
            if (!isset($states[$slug])) {
                $states[$slug] = !empty($module['default']);
            }
        }

        return $states;
    }

    /**
     * 保存模块状态。
     *
     * @param array $states
     */
    public function save_module_states($states) {
        $modules = self::get_registered_modules();
        $clean = [];
        foreach ($modules as $slug => $module) {
            if ($slug === 'core_manager') {
                $clean[$slug] = true;
                continue;
            }
            $clean[$slug] = !empty($states[$slug]);
        }

        update_option(self::OPTION_KEY, $clean);
    }
}

new AEGIS_System();
