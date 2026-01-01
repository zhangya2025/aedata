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
    const HQ_DISPLAY_OPTION = 'aegis_public_query_hq_label';
    const ORDER_SHIPMENT_LINK_OPTION = 'aegis_order_shipment_link';
    const SCHEMA_VERSION = '1.4.0';
    const AUDIT_TABLE = 'aegis_audit_events';
    const MEDIA_TABLE = 'aegis_media_files';
    const SKU_TABLE = 'aegis_skus';
    const DEALER_TABLE = 'aegis_dealers';
    const CODE_BATCH_TABLE = 'aegis_code_batches';
    const CODE_TABLE = 'aegis_codes';
    const SHIPMENT_TABLE = 'aegis_shipments';
    const SHIPMENT_ITEM_TABLE = 'aegis_shipment_items';
    const QUERY_LOG_TABLE = 'aegis_query_logs';
    const ORDER_TABLE = 'aegis_orders';
    const ORDER_ITEM_TABLE = 'aegis_order_items';
    const PAYMENT_TABLE = 'aegis_payment_proofs';

    const CAP_ACCESS_ROOT = 'aegis_access_root';
    const CAP_MANAGE_SYSTEM = 'aegis_manage_system';
    const CAP_MANAGE_WAREHOUSE = 'aegis_manage_warehouse';
    const CAP_USE_WAREHOUSE = 'aegis_use_warehouse';
    const CAP_RESET_B = 'aegis_reset_b';
    const CAP_ORDERS = 'aegis_orders';

    const ACTION_MODULE_ENABLE = 'MODULE_ENABLE';
    const ACTION_MODULE_DISABLE = 'MODULE_DISABLE';
    const ACTION_MODULE_UNINSTALL = 'MODULE_UNINSTALL';
    const ACTION_SCHEMA_UPGRADE = 'SCHEMA_UPGRADE';
    const ACTION_MEDIA_UPLOAD = 'MEDIA_UPLOAD';
    const ACTION_MEDIA_DOWNLOAD = 'MEDIA_DOWNLOAD';
    const ACTION_MEDIA_DOWNLOAD_DENY = 'MEDIA_DOWNLOAD_DENY';
    const ACTION_SKU_CREATE = 'SKU_CREATE';
    const ACTION_SKU_UPDATE = 'SKU_UPDATE';
    const ACTION_SKU_ENABLE = 'SKU_ENABLE';
    const ACTION_SKU_DISABLE = 'SKU_DISABLE';
    const ACTION_SKU_EAN_CORRECT = 'SKU_EAN_CORRECT';
    const ACTION_DEALER_CREATE = 'DEALER_CREATE';
    const ACTION_DEALER_UPDATE = 'DEALER_UPDATE';
    const ACTION_DEALER_ENABLE = 'DEALER_ENABLE';
    const ACTION_DEALER_DISABLE = 'DEALER_DISABLE';
    const ACTION_DEALER_CODE_CORRECT = 'DEALER_CODE_CORRECT';
    const ACTION_CODE_BATCH_CREATE = 'CODE_BATCH_CREATE';
    const ACTION_CODE_EXPORT = 'CODE_EXPORT';
    const ACTION_CODE_PRINT = 'CODE_PRINT';
    const ACTION_SHIPMENT_CREATE = 'SHIPMENT_CREATE';
    const ACTION_SHIPMENT_EXPORT = 'SHIPMENT_EXPORT';
    const ACTION_PUBLIC_QUERY = 'PUBLIC_QUERY';
    const ACTION_PUBLIC_QUERY_RATE_LIMIT = 'PUBLIC_QUERY_RATE_LIMIT';
    const ACTION_RESET_B = 'RESET_B';
    const ACTION_ORDER_CREATE = 'ORDER_CREATE';
    const ACTION_ORDER_UPDATE = 'ORDER_UPDATE';
    const ACTION_PAYMENT_UPLOAD = 'PAYMENT_UPLOAD';
    const ACTION_LOGIN_REDIRECT = 'LOGIN_REDIRECT';
    const ACTION_ADMIN_BLOCKED = 'ADMIN_BLOCKED';
    const ACTION_PORTAL_BLOCKED = 'PORTAL_BLOCKED';

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
        add_shortcode('aegis_query', ['AEGIS_Public_Query', 'render_shortcode']);
        add_shortcode('aegis_system_portal', ['AEGIS_Portal', 'render_portal_shortcode']);
        add_action('wp_enqueue_scripts', ['AEGIS_Assets_Media', 'enqueue_front_assets']);
        add_action('init', ['AEGIS_System_Roles', 'sync_roles']);
        add_action('init', ['AEGIS_Portal', 'ensure_portal_page']);
        add_filter('login_redirect', ['AEGIS_Portal', 'filter_login_redirect'], 999, 3);
        add_filter('login_url', ['AEGIS_Portal', 'filter_login_url'], 10, 3);
        add_action('template_redirect', ['AEGIS_Portal', 'handle_portal_access']);
        add_action('admin_init', ['AEGIS_Portal', 'block_business_admin_access']);
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        add_action('plugins_loaded', ['AEGIS_System_Schema', 'maybe_upgrade']);
    }

    /**
     * 激活时初始化模块状态。
     */
    public static function activate() {
        AEGIS_System_Roles::sync_roles();
        AEGIS_System_Schema::maybe_upgrade();
        AEGIS_Assets_Media::ensure_upload_structure();
        AEGIS_Portal::ensure_portal_page(true);
        if (null === get_option(self::ORDER_SHIPMENT_LINK_OPTION, null)) {
            update_option(self::ORDER_SHIPMENT_LINK_OPTION, false, true);
        }
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
        $states = $this->get_module_states();

        $has_visible = false;
        if (AEGIS_System_Roles::user_can_manage_system()) {
            $has_visible = true;
        }
        if (AEGIS_System_Roles::user_can_manage_warehouse() && (!empty($states['sku']) || !empty($states['dealer_master']) || !empty($states['codes']) || !empty($states['shipments']) || !empty($states['public_query']) || !empty($states['orders']) || !empty($states['payments']))
        ) {
            $has_visible = true;
        }
        if (AEGIS_System_Roles::user_can_use_warehouse() && !AEGIS_System_Roles::user_can_manage_warehouse() && (!empty($states['shipments']) || !empty($states['orders']))) {
            $has_visible = true;
        }
        if (AEGIS_System_Roles::user_can_reset_b() && !empty($states['reset_b'])) {
            $has_visible = true;
        }
        if (AEGIS_System_Roles::is_dealer_only() && (!empty($states['orders']) || !empty($states['payments']) || !empty($states['reset_b']))) {
            $has_visible = true;
        }

        if (!$has_visible) {
            return;
        }

        add_menu_page(
            'AEGIS-SYSTEM',
            'AEGIS-SYSTEM',
            AEGIS_System::CAP_ACCESS_ROOT,
            'aegis-system',
            [$this, 'render_root_router'],
            'dashicons-admin-generic',
            56
        );

        if (AEGIS_System_Roles::user_can_manage_system()) {
            add_submenu_page(
                'aegis-system',
                '模块管理',
                '模块管理',
                AEGIS_System::CAP_MANAGE_SYSTEM,
                'aegis-system-modules',
                [$this, 'render_module_manager']
            );
        }

        if (!empty($states['assets_media']) && AEGIS_System_Roles::user_can_manage_system()) {
            add_submenu_page(
                'aegis-system',
                '排版设置',
                '全局设置',
                AEGIS_System::CAP_MANAGE_SYSTEM,
                'aegis-system-typography',
                ['AEGIS_Assets_Media', 'render_typography_settings']
            );
        }

        if (!empty($states['sku']) && AEGIS_System_Roles::user_can_manage_warehouse()) {
            add_submenu_page(
                'aegis-system',
                'SKU 管理',
                'SKU 管理',
                AEGIS_System::CAP_MANAGE_WAREHOUSE,
                'aegis-system-sku',
                ['AEGIS_SKU', 'render_admin_page']
            );
        }

        if (!empty($states['dealer_master']) && AEGIS_System_Roles::user_can_manage_warehouse()) {
            add_submenu_page(
                'aegis-system',
                '经销商管理',
                '经销商管理',
                AEGIS_System::CAP_MANAGE_WAREHOUSE,
                'aegis-system-dealer',
                ['AEGIS_Dealer', 'render_admin_page']
            );
        }

        if (!empty($states['codes']) && AEGIS_System_Roles::user_can_manage_warehouse()) {
            add_submenu_page(
                'aegis-system',
                '防伪码生成',
                '防伪码生成',
                AEGIS_System::CAP_MANAGE_WAREHOUSE,
                'aegis-system-codes',
                ['AEGIS_Codes', 'render_admin_page']
            );
        }

        if (!empty($states['orders']) && (AEGIS_System_Roles::user_can_manage_warehouse() || current_user_can(AEGIS_System::CAP_ORDERS) || AEGIS_System_Roles::is_dealer_only())) {
            add_submenu_page(
                'aegis-system',
                '订单管理',
                '订单管理',
                AEGIS_System::CAP_ACCESS_ROOT,
                'aegis-system-orders',
                ['AEGIS_Orders', 'render_admin_page']
            );
        }

        if (!empty($states['shipments']) && AEGIS_System_Roles::user_can_use_warehouse()) {
            add_submenu_page(
                'aegis-system',
                '扫码出库',
                '扫码出库',
                AEGIS_System::CAP_USE_WAREHOUSE,
                'aegis-system-shipments',
                ['AEGIS_Shipments', 'render_admin_page']
            );
        }

        if (!empty($states['public_query']) && AEGIS_System_Roles::user_can_manage_warehouse()) {
            add_submenu_page(
                'aegis-system',
                '防伪码查询',
                '防伪码查询',
                AEGIS_System::CAP_MANAGE_WAREHOUSE,
                'aegis-system-public-query',
                ['AEGIS_Public_Query', 'render_admin_page']
            );
        }

        if (!empty($states['reset_b']) && AEGIS_System_Roles::user_can_reset_b()) {
            add_submenu_page(
                'aegis-system',
                '清零系统',
                '清零系统',
                AEGIS_System::CAP_RESET_B,
                'aegis-system-reset-b',
                ['AEGIS_Reset_B', 'render_admin_page']
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
            'aegis-system_page_aegis-system-modules',
            'aegis-system_page_aegis-system-typography',
            'aegis-system_page_aegis-system-sku',
            'aegis-system_page_aegis-system-dealer',
            'aegis-system_page_aegis-system-codes',
            'aegis-system_page_aegis-system-orders',
            'aegis-system_page_aegis-system-shipments',
            'aegis-system_page_aegis-system-public-query',
            'aegis-system_page_aegis-system-reset-b',
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
        if (!AEGIS_System_Roles::user_can_manage_system()) {
            wp_die(__('您无权访问该页面。'));
        }

        $modules = self::get_registered_modules();
        $states = $this->get_module_states();

        $validation = ['success' => true, 'message' => ''];
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_SYSTEM,
                    'nonce_field'     => 'aegis_system_nonce',
                    'nonce_action'    => 'aegis_system_save_modules',
                    'whitelist'       => ['aegis_system_nonce', 'modules', '_wp_http_referer', '_aegis_idempotency', 'submit'],
                    'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                ]
            );
        }

        if ($validation['success'] && 'POST' === $_SERVER['REQUEST_METHOD']) {
            $allowed_modules = array_keys($modules);
            $posted_modules = isset($_POST['modules']) && is_array($_POST['modules']) ? array_keys($_POST['modules']) : [];
            $unknown_modules = array_diff($posted_modules, $allowed_modules);
            if (!empty($unknown_modules)) {
                AEGIS_Access_Audit::record_event('PARAMS_IGNORED', 'SUCCESS', ['keys' => array_values($unknown_modules)]);
            }
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

        if (!empty($clean['payments']) && empty($clean['orders'])) {
            $clean['payments'] = false;
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
     * 根路由占位，根据能力分流。
     */
    public function render_root_router() {
        if (AEGIS_System_Roles::user_can_manage_system()) {
            wp_safe_redirect(admin_url('admin.php?page=aegis-system-modules'));
            exit;
        }

        $states = $this->get_module_states();
        if (AEGIS_System_Roles::user_can_manage_warehouse() && (!empty($states['sku']) || !empty($states['shipments']))) {
            echo '<div class="wrap aegis-system-root"><h1 class="aegis-t-a3">AEGIS-SYSTEM</h1><p class="aegis-t-a6">请选择左侧功能菜单进行操作。</p></div>';
            return;
        }

        if (AEGIS_System_Roles::user_can_use_warehouse() && !empty($states['shipments'])) {
            $url = admin_url('admin.php?page=aegis-system-shipments');
            echo '<div class="wrap aegis-system-root"><h1 class="aegis-t-a3">AEGIS-SYSTEM</h1><p class="aegis-t-a6">请前往 <a href="' . esc_url($url) . '">扫码出库</a>。</p></div>';
            return;
        }

        if (AEGIS_System_Roles::user_can_reset_b() && !empty($states['reset_b'])) {
            $url = admin_url('admin.php?page=aegis-system-reset-b');
            echo '<div class="wrap aegis-system-root"><h1 class="aegis-t-a3">AEGIS-SYSTEM</h1><p class="aegis-t-a6">请前往 <a href="' . esc_url($url) . '">清零系统</a>。</p></div>';
            return;
        }

        wp_die(__('您无权访问该页面。'));
    }

}

class AEGIS_System_Roles {
    /**
     * 确保角色与能力存在且幂等。
     */
    public static function sync_roles() {
        $definitions = self::get_role_definitions();

        foreach ($definitions as $role_key => $def) {
            $role = get_role($role_key);
            if (!$role) {
                $role = add_role($role_key, $def['label'], ['read' => true]);
            }

            if (!$role) {
                continue;
            }

            foreach ($def['caps'] as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                }
            }
        }

        $admin_role = get_role('administrator');
        if ($admin_role) {
            $fallback_caps = [
                AEGIS_System::CAP_ACCESS_ROOT,
                AEGIS_System::CAP_MANAGE_SYSTEM,
                AEGIS_System::CAP_MANAGE_WAREHOUSE,
                AEGIS_System::CAP_USE_WAREHOUSE,
                AEGIS_System::CAP_RESET_B,
            ];

            foreach ($fallback_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * 当前用户是否具备系统访问根权限。
     *
     * @return bool
     */
    public static function user_can_access_root() {
        return current_user_can(AEGIS_System::CAP_ACCESS_ROOT)
            || current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM)
            || current_user_can(AEGIS_System::CAP_MANAGE_WAREHOUSE)
            || current_user_can(AEGIS_System::CAP_USE_WAREHOUSE)
            || current_user_can(AEGIS_System::CAP_RESET_B)
            || current_user_can(AEGIS_System::CAP_ORDERS);
    }

    public static function user_can_manage_system() {
        return current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM);
    }

    public static function user_can_manage_warehouse() {
        return current_user_can(AEGIS_System::CAP_MANAGE_WAREHOUSE) || current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM);
    }

    public static function user_can_use_warehouse() {
        return current_user_can(AEGIS_System::CAP_USE_WAREHOUSE)
            || self::user_can_manage_warehouse();
    }

    public static function user_can_reset_b() {
        return current_user_can(AEGIS_System::CAP_RESET_B) || self::user_can_manage_system();
    }

    /**
     * 是否为仅经销商角色。
     *
     * @return bool
     */
    public static function is_dealer_only() {
        $user = wp_get_current_user();
        if (!$user || empty($user->roles)) {
            return false;
        }
        $roles = (array) $user->roles;
        return 1 === count($roles) && in_array('aegis_dealer', $roles, true);
    }

    /**
     * 业务角色集合。
     *
     * @return array
     */
    public static function get_business_roles() {
        return [
            'aegis_hq_admin',
            'aegis_warehouse_manager',
            'aegis_warehouse_staff',
            'aegis_dealer',
        ];
    }

    /**
     * 是否为业务角色用户。
     *
     * @param WP_User|null $user
     * @return bool
     */
    public static function is_business_user($user = null) {
        if (null === $user) {
            $user = wp_get_current_user();
        }

        if (!$user || empty($user->roles)) {
            return false;
        }

        $roles = (array) $user->roles;
        return !empty(array_intersect($roles, self::get_business_roles()));
    }

    /**
     * 角色定义。
     *
     * @return array
     */
    protected static function get_role_definitions() {
        return [
            'aegis_hq_admin'          => [
                'label' => 'AEGIS HQ 管理员',
                'caps'  => [
                    'read'                                 => true,
                    AEGIS_System::CAP_ACCESS_ROOT          => true,
                    AEGIS_System::CAP_MANAGE_SYSTEM        => true,
                    AEGIS_System::CAP_MANAGE_WAREHOUSE     => true,
                    AEGIS_System::CAP_USE_WAREHOUSE        => true,
                    AEGIS_System::CAP_RESET_B              => true,
                    AEGIS_System::CAP_ORDERS               => true,
                ],
            ],
            'aegis_warehouse_manager' => [
                'label' => 'AEGIS 仓库管理员',
                'caps'  => [
                    'read'                                 => true,
                    AEGIS_System::CAP_ACCESS_ROOT          => true,
                    AEGIS_System::CAP_MANAGE_WAREHOUSE     => true,
                    AEGIS_System::CAP_USE_WAREHOUSE        => true,
                    AEGIS_System::CAP_RESET_B              => true,
                ],
            ],
            'aegis_warehouse_staff'   => [
                'label' => 'AEGIS 仓库员工',
                'caps'  => [
                    'read'                                 => true,
                    AEGIS_System::CAP_ACCESS_ROOT          => true,
                    AEGIS_System::CAP_USE_WAREHOUSE        => true,
                ],
            ],
            'aegis_dealer'            => [
                'label' => 'AEGIS 经销商',
                'caps'  => [
                    'read'                                 => true,
                    AEGIS_System::CAP_ACCESS_ROOT          => true,
                    AEGIS_System::CAP_RESET_B              => true,
                ],
            ],
        ];
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
            'capability'      => AEGIS_System::CAP_MANAGE_SYSTEM,
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
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[AEGIS] Blocked params: ' . wp_json_encode(array_values($extra_keys)));
                }
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

class AEGIS_Portal {
    const PORTAL_SLUG = 'aegis-system';
    const PORTAL_SHORTCODE = 'aegis_system_portal';

    /**
     * 确保 Portal 页面存在。
     */
    public static function ensure_portal_page($force = false) {
        if (wp_installing()) {
            return;
        }

        if (!$force && !current_user_can('manage_options')) {
            return;
        }

        $existing = get_posts(
            [
                'name'        => self::PORTAL_SLUG,
                'post_type'   => 'page',
                'post_status' => ['publish', 'draft', 'pending', 'future', 'private', 'trash'],
                'numberposts' => 1,
                'orderby'     => 'ID',
                'order'       => 'ASC',
            ]
        );

        if (!empty($existing)) {
            $page = $existing[0];
            if ('trash' === $page->post_status) {
                wp_untrash_post($page->ID);
                $page->post_status = 'draft';
            }

            if (empty($page->post_content)) {
                wp_update_post([
                    'ID'           => $page->ID,
                    'post_content' => '[' . self::PORTAL_SHORTCODE . ']',
                ]);
            }
            return;
        }

        wp_insert_post([
            'post_title'   => 'AEGIS-SYSTEM',
            'post_name'    => self::PORTAL_SLUG,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[' . self::PORTAL_SHORTCODE . ']',
        ]);
    }

    /**
     * 登录成功后的分流。
     */
    public static function filter_login_redirect($redirect_to, $requested_redirect, $user) {
        if (!($user instanceof WP_User)) {
            return $redirect_to;
        }

        $target = self::get_portal_url();
        $result = 'SUCCESS';
        $reason = 'business';

        if (!AEGIS_System_Roles::is_business_user($user)) {
            $target = admin_url();
            $reason = 'system';
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_LOGIN_REDIRECT,
            $result,
            [
                'reason'        => $reason,
                'requested'     => $requested_redirect,
                'chosen_target' => $target,
            ]
        );

        return $target;
    }

    /**
     * 覆盖登录入口。
     */
    public static function filter_login_url($login_url, $redirect, $force_reauth) {
        $url = self::get_login_url();
        $args = [];

        if (!empty($redirect)) {
            $args['redirect_to'] = $redirect;
        }

        if ($force_reauth) {
            $args['reauth'] = '1';
        }

        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        return $url;
    }

    /**
     * 拦截业务角色访问后台。
     */
    public static function block_business_admin_access() {
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }

        if (wp_doing_ajax()) {
            return;
        }

        if (!AEGIS_System_Roles::is_business_user()) {
            return;
        }

        $portal_url = self::get_portal_url();
        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ADMIN_BLOCKED,
            'SUCCESS',
            [
                'path'   => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
                'target' => $portal_url,
            ]
        );
        wp_safe_redirect($portal_url);
        exit;
    }

    /**
     * 处理 Portal 访问权限。
     */
    public static function handle_portal_access() {
        if (is_admin() || !is_page(self::PORTAL_SLUG)) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(self::get_login_url());
            exit;
        }

        if (!AEGIS_System_Roles::is_business_user()) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_PORTAL_BLOCKED,
                'FAIL',
                [
                    'reason' => 'non_business',
                ]
            );
            wp_safe_redirect(admin_url());
            exit;
        }
    }

    /**
     * Portal 占位短码渲染。
     */
    public static function render_portal_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="aegis-system-root aegis-t-a5">请先登录后访问。</div>';
        }

        $user = wp_get_current_user();
        if (!AEGIS_System_Roles::is_business_user($user)) {
            return '<div class="aegis-system-root aegis-t-a5">当前账号无权访问 AEGIS Portal。</div>';
        }

        $roles = array_intersect((array) $user->roles, AEGIS_System_Roles::get_business_roles());
        $modules = AEGIS_System::get_registered_modules();
        $states = get_option(AEGIS_System::OPTION_KEY, []);
        $enabled = [];

        foreach ($modules as $slug => $module) {
            $active = ($slug === 'core_manager') ? true : !empty($states[$slug]);
            $enabled[] = esc_html(($module['label'] ?? $slug) . '：' . ($active ? '启用' : '未启用'));
        }

        $output = '<div class="aegis-system-root aegis-t-a5">';
        $output .= '<h2 class="aegis-t-a3">AEGIS-SYSTEM Portal</h2>';
        $output .= '<p class="aegis-t-a5">当前用户：' . esc_html($user->user_login) . '</p>';
        $output .= '<p class="aegis-t-a6">角色：' . esc_html(implode(', ', $roles)) . '</p>';
        $output .= '<p class="aegis-t-a6">可见模块（参考）：</p><ul class="aegis-t-a6">';
        foreach ($enabled as $line) {
            $output .= '<li>' . $line . '</li>';
        }
        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Portal URL。
     *
     * @return string
     */
    protected static function get_portal_url() {
        return home_url('/' . self::PORTAL_SLUG . '/');
    }

    /**
     * 登录入口 URL。
     *
     * @return string
     */
    protected static function get_login_url() {
        return home_url('/aegislogin.php');
    }
}

