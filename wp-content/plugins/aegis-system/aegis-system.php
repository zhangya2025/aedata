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
    const TYPOGRAPHY_OPTION = 'aegis_system_typography';
    const AUDIT_TABLE = 'aegis_audit_events';
    const MEDIA_TABLE = 'aegis_media_files';

    const ACTION_MODULE_ENABLE = 'MODULE_ENABLE';
    const ACTION_MODULE_DISABLE = 'MODULE_DISABLE';
    const ACTION_MODULE_UNINSTALL = 'MODULE_UNINSTALL';
    const ACTION_MEDIA_UPLOAD = 'MEDIA_UPLOAD';
    const ACTION_MEDIA_DOWNLOAD = 'MEDIA_DOWNLOAD';
    const ACTION_MEDIA_DOWNLOAD_DENY = 'MEDIA_DOWNLOAD_DENY';

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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('rest_api_init', ['AEGIS_Assets_Media', 'register_rest_routes']);
        add_filter('query_vars', ['AEGIS_Assets_Media', 'register_query_vars']);
        add_action('template_redirect', ['AEGIS_Assets_Media', 'maybe_serve_media']);
        add_shortcode('aegis_system_page', ['AEGIS_Assets_Media', 'render_frontend_container']);
        add_action('wp_enqueue_scripts', ['AEGIS_Assets_Media', 'enqueue_front_assets']);
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        add_action('plugins_loaded', [__CLASS__, 'maybe_install_tables']);
    }

    /**
     * 激活时初始化模块状态。
     */
    public static function activate() {
        self::maybe_install_tables();
        AEGIS_Assets_Media::ensure_upload_structure();
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

        $states = $this->get_module_states();
        if (!empty($states['assets_media'])) {
            add_submenu_page(
                'aegis-system',
                '排版设置',
                '全局设置',
                'manage_options',
                'aegis-system-typography',
                ['AEGIS_Assets_Media', 'render_typography_settings']
            );
        }

        // 将来根据模块启用状态挂载子菜单，可在此预留。
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
     * 后台按需加载资源。
     *
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        $screens = [
            'toplevel_page_aegis-system',
            'aegis-system_page_aegis-system-typography',
        ];

        if (!in_array($hook, $screens, true)) {
            return;
        }

        wp_register_style('aegis-system-admin-style', false, [], '0.1.0');
        wp_register_script('aegis-system-admin-js', false, [], '0.1.0', true);
        wp_add_inline_style('aegis-system-admin-style', AEGIS_Assets_Media::build_typography_css());
        wp_add_inline_script('aegis-system-admin-js', 'window["aegis-system"] = window["aegis-system"] || { pages: {} };');

        wp_enqueue_style('aegis-system-admin-style');
        wp_enqueue_script('aegis-system-admin-js');
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
        echo '<div class="notice notice-info"><p>“资产与媒体”模块启用后可配置排版等级与附件上传。</p></div>';
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
     * 检查模块是否启用。
     *
     * @param string $slug
     * @return bool
     */
    public static function is_module_enabled($slug) {
        $states = get_option(self::OPTION_KEY, []);
        return !empty($states[$slug]);
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

        $media_table = $wpdb->prefix . self::MEDIA_TABLE;
        $media_sql = "CREATE TABLE {$media_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_type VARCHAR(64) NOT NULL,
            owner_id BIGINT(20) UNSIGNED NULL,
            file_path TEXT NOT NULL,
            mime VARCHAR(191) NULL,
            file_hash VARCHAR(128) NULL,
            visibility VARCHAR(32) NOT NULL DEFAULT 'private',
            uploaded_by BIGINT(20) UNSIGNED NULL,
            uploaded_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY owner (owner_type, owner_id),
            KEY visibility (visibility),
            KEY uploaded_at (uploaded_at)
        ) {$charset_collate};";
        dbDelta($media_sql);
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

class AEGIS_Assets_Media {
    const UPLOAD_ROOT = 'aegis-system';
    const FRONT_SHORTCODE = 'aegis_system_page';
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_SENSITIVE = 'sensitive';

    /**
     * 确保上传目录与防直链文件存在。
     */
    public static function ensure_upload_structure() {
        $upload_dir = wp_upload_dir();
        $base = trailingslashit($upload_dir['basedir']) . self::UPLOAD_ROOT;
        $buckets = ['sku', 'dealer', 'payments', 'exports', 'temp', 'certificate'];

        if (!file_exists($base)) {
            wp_mkdir_p($base);
        }

        $htaccess = $base . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        $index = $base . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden.\n");
        }

        foreach ($buckets as $bucket) {
            $bucket_path = trailingslashit($base) . $bucket;
            if (!file_exists($bucket_path)) {
                wp_mkdir_p($bucket_path);
            }
        }
    }

    /**
     * 排版配置默认值。
     *
     * @return array
     */
    public static function get_typography_defaults() {
        return [
            'A1' => ['size' => '2.4', 'line' => '3.2'],
            'A2' => ['size' => '2.0', 'line' => '2.8'],
            'A3' => ['size' => '1.8', 'line' => '2.6'],
            'A4' => ['size' => '1.6', 'line' => '2.2'],
            'A5' => ['size' => '1.4', 'line' => '2.0'],
            'A6' => ['size' => '1.2', 'line' => '1.8'],
        ];
    }

    /**
     * 允许的表单键。
     *
     * @return array
     */
    public static function allowed_typography_keys() {
        $keys = [];
        foreach (array_keys(self::get_typography_defaults()) as $key) {
            $keys[] = $key . '_size';
            $keys[] = $key . '_line';
        }
        return $keys;
    }

    /**
     * 获取排版设置。
     *
     * @return array
     */
    public static function get_typography_settings() {
        $stored = get_option(AEGIS_System::TYPOGRAPHY_OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $defaults = self::get_typography_defaults();
        foreach ($defaults as $level => $values) {
            if (!isset($stored[$level]['size'])) {
                $stored[$level]['size'] = $values['size'];
            }
            if (!isset($stored[$level]['line'])) {
                $stored[$level]['line'] = $values['line'];
            }
        }

        return $stored;
    }

    /**
     * 解析并清洗排版 POST 数据。
     *
     * @param array $params
     * @return array
     */
    public static function parse_typography_post($params) {
        $settings = [];
        foreach (self::get_typography_defaults() as $key => $defaults) {
            $size_key = $key . '_size';
            $line_key = $key . '_line';
            $size_val = isset($params[$size_key]) ? (float) $params[$size_key] : (float) $defaults['size'];
            $line_val = isset($params[$line_key]) ? (float) $params[$line_key] : (float) $defaults['line'];

            $settings[$key] = [
                'size' => $size_val > 0 ? $size_val : (float) $defaults['size'],
                'line' => $line_val > 0 ? $line_val : (float) $defaults['line'],
            ];
        }

        return $settings;
    }

    /**
     * 后台排版设置渲染。
     */
    public static function render_typography_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('assets_media')) {
            echo '<div class="wrap"><h1>全局设置</h1><div class="notice notice-warning"><p>请先启用“资产与媒体”模块。</p></div></div>';
            return;
        }

        $settings = self::get_typography_settings();
        $validation = ['success' => true, 'message' => ''];
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'   => 'manage_options',
                    'nonce_field'  => 'aegis_typography_nonce',
                    'nonce_action' => 'aegis_typography_save',
                    'whitelist'    => array_merge(['aegis_typography_nonce', '_wp_http_referer'], self::allowed_typography_keys()),
                ]
            );
        }

        if ($validation['success'] && 'POST' === $_SERVER['REQUEST_METHOD']) {
            $settings = self::parse_typography_post($_POST);
            update_option(AEGIS_System::TYPOGRAPHY_OPTION, $settings);
            echo '<div class="updated"><p>排版配置已保存。</p></div>';
        } elseif (!empty($validation['message'])) {
            echo '<div class="error"><p>' . esc_html($validation['message']) . '</p></div>';
        }

        echo '<div class="wrap aegis-system-root">';
        echo '<h1>排版设置（Typography）</h1>';
        echo '<form method="post">';
        wp_nonce_field('aegis_typography_save', 'aegis_typography_nonce');

        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead><tr><th>等级</th><th>字号 (rem)</th><th>行高 (rem)</th></tr></thead>';
        echo '<tbody>';
        foreach (self::get_typography_defaults() as $key => $defaults) {
            $size = isset($settings[$key]['size']) ? $settings[$key]['size'] : $defaults['size'];
            $line = isset($settings[$key]['line']) ? $settings[$key]['line'] : $defaults['line'];
            echo '<tr>';
            echo '<td><strong>' . esc_html($key) . '</strong></td>';
            echo '<td><input type="number" step="0.1" min="0.5" name="' . esc_attr($key) . '_size" value="' . esc_attr($size) . '" /></td>';
            echo '<td><input type="number" step="0.1" min="0.5" name="' . esc_attr($key) . '_line" value="' . esc_attr($line) . '" /></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        submit_button('保存配置');
        echo '</form>';
        echo '</div>';
    }

    /**
     * 构造排版 CSS。
     *
     * @return string
     */
    public static function build_typography_css() {
        $settings = self::get_typography_settings();
        $css = '.aegis-system-root{';
        foreach ($settings as $key => $values) {
            $lower = strtolower($key);
            $css .= '--aegis-' . $lower . '-size:' . $values['size'] . 'rem;';
            $css .= '--aegis-' . $lower . '-line:' . $values['line'] . 'rem;';
        }
        $css .= '}' . PHP_EOL;

        foreach ($settings as $key => $values) {
            $lower = strtolower($key);
            $css .= '.aegis-system-root .aegis-t-' . $lower . '{font-size:var(--aegis-' . $lower . '-size);line-height:var(--aegis-' . $lower . '-line);}';
        }

        return $css;
    }

    /**
     * 在前台有短码时按需加载样式。
     */
    public static function enqueue_front_assets() {
        if (!AEGIS_System::is_module_enabled('assets_media')) {
            return;
        }

        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        if (has_shortcode($post->post_content, self::FRONT_SHORTCODE)) {
            wp_register_style('aegis-system-frontend-style', false, [], '0.1.0');
            wp_add_inline_style('aegis-system-frontend-style', self::build_typography_css());
            wp_enqueue_style('aegis-system-frontend-style');
        }
    }

    /**
     * 前台短码容器。
     *
     * @return string
     */
    public static function render_frontend_container() {
        if (!AEGIS_System::is_module_enabled('assets_media')) {
            return '';
        }

        wp_register_style('aegis-system-frontend-style', false, [], '0.1.0');
        wp_add_inline_style('aegis-system-frontend-style', self::build_typography_css());
        wp_enqueue_style('aegis-system-frontend-style');

        $output  = '<div class="aegis-system-root">';
        $output .= '<div class="aegis-t-a3">AEGIS System 容器</div>';
        $output .= '</div>';
        return $output;
    }

    /**
     * 注册 REST 路由。
     */
    public static function register_rest_routes() {
        register_rest_route(
            'aegis-system/v1',
            '/media/upload',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'handle_upload'],
                'permission_callback' => function () {
                    return AEGIS_System::is_module_enabled('assets_media') && current_user_can('manage_options');
                },
            ]
        );

        register_rest_route(
            'aegis-system/v1',
            '/media/download/(?P<id>\d+)',
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'handle_download_api'],
                'permission_callback' => function () {
                    return AEGIS_System::is_module_enabled('assets_media');
                },
            ]
        );
    }

    /**
     * 允许自定义查询变量用于下载网关。
     */
    public static function register_query_vars($vars) {
        $vars[] = 'aegis_media';
        return $vars;
    }

    /**
     * 模板重定向阶段输出附件。
     */
    public static function maybe_serve_media() {
        if (!AEGIS_System::is_module_enabled('assets_media')) {
            return;
        }

        $id = get_query_var('aegis_media');
        if (!$id) {
            return;
        }

        self::stream_media((int) $id);
    }

    /**
     * 处理上传逻辑。
     */
    public static function handle_upload($request) {
        if (!AEGIS_System::is_module_enabled('assets_media')) {
            return new WP_REST_Response(['message' => '模块未启用'], 403);
        }

        self::ensure_upload_structure();

        $params = $request instanceof WP_REST_Request ? $request->get_params() : [];
        $validation = AEGIS_Access_Audit::validate_write_request(
            $params,
            [
                'capability'   => 'manage_options',
                'nonce_field'  => '_wpnonce',
                'nonce_action' => 'wp_rest',
                'whitelist'    => ['_wpnonce', 'bucket', 'owner_type', 'owner_id', 'visibility', 'meta'],
            ]
        );

        if (!$validation['success']) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_UPLOAD, 'FAIL', ['reason' => $validation['message']]);
            return new WP_REST_Response(['message' => $validation['message']], 400);
        }

        $bucket = isset($params['bucket']) ? sanitize_key($params['bucket']) : 'temp';
        $allowed_buckets = ['sku', 'dealer', 'payments', 'exports', 'temp', 'certificate'];
        if (!in_array($bucket, $allowed_buckets, true)) {
            $bucket = 'temp';
        }

        $owner_type = isset($params['owner_type']) ? sanitize_key($params['owner_type']) : '';
        $owner_id = isset($params['owner_id']) ? (int) $params['owner_id'] : null;
        $visibility = isset($params['visibility']) ? sanitize_key($params['visibility']) : self::VISIBILITY_PRIVATE;

        if ('certificate' === $owner_type && self::VISIBILITY_PUBLIC !== $visibility) {
            $visibility = self::VISIBILITY_PUBLIC;
        }

        $sensitive_types = ['business_license', 'payment_receipt', 'payment_voucher'];
        if (in_array($owner_type, $sensitive_types, true)) {
            $visibility = self::VISIBILITY_SENSITIVE;
        }

        if (!isset($_FILES['file'])) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_UPLOAD, 'FAIL', ['reason' => 'missing_file']);
            return new WP_REST_Response(['message' => '未找到上传文件'], 400);
        }

        $upload_override = ['test_form' => false];
        $dir_filter = function ($uploads) use ($bucket) {
            $uploads['subdir'] = '/' . AEGIS_Assets_Media::UPLOAD_ROOT . '/' . $bucket;
            $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
            $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
            return $uploads;
        };
        add_filter('upload_dir', $dir_filter);

        $result = wp_handle_upload($_FILES['file'], $upload_override);
        remove_filter('upload_dir', $dir_filter);

        if (isset($result['error'])) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_UPLOAD, 'FAIL', ['reason' => $result['error']]);
            return new WP_REST_Response(['message' => $result['error']], 400);
        }

        $file_path = str_replace(trailingslashit(wp_upload_dir()['basedir']), '', $result['file']);
        $hash = hash_file('sha256', $result['file']);
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $wpdb->insert(
            $table,
            [
                'owner_type'  => $owner_type,
                'owner_id'    => $owner_id,
                'file_path'   => ltrim($file_path, '/'),
                'mime'        => isset($result['type']) ? $result['type'] : null,
                'file_hash'   => $hash,
                'visibility'  => $visibility,
                'uploaded_by' => get_current_user_id(),
                'uploaded_at' => current_time('mysql'),
                'meta'        => isset($params['meta']) ? wp_json_encode($params['meta']) : null,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        $id = $wpdb->insert_id;
        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_MEDIA_UPLOAD,
            'SUCCESS',
            [
                'id'          => $id,
                'owner_type'  => $owner_type,
                'owner_id'    => $owner_id,
                'visibility'  => $visibility,
                'bucket'      => $bucket,
                'file'        => basename($result['file']),
            ]
        );

        return new WP_REST_Response(
            [
                'id'         => $id,
                'path'       => $file_path,
                'visibility' => $visibility,
                'mime'       => isset($result['type']) ? $result['type'] : '',
            ],
            200
        );
    }

    /**
     * 处理 REST 下载。
     */
    public static function handle_download_api($request) {
        $id = $request instanceof WP_REST_Request ? (int) $request->get_param('id') : 0;
        self::stream_media($id);
    }

    /**
     * 按鉴权输出媒体文件。
     *
     * @param int $id
     */
    public static function stream_media($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id));

        if (!$record) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_DOWNLOAD_DENY, 'FAIL', ['id' => $id, 'reason' => 'not_found']);
            status_header(404);
            exit;
        }

        $is_public_certificate = (self::VISIBILITY_PUBLIC === $record->visibility && 'certificate' === $record->owner_type);
        if (!$is_public_certificate && !current_user_can('manage_options')) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_DOWNLOAD_DENY, 'FAIL', ['id' => $id, 'reason' => 'forbidden']);
            status_header(403);
            exit;
        }

        $file_full_path = trailingslashit(wp_upload_dir()['basedir']) . $record->file_path;
        if (!file_exists($file_full_path)) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_DOWNLOAD_DENY, 'FAIL', ['id' => $id, 'reason' => 'missing_file']);
            status_header(404);
            exit;
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_MEDIA_DOWNLOAD,
            'SUCCESS',
            [
                'id'         => $id,
                'visibility' => $record->visibility,
                'owner_type' => $record->owner_type,
            ]
        );

        $mime = $record->mime ? $record->mime : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file_full_path));
        header('Content-Disposition: attachment; filename="' . basename($file_full_path) . '"');
        readfile($file_full_path);
        exit;
    }
}

new AEGIS_System();
