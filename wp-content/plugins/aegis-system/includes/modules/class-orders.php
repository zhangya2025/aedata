<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Orders {
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_CLOSED = 'closed';

    protected static function parse_item_post($post) {
        $eans = isset($post['order_item_ean']) ? (array) $post['order_item_ean'] : [];
        $qtys = isset($post['order_item_qty']) ? (array) $post['order_item_qty'] : [];
        $items = [];
        foreach ($eans as $index => $ean_raw) {
            $ean = sanitize_text_field(wp_unslash($ean_raw));
            if ('' === $ean) {
                continue;
            }
            $qty = isset($qtys[$index]) ? (int) $qtys[$index] : 0;
            if ($qty <= 0) {
                continue;
            }
            $items[] = ['ean' => $ean, 'qty' => $qty];
        }
        return $items;
    }

    protected static function create_portal_order($dealer, $items, $note = '') {
        global $wpdb;
        if (empty($items)) {
            return new WP_Error('no_items', '请至少添加一条 SKU 行。');
        }
        if (!$dealer) {
            return new WP_Error('dealer_missing', '未找到经销商信息。');
        }

        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;

        $unique_eans = array_values(array_unique(wp_list_pluck($items, 'ean')));
        $placeholders = implode(',', array_fill(0, count($unique_eans), '%s'));
        $sku_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ean, product_name, status FROM {$sku_table} WHERE ean IN ({$placeholders})",
                $unique_eans
            ),
            OBJECT_K
        );

        foreach ($unique_eans as $ean) {
            if (!isset($sku_rows[$ean])) {
                return new WP_Error('sku_missing', 'SKU 不存在：' . esc_html($ean));
            }
            if (AEGIS_SKU::STATUS_ACTIVE !== $sku_rows[$ean]->status) {
                return new WP_Error('sku_inactive', 'SKU 已停用，禁止下单：' . esc_html($ean));
            }
        }

        $now = current_time('mysql');
        $order_no = self::generate_order_no();
        $item_count = count($items);
        $total_qty = 0;
        foreach ($items as $item) {
            $total_qty += (int) $item['qty'];
        }

        $inserted = $wpdb->insert(
            $order_table,
            [
                'order_no'             => $order_no,
                'dealer_id'            => (int) $dealer->id,
                'status'               => self::STATUS_SUBMITTED,
                'total_amount'         => null,
                'created_by'           => get_current_user_id(),
                'created_at'           => $now,
                'updated_at'           => $now,
                'confirmed_at'         => null,
                'confirmed_by'         => null,
                'note'                 => $note,
                'snapshot_dealer_name' => $dealer->dealer_name,
                'meta'                 => wp_json_encode(['item_count' => $item_count, 'total_qty' => $total_qty]),
            ],
            ['%s', '%d', '%s', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return new WP_Error('order_failed', '订单创建失败，请稍后再试。');
        }

        $order_id = (int) $wpdb->insert_id;
        foreach ($items as $item) {
            $ean = $item['ean'];
            $sku = $sku_rows[$ean];
            $wpdb->insert(
                $item_table,
                [
                    'order_id'              => $order_id,
                    'ean'                   => $ean,
                    'product_name_snapshot' => $sku->product_name,
                    'quantity'              => (int) $item['qty'],
                    'unit_price'            => null,
                    'status'                => 'open',
                    'created_at'            => $now,
                    'meta'                  => null,
                ],
                ['%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s']
            );
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_CREATE,
            'SUCCESS',
            [
                'order_id'  => $order_id,
                'order_no'  => $order_no,
                'lines'     => $item_count,
                'total_qty' => $total_qty,
            ]
        );

        return [
            'order_id' => $order_id,
            'message'  => '下单成功，订单号 ' . $order_no,
        ];
    }

    protected static function generate_order_no() {
        return 'ORD-' . gmdate('Ymd-His', current_time('timestamp')) . '-' . wp_rand(100, 999);
    }

    protected static function update_order_status($order_id, $target_status) {
        global $wpdb;
        if ($order_id <= 0) {
            return new WP_Error('order_missing', '订单不存在。');
        }
        $allowed = [self::STATUS_SUBMITTED, self::STATUS_CONFIRMED, self::STATUS_CANCELLED, self::STATUS_CLOSED];
        if (!in_array($target_status, $allowed, true)) {
            return new WP_Error('bad_status', '无效的目标状态。');
        }

        $order = self::get_order($order_id);
        if (!$order) {
            return new WP_Error('order_missing', '订单不存在。');
        }

        $transition = [
            self::STATUS_SUBMITTED => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_CLOSED, self::STATUS_CANCELLED],
            self::STATUS_CANCELLED => [],
            self::STATUS_CLOSED    => [],
        ];

        $current = $order->status;
        if (!isset($transition[$current]) || !in_array($target_status, $transition[$current], true)) {
            return new WP_Error('invalid_transition', '不支持的状态流转。');
        }

        $update = [
            'status'     => $target_status,
            'updated_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s'];
        if (self::STATUS_CONFIRMED === $target_status) {
            $update['confirmed_at'] = current_time('mysql');
            $update['confirmed_by'] = get_current_user_id();
            $formats[] = '%s';
            $formats[] = '%d';
        }

        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $wpdb->update($order_table, $update, ['id' => $order_id], $formats, ['%d']);

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_STATUS_CHANGE,
            'SUCCESS',
            [
                'order_id' => $order_id,
                'from'     => $current,
                'to'       => $target_status,
            ]
        );

        return ['message' => '订单状态已更新。'];
    }

    protected static function query_portal_orders($args, &$total = 0) {
        global $wpdb;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        $start = $args['start'] ?? '';
        $end = $args['end'] ?? '';
        $dealer_id = $args['dealer_id'] ?? null;
        $order_no = $args['order_no'] ?? '';
        $per_page = $args['per_page'] ?? 20;
        $paged = $args['paged'] ?? 1;
        $offset = ($paged - 1) * $per_page;

        $where = ['o.created_at BETWEEN %s AND %s'];
        $params = [$start, $end];
        if ($dealer_id) {
            $where[] = 'o.dealer_id = %d';
            $params[] = $dealer_id;
        }
        if ($order_no) {
            $where[] = 'o.order_no LIKE %s';
            $params[] = '%' . $wpdb->esc_like($order_no) . '%';
        }
        $where_sql = implode(' AND ', $where);

        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$order_table} o WHERE {$where_sql}", $params));

        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*, d.dealer_name, COALESCE(SUM(oi.quantity),0) AS total_qty, COUNT(DISTINCT oi.ean) AS sku_count FROM {$order_table} o LEFT JOIN {$item_table} oi ON oi.order_id = o.id LEFT JOIN {$dealer_table} d ON o.dealer_id = d.id WHERE {$where_sql} GROUP BY o.id ORDER BY o.created_at DESC LIMIT %d OFFSET %d",
                $params
            )
        );
    }

    protected static function list_active_skus() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_results($wpdb->prepare("SELECT ean, product_name FROM {$table} WHERE status = %s ORDER BY product_name ASC", AEGIS_SKU::STATUS_ACTIVE));
    }

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
                'status'       => self::STATUS_SUBMITTED,
                'total_amount' => $order_total,
                'created_by'   => get_current_user_id(),
                'created_at'   => $now,
                'updated_at'   => $now,
                'snapshot_dealer_name' => $dealer->dealer_name,
                'meta'                 => $items ? wp_json_encode(['item_count' => count($items)]) : null,
            ],
            ['%s', '%d', '%s', '%f', '%d', '%s', '%s', '%s', '%s']
        );

        if (!$wpdb->insert_id) {
            return new WP_Error('order_failed', '订单创建失败。');
        }

        $order_id = (int) $wpdb->insert_id;
        foreach ($items as $item) {
            $wpdb->insert(
                $item_table,
                [
                    'order_id'   => $order_id,
                    'ean'        => $item['ean'],
                    'quantity'   => $item['qty'],
                    'status'     => 'open',
                    'created_at' => $now,
                    'meta'       => null,
                ],
                ['%d', '%s', '%d', '%s', '%s', '%s']
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

    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('orders')) {
            return '<div class="aegis-t-a5">模块未启用。</div>';
        }

        $user = wp_get_current_user();
        $roles = (array) ($user ? $user->roles : []);
        $is_dealer = in_array('aegis_dealer', $roles, true);
        $can_manage = AEGIS_System_Roles::user_can_manage_warehouse() || current_user_can(AEGIS_System::CAP_ORDERS);
        $is_staff_readonly = in_array('aegis_warehouse_staff', $roles, true) && !$can_manage;

        $dealer_state = $is_dealer ? AEGIS_Dealer::evaluate_dealer_access($user) : null;
        $dealer = $dealer_state['dealer'] ?? null;
        $dealer_id = $dealer ? (int) $dealer->id : 0;
        $dealer_blocked = $is_dealer && (!$dealer_state || empty($dealer_state['allowed']));

        $messages = [];
        $errors = [];
        $base_url = add_query_arg('m', 'orders', $portal_url);
        $view_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['order_action']) ? sanitize_key(wp_unslash($_POST['order_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            if ('create_order' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ACCESS_ROOT,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'note', 'order_item_ean', 'order_item_qty', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif ($dealer_blocked || !$dealer_id) {
                    $errors[] = '当前经销商账号不可下单，请联系管理员。';
                } else {
                    $items = self::parse_item_post($_POST);
                    $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
                    $result = self::create_portal_order($dealer, $items, $note);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                        $view_id = (int) $result['order_id'];
                    }
                }
            } elseif ('change_status' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'target_status', 'order_id', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif ($is_staff_readonly) {
                    $errors[] = '当前账号仅可查看订单。';
                } else {
                    $target_status = isset($_POST['target_status']) ? sanitize_key(wp_unslash($_POST['target_status'])) : '';
                    $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                    $result = self::update_order_status($order_id, $target_status);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                        $view_id = $order_id;
                    }
                }
            }
        }

        $default_start = gmdate('Y-m-d', current_time('timestamp') - 6 * DAY_IN_SECONDS);
        $default_end = gmdate('Y-m-d', current_time('timestamp'));
        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : $default_start;
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : $default_end;
        $search_no = isset($_GET['order_no']) ? sanitize_text_field(wp_unslash($_GET['order_no'])) : '';
        $start_datetime = self::normalize_date_boundary($start_date, 'start');
        $end_datetime = self::normalize_date_boundary($end_date, 'end');
        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $dealer_filter = $is_dealer ? ($dealer_id > 0 ? $dealer_id : -1) : null;
        $total = 0;
        $orders = self::query_portal_orders(
            [
                'start'     => $start_datetime,
                'end'       => $end_datetime,
                'order_no'  => $search_no,
                'dealer_id' => $dealer_filter,
                'per_page'  => $per_page,
                'paged'     => $paged,
            ],
            $total
        );

        $order = $view_id ? self::get_order($view_id) : null;
        if ($order && $is_dealer && (int) $order->dealer_id !== $dealer_id) {
            $order = null;
            $errors[] = '无权查看该订单。';
        }
        $items = $order ? self::get_items($order->id) : [];
        $skus = $is_dealer ? self::list_active_skus() : [];

        $context = [
            'base_url'    => $base_url,
            'messages'    => $messages,
            'errors'      => $errors,
            'orders'      => $orders,
            'order'       => $order,
            'items'       => $items,
            'filters'     => [
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'order_no'    => $search_no,
                'per_page'    => $per_page,
                'per_options' => $per_page_options,
                'paged'       => $paged,
                'total'       => $total,
                'total_pages' => $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1,
            ],
            'skus'        => $skus,
            'dealer'      => $dealer,
            'dealer_blocked' => $dealer_blocked,
            'role_flags'  => [
                'is_dealer'    => $is_dealer,
                'can_manage'   => $can_manage,
                'staff_readonly' => $is_staff_readonly,
            ],
        ];

        return AEGIS_Portal::render_portal_template('orders', $context);
    }

}