class AEGIS_System_Schema {
    const OPTION_KEY = 'aegis_schema_version';

    /**
     * 根据版本执行安装或升级。
     */
    public static function maybe_upgrade() {
        global $wpdb;
        $installed = get_option(self::OPTION_KEY, '0');
        if (!is_string($installed) || $installed === '') {
            $installed = '0';
        }

        if (version_compare($installed, AEGIS_System::SCHEMA_VERSION, '>=')) {
            return;
        }

        $wpdb->last_error = '';
        $executed = self::install_tables();
        $result = empty($wpdb->last_error) ? 'SUCCESS' : 'FAIL';

        if ($result === 'SUCCESS') {
            update_option(self::OPTION_KEY, AEGIS_System::SCHEMA_VERSION, true);
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_SCHEMA_UPGRADE,
            $result,
            [
                'from_version' => $installed,
                'to_version'   => AEGIS_System::SCHEMA_VERSION,
                'statements'   => $executed,
                'db_error'     => $wpdb->last_error,
            ]
        );
    }

    /**
     * 返回建表 SQL 集合。
     *
     * @param string $charset_collate
     * @return array
     */
    protected static function get_table_sql($charset_collate) {
        global $wpdb;
        $audit_table = $wpdb->prefix . AEGIS_System::AUDIT_TABLE;
        $media_table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $code_batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $query_log_table = $wpdb->prefix . AEGIS_System::QUERY_LOG_TABLE;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $order_item_table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        $payment_table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;

        $audit_sql = "CREATE TABLE {$audit_table} (
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

        $sku_sql = "CREATE TABLE {$sku_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ean VARCHAR(64) NOT NULL,
            product_name VARCHAR(191) NOT NULL,
            size_label VARCHAR(100) NULL,
            color_label VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            product_image_id BIGINT(20) UNSIGNED NULL,
            certificate_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ean (ean),
            KEY status (status),
            KEY created_at (created_at),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $dealer_sql = "CREATE TABLE {$dealer_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            auth_code VARCHAR(64) NOT NULL,
            dealer_name VARCHAR(191) NOT NULL,
            contact_name VARCHAR(191) NULL,
            phone VARCHAR(64) NULL,
            address VARCHAR(255) NULL,
            authorized_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            business_license_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY auth_code (auth_code),
            KEY status (status),
            KEY authorized_at (authorized_at),
            KEY created_at (created_at),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $code_batch_sql = "CREATE TABLE {$code_batch_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ean VARCHAR(64) NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 0,
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY ean (ean),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $code_sql = "CREATE TABLE {$code_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id BIGINT(20) UNSIGNED NOT NULL,
            ean VARCHAR(64) NOT NULL,
            code VARCHAR(128) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'unused',
            created_at DATETIME NOT NULL,
            printed_at DATETIME NULL,
            exported_at DATETIME NULL,
            query_a_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            query_b_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            query_b_offset BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            last_query_at DATETIME NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY batch_id (batch_id),
            KEY ean (ean),
            KEY status (status),
            KEY created_at (created_at),
            KEY last_query_at (last_query_at)
        ) {$charset_collate};";

