<?php
if (!defined('ABSPATH')) {
    exit;
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

