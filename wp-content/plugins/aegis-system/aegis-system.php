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
    const AUDIT_TABLE = 'aegis_audit_events';

    const ACTION_MODULE_ENABLE = 'MODULE_ENABLE';
    const ACTION_MODULE_DISABLE = 'MODULE_DISABLE';
    const ACTION_MODULE_UNINSTALL = 'MODULE_UNINSTALL';

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
        add_action('plugins_loaded', [__CLASS__, 'maybe_install_tables']);
    }

    /**
     * 激活时初始化模块状态。
     */
    public static function activate() {
        self::maybe_install_tables();
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

        $validation = ['success' => true, 'message' => ''];
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => 'manage_options',
                    'nonce_field'     => 'aegis_system_nonce',
                    'nonce_action'    => 'aegis_system_save_modules',
                    'whitelist'       => ['aegis_system_nonce', 'modules', '_wp_http_referer', '_aegis_idempotency'],
                    'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                ]
            );
        }

        if ($validation['success'] && 'POST' === $_SERVER['REQUEST_METHOD']) {
            $new_states = [];
            foreach ($modules as $slug => $module) {
                if ($slug === 'core_manager') {
                    $new_states[$slug] = true;
                    continue;
                }
                $new_states[$slug] = isset($_POST['modules'][$slug]) ? true : false;
            }
            $this->save_module_states($new_states, $states);
            $states = $this->get_module_states();
            echo '<div class="updated"><p>模块配置已保存。</p></div>';
        } elseif (!empty($validation['message'])) {
            echo '<div class="error"><p>' . esc_html($validation['message']) . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>模块管理</h1>';
        $idempotency_key = wp_generate_uuid4();
        echo '<form method="post">';
        wp_nonce_field('aegis_system_save_modules', 'aegis_system_nonce');
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';
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
     * 保存模块状态并写入审计。
     *
     * @param array $states
     * @param array $previous_states
     */
    public function save_module_states($states, $previous_states = []) {
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

        foreach ($clean as $slug => $enabled) {
            $previous = !empty($previous_states[$slug]);
            if ($enabled === $previous) {
                continue;
            }
            $action = $enabled ? self::ACTION_MODULE_ENABLE : self::ACTION_MODULE_DISABLE;
            AEGIS_Access_Audit::record_event(
                $action,
                'SUCCESS',
                [
                    'module' => $slug,
                ]
            );
        }
    }

    /**
     * 安装或升级审计表。
     */
    public static function maybe_install_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::AUDIT_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_id BIGINT(20) UNSIGNED NULL,
            actor_login VARCHAR(60) NULL,
            action VARCHAR(64) NOT NULL,
            result VARCHAR(20) NOT NULL,
            object_data LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

class AEGIS_Access_Audit {
    /**
     * 验证写入口：统一鉴权、nonce、白名单、可选幂等键。
     *
     * @param array $params
     * @param array $config
     * @return array
     */
    public static function validate_write_request($params, $config = []) {
        $defaults = [
            'capability'      => 'manage_options',
            'nonce_field'     => null,
            'nonce_action'    => null,
            'whitelist'       => [],
            'idempotency_key' => null,
        ];
        $config = wp_parse_args($config, $defaults);

        if (!current_user_can($config['capability'])) {
            self::record_event('ACCESS_DENIED', 'FAIL', ['reason' => 'capability']);
            return [
                'success' => false,
                'message' => '权限不足。',
            ];
        }

        if ($config['nonce_field'] && $config['nonce_action']) {
            $nonce_value = isset($params[$config['nonce_field']]) ? $params[$config['nonce_field']] : '';
            if (!wp_verify_nonce($nonce_value, $config['nonce_action'])) {
                self::record_event('NONCE_INVALID', 'FAIL', ['nonce_field' => $config['nonce_field']]);
                return [
                    'success' => false,
                    'message' => '安全校验失败，请重试。',
                ];
            }
        }

        if (!empty($config['whitelist'])) {
            $extra_keys = array_diff(array_keys($params), $config['whitelist']);
            if (!empty($extra_keys)) {
                self::record_event('PARAMS_NOT_ALLOWED', 'FAIL', ['keys' => array_values($extra_keys)]);
                return [
                    'success' => false,
                    'message' => '请求参数不被允许。',
                ];
            }
        }

        if (!empty($config['idempotency_key'])) {
            $idem_key = 'aegis_idem_' . md5($config['idempotency_key']);
            if (get_transient($idem_key)) {
                self::record_event('IDEMPOTENT_REPLAY', 'FAIL', ['key' => $config['idempotency_key']]);
                return [
                    'success' => false,
                    'message' => '请求已处理，请勿重复提交。',
                ];
            }
            set_transient($idem_key, 1, MINUTE_IN_SECONDS * 10);
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    /**
     * 写入审计事件。
     *
     * @param string $action
     * @param string $result
     * @param array  $object_data
     */
    public static function record_event($action, $result, $object_data = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . AEGIS_System::AUDIT_TABLE;

        $current_user = wp_get_current_user();
        $actor_id = $current_user && $current_user->ID ? (int) $current_user->ID : null;
        $actor_login = $current_user && $current_user->user_login ? $current_user->user_login : null;

        $wpdb->insert(
            $table_name,
            [
                'actor_id'    => $actor_id,
                'actor_login' => $actor_login,
                'action'      => $action,
                'result'      => $result,
                'object_data' => !empty($object_data) ? wp_json_encode($object_data) : null,
                'created_at'  => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );
    }
}

new AEGIS_System();