        $shipment_sql = "CREATE TABLE {$shipment_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shipment_no VARCHAR(100) NOT NULL,
            dealer_id BIGINT(20) UNSIGNED NOT NULL,
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            order_ref VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'created',
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY shipment_no (shipment_no),
            KEY dealer_id (dealer_id),
            KEY created_at (created_at),
            KEY status (status)
        ) {$charset_collate};";

        $shipment_item_sql = "CREATE TABLE {$shipment_item_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shipment_id BIGINT(20) UNSIGNED NOT NULL,
            code_id BIGINT(20) UNSIGNED NOT NULL,
            code_value VARCHAR(128) NOT NULL,
            ean VARCHAR(64) NOT NULL,
            scanned_at DATETIME NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_unique (code_id),
            KEY shipment_id (shipment_id),
            KEY code_value (code_value),
            KEY ean (ean),
            KEY scanned_at (scanned_at)
        ) {$charset_collate};";

        $query_log_sql = "CREATE TABLE {$query_log_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            code_id BIGINT(20) UNSIGNED NOT NULL,
            code_value VARCHAR(128) NOT NULL,
            query_channel VARCHAR(10) NOT NULL,
            context VARCHAR(20) NOT NULL,
            client_ip VARCHAR(100) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY code_channel (code_id, query_channel),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $order_sql = "CREATE TABLE {$order_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_no VARCHAR(120) NOT NULL,
            dealer_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            total_amount DECIMAL(20,4) NULL,
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_no (order_no),
            KEY dealer_id (dealer_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $order_item_sql = "CREATE TABLE {$order_item_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            ean VARCHAR(64) NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 1,
            status VARCHAR(40) NOT NULL DEFAULT 'open',
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY ean (ean),
            KEY status (status)
        ) {$charset_collate};";

        $payment_sql = "CREATE TABLE {$payment_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            dealer_id BIGINT(20) UNSIGNED NOT NULL,
            media_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'submitted',
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY dealer_id (dealer_id),
            KEY media_id (media_id),
            KEY status (status)
        ) {$charset_collate};";

        return [$audit_sql, $media_sql, $sku_sql, $dealer_sql, $code_batch_sql, $code_sql, $shipment_sql, $shipment_item_sql, $query_log_sql, $order_sql, $order_item_sql, $payment_sql];
    }

    /**
     * 执行建表并返回执行列表。
     *
     * @return array
     */
    protected static function install_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $GLOBALS['wpdb']->get_charset_collate();
        $sqls = self::get_table_sql($charset_collate);
        $executed = [];

        foreach ($sqls as $sql) {
            $result = dbDelta($sql);
            if (is_array($result)) {
                $executed = array_merge($executed, array_values($result));
            }
        }

        return $executed;
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
        if (!AEGIS_System_Roles::user_can_manage_system()) {
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
                    'capability'   => AEGIS_System::CAP_MANAGE_SYSTEM,
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
        $assets_enabled = AEGIS_System::is_module_enabled('assets_media');
        $public_query_enabled = AEGIS_System::is_module_enabled('public_query');
        if (!$assets_enabled && !$public_query_enabled) {
            return;
        }

        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        if (has_shortcode($post->post_content, self::FRONT_SHORTCODE) || has_shortcode($post->post_content, 'aegis_query')) {
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
                    return AEGIS_System::is_module_enabled('assets_media') && AEGIS_System_Roles::user_can_manage_warehouse();
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
                    return AEGIS_System::is_module_enabled('assets_media') && !AEGIS_System_Roles::is_dealer_only();
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
                'capability'   => AEGIS_System::CAP_MANAGE_WAREHOUSE,
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
        $allowed_visibility = [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE, self::VISIBILITY_SENSITIVE];
        if (!in_array($visibility, $allowed_visibility, true)) {
            $visibility = self::VISIBILITY_PRIVATE;
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
     * 后台表单上传复用管道。
     *
     * @param array $file
     * @param array $params
     * @return array|WP_Error
     */
    public static function handle_admin_upload($file, $params = []) {
        if (!AEGIS_System::is_module_enabled('assets_media')) {
            return new WP_Error('module_disabled', '资产与媒体模块未启用');
        }

        $required_cap = isset($params['capability']) ? $params['capability'] : AEGIS_System::CAP_MANAGE_WAREHOUSE;
        $allow_dealer_payment = !empty($params['allow_dealer_payment']);
        $permission_callback = isset($params['permission_callback']) ? $params['permission_callback'] : null;

        if (!current_user_can($required_cap)) {
            $bypass = false;
            if ($allow_dealer_payment && is_callable($permission_callback)) {
                $bypass = (bool) call_user_func($permission_callback);
            }

            if (!$bypass) {
                return new WP_Error('forbidden', '权限不足');
            }
        }

        if (!is_array($file) || empty($file['name'])) {
            return new WP_Error('missing_file', '未选择文件');
        }

        self::ensure_upload_structure();

        $allowed_buckets = ['sku', 'dealer', 'payments', 'exports', 'temp', 'certificate'];
        $bucket = isset($params['bucket']) ? sanitize_key($params['bucket']) : 'temp';
        if (!in_array($bucket, $allowed_buckets, true)) {
            $bucket = 'temp';
        }

        $owner_type = isset($params['owner_type']) ? sanitize_key($params['owner_type']) : '';
        $owner_id = isset($params['owner_id']) ? (int) $params['owner_id'] : null;
        $visibility = isset($params['visibility']) ? sanitize_key($params['visibility']) : self::VISIBILITY_PRIVATE;
        $allowed_visibility = [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE, self::VISIBILITY_SENSITIVE];
        if (!in_array($visibility, $allowed_visibility, true)) {
            $visibility = self::VISIBILITY_PRIVATE;
        }

        $sensitive_types = ['business_license', 'payment_receipt', 'payment_voucher'];
        if (in_array($owner_type, $sensitive_types, true)) {
            $visibility = self::VISIBILITY_SENSITIVE;
        }

        $upload_override = ['test_form' => false];
        $dir_filter = function ($uploads) use ($bucket) {
            $uploads['subdir'] = '/' . AEGIS_Assets_Media::UPLOAD_ROOT . '/' . $bucket;
            $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
            $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
            return $uploads;
        };
        add_filter('upload_dir', $dir_filter);

        $result = wp_handle_upload($file, $upload_override);
        remove_filter('upload_dir', $dir_filter);

        if (isset($result['error'])) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_UPLOAD, 'FAIL', ['reason' => $result['error']]);
            return new WP_Error('upload_error', $result['error']);
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
                'id'         => $id,
                'owner_type' => $owner_type,
                'owner_id'   => $owner_id,
                'visibility' => $visibility,
                'bucket'     => $bucket,
                'file'       => basename($result['file']),
            ]
        );

        return [
            'id'         => $id,
            'path'       => $file_path,
            'visibility' => $visibility,
            'mime'       => isset($result['type']) ? $result['type'] : '',
        ];
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
        $can_manage_media = AEGIS_System_Roles::user_can_manage_warehouse();
        $can_reset_media = AEGIS_System_Roles::user_can_reset_b() && in_array($record->owner_type, ['reset_b'], true);
        $is_payment_media = in_array($record->owner_type, ['payment_receipt', 'payment_voucher', 'payment_proof'], true);
        $can_view_payment = false;

        if ($is_payment_media && class_exists('AEGIS_Orders')) {
            $order = AEGIS_Orders::get_order((int) $record->owner_id);
            if ($order && AEGIS_Orders::current_user_can_view_order($order)) {
                $can_view_payment = true;
            }
        }

        if (!$is_public_certificate && !$can_manage_media && !$can_reset_media && !$can_view_payment) {
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

class AEGIS_SKU {
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * 渲染 SKU 管理页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('sku')) {
            echo '<div class="wrap"><h1>SKU 管理</h1><div class="notice notice-warning"><p>请先在模块管理中启用 SKU 模块。</p></div></div>';
            return;
        }

        $assets_enabled = AEGIS_System::is_module_enabled('assets_media');
        $messages = [];
        $errors = [];
        $order_link_enabled = AEGIS_Orders::is_shipment_link_enabled();
        $current_edit = null;

        if (isset($_GET['edit'])) {
            $current_edit = self::get_sku((int) $_GET['edit']);
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['sku_action']) ? sanitize_key(wp_unslash($_POST['sku_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $whitelist = ['sku_action', 'sku_id', 'ean', 'product_name', 'size_label', 'color_label', 'status', 'ean_correct', 'ean_correct_confirm', 'certificate_visibility', 'target_status', 'aegis_sku_nonce', '_wp_http_referer', '_aegis_idempotency'];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                    'nonce_field'     => 'aegis_sku_nonce',
                    'nonce_action'    => 'aegis_sku_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => $idempotency,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } elseif ('save' === $action) {
                $result = self::handle_save_request($_POST, $_FILES, $assets_enabled);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages = array_merge($messages, $result['messages']);
                    $current_edit = $result['sku'];
                }
            } elseif ('toggle_status' === $action) {
                $result = self::handle_status_toggle($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages[] = $result['message'];
                }
            }
        }

        $skus = self::list_skus();

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a2">SKU 管理</h1>';
        echo '<p class="aegis-t-a6">维护产品主数据，启停状态将影响后续业务规则。</p>';

        foreach ($messages as $msg) {
            echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $msg) {
            echo '<div class="error"><p>' . esc_html($msg) . '</p></div>';
        }

        if (!$assets_enabled) {
            echo '<div class="notice notice-warning"><p class="aegis-t-a6">附件上传依赖“资产与媒体”模块，请确保已启用。</p></div>';
        }

        self::render_form($current_edit, $assets_enabled);
        self::render_table($skus);
        echo '</div>';
    }

    /**
     * 渲染新增/编辑表单。
     *
     * @param object|null $sku
     * @param bool        $assets_enabled
     */
    protected static function render_form($sku, $assets_enabled) {
        $id = $sku ? (int) $sku->id : 0;
        $ean = $sku ? $sku->ean : '';
        $product_name = $sku ? $sku->product_name : '';
        $size_label = $sku ? $sku->size_label : '';
        $color_label = $sku ? $sku->color_label : '';
        $status = $sku ? $sku->status : self::STATUS_ACTIVE;
        $idempotency_key = wp_generate_uuid4();

        echo '<div class="aegis-t-a5" style="margin-top:20px;">';
        echo '<h2 class="aegis-t-a3">' . ($sku ? '编辑 SKU' : '新增 SKU') . '</h2>';
        echo '<form method="post" enctype="multipart/form-data" class="aegis-t-a5">';
        wp_nonce_field('aegis_sku_action', 'aegis_sku_nonce');
        echo '<input type="hidden" name="sku_action" value="save" />';
        echo '<input type="hidden" name="sku_id" value="' . esc_attr($id) . '" />';
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';

        echo '<table class="form-table">';
        echo '<tr><th><label for="aegis-ean">EAN</label></th><td>';
        echo '<input type="text" id="aegis-ean" name="ean" value="' . esc_attr($ean) . '" ' . ($sku ? 'readonly' : '') . ' class="regular-text" />';
        if ($sku) {
            echo '<p class="description aegis-t-a6">常规编辑不可修改 EAN。</p>';
            echo '<div class="aegis-t-a6" style="margin-top:8px;">';
            echo '<label><input type="checkbox" name="ean_correct_confirm" value="1" /> 启用受控更正</label><br />';
            echo '<input type="text" name="ean_correct" placeholder="新的 EAN" class="regular-text" />';
            echo '<p class="description">仅限总部管理员，提交将写入审计。</p>';
            echo '</div>';
        }
        echo '</td></tr>';

        echo '<tr><th><label for="aegis-name">产品名称</label></th><td><input type="text" id="aegis-name" name="product_name" value="' . esc_attr($product_name) . '" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="aegis-size">尺码</label></th><td><input type="text" id="aegis-size" name="size_label" value="' . esc_attr($size_label) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="aegis-color">颜色</label></th><td><input type="text" id="aegis-color" name="color_label" value="' . esc_attr($color_label) . '" class="regular-text" /></td></tr>';

        echo '<tr><th>状态</th><td><select name="status">';
        foreach (self::get_status_labels() as $value => $label) {
            $selected = selected($status, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        if ($assets_enabled) {
            echo '<tr><th>产品图片</th><td>';
            echo '<input type="file" name="product_image" accept="image/*" />';
            if ($sku && $sku->product_image_id) {
                echo '<p class="description aegis-t-a6">已关联媒体 ID：' . esc_html($sku->product_image_id) . '</p>';
            }
            echo '</td></tr>';

            echo '<tr><th>证书上传</th><td>';
            echo '<input type="file" name="certificate_file" />';
            $visibility = isset($_POST['certificate_visibility']) ? sanitize_key(wp_unslash($_POST['certificate_visibility'])) : AEGIS_Assets_Media::VISIBILITY_PRIVATE;
            echo '<p class="aegis-t-a6">证书可设置公开（public）或内部可见（internal=private）。</p>';
            echo '<label><input type="radio" name="certificate_visibility" value="private" ' . checked($visibility, 'private', false) . ' /> 内部</label> ';
            echo '<label><input type="radio" name="certificate_visibility" value="public" ' . checked($visibility, 'public', false) . ' /> 公开</label>';
            if ($sku && $sku->certificate_id) {
                echo '<p class="description aegis-t-a6" style="margin-top:6px;">已关联证书媒体 ID：' . esc_html($sku->certificate_id) . '</p>';
            }
            echo '</td></tr>';
        }

        echo '</table>';
        submit_button($sku ? '保存 SKU' : '新增 SKU');
        echo '</form>';
        echo '</div>';
    }

    /**
     * 处理保存请求。
     *
     * @param array $post
     * @param array $files
     * @param bool  $assets_enabled
     * @return array|WP_Error
     */
    protected static function handle_save_request($post, $files, $assets_enabled) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $sku_id = isset($post['sku_id']) ? (int) $post['sku_id'] : 0;
        $ean_input = isset($post['ean']) ? sanitize_text_field(wp_unslash($post['ean'])) : '';
        $product_name = isset($post['product_name']) ? sanitize_text_field(wp_unslash($post['product_name'])) : '';
        $size_label = isset($post['size_label']) ? sanitize_text_field(wp_unslash($post['size_label'])) : '';
        $color_label = isset($post['color_label']) ? sanitize_text_field(wp_unslash($post['color_label'])) : '';
        $status_raw = isset($post['status']) ? sanitize_key($post['status']) : '';
        $status = $status_raw && array_key_exists($status_raw, self::get_status_labels()) ? $status_raw : self::STATUS_ACTIVE;

        $now = current_time('mysql');
        $is_new = $sku_id === 0;
        $existing = $is_new ? null : self::get_sku($sku_id);
        if (!$is_new && !$existing) {
            return new WP_Error('not_found', '未找到对应的 SKU。');
        }

        $ean_to_use = $is_new ? $ean_input : $existing->ean;
        $ean_correct = isset($post['ean_correct']) ? sanitize_text_field(wp_unslash($post['ean_correct'])) : '';
        $ean_confirm = !empty($post['ean_correct_confirm']);

        if ($is_new && '' === $ean_to_use) {
            return new WP_Error('ean_required', '请填写 EAN。');
        }

        if (!$is_new && $ean_confirm && $ean_correct) {
            $ean_to_use = $ean_correct;
        }

        if (self::ean_exists($ean_to_use, $sku_id)) {
            return new WP_Error('ean_exists', 'EAN 已存在，无法重复。');
        }

        $data = [
            'product_name' => $product_name,
            'size_label'   => $size_label,
            'color_label'  => $color_label,
            'status'       => $status,
            'updated_at'   => $now,
        ];

        $messages = [];

        if ($is_new) {
            $data['ean'] = $ean_to_use;
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            $sku_id = (int) $wpdb->insert_id;
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SKU_CREATE, 'SUCCESS', ['id' => $sku_id, 'ean' => $ean_to_use]);
            $messages[] = 'SKU 已创建。';
        } else {
            $wpdb->update($table, $data, ['id' => $sku_id]);
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SKU_UPDATE, 'SUCCESS', ['id' => $sku_id]);
            $messages[] = 'SKU 已更新。';

            if ($existing->status !== $status) {
                $action = self::STATUS_ACTIVE === $status ? AEGIS_System::ACTION_SKU_ENABLE : AEGIS_System::ACTION_SKU_DISABLE;
                AEGIS_Access_Audit::record_event($action, 'SUCCESS', ['id' => $sku_id, 'from' => $existing->status, 'to' => $status]);
            }

            if ($ean_to_use !== $existing->ean) {
                $wpdb->update($table, ['ean' => $ean_to_use], ['id' => $sku_id]);
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SKU_EAN_CORRECT, 'SUCCESS', ['id' => $sku_id, 'from' => $existing->ean, 'to' => $ean_to_use]);
                $messages[] = 'EAN 已完成受控更正。';
            }
        }

        if ($assets_enabled) {
            if (isset($files['product_image']) && is_array($files['product_image']) && !empty($files['product_image']['name'])) {
                $upload = AEGIS_Assets_Media::handle_admin_upload($files['product_image'], [
                    'bucket'     => 'sku',
                    'owner_type' => 'sku_image',
                    'owner_id'   => $sku_id,
                    'visibility' => AEGIS_Assets_Media::VISIBILITY_PRIVATE,
                    'meta'       => ['type' => 'product_image', 'sku' => $ean_to_use],
                ]);

                if (is_wp_error($upload)) {
                    $messages[] = '产品图片上传失败：' . $upload->get_error_message();
                } else {
                    $wpdb->update($table, ['product_image_id' => $upload['id']], ['id' => $sku_id]);
                    $messages[] = '产品图片已上传并关联。';
                }
            }

            if (isset($files['certificate_file']) && is_array($files['certificate_file']) && !empty($files['certificate_file']['name'])) {
                $visibility = isset($post['certificate_visibility']) && 'public' === sanitize_key($post['certificate_visibility']) ? AEGIS_Assets_Media::VISIBILITY_PUBLIC : AEGIS_Assets_Media::VISIBILITY_PRIVATE;
                $upload = AEGIS_Assets_Media::handle_admin_upload($files['certificate_file'], [
                    'bucket'     => 'certificate',
                    'owner_type' => 'certificate',
                    'owner_id'   => $sku_id,
                    'visibility' => $visibility,
                    'meta'       => ['type' => 'sku_certificate', 'sku' => $ean_to_use],
                ]);

                if (is_wp_error($upload)) {
                    $messages[] = '证书上传失败：' . $upload->get_error_message();
                } else {
                    $wpdb->update($table, ['certificate_id' => $upload['id']], ['id' => $sku_id]);
                    $messages[] = '证书已上传并关联。';
                }
            }
        }

        $sku = self::get_sku($sku_id);

        return [
            'sku'      => $sku,
            'messages' => $messages,
        ];
    }

    /**
     * 处理状态切换。
     *
     * @param array $post
     * @return array|WP_Error
     */
    protected static function handle_status_toggle($post) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $sku_id = isset($post['sku_id']) ? (int) $post['sku_id'] : 0;
        $target = isset($post['target_status']) ? sanitize_key($post['target_status']) : '';

        if (!array_key_exists($target, self::get_status_labels())) {
            return new WP_Error('bad_status', '无效的状态。');
        }

        $sku = self::get_sku($sku_id);
        if (!$sku) {
            return new WP_Error('not_found', '未找到对应的 SKU。');
        }

        $wpdb->update($table, ['status' => $target, 'updated_at' => current_time('mysql')], ['id' => $sku_id]);
        $action = self::STATUS_ACTIVE === $target ? AEGIS_System::ACTION_SKU_ENABLE : AEGIS_System::ACTION_SKU_DISABLE;
        AEGIS_Access_Audit::record_event($action, 'SUCCESS', ['id' => $sku_id, 'from' => $sku->status, 'to' => $target]);

        return ['message' => '状态已更新。'];
    }

    /**
     * 列出所有 SKU。
     *
     * @return array
     */
    protected static function list_skus() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
    }

    /**
     * 获取单个 SKU。
     *
     * @param int $id
     * @return object|null
     */
    protected static function get_sku($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /**
     * 检查 EAN 是否已存在。
     *
     * @param string $ean
     * @param int    $exclude_id
     * @return bool
     */
    protected static function ean_exists($ean, $exclude_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $sql = "SELECT COUNT(*) FROM {$table} WHERE ean = %s";
        $params = [$ean];
        if ($exclude_id > 0) {
            $sql .= ' AND id != %d';
            $params[] = $exclude_id;
        }
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        return ((int) $count) > 0;
    }

    /**
     * 状态字典。
     *
     * @return array
     */
    protected static function get_status_labels() {
        return [
            self::STATUS_ACTIVE   => '启用',
            self::STATUS_INACTIVE => '停用',
        ];
    }

    /**
     * 渲染列表。
     *
     * @param array $skus
     */
    protected static function render_table($skus) {
        echo '<h2 class="aegis-t-a3" style="margin-top:24px;">SKU 列表</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>EAN</th><th>名称</th><th>尺码</th><th>颜色</th><th>状态</th><th>附件</th><th>操作</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($skus)) {
            echo '<tr><td colspan="8">暂无记录。</td></tr>';
        }

        foreach ($skus as $sku) {
            $status_label = isset(self::get_status_labels()[$sku->status]) ? self::get_status_labels()[$sku->status] : $sku->status;
            echo '<tr>';
            echo '<td>' . esc_html($sku->id) . '</td>';
            echo '<td>' . esc_html($sku->ean) . '</td>';
            echo '<td>' . esc_html($sku->product_name) . '</td>';
            echo '<td>' . esc_html($sku->size_label) . '</td>';
            echo '<td>' . esc_html($sku->color_label) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '<td>';
            if ($sku->product_image_id) {
                echo '<div>图：' . esc_html($sku->product_image_id) . '</div>';
            }
            if ($sku->certificate_id) {
                echo '<div>证：' . esc_html($sku->certificate_id) . '</div>';
            }
            echo '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url(add_query_arg(['page' => 'aegis-system-sku', 'edit' => $sku->id], admin_url('admin.php'))) . '">编辑</a> ';
            $target_status = self::STATUS_ACTIVE === $sku->status ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field('aegis_sku_action', 'aegis_sku_nonce');
            echo '<input type="hidden" name="sku_action" value="toggle_status" />';
            echo '<input type="hidden" name="sku_id" value="' . esc_attr($sku->id) . '" />';
            echo '<input type="hidden" name="target_status" value="' . esc_attr($target_status) . '" />';
            echo '<button type="submit" class="button button-small">' . (self::STATUS_ACTIVE === $sku->status ? '停用' : '启用') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
}

class AEGIS_Dealer {
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * 渲染经销商管理页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('dealer_master')) {
            echo '<div class="wrap"><h1>经销商管理</h1><div class="notice notice-warning"><p>请先在模块管理中启用经销商主数据模块。</p></div></div>';
            return;
        }

        $assets_enabled = AEGIS_System::is_module_enabled('assets_media');
        $messages = [];
        $errors = [];
        $current_edit = null;

        if (isset($_GET['edit'])) {
            $current_edit = self::get_dealer((int) $_GET['edit']);
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['dealer_action']) ? sanitize_key(wp_unslash($_POST['dealer_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $whitelist = [
                'dealer_action',
                'dealer_id',
                'auth_code',
                'dealer_name',
                'contact_name',
                'phone',
                'address',
                'authorized_at',
                'status',
                'auth_code_correct',
                'auth_code_correct_confirm',
                'target_status',
                '_wp_http_referer',
                '_aegis_idempotency',
                'aegis_dealer_nonce',
            ];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                    'nonce_field'     => 'aegis_dealer_nonce',
                    'nonce_action'    => 'aegis_dealer_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => $idempotency,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } elseif ('save' === $action) {
                $result = self::handle_save_request($_POST, $_FILES, $assets_enabled);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages = array_merge($messages, $result['messages']);
                    $current_edit = $result['dealer'];
                }
            } elseif ('toggle_status' === $action) {
                $result = self::handle_status_toggle($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages[] = $result['message'];
                }
            }
        }

        $dealers = self::list_dealers();

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a2">经销商管理</h1>';
        echo '<p class="aegis-t-a6">维护经销商主数据，营业执照附件默认敏感仅内部可见。</p>';

        foreach ($messages as $msg) {
            echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $msg) {
            echo '<div class="error"><p>' . esc_html($msg) . '</p></div>';
        }

        if (!$assets_enabled) {
            echo '<div class="notice notice-warning"><p class="aegis-t-a6">附件上传依赖“资产与媒体”模块，请确保已启用。</p></div>';
        }

        self::render_form($current_edit, $assets_enabled);
        self::render_table($dealers);
        echo '</div>';
    }

    /**
     * 渲染新增/编辑表单。
     *
     * @param object|null $dealer
     * @param bool        $assets_enabled
     */
    protected static function render_form($dealer, $assets_enabled) {
        $id = $dealer ? (int) $dealer->id : 0;
        $auth_code = $dealer ? $dealer->auth_code : '';
        $dealer_name = $dealer ? $dealer->dealer_name : '';
        $contact_name = $dealer ? $dealer->contact_name : '';
        $phone = $dealer ? $dealer->phone : '';
        $address = $dealer ? $dealer->address : '';
        $authorized_at = $dealer ? $dealer->authorized_at : '';
        $status = $dealer ? $dealer->status : self::STATUS_ACTIVE;
        $idempotency_key = wp_generate_uuid4();

        echo '<div class="aegis-t-a5" style="margin-top:20px;">';
        echo '<h2 class="aegis-t-a3">' . ($dealer ? '编辑经销商' : '新增经销商') . '</h2>';
        echo '<form method="post" enctype="multipart/form-data" class="aegis-t-a5">';
        wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce');
        echo '<input type="hidden" name="dealer_action" value="save" />';
        echo '<input type="hidden" name="dealer_id" value="' . esc_attr($id) . '" />';
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';

        echo '<table class="form-table">';
        echo '<tr><th><label for="aegis-auth-code">授权编码</label></th><td>';
        echo '<input type="text" id="aegis-auth-code" name="auth_code" value="' . esc_attr($auth_code) . '" ' . ($dealer ? 'readonly' : '') . ' class="regular-text" />';
        if ($dealer) {
            echo '<p class="description aegis-t-a6">常规编辑不可修改授权编码。</p>';
            echo '<div class="aegis-t-a6" style="margin-top:8px;">';
            echo '<label><input type="checkbox" name="auth_code_correct_confirm" value="1" /> 启用受控更正</label><br />';
            echo '<input type="text" name="auth_code_correct" placeholder="新的授权编码" class="regular-text" />';
            echo '<p class="description">仅限总部管理员，提交将写入审计。</p>';
            echo '</div>';
        }
        echo '</td></tr>';

        echo '<tr><th><label for="aegis-dealer-name">名称</label></th><td><input type="text" id="aegis-dealer-name" name="dealer_name" value="' . esc_attr($dealer_name) . '" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="aegis-contact-name">联系人</label></th><td><input type="text" id="aegis-contact-name" name="contact_name" value="' . esc_attr($contact_name) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="aegis-phone">电话</label></th><td><input type="text" id="aegis-phone" name="phone" value="' . esc_attr($phone) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="aegis-address">地址</label></th><td><input type="text" id="aegis-address" name="address" value="' . esc_attr($address) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="aegis-authorized">授权时间</label></th><td><input type="datetime-local" id="aegis-authorized" name="authorized_at" value="' . esc_attr(self::format_datetime_local($authorized_at)) . '" /></td></tr>';

        echo '<tr><th>状态</th><td><select name="status">';
        foreach (self::get_status_labels() as $value => $label) {
            $selected = selected($status, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        if ($assets_enabled) {
            echo '<tr><th>营业执照</th><td>';
            echo '<input type="file" name="business_license" />';
            echo '<p class="description aegis-t-a6">上传将存储为敏感文件，不会公开访问。</p>';
            if ($dealer && $dealer->business_license_id) {
                echo '<p class="description aegis-t-a6">已关联营业执照媒体 ID：' . esc_html($dealer->business_license_id) . '</p>';
            }
            echo '</td></tr>';
        }

        echo '</table>';
        submit_button($dealer ? '保存经销商' : '新增经销商');
        echo '</form>';
        echo '</div>';
    }

    /**
     * 处理保存。
     *
     * @param array $post
     * @param array $files
     * @param bool  $assets_enabled
     * @return array|WP_Error
     */
    protected static function handle_save_request($post, $files, $assets_enabled) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        $dealer_id = isset($post['dealer_id']) ? (int) $post['dealer_id'] : 0;
        $auth_code_input = isset($post['auth_code']) ? sanitize_text_field(wp_unslash($post['auth_code'])) : '';
        $dealer_name = isset($post['dealer_name']) ? sanitize_text_field(wp_unslash($post['dealer_name'])) : '';
        $contact_name = isset($post['contact_name']) ? sanitize_text_field(wp_unslash($post['contact_name'])) : '';
        $phone = isset($post['phone']) ? sanitize_text_field(wp_unslash($post['phone'])) : '';
        $address = isset($post['address']) ? sanitize_text_field(wp_unslash($post['address'])) : '';
        $authorized_raw = isset($post['authorized_at']) ? sanitize_text_field(wp_unslash($post['authorized_at'])) : '';
        $status_raw = isset($post['status']) ? sanitize_key($post['status']) : '';
        $status = $status_raw && array_key_exists($status_raw, self::get_status_labels()) ? $status_raw : self::STATUS_ACTIVE;

        $is_new = $dealer_id === 0;
        $existing = $is_new ? null : self::get_dealer($dealer_id);
        if (!$is_new && !$existing) {
            return new WP_Error('not_found', '未找到对应的经销商。');
        }

        $auth_code_to_use = $is_new ? $auth_code_input : $existing->auth_code;
        $auth_code_correct = isset($post['auth_code_correct']) ? sanitize_text_field(wp_unslash($post['auth_code_correct'])) : '';
        $auth_code_confirm = !empty($post['auth_code_correct_confirm']);

        if ($is_new && '' === $auth_code_to_use) {
            return new WP_Error('code_required', '请填写授权编码。');
        }

        if (!$is_new && $auth_code_confirm && $auth_code_correct) {
            $auth_code_to_use = $auth_code_correct;
        }

        if (self::auth_code_exists($auth_code_to_use, $dealer_id)) {
            return new WP_Error('code_exists', '授权编码已存在，无法重复。');
        }

        $now = current_time('mysql');
        $authorized_at = $authorized_raw ? self::normalize_datetime($authorized_raw) : null;

        $data = [
            'dealer_name'  => $dealer_name,
            'contact_name' => $contact_name,
            'phone'        => $phone,
            'address'      => $address,
            'authorized_at'=> $authorized_at,
            'status'       => $status,
            'updated_at'   => $now,
        ];

        $messages = [];

        if ($is_new) {
            $data['auth_code'] = $auth_code_to_use;
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            $dealer_id = (int) $wpdb->insert_id;
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_DEALER_CREATE, 'SUCCESS', ['id' => $dealer_id, 'code' => $auth_code_to_use]);
            $messages[] = '经销商已创建。';
        } else {
            $wpdb->update($table, $data, ['id' => $dealer_id]);
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_DEALER_UPDATE, 'SUCCESS', ['id' => $dealer_id]);
            $messages[] = '经销商已更新。';

            if ($existing->status !== $status) {
                $action = self::STATUS_ACTIVE === $status ? AEGIS_System::ACTION_DEALER_ENABLE : AEGIS_System::ACTION_DEALER_DISABLE;
                AEGIS_Access_Audit::record_event($action, 'SUCCESS', ['id' => $dealer_id, 'from' => $existing->status, 'to' => $status]);
            }

            if ($auth_code_to_use !== $existing->auth_code) {
                $wpdb->update($table, ['auth_code' => $auth_code_to_use], ['id' => $dealer_id]);
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_DEALER_CODE_CORRECT, 'SUCCESS', ['id' => $dealer_id, 'from' => $existing->auth_code, 'to' => $auth_code_to_use]);
                $messages[] = '授权编码已完成受控更正。';
            }
        }

        if ($assets_enabled && isset($files['business_license']) && is_array($files['business_license']) && !empty($files['business_license']['name'])) {
            $upload = AEGIS_Assets_Media::handle_admin_upload($files['business_license'], [
                'bucket'     => 'dealer',
                'owner_type' => 'business_license',
                'owner_id'   => $dealer_id,
                'visibility' => AEGIS_Assets_Media::VISIBILITY_SENSITIVE,
                'meta'       => ['type' => 'business_license', 'dealer_code' => $auth_code_to_use],
            ]);

            if (is_wp_error($upload)) {
                $messages[] = '营业执照上传失败：' . $upload->get_error_message();
            } else {
                $wpdb->update($table, ['business_license_id' => $upload['id']], ['id' => $dealer_id]);
                $messages[] = '营业执照已上传并关联。';
            }
        }

        $dealer = self::get_dealer($dealer_id);

        return [
            'dealer'   => $dealer,
            'messages' => $messages,
        ];
    }

    /**
     * 处理状态切换。
     *
     * @param array $post
     * @return array|WP_Error
     */
    protected static function handle_status_toggle($post) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $dealer_id = isset($post['dealer_id']) ? (int) $post['dealer_id'] : 0;
        $target = isset($post['target_status']) ? sanitize_key($post['target_status']) : '';

        if (!array_key_exists($target, self::get_status_labels())) {
            return new WP_Error('bad_status', '无效的状态。');
        }

        $dealer = self::get_dealer($dealer_id);
        if (!$dealer) {
            return new WP_Error('not_found', '未找到对应的经销商。');
        }

        $wpdb->update($table, ['status' => $target, 'updated_at' => current_time('mysql')], ['id' => $dealer_id]);
        $action = self::STATUS_ACTIVE === $target ? AEGIS_System::ACTION_DEALER_ENABLE : AEGIS_System::ACTION_DEALER_DISABLE;
        AEGIS_Access_Audit::record_event($action, 'SUCCESS', ['id' => $dealer_id, 'from' => $dealer->status, 'to' => $target]);

        return ['message' => '状态已更新。'];
    }

    /**
     * 列出经销商。
     *
     * @return array
     */
    protected static function list_dealers() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
    }

    /**
     * 获取经销商。
     *
     * @param int $id
     * @return object|null
     */
    protected static function get_dealer($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /**
     * 检查授权编码唯一性。
     *
     * @param string $code
     * @param int    $exclude_id
     * @return bool
     */
    protected static function auth_code_exists($code, $exclude_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $sql = "SELECT COUNT(*) FROM {$table} WHERE auth_code = %s";
        $params = [$code];
        if ($exclude_id > 0) {
            $sql .= ' AND id != %d';
            $params[] = $exclude_id;
        }
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        return ((int) $count) > 0;
    }

    /**
     * 状态字典。
     *
     * @return array
     */
    protected static function get_status_labels() {
        return [
            self::STATUS_ACTIVE   => '启用',
            self::STATUS_INACTIVE => '停用',
        ];
    }

    /**
     * 渲染列表。
     *
     * @param array $dealers
     */
    protected static function render_table($dealers) {
        echo '<h2 class="aegis-t-a3" style="margin-top:24px;">经销商列表</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>授权编码</th><th>名称</th><th>联系人</th><th>电话</th><th>状态</th><th>营业执照</th><th>操作</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($dealers)) {
            echo '<tr><td colspan="8">暂无记录。</td></tr>';
        }

        foreach ($dealers as $dealer) {
            $status_label = isset(self::get_status_labels()[$dealer->status]) ? self::get_status_labels()[$dealer->status] : $dealer->status;
            echo '<tr>';
            echo '<td>' . esc_html($dealer->id) . '</td>';
            echo '<td>' . esc_html($dealer->auth_code) . '</td>';
            echo '<td>' . esc_html($dealer->dealer_name) . '</td>';
            echo '<td>' . esc_html($dealer->contact_name) . '</td>';
            echo '<td>' . esc_html($dealer->phone) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '<td>';
            if ($dealer->business_license_id) {
                echo '<div>证：' . esc_html($dealer->business_license_id) . '</div>';
            }
            echo '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url(add_query_arg(['page' => 'aegis-system-dealer', 'edit' => $dealer->id], admin_url('admin.php'))) . '">编辑</a> ';
            $target_status = self::STATUS_ACTIVE === $dealer->status ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce');
            echo '<input type="hidden" name="dealer_action" value="toggle_status" />';
            echo '<input type="hidden" name="dealer_id" value="' . esc_attr($dealer->id) . '" />';
            echo '<input type="hidden" name="target_status" value="' . esc_attr($target_status) . '" />';
            echo '<button type="submit" class="button button-small">' . (self::STATUS_ACTIVE === $dealer->status ? '停用' : '启用') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * 格式化 datetime-local 字段值。
     *
     * @param string|null $value
     * @return string
     */
    protected static function format_datetime_local($value) {
        if (empty($value)) {
            return '';
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '';
        }
        return gmdate('Y-m-d\TH:i', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }

    /**
     * 归一化日期时间。
     *
     * @param string $value
     * @return string|null
     */
    protected static function normalize_datetime($value) {
        if (empty($value)) {
            return null;
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return null;
        }
        return gmdate('Y-m-d H:i:s', $timestamp - (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }
}

class AEGIS_Codes {
    const STATUS_UNUSED = 'unused';
    const STATUS_USED = 'used';

    /**
     * 渲染防伪码管理页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('codes')) {
            echo '<div class="wrap"><h1>防伪码生成</h1><div class="notice notice-warning"><p>请先在模块管理中启用编码管理模块。</p></div></div>';
            return;
        }

        $messages = [];
        $errors = [];

        if (isset($_GET['codes_action'])) {
            $action = sanitize_key(wp_unslash($_GET['codes_action']));
            $batch_id = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
            if ('export' === $action) {
                $result = self::handle_export($batch_id);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            } elseif ('print' === $action) {
                $result = self::handle_print($batch_id);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            }
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $whitelist = ['codes_action', 'ean', 'quantity', 'batch_note', '_wp_http_referer', '_aegis_idempotency', 'aegis_codes_nonce'];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                    'nonce_field'     => 'aegis_codes_nonce',
                    'nonce_action'    => 'aegis_codes_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => $idempotency,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } else {
                $result = self::handle_generate_request($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages = array_merge($messages, $result['messages']);
                }
            }
        }

        $default_start = gmdate('Y-m-d', current_time('timestamp') - 6 * DAY_IN_SECONDS);
        $default_end = gmdate('Y-m-d', current_time('timestamp'));
        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : $default_start;
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : $default_end;
        $start_datetime = self::normalize_date_boundary($start_date, 'start');
        $end_datetime = self::normalize_date_boundary($end_date, 'end');

        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $total = 0;
        $batches = self::query_batches($start_datetime, $end_datetime, $per_page, $paged, $total);
        $sku_options = self::get_sku_options();
        $view_batch = isset($_GET['view']) ? (int) $_GET['view'] : 0;
        $view_codes = $view_batch ? self::get_codes_for_batch($view_batch) : [];
        $view_batch_row = $view_batch ? self::get_batch($view_batch) : null;

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a2">防伪码生成</h1>';
        echo '<p class="aegis-t-a6">按 SKU 生成防伪码批次，默认显示最近 7 天记录。</p>';

        foreach ($messages as $msg) {
            echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $msg) {
            echo '<div class="error"><p>' . esc_html($msg) . '</p></div>';
        }

        self::render_form($sku_options);
        self::render_filters($start_date, $end_date, $per_page, $per_page_options);
        self::render_batches_table($batches, $per_page, $paged, $total, $start_date, $end_date);

        if ($view_batch && $view_batch_row) {
            self::render_codes_table($view_batch_row, $view_codes);
        }

        echo '</div>';
    }

    /**
     * 渲染生成表单。
     */
    protected static function render_form($sku_options) {
        $idempotency_key = wp_generate_uuid4();
        echo '<div class="aegis-t-a5" style="margin-top:20px;">';
        echo '<h2 class="aegis-t-a3">生成新批次</h2>';
        echo '<form method="post" class="aegis-t-a5">';
        wp_nonce_field('aegis_codes_action', 'aegis_codes_nonce');
        echo '<input type="hidden" name="codes_action" value="generate" />';
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';

        echo '<table class="form-table">';
        echo '<tr><th><label for="aegis-code-ean">SKU (EAN)</label></th><td>';
        echo '<select id="aegis-code-ean" name="ean" required>';
        echo '<option value="">选择 SKU</option>';
        foreach ($sku_options as $sku) {
            $label = $sku->ean . ' - ' . $sku->product_name;
            echo '<option value="' . esc_attr($sku->ean) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th><label for="aegis-code-qty">数量</label></th><td>';
        echo '<input type="number" id="aegis-code-qty" name="quantity" min="1" max="100" step="1" required />';
        echo '<p class="description aegis-t-a6">单 SKU 最多 100，单次提交总量不超过 300。</p>';
        echo '</td></tr>';

        echo '<tr><th><label for="aegis-code-note">备注（可选）</label></th><td>';
        echo '<input type="text" id="aegis-code-note" name="batch_note" class="regular-text" />';
        echo '</td></tr>';
        echo '</table>';

        submit_button('生成');
        echo '</form>';
        echo '</div>';
    }

    /**
     * 渲染筛选与分页设置。
     */
    protected static function render_filters($start_date, $end_date, $per_page, $options) {
        echo '<form method="get" class="aegis-t-a6" style="margin-top:15px;">';
        echo '<input type="hidden" name="page" value="aegis-system-codes" />';
        echo '<label>开始日期 <input type="date" name="start_date" value="' . esc_attr($start_date) . '" /></label> ';
        echo '<label>结束日期 <input type="date" name="end_date" value="' . esc_attr($end_date) . '" /></label> ';
        echo '<label>每页 <select name="per_page">';
        foreach ($options as $opt) {
            $selected = selected($per_page, $opt, false);
            echo '<option value="' . esc_attr($opt) . '" ' . $selected . '>' . esc_html($opt) . '</option>';
        }
        echo '</select></label> ';
        submit_button('筛选', 'secondary', '', false);
        echo '</form>';
    }

    /**
     * 渲染批次列表。
     */
    protected static function render_batches_table($batches, $per_page, $paged, $total, $start_date, $end_date) {
        echo '<h2 class="aegis-t-a3" style="margin-top:20px;">批次列表</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>EAN</th><th>数量</th><th>创建人</th><th>创建时间</th><th>操作</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($batches)) {
            echo '<tr><td colspan="6">暂无批次</td></tr>';
        }
        foreach ($batches as $batch) {
            $user = $batch->created_by ? get_userdata($batch->created_by) : null;
            $user_label = $user ? $user->user_login : '-';
            $export_nonce = wp_create_nonce('aegis_codes_export_' . $batch->id);
            $print_nonce = wp_create_nonce('aegis_codes_print_' . $batch->id);
            $view_url = esc_url(add_query_arg([
                'page'       => 'aegis-system-codes',
                'view'       => $batch->id,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'per_page'   => $per_page,
            ], admin_url('admin.php')));
            $export_url = esc_url(wp_nonce_url(add_query_arg([
                'page'        => 'aegis-system-codes',
                'codes_action'=> 'export',
                'batch_id'    => $batch->id,
            ], admin_url('admin.php')), 'aegis_codes_export_' . $batch->id));
            $print_url = esc_url(wp_nonce_url(add_query_arg([
                'page'        => 'aegis-system-codes',
                'codes_action'=> 'print',
                'batch_id'    => $batch->id,
            ], admin_url('admin.php')), 'aegis_codes_print_' . $batch->id));

            echo '<tr>';
            echo '<td>' . esc_html($batch->id) . '</td>';
            echo '<td>' . esc_html($batch->ean) . '</td>';
            echo '<td>' . esc_html($batch->quantity) . '</td>';
            echo '<td>' . esc_html($user_label) . '</td>';
            echo '<td>' . esc_html($batch->created_at) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . $view_url . '">查看</a> ';
            echo '<a class="button button-small" href="' . $export_url . '">导出 CSV</a> ';
            echo '<a class="button button-small" href="' . $print_url . '" target="_blank">打印</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        $total_pages = $per_page > 0 ? ceil($total / $per_page) : 1;
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            if ($paged > 1) {
                $prev_url = esc_url(add_query_arg([
                    'page'       => 'aegis-system-codes',
                    'paged'      => $paged - 1,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'per_page'   => $per_page,
                ], admin_url('admin.php')));
                echo '<a class="button" href="' . $prev_url . '">上一页</a> ';
            }
            echo '<span class="aegis-t-a6">第 ' . esc_html($paged) . ' / ' . esc_html($total_pages) . ' 页</span> ';
            if ($paged < $total_pages) {
                $next_url = esc_url(add_query_arg([
                    'page'       => 'aegis-system-codes',
                    'paged'      => $paged + 1,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'per_page'   => $per_page,
                ], admin_url('admin.php')));
                echo '<a class="button" href="' . $next_url . '">下一页</a>';
            }
            echo '</div></div>';
        }
    }

    /**
     * 渲染批次内码列表。
     */
    protected static function render_codes_table($batch, $codes) {
        echo '<h2 class="aegis-t-a3" style="margin-top:20px;">批次 #' . esc_html($batch->id) . ' 代码列表</h2>';
        echo '<p class="aegis-t-a6">EAN：' . esc_html($batch->ean) . '，数量：' . esc_html($batch->quantity) . '</p>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>Code</th><th>状态</th><th>创建时间</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($codes)) {
            echo '<tr><td colspan="4">无数据</td></tr>';
        }
        foreach ($codes as $code) {
            echo '<tr>';
            echo '<td>' . esc_html($code->id) . '</td>';
            echo '<td>' . esc_html($code->code) . '</td>';
            echo '<td>' . esc_html($code->status) . '</td>';
            echo '<td>' . esc_html($code->created_at) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * 处理生成请求。
     */
    protected static function handle_generate_request($post) {
        global $wpdb;
        $batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $ean = isset($post['ean']) ? sanitize_text_field(wp_unslash($post['ean'])) : '';
        $quantity = isset($post['quantity']) ? absint($post['quantity']) : 0;
        $batch_note = isset($post['batch_note']) ? sanitize_text_field(wp_unslash($post['batch_note'])) : '';

        if ('' === $ean) {
            return new WP_Error('ean_missing', '请选择 SKU。');
        }

        if ($quantity < 1) {
            return new WP_Error('quantity_invalid', '数量需大于 0。');
        }

        if ($quantity > 100) {
            return new WP_Error('quantity_exceed', '单个 SKU 生成数量不得超过 100。');
        }

        if ($quantity > 300) {
            return new WP_Error('total_exceed', '单次生成总量不得超过 300。');
        }

        $sku = $wpdb->get_row($wpdb->prepare("SELECT id, ean, status FROM {$sku_table} WHERE ean = %s", $ean));
        if (!$sku) {
            return new WP_Error('sku_missing', '未找到对应 SKU。');
        }

        $now = current_time('mysql');
        $wpdb->insert(
            $batch_table,
            [
                'ean'        => $ean,
                'quantity'   => $quantity,
                'created_by' => get_current_user_id(),
                'created_at' => $now,
                'meta'       => $batch_note ? wp_json_encode(['note' => $batch_note]) : null,
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );

        $batch_id = (int) $wpdb->insert_id;
        $codes = self::generate_unique_codes($quantity);
        if (is_wp_error($codes)) {
            return $codes;
        }

        foreach ($codes as $code) {
            $wpdb->insert(
                $code_table,
                [
                    'batch_id'  => $batch_id,
                    'ean'       => $ean,
                    'code'      => $code,
                    'status'    => self::STATUS_UNUSED,
                    'created_at'=> $now,
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_CODE_BATCH_CREATE,
            'SUCCESS',
            [
                'batch_id' => $batch_id,
                'ean'      => $ean,
                'quantity' => $quantity,
            ]
        );

        return [
            'messages' => ['批次 #' . $batch_id . ' 已生成，共 ' . $quantity . ' 条。'],
        ];
    }

    /**
     * 生成唯一编码集合。
     */
    protected static function generate_unique_codes($quantity) {
        global $wpdb;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $codes = [];
        $attempts = 0;

        while (count($codes) < $quantity && $attempts < $quantity * 10) {
            $candidate = strtoupper(wp_generate_password(16, false, false));
            $attempts++;
            if (isset($codes[$candidate])) {
                continue;
            }
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$code_table} WHERE code = %s", $candidate));
            if ($exists) {
                continue;
            }
            $codes[$candidate] = $candidate;
        }

        if (count($codes) < $quantity) {
            return new WP_Error('code_generate_fail', '生成唯一编码失败，请重试。');
        }

        return array_values($codes);
    }

    /**
     * 查询批次。
     */
    protected static function query_batches($start, $end, $per_page, $paged, &$total) {
        global $wpdb;
        $batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        $offset = ($paged - 1) * $per_page;
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$batch_table} WHERE created_at BETWEEN %s AND %s",
                $start,
                $end
            )
        );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$batch_table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $start,
                $end,
                $per_page,
                $offset
            )
        );
    }

    /**
     * 获取批次详情。
     */
    protected static function get_batch($batch_id) {
        global $wpdb;
        $batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$batch_table} WHERE id = %d", $batch_id));
    }

    /**
     * 获取批次内码列表。
     */
    protected static function get_codes_for_batch($batch_id) {
        global $wpdb;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        return $wpdb->get_results($wpdb->prepare("SELECT id, code, status, created_at FROM {$code_table} WHERE batch_id = %d ORDER BY id ASC", $batch_id));
    }

    /**
     * 获取 SKU 选项。
     */
    protected static function get_sku_options() {
        global $wpdb;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_results("SELECT ean, product_name FROM {$sku_table} ORDER BY created_at DESC");
    }

    /**
     * 处理导出。
     */
    protected static function handle_export($batch_id) {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            return new WP_Error('forbidden', '权限不足');
        }

        if (!AEGIS_System::is_module_enabled('codes')) {
            return new WP_Error('module_disabled', '模块未启用');
        }

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'aegis_codes_export_' . $batch_id)) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_EXPORT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'nonce']);
            return new WP_Error('nonce', '安全校验失败');
        }

        $batch = self::get_batch($batch_id);
        if (!$batch) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_EXPORT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'not_found']);
            return new WP_Error('not_found', '批次不存在');
        }

        $codes = self::get_codes_for_batch($batch_id);
        $filename = 'aegis-codes-batch-' . $batch_id . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputcsv($output, ['code', 'ean', 'status']);
        foreach ($codes as $code) {
            fputcsv($output, [$code->code, $batch->ean, $code->status]);
        }
        fclose($output);

        global $wpdb;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $wpdb->update($code_table, ['exported_at' => current_time('mysql')], ['batch_id' => $batch_id]);

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_EXPORT, 'SUCCESS', ['batch_id' => $batch_id, 'count' => count($codes)]);
        exit;
    }

    /**
     * 处理打印视图。
     */
    protected static function handle_print($batch_id) {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            return new WP_Error('forbidden', '权限不足');
        }

        if (!AEGIS_System::is_module_enabled('codes')) {
            return new WP_Error('module_disabled', '模块未启用');
        }

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'aegis_codes_print_' . $batch_id)) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_PRINT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'nonce']);
            return new WP_Error('nonce', '安全校验失败');
        }

        $batch = self::get_batch($batch_id);
        if (!$batch) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_PRINT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'not_found']);
            return new WP_Error('not_found', '批次不存在');
        }

        $codes = self::get_codes_for_batch($batch_id);

        global $wpdb;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $wpdb->update($code_table, ['printed_at' => current_time('mysql')], ['batch_id' => $batch_id]);

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_PRINT, 'SUCCESS', ['batch_id' => $batch_id, 'count' => count($codes)]);

        echo '<html><head><meta charset="utf-8"><title>批次打印</title>';
        echo '<style>.aegis-print{font-family:Arial;margin:20px;} .aegis-print h1{font-size:20px;} .aegis-print table{width:100%;border-collapse:collapse;} .aegis-print th,.aegis-print td{border:1px solid #ddd;padding:6px;text-align:left;}</style>';
        echo '</head><body class="aegis-print">';
        echo '<h1>批次 #' . esc_html($batch->id) . ' 防伪码</h1>';
        echo '<p>EAN：' . esc_html($batch->ean) . ' 数量：' . esc_html($batch->quantity) . '</p>';
        echo '<table><thead><tr><th>ID</th><th>Code</th></tr></thead><tbody>';
        foreach ($codes as $code) {
            echo '<tr><td>' . esc_html($code->id) . '</td><td>' . esc_html($code->code) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</body></html>';
        exit;
    }

    /**
     * 日期边界格式化。
     */
    protected static function normalize_date_boundary($date, $type) {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            $timestamp = current_time('timestamp');
        }
        if ('end' === $type) {
            return gmdate('Y-m-d 23:59:59', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
        }
        return gmdate('Y-m-d 00:00:00', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }
}

