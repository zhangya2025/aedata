<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Dealer {
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const USER_META_KEY = 'aegis_dealer_id';

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
                'auth_start_date',
                'auth_end_date',
                'status',
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
        $auth_start_date = $dealer ? $dealer->auth_start_date : '';
        $auth_end_date = $dealer ? $dealer->auth_end_date : '';
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
        }
        echo '</td></tr>';

        echo '<tr><th><label for="aegis-dealer-name">名称</label></th><td><input type="text" id="aegis-dealer-name" name="dealer_name" value="' . esc_attr($dealer_name) . '" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="aegis-contact-name">联系人</label></th><td><input type="text" id="aegis-contact-name" name="contact_name" value="' . esc_attr($contact_name) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="aegis-phone">电话</label></th><td><input type="text" id="aegis-phone" name="phone" value="' . esc_attr($phone) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="aegis-address">地址</label></th><td><input type="text" id="aegis-address" name="address" value="' . esc_attr($address) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="aegis-authorized-start">授权开始日期</label></th><td><input type="date" id="aegis-authorized-start" name="auth_start_date" value="' . esc_attr(self::format_date_input($auth_start_date)) . '" required /></td></tr>';
        echo '<tr><th><label for="aegis-authorized-end">授权截止日期</label></th><td><input type="date" id="aegis-authorized-end" name="auth_end_date" value="' . esc_attr(self::format_date_input($auth_end_date)) . '" required /></td></tr>';

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
        $auth_start_raw = isset($post['auth_start_date']) ? sanitize_text_field(wp_unslash($post['auth_start_date'])) : '';
        $auth_end_raw = isset($post['auth_end_date']) ? sanitize_text_field(wp_unslash($post['auth_end_date'])) : '';
        $status_raw = isset($post['status']) ? sanitize_key($post['status']) : '';

        $is_new = $dealer_id === 0;
        $existing = $is_new ? null : self::get_dealer($dealer_id);
        if (!$is_new && !$existing) {
            return new WP_Error('not_found', '未找到对应的经销商。');
        }

        $auth_code_to_use = $is_new ? $auth_code_input : $existing->auth_code;

        if ($is_new && '' === $auth_code_to_use) {
            return new WP_Error('code_required', '请填写授权编码。');
        }

        if (!$is_new && $auth_code_input && $auth_code_input !== $auth_code_to_use) {
            return new WP_Error('code_locked', '授权编码不可修改，如录入错误请停用后新建。');
        }

        if (self::auth_code_exists($auth_code_to_use, $dealer_id)) {
            return new WP_Error('code_exists', '授权编码已存在，无法重复。');
        }

        $auth_start_date = $auth_start_raw ? self::normalize_date($auth_start_raw) : '';
        $auth_end_date = $auth_end_raw ? self::normalize_date($auth_end_raw) : '';

        if (!$auth_start_date || !$auth_end_date) {
            return new WP_Error('auth_period_required', '请填写授权有效期。');
        }

        if (strtotime($auth_end_date) < strtotime($auth_start_date)) {
            return new WP_Error('auth_period_invalid', '授权截止日期不能早于开始日期。');
        }

        $status = $is_new ? self::STATUS_ACTIVE : ($existing ? $existing->status : self::STATUS_ACTIVE);
        if ($status_raw && array_key_exists($status_raw, self::get_status_labels())) {
            $status = $status_raw;
        }

        if (!array_key_exists($status, self::get_status_labels())) {
            $status = self::STATUS_ACTIVE;
        }

        $now = current_time('mysql');

        $data = [
            'dealer_name'  => $dealer_name,
            'contact_name' => $contact_name,
            'phone'        => $phone,
            'address'      => $address,
            'auth_start_date' => $auth_start_date,
            'auth_end_date'   => $auth_end_date,
            'authorized_at'   => $auth_start_date ? $auth_start_date . ' 00:00:00' : null,
            'status'       => $status,
            'updated_at'   => $now,
        ];

        $messages = [];

        if ($is_new) {
            $data['auth_code'] = $auth_code_to_use;
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, array_fill(0, count($data), '%s'));
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
     * 格式化 date 输入值。
     *
     * @param string|null $value
     * @return string
     */
    public static function format_date_input($value) {
        if (empty($value)) {
            return '';
        }

        return mysql2date('Y-m-d', $value);
    }

    /**
     * 前台展示日期。
     *
     * @param string|null $value
     * @return string
     */
    public static function format_date_display($value) {
        if (empty($value)) {
            return '—';
        }

        return mysql2date('Y-m-d', $value);
    }

    /**
     * 展示授权区间。
     *
     * @param object $dealer
     * @return string
     */
    public static function format_auth_range($dealer) {
        if (!$dealer) {
            return '';
        }

        $start = self::format_date_display($dealer->auth_start_date);
        $end = self::format_date_display($dealer->auth_end_date);

        return trim($start . ' ~ ' . $end);
    }

    /**
     * 归一化日期。
     *
     * @param string $value
     * @return string|null
     */
    protected static function normalize_date($value) {
        if (empty($value)) {
            return null;
        }
        $dt = date_create($value, wp_timezone());
        if (!$dt) {
            return null;
        }

        return $dt->format('Y-m-d');
    }

    /**
     * Portal 经销商面板渲染。
     *
     * @param string $portal_url
     * @return string
     */
    public static function render_portal_panel($portal_url) {
        $user = wp_get_current_user();

        if (!AEGIS_System::is_module_enabled('dealer_master')) {
            return '<div class="aegis-t-a5">经销商模块未启用，请在系统设置中启用。</div>';
        }

        if ($user && in_array('aegis_dealer', (array) $user->roles, true)) {
            return '<div class="aegis-t-a5">当前账号无权访问经销商管理。</div>';
        }

        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return '<div class="aegis-t-a5">当前账号无权访问经销商模块。</div>';
        }

        $can_edit = AEGIS_System_Roles::user_can_manage_warehouse();
        $assets_enabled = AEGIS_System::is_module_enabled('assets_media');
        $base_url = add_query_arg('m', 'dealer_master', $portal_url);

        wp_enqueue_script(
            'aegis-system-portal-sku',
            AEGIS_SYSTEM_URL . 'assets/js/portal-sku.js',
            [],
            AEGIS_Assets_Media::get_asset_version('assets/js/portal-sku.js'),
            true
        );

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $current_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $current_dealer = $current_id ? self::get_dealer($current_id) : null;
        $messages = [];
        $errors = [];

        if ('POST' === $_SERVER['REQUEST_METHOD'] && $can_edit) {
            $request_action = isset($_POST['dealer_action']) ? sanitize_key(wp_unslash($_POST['dealer_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $whitelist = [
                'dealer_action',
                'dealer_id',
                'auth_code',
                'dealer_name',
                'contact_name',
                'phone',
                'address',
                'auth_start_date',
                'auth_end_date',
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
            } elseif ('save' === $request_action) {
                $result = self::handle_portal_save($_POST, $_FILES, $assets_enabled);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages = array_merge($messages, $result['messages']);
                    $current_dealer = $result['dealer'];
                    $current_id = $current_dealer ? (int) $current_dealer->id : 0;
                    $action = 'edit';
                }
            } elseif ('toggle_status' === $request_action) {
                $result = self::handle_portal_status_toggle($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages[] = $result['message'];
                }
            }
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        $per_options = [20, 50, 100];
        if (!in_array($per_page, $per_options, true)) {
            $per_page = 20;
        }
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $list_args = [
            'search'   => $search,
            'page'     => $page,
            'per_page' => $per_page,
            'order_by' => 'updated_at',
            'order'    => 'DESC',
        ];

        $dealers = self::list_portal_dealers($list_args);
        $license_ids = [];
        foreach ($dealers as $dealer) {
            if (!empty($dealer->business_license_id)) {
                $license_ids[] = (int) $dealer->business_license_id;
            }
        }

        $licenses = self::get_media_records_by_ids($license_ids);
        foreach ($dealers as $dealer) {
            $license = $dealer->business_license_id && isset($licenses[$dealer->business_license_id]) ? $licenses[$dealer->business_license_id] : null;
            $dealer->license_url = $license ? self::get_media_gateway_url($license->id) : '';
            $dealer->license_mime = $license && !empty($license->mime) ? $license->mime : '';
        }

        $total = self::count_portal_dealers(['search' => $search]);
        $total_pages = $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1;

        $current_license = $current_dealer && $current_dealer->business_license_id ? self::get_media_records_by_ids([$current_dealer->business_license_id]) : [];
        $current_license_record = $current_license ? reset($current_license) : null;

        $context = [
            'base_url'       => $base_url,
            'action'         => $action,
            'can_edit'       => $can_edit,
            'assets_enabled' => $assets_enabled,
            'messages'       => $messages,
            'errors'         => $errors,
            'dealers'        => $dealers,
            'status_labels'  => self::get_status_labels(),
            'current_dealer' => $current_dealer,
            'current_media'  => $current_license_record,
            'list'           => [
                'search'      => $search,
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => $total_pages,
                'per_options' => $per_options,
            ],
        ];

        return AEGIS_Portal::render_portal_template('dealer', $context);
    }

    /**
     * Portal 保存处理。
     *
     * @param array $post
     * @param array $files
     * @param bool  $assets_enabled
     * @return array|WP_Error
     */
    protected static function handle_portal_save($post, $files, $assets_enabled) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        $dealer_id = isset($post['dealer_id']) ? (int) $post['dealer_id'] : 0;
        $auth_code_input = isset($post['auth_code']) ? sanitize_text_field(wp_unslash($post['auth_code'])) : '';
        $dealer_name = isset($post['dealer_name']) ? sanitize_text_field(wp_unslash($post['dealer_name'])) : '';
        $contact_name = isset($post['contact_name']) ? sanitize_text_field(wp_unslash($post['contact_name'])) : '';
        $phone = isset($post['phone']) ? sanitize_text_field(wp_unslash($post['phone'])) : '';
        $address = isset($post['address']) ? sanitize_text_field(wp_unslash($post['address'])) : '';
        $auth_start_raw = isset($post['auth_start_date']) ? sanitize_text_field(wp_unslash($post['auth_start_date'])) : '';
        $auth_end_raw = isset($post['auth_end_date']) ? sanitize_text_field(wp_unslash($post['auth_end_date'])) : '';

        $is_new = $dealer_id === 0;
        $existing = $is_new ? null : self::get_dealer($dealer_id);
        if (!$is_new && !$existing) {
            return new WP_Error('not_found', '未找到对应的经销商。');
        }

        $auth_code_to_use = $is_new ? $auth_code_input : $existing->auth_code;

        if ($is_new && '' === $auth_code_to_use) {
            return new WP_Error('code_required', '请填写授权编码。');
        }

        if (!$is_new && $auth_code_input && $auth_code_input !== $auth_code_to_use) {
            return new WP_Error('code_locked', '授权编码不可修改，如录入错误请停用后新建。');
        }

        if (self::auth_code_exists($auth_code_to_use, $dealer_id)) {
            return new WP_Error('code_exists', '授权编码已存在，无法重复。');
        }

        $now = current_time('mysql');
        $auth_start_date = $auth_start_raw ? self::normalize_date($auth_start_raw) : '';
        $auth_end_date = $auth_end_raw ? self::normalize_date($auth_end_raw) : '';

        if (!$auth_start_date || !$auth_end_date) {
            return new WP_Error('auth_period_required', '请填写授权有效期。');
        }

        if (strtotime($auth_end_date) < strtotime($auth_start_date)) {
            return new WP_Error('auth_period_invalid', '授权截止日期不能早于开始日期。');
        }

        $status = $is_new ? self::STATUS_ACTIVE : ($existing ? $existing->status : self::STATUS_ACTIVE);

        if (!array_key_exists($status, self::get_status_labels())) {
            $status = self::STATUS_ACTIVE;
        }

        $data = [
            'dealer_name'  => $dealer_name,
            'contact_name' => $contact_name,
            'phone'        => $phone,
            'address'      => $address,
            'auth_start_date' => $auth_start_date,
            'auth_end_date'   => $auth_end_date,
            'authorized_at'   => $auth_start_date ? $auth_start_date . ' 00:00:00' : null,
            'status'       => $status,
            'updated_at'   => $now,
        ];

        $messages = [];

        if ($is_new) {
            $data['auth_code'] = $auth_code_to_use;
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, array_fill(0, count($data), '%s'));
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
        }

        if ($assets_enabled && isset($files['business_license']) && is_array($files['business_license']) && !empty($files['business_license']['name'])) {
            $upload = AEGIS_Assets_Media::handle_admin_upload($files['business_license'], [
                'bucket'     => 'dealer',
                'owner_type' => 'dealer_license',
                'owner_id'   => $dealer_id,
                'visibility' => AEGIS_Assets_Media::VISIBILITY_SENSITIVE,
                'meta'       => ['type' => 'dealer_license', 'auth_code' => $auth_code_to_use],
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
     * Portal 状态切换。
     *
     * @param array $post
     * @return array|WP_Error
     */
    protected static function handle_portal_status_toggle($post) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $dealer_id = isset($post['dealer_id']) ? (int) $post['dealer_id'] : 0;
        $target = isset($post['target_status']) ? sanitize_key($post['target_status']) : '';

        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            return new WP_Error('forbidden', '当前账号无权更改经销商状态。');
        }

        if (!AEGIS_System::is_module_enabled('dealer_master')) {
            return new WP_Error('module_disabled', '经销商模块未启用。');
        }

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
     * Portal 列表查询。
     *
     * @param array $args
     * @return array
     */
    protected static function list_portal_dealers($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $search = isset($args['search']) ? $args['search'] : '';
        $page = isset($args['page']) ? max(1, (int) $args['page']) : 1;
        $per_page = isset($args['per_page']) ? max(1, (int) $args['per_page']) : 20;
        $order_by = isset($args['order_by']) ? sanitize_key($args['order_by']) : 'updated_at';
        $order = isset($args['order']) && in_array(strtoupper($args['order']), ['ASC', 'DESC'], true) ? strtoupper($args['order']) : 'DESC';
        $offset = ($page - 1) * $per_page;

        $allowed_order = ['updated_at', 'created_at'];
        if (!in_array($order_by, $allowed_order, true)) {
            $order_by = 'updated_at';
        }

        $where = 'WHERE 1=1';
        $params = [];
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (auth_code LIKE %s OR dealer_name LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM {$table} {$where} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Portal 列表计数。
     *
     * @param array $args
     * @return int
     */
    protected static function count_portal_dealers($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $search = isset($args['search']) ? $args['search'] : '';

        $where = 'WHERE 1=1';
        $params = [];
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (auth_code LIKE %s OR dealer_name LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * 批量获取媒体记录。
     *
     * @param array $ids
     * @return array
     */
    protected static function get_media_records_by_ids($ids) {
        $ids = array_filter(array_map('intval', (array) $ids));
        if (empty($ids)) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $sql = "SELECT * FROM {$table} WHERE id IN ({$placeholders}) AND deleted_at IS NULL";

        $records = $wpdb->get_results($wpdb->prepare($sql, $ids));
        $map = [];
        foreach ($records as $record) {
            $map[$record->id] = $record;
        }

        return $map;
    }

    /**
     * 获取当前用户绑定的经销商记录。
     *
     * @param WP_User|null $user
     * @return object|null
     */
    public static function get_dealer_for_user($user = null) {
        if (null === $user) {
            $user = wp_get_current_user();
        }

        if (!$user || !$user->ID) {
            return null;
        }

        $dealer_id = (int) get_user_meta($user->ID, self::USER_META_KEY, true);
        if ($dealer_id <= 0) {
            return null;
        }

        return self::get_dealer($dealer_id);
    }

    /**
     * 评估经销商账号访问状态。
     *
     * @param WP_User|null $user
     * @return array
     */
    public static function evaluate_dealer_access($user = null) {
        $dealer = self::get_dealer_for_user($user);

        if (!$dealer) {
            return [
                'allowed' => false,
                'reason'  => 'dealer_missing',
                'dealer'  => null,
            ];
        }

        if (self::STATUS_ACTIVE !== $dealer->status) {
            return [
                'allowed' => false,
                'reason'  => 'dealer_inactive',
                'dealer'  => $dealer,
            ];
        }

        $end_ts = self::get_auth_end_timestamp($dealer->auth_end_date);
        $remaining_days = null;

        if ($end_ts) {
            $now = current_time('timestamp');
            if ($now > $end_ts) {
                return [
                    'allowed' => false,
                    'reason'  => 'dealer_expired',
                    'dealer'  => $dealer,
                ];
            }

            $remaining_days = (int) floor(($end_ts - $now) / DAY_IN_SECONDS);
        }

        return [
            'allowed'       => true,
            'reason'        => 'ok',
            'dealer'        => $dealer,
            'remaining_days'=> $remaining_days,
        ];
    }

    /**
     * 授权截止日期按日末时间戳。
     *
     * @param string|null $date
     * @return int|null
     */
    protected static function get_auth_end_timestamp($date) {
        if (empty($date)) {
            return null;
        }

        $dt = date_create_from_format('Y-m-d H:i:s', $date . ' 23:59:59', wp_timezone());
        if (!$dt) {
            return null;
        }

        return $dt->getTimestamp();
    }

    /**
     * 媒体网关 URL。
     *
     * @param int $media_id
     * @return string
     */
    public static function get_media_gateway_url($media_id) {
        if (!$media_id) {
            return '';
        }

        return add_query_arg('aegis_media', (int) $media_id, home_url('/'));
    }

    /**
     * Portal 经销商面板渲染。
     *
     * @param string $portal_url
     * @return string
     */
    public static function render_portal_panel($portal_url) {
        $user = wp_get_current_user();

        if (!AEGIS_System::is_module_enabled('dealer_master')) {
            return '<div class="aegis-t-a5">经销商模块未启用，请在系统设置中启用。</div>';
        }

        if ($user && in_array('aegis_dealer', (array) $user->roles, true)) {
            return '<div class="aegis-t-a5">当前账号无权访问经销商管理。</div>';
        }

        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return '<div class="aegis-t-a5">当前账号无权访问经销商模块。</div>';
        }

        $can_edit = AEGIS_System_Roles::user_can_manage_warehouse();
        $assets_enabled = AEGIS_System::is_module_enabled('assets_media');
        $base_url = add_query_arg('m', 'dealer_master', $portal_url);

        wp_enqueue_script(
            'aegis-system-portal-sku',
            AEGIS_SYSTEM_URL . 'assets/js/portal-sku.js',
            [],
            AEGIS_Assets_Media::get_asset_version('assets/js/portal-sku.js'),
            true
        );

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $current_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $current_dealer = $current_id ? self::get_dealer($current_id) : null;
        $messages = [];
        $errors = [];

        if ('POST' === $_SERVER['REQUEST_METHOD'] && $can_edit) {
            $request_action = isset($_POST['dealer_action']) ? sanitize_key(wp_unslash($_POST['dealer_action'])) : '';
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
            } elseif ('save' === $request_action) {
                $result = self::handle_portal_save($_POST, $_FILES, $assets_enabled);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages = array_merge($messages, $result['messages']);
                    $current_dealer = $result['dealer'];
                    $current_id = $current_dealer ? (int) $current_dealer->id : 0;
                    $action = 'edit';
                }
            } elseif ('toggle_status' === $request_action) {
                $result = self::handle_portal_status_toggle($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages[] = $result['message'];
                }
            }
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        $per_options = [20, 50, 100];
        if (!in_array($per_page, $per_options, true)) {
            $per_page = 20;
        }
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $list_args = [
            'search'   => $search,
            'page'     => $page,
            'per_page' => $per_page,
            'order_by' => 'updated_at',
            'order'    => 'DESC',
        ];

        $dealers = self::list_portal_dealers($list_args);
        $license_ids = [];
        foreach ($dealers as $dealer) {
            if (!empty($dealer->business_license_id)) {
                $license_ids[] = (int) $dealer->business_license_id;
            }
        }

        $licenses = self::get_media_records_by_ids($license_ids);
        foreach ($dealers as $dealer) {
            $license = $dealer->business_license_id && isset($licenses[$dealer->business_license_id]) ? $licenses[$dealer->business_license_id] : null;
            $dealer->license_url = $license ? self::get_media_gateway_url($license->id) : '';
            $dealer->license_mime = $license && !empty($license->mime) ? $license->mime : '';
        }

        $total = self::count_portal_dealers(['search' => $search]);
        $total_pages = $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1;

        $current_license = $current_dealer && $current_dealer->business_license_id ? self::get_media_records_by_ids([$current_dealer->business_license_id]) : [];
        $current_license_record = $current_license ? reset($current_license) : null;

        $context = [
            'base_url'       => $base_url,
            'action'         => $action,
            'can_edit'       => $can_edit,
            'assets_enabled' => $assets_enabled,
            'messages'       => $messages,
            'errors'         => $errors,
            'dealers'        => $dealers,
            'status_labels'  => self::get_status_labels(),
            'current_dealer' => $current_dealer,
            'current_media'  => $current_license_record,
            'list'           => [
                'search'      => $search,
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => $total_pages,
                'per_options' => $per_options,
            ],
        ];

        return AEGIS_Portal::render_portal_template('dealer', $context);
    }

    /**
     * Portal 保存处理。
     *
     * @param array $post
     * @param array $files
     * @param bool  $assets_enabled
     * @return array|WP_Error
     */
    protected static function handle_portal_save($post, $files, $assets_enabled) {
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

        if ($is_new && '' === $auth_code_to_use) {
            return new WP_Error('code_required', '请填写授权编码。');
        }

        if (!$is_new && $auth_code_input && $auth_code_input !== $auth_code_to_use) {
            return new WP_Error('code_locked', '授权编码不可修改，如录入错误请停用后新建。');
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
        }

        if ($assets_enabled && isset($files['business_license']) && is_array($files['business_license']) && !empty($files['business_license']['name'])) {
            $upload = AEGIS_Assets_Media::handle_admin_upload($files['business_license'], [
                'bucket'     => 'dealer',
                'owner_type' => 'dealer_license',
                'owner_id'   => $dealer_id,
                'visibility' => AEGIS_Assets_Media::VISIBILITY_SENSITIVE,
                'meta'       => ['type' => 'dealer_license', 'auth_code' => $auth_code_to_use],
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
     * Portal 状态切换。
     *
     * @param array $post
     * @return array|WP_Error
     */
    protected static function handle_portal_status_toggle($post) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $dealer_id = isset($post['dealer_id']) ? (int) $post['dealer_id'] : 0;
        $target = isset($post['target_status']) ? sanitize_key($post['target_status']) : '';

        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            return new WP_Error('forbidden', '当前账号无权更改经销商状态。');
        }

        if (!AEGIS_System::is_module_enabled('dealer_master')) {
            return new WP_Error('module_disabled', '经销商模块未启用。');
        }

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
     * Portal 列表查询。
     *
     * @param array $args
     * @return array
     */
    protected static function list_portal_dealers($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $search = isset($args['search']) ? $args['search'] : '';
        $page = isset($args['page']) ? max(1, (int) $args['page']) : 1;
        $per_page = isset($args['per_page']) ? max(1, (int) $args['per_page']) : 20;
        $order_by = isset($args['order_by']) ? sanitize_key($args['order_by']) : 'updated_at';
        $order = isset($args['order']) && in_array(strtoupper($args['order']), ['ASC', 'DESC'], true) ? strtoupper($args['order']) : 'DESC';
        $offset = ($page - 1) * $per_page;

        $allowed_order = ['updated_at', 'created_at'];
        if (!in_array($order_by, $allowed_order, true)) {
            $order_by = 'updated_at';
        }

        $where = 'WHERE 1=1';
        $params = [];
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (auth_code LIKE %s OR dealer_name LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM {$table} {$where} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Portal 列表计数。
     *
     * @param array $args
     * @return int
     */
    protected static function count_portal_dealers($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $search = isset($args['search']) ? $args['search'] : '';

        $where = 'WHERE 1=1';
        $params = [];
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (auth_code LIKE %s OR dealer_name LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * 批量获取媒体记录。
     *
     * @param array $ids
     * @return array
     */
    protected static function get_media_records_by_ids($ids) {
        $ids = array_filter(array_map('intval', (array) $ids));
        if (empty($ids)) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $sql = "SELECT * FROM {$table} WHERE id IN ({$placeholders}) AND deleted_at IS NULL";

        $records = $wpdb->get_results($wpdb->prepare($sql, $ids));
        $map = [];
        foreach ($records as $record) {
            $map[$record->id] = $record;
        }

        return $map;
    }

    /**
     * 媒体网关 URL。
     *
     * @param int $media_id
     * @return string
     */
    public static function get_media_gateway_url($media_id) {
        if (!$media_id) {
            return '';
        }

        return add_query_arg('aegis_media', (int) $media_id, home_url('/'));
    }
}

