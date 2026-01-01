<?php
if (!defined('ABSPATH')) {
    exit;
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
                ['status' => 'used', 'stock_status' => 'shipped'],
                ['id' => $code->id, 'stock_status' => 'in_stock'],
                ['%s', '%s'],
                ['%d', '%s']
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
            if ('in_stock' !== $row->stock_status) {
                if ('generated' === $row->stock_status || !$row->stock_status) {
                    $invalid[] = $row->code . '（未入库）';
                } elseif ('shipped' === $row->stock_status) {
                    $invalid[] = $row->code . '（已出库）';
                } else {
                    $invalid[] = $row->code . '（状态异常）';
                }
            }
        }
        if (!empty($invalid)) {
            return new WP_Error('code_used', '以下防伪码不可出库：' . implode(', ', array_map('esc_html', $invalid)));
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