class AEGIS_Orders {
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * 渲染订单与付款页面。
     */
    public static function render_admin_page() {
        $orders_enabled = AEGIS_System::is_module_enabled('orders');
        $payments_enabled = $orders_enabled && AEGIS_System::is_module_enabled('payments');
        $is_dealer_user = AEGIS_System_Roles::is_dealer_only();

        if (!$orders_enabled) {
            wp_die(__('订单模块未启用。'));
        }

        if (!$is_dealer_user && !AEGIS_System_Roles::user_can_manage_warehouse() && !current_user_can(AEGIS_System::CAP_ORDERS)) {
            wp_die(__('您无权访问该页面。'));
        }

        $messages = [];
        $errors = [];
        $dealer = $is_dealer_user ? self::get_current_dealer() : null;

        if ($is_dealer_user && !$dealer) {
            $errors[] = '未找到您的经销商档案。';
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['order_action']) ? sanitize_key(wp_unslash($_POST['order_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;

            if ('create_order' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'dealer_id', 'order_no', 'order_status', 'order_items', 'order_total', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $result = self::handle_create_order($_POST);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                    }
                }
            } elseif ('upload_payment' === $action && $payments_enabled) {
                $capability = $is_dealer_user ? AEGIS_System::CAP_ACCESS_ROOT : AEGIS_System::CAP_MANAGE_WAREHOUSE;
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => $capability,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'order_id', 'payment_status', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif (!isset($_FILES['payment_proof'])) {
                    $errors[] = '请上传付款凭证。';
                } else {
                    $result = self::handle_payment_upload($_POST, $_FILES);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                    }
                }
            } elseif ('toggle_link' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_MANAGE_SYSTEM,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'enable_order_link', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $link_enabled = !empty($_POST['enable_order_link']);
                    update_option(AEGIS_System::ORDER_SHIPMENT_LINK_OPTION, $link_enabled, true);
                    $messages[] = $link_enabled ? '已开启订单-出库关联。' : '已关闭订单-出库关联。';
                }
            }
        }

        $default_start = gmdate('Y-m-d', current_time('timestamp') - 6 * DAY_IN_SECONDS);
        $default_end = gmdate('Y-m-d', current_time('timestamp'));
        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : $default_start;
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : $default_end;
        $start_datetime = self::normalize_date_boundary($start_date, 'start');
        $end_datetime = self::normalize_date_boundary($end_date, 'end');

        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $total = 0;
        $dealer_filter = $is_dealer_user && $dealer ? (int) $dealer->id : null;
        $orders = self::query_orders($start_datetime, $end_datetime, $per_page, $paged, $total, $dealer_filter);
        $view_order_id = isset($_GET['view']) ? (int) $_GET['view'] : 0;
        $view_order = $view_order_id ? self::get_order($view_order_id) : null;
        $order_items = $view_order ? self::get_items($view_order_id) : [];
        $dealer_options = self::list_dealers();
        $link_enabled = self::is_shipment_link_enabled();

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a2">订单管理</h1>';
        echo '<p class="aegis-t-a6">模块默认关闭，启用后可创建订单与上传付款凭证。经销商仅可查看自身订单。</p>';

        foreach ($messages as $msg) {
            echo '<div class="updated"><p class="aegis-t-a6">' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $msg) {
            echo '<div class="error"><p class="aegis-t-a6">' . esc_html($msg) . '</p></div>';
        }

        echo '<div style="display:flex;gap:20px;align-items:flex-start;">';
        echo '<div style="flex:2;">';
        self::render_filters($start_date, $end_date, $per_page, $per_page_options);
        self::render_orders_table($orders, $per_page, $paged, $total, $start_date, $end_date, $dealer_filter);

        if ($view_order && self::current_user_can_view_order($view_order)) {
            self::render_order_detail($view_order, $order_items, $payments_enabled);
        }
        echo '</div>';

        echo '<div style="flex:1;">';
        if (!$is_dealer_user) {
            self::render_create_form($dealer_options);
            self::render_link_toggle($link_enabled);
        }

        if ($payments_enabled && $view_order && self::current_user_can_view_order($view_order)) {
            self::render_payment_form($view_order);
        } elseif ($payments_enabled && $is_dealer_user) {
            echo '<div class="notice notice-info"><p class="aegis-t-a6">请选择订单以提交付款凭证。</p></div>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    public static function current_user_can_view_order($order) {
        if (AEGIS_System_Roles::user_can_manage_warehouse() || current_user_can(AEGIS_System::CAP_ORDERS)) {
            return true;
        }
        $dealer = self::get_current_dealer();
        return $dealer && (int) $dealer->id === (int) $order->dealer_id;
    }

    public static function get_order($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $order_id));
    }

    public static function get_order_by_no($order_no) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_no = %s", $order_no));
    }

    public static function is_shipment_link_enabled() {
        return AEGIS_System::is_module_enabled('orders') && (bool) get_option(AEGIS_System::ORDER_SHIPMENT_LINK_OPTION, false);
    }

    protected static function render_filters($start, $end, $per_page, $per_page_options) {
        echo '<form method="get" class="aegis-t-a6" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="aegis-system-orders" />';
        echo '起始：<input type="date" name="start_date" value="' . esc_attr($start) . '" /> ';
        echo '结束：<input type="date" name="end_date" value="' . esc_attr($end) . '" /> ';
        echo '每页：<select name="per_page">';
        foreach ($per_page_options as $opt) {
            $sel = $opt === $per_page ? 'selected' : '';
            echo '<option value="' . esc_attr($opt) . '" ' . $sel . '>' . esc_html($opt) . '</option>';
        }
        echo '</select> ';
        submit_button('筛选', 'primary', '', false);
        echo '</form>';
    }

    protected static function render_orders_table($orders, $per_page, $paged, $total, $start_date, $end_date, $dealer_filter) {
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>订单号</th><th>经销商</th><th>状态</th><th>金额</th><th>创建时间</th><th></th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($orders)) {
            echo '<tr><td colspan="7">暂无记录</td></tr>';
        }
        foreach ($orders as $order) {
            $view_url = esc_url(add_query_arg([
                'page'       => 'aegis-system-orders',
                'view'       => $order->id,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'per_page'   => $per_page,
            ], admin_url('admin.php')));
            echo '<tr>';
            echo '<td>' . esc_html($order->id) . '</td>';
            echo '<td>' . esc_html($order->order_no) . '</td>';
            echo '<td>' . esc_html($order->dealer_name) . '</td>';
            echo '<td>' . esc_html($order->status) . '</td>';
            echo '<td>' . esc_html(number_format((float) $order->total_amount, 2)) . '</td>';
            echo '<td>' . esc_html($order->created_at) . '</td>';
            echo '<td><a class="button" href="' . $view_url . '">查看</a></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        $total_pages = $per_page > 0 ? ceil($total / $per_page) : 1;
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            if ($paged > 1) {
                $prev_url = esc_url(add_query_arg([
                    'page'       => 'aegis-system-orders',
                    'paged'      => $paged - 1,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'per_page'   => $per_page,
                ], admin_url('admin.php')));
                echo '<a class="button" href="' . $prev_url . '">上一页</a> ';
            }
            echo '<span class="aegis-t-a6">第 ' . esc_html($paged) . ' / ' . esc_html($total_pages) . ' 页</span> ';
            if ($paged < $total_pages) {
                $next_url = esc_url(add_query_arg([
                    'page'       => 'aegis-system-orders',
                    'paged'      => $paged + 1,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'per_page'   => $per_page,
                ], admin_url('admin.php')));
                echo '<a class="button" href="' . $next_url . '">下一页</a>';
            }
            echo '</div></div>';
        }
    }

    protected static function render_create_form($dealers) {
        $idempotency_key = wp_generate_uuid4();
        echo '<div class="postbox" style="padding:12px;">';
        echo '<h2 class="aegis-t-a4">创建订单</h2>';
        echo '<form method="post">';
        wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce');
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';
        echo '<input type="hidden" name="order_action" value="create_order" />';
        echo '<table class="form-table aegis-t-a6">';
        echo '<tr><th><label for="dealer_id">经销商</label></th><td><select name="dealer_id" id="dealer_id">';
        foreach ($dealers as $dealer) {
            echo '<option value="' . esc_attr($dealer->id) . '">' . esc_html($dealer->dealer_name) . ' (' . esc_html($dealer->auth_code) . ')</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="order_no">订单号（可选）</label></th><td><input type="text" name="order_no" id="order_no" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="order_total">金额（可选）</label></th><td><input type="number" step="0.01" name="order_total" id="order_total" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="order_items">明细</label></th><td><textarea name="order_items" id="order_items" rows="6" class="large-text" placeholder="每行：EAN|数量"></textarea><p class="description">支持 A1-A6 排版类名，数量默认 1。</p></td></tr>';
        echo '</table>';
        submit_button('创建订单');
        echo '</form>';
        echo '</div>';
    }

    protected static function render_order_detail($order, $items, $payments_enabled) {
        echo '<div class="postbox" style="margin-top:16px; padding:12px;">';
        echo '<h2 class="aegis-t-a4">订单 #' . esc_html($order->id) . '</h2>';
        echo '<p class="aegis-t-a6">订单号：' . esc_html($order->order_no) . ' | 状态：' . esc_html($order->status) . ' | 经销商 ID：' . esc_html($order->dealer_id) . '</p>';
        echo '<p class="aegis-t-a6">金额：' . esc_html(number_format((float) $order->total_amount, 2)) . ' | 创建：' . esc_html($order->created_at) . '</p>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>EAN</th><th>数量</th><th>状态</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($items)) {
            echo '<tr><td colspan="4">暂无明细</td></tr>';
        }
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item->id) . '</td>';
            echo '<td>' . esc_html($item->ean) . '</td>';
            echo '<td>' . esc_html($item->quantity) . '</td>';
            echo '<td>' . esc_html($item->status) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        if ($payments_enabled) {
            $proofs = self::get_payments($order->id);
            echo '<h3 class="aegis-t-a5" style="margin-top:12px;">付款凭证</h3>';
            echo '<ul class="aegis-t-a6">';
            if (empty($proofs)) {
                echo '<li>暂无凭证</li>';
            }
            foreach ($proofs as $proof) {
                $download_url = esc_url(add_query_arg(['rest_route' => '/aegis-system/media', 'id' => $proof->media_id], site_url('/')));
                echo '<li>凭证 #' . esc_html($proof->id) . ' 状态：' . esc_html($proof->status) . ' <a href="' . $download_url . '" target="_blank">下载</a></li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    protected static function render_payment_form($order) {
        $idempotency_key = wp_generate_uuid4();
        echo '<div class="postbox" style="padding:12px; margin-top:16px;">';
        echo '<h2 class="aegis-t-a4">上传付款凭证</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce');
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';
        echo '<input type="hidden" name="order_action" value="upload_payment" />';
        echo '<input type="hidden" name="order_id" value="' . esc_attr($order->id) . '" />';
        echo '<table class="form-table aegis-t-a6">';
        echo '<tr><th><label for="payment_proof">凭证文件</label></th><td><input type="file" name="payment_proof" id="payment_proof" required /></td></tr>';
        echo '<tr><th><label for="payment_status">状态</label></th><td><select name="payment_status" id="payment_status">';
        echo '<option value="submitted">已提交</option>';
        echo '<option value="confirmed">已确认</option>';
        echo '</select></td></tr>';
        echo '</table>';
        submit_button('上传凭证');
        echo '</form>';
        echo '</div>';
    }

    protected static function render_link_toggle($enabled) {
        $idempotency_key = wp_generate_uuid4();
        echo '<div class="postbox" style="padding:12px; margin-top:16px;">';
        echo '<h2 class="aegis-t-a4">订单-出库关联</h2>';
        echo '<form method="post">';
        wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce');
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';
        echo '<input type="hidden" name="order_action" value="toggle_link" />';
        echo '<label class="aegis-t-a6"><input type="checkbox" name="enable_order_link" value="1" ' . checked($enabled, true, false) . ' /> 启用出库选择订单（默认关闭）。</label>';
        submit_button('保存设置');
        echo '</form>';
        echo '</div>';
    }

    protected static function handle_create_order($post) {
        global $wpdb;
        $dealer_id = isset($post['dealer_id']) ? (int) $post['dealer_id'] : 0;
        $order_no = isset($post['order_no']) ? sanitize_text_field(wp_unslash($post['order_no'])) : '';
        $order_items = isset($post['order_items']) ? (string) wp_unslash($post['order_items']) : '';
        $order_total = isset($post['order_total']) ? (float) $post['order_total'] : null;

        if ($dealer_id <= 0) {
            return new WP_Error('bad_dealer', '请选择经销商。');
        }

        $dealer = self::get_dealer($dealer_id);
        if (!$dealer || $dealer->status !== 'active') {
            return new WP_Error('dealer_inactive', '经销商不存在或已停用。');
        }

        if ('' === $order_no) {
            $order_no = 'ORD-' . gmdate('Ymd-His', current_time('timestamp'));
        }

        $items = self::parse_items($order_items);
        $now = current_time('mysql');
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;

        $wpdb->insert(
            $order_table,
            [
                'order_no'     => $order_no,
                'dealer_id'    => $dealer_id,
                'status'       => self::STATUS_PENDING,
                'total_amount' => $order_total,
                'created_by'   => get_current_user_id(),
                'created_at'   => $now,
                'updated_at'   => $now,
                'meta'         => $items ? wp_json_encode(['item_count' => count($items)]) : null,
            ],
            ['%s', '%d', '%s', '%f', '%d', '%s', '%s', '%s']
        );

        if (!$wpdb->insert_id) {
            return new WP_Error('order_failed', '订单创建失败。');
        }

        $order_id = (int) $wpdb->insert_id;
        foreach ($items as $item) {
            $wpdb->insert(
                $item_table,
                [
                    'order_id' => $order_id,
                    'ean'      => $item['ean'],
                    'quantity' => $item['qty'],
                    'status'   => 'open',
                    'meta'     => null,
                ],
                ['%d', '%s', '%d', '%s', '%s']
            );
        }

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_ORDER_CREATE, 'SUCCESS', ['order_id' => $order_id, 'dealer_id' => $dealer_id]);

        return ['message' => '订单已创建，编号 ' . $order_no];
    }

    protected static function handle_payment_upload($post, $files) {
        global $wpdb;
        $order_id = isset($post['order_id']) ? (int) $post['order_id'] : 0;
        $status = isset($post['payment_status']) ? sanitize_key($post['payment_status']) : 'submitted';

        if ($order_id <= 0) {
            return new WP_Error('order_missing', '订单不存在。');
        }

        if (!AEGIS_System::is_module_enabled('payments')) {
            return new WP_Error('module_disabled', '支付模块未启用。');
        }

        $order = self::get_order($order_id);
        if (!$order) {
            return new WP_Error('order_missing', '订单不存在。');
        }

        if (!self::current_user_can_view_order($order)) {
            return new WP_Error('forbidden', '无权上传该订单凭证。');
        }

        $upload = AEGIS_Assets_Media::handle_admin_upload(
            $files['payment_proof'],
            [
                'bucket'              => 'payments',
                'owner_type'          => 'payment_proof',
                'owner_id'            => $order_id,
                'visibility'          => AEGIS_Assets_Media::VISIBILITY_SENSITIVE,
                'allow_dealer_payment'=> true,
                'capability'          => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                'permission_callback' => function () use ($order) {
                    return AEGIS_Orders::current_user_can_view_order($order);
                },
            ]
        );

        if (is_wp_error($upload)) {
            return $upload;
        }

        $payment_table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;
        $wpdb->insert(
            $payment_table,
            [
                'order_id'   => $order_id,
                'dealer_id'  => $order->dealer_id,
                'media_id'   => isset($upload['id']) ? (int) $upload['id'] : 0,
                'status'     => $status,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%d', '%s']
        );

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_PAYMENT_UPLOAD, 'SUCCESS', ['order_id' => $order_id, 'media_id' => isset($upload['id']) ? (int) $upload['id'] : 0]);

        return ['message' => '付款凭证已上传。'];
    }

    protected static function parse_items($input) {
        $items = [];
        $lines = preg_split('/\r?\n/', $input);
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $parts = explode('|', $line);
            $ean = sanitize_text_field($parts[0]);
            $qty = isset($parts[1]) ? (int) $parts[1] : 1;
            if ($qty <= 0) {
                $qty = 1;
            }
            $items[] = ['ean' => $ean, 'qty' => $qty];
        }
        return $items;
    }

    protected static function query_orders($start, $end, $per_page, $paged, &$total, $dealer_id = null) {
        global $wpdb;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $offset = ($paged - 1) * $per_page;
        $where = 'created_at BETWEEN %s AND %s';
        $params = [$start, $end];
        if ($dealer_id) {
            $where .= ' AND dealer_id = %d';
            $params[] = $dealer_id;
        }
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$order_table} WHERE {$where}", $params));

        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*, d.dealer_name FROM {$order_table} o LEFT JOIN {$dealer_table} d ON o.dealer_id = d.id WHERE {$where} ORDER BY o.created_at DESC LIMIT %d OFFSET %d",
                $params
            )
        );
    }

    protected static function get_items($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d", $order_id));
    }

    protected static function get_payments($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC", $order_id));
    }

    protected static function list_dealers() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY dealer_name ASC");
    }

    protected static function get_dealer($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    protected static function get_current_dealer() {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return null;
        }
        $dealer_id = get_user_meta($user->ID, AEGIS_Reset_B::DEALER_META_KEY, true);
        $dealer_id = (int) $dealer_id;
        if ($dealer_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $dealer_id));
    }

    protected static function normalize_date_boundary($date, $type) {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            $timestamp = current_time('timestamp');
        }
        if ('end' === $type) {
            return gmdate('Y-m-d 23:59:59', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
        }
        return gmdate('Y-m-d 00:00:00', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }
}

