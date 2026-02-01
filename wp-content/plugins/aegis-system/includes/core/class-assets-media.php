<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Assets_Media {
    const UPLOAD_ROOT = 'aegis-system';
    const FRONT_SHORTCODE = 'aegis_system_page';
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_INTERNAL = 'internal';
    const VISIBILITY_PRIVATE = 'private'; // legacy alias
    const VISIBILITY_SENSITIVE = 'sensitive';
    const DEFAULT_MAX_SIZE_MB = 10;
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /**
     * 确保上传目录与防直链文件存在。
     */
    public static function ensure_upload_structure() {
        $upload_dir = wp_upload_dir();
        $base = trailingslashit($upload_dir['basedir']) . self::UPLOAD_ROOT;
        $buckets = ['certificates', 'sku-images', 'licenses', 'payments', 'exports', 'temp', 'internal'];

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
     * 允许的扩展名与 MIME 类型映射。
     *
     * @return array
     */
    public static function get_allowed_mime_types() {
        $types = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
        ];

        return apply_filters('aegis_media_allowed_types', $types);
    }

    /**
     * 最大上传尺寸（字节）。
     *
     * @return int
     */
    public static function get_max_upload_size() {
        $limit = (int) apply_filters('aegis_media_max_size_mb', self::DEFAULT_MAX_SIZE_MB);
        if ($limit <= 0) {
            $limit = self::DEFAULT_MAX_SIZE_MB;
        }

        return $limit * MB_IN_BYTES;
    }

    /**
     * 生成安全的唯一文件名。
     *
     * @param string $dir
     * @param string $name
     * @param string $ext
     * @return string
     */
    protected static function generate_unique_filename($dir, $name, $ext) {
        $ext = $ext ? '.' . ltrim($ext, '.') : '';
        $hash = wp_generate_password(16, false, false);
        $base = 'aegis-' . $hash;
        $filename = $base . $ext;

        return wp_unique_filename($dir, $filename);
    }

    /**
     * 上传目录过滤器（owner_type 分桶 + 年月）。
     *
     * @param string $owner_type
     * @return callable
     */
    protected static function build_upload_dir_filter($owner_type) {
        $year = gmdate('Y');
        $month = gmdate('m');
        $safe_owner = $owner_type ? sanitize_key($owner_type) : 'internal';

        return function ($uploads) use ($safe_owner, $year, $month) {
            $uploads['subdir'] = '/' . self::UPLOAD_ROOT . '/' . $safe_owner . '/' . $year . '/' . $month;
            $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
            $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];
            return $uploads;
        };
    }

    /**
     * 规范化可见性。
     *
     * @param string $visibility
     * @param string $owner_type
     * @return string
     */
    protected static function normalize_visibility($visibility, $owner_type = '') {
        $value = $visibility ?: self::VISIBILITY_INTERNAL;
        if (self::VISIBILITY_PRIVATE === $value) {
            $value = self::VISIBILITY_INTERNAL;
        }

        $sensitive_types = ['business_license', 'dealer_license', 'payment_receipt', 'payment_voucher', 'payment_proof', 'order_payment_proof'];
        if (in_array($owner_type, $sensitive_types, true)) {
            return self::VISIBILITY_SENSITIVE;
        }

        $allowed = [self::VISIBILITY_PUBLIC, self::VISIBILITY_INTERNAL, self::VISIBILITY_SENSITIVE];
        if (!in_array($value, $allowed, true)) {
            return self::VISIBILITY_INTERNAL;
        }

        return $value;
    }

    /**
     * 上传依赖加载。
     */
    protected static function ensure_upload_dependencies() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    /**
     * 校验文件类型与大小。
     *
     * @param array $file
     * @return array|WP_Error
     */
    protected static function validate_file($file) {
        if (!is_array($file) || empty($file['name'])) {
            return new WP_Error('missing_file', '未选择文件');
        }

        $allowed = self::get_allowed_mime_types();
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed);
        if (empty($check['ext']) || empty($check['type']) || !isset($allowed[strtolower($check['ext'])])) {
            return new WP_Error('invalid_type', '文件类型不被允许，仅支持 jpg/png/webp/pdf');
        }

        if ((int) $file['size'] > self::get_max_upload_size()) {
            return new WP_Error('file_too_large', '文件超出大小限制');
        }

        $sanitized_name = sanitize_file_name($file['name']);
        if ('' === $sanitized_name) {
            $sanitized_name = 'upload.' . $check['ext'];
        }

        return [
            'ext'      => strtolower($check['ext']),
            'type'     => $check['type'],
            'filename' => $sanitized_name,
        ];
    }

    /**
     * 统一网关 URL 生成。
     *
     * @param int $media_id
     * @return string
     */
    public static function get_media_gateway_url($media_id, $args = []) {
        if (!$media_id) {
            return '';
        }

        $url = add_query_arg('aegis_media', (int) $media_id, home_url('/'));
        if (!empty($args) && is_array($args)) {
            $url = add_query_arg($args, $url);
        }

        return $url;
    }

    /**
     * 软删除同 owner/kind 的旧记录。
     *
     * @param string $owner_type
     * @param string $owner_id
     * @param string $kind
     * @param string $timestamp
     */
    protected static function soft_delete_previous($owner_type, $owner_id, $kind, $timestamp) {
        if (!$owner_type || '' === $owner_id) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $where = ['owner_type = %s', 'owner_id = %s', 'deleted_at IS NULL'];
        $params = [$owner_type, $owner_id];

        if ($kind) {
            $where[] = 'kind = %s';
            $params[] = $kind;
        }

        $sql = "UPDATE {$table} SET deleted_at = %s WHERE " . implode(' AND ', $where);
        array_unshift($params, $timestamp);
        $wpdb->query($wpdb->prepare($sql, $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
            wp_enqueue_style(
                'aegis-public-query',
                AEGIS_SYSTEM_URL . 'assets/css/public-query.css',
                ['aegis-system-frontend-style'],
                self::get_asset_version('assets/css/public-query.css')
            );
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
     * Portal 面板。
     *
     * @param string $portal_url
     * @return string
     */
    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('assets_media')) {
            return '<div class=\"aegis-t-a5\">模块未启用。</div>';
        }

        $user = wp_get_current_user();
        if (!$user || !AEGIS_System_Roles::user_can_manage_system($user)) {
            return '<div class=\"aegis-t-a5\">当前账号无权访问资产与媒体面板。</div>';
        }

        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']) . self::UPLOAD_ROOT;
        $allowed_types = array_keys(self::get_allowed_mime_types());
        $max_size_mb = round(self::get_max_upload_size() / MB_IN_BYTES, 2);

        $context = [
            'portal_url'  => $portal_url,
            'base_dir'    => $base_dir,
            'allowed'     => $allowed_types,
            'max_size_mb' => $max_size_mb,
            'checks'      => [
                'path_exists'   => file_exists($base_dir),
                'path_writable' => is_dir($base_dir) && wp_is_writable($base_dir),
                'table_exists'  => self::media_table_exists(),
            ],
        ];

        return AEGIS_Portal::render_portal_template('assets-media', $context);
    }

    /**
     * 判断媒体表是否存在。
     *
     * @return bool
     */
    protected static function media_table_exists() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table);

        return (string) $wpdb->get_var($query) === $table;
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

        register_rest_route(
            'aegis-system/v1',
            '/media/cleanup',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'handle_cleanup'],
                'permission_callback' => function () {
                    return AEGIS_System::is_module_enabled('assets_media') && AEGIS_System_Roles::user_can_manage_system();
                },
            ]
        );
    }

    /**
     * 允许自定义查询变量用于下载网关。
     */
    public static function register_query_vars($vars) {
        $vars[] = 'aegis_media';
        $vars[] = 'aegis_media_disposition';
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
        self::ensure_upload_dependencies();

        $params = $request instanceof WP_REST_Request ? $request->get_params() : [];
        $validation = AEGIS_Access_Audit::validate_write_request(
            $params,
            [
                'capability'   => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                'nonce_field'  => '_wpnonce',
                'nonce_action' => 'wp_rest',
                'whitelist'    => ['_wpnonce', 'bucket', 'owner_type', 'owner_id', 'kind', 'visibility', 'meta'],
            ]
        );

        if (!$validation['success']) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_UPLOAD, 'FAIL', ['reason' => $validation['message']]);
            return new WP_REST_Response(['message' => $validation['message']], 400);
        }

        if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
            return new WP_REST_Response(['message' => '未选择文件'], 400);
        }

        $file_validation = self::validate_file($_FILES['file']);
        if (is_wp_error($file_validation)) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_MEDIA_UPLOAD,
                'FAIL',
                ['reason' => $file_validation->get_error_message()]
            );
            return new WP_REST_Response(['message' => $file_validation->get_error_message()], 400);
        }

        $owner_type = isset($params['owner_type']) ? sanitize_key($params['owner_type']) : '';
        $owner_id = isset($params['owner_id']) ? sanitize_text_field($params['owner_id']) : '';
        $kind = isset($params['kind']) ? sanitize_key($params['kind']) : '';
        $visibility = self::normalize_visibility(isset($params['visibility']) ? sanitize_key($params['visibility']) : '', $owner_type);

        $upload_override = [
            'test_form'                => false,
            'mimes'                    => self::get_allowed_mime_types(),
            'unique_filename_callback' => function ($dir, $name, $ext) {
                return AEGIS_Assets_Media::generate_unique_filename($dir, $name, $ext);
            },
        ];

        $dir_filter = self::build_upload_dir_filter($owner_type ?: 'internal');
        add_filter('upload_dir', $dir_filter);

        $uploads = wp_upload_dir();
        if (!empty($uploads['path']) && !file_exists($uploads['path'])) {
            wp_mkdir_p($uploads['path']);
        }

        $file = $_FILES['file'];
        $file['name'] = $file_validation['filename'];
        $result = wp_handle_upload($file, $upload_override);
        remove_filter('upload_dir', $dir_filter);

        if (isset($result['error'])) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_UPLOAD, 'FAIL', ['reason' => $result['error']]);
            return new WP_REST_Response(['message' => $result['error']], 400);
        }

        $file_path = str_replace(trailingslashit($uploads['basedir']), '', $result['file']);
        $hash = hash_file('sha256', $result['file']);
        $size = (int) filesize($result['file']);
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $now = current_time('mysql');

        self::soft_delete_previous($owner_type, $owner_id, $kind, $now);

        $wpdb->insert(
            $table,
            [
                'owner_type'  => $owner_type,
                'owner_id'    => $owner_id,
                'kind'        => $kind,
                'file_path'   => ltrim($file_path, '/'),
                'mime'        => isset($result['type']) ? $result['type'] : null,
                'file_hash'   => $hash,
                'hash'        => $hash,
                'size_bytes'  => $size,
                'visibility'  => $visibility,
                'created_by'  => get_current_user_id(),
                'created_at'  => $now,
                'uploaded_by' => get_current_user_id(),
                'uploaded_at' => $now,
                'meta'        => isset($params['meta']) ? wp_json_encode($params['meta']) : null,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s']
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
                'kind'        => $kind,
                'file'        => basename($result['file']),
            ]
        );

        return new WP_REST_Response(
            [
                'id'          => $id,
                'gateway_url' => self::get_media_gateway_url($id),
                'visibility'  => $visibility,
                'mime'        => isset($result['type']) ? $result['type'] : '',
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
        self::ensure_upload_dependencies();

        $file_validation = self::validate_file($file);
        if (is_wp_error($file_validation)) {
            return $file_validation;
        }

        $owner_type = isset($params['owner_type']) ? sanitize_key($params['owner_type']) : '';
        $owner_id = isset($params['owner_id']) ? sanitize_text_field($params['owner_id']) : '';
        $kind = isset($params['kind']) ? sanitize_key($params['kind']) : '';
        $visibility = self::normalize_visibility(isset($params['visibility']) ? sanitize_key($params['visibility']) : '', $owner_type);

        $upload_override = [
            'test_form'                => false,
            'mimes'                    => self::get_allowed_mime_types(),
            'unique_filename_callback' => function ($dir, $name, $ext) {
                return AEGIS_Assets_Media::generate_unique_filename($dir, $name, $ext);
            },
        ];

        $dir_filter = self::build_upload_dir_filter($owner_type ?: 'internal');
        add_filter('upload_dir', $dir_filter);

        $uploads = wp_upload_dir();
        if (!empty($uploads['path']) && !file_exists($uploads['path'])) {
            wp_mkdir_p($uploads['path']);
        }

        $file['name'] = $file_validation['filename'];
        $result = wp_handle_upload($file, $upload_override);
        remove_filter('upload_dir', $dir_filter);

        if (isset($result['error'])) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MEDIA_UPLOAD, 'FAIL', ['reason' => $result['error']]);
            return new WP_Error('upload_error', $result['error']);
        }

        $file_path = str_replace(trailingslashit($uploads['basedir']), '', $result['file']);
        $hash = hash_file('sha256', $result['file']);
        $size = (int) filesize($result['file']);
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $now = current_time('mysql');

        self::soft_delete_previous($owner_type, $owner_id, $kind, $now);

        $wpdb->insert(
            $table,
            [
                'owner_type'  => $owner_type,
                'owner_id'    => $owner_id,
                'kind'        => $kind,
                'file_path'   => ltrim($file_path, '/'),
                'mime'        => isset($result['type']) ? $result['type'] : null,
                'file_hash'   => $hash,
                'hash'        => $hash,
                'size_bytes'  => $size,
                'visibility'  => $visibility,
                'created_by'  => get_current_user_id(),
                'created_at'  => $now,
                'uploaded_by' => get_current_user_id(),
                'uploaded_at' => $now,
                'meta'        => isset($params['meta']) ? wp_json_encode($params['meta']) : null,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s']
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
                'kind'       => $kind,
                'file'       => basename($result['file']),
            ]
        );

        return [
            'id'          => $id,
            'gateway_url' => self::get_media_gateway_url($id),
            'visibility'  => $visibility,
            'mime'        => isset($result['type']) ? $result['type'] : '',
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
     * 清理过期或孤儿文件。
     */
    public static function handle_cleanup($request) {
        $params = $request instanceof WP_REST_Request ? $request->get_params() : [];
        $days = isset($params['days']) ? max(1, (int) $params['days']) : 30;
        $cutoff_ts = current_time('timestamp') - ($days * DAY_IN_SECONDS);
        $cutoff = gmdate('Y-m-d H:i:s', $cutoff_ts);

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $basedir = trailingslashit(wp_upload_dir()['basedir']);

        $stale = $wpdb->get_results($wpdb->prepare("SELECT id, file_path FROM {$table} WHERE deleted_at IS NOT NULL AND deleted_at < %s", $cutoff));
        $orphan = $wpdb->get_results("SELECT id, file_path FROM {$table} WHERE deleted_at IS NULL");

        $removed_files = 0;
        $orphan_marked = 0;

        foreach ($stale as $row) {
            $path = $basedir . $row->file_path;
            if (file_exists($path)) {
                wp_delete_file($path);
                $removed_files++;
            }
            $wpdb->delete($table, ['id' => $row->id], ['%d']);
        }

        foreach ($orphan as $row) {
            $path = $basedir . $row->file_path;
            if (!file_exists($path)) {
                $wpdb->update($table, ['deleted_at' => current_time('mysql')], ['id' => $row->id], ['%s'], ['%d']);
                $orphan_marked++;
            }
        }

        AEGIS_Access_Audit::log(
            AEGIS_System::ACTION_MEDIA_CLEANUP,
            [
                'result'      => 'SUCCESS',
                'entity_type' => 'media',
                'meta'        => [
                    'days'            => $days,
                    'removed_files'   => $removed_files,
                    'orphan_marked'   => $orphan_marked,
                ],
            ]
        );

        return new WP_REST_Response([
            'removed_files' => $removed_files,
            'orphan_marked' => $orphan_marked,
        ]);
    }

    /**
     * 按鉴权输出媒体文件。
     *
     * @param int $id
     */
    public static function stream_media($id) {
        $guard = AEGIS_Dealer::guard_dealer_portal_access();
        if (is_wp_error($guard)) {
            status_header(403);
            wp_die('账户已停用，请联系管理员', 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id));

        if (!$record) {
            AEGIS_Access_Audit::log(
                AEGIS_System::ACTION_MEDIA_ACCESS_DENY,
                [
                    'result'      => 'FAIL',
                    'entity_type' => 'media',
                    'entity_id'   => $id,
                    'message'     => 'not_found',
                ]
            );
            status_header(404);
            exit;
        }

        $visibility = self::normalize_visibility($record->visibility, $record->owner_type);
        $disposition = sanitize_key(get_query_var('aegis_media_disposition'));
        if (!in_array($disposition, ['inline', 'attachment'], true)) {
            $disposition = 'attachment';
        }
        $is_public_certificate = (self::VISIBILITY_PUBLIC === $visibility && in_array($record->owner_type, ['certificate', 'certificates'], true));
        $is_hq = AEGIS_System_Roles::user_can_manage_system() || AEGIS_System_Roles::user_can_manage_warehouse();
        $is_warehouse = AEGIS_System_Roles::user_can_use_warehouse();
        $is_payment_media = in_array($record->owner_type, ['payment_receipt', 'payment_voucher', 'payment_proof', 'order_payment_proof'], true);
        $is_license_media = in_array($record->owner_type, ['business_license', 'dealer_license', 'license'], true);
        $can_view_payment = false;
        $can_view_license = false;

        if ($is_payment_media && class_exists('AEGIS_Orders')) {
            $order = AEGIS_Orders::get_order((int) $record->owner_id);
            if ($order && AEGIS_Orders::current_user_can_view_order($order)) {
                $can_view_payment = true;
            }
        }

        if ($is_license_media && class_exists('AEGIS_Dealer')) {
            $dealer = AEGIS_Dealer::get_dealer_for_user();
            if ($dealer && (string) $dealer->id === (string) $record->owner_id) {
                $can_view_license = true;
            }
        }

        $access_granted = false;
        if ($is_public_certificate) {
            $access_granted = true;
        } elseif (self::VISIBILITY_INTERNAL === $visibility) {
            $access_granted = ($is_hq || $is_warehouse);
        } elseif (self::VISIBILITY_SENSITIVE === $visibility) {
            $access_granted = $is_hq || $can_view_payment || $can_view_license;
        }

        if (!$access_granted) {
            AEGIS_Access_Audit::record_event(
                'ACCESS_DENIED',
                'FAIL',
                [
                    'entity_type' => 'media',
                    'entity_id'   => $id,
                    'reason_code' => 'media_forbidden',
                    'owner_type'  => $record->owner_type,
                ]
            );
            AEGIS_Access_Audit::log(
                AEGIS_System::ACTION_MEDIA_ACCESS_DENY,
                [
                    'result'      => 'FAIL',
                    'entity_type' => 'media',
                    'entity_id'   => $id,
                    'message'     => 'forbidden',
                    'meta'        => ['owner_type' => $record->owner_type, 'visibility' => $visibility],
                ]
            );
            status_header(403);
            exit;
        }

        $file_full_path = trailingslashit(wp_upload_dir()['basedir']) . $record->file_path;
        if (!file_exists($file_full_path)) {
            AEGIS_Access_Audit::log(
                AEGIS_System::ACTION_MEDIA_ACCESS_DENY,
                [
                    'result'      => 'FAIL',
                    'entity_type' => 'media',
                    'entity_id'   => $id,
                    'message'     => 'missing_file',
                    'meta'        => ['owner_type' => $record->owner_type, 'visibility' => $visibility],
                ]
            );
            status_header(404);
            exit;
        }

        AEGIS_Access_Audit::log(
            AEGIS_System::ACTION_MEDIA_ACCESS,
            [
                'result'      => 'SUCCESS',
                'entity_type' => 'media',
                'entity_id'   => $id,
                'meta'        => [
                    'visibility' => $visibility,
                    'owner_type' => $record->owner_type,
                ],
            ]
        );

        $mime = $record->mime ? $record->mime : 'application/octet-stream';
        $filename = $record->file_path ? basename($record->file_path) : 'download';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file_full_path));
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        readfile($file_full_path);
        exit;
    }
}
