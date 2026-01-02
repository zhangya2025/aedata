<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Orders {
    const STATUS_PENDING_INITIAL_REVIEW = 'pending_initial_review';
    const STATUS_CANCELLED_BY_DEALER = 'cancelled_by_dealer';

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

    protected static function generate_order_no() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        for ($i = 0; $i < 5; $i++) {
            $order_no = 'ORD-' . gmdate('Ymd-His', current_time('timestamp')) . '-' . wp_rand(100, 999);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE order_no = %s", $order_no));
            if (!$exists) {
                return $order_no;
            }
        }
        return uniqid('ORD-', false);
    }

    protected static function load_skus($eans) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        if (empty($eans)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eans), '%s'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ean, product_name, status FROM {$table} WHERE ean IN ({$placeholders})",
                $eans
            ),
            OBJECT_K
        );
        return $rows ?: [];
    }

    protected static function prepare_priced_items($dealer, $items) {
        if (empty($items)) {
            return new WP_Error('no_items', '请至少添加一条 SKU 行。');
        }

        $unique_eans = array_values(array_unique(wp_list_pluck($items, 'ean')));
        $sku_rows = self::load_skus($unique_eans);
        foreach ($unique_eans as $ean) {
            if (!isset($sku_rows[$ean])) {
                return new WP_Error('sku_missing', 'SKU 不存在：' . esc_html($ean));
            }
            if (AEGIS_SKU::STATUS_ACTIVE !== $sku_rows[$ean]->status) {
                return new WP_Error('sku_inactive', 'SKU 已停用，禁止下单：' . esc_html($ean));
            }
        }

        $priced = [];
        foreach ($items as $item) {
            $ean = $item['ean'];
            $qty = (int) $item['qty'];
            $quote = AEGIS_Pricing::get_quote((int) $dealer->id, $ean);
            if (is_wp_error($quote)) {
                return $quote;
            }
            if (empty($quote['unit_price'])) {
                return new WP_Error('missing_price', 'SKU 无可用价格，禁止下单：' . esc_html($ean));
            }
            $priced[] = [
                'ean'                   => $ean,
                'qty'                   => $qty,
                'product_name_snapshot' => $sku_rows[$ean]->product_name,
                'unit_price_snapshot'   => number_format((float) $quote['unit_price'], 4, '.', ''),
                'price_source'          => $quote['price_source'],
                'price_level_snapshot'  => $quote['price_level_used'],
            ];
        }

        return $priced;
    }

    protected static function calculate_totals($items) {
        $total_qty = 0;
        $total_amount = 0.0;
        foreach ($items as $item) {
            $qty = (int) $item['qty'];
            $total_qty += $qty;
            $total_amount += ((float) $item['unit_price_snapshot']) * $qty;
        }
        return [
            'item_count'   => count($items),
            'total_qty'    => $total_qty,
            'total_amount' => $total_amount,
        ];
    }

    protected static function persist_items($order_id, $items, $timestamp) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        $wpdb->delete($table, ['order_id' => $order_id], ['%d']);
        foreach ($items as $item) {
            $wpdb->insert(
                $table,
                [
                    'order_id'              => $order_id,
                    'ean'                   => $item['ean'],
                    'product_name_snapshot' => $item['product_name_snapshot'],
                    'qty'                   => (int) $item['qty'],
                    'unit_price_snapshot'   => $item['unit_price_snapshot'],
                    'price_source'          => $item['price_source'],
                    'price_level_snapshot'  => $item['price_level_snapshot'],
                    'created_at'            => $timestamp,
                    'meta'                  => null,
                ],
                ['%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s']
            );
        }
    }

    protected static function create_portal_order($dealer, $items, $note = '') {
        global $wpdb;
        $priced_items = self::prepare_priced_items($dealer, $items);
        if (is_wp_error($priced_items)) {
            return $priced_items;
        }

        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $now = current_time('mysql');
        $order_no = self::generate_order_no();
        $totals = self::calculate_totals($priced_items);

        $inserted = $wpdb->insert(
            $order_table,
            [
                'order_no'               => $order_no,
                'dealer_id'              => (int) $dealer->id,
                'status'                 => self::STATUS_PENDING_INITIAL_REVIEW,
                'total_amount'           => $totals['total_amount'],
                'created_by'             => get_current_user_id(),
                'created_at'             => $now,
                'updated_at'             => $now,
                'confirmed_at'           => null,
                'confirmed_by'           => null,
                'note'                   => $note,
                'snapshot_dealer_name'   => $dealer->dealer_name,
                'dealer_name_snapshot'   => $dealer->dealer_name,
                'sales_user_id_snapshot' => $dealer->sales_user_id,
                'meta'                   => wp_json_encode([
                    'item_count' => $totals['item_count'],
                    'total_qty'  => $totals['total_qty'],
                ]),
            ],
            ['%s', '%d', '%s', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s']
        );

        if (!$inserted) {
            return new WP_Error('order_failed', '订单创建失败，请稍后再试。');
        }

        $order_id = (int) $wpdb->insert_id;
        self::persist_items($order_id, $priced_items, $now);

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_CREATE,
            'SUCCESS',
            [
                'order_id'  => $order_id,
                'order_no'  => $order_no,
                'lines'     => $totals['item_count'],
                'total_qty' => $totals['total_qty'],
            ]
        );

        return [
            'order_id' => $order_id,
            'message'  => '下单成功，订单号 ' . $order_no,
        ];
    }

    protected static function update_portal_order($dealer, $order, $items, $note = '') {
        global $wpdb;
        if ((int) $order->dealer_id !== (int) $dealer->id) {
            return new WP_Error('forbidden', '无权操作该订单。');
        }
        if (self::STATUS_PENDING_INITIAL_REVIEW !== $order->status) {
            return new WP_Error('bad_status', '仅待初审订单可编辑。');
        }

        $priced_items = self::prepare_priced_items($dealer, $items);
        if (is_wp_error($priced_items)) {
            return $priced_items;
        }

        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $now = current_time('mysql');
        $totals = self::calculate_totals($priced_items);
        $updated = $wpdb->update(
            $order_table,
            [
                'updated_at'   => $now,
                'note'         => $note,
                'total_amount' => $totals['total_amount'],
                'meta'         => wp_json_encode([
                    'item_count' => $totals['item_count'],
                    'total_qty'  => $totals['total_qty'],
                ]),
            ],
            ['id' => (int) $order->id],
            ['%s', '%s', '%f', '%s'],
            ['%d']
        );

        if (false === $updated) {
            return new WP_Error('update_failed', '订单更新失败，请稍后再试。');
        }

        self::persist_items((int) $order->id, $priced_items, $now);

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_UPDATE,
            'SUCCESS',
            [
                'order_id'  => (int) $order->id,
                'order_no'  => $order->order_no,
                'lines'     => $totals['item_count'],
                'total_qty' => $totals['total_qty'],
            ]
        );

        return ['message' => '订单已更新。'];
    }

    protected static function cancel_portal_order($dealer, $order) {
        global $wpdb;
        if ((int) $order->dealer_id !== (int) $dealer->id) {
            return new WP_Error('forbidden', '无权操作该订单。');
        }
        if (self::STATUS_PENDING_INITIAL_REVIEW !== $order->status) {
            return new WP_Error('bad_status', '仅待初审订单可撤销。');
        }

        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $wpdb->update(
            $table,
            [
                'status'     => self::STATUS_CANCELLED_BY_DEALER,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => (int) $order->id],
            ['%s', '%s'],
            ['%d']
        );

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_STATUS_CHANGE,
            'SUCCESS',
            [
                'order_id' => (int) $order->id,
                'order_no' => $order->order_no,
                'from'     => $order->status,
                'to'       => self::STATUS_CANCELLED_BY_DEALER,
            ]
        );

        return ['message' => '订单已撤销。'];
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

    public static function get_items($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d", $order_id));
    }

    public static function is_shipment_link_enabled() {
        return AEGIS_System::is_module_enabled('orders') && (bool) get_option(AEGIS_System::ORDER_SHIPMENT_LINK_OPTION, false);
    }

    protected static function query_portal_orders($args, &$total = 0) {
        global $wpdb;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        $where = ['o.created_at BETWEEN %s AND %s'];
        $params = [$args['start'], $args['end']];

        if (!empty($args['dealer_id'])) {
            $where[] = 'o.dealer_id = %d';
            $params[] = (int) $args['dealer_id'];
        }
        if (!empty($args['order_no'])) {
            $where[] = 'o.order_no LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['order_no']) . '%';
        }

        $where_sql = implode(' AND ', $where);
        $per_page = $args['per_page'];
        $paged = $args['paged'];
        $offset = ($paged - 1) * $per_page;

        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$order_table} o WHERE {$where_sql}", $params));

        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*, d.dealer_name, COALESCE(SUM(oi.qty),0) AS total_qty, COUNT(DISTINCT oi.ean) AS sku_count FROM {$order_table} o \
