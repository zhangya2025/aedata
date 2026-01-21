<?php
if (!defined('ABSPATH')) {
    exit;
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
            $status_changes = [];

            if ('trash' === $page->post_status) {
                wp_untrash_post($page->ID);
                $page->post_status = 'draft';
                $status_changes[] = 'untrashed';
            }

            if ('publish' !== $page->post_status) {
                wp_update_post([
                    'ID'          => $page->ID,
                    'post_status' => 'publish',
                ]);
                $status_changes[] = 'published';
            }

            $content = (string) $page->post_content;
            if (strpos($content, '[' . self::PORTAL_SHORTCODE) === false) {
                wp_update_post([
                    'ID'           => $page->ID,
                    'post_content' => '[' . self::PORTAL_SHORTCODE . ']',
                ]);
                $status_changes[] = 'content_reset';
            }

            if ($force && !empty($status_changes)) {
                AEGIS_Access_Audit::record_event(
                    AEGIS_System::ACTION_SCHEMA_UPGRADE,
                    'SUCCESS',
                    [
                        'action'  => 'ensure_portal_page',
                        'page_id' => $page->ID,
                        'changes' => $status_changes,
                    ]
                );
            }

            return;
        }

        $page_id = wp_insert_post([
            'post_title'   => 'AEGIS-SYSTEM',
            'post_name'    => self::PORTAL_SLUG,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[' . self::PORTAL_SHORTCODE . ']',
        ]);

        if ($force && !is_wp_error($page_id)) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_SCHEMA_UPGRADE,
                'SUCCESS',
                [
                    'action'  => 'ensure_portal_page',
                    'page_id' => $page_id,
                ]
            );
        }
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
     * 登录页提示。
     */
    public static function render_login_notice($message) {
        if (!isset($_GET['aegis_notice'])) {
            return $message;
        }

        $notice = sanitize_key(wp_unslash($_GET['aegis_notice']));
        $map = [
            'dealer_inactive' => '账号已停用，请联系管理员。',
            'dealer_expired'  => '授权已到期，请联系管理员续期后再登录。',
            'dealer_missing'  => '未找到经销商授权信息，请联系管理员。',
        ];

        if (!isset($map[$notice])) {
            return $message;
        }

        $text = $map[$notice];
        $html = '<div class="notice notice-error"><p>' . esc_html($text) . '</p></div>';

        return $message . $html;
    }

    /**
     * 业务角色登出后强制返回登录入口。
     */
    public static function filter_logout_redirect($redirect_to, $requested, $user) {
        if ($user instanceof WP_User && AEGIS_System_Roles::is_business_user($user)) {
            return self::get_login_url();
        }

        return $redirect_to;
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

        $user = wp_get_current_user();

        if (!AEGIS_System_Roles::is_business_user($user)) {
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

        if (in_array('aegis_dealer', (array) $user->roles, true)) {
            $dealer_state = AEGIS_Dealer::evaluate_dealer_access($user);
            if (empty($dealer_state['allowed'])) {
                AEGIS_Access_Audit::record_event(
                    AEGIS_System::ACTION_PORTAL_BLOCKED,
                    'FAIL',
                    [
                        'reason' => $dealer_state['reason'],
                    ]
                );

                wp_logout();
                $redirect = add_query_arg('aegis_notice', $dealer_state['reason'], self::get_login_url());
                wp_safe_redirect($redirect);
                exit;
            }
        }

        self::render_portal_shell();
        exit;
    }

    /**
     * 在 Portal 页输出独立壳，绕过主题模板。
     */
    protected static function render_portal_shell() {
        status_header(200);

        self::enqueue_portal_assets();
        $content = self::render_portal_shortcode();

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>AEGIS SYSTEM</title>
    <?php
        wp_head();
    ?>
</head>
<body class="aegis-portal-shell">
<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php wp_footer(); ?>
</body>
</html>
<?php
    }

    /**
     * 避免业务角色进入 WooCommerce My Account。
     */
    public static function maybe_redirect_my_account() {
        if (is_admin() || !is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if (!AEGIS_System_Roles::is_business_user($user)) {
            return;
        }

        $is_account = false;
        if (function_exists('is_account_page') && is_account_page()) {
            $is_account = true;
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
            if (is_string($path) && preg_match('#/my-account/?$#', $path)) {
                $is_account = true;
            }
        }

        if (!$is_account) {
            return;
        }

        $portal_url = self::get_portal_url();
        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_PORTAL_BLOCKED,
            'SUCCESS',
            [
                'reason' => 'business_account_redirect',
                'target' => $portal_url,
            ]
        );
        wp_safe_redirect($portal_url);
        exit;
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

        self::enqueue_portal_assets();

        $portal_url = self::get_portal_url();
        $module_states = self::get_portal_module_states();
        $visible = self::get_visible_modules_for_user($user, $module_states);
        $modules = self::build_portal_modules($user, $visible, $module_states, $portal_url);
        $allowed_modules = self::get_allowed_modules($user, $visible, $module_states);

        $dealer_notice = null;
        if (in_array('aegis_dealer', (array) $user->roles, true)) {
            $dealer_state = AEGIS_Dealer::evaluate_dealer_access($user);
            if (!empty($dealer_state['allowed']) && !empty($dealer_state['dealer'])) {
                $warning = '';
                if (null !== $dealer_state['remaining_days'] && $dealer_state['remaining_days'] < 30) {
                    $warning = '授权即将到期，请联系管理员续期。';
                }

                $dealer_notice = [
                    'range'   => AEGIS_Dealer::format_auth_range($dealer_state['dealer']),
                    'warning' => $warning,
                ];
            }
        }

        $requested = isset($_GET['m']) ? sanitize_key(wp_unslash($_GET['m'])) : '';
        if ('core_manager' === $requested) {
            $requested = 'system_settings';
        }

        $unavailable_panel = '';
        if ('payments' === $requested) {
            $modules['payments'] = [
                'label'   => '支付',
                'enabled' => false,
                'href'    => add_query_arg('m', 'payments', $portal_url),
            ];
            $unavailable_panel = '<div class="aegis-t-a5">模块不存在或已并入订单流程。</div>';
        }

        $default_panel = self::get_default_panel_for_user($user);
        $requested = self::resolve_requested_panel($requested, $allowed_modules, $user, $default_panel);

        $registered = AEGIS_System::get_registered_modules();
        $is_virtual = in_array($requested, ['dashboard', 'workbench'], true);
        $current_slug = $requested;
        $current_panel = '';

        if (!$is_virtual && !isset($registered[$requested])) {
            $current_slug = $default_panel;
            $current_panel = self::render_module_panel($current_slug, $modules[$current_slug] ?? []);
        } else {
            if (!isset($modules[$requested]) && !$is_virtual) {
                $state = $module_states[$requested] ?? ['label' => $requested, 'enabled' => true];
                $modules[$requested] = [
                    'label'   => $state['label'] ?? $requested,
                    'enabled' => true,
                    'href'    => add_query_arg('m', $requested, $portal_url),
                ];
            }
            $current_panel = $unavailable_panel ?: self::render_module_panel($requested, $modules[$requested] ?? []);
        }

        $context = [
            'user'          => $user,
            'portal_url'    => $portal_url,
            'modules'       => $modules,
            'current'       => $current_slug,
            'logout_url'    => wp_logout_url(self::get_login_url()),
            'current_panel' => $current_panel,
            'role_labels'   => implode(' / ', self::get_role_labels_for_user($user)),
            'dealer_notice' => $dealer_notice,
            'is_warehouse_mode' => AEGIS_System_Roles::is_warehouse_user($user),
        ];

        return self::render_template('shell', $context);
    }

    /**
     * 供模块渲染 Portal 模板的公共入口。
     *
     * @param string $template
     * @param array  $context
     * @return string
     */
    public static function render_portal_template($template, $context = []) {
        return self::render_template($template, $context);
    }

    /**
     * Portal 样式按需加载。
     */
    protected static function enqueue_portal_assets() {
        AEGIS_Assets_Media::enqueue_typography_style('aegis-system-portal-typography');

        $css_path = AEGIS_SYSTEM_PATH . 'assets/css/portal.css';
        wp_enqueue_style(
            'aegis-system-portal-style',
            AEGIS_SYSTEM_URL . 'assets/css/portal.css',
            ['aegis-system-portal-typography'],
            file_exists($css_path) ? filemtime($css_path) : AEGIS_Assets_Media::get_asset_version('assets/css/portal.css')
        );

        $js_path = AEGIS_SYSTEM_PATH . 'assets/js/portal.js';
        wp_enqueue_script(
            'aegis-system-portal',
            AEGIS_SYSTEM_URL . 'assets/js/portal.js',
            [],
            file_exists($js_path) ? filemtime($js_path) : AEGIS_Assets_Media::get_asset_version('assets/js/portal.js'),
            true
        );

        $mobile_js_path = AEGIS_SYSTEM_PATH . 'assets/js/portal-mobile.js';
        wp_enqueue_script(
            'aegis-system-portal-mobile',
            AEGIS_SYSTEM_URL . 'assets/js/portal-mobile.js',
            ['aegis-system-portal'],
            file_exists($mobile_js_path) ? filemtime($mobile_js_path) : AEGIS_Assets_Media::get_asset_version('assets/js/portal-mobile.js'),
            false
        );
    }

    /**
     * 渲染 Portal 模板。
     *
     * @param string $template
     * @param array  $context
     * @return string
     */
    protected static function render_template($template, $context = []) {
        $path = trailingslashit(AEGIS_SYSTEM_PATH) . 'templates/portal/' . $template . '.php';
        if (!file_exists($path)) {
            return '';
        }

        ob_start();
        $context_data = $context;
        include $path;
        return ob_get_clean();
    }

    /**
     * Portal URL。
     *
     * @return string
     */
    protected static function get_portal_url() {
        $fallback = home_url('/index.php/' . self::PORTAL_SLUG . '/');
        $structure = get_option('permalink_structure');

        if (!empty($structure)) {
            $pretty = home_url('/' . self::PORTAL_SLUG . '/');
            $use_pretty = apply_filters('aegis_portal_use_pretty_links', false, $pretty, $fallback);

            if ($use_pretty) {
                return $pretty;
            }
        }

        return $fallback;
    }

    /**
     * 登录入口 URL。
     *
     * @return string
     */
    protected static function get_login_url() {
        return home_url('/aegislogin.php');
    }

    /**
     * 获取模块状态（基于存储）。
     *
     * @return array
     */
    protected static function get_portal_module_states() {
        $stored = get_option(AEGIS_System::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $modules = AEGIS_System::get_registered_modules();
        $states = [];
        foreach ($modules as $slug => $module) {
            $states[$slug] = [
                'label'   => $module['label'] ?? $slug,
                'enabled' => ($slug === 'core_manager') ? true : !empty($stored[$slug]),
            ];
        }

        return $states;
    }

    /**
     * 构建 Portal 导航模块。
     *
     * @param WP_User $user
     * @param array   $visible
     * @param array   $module_states
     * @param string  $portal_url
     * @return array
     */
    protected static function build_portal_modules($user, $visible, $module_states, $portal_url) {
        $modules = [];

        if (AEGIS_System_Roles::is_warehouse_user($user) || in_array('aegis_dealer', (array) $user->roles, true)) {
            $modules['workbench'] = [
                'label'   => '工作台',
                'enabled' => true,
                'href'    => add_query_arg('m', 'workbench', $portal_url),
            ];
        } else {
            $modules['dashboard'] = [
                'label'   => '控制台',
                'enabled' => true,
                'href'    => $portal_url,
            ];
        }

        if (AEGIS_System_Roles::user_can_manage_system($user)) {
            $modules['system_settings'] = [
                'label'   => '系统设置',
                'enabled' => true,
                'href'    => add_query_arg('m', 'system_settings', $portal_url),
            ];
            $modules['aegis_typography'] = [
                'label'   => '排版设置',
                'enabled' => true,
                'href'    => add_query_arg('m', 'aegis_typography', $portal_url),
            ];
        }

        $label_map = [
            'core_manager'     => '系统设置',
            'aegis_typography' => '排版设置',
            'workbench'        => '工作台',
            'sku'              => 'SKU 管理',
            'dealer_master'    => '经销商管理',
            'codes'            => '防伪码生成',
            'inbound'          => '扫码入库',
            'shipments'        => '扫码出库',
            'public_query'     => '公共查询',
            'reset_b'          => '清零B',
            'orders'           => '订单',
            'reports'          => '报表',
            'monitoring'       => '监控',
            'access_audit'     => '访问审计',
            'assets_media'     => '资产与媒体',
        ];

        foreach ($visible as $slug => $info) {
            if (in_array($slug, ['core_manager', 'aegis_typography'], true)) {
                continue;
            }

            if (empty($info['enabled'])) {
                continue;
            }

            $info['label'] = $label_map[$slug] ?? ($info['label'] ?? $slug);
            $info['href'] = add_query_arg('m', $slug, $portal_url);
            $modules[$slug] = $info;
        }

        return $modules;
    }

    /**
     * 按角色过滤可见模块。
     *
     * @param WP_User $user
     * @param array   $states
     * @return array
     */
    protected static function get_visible_modules_for_user($user, $states) {
        $roles = (array) $user->roles;
        $all_modules = array_keys(AEGIS_System::get_registered_modules());

        if (AEGIS_System_Roles::is_hq_admin($user)) {
            $allowed = $all_modules;
        } elseif (in_array('aegis_sales', $roles, true) || in_array('aegis_finance', $roles, true)) {
            $allowed = ['orders', 'access_audit'];
        } elseif (AEGIS_System_Roles::is_warehouse_user($user)) {
            $allowed = ['inbound', 'shipments'];
        } elseif (in_array('aegis_dealer', $roles, true)) {
            $allowed = ['reset_b'];
            if (!empty($states['orders']['enabled'])) {
                $allowed[] = 'orders';
            }
        } else {
            $allowed = [];
        }

        $visible = [];
        foreach ($allowed as $slug) {
            if (isset($states[$slug])) {
                $visible[$slug] = $states[$slug];
            }
        }

        return $visible;
    }

    /**
     * 渲染模块内容占位。
     *
     * @param string $slug
     * @param array  $info
     * @return string
     */
    protected static function render_module_panel($slug, $info) {
        switch ($slug) {
            case 'workbench':
                return self::render_workbench_panel();
            case 'dashboard':
                return self::render_dashboard_panel();
            case 'system_settings':
                return self::render_system_settings_panel();
            case 'aegis_typography':
                return self::render_typography_panel();
            case 'sku':
                return AEGIS_SKU::render_portal_panel(self::get_portal_url());
            case 'dealer_master':
                return AEGIS_Dealer::render_portal_panel(self::get_portal_url());
            case 'codes':
                return AEGIS_Codes::render_portal_panel(self::get_portal_url());
            case 'inbound':
                return AEGIS_Inbound::render_portal_panel(self::get_portal_url());
            case 'shipments':
                return AEGIS_Shipments::render_portal_panel(self::get_portal_url());
            case 'access_audit':
                return AEGIS_Access_Audit_Module::render_portal_panel(self::get_portal_url());
            case 'assets_media':
                return AEGIS_Assets_Media::render_portal_panel(self::get_portal_url());
            case 'public_query':
                $public_url = AEGIS_Public_Query::get_public_page_url();
                if ($public_url) {
                    return '<div class="aegis-t-a5">公共查询入口：<a class="aegis-t-a6" href="' . esc_url($public_url) . '" target="_blank" rel="noopener">访问防伪码公共查询</a></div>';
                }
                return '<div class="aegis-t-a5">公共查询页面未就绪。</div>';
            case 'reset_b':
                return AEGIS_Reset_B::render_portal_panel(self::get_portal_url());
            case 'orders':
                return AEGIS_Orders::render_portal_panel(self::get_portal_url());
            case 'reports':
                return AEGIS_Reports::render_portal_panel(self::get_portal_url());
            case 'monitoring':
                return AEGIS_Monitoring::render_portal_panel(self::get_portal_url());
            default:
                return '<div class="aegis-t-a5">该模块前台界面尚未实现（占位）。</div>';
        }
    }

    /**
     * 工作台页面。
     *
     * @return string
     */
    protected static function render_workbench_panel() {
        $portal_url = self::get_portal_url();
        $user = wp_get_current_user();
        $entries = [];

        if (AEGIS_System_Roles::is_warehouse_user($user)) {
            if (AEGIS_System::is_module_enabled('inbound')) {
                $entries[] = [
                    'title' => '扫码入库',
                    'desc'  => '扫码模式',
                    'icon'  => '📥',
                    'href'  => add_query_arg('m', 'inbound', $portal_url),
                ];
            }
            if (AEGIS_System::is_module_enabled('shipments')) {
                $entries[] = [
                    'title' => '扫码出库',
                    'desc'  => '扫码模式',
                    'icon'  => '📤',
                    'href'  => add_query_arg('m', 'shipments', $portal_url),
                ];
            }
        } elseif (in_array('aegis_dealer', (array) $user->roles, true)) {
            if (AEGIS_System::is_module_enabled('orders')) {
                $entries[] = [
                    'title' => '订单',
                    'desc'  => '订单管理',
                    'icon'  => '🧾',
                    'href'  => add_query_arg('m', 'orders', $portal_url),
                ];
            }
            if (AEGIS_System::is_module_enabled('reset_b')) {
                $entries[] = [
                    'title' => '清零B',
                    'desc'  => '清零申请',
                    'icon'  => '♻️',
                    'href'  => add_query_arg('m', 'reset_b', $portal_url),
                ];
            }
        } else {
            return self::render_dashboard_panel();
        }

        $context = [
            'portal_url' => $portal_url,
            'entries'    => $entries,
        ];

        return self::render_template('workbench', $context);
    }

    /**
     * 模块未启用提示。
     *
     * @param string $portal_url
     * @param string $default_panel
     * @param string $label
     * @return string
     */
    protected static function render_module_disabled_panel($portal_url, $default_panel, $label) {
        $back_url = self::get_panel_back_url($portal_url, $default_panel);
        return sprintf(
            '<div class="aegis-t-a5"><p>模块“%s”未启用，请联系管理员。</p><a class="aegis-portal-button is-primary" href="%s">返回工作台</a></div>',
            esc_html($label),
            esc_url($back_url)
        );
    }

    /**
     * 无权限提示。
     *
     * @param string $portal_url
     * @param string $default_panel
     * @return string
     */
    protected static function render_access_denied_panel($portal_url, $default_panel) {
        $back_url = self::get_panel_back_url($portal_url, $default_panel);
        return sprintf(
            '<div class="aegis-t-a5"><p>当前账号无权访问该模块。</p><a class="aegis-portal-button is-primary" href="%s">返回工作台</a></div>',
            esc_url($back_url)
        );
    }

    /**
     * 获取返回工作台/控制台的 URL。
     *
     * @param string $portal_url
     * @param string $panel
     * @return string
     */
    protected static function get_panel_back_url($portal_url, $panel) {
        if (in_array($panel, ['dashboard', 'workbench'], true)) {
            return $portal_url;
        }

        return add_query_arg('m', $panel, $portal_url);
    }

    /**
     * 欢迎页控制台。
     */
    protected static function render_dashboard_panel() {
        $user = wp_get_current_user();
        $role_labels = self::get_role_labels_for_user($user);

        ob_start();
        ?>
        <div class="aegis-t-a3" style="margin-bottom:12px;">欢迎来到 AEGIS Portal</div>
        <div class="aegis-t-a5" style="margin-bottom:12px;">当前用户：<?php echo esc_html($user ? $user->user_login : ''); ?></div>
        <?php if (!empty($role_labels)) : ?>
            <div class="aegis-t-a6" style="margin-bottom:16px; color:#555;">角色：<?php echo esc_html(implode(' / ', $role_labels)); ?></div>
        <?php endif; ?>
        <div class="aegis-t-a5" style="padding:12px 16px; border:1px dashed #d9dce3; border-radius:8px; background:#f8f9fb;">
            请选择左侧功能模块开始操作。
        </div>
        <?php if (AEGIS_System::is_module_enabled('public_query') && AEGIS_System_Roles::user_can_manage_warehouse()) : ?>
            <?php $public_url = AEGIS_Public_Query::get_public_page_url(); ?>
            <?php if (!empty($public_url)) : ?>
                <div class="aegis-t-a6" style="margin-top:12px;">
                    <a class="aegis-t-a6" href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener">防伪码公共查询（入口）</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * 获取默认落地模块。
     *
     * @param WP_User|null $user
     * @return string
     */
    protected static function get_default_panel_for_user($user = null) {
        if (AEGIS_System_Roles::is_warehouse_user($user)) {
            return 'workbench';
        }

        if ($user && in_array('aegis_dealer', (array) $user->roles, true)) {
            return 'workbench';
        }

        return 'dashboard';
    }

    /**
     * 获取当前用户可见且已启用的模块集合。
     *
     * @param WP_User $user
     * @param array   $visible
     * @param array   $module_states
     * @return array
     */
    protected static function get_allowed_modules($user, $visible, $module_states) {
        $allowed = [
            'dashboard' => true,
            'workbench' => true,
        ];

        foreach ($visible as $slug => $info) {
            if (empty($module_states[$slug]['enabled'])) {
                continue;
            }
            $allowed[$slug] = true;
        }

        if (AEGIS_System_Roles::user_can_manage_system($user)) {
            $allowed['system_settings'] = true;
            $allowed['aegis_typography'] = true;
        }

        if (!empty($module_states['public_query']['enabled'])) {
            $allowed['public_query'] = true;
        }

        return $allowed;
    }

    /**
     * 解析请求模块，确保落地到可访问页面。
     *
     * @param string $requested
     * @param array  $allowed_modules
     * @param WP_User $user
     * @param string $default_panel
     * @return string
     */
    protected static function resolve_requested_panel($requested, $allowed_modules, $user, $default_panel) {
        if (!empty($requested) && isset($allowed_modules[$requested])) {
            return $requested;
        }

        if (wp_is_mobile() && AEGIS_System_Roles::is_warehouse_user($user)) {
            $default_panel = 'workbench';
        }

        if (isset($allowed_modules[$default_panel])) {
            return $default_panel;
        }

        foreach ($allowed_modules as $slug => $enabled) {
            if ($enabled) {
                return $slug;
            }
        }

        return $default_panel;
    }

    /**
     * Portal 中的系统设置（模块管理）。
     */
    protected static function render_system_settings_panel() {
        if (!AEGIS_System_Roles::user_can_manage_system()) {
            return '<div class="aegis-t-a5">当前账号无权访问系统设置。</div>';
        }

        $modules = AEGIS_System::get_registered_modules();
        $states = self::get_portal_module_states();
        $validation = ['success' => true, 'message' => ''];
        $message = '';

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_SYSTEM,
                    'nonce_field'     => 'aegis_system_portal_nonce',
                    'nonce_action'    => 'aegis_system_portal_save_modules',
                    'whitelist'       => ['aegis_system_portal_nonce', '_wp_http_referer', '_aegis_idempotency', 'modules', 'submit'],
                    'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                ]
            );

            if ($validation['success']) {
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

                self::save_module_states($new_states, $states);
                $states = self::get_portal_module_states();
                $message = '模块配置已保存。';
            }
        }

        ob_start();
        $portal_url = self::get_portal_url();
        $action_url = add_query_arg('m', 'system_settings', $portal_url);
        ?>
        <div class="aegis-t-a3" style="margin-bottom:12px;">系统设置</div>
        <div class="aegis-t-a6" style="margin-bottom:12px; color:#555;">管理可用模块并控制其启用状态。</div>
        <?php if (!$validation['success'] && !empty($validation['message'])) : ?>
            <div class="aegis-t-a6" style="padding:10px 12px; border:1px solid #d14343; background:#fff5f5; border-radius:6px; margin-bottom:12px;">
                <?php echo esc_html($validation['message']); ?>
            </div>
        <?php elseif ($message) : ?>
            <div class="aegis-t-a6" style="padding:10px 12px; border:1px solid #46b450; background:#f1fff0; border-radius:6px; margin-bottom:12px;">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url($action_url); ?>" class="aegis-t-a6">
            <?php wp_nonce_field('aegis_system_portal_save_modules', 'aegis_system_portal_nonce'); ?>
            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
            <div style="border:1px solid #e5e5e5; border-radius:8px; overflow:hidden;">
                <div style="display:grid; grid-template-columns:1.2fr 0.6fr 1fr; gap:0; background:#f8f9fb; border-bottom:1px solid #e5e5e5;" class="aegis-t-a6">
                    <div style="padding:10px 12px; font-weight:600;">模块</div>
                    <div style="padding:10px 12px; font-weight:600;">启用</div>
                    <div style="padding:10px 12px; font-weight:600;">说明</div>
                </div>
                <?php foreach ($modules as $slug => $module) :
                    $enabled = !empty($states[$slug]['enabled']);
                    $label = $module['label'] ?? $slug;
                    $disabled_attr = $slug === 'core_manager' ? 'disabled="disabled"' : '';
                    $checked_attr = $enabled ? 'checked="checked"' : '';
                ?>
                    <div style="display:grid; grid-template-columns:1.2fr 0.6fr 1fr; gap:0; border-top:1px solid #e5e5e5; align-items:center;">
                        <div style="padding:10px 12px;">
                            <div class="aegis-t-a5" style="font-weight:600; margin-bottom:4px;"><?php echo esc_html($label); ?></div>
                            <div class="aegis-t-a6" style="color:#666;"><code><?php echo esc_html($slug); ?></code></div>
                        </div>
                        <div style="padding:10px 12px;">
                            <label style="display:inline-flex; align-items:center; gap:8px;">
                                <input type="checkbox" name="modules[<?php echo esc_attr($slug); ?>]" <?php echo $checked_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
                                <span><?php echo $enabled ? '已启用' : '未启用'; ?></span>
                            </label>
                        </div>
                        <div style="padding:10px 12px; color:#666;">
                            <?php echo ('core_manager' === $slug) ? '核心模块始终启用' : '—'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:12px;">
                <button type="submit" class="button button-primary">保存配置</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Portal 排版设置。
     *
     * @return string
     */
    protected static function render_typography_panel() {
        if (!AEGIS_System_Roles::user_can_manage_system()) {
            return '<div class="aegis-t-a5">当前账号无权访问排版设置。</div>';
        }

        $settings = AEGIS_Assets_Media::get_typography_settings();
        $message = '';
        $error = '';

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'   => AEGIS_System::CAP_MANAGE_SYSTEM,
                    'nonce_field'  => 'aegis_typography_nonce',
                    'nonce_action' => 'aegis_typography_save',
                    'whitelist'    => array_merge(['aegis_typography_nonce', '_wp_http_referer'], AEGIS_Assets_Media::allowed_typography_keys()),
                ]
            );

            if ($validation['success']) {
                $settings = AEGIS_Assets_Media::parse_typography_post($_POST);
                update_option(AEGIS_System::TYPOGRAPHY_OPTION, $settings);
                AEGIS_Access_Audit::log(
                    AEGIS_System::ACTION_SETTINGS_UPDATE,
                    [
                        'result'      => 'SUCCESS',
                        'entity_type' => 'typography',
                        'message'     => 'Typography updated via portal',
                        'meta'        => ['keys' => array_keys($settings)],
                    ]
                );
                $message = '排版配置已保存。';
            } else {
                $error = $validation['message'];
            }
        }

        $portal_url = self::get_portal_url();
        $action_url = add_query_arg('m', 'aegis_typography', $portal_url);

        ob_start();
        echo '<div class="aegis-t-a4" style="margin-bottom:12px;">排版设置（Typography）</div>';
        if ($message) {
            echo '<div class="aegis-t-a6" style="padding:8px 12px;border:1px solid #46b450;background:#f1fff0;margin-bottom:12px;">' . esc_html($message) . '</div>';
        }
        if ($error) {
            echo '<div class="aegis-t-a6" style="padding:8px 12px;border:1px solid #dc3232;background:#fff5f5;margin-bottom:12px;">' . esc_html($error) . '</div>';
        }

        echo '<form method="post" class="aegis-t-a6" action="' . esc_url($action_url) . '" style="display:block;">';
        wp_nonce_field('aegis_typography_save', 'aegis_typography_nonce');

        echo '<div class="aegis-t-a6" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">';
        foreach (AEGIS_Assets_Media::get_typography_defaults() as $key => $defaults) {
            $size = isset($settings[$key]['size']) ? $settings[$key]['size'] : $defaults['size'];
            $line = isset($settings[$key]['line']) ? $settings[$key]['line'] : $defaults['line'];
            echo '<div style="border:1px solid #e5e5e5;padding:12px;border-radius:4px;background:#fafafa;">';
            echo '<div class="aegis-t-a5" style="margin-bottom:8px;">' . esc_html(strtoupper($key)) . '</div>';
            echo '<label class="aegis-t-a6" style="display:block;margin-bottom:6px;">字号 (rem)<input type="number" step="0.1" min="0.5" name="' . esc_attr($key) . '_size" value="' . esc_attr($size) . '" style="width:100%;margin-top:4px;" /></label>';
            echo '<label class="aegis-t-a6" style="display:block;">行高 (rem)<input type="number" step="0.1" min="0.5" name="' . esc_attr($key) . '_line" value="' . esc_attr($line) . '" style="width:100%;margin-top:4px;" /></label>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div style="margin-top:12px;">';
        echo '<button type="submit" class="aegis-t-a6" style="padding:8px 16px;background:#2271b1;border:1px solid #1c5a8e;color:#fff;border-radius:4px;cursor:pointer;">保存配置</button>';
        echo '</div>';
        echo '</form>';

        return ob_get_clean();
    }

    /**
     * 保存模块状态（与后台模块管理保持一致）。
     *
     * @param array $states
     * @param array $previous_states
     */
    protected static function save_module_states($states, $previous_states = []) {
        $modules = AEGIS_System::get_registered_modules();
        $clean = [];
        foreach ($modules as $slug => $module) {
            if ($slug === 'core_manager') {
                $clean[$slug] = true;
                continue;
            }
            $clean[$slug] = !empty($states[$slug]);
        }

        update_option(AEGIS_System::OPTION_KEY, $clean);

        foreach ($clean as $slug => $enabled) {
            $previous = !empty($previous_states[$slug]['enabled']);
            if ($enabled === $previous) {
                continue;
            }
            $action = $enabled ? AEGIS_System::ACTION_MODULE_ENABLE : AEGIS_System::ACTION_MODULE_DISABLE;
            AEGIS_Access_Audit::log(
                $action,
                [
                    'result'      => 'SUCCESS',
                    'entity_type' => 'module',
                    'entity_id'   => $slug,
                    'meta'        => ['module' => $slug],
                ]
            );
        }

        AEGIS_Access_Audit::log(
            AEGIS_System::ACTION_SETTINGS_UPDATE,
            [
                'result'      => 'SUCCESS',
                'entity_type' => 'module_states',
                'message'     => 'Portal module toggles saved',
                'meta'        => ['states' => $clean],
            ]
        );
    }

    /**
     * 将角色 key 转换为可读标签。
     *
     * @param WP_User $user
     * @return array
     */
    protected static function get_role_labels_for_user($user) {
        $map = [
            'aegis_hq_admin'          => '总部管理员',
            'aegis_sales'             => '销售人员',
            'aegis_finance'           => '财务人员',
            'aegis_warehouse_manager' => '仓库管理员',
            'aegis_warehouse_staff'   => '仓库员工',
            'aegis_dealer'            => '经销商',
        ];

        $labels = [];
        $roles = $user ? (array) $user->roles : [];
        foreach ($roles as $role) {
            if (isset($map[$role])) {
                $labels[] = $map[$role];
            }
        }

        if (empty($labels)) {
            return $roles;
        }

        return $labels;
    }
}
