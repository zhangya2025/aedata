<?php
if (!defined('ABSPATH')) {
    exit;
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
     * 获取静态资源版本号（基于文件修改时间）。
     *
     * @param string $relative_path
     * @return int|string
     */
    public static function get_asset_version($relative_path) {
        $file = trailingslashit(AEGIS_SYSTEM_PATH) . ltrim($relative_path, '/');

        if (file_exists($file)) {
            $mtime = filemtime($file);
            if ($mtime) {
                return $mtime;
            }
        }

        return AEGIS_SYSTEM_VERSION;
    }

    /**
     * 注册并输出排版样式表与变量。
     *
     * @param string $handle
     */
    public static function enqueue_typography_style($handle = 'aegis-system-typography') {
        wp_register_style(
            $handle,
            AEGIS_SYSTEM_URL . 'assets/css/typography.css',
            [],
            self::get_asset_version('assets/css/typography.css')
        );
        wp_add_inline_style($handle, self::build_typography_css());
        wp_enqueue_style($handle);
    }

    /**
     * 构造排版变量 CSS。
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
        $css .= '}';

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
            self::enqueue_typography_style('aegis-system-frontend-style');
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

        self::enqueue_typography_style('aegis-system-frontend-style');

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

        $sensitive_types = ['business_license', 'dealer_license', 'payment_receipt', 'payment_voucher'];
        if (in_array($owner_type, $sensitive_types, true)) {
            $visibility = self::VISIBILITY_SENSITIVE;
        }

        if (!isset($_FILES['file'])) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_UPLOAD, 'FAIL', ['reason' => 'missing_file']);
            return new WP_REST_Response(['message' => '未找到上传文件'], 400);
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
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

        $sensitive_types = ['business_license', 'dealer_license', 'payment_receipt', 'payment_voucher'];
        if (in_array($owner_type, $sensitive_types, true)) {
            $visibility = self::VISIBILITY_SENSITIVE;
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
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