LEFT JOIN {$item_table} oi ON oi.order_id = o.id LEFT JOIN {$dealer_table} d ON o.dealer_id = d.id WHERE {$where_sql} GROUP BY o.id ORDER BY o.created_at DESC LIMIT %d OFFSET %d",
                $params
            )
        );
    }

    protected static function list_active_skus() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_results($wpdb->prepare("SELECT ean, product_name FROM {$table} WHERE status = %s ORDER BY product_name ASC", AEGIS_SKU::STATUS_ACTIVE));
    }

    protected static function build_price_map($dealer, $skus) {
        $map = [];
        foreach ($skus as $sku) {
            $quote = AEGIS_Pricing::get_quote((int) $dealer->id, $sku->ean);
            if (is_wp_error($quote)) {
                continue;
            }
            $map[$sku->ean] = [
                'unit_price' => $quote['unit_price'],
                'price_source' => $quote['price_source'],
                'price_level_used' => $quote['price_level_used'],
                'label' => $quote['unit_price'] ? ('¥' . number_format((float) $quote['unit_price'], 2) . ' · ' . ('override' === $quote['price_source'] ? '覆盖价' : '等级价')) : '无价',
            ];
        }
        return $map;
    }

    protected static function get_current_dealer() {
        return AEGIS_Dealer::get_dealer_for_user();
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
            } elseif ('update_order' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ACCESS_ROOT,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'order_id', 'note', 'order_item_ean', 'order_item_qty', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                $order = $order_id ? self::get_order($order_id) : null;
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif (!$is_dealer || !$order) {
                    $errors[] = '无效的订单。';
                } else {
                    $items = self::parse_item_post($_POST);
                    $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
                    $result = self::update_portal_order($dealer, $order, $items, $note);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                        $view_id = (int) $order_id;
                    }
                }
            } elseif ('cancel_order' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ACCESS_ROOT,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'order_id', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                $order = $order_id ? self::get_order($order_id) : null;
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif (!$is_dealer || !$order) {
                    $errors[] = '无效的订单。';
                } else {
                    $result = self::cancel_portal_order($dealer, $order);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                        $view_id = (int) $order_id;
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
        $price_map = ($is_dealer && $dealer && $skus) ? self::build_price_map($dealer, $skus) : [];

        $context = [
            'base_url'       => $base_url,
            'messages'       => $messages,
            'errors'         => $errors,
            'orders'         => $orders,
            'order'          => $order,
            'items'          => $items,
            'filters'        => [
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'order_no'    => $search_no,
                'per_page'    => $per_page,
                'per_options' => $per_page_options,
                'paged'       => $paged,
                'total'       => $total,
                'total_pages' => $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1,
            ],
            'skus'          => $skus,
            'dealer'        => $dealer,
            'dealer_blocked'=> $dealer_blocked,
            'price_map'     => $price_map,
            'role_flags'    => [
                'is_dealer'      => $is_dealer,
                'can_manage'     => $can_manage,
                'staff_readonly' => $is_staff_readonly,
            ],
        ];

        return AEGIS_Portal::render_portal_template('orders', $context);
    }

    public static function render_admin_page() {
        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a2">订单</h1>';
        echo '<p class="aegis-t-a6">订单创建与经销商编辑请在前台 Portal 使用 ?m=orders。</p>';
        echo '</div>';
    }
}