class AEGIS_Shipments {
    /**
     * 渲染扫码出库页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('shipments')) {
            echo '<div class="wrap"><h1 class="aegis-t-a3">扫码出库</h1><div class="notice notice-warning"><p class="aegis-t-a6">请先在模块管理中启用出货管理模块。</p></div></div>';
            return;
        }

        $messages = [];
        $errors = [];

        if (isset($_GET['shipments_action']) && 'export' === sanitize_key(wp_unslash($_GET['shipments_action']))) {
            $shipment_id = isset($_GET['shipment_id']) ? (int) $_GET['shipment_id'] : 0;
            $result = self::handle_export($shipment_id);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            }
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $whitelist = ['shipment_action', 'dealer_id', 'shipment_no', 'order_ref', 'codes', '_wp_http_referer', 'aegis_shipments_nonce', '_aegis_idempotency'];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_USE_WAREHOUSE,
                    'nonce_field'     => 'aegis_shipments_nonce',
                    'nonce_action'    => 'aegis_shipments_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => $idempotency,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } else {
                $result = self::handle_create_request($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages[] = $result['message'];
                }
            }
        }

        $default_start = gmdate('Y-m-d', current_time('timestamp') - 6 * DAY_IN_SECONDS);
        $default_end = gmdate('Y-m-d', current_time('timestamp'));
        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : $default_start;
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : $default_end;
        $start_datetime = self::normalize_date_boundary($start_date, 'start');
        $end_datetime = self::normalize_date_boundary($end_date, 'end');

        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $total = 0;
        $shipments = self::query_shipments($start_datetime, $end_datetime, $per_page, $paged, $total);

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a2">扫码出库</h1>';
        if ($order_link_enabled) {
            echo '<p class="aegis-t-a6">订单关联已开启，请确保选择的订单与经销商匹配。</p>';
        } else {
            echo '<p class="aegis-t-a6">订单关联默认关闭，出库单仅绑定经销商与扫码记录。</p>';
        }

        foreach ($messages as $msg) {
            echo '<div class="notice notice-success"><p class="aegis-t-a6">' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $error) {
            echo '<div class="notice notice-error"><p class="aegis-t-a6">' . esc_html($error) . '</p></div>';
        }

        self::render_create_form($order_link_enabled);

        self::render_filters($start_date, $end_date, $per_page, $per_page_options);

        self::render_shipments_table($shipments, $per_page, $paged, $total, $start_date, $end_date);

        if (isset($_GET['view'])) {
            $view_id = (int) $_GET['view'];
            self::render_shipment_detail($view_id);
        }

        echo '</div>';
    }

    /**
     * 处理出库创建请求。
     *
     * @param array $data
     * @return array|WP_Error
     */
    protected static function handle_create_request($data) {
        global $wpdb;
        $dealer_id = isset($data['dealer_id']) ? (int) $data['dealer_id'] : 0;
        $shipment_no = isset($data['shipment_no']) ? sanitize_text_field(wp_unslash($data['shipment_no'])) : '';
        $order_ref = isset($data['order_ref']) ? sanitize_text_field(wp_unslash($data['order_ref'])) : '';
        $codes_input = isset($data['codes']) ? wp_unslash($data['codes']) : '';

        $order_link_enabled = AEGIS_Orders::is_shipment_link_enabled();
        $order_ref = $order_link_enabled ? $order_ref : '';
        $order = null;

        if ($dealer_id <= 0) {
            return new WP_Error('invalid_dealer', '请选择经销商。');
        }

        $dealer = self::get_dealer($dealer_id);
        if (!$dealer) {
            return new WP_Error('invalid_dealer', '经销商不存在。');
        }
        if ('active' !== $dealer->status) {
            return new WP_Error('dealer_inactive', '经销商已停用，禁止新出库。');
        }

        if ($order_ref) {
            if (!AEGIS_System::is_module_enabled('orders')) {
                return new WP_Error('order_disabled', '订单模块未启用，无法关联。');
            }
            $order = AEGIS_Orders::get_order_by_no($order_ref);
            if (!$order) {
                return new WP_Error('order_missing', '未找到关联订单。');
            }
            if ((int) $order->dealer_id !== (int) $dealer_id) {
                return new WP_Error('order_mismatch', '订单与经销商不匹配。');
            }
        }

        $codes = self::parse_codes($codes_input);
        if (empty($codes)) {
            return new WP_Error('no_codes', '请输入要出库的防伪码。');
        }

        $validated_codes = self::validate_codes($codes);
        if (is_wp_error($validated_codes)) {
            return $validated_codes;
        }

        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;

        if ('' === $shipment_no) {
            $shipment_no = 'SHP-' . gmdate('Ymd-His', current_time('timestamp'));
        }

        $inserted = $wpdb->insert(
            $shipment_table,
            [
                'shipment_no' => $shipment_no,
                'dealer_id'   => $dealer_id,
                'created_by'  => get_current_user_id(),
                'created_at'  => current_time('mysql'),
                'order_ref'   => $order_ref ? $order_ref : null,
                'status'      => 'created',
                'meta'        => wp_json_encode(['count' => count($validated_codes), 'order_ref' => $order_ref]),
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_CREATE, 'FAIL', ['dealer_id' => $dealer_id, 'error' => $wpdb->last_error]);
            return new WP_Error('insert_failed', '出库单创建失败，请重试。');
        }

        $shipment_id = (int) $wpdb->insert_id;
        $now = current_time('mysql');

        foreach ($validated_codes as $code) {
            $wpdb->insert(
                $shipment_item_table,
                [
                    'shipment_id' => $shipment_id,
                    'code_id'     => $code->id,
                    'code_value'  => $code->code,
                    'ean'         => $code->ean,
                    'scanned_at'  => $now,
                    'meta'        => null,
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s']
            );
        }

        foreach ($validated_codes as $code) {
            $wpdb->update(
                $code_table,
                ['status' => 'used'],
                ['id' => $code->id],
                ['%s'],
                ['%d']
            );
        }

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_CREATE, 'SUCCESS', [
            'shipment_id' => $shipment_id,
            'dealer_id'   => $dealer_id,
            'count'       => count($validated_codes),
        ]);

        return [
            'message' => '出库成功，出库单号：' . $shipment_no,
        ];
    }

