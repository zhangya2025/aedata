<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Shipments {
    const MAX_PER_SHIPMENT = 300;

    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('shipments')) {
            return '<div class="aegis-t-a5">出库模块未启用，请联系管理员。</div>';
        }

        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return '<div class="aegis-t-a5">当前账号无权访问出库模块。</div>';
        }

        $base_url = add_query_arg('m', 'shipments', $portal_url);
        $messages = [];
        $errors = [];
        $order_link_enabled = AEGIS_Orders::is_shipment_link_enabled();
        $shipment_id = isset($_GET['shipment']) ? (int) $_GET['shipment'] : 0;
        $prefill_dealer_id = isset($_GET['dealer_id']) ? (int) $_GET['dealer_id'] : 0;
        $prefill_order_ref = isset($_GET['order_ref']) ? sanitize_text_field(wp_unslash($_GET['order_ref'])) : '';

        if (isset($_GET['shipments_action'])) {
            $action = sanitize_key(wp_unslash($_GET['shipments_action']));
            $target = isset($_GET['shipment']) ? (int) $_GET['shipment'] : 0;
            if ('export_summary' === $action) {
                $result = self::handle_export_summary($target);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            } elseif ('export_detail' === $action) {
                $result = self::handle_export_detail($target);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            } elseif ('print' === $action && $target) {
                $result = self::handle_print_summary($target);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            }
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $whitelist = ['shipments_action', 'dealer_id', 'note', 'order_ref', 'shipment_id', 'code', '_wp_http_referer', 'aegis_shipments_nonce', '_aegis_idempotency'];
            $action = isset($_POST['shipments_action']) ? sanitize_key(wp_unslash($_POST['shipments_action'])) : '';
            $capability = 'delete_shipment' === $action ? AEGIS_System::CAP_MANAGE_SYSTEM : AEGIS_System::CAP_USE_WAREHOUSE;
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => $capability,
                    'nonce_field'     => 'aegis_shipments_nonce',
                    'nonce_action'    => 'aegis_shipments_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } else {
                if ('start' === $action) {
                    $dealer_id = isset($_POST['dealer_id']) ? (int) $_POST['dealer_id'] : 0;
                    $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
                    $order_ref = isset($_POST['order_ref']) ? sanitize_text_field(wp_unslash($_POST['order_ref'])) : '';
                    $result = self::handle_portal_start($dealer_id, $note, $order_ref);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        wp_safe_redirect(add_query_arg('shipment', (int) $result['shipment_id'], $base_url));
                        exit;
                    }
                } elseif ('add' === $action) {
                    $shipment_id = isset($_POST['shipment_id']) ? (int) $_POST['shipment_id'] : 0;
                    $code_value = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
                    $code_value = AEGIS_System::normalize_code_value($code_value);
                    $result = self::handle_portal_add_code($shipment_id, $code_value);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                    }
                } elseif ('complete' === $action) {
                    $shipment_id = isset($_POST['shipment_id']) ? (int) $_POST['shipment_id'] : 0;
                    $result = self::handle_portal_complete($shipment_id);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                    }
                } elseif ('delete_shipment' === $action) {
                    $shipment_id = isset($_POST['shipment_id']) ? (int) $_POST['shipment_id'] : 0;
                    $result = self::handle_delete_shipment($shipment_id);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                    }
                }
            }
        }

        $default_start = gmdate('Y-m-d', current_time('timestamp') - 6 * DAY_IN_SECONDS);
        $default_end = gmdate('Y-m-d', current_time('timestamp'));
        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : $default_start;
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : $default_end;
        $dealer_filter = isset($_GET['dealer_id']) ? (int) $_GET['dealer_id'] : 0;
        $start_datetime = self::normalize_date_boundary($start_date, 'start');
        $end_datetime = self::normalize_date_boundary($end_date, 'end');

        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $total = 0;
        $shipments = self::query_shipments($start_datetime, $end_datetime, $per_page, $paged, $total, $dealer_filter);
        $pending_orders = [];
        if ($order_link_enabled && AEGIS_System::is_module_enabled('orders')) {
            $pending_orders = self::query_orders_ready_for_fulfillment($dealer_filter);
        }

        $shipment = $shipment_id ? self::get_shipment($shipment_id) : null;
        $items = $shipment ? self::get_items_by_shipment($shipment_id, true) : [];
        $summary = $shipment ? self::get_shipment_summary($shipment_id) : null;
        $sku_summary = $shipment ? self::group_items_by_sku($items) : [];
        $dealers = self::get_active_dealers();
        $cancel_pending = false;
        if ($shipment && $shipment->order_ref && AEGIS_System::is_module_enabled('orders')) {
            $linked_order = AEGIS_Orders::get_order_by_no($shipment->order_ref);
            if ($linked_order) {
                $cancel_pending = AEGIS_Orders::is_cancel_pending($linked_order->id);
            }
        }

        $context = [
            'base_url'     => $base_url,
            'portal_url'   => $portal_url,
            'messages'     => $messages,
            'errors'       => $errors,
            'shipment'     => $shipment,
            'items'        => $items,
            'summary'      => $summary,
            'sku_summary'  => $sku_summary,
            'dealers'      => $dealers,
            'cancel_pending' => $cancel_pending,
            'pending_orders' => $pending_orders,
            'prefill'      => [
                'dealer_id' => $prefill_dealer_id,
                'order_ref' => $prefill_order_ref,
            ],
            'order_link_enabled' => $order_link_enabled,
            'filters'      => [
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'per_page'    => $per_page,
                'paged'       => $paged,
                'total'       => $total,
                'per_options' => $per_page_options,
                'dealer_id'   => $dealer_filter,
                'total_pages' => $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1,
            ],
            'shipments'    => $shipments,
        ];

        self::enqueue_scanner_assets();
        return AEGIS_Portal::render_portal_template('shipments', $context);
    }

    protected static function enqueue_scanner_assets() {
        $quagga_path = AEGIS_SYSTEM_PATH . 'assets/vendor/quagga2.min.js';
        wp_enqueue_script(
            'aegis-system-quagga2',
            AEGIS_SYSTEM_URL . 'assets/vendor/quagga2.min.js',
            [],
            file_exists($quagga_path) ? filemtime($quagga_path) : AEGIS_Assets_Media::get_asset_version('assets/vendor/quagga2.min.js'),
            true
        );
        $js_path = AEGIS_SYSTEM_PATH . 'assets/js/scanner-1d.js';
        wp_enqueue_script(
            'aegis-system-scanner-1d',
            AEGIS_SYSTEM_URL . 'assets/js/scanner-1d.js',
            ['aegis-system-quagga2'],
            file_exists($js_path) ? filemtime($js_path) : AEGIS_Assets_Media::get_asset_version('assets/js/scanner-1d.js'),
            true
        );
    }

    protected static function handle_portal_start($dealer_id, $note = '', $order_ref = '') {
        global $wpdb;
        if ($dealer_id <= 0) {
            return new WP_Error('invalid_dealer', '请选择经销商。');
        }
        $dealer = self::get_dealer($dealer_id);
        if (!$dealer) {
            return new WP_Error('dealer_missing', '经销商不存在。');
        }
        if ('active' !== $dealer->status) {
            return new WP_Error('dealer_inactive', '该经销商已停用，禁止出库。');
        }

        $order_link_enabled = AEGIS_Orders::is_shipment_link_enabled();
        $order_ref = $order_link_enabled ? $order_ref : '';
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
            if (AEGIS_Orders::STATUS_APPROVED_PENDING_FULFILLMENT !== $order->status) {
                return new WP_Error('order_not_ready', '订单未进入待出库状态，无法开始出库。');
            }
        }

        $shipment_no = 'SHP-' . gmdate('Ymd-His', current_time('timestamp'));
        $meta = [];
        if ($note) {
            $meta['note'] = $note;
        }
        if ($order_ref) {
            $meta['order_ref'] = $order_ref;
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE,
            [
                'shipment_no' => $shipment_no,
                'dealer_id'   => $dealer_id,
                'created_by'  => get_current_user_id(),
                'created_at'  => current_time('mysql'),
                'qty'         => 0,
                'note'        => $note,
                'order_ref'   => $order_ref ? $order_ref : null,
                'status'      => 'draft',
                'meta'        => !empty($meta) ? wp_json_encode($meta) : null,
            ],
            ['%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_CREATE, 'FAIL', ['dealer_id' => $dealer_id, 'error' => $wpdb->last_error]);
            return new WP_Error('create_fail', '出库单创建失败，请重试。');
        }

        $shipment_id = (int) $wpdb->insert_id;
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_CREATE, 'SUCCESS', ['shipment_id' => $shipment_id, 'dealer_id' => $dealer_id, 'phase' => 'draft']);
        return ['shipment_id' => $shipment_id];
    }

    protected static function handle_portal_add_code($shipment_id, $code_value) {
        global $wpdb;
        $code_value = AEGIS_System::normalize_code_value($code_value);
        $formatted_code = AEGIS_System::format_code_display($code_value);
        if ($shipment_id <= 0 || '' === $code_value) {
            return new WP_Error('invalid_input', '出库单或防伪码无效。');
        }

        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            return new WP_Error('shipment_missing', '出库单不存在。');
        }
        if ('completed' === $shipment->status) {
            return new WP_Error('shipment_closed', '出库单已完成。');
        }

        $dealer = self::get_dealer($shipment->dealer_id);
        if (!$dealer || 'active' !== $dealer->status) {
            return new WP_Error('dealer_inactive', '经销商已停用，禁止出库。');
        }

        $items = self::get_items_by_shipment($shipment_id);
        if (count($items) >= self::MAX_PER_SHIPMENT) {
            return new WP_Error('shipment_full', '单次最多出库 300 条。');
        }

        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;

        $code = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$code_table} WHERE code = %s", $code_value));
        if (!$code) {
            return new WP_Error('code_missing', '防伪码不存在：' . $formatted_code . '。');
        }
        if ('in_stock' !== $code->stock_status) {
            if ('generated' === $code->stock_status || !$code->stock_status) {
                return new WP_Error('code_not_stocked', '未入库，不可出库：' . $formatted_code . '。');
            }
            if ('shipped' === $code->stock_status) {
                return new WP_Error('code_shipped', '该防伪码已出库：' . $formatted_code . '。');
            }
            return new WP_Error('code_invalid', '该防伪码状态异常：' . $formatted_code . '。');
        }

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$shipment_item_table} WHERE code_id = %d", (int) $code->id));
        if ($exists) {
            return new WP_Error('duplicate_code', '该防伪码已在出库单中：' . $formatted_code . '。');
        }

        $now = current_time('mysql');
        $wpdb->query('START TRANSACTION');
        $inserted = $wpdb->insert(
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
        if (!$inserted) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('item_fail', '写入出库明细失败。');
        }

        $updated = $wpdb->update(
            $code_table,
            [
                'status'       => 'used',
                'stock_status' => 'shipped',
            ],
            ['id' => $code->id, 'stock_status' => 'in_stock'],
            ['%s', '%s'],
            ['%d', '%s']
        );
        if (!$updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('code_update_fail', '更新防伪码状态失败。');
        }

        $wpdb->query('COMMIT');
        return ['message' => '已出库：' . $formatted_code];
    }

    protected static function handle_portal_complete($shipment_id) {
        global $wpdb;
        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            return new WP_Error('shipment_missing', '出库单不存在。');
        }
        $order = null;
        if ($shipment->order_ref) {
            if (!AEGIS_System::is_module_enabled('orders')) {
                return new WP_Error('order_disabled', '订单模块未启用，无法更新订单状态。');
            }
            $order = AEGIS_Orders::get_order_by_no($shipment->order_ref);
            if (!$order) {
                return new WP_Error('order_missing', '关联订单不存在，无法完成出库。');
            }
            $guard = AEGIS_Orders::guard_not_cancel_pending(
                (int) $order->id,
                'shipment_complete',
                [
                    'order_no'    => $order->order_no,
                    'shipment_id' => (int) $shipment_id,
                ]
            );
            if (is_wp_error($guard)) {
                return $guard;
            }
            if (AEGIS_Orders::STATUS_APPROVED_PENDING_FULFILLMENT !== $order->status) {
                return new WP_Error('order_not_ready', '关联订单未处于待出库状态，无法完成出库。');
            }
        }
        $items = self::get_items_by_shipment($shipment_id);
        $count = count($items);
        if ($count <= 0) {
            return new WP_Error('empty_shipment', '请先扫码或录入防伪码。');
        }
        $wpdb->update(
            $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE,
            [
                'qty'    => $count,
                'status' => 'completed',
            ],
            ['id' => $shipment_id],
            ['%d', '%s'],
            ['%d']
        );
        if (!empty($shipment->order_ref)) {
            $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
            $wpdb->update(
                $order_table,
                [
                    'status'     => AEGIS_Orders::STATUS_FULFILLED,
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
                    'to'       => AEGIS_Orders::STATUS_FULFILLED,
                ]
            );
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_SHIPMENT_COMPLETE,
                'SUCCESS',
                [
                    'shipment_id' => $shipment_id,
                    'order_id'    => (int) $order->id,
                    'order_no'    => $order->order_no,
                ]
            );
        }
        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_SHIPMENT_CREATE,
            'SUCCESS',
            [
                'shipment_id' => $shipment_id,
                'dealer_id'   => $shipment->dealer_id,
                'qty'         => $count,
            ]
        );
        return ['message' => '出库完成，共 ' . $count . ' 条。'];
    }

    protected static function handle_delete_shipment($shipment_id) {
        global $wpdb;
        if (!AEGIS_System_Roles::user_can_manage_system()) {
            return new WP_Error('forbidden', '当前账号无权删除出库单。');
        }
        if ($shipment_id <= 0) {
            return new WP_Error('invalid_shipment', '出库单不存在。');
        }
        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            return new WP_Error('shipment_missing', '出库单不存在。');
        }
        if ('draft' !== $shipment->status) {
            return new WP_Error('not_draft', '该出库单已非草稿状态，禁止删除。');
        }
        $item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $item_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$item_table} WHERE shipment_id = %d", $shipment_id));
        if ($item_count > 0) {
            return new WP_Error('not_empty', '该出库单已有明细，禁止删除。');
        }
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $deleted = $wpdb->delete($shipment_table, ['id' => $shipment_id], ['%d']);
        if (!$deleted) {
            return new WP_Error('delete_failed', '删除出库单失败，请重试。');
        }
        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_SHIPMENT_DELETE,
            'SUCCESS',
            [
                'shipment_id' => $shipment_id,
                'shipment_no' => $shipment->shipment_no,
            ]
        );
        return ['message' => '出库单已删除。'];
    }

    protected static function handle_export_summary($shipment_id) {
        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return new WP_Error('no_permission', '无权导出出库汇总。');
        }
        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            return new WP_Error('missing_shipment', '出库单不存在。');
        }
        $items = self::get_items_by_shipment($shipment_id, true);
        $summary = self::group_items_by_sku($items);
        if (empty($summary)) {
            return new WP_Error('empty', '没有可导出的汇总。');
        }

        $dealer = self::get_dealer($shipment->dealer_id);
        $dealer_label = $dealer ? $dealer->dealer_name : '';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="shipment-' . $shipment->shipment_no . '-summary.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['shipment_no', 'dealer', 'ean', 'product_name', 'quantity']);
        foreach ($summary as $row) {
            fputcsv($output, [$shipment->shipment_no, $dealer_label, $row['ean'], $row['product_name'], $row['count']]);
        }
        fclose($output);
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_EXPORT_SUMMARY, 'SUCCESS', ['shipment_id' => $shipment_id, 'sku_lines' => count($summary)]);
        exit;
    }

    protected static function handle_export_detail($shipment_id) {
        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return new WP_Error('no_permission', '无权导出出库明细。');
        }
        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            return new WP_Error('missing_shipment', '出库单不存在。');
        }
        $items = self::get_items_by_shipment($shipment_id, true);
        if (empty($items)) {
            return new WP_Error('empty', '没有可导出的明细。');
        }

        $dealer = self::get_dealer($shipment->dealer_id);
        $dealer_label = $dealer ? $dealer->dealer_name : '';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="shipment-' . $shipment->shipment_no . '-detail.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['shipment_no', 'dealer', 'code', 'ean', 'product_name', 'scanned_at']);
        foreach ($items as $item) {
            fputcsv($output, [$shipment->shipment_no, $dealer_label, $item->code_value, $item->ean, $item->product_name, $item->scanned_at]);
        }
        fclose($output);
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_EXPORT_DETAIL, 'SUCCESS', ['shipment_id' => $shipment_id, 'count' => count($items)]);
        exit;
    }

    protected static function handle_print_summary($shipment_id) {
        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            return new WP_Error('missing_shipment', '出库单不存在。');
        }
        $items = self::get_items_by_shipment($shipment_id, true);
        $summary = self::group_items_by_sku($items);
        $dealer = self::get_dealer($shipment->dealer_id);
        $dealer_label = $dealer ? $dealer->dealer_name : '-';
        $dealer_contact = $dealer ? $dealer->contact_name : '-';
        $dealer_phone = $dealer ? $dealer->phone : '-';
        $dealer_address = $dealer ? $dealer->address : '-';
        $shipment_time = $shipment->created_at ? $shipment->created_at : '-';

        echo '<html><head><title>出库单打印</title>';
        echo '<style>@page{margin:12mm;}body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111;margin:0;}';
        echo '.aegis-print-wrap{padding:12px 8px;}';
        echo '.aegis-print-title{text-align:center;font-size:18px;font-weight:700;margin:0 0 12px;}';
        echo '.aegis-print-info{display:grid;grid-template-columns:1fr 1fr;gap:6px 18px;margin-bottom:12px;}';
        echo '.aegis-print-table{width:100%;border-collapse:collapse;margin-top:10px;}';
        echo '.aegis-print-table th,.aegis-print-table td{border:1px solid #444;padding:10px 8px;vertical-align:middle;}';
        echo '.aegis-print-table th{background:#f2f2f2;font-weight:600;}';
        echo '.aegis-print-table .col-ean{width:220px;}';
        echo '.aegis-print-table .col-name{width:auto;}';
        echo '.aegis-print-table .col-qty{width:80px;}';
        echo '.aegis-print-table td:nth-child(1),.aegis-print-table th:nth-child(1){text-align:left;}';
        echo '.aegis-print-table td:nth-child(2),.aegis-print-table th:nth-child(2){text-align:left;word-break:break-word;}';
        echo '.aegis-print-table td:nth-child(3),.aegis-print-table th:nth-child(3){text-align:right;white-space:nowrap;}';
        echo '</style></head><body class="aegis-t-a5">';
        echo '<div class="aegis-print-wrap">';
        echo '<h1 class="aegis-print-title">南京翼马出库单汇总</h1>';
        echo '<div class="aegis-print-info">';
        echo '<div>出库单号：' . esc_html($shipment->shipment_no) . '</div>';
        echo '<div>出库时间：' . esc_html($shipment_time) . '</div>';
        echo '<div>经销商：' . esc_html($dealer_label) . '</div>';
        echo '<div>联系人：' . esc_html($dealer_contact) . '</div>';
        echo '<div>联系电话：' . esc_html($dealer_phone) . '</div>';
        echo '<div>地址：' . esc_html($dealer_address) . '</div>';
        echo '</div>';
        echo '<table class="aegis-print-table">';
        echo '<colgroup><col class="col-ean" /><col class="col-name" /><col class="col-qty" /></colgroup>';
        echo '<thead><tr><th>EAN</th><th>产品名</th><th>数量</th></tr></thead><tbody>';
        foreach ($summary as $row) {
            echo '<tr><td>' . esc_html($row['ean']) . '</td><td>' . esc_html($row['product_name']) . '</td><td>' . esc_html($row['count']) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</body></html>';
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_PRINT_SUMMARY, 'SUCCESS', ['shipment_id' => $shipment_id, 'mode' => 'print']);
        exit;
    }

    protected static function get_shipment_summary($shipment_id) {
        global $wpdb;
        $item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT COUNT(si.id) as total, COUNT(DISTINCT c.ean) as sku_count FROM {$item_table} si JOIN {$code_table} c ON si.code_id = c.id WHERE si.shipment_id = %d", $shipment_id));
    }

    protected static function group_items_by_sku($items) {
        $grouped = [];
        foreach ($items as $item) {
            $key = $item->ean;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'ean'          => $item->ean,
                    'product_name' => $item->product_name ?? '',
                    'count'        => 0,
                ];
            }
            $grouped[$key]['count']++;
        }
        return array_values($grouped);
    }
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
            if (AEGIS_Orders::STATUS_APPROVED_PENDING_FULFILLMENT !== $order->status) {
                return new WP_Error('order_not_ready', '订单未进入待出库状态，无法出库。');
            }
        }

        $codes = self::parse_codes($codes_input);
        if (empty($codes)) {
            return new WP_Error('no_codes', '请输入要出库的防伪码。');
        }

        if (count($codes) > self::MAX_PER_SHIPMENT) {
            return new WP_Error('too_many', '单次最多出库 300 条。');
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
                'qty'         => count($validated_codes),
                'note'        => null,
                'order_ref'   => $order_ref ? $order_ref : null,
                'status'      => 'created',
                'meta'        => wp_json_encode(['count' => count($validated_codes), 'order_ref' => $order_ref]),
            ],
            ['%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s']
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

        if ($order_ref && $order) {
            $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
            $wpdb->update(
                $order_table,
                [
                    'status'     => AEGIS_Orders::STATUS_FULFILLED,
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
                    'to'       => AEGIS_Orders::STATUS_FULFILLED,
                ]
            );
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_SHIPMENT_COMPLETE,
                'SUCCESS',
                [
                    'shipment_id' => $shipment_id,
                    'order_id'    => (int) $order->id,
                    'order_no'    => $order->order_no,
                ]
            );
        }

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

    protected static function query_orders_ready_for_fulfillment($dealer_id = 0, $limit = 50) {
        global $wpdb;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        $where = ['o.status = %s'];
        $params = [AEGIS_Orders::STATUS_APPROVED_PENDING_FULFILLMENT];
        if ($dealer_id > 0) {
            $where[] = 'o.dealer_id = %d';
            $params[] = $dealer_id;
        }
        $where_sql = implode(' AND ', $where);
        $params[] = $limit;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.id, o.order_no, o.dealer_id, o.created_at, d.dealer_name, COALESCE(SUM(oi.qty),0) AS total_qty
                 FROM {$order_table} o
                 LEFT JOIN {$dealer_table} d ON o.dealer_id = d.id
                 LEFT JOIN {$item_table} oi ON oi.order_id = o.id
                 WHERE {$where_sql}
                 GROUP BY o.id
                 ORDER BY o.created_at DESC
                 LIMIT %d",
                $params
            )
        );
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
            echo '<td>' . esc_html(AEGIS_System::format_code_display($item->code_value)) . '</td>';
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

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SHIPMENT_EXPORT_DETAIL, 'SUCCESS', [
            'shipment_id' => $shipment->id,
            'count'       => count($items),
        ]);
        exit;
    }

    /**
     * 获取出库单列表。
     */
    protected static function query_shipments($start, $end, $per_page, $paged, &$total, $dealer_id = 0) {
        global $wpdb;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;

        $where_parts = ['s.created_at BETWEEN %s AND %s'];
        $params = [$start, $end];
        if ($dealer_id > 0) {
            $where_parts[] = 's.dealer_id = %d';
            $params[] = $dealer_id;
        }
        $where = implode(' AND ', $where_parts);

        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$shipment_table} s WHERE {$where}", $params));
        $offset = ($paged - 1) * $per_page;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, COUNT(i.id) as item_count FROM {$shipment_table} s LEFT JOIN {$shipment_item_table} i ON s.id = i.shipment_id WHERE {$where} GROUP BY s.id ORDER BY s.created_at DESC LIMIT %d OFFSET %d",
                array_merge($params, [$per_page, $offset])
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
    protected static function get_items_by_shipment($id, $with_product = false) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        if ($with_product) {
            $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
            $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
            $sql = $wpdb->prepare(
                "SELECT si.*, c.code as code_value, c.ean, s.product_name FROM {$table} si JOIN {$code_table} c ON si.code_id = c.id LEFT JOIN {$sku_table} s ON c.ean = s.ean WHERE si.shipment_id = %d ORDER BY si.id ASC",
                $id
            );
            return $wpdb->get_results($sql);
        }
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
