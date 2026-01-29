<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Orders {
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_INITIAL_REVIEW = 'pending_initial_review';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_CANCELLED_BY_DEALER = 'cancelled_by_dealer';
    const STATUS_PENDING_DEALER_CONFIRM = 'pending_dealer_confirm';
    const STATUS_PENDING_HQ_PAYMENT_REVIEW = 'pending_hq_payment_review';
    const STATUS_APPROVED_PENDING_FULFILLMENT = 'approved_pending_fulfillment';
    const STATUS_FULFILLED = 'shipped';
    const STATUS_VOIDED_BY_HQ = 'voided_by_hq';

    const PAYMENT_STATUS_NONE = 'none';
    const PAYMENT_STATUS_SUBMITTED = 'submitted';
    const PAYMENT_STATUS_APPROVED = 'approved';
    const PAYMENT_STATUS_REJECTED = 'rejected';
    const PAYMENT_STATUS_NEED_MORE = 'need_more';
    protected static $portal_query_error = '';

    /**
     * HQ 逐级退回上一状态映射（仅规则，不触发任何流转）。
     *
     * 映射规则（基于当前系统真实状态值）：
     * - approved_pending_fulfillment -> pending_hq_payment_review
     * - pending_hq_payment_review -> pending_dealer_confirm
     * - pending_dealer_confirm -> pending_initial_review
     * - pending_initial_review -> null（系统未定义“已创建/created”订单状态）
     *
     * 说明：已撤销/已作废等终态不允许退回，因此未纳入映射表。
     *
     * @return array<string, string|null>
     */
    private static function get_prev_status_map(): array {
        return [
            self::STATUS_APPROVED_PENDING_FULFILLMENT => self::STATUS_PENDING_HQ_PAYMENT_REVIEW,
            self::STATUS_PENDING_HQ_PAYMENT_REVIEW => self::STATUS_PENDING_DEALER_CONFIRM,
            self::STATUS_PENDING_DEALER_CONFIRM => self::STATUS_PENDING_INITIAL_REVIEW,
            self::STATUS_PENDING_INITIAL_REVIEW => null,
            self::STATUS_FULFILLED => self::STATUS_APPROVED_PENDING_FULFILLMENT,
        ];
    }

    /**
     * 获取订单上一状态（用于 HQ 逐级退回）。
     *
     * @param string $status
     * @return string|null
     */
    public static function get_prev_status(string $status): ?string {
        $map = self::get_prev_status_map();
        if (array_key_exists($status, $map)) {
            return $map[$status];
        }
        return null;
    }

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

    protected static function get_order_meta($order): array {
        if (!$order || empty($order->meta)) {
            return [];
        }
        $decoded = json_decode($order->meta, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected static function build_order_meta($order, $totals, array $extra_meta = []): array {
        $meta = self::get_order_meta($order);
        $meta['item_count'] = $totals['item_count'];
        $meta['total_qty'] = $totals['total_qty'];
        foreach ($extra_meta as $key => $value) {
            $meta[$key] = $value;
        }
        return $meta;
    }

    protected static function get_cancel_request($order): ?array {
        $meta = self::get_order_meta($order);
        if (!empty($meta['cancel']) && is_array($meta['cancel'])) {
            return $meta['cancel'];
        }
        return null;
    }

    protected static function update_cancel_request($order_id, array $cancel) {
        global $wpdb;
        $order = self::get_order((int) $order_id);
        if (!$order) {
            return false;
        }
        $meta = self::get_order_meta($order);
        $current = [];
        if (!empty($meta['cancel']) && is_array($meta['cancel'])) {
            $current = $meta['cancel'];
        }
        $meta['cancel'] = array_merge($current, $cancel);
        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        return false !== $wpdb->update(
            $table,
            ['meta' => wp_json_encode($meta)],
            ['id' => (int) $order_id],
            ['%s'],
            ['%d']
        );
    }

    public static function can_force_cancel($user = null): bool {
        $user = $user ?: wp_get_current_user();
        return current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM)
            || current_user_can(AEGIS_System::CAP_ACCESS_ROOT)
            || current_user_can(AEGIS_System::CAP_ORDERS_MANAGE_ALL)
            || AEGIS_System_Roles::is_hq_admin($user);
    }

    public static function can_approve_cancel($order, $user = null): bool {
        if (!$order) {
            return false;
        }
        $terminal_statuses = [self::STATUS_CANCELLED, self::STATUS_CANCELLED_BY_DEALER, self::STATUS_VOIDED_BY_HQ, self::STATUS_FULFILLED];
        if (in_array($order->status, $terminal_statuses, true)) {
            return false;
        }
        $user = $user ?: wp_get_current_user();
        $roles = (array) ($user ? $user->roles : []);
        $is_hq = self::can_force_cancel($user);
        if (in_array($order->status, [self::STATUS_PENDING_INITIAL_REVIEW, self::STATUS_PENDING_DEALER_CONFIRM], true)) {
            if ($is_hq) {
                return true;
            }
            if (in_array('aegis_sales', $roles, true)) {
                $sales_user_id = self::get_order_sales_user_id($order);
                return $sales_user_id > 0 && (int) $sales_user_id === (int) $user->ID;
            }
            return false;
        }
        if (self::STATUS_PENDING_HQ_PAYMENT_REVIEW === $order->status) {
            return $is_hq;
        }
        if (self::STATUS_APPROVED_PENDING_FULFILLMENT === $order->status) {
            return $is_hq || AEGIS_System_Roles::user_can_use_warehouse();
        }
        return false;
    }

    protected static function get_order_sales_user_id($order): int {
        global $wpdb;
        if (!$order) {
            return 0;
        }
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT sales_user_id FROM {$dealer_table} WHERE id = %d",
                (int) $order->dealer_id
            )
        );
    }

    protected static function get_processing_lock($order): ?array {
        $meta = self::get_order_meta($order);
        if (!empty($meta['processing_lock']) && is_array($meta['processing_lock'])) {
            return $meta['processing_lock'];
        }
        return null;
    }

    protected static function set_processing_lock($order_id, $user_id, $reason = 'review') {
        global $wpdb;
        $order = self::get_order((int) $order_id);
        if (!$order) {
            return false;
        }
        $meta = self::get_order_meta($order);
        $meta['processing_lock'] = [
            'locked' => true,
            'by'     => (int) $user_id,
            'at'     => current_time('mysql'),
            'reason' => $reason,
        ];
        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        return false !== $wpdb->update(
            $table,
            [
                'meta' => wp_json_encode($meta),
            ],
            ['id' => (int) $order_id],
            ['%s'],
            ['%d']
        );
    }

    protected static function clear_processing_lock($order_id) {
        global $wpdb;
        $order = self::get_order((int) $order_id);
        if (!$order) {
            return false;
        }
        $meta = self::get_order_meta($order);
        if (isset($meta['processing_lock'])) {
            unset($meta['processing_lock']);
        }
        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        return false !== $wpdb->update(
            $table,
            [
                'meta' => wp_json_encode($meta),
            ],
            ['id' => (int) $order_id],
            ['%s'],
            ['%d']
        );
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

    protected static function prepare_review_items($order_items, $posted_items) {
        if (empty($posted_items)) {
            return new WP_Error('no_items', '初审后至少需保留一条 SKU 行。');
        }

        $existing = [];
        foreach ($order_items as $item) {
            $existing[$item->ean] = $item;
        }

        $prepared = [];
        $changes = [
            'removed' => [],
            'qty'     => [],
        ];
        $seen = [];

        foreach ($posted_items as $item) {
            $ean = $item['ean'];
            $seen[$ean] = true;
            $qty = (int) $item['qty'];
            if (!isset($existing[$ean])) {
                return new WP_Error('invalid_item', '初审不允许新增 SKU：' . esc_html($ean));
            }
            $orig_qty = (int) $existing[$ean]->qty;
            if ($qty < 0) {
                return new WP_Error('invalid_qty', '数量不能小于0。');
            }
            if ($qty > $orig_qty) {
                return new WP_Error('qty_increase_blocked', '初审阶段仅允许删减数量：' . esc_html($ean));
            }
            if (0 === $qty) {
                $changes['removed'][] = $ean;
                continue;
            }

            $prepared[] = [
                'ean'                   => $ean,
                'qty'                   => $qty,
                'product_name_snapshot' => $existing[$ean]->product_name_snapshot,
                'unit_price_snapshot'   => $existing[$ean]->unit_price_snapshot,
                'price_source'          => $existing[$ean]->price_source,
                'price_level_snapshot'  => $existing[$ean]->price_level_snapshot,
            ];

            if ($qty < $orig_qty) {
                $changes['qty'][] = [
                    'ean'     => $ean,
                    'from'    => $orig_qty,
                    'to'      => $qty,
                ];
            }
        }

        foreach ($existing as $ean => $item) {
            if (empty($seen[$ean])) {
                $changes['removed'][] = $ean;
            }
        }

        if (empty($prepared)) {
            return new WP_Error('no_items_after_review', '初审后需至少保留一条明细。');
        }

        return [
            'items'   => $prepared,
            'changes' => $changes,
        ];
    }

    protected static function create_portal_order($dealer, $items, $note = '', $status = self::STATUS_DRAFT, $success_message = '') {
        global $wpdb;
        $priced_items = self::prepare_priced_items($dealer, $items);
        if (is_wp_error($priced_items)) {
            return $priced_items;
        }

        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $now = current_time('mysql');
        $order_no = self::generate_order_no();
        $totals = self::calculate_totals($priced_items);

        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $now = current_time('mysql');
        $order_no = self::generate_order_no();
        $totals = self::calculate_totals($priced_items);

        $inserted = $wpdb->insert(
            $order_table,
            [
                'order_no'               => $order_no,
                'dealer_id'              => (int) $dealer->id,
                'status'                 => $status,
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
                'meta'                   => wp_json_encode(self::build_order_meta(null, $totals)),
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

        if ('' === $success_message) {
            $success_message = '下单成功，订单号 ' . $order_no;
        }

        return [
            'order_id' => $order_id,
            'message'  => $success_message,
        ];
    }

    protected static function update_portal_order($dealer, $order, $items, $note = '') {
        global $wpdb;
        if ((int) $order->dealer_id !== (int) $dealer->id) {
            AEGIS_Access_Audit::record_event(
                'ACCESS_DENIED',
                'FAIL',
                [
                    'order_id'        => (int) $order->id,
                    'order_no'        => $order->order_no,
                    'reason_code'     => 'dealer_mismatch',
                    'dealer_id'       => (int) $order->dealer_id,
                    'actor_dealer_id' => (int) $dealer->id,
                ]
            );
            return new WP_Error('forbidden', '权限不足，订单不可编辑。');
        }
        if (self::STATUS_DRAFT !== $order->status) {
            AEGIS_Access_Audit::record_event(
                'ACCESS_DENIED',
                'FAIL',
                [
                    'order_id'    => (int) $order->id,
                    'order_no'    => $order->order_no,
                    'reason_code' => 'bad_status',
                    'status'      => $order->status,
                ]
            );
            return new WP_Error('bad_status', '权限不足，订单不可编辑。');
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
                'meta'         => wp_json_encode(self::build_order_meta($order, $totals)),
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
        if (self::STATUS_DRAFT !== $order->status) {
            return new WP_Error('bad_status', '仅草稿订单可撤销。');
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

        AEGIS_Access_Audit::log(
            AEGIS_System::ACTION_ORDER_CANCEL_BY_DEALER,
            [
                'result'      => 'SUCCESS',
                'entity_type' => 'order',
                'entity_id'   => $order->order_no,
                'meta'        => [
                    'order_id' => (int) $order->id,
                    'from'     => $order->status,
                    'to'       => self::STATUS_CANCELLED_BY_DEALER,
                ],
            ]
        );

        return ['message' => '订单已撤销。'];
    }

    protected static function apply_initial_review_edits($order, $items, $review_note = '', $status = null, $mark_reviewed = false, $status_error_message = '') {
        global $wpdb;
        if (self::STATUS_PENDING_INITIAL_REVIEW !== $order->status) {
            $message = $status_error_message ?: '仅待初审订单可编辑初审内容。';
            return new WP_Error('bad_status', $message);
        }

        $existing_items = self::get_items($order->id);
        $prepared = self::prepare_review_items($existing_items, $items);
        if (is_wp_error($prepared)) {
            return $prepared;
        }

        $now = current_time('mysql');
        $totals = self::calculate_totals($prepared['items']);
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $update_data = [
            'updated_at'   => $now,
            'review_note'  => $review_note,
            'total_amount' => $totals['total_amount'],
            'meta'         => wp_json_encode(self::build_order_meta($order, $totals)),
        ];
        $update_formats = ['%s', '%s', '%f', '%s'];
        if (null !== $status) {
            $update_data['status'] = $status;
            $update_formats[] = '%s';
        }
        if ($mark_reviewed) {
            $update_data['reviewed_by'] = get_current_user_id();
            $update_formats[] = '%d';
            $update_data['reviewed_at'] = $now;
            $update_formats[] = '%s';
        }
        $updated = $wpdb->update(
            $order_table,
            $update_data,
            ['id' => (int) $order->id],
            $update_formats,
            ['%d']
        );

        if (false === $updated) {
            return new WP_Error('update_failed', '初审更新失败，请稍后再试。');
        }

        self::persist_items((int) $order->id, $prepared['items'], $now);

        return [
            'items'   => $prepared['items'],
            'changes' => $prepared['changes'],
            'totals'  => $totals,
            'time'    => $now,
        ];
    }

    protected static function save_initial_review_draft($order, $items, $review_note = '') {
        if (self::STATUS_PENDING_INITIAL_REVIEW !== $order->status) {
            return new WP_Error('bad_status', '仅待初审订单可保存草稿。');
        }
        $result = self::apply_initial_review_edits(
            $order,
            $items,
            $review_note,
            null,
            false,
            '仅待初审订单可保存草稿。'
        );
        if (is_wp_error($result)) {
            return $result;
        }
        self::set_processing_lock($order->id, get_current_user_id(), 'review');
        return ['message' => '已保存草稿'];
    }

    protected static function review_order_by_hq($order, $items, $review_note = '') {
        $result = self::apply_initial_review_edits(
            $order,
            $items,
            $review_note,
            self::STATUS_PENDING_DEALER_CONFIRM,
            true,
            '仅待初审订单可审核。'
        );
        if (is_wp_error($result)) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_ORDER_INITIAL_REVIEW,
                'FAIL',
                [
                    'order_id' => (int) $order->id,
                    'order_no' => $order->order_no,
                    'reason'   => $result->get_error_message(),
                ]
            );
            return $result;
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_INITIAL_REVIEW,
            'SUCCESS',
            [
                'order_id' => (int) $order->id,
                'order_no' => $order->order_no,
                'removed'  => $result['changes']['removed'],
                'qty'      => $result['changes']['qty'],
                'status_to' => self::STATUS_PENDING_DEALER_CONFIRM,
            ]
        );

        if (!empty($result['changes']['removed']) || !empty($result['changes']['qty'])) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_ORDER_INITIAL_REVIEW_EDIT,
                'SUCCESS',
                [
                    'order_id' => (int) $order->id,
                    'order_no' => $order->order_no,
                    'removed'  => $result['changes']['removed'],
                    'qty'      => $result['changes']['qty'],
                ]
            );
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_INITIAL_REVIEW_PASS,
            'SUCCESS',
            [
                'order_id'  => (int) $order->id,
                'order_no'  => $order->order_no,
                'dealer_id' => (int) $order->dealer_id,
                'status_to' => self::STATUS_PENDING_DEALER_CONFIRM,
            ]
        );
        self::clear_processing_lock($order->id);

        return ['message' => '初审已通过，等待经销商确认。'];
    }

    protected static function void_order_by_hq($order, $reason = '') {
        global $wpdb;
        if (!in_array($order->status, [self::STATUS_PENDING_INITIAL_REVIEW, self::STATUS_PENDING_DEALER_CONFIRM, self::STATUS_PENDING_HQ_PAYMENT_REVIEW], true)) {
            return new WP_Error('bad_status', '仅待初审/待确认/待审核订单可作废。');
        }

        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $updated = $wpdb->update(
            $table,
            [
                'status'      => self::STATUS_VOIDED_BY_HQ,
                'updated_at'  => current_time('mysql'),
                'voided_at'   => current_time('mysql'),
                'voided_by'   => get_current_user_id(),
                'void_reason' => $reason,
            ],
            ['id' => (int) $order->id],
            ['%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );

        if (false === $updated) {
            return new WP_Error('void_failed', '订单作废失败，请稍后再试。');
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_VOID_BY_HQ,
            'SUCCESS',
            [
                'order_id'  => (int) $order->id,
                'order_no'  => $order->order_no,
                'dealer_id' => (int) $order->dealer_id,
                'status'    => $order->status,
                'reason'    => $reason,
            ]
        );

        return ['message' => '订单已作废。'];
    }

    protected static function rollback_order_one_step($order, $reason) {
        global $wpdb;
        if (in_array($order->status, [self::STATUS_CANCELLED, self::STATUS_CANCELLED_BY_DEALER, self::STATUS_VOIDED_BY_HQ], true)) {
            AEGIS_Access_Audit::record_event(
                'ORDER_ROLLBACK_STEP',
                'FAIL',
                [
                    'order_id'    => (int) $order->id,
                    'from'        => $order->status,
                    'reason_code' => 'terminal_state',
                ]
            );
            return new WP_Error('bad_status', '该状态不可退回。');
        }

        $prev_status = self::get_prev_status($order->status);
        if (null === $prev_status) {
            AEGIS_Access_Audit::record_event(
                'ORDER_ROLLBACK_STEP',
                'FAIL',
                [
                    'order_id'    => (int) $order->id,
                    'from'        => $order->status,
                    'reason_code' => 'no_prev_status',
                ]
            );
            return new WP_Error('bad_status', '该状态不可退回。');
        }

        $meta = [];
        if (!empty($order->meta)) {
            $decoded = json_decode($order->meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $now = current_time('mysql');
        $meta['rollback_reason'] = $reason;
        $meta['rollback_by'] = get_current_user_id();
        $meta['rollback_at'] = $now;

        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $updated = $wpdb->update(
            $table,
            [
                'status'     => $prev_status,
                'updated_at' => $now,
                'meta'       => wp_json_encode($meta),
            ],
            ['id' => (int) $order->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if (false === $updated) {
            AEGIS_Access_Audit::record_event(
                'ORDER_ROLLBACK_STEP',
                'FAIL',
                [
                    'order_id'    => (int) $order->id,
                    'from'        => $order->status,
                    'to'          => $prev_status,
                    'reason_code' => 'update_failed',
                ]
            );
            return new WP_Error('rollback_failed', '订单退回失败，请稍后再试。');
        }

        AEGIS_Access_Audit::record_event(
            'ORDER_ROLLBACK_STEP',
            'SUCCESS',
            [
                'order_id' => (int) $order->id,
                'from'     => $order->status,
                'to'       => $prev_status,
                'reason'   => self::truncate_audit_reason($reason),
            ]
        );

        return [
            'from'    => $order->status,
            'to'      => $prev_status,
            'message' => '已退回：' . $order->status . ' → ' . $prev_status,
        ];
    }

    protected static function truncate_audit_reason($reason) {
        $reason = trim((string) $reason);
        if ($reason === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($reason, 0, 200);
        }
        return substr($reason, 0, 200);
    }

    public static function current_user_can_view_order($order) {
        $roles = AEGIS_System_Roles::get_user_roles();
        $is_sales = in_array('aegis_sales', $roles, true);
        $can_manage_system = AEGIS_System_Roles::user_can_manage_system();
        $can_manage_warehouse = AEGIS_System_Roles::user_can_manage_warehouse();
        $can_manage_all = current_user_can(AEGIS_System::CAP_ORDERS_MANAGE_ALL);

        if ($is_sales && !$can_manage_system && !$can_manage_warehouse && !$can_manage_all) {
            global $wpdb;
            $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
            $order_sales_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT sales_user_id FROM {$dealer_table} WHERE id = %d",
                    (int) $order->dealer_id
                )
            );

            return $order_sales_id === get_current_user_id();
        }

        if (AEGIS_System_Roles::user_can_manage_warehouse()
            || current_user_can(AEGIS_System::CAP_ORDERS_VIEW_ALL)
            || current_user_can(AEGIS_System::CAP_ORDERS_INITIAL_REVIEW)
            || current_user_can(AEGIS_System::CAP_ORDERS_PAYMENT_REVIEW)
            || current_user_can(AEGIS_System::CAP_ORDERS_MANAGE_ALL)
        ) {
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

    protected static function get_payment_record($order_id) {
        global $wpdb;
        $payment_table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;
        $media_table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.*, m.file_path, m.mime FROM {$payment_table} p LEFT JOIN {$media_table} m ON p.media_id = m.id WHERE p.order_id = %d",
                (int) $order_id
            )
        );
    }

    protected static function guard_dealer_action($dealer_state, $order, $allowed_statuses, $message, $audit_event = null, $audit_meta = []) {
        if (!$dealer_state || empty($dealer_state['allowed'])) {
            if ($audit_event) {
                AEGIS_Access_Audit::record_event(
                    $audit_event,
                    'FAIL',
                    array_merge(
                        [
                            'order_id'    => (int) $order->id,
                            'order_no'    => $order->order_no,
                            'status'      => $order->status,
                            'reason_code' => 'dealer_blocked',
                        ],
                        $audit_meta
                    )
                );
            }
            return new WP_Error('dealer_blocked', '经销商账号已停用或授权到期，无法操作订单。');
        }

        $dealer = $dealer_state['dealer'];
        if (!$dealer) {
            if ($audit_event) {
                AEGIS_Access_Audit::record_event(
                    $audit_event,
                    'FAIL',
                    array_merge(
                        [
                            'order_id'    => (int) $order->id,
                            'order_no'    => $order->order_no,
                            'status'      => $order->status,
                            'reason_code' => 'dealer_missing',
                        ],
                        $audit_meta
                    )
                );
            }
            return new WP_Error('dealer_missing', '经销商未绑定，无法操作订单。');
        }

        if ((int) $order->dealer_id !== (int) $dealer->id) {
            if ($audit_event) {
                AEGIS_Access_Audit::record_event(
                    $audit_event,
                    'FAIL',
                    array_merge(
                        [
                            'order_id'    => (int) $order->id,
                            'order_no'    => $order->order_no,
                            'status'      => $order->status,
                            'reason_code' => 'forbidden',
                        ],
                        $audit_meta
                    )
                );
            }
            return new WP_Error('forbidden', '无权操作该订单。');
        }

        if (!in_array($order->status, (array) $allowed_statuses, true)) {
            if ($audit_event) {
                AEGIS_Access_Audit::record_event(
                    $audit_event,
                    'FAIL',
                    array_merge(
                        [
                            'order_id'    => (int) $order->id,
                            'order_no'    => $order->order_no,
                            'status'      => $order->status,
                            'reason_code' => 'bad_status',
                        ],
                        $audit_meta
                    )
                );
            }
            return new WP_Error('bad_status', $message);
        }

        return $dealer;
    }

    public static function get_media_gateway_url($media_id) {
        return AEGIS_Assets_Media::get_media_gateway_url($media_id);
    }

    protected static function upload_payment_proof($dealer_state, $order) {
        if (!AEGIS_System::is_module_enabled('assets_media')) {
            return new WP_Error('media_disabled', '资产与媒体模块未启用，无法上传付款凭证。');
        }

        $dealer = self::guard_dealer_action(
            $dealer_state,
            $order,
            [self::STATUS_PENDING_DEALER_CONFIRM],
            '仅待确认订单可上传付款凭证。',
            AEGIS_System::ACTION_PAYMENT_PROOF_UPLOAD
        );
        if (is_wp_error($dealer)) {
            return $dealer;
        }

        if (!isset($_FILES['payment_file']) || empty($_FILES['payment_file']['name'])) {
            return new WP_Error('missing_file', '请先选择付款凭证文件。');
        }

        $upload = AEGIS_Assets_Media::handle_admin_upload(
            $_FILES['payment_file'],
            [
                'bucket'                => 'payments',
                'owner_type'            => 'order_payment_proof',
                'owner_id'              => (int) $order->id,
                'kind'                  => 'payment_proof',
                'visibility'            => AEGIS_Assets_Media::VISIBILITY_SENSITIVE,
                'allow_dealer_payment'  => true,
                'permission_callback'   => function () use ($dealer_state, $order) {
                    return $dealer_state && !empty($dealer_state['allowed']) && isset($dealer_state['dealer']) && (int) $dealer_state['dealer']->id === (int) $order->dealer_id && self::STATUS_PENDING_DEALER_CONFIRM === $order->status;
                },
            ]
        );

        if (is_wp_error($upload)) {
            return $upload;
        }

        global $wpdb;
        $payment_table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;
        $existing = self::get_payment_record($order->id);
        $now = current_time('mysql');

        if ($existing) {
            $wpdb->update(
                $payment_table,
                [
                    'media_id'     => (int) $upload['id'],
                    'status'       => self::PAYMENT_STATUS_SUBMITTED,
                    'submitted_at' => $now,
                    'submitted_by' => get_current_user_id(),
                    'reviewed_at'  => null,
                    'reviewed_by'  => null,
                    'review_note'  => null,
                    'updated_at'   => $now,
                ],
                ['id' => (int) $existing->id],
                ['%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s'],
                ['%d']
            );
            $payment_id = (int) $existing->id;
        } else {
            $wpdb->insert(
                $payment_table,
                [
                    'order_id'    => (int) $order->id,
                    'dealer_id'   => (int) $dealer->id,
                    'media_id'    => (int) $upload['id'],
                    'status'      => self::PAYMENT_STATUS_SUBMITTED,
                    'submitted_at' => $now,
                    'submitted_by' => get_current_user_id(),
                    'created_by'  => get_current_user_id(),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                ['%d', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s']
            );
            $payment_id = (int) $wpdb->insert_id;
        }

        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $wpdb->update(
            $order_table,
            [
                'status'     => self::STATUS_PENDING_HQ_PAYMENT_REVIEW,
                'updated_at' => $now,
            ],
            ['id' => (int) $order->id],
            ['%s', '%s'],
            ['%d']
        );

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_PAYMENT_PROOF_UPLOAD,
            'SUCCESS',
            [
                'order_id'   => (int) $order->id,
                'order_no'   => $order->order_no,
                'payment_id' => $payment_id,
                'media_id'   => (int) $upload['id'],
            ]
        );

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_STATUS_CHANGE,
            'SUCCESS',
            [
                'order_id' => (int) $order->id,
                'order_no' => $order->order_no,
                'from'     => $order->status,
                'to'       => self::STATUS_PENDING_HQ_PAYMENT_REVIEW,
            ]
        );

        return ['message' => '已提交付款凭证，等待审核。'];
    }

    protected static function submit_payment_confirmation($dealer_state, $order) {
        $dealer = self::guard_dealer_action($dealer_state, $order, [self::STATUS_PENDING_DEALER_CONFIRM], '仅待确认订单可提交确认。');
        if (is_wp_error($dealer)) {
            return $dealer;
        }

        $payment = self::get_payment_record($order->id);
        if (!$payment || !$payment->media_id) {
            return new WP_Error('missing_payment', '请先上传付款凭证后再提交确认。');
        }

        global $wpdb;
        $payment_table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $now = current_time('mysql');

        $wpdb->update(
            $payment_table,
            [
                'status'       => self::PAYMENT_STATUS_SUBMITTED,
                'submitted_at' => $now,
                'submitted_by' => get_current_user_id(),
                'updated_at'   => $now,
            ],
            ['order_id' => (int) $order->id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        $wpdb->update(
            $order_table,
            [
                'status'     => self::STATUS_PENDING_HQ_PAYMENT_REVIEW,
                'updated_at' => $now,
            ],
            ['id' => (int) $order->id],
            ['%s', '%s'],
            ['%d']
        );

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_PAYMENT_CONFIRM_SUBMIT,
            'SUCCESS',
            [
                'order_id'   => (int) $order->id,
                'order_no'   => $order->order_no,
                'dealer_id'  => (int) $order->dealer_id,
                'payment_id' => isset($payment->id) ? (int) $payment->id : null,
            ]
        );

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_STATUS_CHANGE,
            'SUCCESS',
            [
                'order_id' => (int) $order->id,
                'order_no' => $order->order_no,
                'from'     => $order->status,
                'to'       => self::STATUS_PENDING_HQ_PAYMENT_REVIEW,
            ]
        );

        return ['message' => '已提交确认，等待审核。'];
    }

    protected static function review_payment_by_hq($order, $decision, $note = '') {
        $audit_action = ('reject' === $decision) ? AEGIS_System::ACTION_PAYMENT_REVIEW_REJECT : AEGIS_System::ACTION_PAYMENT_REVIEW_APPROVE;
        if (self::STATUS_PENDING_HQ_PAYMENT_REVIEW !== $order->status) {
            AEGIS_Access_Audit::record_event(
                $audit_action,
                'FAIL',
                [
                    'order_id'    => (int) $order->id,
                    'order_no'    => $order->order_no,
                    'status'      => $order->status,
                    'reason_code' => 'bad_status',
                ]
            );
            return new WP_Error('bad_status', '仅待审核订单可进行付款审核。');
        }

        $payment = self::get_payment_record($order->id);
        if (!$payment || self::PAYMENT_STATUS_SUBMITTED !== $payment->status) {
            AEGIS_Access_Audit::record_event(
                $audit_action,
                'FAIL',
                [
                    'order_id'    => (int) $order->id,
                    'order_no'    => $order->order_no,
                    'status'      => $order->status,
                    'reason_code' => 'payment_missing',
                ]
            );
            return new WP_Error('payment_missing', '未找到可审核的付款凭证。');
        }

        $decision = in_array($decision, ['approve', 'reject'], true) ? $decision : '';
        if (!$decision) {
            return new WP_Error('invalid_decision', '请选择审核结果。');
        }

        if ('reject' === $decision && '' === trim((string) $note)) {
            return new WP_Error('missing_reason', '驳回必须填写原因。');
        }

        global $wpdb;
        $payment_table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $now = current_time('mysql');

        $payment_status = 'approve' === $decision ? self::PAYMENT_STATUS_APPROVED : self::PAYMENT_STATUS_REJECTED;
        $order_status = 'approve' === $decision ? self::STATUS_APPROVED_PENDING_FULFILLMENT : self::STATUS_PENDING_DEALER_CONFIRM;

        $wpdb->update(
            $payment_table,
            [
                'status'       => $payment_status,
                'review_note'  => ('reject' === $decision) ? $note : null,
                'reviewed_at'  => $now,
                'reviewed_by'  => get_current_user_id(),
                'updated_at'   => $now,
            ],
            ['order_id' => (int) $order->id],
            ['%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );

        $wpdb->update(
            $order_table,
            [
                'status'                    => $order_status,
                'payment_reviewed_at'       => $now,
                'payment_reviewed_by'       => get_current_user_id(),
                'ready_for_fulfillment_at'  => ('approve' === $decision) ? $now : null,
                'updated_at'                => $now,
            ],
            ['id' => (int) $order->id],
            ['%s', '%s', '%d', '%s', '%s'],
            ['%d']
        );

        $action = ('approve' === $decision) ? AEGIS_System::ACTION_PAYMENT_REVIEW_APPROVE : AEGIS_System::ACTION_PAYMENT_REVIEW_REJECT;
        AEGIS_Access_Audit::record_event(
            $action,
            'SUCCESS',
            [
                'order_id'   => (int) $order->id,
                'order_no'   => $order->order_no,
                'dealer_id'  => (int) $order->dealer_id,
                'decision'   => $decision,
                'note'       => $note,
            ]
        );

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_ORDER_STATUS_CHANGE,
            'SUCCESS',
            [
                'order_id' => (int) $order->id,
                'order_no' => $order->order_no,
                'from'     => $order->status,
                'to'       => $order_status,
            ]
        );

        if ('approve' === $decision) {
            return ['message' => '审核已通过，订单进入待出库。'];
        }

        return ['message' => '已驳回付款凭证，订单回到待确认。'];
    }

    public static function is_shipment_link_enabled() {
        return AEGIS_System::is_module_enabled('orders') && (bool) get_option(AEGIS_System::ORDER_SHIPMENT_LINK_OPTION, false);
    }

    protected static function query_portal_orders($args, &$total = 0) {
        global $wpdb;
        self::$portal_query_error = '';
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $payment_table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;
        $user = wp_get_current_user();
        $roles = (array) ($user ? $user->roles : []);

        if (in_array('aegis_dealer', $roles, true)) {
            $dealer_id = !empty($args['dealer_id']) ? (int) $args['dealer_id'] : 0;
            if ($dealer_id <= 0) {
                $dealer_id = self::resolve_portal_dealer_id($user);
                if ($dealer_id > 0) {
                    $args['dealer_id'] = $dealer_id;
                } else {
                    $total = 0;
                    return [];
                }
            }
        }

        $where = ['o.created_at >= %s', 'o.created_at < %s'];
        $params = [$args['start'], $args['end']];
        $sales_user_id = !empty($args['sales_user_id']) ? (int) $args['sales_user_id'] : 0;

        if (!empty($args['dealer_id'])) {
            $where[] = 'o.dealer_id = %d';
            $params[] = (int) $args['dealer_id'];
        }
        if ($sales_user_id > 0) {
            $where[] = 'd.sales_user_id = %d';
            $params[] = $sales_user_id;
        }
        if (!empty($args['order_no'])) {
            $where[] = 'o.order_no LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['order_no']) . '%';
        }
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            $placeholders = implode(',', array_fill(0, count($args['statuses']), '%s'));
            $where[] = "o.status IN ({$placeholders})";
            $params = array_merge($params, $args['statuses']);
        } elseif (!in_array('aegis_dealer', $roles, true)) {
            $where[] = 'o.status != %s';
            $params[] = self::STATUS_DRAFT;
        }

        $where_sql = implode(' AND ', $where);
        $per_page = $args['per_page'];
        $paged = $args['paged'];
        $offset = ($paged - 1) * $per_page;

        $total_sql = "SELECT COUNT(*) FROM {$order_table} o";
        if ($sales_user_id > 0) {
            $total_sql .= " LEFT JOIN {$dealer_table} d ON o.dealer_id = d.id";
        }
        $total_sql .= " WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($wpdb->prepare($total_sql, $params));

        $params[] = $per_page;
        $params[] = $offset;

        $include_payment = false;
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            $statuses = array_values($args['statuses']);
            if (1 === count($statuses) && self::STATUS_PENDING_HQ_PAYMENT_REVIEW === $statuses[0]) {
                $include_payment = true;
            }
        }

        $select_fields = "o.*, d.dealer_name, COALESCE(SUM(oi.qty),0) AS total_qty, COUNT(DISTINCT oi.ean) AS sku_count, COALESCE(SUM(oi.qty * oi.unit_price_snapshot),0) AS total_amount";
        if ($include_payment) {
            $select_fields .= ", pay.status AS payment_status, pay.submitted_at AS payment_submitted_at, pay.review_note AS payment_review_note";
        }

        $joins = "LEFT JOIN {$item_table} oi ON oi.order_id = o.id LEFT JOIN {$dealer_table} d ON o.dealer_id = d.id";
        if ($include_payment) {
            $joins .= " LEFT JOIN {$payment_table} pay ON pay.order_id = o.id";
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$select_fields} FROM {$order_table} o {$joins} WHERE {$where_sql} GROUP BY o.id ORDER BY o.created_at DESC LIMIT %d OFFSET %d",
                $params
            )
        );

        if (empty($results) && !empty($wpdb->last_error)) {
            self::$portal_query_error = $wpdb->last_error;
            $total = 0;
            return [];
        }

        return $results;
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
                'label' => $quote['unit_price'] ? ('¥' . number_format((float) $quote['unit_price'], 2)) : '无价',
            ];
        }
        return $map;
    }

    protected static function get_current_dealer() {
        return AEGIS_Dealer::get_dealer_for_user();
    }

    protected static function resolve_portal_dealer_id($user = null) {
        $dealer = AEGIS_Dealer::get_dealer_for_user($user);
        if ($dealer && isset($dealer->id)) {
            return (int) $dealer->id;
        }

        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aegis_dealers WHERE user_id = %d LIMIT 1",
                (int) get_current_user_id()
            )
        );
    }

    protected static function is_datetime_input($value) {
        return (bool) preg_match('/\d{2}:\d{2}(:\d{2})?/', (string) $value);
    }

    protected static function normalize_date_range($start_date, $end_date) {
        $start_ts = strtotime((string) $start_date);
        if (!$start_ts) {
            $start_ts = current_time('timestamp');
        }
        if (!self::is_datetime_input($start_date)) {
            $start_ts = strtotime(wp_date('Y-m-d 00:00:00', $start_ts));
        }

        $end_ts = strtotime((string) $end_date);
        if (!$end_ts) {
            $end_ts = current_time('timestamp');
        }

        if (self::is_datetime_input($end_date)) {
            $end_ts = strtotime(wp_date('Y-m-d H:i:s', $end_ts)) + 1;
        } else {
            $end_ts = strtotime(wp_date('Y-m-d 00:00:00', $end_ts)) + DAY_IN_SECONDS;
        }

        return [
            'start' => wp_date('Y-m-d H:i:s', $start_ts),
            'end'   => wp_date('Y-m-d H:i:s', $end_ts),
        ];
    }

    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('orders')) {
            return '<div class="aegis-t-a5">模块未启用。</div>';
        }

        $user = wp_get_current_user();
        $roles = (array) ($user ? $user->roles : []);
        $is_dealer = in_array('aegis_dealer', $roles, true);
        $is_sales = in_array('aegis_sales', $roles, true);
        $can_manage_system = AEGIS_System_Roles::user_can_manage_system();
        $can_manage_all = current_user_can(AEGIS_System::CAP_ORDERS_MANAGE_ALL);
        $can_initial_review = $can_manage_all || current_user_can(AEGIS_System::CAP_ORDERS_INITIAL_REVIEW);
        $can_payment_review = $can_manage_all || current_user_can(AEGIS_System::CAP_ORDERS_PAYMENT_REVIEW);
        $can_create_order = current_user_can(AEGIS_System::CAP_ORDERS_CREATE);
        $can_view_all = $can_manage_all
            || current_user_can(AEGIS_System::CAP_ORDERS_VIEW_ALL)
            || $can_initial_review
            || $can_payment_review
            || AEGIS_System_Roles::user_can_manage_warehouse();
        $can_manage_warehouse = AEGIS_System_Roles::user_can_manage_warehouse();
        $can_manage = $can_manage_warehouse || $can_manage_all;
        $is_staff_readonly = in_array('aegis_warehouse_staff', $roles, true) && !$can_manage;
        $sales_user_filter = 0;
        if ($is_sales && !$can_manage_system && !$can_manage_warehouse && !$can_manage_all) {
            $sales_user_filter = get_current_user_id();
        }

        $dealer_state = $is_dealer ? AEGIS_Dealer::evaluate_dealer_access($user) : null;
        $dealer = $dealer_state['dealer'] ?? null;
        $dealer_id = $dealer ? (int) $dealer->id : 0;
        if ($is_dealer && $dealer_id <= 0) {
            $dealer_id = self::resolve_portal_dealer_id($user);
        }
        $dealer_blocked = $is_dealer && (!$dealer_state || empty($dealer_state['allowed']));
        $dealer_missing = $is_dealer && $dealer_id <= 0;

        $allowed_views = ['list'];
        $default_view = 'list';
        if ($can_manage_all || ($can_initial_review && $can_payment_review)) {
            $allowed_views = ['review', 'payment_review', 'list'];
            $default_view = 'review';
        } elseif ($can_initial_review) {
            $allowed_views = ['review'];
            $default_view = 'review';
        } elseif ($can_payment_review) {
            $allowed_views = ['payment_review'];
            $default_view = 'payment_review';
        }

        $view_mode = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : $default_view;
        if (!in_array($view_mode, $allowed_views, true)) {
            $view_mode = $default_view;
        }

        $review_queue = $can_initial_review && 'review' === $view_mode;
        $payment_queue = $can_payment_review && 'payment_review' === $view_mode;
        $queue_view = $review_queue || $payment_queue;

        $messages = [];
        $errors = [];
        $view_id = 0;
        $auto_open_drawer = false;
        $cancel_form_error = '';
        if (isset($_GET['aegis_orders_message'])) {
            $messages[] = sanitize_text_field(wp_unslash($_GET['aegis_orders_message']));
        }
        if (isset($_GET['aegis_orders_error'])) {
            $errors[] = sanitize_text_field(wp_unslash($_GET['aegis_orders_error']));
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['order_action']) ? sanitize_key(wp_unslash($_POST['order_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            if (in_array($action, ['create_order', 'save_draft', 'submit_order'], true)) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ORDERS_CREATE,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'note', 'order_item_ean', 'order_item_qty', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                $is_submit_action = 'submit_order' === $action;
                $status = $is_submit_action ? self::STATUS_PENDING_INITIAL_REVIEW : self::STATUS_DRAFT;
                $success_message = $is_submit_action ? '已提交，等待初审。' : '已保存草稿。';
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif ($dealer_blocked || !$dealer_id) {
                    $errors[] = '当前经销商账号不可下单，请联系管理员。';
                } else {
                    $items = self::parse_item_post($_POST);
                    $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
                    $result = self::create_portal_order($dealer, $items, $note, $status, $success_message);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $redirect_url = add_query_arg(
                            [
                                'm' => 'orders',
                                'order_id' => (int) $result['order_id'],
                                'aegis_orders_message' => $result['message'],
                            ],
                            $portal_url
                        );
                        wp_safe_redirect($redirect_url);
                        exit;
                    }
                }
            } elseif ('update_order' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ORDERS_CREATE,
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
                } elseif (!$is_dealer || !$order || !$dealer) {
                    AEGIS_Access_Audit::record_event(
                        'ACCESS_DENIED',
                        'FAIL',
                        [
                            'order_id'    => $order_id,
                            'reason_code' => !$is_dealer ? 'not_dealer' : (!$order ? 'invalid_order' : 'dealer_missing'),
                        ]
                    );
                    $errors[] = '权限不足，订单不可编辑。';
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
            } elseif ('submit_draft' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ORDERS_CREATE,
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
                } elseif (!$is_dealer || !$order || !$dealer) {
                    $errors[] = '无效的订单。';
                } elseif ((int) $order->dealer_id !== (int) $dealer->id) {
                    $errors[] = '权限不足，无法提交草稿。';
                } elseif (self::STATUS_DRAFT !== $order->status) {
                    $errors[] = '当前订单无法提交。';
                } else {
                    $lock = self::get_processing_lock($order);
                    if (!empty($lock['locked'])) {
                        $errors[] = '订单处理中，无法提交。';
                    } else {
                        global $wpdb;
                        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
                        $now = current_time('mysql');
                        $updated = $wpdb->update(
                            $table,
                            [
                                'status'     => self::STATUS_PENDING_INITIAL_REVIEW,
                                'updated_at' => $now,
                            ],
                            ['id' => (int) $order->id],
                            ['%s', '%s'],
                            ['%d']
                        );
                        if (false === $updated) {
                            $errors[] = '提交失败，请稍后再试。';
                        } else {
                            AEGIS_Access_Audit::record_event(
                                'SUBMIT_DRAFT',
                                'SUCCESS',
                                [
                                    'order_id' => (int) $order->id,
                                    'order_no' => $order->order_no,
                                    'from'     => $order->status,
                                    'to'       => self::STATUS_PENDING_INITIAL_REVIEW,
                                ]
                            );
                            $messages[] = '已提交，等待初审。';
                            $view_id = (int) $order->id;
                        }
                    }
                }
            } elseif ('withdraw_order' === $action) {
                global $wpdb;
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ORDERS_CREATE,
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
                } elseif (!$is_dealer || !$order || !$dealer) {
                    $errors[] = '权限不足，无法撤回订单。';
                } elseif ((int) $order->dealer_id !== (int) $dealer->id) {
                    $errors[] = '权限不足，无法撤回订单。';
                } elseif (self::STATUS_PENDING_INITIAL_REVIEW !== $order->status) {
                    $errors[] = '当前订单无法撤回。';
                } else {
                    $lock = self::get_processing_lock($order);
                    if (!empty($lock['locked'])) {
                        $errors[] = '订单处理中，无法撤回提交。';
                    } else {
                        $meta = self::get_order_meta($order);
                        if (isset($meta['processing_lock'])) {
                            unset($meta['processing_lock']);
                        }
                        $table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
                        $updated = $wpdb->update(
                            $table,
                            [
                                'status'     => self::STATUS_DRAFT,
                                'updated_at' => current_time('mysql'),
                                'meta'       => wp_json_encode($meta),
                            ],
                            ['id' => (int) $order->id],
                            ['%s', '%s', '%s'],
                            ['%d']
                        );
                        if (false === $updated) {
                            $errors[] = '撤回失败，请稍后再试。';
                        } else {
                            $messages[] = '已撤回提交，订单恢复为草稿。';
                            $view_id = (int) $order->id;
                        }
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
            } elseif ('request_cancel' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ORDERS_CREATE,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'order_id', 'cancel_reason', '_wp_http_referer', 'aegis_orders_nonce'],
                    ]
                );
                $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                $order = $order_id ? self::get_order($order_id) : null;
                $reason = isset($_POST['cancel_reason']) ? sanitize_text_field(wp_unslash($_POST['cancel_reason'])) : '';
                $allowed_statuses = [self::STATUS_PENDING_INITIAL_REVIEW, self::STATUS_PENDING_DEALER_CONFIRM, self::STATUS_PENDING_HQ_PAYMENT_REVIEW, self::STATUS_APPROVED_PENDING_FULFILLMENT];
                $request_path = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
                if (!$validation['success']) {
                    $message = $validation['message'];
                    if (in_array($message, ['权限不足。', '安全校验失败，请重试。', '请求参数不被允许。'], true)) {
                        $message = '会话过期/校验失败，请刷新重试。';
                    }
                    $errors[] = $message;
                    $cancel_form_error = $message;
                    $view_id = (int) $order_id;
                    $auto_open_drawer = true;
                    AEGIS_Access_Audit::record_event('CANCEL_REQUEST', 'FAIL', [
                        'order_id'    => (int) $order_id,
                        'reason_code' => 'validation_failed',
                        'path'        => $request_path,
                        'actor_id'    => get_current_user_id(),
                    ]);
                } elseif (!$is_dealer || !$order || !$dealer) {
                    $errors[] = '无效的订单。';
                    $cancel_form_error = '无效的订单。';
                    $view_id = (int) $order_id;
                    $auto_open_drawer = true;
                    AEGIS_Access_Audit::record_event('CANCEL_REQUEST', 'FAIL', [
                        'order_id'    => (int) $order_id,
                        'reason_code' => 'invalid_order',
                        'path'        => $request_path,
                        'actor_id'    => get_current_user_id(),
                    ]);
                } elseif ((int) $order->dealer_id !== (int) $dealer->id) {
                    $errors[] = '权限不足，无法申请撤销。';
                    $cancel_form_error = '权限不足，无法申请撤销。';
                    $view_id = (int) $order_id;
                    $auto_open_drawer = true;
                    AEGIS_Access_Audit::record_event('CANCEL_REQUEST', 'FAIL', [
                        'order_id'    => (int) $order_id,
                        'order_no'    => $order->order_no,
                        'reason_code' => 'forbidden',
                        'path'        => $request_path,
                        'actor_id'    => get_current_user_id(),
                    ]);
                } elseif (!in_array($order->status, $allowed_statuses, true)) {
                    $errors[] = '当前状态不可撤销。';
                    $cancel_form_error = '当前状态不可撤销。';
                    $view_id = (int) $order->id;
                    $auto_open_drawer = true;
                    AEGIS_Access_Audit::record_event('CANCEL_REQUEST', 'FAIL', [
                        'order_id'    => (int) $order->id,
                        'order_no'    => $order->order_no,
                        'status'      => $order->status,
                        'reason_code' => 'invalid_status',
                        'path'        => $request_path,
                        'actor_id'    => get_current_user_id(),
                    ]);
                } elseif ('' === $reason) {
                    $errors[] = '撤销原因必填。';
                    $cancel_form_error = '撤销原因必填。';
                    $view_id = (int) $order->id;
                    $auto_open_drawer = true;
                    AEGIS_Access_Audit::record_event('CANCEL_REQUEST', 'FAIL', [
                        'order_id'    => (int) $order->id,
                        'order_no'    => $order->order_no,
                        'status'      => $order->status,
                        'reason_code' => 'missing_reason',
                        'path'        => $request_path,
                        'actor_id'    => get_current_user_id(),
                    ]);
                } else {
                    $existing = self::get_cancel_request($order);
                    if (!empty($existing['requested']) && ('pending' === ($existing['decision'] ?? ''))) {
                        $errors[] = '撤销申请已提交，请等待审批。';
                        $cancel_form_error = '撤销申请已提交，请等待审批。';
                        $view_id = (int) $order->id;
                        $auto_open_drawer = true;
                        AEGIS_Access_Audit::record_event('CANCEL_REQUEST', 'FAIL', [
                            'order_id'    => (int) $order->id,
                            'order_no'    => $order->order_no,
                            'status'      => $order->status,
                            'reason_code' => 'duplicate_request',
                            'path'        => $request_path,
                            'actor_id'    => get_current_user_id(),
                        ]);
                    } else {
                        $cancel = [
                            'requested'    => true,
                            'reason'       => $reason,
                            'requested_by' => get_current_user_id(),
                            'requested_at' => current_time('mysql'),
                            'decision'     => 'pending',
                        ];
                        $updated = self::update_cancel_request($order->id, $cancel);
                        if (!$updated) {
                            global $wpdb;
                            $errors[] = '提交失败，请稍后再试。';
                            $cancel_form_error = '提交失败，请稍后再试。';
                            $view_id = (int) $order->id;
                            $auto_open_drawer = true;
                            AEGIS_Access_Audit::record_event('CANCEL_REQUEST', 'FAIL', [
                                'order_id'    => (int) $order->id,
                                'order_no'    => $order->order_no,
                                'status'      => $order->status,
                                'reason_code' => 'db_error',
                                'path'        => $request_path,
                                'actor_id'    => get_current_user_id(),
                                'db_error'    => $wpdb->last_error,
                            ]);
                        } else {
                            AEGIS_Access_Audit::record_event(
                                'CANCEL_REQUEST',
                                'SUCCESS',
                                [
                                    'order_id'   => (int) $order->id,
                                    'order_no'   => $order->order_no,
                                    'status'     => $order->status,
                                    'reason'     => $reason,
                                    'path'       => $request_path,
                                    'actor_id'   => get_current_user_id(),
                                ]
                            );
                            $redirect_params = [
                                'm'                => 'orders',
                                'order_id'         => (int) $order->id,
                                'cancel_submitted' => 1,
                            ];
                            if ($queue_view) {
                                $redirect_params['view'] = $view_mode;
                            }
                            $redirect_url = add_query_arg($redirect_params, $portal_url);
                            wp_safe_redirect($redirect_url);
                            exit;
                        }
                    }
                }
            } elseif (in_array($action, ['save_review_draft', 'submit_initial_review', 'review_order', 'initial_review_submit'], true)) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ORDERS_INITIAL_REVIEW,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'order_id', 'review_note', 'order_item_ean', 'order_item_qty', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                $order = $order_id ? self::get_order($order_id) : null;
                $is_submit_action = in_array($action, ['submit_initial_review', 'review_order', 'initial_review_submit'], true);
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                    if ($is_submit_action) {
                        AEGIS_Access_Audit::record_event(
                            AEGIS_System::ACTION_ORDER_INITIAL_REVIEW,
                            'FAIL',
                            [
                                'order_id' => $order_id,
                                'reason'   => $validation['message'],
                            ]
                        );
                    }
                } elseif (!$can_initial_review || !$order) {
                    $errors[] = '无效的订单。';
                    if ($is_submit_action) {
                        AEGIS_Access_Audit::record_event(
                            AEGIS_System::ACTION_ORDER_INITIAL_REVIEW,
                            'FAIL',
                            [
                                'order_id' => $order_id,
                                'reason'   => 'invalid_order',
                            ]
                        );
                    }
                } else {
                    $sales_scope_ok = true;
                    global $wpdb;
                    if ($sales_user_filter > 0) {
                        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
                        $order_sales_id = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT sales_user_id FROM {$dealer_table} WHERE id = %d",
                                (int) $order->dealer_id
                            )
                        );
                        if ($order_sales_id !== $sales_user_filter) {
                            $sales_scope_ok = false;
                            $errors[] = '无权审核该订单。';
                            AEGIS_Access_Audit::record_event(
                                AEGIS_System::ACTION_ORDER_INITIAL_REVIEW,
                                'FAIL',
                                [
                                    'order_id' => $order_id,
                                    'reason'   => 'forbidden_sales_scope',
                                ]
                            );
                        }
                    }
                    if ($sales_scope_ok) {
                        $items = self::parse_item_post($_POST);
                        $note = isset($_POST['review_note']) ? sanitize_text_field(wp_unslash($_POST['review_note'])) : '';
                        $result = $is_submit_action
                            ? self::review_order_by_hq($order, $items, $note)
                            : self::save_initial_review_draft($order, $items, $note);
                        if (is_wp_error($result)) {
                            $errors[] = $result->get_error_message();
                            if ($is_submit_action) {
                                AEGIS_Access_Audit::record_event(
                                    AEGIS_System::ACTION_ORDER_INITIAL_REVIEW,
                                    'FAIL',
                                    [
                                        'order_id' => $order_id,
                                        'reason'   => $result->get_error_message(),
                                    ]
                                );
                            }
                        } else {
                            $messages[] = $result['message'];
                            $view_id = (int) $order_id;
                            if ($is_submit_action) {
                                $view_mode = 'list';
                            }
                        }
                    }
                }
            } elseif ('void_order' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ACCESS_ROOT,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'order_id', 'void_reason', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                $order = $order_id ? self::get_order($order_id) : null;
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif (!$can_initial_review || !$order) {
                    $errors[] = '无效的订单。';
                } else {
                    $reason = isset($_POST['void_reason']) ? sanitize_text_field(wp_unslash($_POST['void_reason'])) : '';
                    $result = self::void_order_by_hq($order, $reason);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                        $view_id = (int) $order_id;
                        $view_mode = 'list';
                    }
                }
            } elseif ('rollback_step' === $action) {
                $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                $nonce_action = 'aegis_orders_rollback_' . $order_id;
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ACCESS_ROOT,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => $nonce_action,
                        'whitelist'       => ['order_action', 'order_id', 'rollback_reason', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                $order = $order_id ? self::get_order($order_id) : null;
                $reason = isset($_POST['rollback_reason']) ? trim(sanitize_text_field(wp_unslash($_POST['rollback_reason']))) : '';
                $rollback_confirm = isset($_POST['rollback_confirm']) ? trim(sanitize_text_field(wp_unslash($_POST['rollback_confirm']))) : '';
                $is_hq = current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM)
                    || current_user_can(AEGIS_System::CAP_ACCESS_ROOT)
                    || AEGIS_System_Roles::is_hq_admin();

                $redirect_url = add_query_arg('m', 'orders', $portal_url);
                if ($order_id > 0) {
                    $redirect_url = add_query_arg(['view' => $order_id, 'order_id' => $order_id], $redirect_url);
                }

                if (!$validation['success']) {
                    AEGIS_Access_Audit::record_event(
                        'ORDER_ROLLBACK_STEP',
                        'FAIL',
                        [
                            'order_id'    => (int) $order_id,
                            'reason_code' => 'nonce_failed',
                        ]
                    );
                    $redirect_url = add_query_arg('aegis_orders_error', $validation['message'], $redirect_url);
                } elseif (!$is_hq || !$order) {
                    AEGIS_Access_Audit::record_event(
                        'ORDER_ROLLBACK_STEP',
                        'FAIL',
                        [
                            'order_id'    => (int) $order_id,
                            'from'        => $order ? $order->status : null,
                            'reason_code' => !$is_hq ? 'forbidden' : 'invalid_order',
                        ]
                    );
                    $redirect_url = add_query_arg('aegis_orders_error', '无权操作该订单。', $redirect_url);
                } elseif ('' === $reason) {
                    AEGIS_Access_Audit::record_event(
                        'ORDER_ROLLBACK_STEP',
                        'FAIL',
                        [
                            'order_id'    => (int) $order_id,
                            'from'        => $order ? $order->status : null,
                            'reason_code' => 'empty_reason',
                        ]
                    );
                    $redirect_url = add_query_arg('aegis_orders_error', '退回原因不能为空。', $redirect_url);
                } elseif ($order && $order->status === 'shipped' && 'ROLLBACK' !== $rollback_confirm) {
                    AEGIS_Access_Audit::record_event(
                        'ORDER_ROLLBACK_STEP',
                        'FAIL',
                        [
                            'order_id'    => (int) $order_id,
                            'from'        => $order->status,
                            'reason_code' => 'confirm_required',
                        ]
                    );
                    $redirect_url = add_query_arg('aegis_orders_error', '已出库订单退回需要输入 ROLLBACK 确认。', $redirect_url);
                } else {
                    $result = self::rollback_order_one_step($order, $reason);
                    if (is_wp_error($result)) {
                        $redirect_url = add_query_arg('aegis_orders_error', $result->get_error_message(), $redirect_url);
                    } else {
                        $redirect_url = add_query_arg('aegis_orders_message', $result['message'], $redirect_url);
                    }
                }

                wp_safe_redirect($redirect_url);
                exit;
            } elseif ('upload_payment' === $action) {
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
                    AEGIS_Access_Audit::record_event(
                        AEGIS_System::ACTION_PAYMENT_PROOF_UPLOAD,
                        'FAIL',
                        [
                            'order_id'    => (int) $order_id,
                            'order_no'    => $order ? $order->order_no : null,
                            'reason_code' => !$is_dealer ? 'forbidden' : 'invalid_order',
                        ]
                    );
                    $errors[] = '无效的订单。';
                } else {
                    $result = self::upload_payment_proof($dealer_state, $order);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                        $view_id = (int) $order_id;
                    }
                }
            } elseif ('submit_payment' === $action) {
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
                    $result = self::submit_payment_confirmation($dealer_state, $order);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                        $view_id = (int) $order_id;
                        $view_mode = 'list';
                    }
                }
            } elseif ('review_payment' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_ACCESS_ROOT,
                        'nonce_field'     => 'aegis_orders_nonce',
                        'nonce_action'    => 'aegis_orders_action',
                        'whitelist'       => ['order_action', 'order_id', 'decision', 'review_note', '_wp_http_referer', '_aegis_idempotency', 'aegis_orders_nonce'],
                        'idempotency_key' => $idempotency,
                    ]
                );
                $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                $order = $order_id ? self::get_order($order_id) : null;
                $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : '';
                $note = isset($_POST['review_note']) ? sanitize_text_field(wp_unslash($_POST['review_note'])) : '';
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif (!$can_payment_review || !$order) {
                    $audit_action = ('reject' === $decision) ? AEGIS_System::ACTION_PAYMENT_REVIEW_REJECT : AEGIS_System::ACTION_PAYMENT_REVIEW_APPROVE;
                    AEGIS_Access_Audit::record_event(
                        $audit_action,
                        'FAIL',
                        [
                            'order_id'    => (int) $order_id,
                            'order_no'    => $order ? $order->order_no : null,
                            'reason_code' => !$can_payment_review ? 'forbidden' : 'invalid_order',
                        ]
                    );
                    $errors[] = '无效的订单。';
                } else {
                    $result = self::review_payment_by_hq($order, $decision, $note);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                        $view_id = (int) $order_id;
                        $view_mode = 'payment_review';
                    }
                }
            }
        }

        if (!$view_id) {
            $view_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        }

        $review_queue = $can_initial_review && 'review' === $view_mode;
        $payment_queue = $can_payment_review && 'payment_review' === $view_mode;
        $queue_view = $review_queue || $payment_queue;
        $base_url = add_query_arg('m', 'orders', $portal_url);
        if ($queue_view) {
            $base_url = add_query_arg('view', $view_mode, $base_url);
        } elseif ($can_view_all) {
            $base_url = add_query_arg('view', 'list', $base_url);
        }

        $default_start = wp_date('Y-m-d', current_time('timestamp') - 6 * DAY_IN_SECONDS);
        $default_end = wp_date('Y-m-d', current_time('timestamp'));
        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : $default_start;
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : $default_end;
        $search_no = isset($_GET['order_no']) ? sanitize_text_field(wp_unslash($_GET['order_no'])) : '';
        $normalized_range = self::normalize_date_range($start_date, $end_date);
        $start_datetime = $normalized_range['start'];
        $end_datetime = $normalized_range['end'];
        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $dealer_filter = $is_dealer ? ($dealer_id > 0 ? $dealer_id : 0) : null;
        if (!$is_dealer && isset($_GET['dealer_id'])) {
            $dealer_filter = absint(wp_unslash($_GET['dealer_id']));
            if ($dealer_filter <= 0) {
                $dealer_filter = null;
            }
        }
        $dealer_filter_param = (!$is_dealer && !empty($dealer_filter)) ? $dealer_filter : 0;
        $statuses_filter = [];
        if ($review_queue) {
            $statuses_filter = [self::STATUS_PENDING_INITIAL_REVIEW];
        } elseif ($payment_queue) {
            $statuses_filter = [self::STATUS_PENDING_HQ_PAYMENT_REVIEW];
        }
        $total = 0;
        self::$portal_query_error = '';
        if ($dealer_missing) {
            $errors[] = '账号未绑定经销商，无法查询订单。';
            AEGIS_Access_Audit::record_event(
                'ACCESS_DENIED',
                'FAIL',
                [
                    'reason_code' => 'dealer_missing',
                    'user_id' => (int) get_current_user_id(),
                    'roles' => $roles,
                    'derived_dealer_id' => $dealer_id,
                    'path' => isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '',
                ]
            );
            $orders = [];
        } else {
            $orders = self::query_portal_orders(
                [
                    'start'     => $start_datetime,
                    'end'       => $end_datetime,
                    'order_no'  => $search_no,
                    'dealer_id' => $dealer_filter,
                    'sales_user_id' => $sales_user_filter,
                    'statuses'  => $statuses_filter,
                    'per_page'  => $per_page,
                    'paged'     => $paged,
                ],
                $total
            );
        }
        if (self::$portal_query_error) {
            if ($can_manage_system || current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM)) {
                $errors[] = '订单列表查询失败，请联系管理员。';
            } else {
                $errors[] = '系统查询失败，请联系管理员。';
            }
        }

        $order = $view_id ? self::get_order($view_id) : null;
        if ($order && $is_dealer && (int) $order->dealer_id !== $dealer_id) {
            $order = null;
            $errors[] = '无权查看该订单。';
        }
        if ($order && $sales_user_filter > 0) {
            global $wpdb;
            $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
            $order_sales_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT sales_user_id FROM {$dealer_table} WHERE id = %d",
                    (int) $order->dealer_id
                )
            );
            if ($order_sales_id !== $sales_user_filter) {
                $order = null;
                $errors[] = '无权查看该订单。';
            }
        }
        $processing_lock = $order ? self::get_processing_lock($order) : null;
        $cancel_request = $order ? self::get_cancel_request($order) : null;
        $cancel_submitted = !empty($_GET['cancel_submitted']) && (int) ($_GET['order_id'] ?? 0) === $view_id;
        if ($cancel_submitted || (!empty($cancel_request['requested']) && ('pending' === ($cancel_request['decision'] ?? '')))) {
            $auto_open_drawer = true;
        }
        $items = $order ? self::get_items($order->id) : [];
        $payment = $order ? self::get_payment_record($order->id) : null;
        $skus = $is_dealer ? self::list_active_skus() : [];
        $price_map = ($is_dealer && $dealer && $skus) ? self::build_price_map($dealer, $skus) : [];

        $status_labels = [
            self::STATUS_DRAFT                 => '草稿',
            self::STATUS_PENDING_INITIAL_REVIEW => '待初审',
            self::STATUS_PENDING_DEALER_CONFIRM => '待确认',
            self::STATUS_PENDING_HQ_PAYMENT_REVIEW => '待审核',
            self::STATUS_APPROVED_PENDING_FULFILLMENT => '已通过（待出库）',
            self::STATUS_FULFILLED             => '已出库',
            self::STATUS_CANCELLED             => '已撤销',
            self::STATUS_CANCELLED_BY_DEALER    => '已撤销',
            self::STATUS_VOIDED_BY_HQ           => '已作废',
        ];

        $context = [
            'base_url'       => $base_url,
            'messages'       => $messages,
            'errors'         => $errors,
            'auto_open_drawer' => $auto_open_drawer,
            'orders'         => $orders,
            'order'          => $order,
            'items'          => $items,
            'payment'        => $payment,
            'processing_lock' => $processing_lock,
            'cancel_request' => $cancel_request,
            'cancel_form_error' => $cancel_form_error,
            'filters'        => [
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'order_no'    => $search_no,
                'dealer_id'   => $dealer_filter_param,
                'per_page'    => $per_page,
                'per_options' => $per_page_options,
                'paged'       => $paged,
                'total'       => $total,
                'total_pages' => $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1,
            ],
            'skus'           => $skus,
            'dealer'         => $dealer,
            'dealer_blocked' => $dealer_blocked,
            'price_map'      => $price_map,
            'view_mode'      => $view_mode,
            'status_labels'  => $status_labels,
            'role_flags'     => [
                'is_dealer'          => $is_dealer,
                'can_view_all'       => $can_view_all,
                'can_initial_review' => $can_initial_review,
                'can_payment_review' => $can_payment_review,
                'can_manage_all'     => $can_manage_all,
                'can_create_order'   => $can_create_order,
                'queue_view'         => $queue_view,
                'payment_queue'      => $payment_queue,
                'can_manage'         => $can_manage,
                'staff_readonly'     => $is_staff_readonly,
            ],
            'queue_mode'     => $queue_view ? $view_mode : '',
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
