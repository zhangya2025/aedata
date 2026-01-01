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
        if (empty($visible)) {
            return '<div class="aegis-system-root aegis-t-a5">未找到可访问的模块。</div>';
        }

        if (AEGIS_System_Roles::user_can_manage_system($user)) {
            $visible = array_merge([
                'aegis_typography' => [
                    'label'   => '系统设置/排版设置',
                    'enabled' => true,
                    'type'    => 'settings',
                ],
            ], $visible);
        }

        $requested = isset($_GET['m']) ? sanitize_key(wp_unslash($_GET['m'])) : '';
        if (!isset($visible[$requested])) {
            $keys = array_keys($visible);
            $requested = $keys[0];
        }

        $context = [
            'user'          => $user,
            'portal_url'    => $portal_url,
            'modules'       => $visible,
            'current'       => $requested,
            'logout_url'    => wp_logout_url(self::get_login_url()),
            'current_panel' => self::render_module_panel($requested, $visible[$requested]),
            'role_labels'   => implode(', ', array_intersect((array) $user->roles, AEGIS_System_Roles::get_business_roles())),
        ];

        return self::render_template('shell', $context);
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
            $allowed = ['sku', 'dealer_master', 'codes', 'shipments', 'public_query', 'reset_b'];
        } elseif (in_array('aegis_warehouse_staff', $roles, true)) {
            $allowed = ['shipments', 'public_query'];
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
            case 'aegis_typography':
                return self::render_typography_panel();
            case 'shipments':
                return '<div class="aegis-t-a5">出货管理前台界面尚未实现（占位）。</div>';
            case 'public_query':
                return '<div class="aegis-t-a5">防伪码查询内部入口占位，具体前台页面将在模块中实现。</div>';
            case 'reset_b':
                return '<div class="aegis-t-a5">清零系统占位，请根据权限在此执行 B 清零操作。</div>';
            default:
                return '<div class="aegis-t-a5">该模块前台界面尚未实现（占位）。</div>';
        }
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
}