    /**
     * 校验码集合。
     *
     * @param array $codes
     * @return array|WP_Error
     */
    protected static function validate_codes($codes) {
        global $wpdb;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;

        $placeholders = implode(',', array_fill(0, count($codes), '%s'));
        $query = $wpdb->prepare("SELECT * FROM {$code_table} WHERE code IN ($placeholders)", $codes);
        $rows = $wpdb->get_results($query);

        if (count($rows) !== count($codes)) {
            $missing = array_diff($codes, wp_list_pluck($rows, 'code'));
            return new WP_Error('code_missing', '以下防伪码不存在：' . implode(', ', array_map('esc_html', $missing)));
        }

        $invalid = [];
        foreach ($rows as $row) {
            if ('unused' !== $row->status) {
                $invalid[] = $row->code;
            }
        }
        if (!empty($invalid)) {
            return new WP_Error('code_used', '以下防伪码已出库或不可用：' . implode(', ', array_map('esc_html', $invalid)));
        }

        $code_ids = wp_list_pluck($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($code_ids), '%d'));
        $existing = $wpdb->get_col($wpdb->prepare("SELECT code_id FROM {$shipment_item_table} WHERE code_id IN ($placeholders)", $code_ids));
        if (!empty($existing)) {
            $existing_codes = [];
            foreach ($rows as $row) {
                if (in_array($row->id, $existing, true)) {
                    $existing_codes[] = $row->code;
                }
            }
            return new WP_Error('code_duplicate', '以下防伪码已出库：' . implode(', ', array_map('esc_html', $existing_codes)));
        }

