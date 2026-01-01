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
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>AEGIS SYSTEM</title>
    <?php
        wp_print_styles();
        wp_print_scripts();
    ?>
</head>
<body class="aegis-portal-shell">
<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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

        if (!isset($modules[$requested])) {
            $requested = 'dashboard';
        }

        $context = [
            'user'          => $user,
            'portal_url'    => $portal_url,
            'modules'       => $modules,
            'current'       => $requested,
            'logout_url'    => wp_logout_url(self::get_login_url()),
            'current_panel' => self::render_module_panel($requested, $modules[$requested]),
            'role_labels'   => implode(' / ', self::get_role_labels_for_user($user)),
            'dealer_notice' => $dealer_notice,
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

        wp_enqueue_style(
            'aegis-system-portal-style',
            AEGIS_SYSTEM_URL . 'assets/css/portal.css',
            ['aegis-system-portal-typography'],
            AEGIS_Assets_Media::get_asset_version('assets/css/portal.css')
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
        $modules = [
            'dashboard' => [
                'label'   => '控制台',
                'enabled' => true,
                'href'    => $portal_url,
            ],
        ];

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
            'core_manager'  => '系统设置',
            'aegis_typography' => '排版设置',
            'sku'           => 'SKU 管理',
            'dealer_master' => '经销商管理',
            'codes'         => '防伪码生成',
            'inbound'       => '扫码入库',
            'shipments'     => '出库管理',
            'public_query'  => '公共查询',
            'reset_b'       => '清零B',
            'orders'        => '订单',
            'payments'      => '支付',
            'reports'       => '报表',
            'monitoring'    => '监控',
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

        if (in_array('aegis_hq_admin', $roles, true)) {
            $allowed = $all_modules;
        } elseif (in_array('aegis_warehouse_manager', $roles, true)) {
            $allowed = ['sku', 'dealer_master', 'codes', 'inbound', 'shipments', 'public_query', 'reset_b'];
        } elseif (in_array('aegis_warehouse_staff', $roles, true)) {
            $allowed = ['sku', 'dealer_master', 'codes', 'inbound', 'shipments', 'public_query'];
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
        if (empty($info['enabled'])) {
            return '<div class="aegis-t-a5">模块未启用。</div>';
        }

        switch ($slug) {
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
            case 'public_query':
                $public_url = AEGIS_Public_Query::get_public_page_url();
                if ($public_url) {
                    return '<div class="aegis-t-a5">公共查询入口：<a class="aegis-t-a6" href="' . esc_url($public_url) . '" target="_blank" rel="noopener">访问防伪码公共查询</a></div>';
                }
                return '<div class="aegis-t-a5">公共查询页面未就绪。</div>';
            case 'reset_b':
                return '<div class="aegis-t-a5">清零系统占位，请根据权限在此执行 B 清零操作。</div>';
            default:
                return '<div class="aegis-t-a5">该模块前台界面尚未实现（占位）。</div>';
        }
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

        if (!empty($clean['payments']) && empty($clean['orders'])) {
            $clean['payments'] = false;
        }

        update_option(AEGIS_System::OPTION_KEY, $clean);

        foreach ($clean as $slug => $enabled) {
            $previous = !empty($previous_states[$slug]['enabled']);
            if ($enabled === $previous) {
                continue;
            }
            $action = $enabled ? AEGIS_System::ACTION_MODULE_ENABLE : AEGIS_System::ACTION_MODULE_DISABLE;
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
     * 将角色 key 转换为可读标签。
     *
     * @param WP_User $user
     * @return array
     */
    protected static function get_role_labels_for_user($user) {
        $map = [
            'aegis_hq_admin'         => '总部管理员',
            'aegis_warehouse_manager'=> '仓库管理员',
            'aegis_warehouse_staff'  => '仓库员工',
            'aegis_dealer'           => '经销商',
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

