<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_System {
    const OPTION_KEY = 'aegis_system_modules';
    const TYPOGRAPHY_OPTION = 'aegis_system_typography';
    const HQ_DISPLAY_OPTION = 'aegis_public_query_hq_label';
    const ORDER_SHIPMENT_LINK_OPTION = 'aegis_order_shipment_link';
    const SCHEMA_VERSION = '2.6.4';
    const AUDIT_TABLE = 'aegis_audit_events';
    const MEDIA_TABLE = 'aegis_media_files';
    const SKU_TABLE = 'aegis_skus';
    const DEALER_TABLE = 'aegis_dealers';
    const DEALER_PRICE_TABLE = 'aegis_dealer_prices';
    const CODE_BATCH_TABLE = 'aegis_code_batches';
    const CODE_TABLE = 'aegis_codes';
    const SHIPMENT_TABLE = 'aegis_shipments';
    const SHIPMENT_ITEM_TABLE = 'aegis_shipment_items';
    const RECEIPT_TABLE = 'aegis_receipts';
    const RECEIPT_ITEM_TABLE = 'aegis_receipt_items';
    const QUERY_LOG_TABLE = 'aegis_query_logs';
    const ORDER_TABLE = 'aegis_orders';
    const ORDER_ITEM_TABLE = 'aegis_order_items';
    const ORDER_STATUS_LOG_TABLE = 'aegis_order_status_logs';
    const PAYMENT_TABLE = 'aegis_payment_proofs';
    const RESET_LOG_TABLE = 'aegis_reset_logs';
    const RETURN_REQUEST_TABLE = 'aegis_return_requests';
    const RETURN_REQUEST_ITEM_TABLE = 'aegis_return_request_items';
    const RETURN_REQUEST_VERSION_TABLE = 'aegis_return_request_versions';
    const RETURN_OVERRIDE_CODE_TABLE = 'aegis_return_override_codes';
    const RETURN_CODE_LOCK_TABLE = 'aegis_return_code_locks';
    const RETURN_WAREHOUSE_CHECK_TABLE = 'aegis_return_warehouse_checks';
    const RETURN_WAREHOUSE_SCAN_TABLE = 'aegis_return_warehouse_scans';

    const CAP_ACCESS_ROOT = 'aegis_access_root';
    const CAP_ACCESS_AUDIT_VIEW = 'aegis_access_audit_view';
    const CAP_MANAGE_SYSTEM = 'aegis_manage_system';
    const CAP_MANAGE_WAREHOUSE = 'aegis_manage_warehouse';
    const CAP_USE_WAREHOUSE = 'aegis_use_warehouse';
    const CAP_RESET_B = 'aegis_reset_b';
    const CAP_ORDERS = 'aegis_orders';
    const CAP_ORDERS_VIEW_ALL = 'aegis_orders_view_all';
    const CAP_ORDERS_INITIAL_REVIEW = 'aegis_orders_initial_review';
    const CAP_ORDERS_PAYMENT_REVIEW = 'aegis_orders_payment_review';
    const CAP_ORDERS_CREATE = 'aegis_orders_create';
    const CAP_ORDERS_MANAGE_ALL = self::CAP_ORDERS;
    const CAP_RETURNS_DEALER_APPLY = 'aegis_returns_dealer_apply';
    const CAP_RETURNS_DEALER_SUBMIT = 'aegis_returns_dealer_submit';
    const CAP_RETURNS_SALES_REVIEW = 'aegis_returns_sales_review';
    const CAP_RETURNS_OVERRIDE_ISSUE = 'aegis_returns_override_issue';
    const CAP_RETURNS_OVERRIDE_REVOKE = 'aegis_returns_override_revoke';
    const CAP_RETURNS_WAREHOUSE_CHECK = 'aegis_returns_warehouse_check';
    const CAP_RETURNS_FINANCE_REVIEW = 'aegis_returns_finance_review';

    const RETURNS_AFTER_SALES_DAYS_OPTION = 'aegis_returns_after_sales_days';

    const ACTION_MODULE_ENABLE = 'MODULE_ENABLE';
    const ACTION_MODULE_DISABLE = 'MODULE_DISABLE';
    const ACTION_MODULE_UNINSTALL = 'MODULE_UNINSTALL';
    const ACTION_SETTINGS_UPDATE = 'SETTINGS_UPDATE';
    const ACTION_SCHEMA_UPGRADE = 'SCHEMA_UPGRADE';
    const ACTION_MEDIA_UPLOAD = 'MEDIA_UPLOAD';
    const ACTION_MEDIA_ACCESS = 'MEDIA_ACCESS';
    const ACTION_MEDIA_DOWNLOAD = self::ACTION_MEDIA_ACCESS;
    const ACTION_MEDIA_ACCESS_DENY = 'MEDIA_ACCESS_DENY';
    const ACTION_MEDIA_DOWNLOAD_DENY = self::ACTION_MEDIA_ACCESS_DENY;
    const ACTION_MEDIA_EXPORT = 'MEDIA_EXPORT';
    const ACTION_MEDIA_CLEANUP = 'MEDIA_CLEANUP';
    const ACTION_SKU_CREATE = 'SKU_CREATE';
    const ACTION_SKU_UPDATE = 'SKU_UPDATE';
    const ACTION_SKU_PRICE_UPDATE = 'SKU_PRICE_UPDATE';
    const ACTION_SKU_ENABLE = 'SKU_ENABLE';
    const ACTION_SKU_DISABLE = 'SKU_DISABLE';
    const ACTION_SKU_EAN_CORRECT = 'SKU_EAN_CORRECT';
    const ACTION_DEALER_CREATE = 'DEALER_CREATE';
    const ACTION_DEALER_UPDATE = 'DEALER_UPDATE';
    const ACTION_DEALER_TRADE_ATTR_UPDATE = 'DEALER_TRADE_ATTR_UPDATE';
    const ACTION_DEALER_ENABLE = 'DEALER_ENABLE';
    const ACTION_DEALER_DISABLE = 'DEALER_DISABLE';
    const ACTION_DEALER_CODE_CORRECT = 'DEALER_CODE_CORRECT';
    const ACTION_DEALER_PRICE_OVERRIDE_CREATE = 'DEALER_PRICE_OVERRIDE_CREATE';
    const ACTION_DEALER_PRICE_OVERRIDE_UPDATE = 'DEALER_PRICE_OVERRIDE_UPDATE';
    const ACTION_DEALER_PRICE_OVERRIDE_DELETE = 'DEALER_PRICE_OVERRIDE_DELETE';
    const ACTION_DEALER_USER_CREATE_BIND = 'DEALER_USER_CREATE_BIND';
    const ACTION_CODE_BATCH_CREATE = 'CODE_BATCH_CREATE';
    const ACTION_CODE_EXPORT = 'CODE_EXPORT';
    const ACTION_CODE_PRINT = 'CODE_PRINT';
    const ACTION_RECEIPT_CREATE = 'RECEIPT_CREATE';
    const ACTION_RECEIPT_ITEM_ADD = 'RECEIPT_ITEM_ADD';
    const ACTION_RECEIPT_COMPLETE = 'RECEIPT_COMPLETE';
    const ACTION_RECEIPT_EXPORT = 'RECEIPT_EXPORT_DETAIL';
    const ACTION_RECEIPT_PRINT = 'RECEIPT_PRINT_SUMMARY';
    const ACTION_RECEIPT_DELETE = 'RECEIPT_DELETE';
    const ACTION_SHIPMENT_CREATE = 'SHIPMENT_CREATE';
    const ACTION_SHIPMENT_ITEM_ADD = 'SHIPMENT_ITEM_ADD';
    const ACTION_SHIPMENT_COMPLETE = 'SHIPMENT_COMPLETE';
    const ACTION_SHIPMENT_DELETE = 'SHIPMENT_DELETE';
    const ACTION_SHIPMENT_EXPORT_SUMMARY = 'SHIPMENT_EXPORT_SUMMARY';
    const ACTION_SHIPMENT_EXPORT_DETAIL = 'SHIPMENT_EXPORT_DETAIL';
    const ACTION_SHIPMENT_PRINT_SUMMARY = 'SHIPMENT_PRINT_SUMMARY';
    const ACTION_PUBLIC_QUERY = 'PUBLIC_QUERY';
    const ACTION_PUBLIC_QUERY_RATE_LIMIT = 'PUBLIC_QUERY_RATE_LIMIT';
    const ACTION_RESET_B = 'RESET_B_EXECUTED';
    const ACTION_ORDER_CREATE = 'ORDER_CREATE';
    const ACTION_ORDER_UPDATE = 'ORDER_UPDATE';
    const ACTION_ORDER_UPDATE_BY_DEALER = 'ORDER_UPDATE_BY_DEALER';
    const ACTION_ORDER_STATUS_CHANGE = 'ORDER_STATUS_CHANGE';
    const ACTION_ORDER_CANCEL_BY_DEALER = 'ORDER_CANCEL_BY_DEALER';
    const ACTION_ORDER_INITIAL_REVIEW = 'ORDER_INITIAL_REVIEW';
    const ACTION_ORDER_INITIAL_REVIEW_PASS = 'ORDER_INITIAL_REVIEW_PASS';
    const ACTION_ORDER_INITIAL_REVIEW_EDIT = 'ORDER_INITIAL_REVIEW_EDIT';
    const ACTION_ORDER_VOID_BY_HQ = 'ORDER_VOID_BY_HQ';
    const ACTION_PAYMENT_UPLOAD = 'PAYMENT_UPLOAD';
    const ACTION_PAYMENT_PROOF_UPLOAD = 'PAYMENT_PROOF_UPLOAD';
    const ACTION_PAYMENT_CONFIRM_SUBMIT = 'PAYMENT_CONFIRM_SUBMIT';
    const ACTION_PAYMENT_REVIEW_APPROVE = 'PAYMENT_REVIEW_APPROVE';
    const ACTION_PAYMENT_REVIEW_REJECT = 'PAYMENT_REVIEW_REJECT';
    const ACTION_LOGIN_REDIRECT = 'LOGIN_REDIRECT';
    const ACTION_ADMIN_BLOCKED = 'ADMIN_BLOCKED';
    const ACTION_PORTAL_BLOCKED = 'PORTAL_BLOCKED';
    const ACTION_REPORT_EXPORT = 'REPORT_EXPORT';
    const ACTION_MONITOR_EXPORT = 'MONITOR_EXPORT';

    /**
     * 预置模块注册表。
     *
     * @return array
     */
    public static function get_registered_modules() {
        return [
            'core_manager'   => ['label' => '核心管理', 'default' => true],
            'workbench'      => ['label' => '工作台', 'default' => true],
            'access_audit'   => ['label' => '访问审计', 'default' => true],
            'system_settings' => ['label' => '系统设置', 'default' => true],
            'aegis_typography' => ['label' => '排版设置', 'default' => true],
            'assets_media'   => ['label' => '资产与媒体', 'default' => false],
            'sku'            => ['label' => 'SKU', 'default' => false],
            'dealer_master'  => ['label' => '经销商主数据', 'default' => false],
            'sales_master'   => ['label' => '销售管理', 'default' => true],
            'warehouse_master' => ['label' => '仓库人员管理', 'default' => true],
            'my_dealers'     => ['label' => '我的经销商', 'default' => true],
            'codes'          => ['label' => '编码管理', 'default' => false],
            'inbound'        => ['label' => '扫码入库', 'default' => false],
            'shipments'      => ['label' => '出货管理', 'default' => false],
            'public_query'   => ['label' => '公开查询', 'default' => false],
            'reset_b'        => ['label' => '重置 B', 'default' => false],
            'orders'         => ['label' => '订单', 'default' => false],
            'returns'        => ['label' => '退货申请', 'default' => false],
            'reports'        => ['label' => '报表', 'default' => false],
            'monitoring'     => ['label' => '监控', 'default' => false],
        ];
    }

    /**
     * 规范化防伪码：去除非字母数字字符并转大写。
     *
     * @param mixed $raw
     * @return string
     */
    public static function normalize_code_value($raw): string {
        $raw = strtoupper((string) $raw);
        return preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';
    }

    /**
     * 格式化展示用防伪码：20 位按每 4 位插入短横线。
     *
     * @param mixed $code
     * @return string
     */
    public static function format_code_display($code): string {
        $normalized = self::normalize_code_value($code);
        if (20 !== strlen($normalized)) {
            return $normalized;
        }
        return implode('-', str_split($normalized, 4));
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
        add_action('init', ['AEGIS_Public_Query', 'ensure_public_page']);
        add_filter('login_redirect', ['AEGIS_Portal', 'filter_login_redirect'], 9999, 3);
        add_filter('login_url', ['AEGIS_Portal', 'filter_login_url'], 10, 3);
        add_filter('login_message', ['AEGIS_Portal', 'render_login_notice']);
        add_filter('logout_redirect', ['AEGIS_Portal', 'filter_logout_redirect'], 9999, 3);
        add_action('template_redirect', ['AEGIS_Portal', 'handle_portal_access']);
        add_action('template_redirect', ['AEGIS_Portal', 'maybe_redirect_my_account']);
        add_action('admin_init', ['AEGIS_Portal', 'block_business_admin_access']);
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
        AEGIS_Public_Query::ensure_public_page(true);
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
        $can_view_orders = AEGIS_System_Roles::user_can_manage_warehouse()
            || current_user_can(AEGIS_System::CAP_ORDERS_VIEW_ALL)
            || current_user_can(AEGIS_System::CAP_ORDERS_INITIAL_REVIEW)
            || current_user_can(AEGIS_System::CAP_ORDERS_PAYMENT_REVIEW)
            || current_user_can(AEGIS_System::CAP_ORDERS_MANAGE_ALL);

        $has_visible = false;
        if (AEGIS_System_Roles::user_can_manage_system()) {
            $has_visible = true;
        }
        if (AEGIS_System_Roles::user_can_manage_warehouse() && (!empty($states['sku']) || !empty($states['dealer_master']) || !empty($states['codes']) || !empty($states['shipments']) || !empty($states['public_query']) || !empty($states['orders']))
        ) {
            $has_visible = true;
        }
        if (AEGIS_System_Roles::user_can_use_warehouse() && !AEGIS_System_Roles::user_can_manage_warehouse() && (!empty($states['shipments']) || !empty($states['orders']))) {
            $has_visible = true;
        }
        if ($can_view_orders && !AEGIS_System_Roles::user_can_manage_warehouse() && !AEGIS_System_Roles::user_can_use_warehouse()) {
            $has_visible = true;
        }
        if (AEGIS_System_Roles::user_can_reset_b() && !empty($states['reset_b'])) {
            $has_visible = true;
        }
        if (AEGIS_System_Roles::is_dealer_only() && (!empty($states['orders']) || !empty($states['reset_b']))) {
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

        if (!empty($states['orders']) && ($can_view_orders || AEGIS_System_Roles::is_dealer_only())) {
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

        $style_handle = 'aegis-system-admin-style';
        wp_register_style(
            $style_handle,
            AEGIS_SYSTEM_URL . 'assets/css/typography.css',
            [],
            AEGIS_SYSTEM_VERSION
        );
        wp_register_script('aegis-system-admin-js', false, [], AEGIS_SYSTEM_VERSION, true);
        wp_add_inline_style($style_handle, AEGIS_Assets_Media::build_typography_css());
        wp_add_inline_script('aegis-system-admin-js', 'window["aegis-system"] = window["aegis-system"] || { pages: {} };');

        wp_enqueue_style($style_handle);
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