        return $rows;
    }

    /**
     * 渲染创建表单。
     */
    protected static function render_create_form($order_link_enabled = false) {
        $dealers = self::get_active_dealers();
        echo '<h2 class="aegis-t-a3" style="margin-top:20px;">新建出库单</h2>';
        echo '<form method="post" class="aegis-t-a6" style="max-width:760px;">';
        wp_nonce_field('aegis_shipments_action', 'aegis_shipments_nonce');
        echo '<input type="hidden" name="shipment_action" value="create" />';
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr(wp_generate_uuid4()) . '" />';

        echo '<p><label class="aegis-t-a6">经销商 <select name="dealer_id" required>';
        echo '<option value="">请选择</option>';
        foreach ($dealers as $dealer) {
            echo '<option value="' . esc_attr($dealer->id) . '">' . esc_html($dealer->dealer_name . '（' . $dealer->auth_code . '）') . '</option>';
        }
        echo '</select></label></p>';

        echo '<p><label class="aegis-t-a6">出库单号 <input type="text" name="shipment_no" placeholder="可留空自动生成" /></label></p>';
        if ($order_link_enabled && AEGIS_System::is_module_enabled('orders')) {
            echo '<p><label class="aegis-t-a6">关联订单号（可选） <input type="text" name="order_ref" placeholder="订单号需匹配经销商" /></label></p>';
        }
        echo '<p><label class="aegis-t-a6">防伪码（每行一个）<br />';
        echo '<textarea name="codes" rows="6" style="width:100%;"></textarea>';
        echo '</label></p>';
        submit_button('提交出库', 'primary', '', false);
        echo '</form>';
    }

    /**
     * 渲染筛选表单。
     */
    protected static function render_filters($start_date, $end_date, $per_page, $options) {
        echo '<form method="get" class="aegis-t-a6" style="margin-top:20px;">';
        echo '<input type="hidden" name="page" value="aegis-system-shipments" />';
        echo '<label>开始日期 <input type="date" name="start_date" value="' . esc_attr($start_date) . '" /></label> ';
        echo '<label>结束日期 <input type="date" name="end_date" value="' . esc_attr($end_date) . '" /></label> ';
        echo '<label>每页 <select name="per_page">';
        foreach ($options as $opt) {
            $selected = selected($per_page, $opt, false);
            echo '<option value="' . esc_attr($opt) . '" ' . $selected . '>' . esc_html($opt) . '</option>';
        }
        echo '</select></label> ';
        submit_button('筛选', 'secondary', '', false);
        echo '</form>';
    }

    /**
     * 渲染出库单列表。
     */
    protected static function render_shipments_table($shipments, $per_page, $paged, $total, $start_date, $end_date) {
        echo '<h2 class="aegis-t-a3" style="margin-top:20px;">出库单列表</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>出库单号</th><th>经销商</th><th>数量</th><th>创建人</th><th>创建时间</th><th>操作</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($shipments)) {
            echo '<tr><td colspan="7">暂无数据</td></tr>';
        }
        foreach ($shipments as $shipment) {
            $dealer = self::get_dealer($shipment->dealer_id);
            $dealer_label = $dealer ? $dealer->dealer_name : '-';
            $user = $shipment->created_by ? get_userdata($shipment->created_by) : null;
            $user_label = $user ? $user->user_login : '-';
            $view_url = esc_url(add_query_arg([
                'page'       => 'aegis-system-shipments',
                'view'       => $shipment->id,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'per_page'   => $per_page,
            ], admin_url('admin.php')));
            $export_url = esc_url(wp_nonce_url(add_query_arg([
                'page'             => 'aegis-system-shipments',
                'shipments_action' => 'export',
                'shipment_id'      => $shipment->id,
            ], admin_url('admin.php')), 'aegis_shipments_export_' . $shipment->id));

            echo '<tr>';
            echo '<td>' . esc_html($shipment->id) . '</td>';
            echo '<td>' . esc_html($shipment->shipment_no) . '</td>';
            echo '<td>' . esc_html($dealer_label) . '</td>';
            echo '<td>' . esc_html((int) $shipment->item_count) . '</td>';
            echo '<td>' . esc_html($user_label) . '</td>';
            echo '<td>' . esc_html($shipment->created_at) . '</td>';
            echo '<td><a class="button button-small" href="' . $view_url . '">查看</a> <a class="button button-small" href="' . $export_url . '">导出</a></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        $total_pages = $per_page > 0 ? ceil($total / $per_page) : 1;
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            if ($paged > 1) {
                $prev_url = esc_url(add_query_arg([
                    'page'       => 'aegis-system-shipments',
                    'paged'      => $paged - 1,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'per_page'   => $per_page,
                ], admin_url('admin.php')));
                echo '<a class="button" href="' . $prev_url . '">上一页</a> ';
            }
            echo '<span class="aegis-t-a6">第 ' . esc_html($paged) . ' / ' . esc_html($total_pages) . ' 页</span> ';
            if ($paged < $total_pages) {
                $next_url = esc_url(add_query_arg([
                    'page'       => 'aegis-system-shipments',
                    'paged'      => $paged + 1,
                    'start_date' => $start_date,
                    'end_date'   => $end_date,
                    'per_page'   => $per_page,
                ], admin_url('admin.php')));
                echo '<a class="button" href="' . $next_url . '">下一页</a>';
            }
            echo '</div></div>';
        }
    }

    /**
     * 渲染出库单详情。
     */
    protected static function render_shipment_detail($shipment_id) {
        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            echo '<div class="notice notice-error"><p class="aegis-t-a6">出库单不存在。</p></div>';
            return;
        }

        $items = self::get_items_by_shipment($shipment_id);
        $dealer = self::get_dealer($shipment->dealer_id);
        $dealer_label = $dealer ? $dealer->dealer_name : '-';

        echo '<h2 class="aegis-t-a3" style="margin-top:20px;">出库单详情 #' . esc_html($shipment->id) . '</h2>';
        echo '<p class="aegis-t-a6">出库单号：' . esc_html($shipment->shipment_no) . '，经销商：' . esc_html($dealer_label) . '，创建时间：' . esc_html($shipment->created_at) . '</p>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>Code</th><th>EAN</th><th>扫码时间</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($items)) {
            echo '<tr><td colspan="4">无记录</td></tr>';
        }
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item->id) . '</td>';
            echo '<td>' . esc_html($item->code_value) . '</td>';
            echo '<td>' . esc_html($item->ean) . '</td>';
            echo '<td>' . esc_html($item->scanned_at) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * 导出出库单。
     *
     * @param int $shipment_id
     * @return true|WP_Error
     */
    protected static function handle_export($shipment_id) {
        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return new WP_Error('forbidden', '权限不足。');
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aegis_shipments_export_' . $shipment_id)) {
            return new WP_Error('bad_nonce', '安全校验失败。');
        }

        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            return new WP_Error('not_found', '出库单不存在。');
        }

        $items = self::get_items_by_shipment($shipment_id);
        $dealer = self::get_dealer($shipment->dealer_id);
        $dealer_label = $dealer ? $dealer->dealer_name : '';

        $filename = 'shipment-' . $shipment->id . '.csv';
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Shipment ID', 'Shipment No', 'Dealer', 'Code', 'EAN', 'Scanned At']);
        foreach ($items as $item) {
            fputcsv($output, [$shipment->id, $shipment->shipment_no, $dealer_label, $item->code_value, $item->ean, $item->scanned_at]);
        }
        fclose($output);

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_EXPORT, 'SUCCESS', [
            'shipment_id' => $shipment->id,
            'count'       => count($items),
        ]);
        exit;
    }

    /**
     * 获取出库单列表。
     */
    protected static function query_shipments($start, $end, $per_page, $paged, &$total) {
        global $wpdb;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;

        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$shipment_table} WHERE created_at BETWEEN %s AND %s", $start, $end));
        $offset = ($paged - 1) * $per_page;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, COUNT(i.id) as item_count FROM {$shipment_table} s LEFT JOIN {$shipment_item_table} i ON s.id = i.shipment_id WHERE s.created_at BETWEEN %s AND %s GROUP BY s.id ORDER BY s.created_at DESC LIMIT %d OFFSET %d",
                $start,
                $end,
                $per_page,
                $offset
            )
        );
    }

    /**
     * 获取单个出库单。
     */
    protected static function get_shipment($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /**
     * 获取出库单的扫码明细。
     */
    protected static function get_items_by_shipment($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE shipment_id = %d ORDER BY scanned_at DESC", $id));
    }

    /**
     * 获取经销商。
     */
    protected static function get_dealer($id) {
        global $wpdb;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$dealer_table} WHERE id = %d", $id));
    }

    /**
     * 获取可用经销商列表。
     */
    protected static function get_active_dealers() {
        global $wpdb;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_results("SELECT id, dealer_name, auth_code, status FROM {$dealer_table} WHERE status = 'active' ORDER BY dealer_name ASC");
    }

    /**
     * 将文本转换为唯一的 code 集合。
     */
    protected static function parse_codes($raw) {
        $lines = preg_split('/\r?\n/', (string) $raw);
        $codes = [];
        foreach ($lines as $line) {
            $value = trim($line);
            if ($value !== '') {
                $codes[] = $value;
            }
        }
        return array_values(array_unique($codes));
    }

    /**
     * 归一化日期边界。
     */
    protected static function normalize_date_boundary($date, $type) {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            $timestamp = current_time('timestamp');
        }
        if ('end' === $type) {
            return gmdate('Y-m-d 23:59:59', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
        }
        return gmdate('Y-m-d 00:00:00', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }
}

class AEGIS_Public_Query {
    const CONTEXT_PUBLIC = 'public';
    const CONTEXT_INTERNAL = 'internal';

    /**
     * 后台内部查询页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('public_query')) {
            echo '<div class="wrap aegis-system-root"><h1 class="aegis-t-a3">防伪码查询</h1><div class="notice notice-warning"><p class="aegis-t-a6">请先启用公开查询模块。</p></div></div>';
            return;
        }

        $messages = [];
        $errors = [];
        $result = null;

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $whitelist = ['public_query_action', 'code_value', 'aegis_public_query_nonce', '_wp_http_referer'];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'   => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                    'nonce_field'  => 'aegis_public_query_nonce',
                    'nonce_action' => 'aegis_public_query_action',
                    'whitelist'    => $whitelist,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } else {
                $code_value = isset($_POST['code_value']) ? sanitize_text_field(wp_unslash($_POST['code_value'])) : '';
                $result = self::handle_query($code_value, self::CONTEXT_INTERNAL, false);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                    $result = null;
                }
            }
        }

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a3">防伪码查询</h1>';
        if (!empty($messages)) {
            foreach ($messages as $msg) {
                echo '<div class="updated"><p class="aegis-t-a6">' . esc_html($msg) . '</p></div>';
            }
        }
        if (!empty($errors)) {
            foreach ($errors as $err) {
                echo '<div class="error"><p class="aegis-t-a6">' . esc_html($err) . '</p></div>';
            }
        }

        echo '<form method="post" class="aegis-t-a6" style="margin-top:12px;">';
        wp_nonce_field('aegis_public_query_action', 'aegis_public_query_nonce');
        echo '<input type="hidden" name="public_query_action" value="query" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">防伪码</th><td><input type="text" name="code_value" value="" class="regular-text" required /></td></tr>';
        echo '</tbody></table>';
        submit_button('查询');
        echo '</form>';

        if ($result) {
            self::render_result($result, true);
        }

        echo '</div>';
    }

    /**
     * 前台短码渲染。
     */
    public static function render_shortcode($atts = []) {
        if (!AEGIS_System::is_module_enabled('public_query')) {
            return '';
        }

        wp_register_style('aegis-system-frontend-style', false, [], '0.1.0');
        wp_add_inline_style('aegis-system-frontend-style', AEGIS_Assets_Media::build_typography_css());
        wp_enqueue_style('aegis-system-frontend-style');

        $messages = [];
        $errors = [];
        $result = null;
        $context = self::CONTEXT_PUBLIC;
        $is_internal = is_user_logged_in() && AEGIS_System_Roles::user_can_manage_warehouse() && isset($_GET['internal']);
        if ($is_internal) {
            $context = self::CONTEXT_INTERNAL;
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $nonce = isset($_POST['aegis_public_query_nonce']) ? $_POST['aegis_public_query_nonce'] : '';
            if (!wp_verify_nonce($nonce, 'aegis_public_query_action')) {
                $errors[] = '安全校验失败，请重试。';
            } else {
                $code_value = isset($_POST['code_value']) ? sanitize_text_field(wp_unslash($_POST['code_value'])) : '';
                $result = self::handle_query($code_value, $context, self::CONTEXT_PUBLIC === $context);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                    $result = null;
                }
            }
        }

        ob_start();
        echo '<div class="aegis-system-root aegis-t-a6" style="padding:12px;">';
        echo '<h2 class="aegis-t-a4">防伪码查询</h2>';
        foreach ($messages as $msg) {
            echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $err) {
            echo '<div class="error"><p>' . esc_html($err) . '</p></div>';
        }
        echo '<form method="post" class="aegis-public-query-form" style="margin-top:8px;">';
        wp_nonce_field('aegis_public_query_action', 'aegis_public_query_nonce');
        echo '<label class="aegis-t-a6" for="aegis-code-input">请输入防伪码：</label><br />';
        echo '<input id="aegis-code-input" type="text" name="code_value" required style="min-width:260px;" /> ';
        echo '<button type="submit">查询</button>';
        echo '</form>';

        if ($result) {
            self::render_result($result, false);
        }
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * 展示查询结果。
     */
    protected static function render_result($result, $is_internal) {
        echo '<div class="aegis-public-query-result" style="margin-top:16px;">';
        echo '<h3 class="aegis-t-a4">查询结果</h3>';
        echo '<ul class="aegis-t-a6">';
        echo '<li><strong>防伪码：</strong>' . esc_html($result['code']) . '</li>';
        echo '<li><strong>产品：</strong>' . esc_html($result['product']) . '</li>';
        echo '<li><strong>状态：</strong>' . esc_html($result['status_label']) . '</li>';
        echo '<li><strong>经销商：</strong>' . esc_html($result['dealer_label']) . '</li>';
        if (!empty($result['shipment_no'])) {
            echo '<li><strong>出库单号：</strong>' . esc_html($result['shipment_no']) . '</li>';
            echo '<li><strong>出库时间：</strong>' . esc_html($result['shipment_time']) . '</li>';
        }
        echo '<li><strong>A 计数：</strong>' . esc_html($result['counts']['a']) . '</li>';
        echo '<li><strong>B 计数：</strong>' . esc_html($result['counts']['b']) . '</li>';
        if (!empty($result['last_query_at'])) {
            echo '<li><strong>最近查询：</strong>' . esc_html($result['last_query_at']) . '</li>';
        }
        if (!empty($result['certificate'])) {
            echo '<li><strong>证书：</strong><a href="' . esc_url($result['certificate']['url']) . '" target="_blank" rel="noopener">下载</a></li>';
        }
        if ($is_internal && !empty($result['raw_b'])) {
            echo '<li><strong>B 原始累积：</strong>' . esc_html($result['raw_b']) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    /**
     * 查询处理。
     */
    protected static function handle_query($code_value, $context, $enforce_rate_limit) {
        $code_value = trim((string) $code_value);
        if ('' === $code_value) {
            return new WP_Error('empty_code', '请输入防伪码。');
        }

        if ($enforce_rate_limit && !self::check_rate_limit()) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_PUBLIC_QUERY_RATE_LIMIT,
                'FAIL',
                ['ip' => self::get_client_ip()]
            );
            return new WP_Error('rate_limited', '查询过于频繁，请稍后再试。');
        }

        $record = self::get_code_record($code_value);
        if (!$record) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_PUBLIC_QUERY,
                'FAIL',
                ['code' => $code_value, 'reason' => 'not_found', 'context' => $context]
            );
            return new WP_Error('code_not_found', '未找到对应防伪码。');
        }

        $counts = self::increment_counters((int) $record->id, $record->code, $context);
        $shipment = self::get_latest_shipment((int) $record->id);
        $sku = self::get_sku($record->ean);
        $certificate = self::get_public_certificate($sku ? $sku->certificate_id : 0);

        $dealer_label = self::get_hq_label();
        $shipment_no = '';
        $shipment_time = '';
        $dealer_id = null;
        if ($shipment) {
            $dealer_label = $shipment->dealer_name ? $shipment->dealer_name : $dealer_label;
            $shipment_no = $shipment->shipment_no;
            $shipment_time = $shipment->scanned_at;
            $dealer_id = $shipment->dealer_id;
        }

        $result = [
            'code'          => $record->code,
            'ean'           => $record->ean,
            'product'       => $sku ? $sku->product_name : '未知产品',
            'status_label'  => 'used' === $record->status ? '已出库' : '未出库',
            'dealer_label'  => $dealer_label,
            'shipment_no'   => $shipment_no,
            'shipment_time' => $shipment_time,
            'counts'        => $counts,
            'raw_b'         => $counts['raw_b'],
            'last_query_at' => $counts['last_query_at'],
            'certificate'   => $certificate,
        ];

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_PUBLIC_QUERY,
            'SUCCESS',
            [
                'code_id'  => (int) $record->id,
                'context'  => $context,
                'ean'      => $record->ean,
                'dealer'   => $dealer_id,
                'counts'   => $counts,
            ]
        );

        return $result;
    }

    /**
     * 拉取码数据。
     */
    protected static function get_code_record($code_value) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s", $code_value));
    }

    /**
     * 获取 SKU 数据。
     */
    protected static function get_sku($ean) {
        if (!$ean) {
            return null;
        }
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE ean = %s", $ean));
    }

    /**
     * 取最新出库信息。
     */
    protected static function get_latest_shipment($code_id) {
        global $wpdb;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT si.scanned_at, si.code_id, si.ean, sh.shipment_no, sh.dealer_id, d.dealer_name FROM {$shipment_item_table} si LEFT JOIN {$shipment_table} sh ON si.shipment_id = sh.id LEFT JOIN {$dealer_table} d ON sh.dealer_id = d.id WHERE si.code_id = %d ORDER BY si.scanned_at DESC LIMIT 1",
                $code_id
            )
        );
    }

    /**
     * 仅返回公开证书。
     */
    protected static function get_public_certificate($media_id) {
        $media_id = (int) $media_id;
        if ($media_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND visibility = %s AND owner_type = %s AND deleted_at IS NULL",
                $media_id,
                AEGIS_Assets_Media::VISIBILITY_PUBLIC,
                'certificate'
            )
        );

        if (!$record) {
            return null;
        }

        return [
            'id'  => (int) $record->id,
            'url' => rest_url('aegis-system/v1/media/download/' . (int) $record->id),
        ];
    }

    /**
     * 计数累加与日志记录。
     */
    protected static function increment_counters($code_id, $code_value, $context) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $now = current_time('mysql');
        $is_public = self::CONTEXT_PUBLIC === $context;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET query_a_count = query_a_count + 1, query_b_count = query_b_count + %d, last_query_at = %s WHERE id = %d",
                $is_public ? 1 : 0,
                $now,
                $code_id
            )
        );

        self::insert_query_log($code_id, $code_value, 'A', $context);
        if ($is_public) {
            self::insert_query_log($code_id, $code_value, 'B', $context);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT query_a_count, query_b_count, query_b_offset, last_query_at FROM {$table} WHERE id = %d", $code_id));
        $b_effective = 0;
        $raw_b = 0;
        if ($row) {
            $raw_b = (int) $row->query_b_count;
            $b_effective = max(0, $raw_b - (int) $row->query_b_offset);
        }

        return [
            'a'             => $row ? (int) $row->query_a_count : 0,
            'b'             => $b_effective,
            'raw_b'         => $raw_b,
            'last_query_at' => $row ? $row->last_query_at : '',
        ];
    }

    /**
     * 写入查询日志。
     */
    protected static function insert_query_log($code_id, $code_value, $channel, $context) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::QUERY_LOG_TABLE;
        $wpdb->insert(
            $table,
            [
                'code_id'      => $code_id,
                'code_value'   => $code_value,
                'query_channel'=> $channel,
                'context'      => $context,
                'client_ip'    => self::get_client_ip(),
                'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? wp_strip_all_tags((string) wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * 简单频率限制。
     */
    protected static function check_rate_limit() {
        $ip = self::get_client_ip();
        if ('' === $ip) {
            return true;
        }
        $key = 'aegis_query_rate_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= 5) {
            return false;
        }
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * 获取总部显示名。
     */
    protected static function get_hq_label() {
        $label = get_option(AEGIS_System::HQ_DISPLAY_OPTION, '总部销售');
        if (!is_string($label) || '' === trim($label)) {
            $label = '总部销售';
        }
        return $label;
    }

    /**
     * 获取客户端 IP。
     */
    protected static function get_client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        return sanitize_text_field($ip);
    }
}

class AEGIS_Reset_B {
    const DEALER_META_KEY = 'aegis_dealer_id';

    /**
     * 渲染清零系统页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_reset_b()) {
            wp_die(__('您无权访问该页面。'));
        }

        $module_enabled = AEGIS_System::is_module_enabled('reset_b');
        $messages = [];
        $errors = [];
        $result = null;
        $dealer = self::get_current_dealer();
        $is_dealer_user = AEGIS_System_Roles::is_dealer_only();

        if (!$module_enabled) {
            $errors[] = '清零系统模块未启用。';
        }

        if ($is_dealer_user && !$dealer) {
            $errors[] = '未找到您的经销商档案，无法执行清零。';
        } elseif ($is_dealer_user && $dealer && $dealer->status !== 'active') {
            $errors[] = '当前经销商已停用，禁止清零操作。';
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'   => AEGIS_System::CAP_RESET_B,
                    'nonce_field'  => 'aegis_reset_b_nonce',
                    'nonce_action' => 'aegis_reset_b_action',
                    'whitelist'    => ['aegis_reset_b_nonce', 'reset_b_action', 'code_value', 'reason', 'confirm_reset', '_wp_http_referer', '_aegis_idempotency'],
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } elseif (!$module_enabled) {
                $errors[] = '清零系统模块未启用。';
            } elseif ($is_dealer_user && (!$dealer || $dealer->status !== 'active')) {
                $errors[] = '经销商状态异常，禁止清零。';
            } elseif (empty($_POST['confirm_reset'])) {
                $errors[] = '请勾选确认后再执行清零。';
            } else {
                $result = self::handle_reset($_POST, $dealer, $is_dealer_user);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                    $result = null;
                } else {
                    $messages[] = 'B 清零成功。';
                }
            }
        }

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a3">清零系统</h1>';

        if (!empty($messages)) {
            foreach ($messages as $msg) {
                echo '<div class="updated"><p class="aegis-t-a6">' . esc_html($msg) . '</p></div>';
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $err) {
                echo '<div class="error"><p class="aegis-t-a6">' . esc_html($err) . '</p></div>';
            }
        }

        if (!$module_enabled) {
            echo '</div>';
            return;
        }

        $idempotency_key = wp_generate_uuid4();
        echo '<form method="post" class="aegis-t-a5" style="max-width:720px;">';
        wp_nonce_field('aegis_reset_b_action', 'aegis_reset_b_nonce');
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';
        echo '<input type="hidden" name="reset_b_action" value="reset" />';

        if ($is_dealer_user && $dealer) {
            echo '<p class="aegis-t-a6"><strong>经销商：</strong>' . esc_html($dealer->dealer_name) . '（授权码 ' . esc_html($dealer->auth_code) . '）</p>';
        }

        echo '<p class="aegis-t-a6">请输入要清零 B 计数的防伪码，确认后将仅重置 B（内部计数），A 计数保持不变。</p>';
        echo '<table class="form-table">';
        echo '<tr><th><label class="aegis-t-a5" for="code_value">防伪码</label></th><td><input type="text" id="code_value" name="code_value" class="regular-text" required /></td></tr>';
        echo '<tr><th><label class="aegis-t-a5" for="reason">原因（可选）</label></th><td><input type="text" id="reason" name="reason" class="regular-text" /></td></tr>';
        echo '<tr><th></th><td><label class="aegis-t-a6"><input type="checkbox" name="confirm_reset" value="1" /> 我确认仅清零 B 计数，操作将被审计。</label></td></tr>';
        echo '</table>';
        submit_button('执行清零');
        echo '</form>';

        if ($result) {
            echo '<h2 class="aegis-t-a4">清零结果</h2>';
            echo '<p class="aegis-t-a6">防伪码：' . esc_html($result['code_value']) . '</p>';
            echo '<p class="aegis-t-a6">清零前 B：' . esc_html($result['before_b']) . '，清零后 B：' . esc_html($result['after_b']) . '</p>';
            if (!empty($result['dealer_name'])) {
                echo '<p class="aegis-t-a6">归属经销商：' . esc_html($result['dealer_name']) . '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * 执行清零逻辑。
     */
    protected static function handle_reset($post, $dealer, $is_dealer_user) {
        global $wpdb;
        $code_value = isset($post['code_value']) ? sanitize_text_field(wp_unslash($post['code_value'])) : '';
        $reason = isset($post['reason']) ? sanitize_text_field(wp_unslash($post['reason'])) : '';

        if ('' === $code_value) {
            return new WP_Error('empty_code', '请输入防伪码。');
        }

        $record = self::get_code_record($code_value);
        if (!$record) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code' => $code_value, 'reason' => 'not_found']);
            return new WP_Error('code_not_found', '未找到对应防伪码。');
        }

        $shipment = self::get_latest_shipment($record->id);

        if ($is_dealer_user) {
            if (!$dealer) {
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code_id' => (int) $record->id, 'reason' => 'dealer_missing']);
                return new WP_Error('dealer_missing', '未配置经销商资料，无法清零。');
            }
            if ($dealer->status !== 'active') {
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code_id' => (int) $record->id, 'reason' => 'dealer_inactive', 'dealer_id' => (int) $dealer->id]);
                return new WP_Error('dealer_inactive', '经销商已停用，禁止清零。');
            }
            if (!$shipment || (int) $shipment->dealer_id !== (int) $dealer->id) {
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code_id' => (int) $record->id, 'reason' => 'not_owned', 'dealer_id' => (int) $dealer->id]);
                return new WP_Error('not_owned', '该防伪码未出库至您或尚未出库，禁止清零。');
            }
        }

        $table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $raw_b = (int) $record->query_b_count;
        $offset = (int) $record->query_b_offset;
        $before_b = max(0, $raw_b - $offset);
        $update = $wpdb->update($table, ['query_b_offset' => $raw_b], ['id' => $record->id], ['%d'], ['%d']);

        if (false === $update) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code_id' => (int) $record->id, 'reason' => 'db_error', 'db_error' => $wpdb->last_error]);
            return new WP_Error('db_error', '清零失败：数据库错误。');
        }

        $audit_payload = [
            'code_id'        => (int) $record->id,
            'code'           => $code_value,
            'before_b'       => $before_b,
            'after_b'        => 0,
            'raw_b'          => $raw_b,
            'prior_offset'   => $offset,
            'reason'         => $reason,
            'shipment_id'    => $shipment ? (int) $shipment->shipment_id : null,
            'dealer_id'      => $shipment ? (int) $shipment->dealer_id : null,
            'dealer_name'    => $shipment && isset($shipment->dealer_name) ? $shipment->dealer_name : null,
            'actor_dealer'   => $dealer ? (int) $dealer->id : null,
        ];

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'SUCCESS', $audit_payload);

        return [
            'code_value' => $code_value,
            'before_b'   => $before_b,
            'after_b'    => 0,
            'dealer_name'=> $shipment && isset($shipment->dealer_name) ? $shipment->dealer_name : '',
        ];
    }

    /**
     * 获取当前用户绑定的经销商。
     */
    protected static function get_current_dealer() {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return null;
        }
        $dealer_id = get_user_meta($user->ID, self::DEALER_META_KEY, true);
        $dealer_id = (int) $dealer_id;
        if ($dealer_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $dealer_id));
    }

    /**
     * 获取码记录。
     */
    protected static function get_code_record($code_value) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s", $code_value));
    }

    /**
     * 获取最近一次出库归属。
     */
    protected static function get_latest_shipment($code_id) {
        global $wpdb;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT si.shipment_id, si.scanned_at, si.code_id, si.ean, sh.dealer_id, d.dealer_name FROM {$shipment_item_table} si LEFT JOIN {$shipment_table} sh ON si.shipment_id = sh.id LEFT JOIN {$dealer_table} d ON sh.dealer_id = d.id WHERE si.code_id = %d ORDER BY si.scanned_at DESC LIMIT 1",
                $code_id
            )
        );
    }
}

new AEGIS_System();
