<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Inbound {
    const MAX_PER_RECEIPT = 300;

    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('inbound')) {
            return '<div class="aegis-t-a5">入库模块未启用，请联系管理员。</div>';
        }

        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return '<div class="aegis-t-a5">当前账号无权访问入库模块。</div>';
        }

        $base_url = add_query_arg('m', 'inbound', $portal_url);
        $messages = [];
        $errors = [];
        $receipt_id = isset($_GET['receipt']) ? (int) $_GET['receipt'] : 0;

        if (isset($_GET['inbound_action']) && 'export' === sanitize_key(wp_unslash($_GET['inbound_action']))) {
            $target_receipt = isset($_GET['receipt']) ? (int) $_GET['receipt'] : 0;
            $result = self::handle_export($target_receipt);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            }
        }

        if (isset($_GET['inbound_action']) && 'print' === sanitize_key(wp_unslash($_GET['inbound_action'])) && $receipt_id) {
            $result = self::handle_print($receipt_id);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            }
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $whitelist = ['inbound_action', 'code', 'note', 'receipt_id', '_wp_http_referer', 'aegis_inbound_nonce', '_aegis_idempotency'];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_USE_WAREHOUSE,
                    'nonce_field'     => 'aegis_inbound_nonce',
                    'nonce_action'    => 'aegis_inbound_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } else {
                $action = isset($_POST['inbound_action']) ? sanitize_key(wp_unslash($_POST['inbound_action'])) : '';
                if ('start' === $action) {
                    $result = self::handle_start(isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '');
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        wp_safe_redirect(add_query_arg('receipt', (int) $result['receipt_id'], $base_url));
                        exit;
                    }
                } elseif ('add' === $action) {
                    $receipt_id = isset($_POST['receipt_id']) ? (int) $_POST['receipt_id'] : 0;
                    $code_value = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
                    $result = self::handle_add_code($receipt_id, $code_value);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $messages[] = $result['message'];
                    }
                } elseif ('complete' === $action) {
                    $receipt_id = isset($_POST['receipt_id']) ? (int) $_POST['receipt_id'] : 0;
                    $result = self::handle_complete($receipt_id);
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
        $start_datetime = self::normalize_date_boundary($start_date, 'start');
        $end_datetime = self::normalize_date_boundary($end_date, 'end');
        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $total = 0;
        $receipts = self::query_receipts($start_datetime, $end_datetime, $per_page, $paged, $total);

        $receipt = $receipt_id ? self::get_receipt($receipt_id) : null;
        $items = $receipt ? self::get_items($receipt_id) : [];
        $summary = $receipt ? self::get_summary($receipt_id) : [];
        $sku_summary = $receipt ? self::group_by_sku($items) : [];

        $context = [
            'base_url'      => $base_url,
            'messages'      => $messages,
            'errors'        => $errors,
            'receipt'       => $receipt,
            'items'         => $items,
            'summary'       => $summary,
            'sku_summary'   => $sku_summary,
            'filters'       => [
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'per_page'    => $per_page,
                'paged'       => $paged,
                'total'       => $total,
                'per_options' => $per_page_options,
                'total_pages' => $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1,
            ],
            'receipts'      => $receipts,
        ];

        return AEGIS_Portal::render_portal_template('inbound', $context);
    }

    protected static function handle_start($note = '') {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RECEIPT_TABLE;
        $receipt_no = 'RCPT-' . gmdate('Ymd-His', current_time('timestamp'));
        $inserted = $wpdb->insert(
            $table,
            [
                'receipt_no' => $receipt_no,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'qty'        => 0,
                'note'       => $note,
            ],
            ['%s', '%d', '%s', '%d', '%s']
        );

        if (!$inserted) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RECEIPT_CREATE, 'FAIL', ['error' => $wpdb->last_error]);
            return new WP_Error('receipt_create_fail', '入库单创建失败，请重试。');
        }

        $receipt_id = (int) $wpdb->insert_id;
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RECEIPT_CREATE, 'SUCCESS', ['receipt_id' => $receipt_id]);
        return [
            'receipt_id' => $receipt_id,
        ];
    }

    protected static function handle_add_code($receipt_id, $code_value) {
        global $wpdb;
        if ($receipt_id <= 0 || '' === $code_value) {
            return new WP_Error('invalid_input', '入库单或防伪码无效。');
        }
        $receipt = self::get_receipt($receipt_id);
        if (!$receipt) {
            return new WP_Error('receipt_missing', '入库单不存在。');
        }
        $items = self::get_items($receipt_id);
        if (count($items) >= self::MAX_PER_RECEIPT) {
            return new WP_Error('receipt_full', '单次最多录入 300 条。');
        }

        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $receipt_item_table = $wpdb->prefix . AEGIS_System::RECEIPT_ITEM_TABLE;

        $code = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$code_table} WHERE code = %s", $code_value));
        if (!$code) {
            return new WP_Error('code_missing', '防伪码不存在。');
        }
        if ('generated' !== $code->stock_status) {
            if ('in_stock' === $code->stock_status) {
                return new WP_Error('code_in_stock', '该防伪码已入库。');
            }
            return new WP_Error('code_shipped', '该防伪码已出库。');
        }

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$receipt_item_table} WHERE code_id = %d", (int) $code->id));
        if ($exists) {
            return new WP_Error('duplicate', '该防伪码已在其他入库单中。');
        }

        $wpdb->query('START TRANSACTION');
        $now = current_time('mysql');
        $inserted = $wpdb->insert(
            $receipt_item_table,
            [
                'receipt_id' => $receipt_id,
                'code_id'    => $code->id,
                'ean'        => $code->ean,
                'created_at' => $now,
            ],
            ['%d', '%d', '%s', '%s']
        );
        if (!$inserted) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('item_fail', '写入入库明细失败。');
        }

        $updated = $wpdb->update(
            $code_table,
            [
                'stock_status' => 'in_stock',
                'stocked_at'   => $now,
                'stocked_by'   => get_current_user_id(),
                'receipt_id'   => $receipt_id,
            ],
            ['id' => $code->id, 'stock_status' => 'generated'],
            ['%s', '%s', '%d', '%d'],
            ['%d', '%s']
        );
        if (!$updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('code_update_fail', '更新防伪码状态失败。');
        }

        $wpdb->query('COMMIT');
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RECEIPT_ITEM_ADD, 'SUCCESS', ['receipt_id' => $receipt_id, 'code' => $code_value]);
        return ['message' => '已入库：' . $code_value];
    }

    protected static function handle_complete($receipt_id) {
        global $wpdb;
        $receipt = self::get_receipt($receipt_id);
        if (!$receipt) {
            return new WP_Error('missing_receipt', '入库单不存在。');
        }
        $items = self::get_items($receipt_id);
        $count = count($items);
        $table = $wpdb->prefix . AEGIS_System::RECEIPT_TABLE;
        $wpdb->update(
            $table,
            ['qty' => $count],
            ['id' => $receipt_id],
            ['%d'],
            ['%d']
        );
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RECEIPT_COMPLETE, 'SUCCESS', ['receipt_id' => $receipt_id, 'qty' => $count]);
        return ['message' => '入库完成，共 ' . $count . ' 条。'];
    }

    protected static function handle_export($receipt_id) {
        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return new WP_Error('no_permission', '无权导出入库明细。');
        }
        $receipt = self::get_receipt($receipt_id);
        if (!$receipt) {
            return new WP_Error('missing_receipt', '入库单不存在。');
        }
        $items = self::get_items($receipt_id);
        if (empty($items)) {
            return new WP_Error('empty', '没有可导出的明细。');
        }

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RECEIPT_EXPORT, 'SUCCESS', ['receipt_id' => $receipt_id, 'count' => count($items)]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="receipt-' . $receipt->receipt_no . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['receipt_no', 'code', 'ean', 'product_name', 'stocked_at']);
        foreach ($items as $item) {
            fputcsv($output, [$receipt->receipt_no, $item->code, $item->ean, $item->product_name, $item->created_at]);
        }
        fclose($output);
        exit;
    }

    protected static function handle_print($receipt_id) {
        $receipt = self::get_receipt($receipt_id);
        if (!$receipt) {
            return new WP_Error('missing_receipt', '入库单不存在。');
        }
        $items = self::get_items($receipt_id);
        $sku_summary = self::group_by_sku($items);
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RECEIPT_PRINT, 'SUCCESS', ['receipt_id' => $receipt_id]);
        echo '<html><head><title>入库单打印</title></head><body class="aegis-t-a5">';
        echo '<h2 class="aegis-t-a3">入库单汇总</h2>';
        echo '<p class="aegis-t-a6">入库单号：' . esc_html($receipt->receipt_no) . ' 入库时间：' . esc_html($receipt->created_at) . '</p>';
        echo '<table class="aegis-table" style="width:100%; border:1px solid #ccc;" cellspacing="0" cellpadding="6">';
        echo '<thead><tr><th>EAN</th><th>产品名</th><th>数量</th></tr></thead><tbody>';
        foreach ($sku_summary as $row) {
            echo '<tr><td>' . esc_html($row['ean']) . '</td><td>' . esc_html($row['product_name']) . '</td><td>' . esc_html($row['count']) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</body></html>';
        exit;
    }

    protected static function query_receipts($start, $end, $per_page, $paged, &$total) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RECEIPT_TABLE;
        $offset = ($paged - 1) * $per_page;
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE created_at BETWEEN %s AND %s", $start, $end));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $start, $end, $per_page, $offset));
    }

    protected static function get_receipt($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RECEIPT_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    protected static function get_items($receipt_id) {
        global $wpdb;
        $item_table = $wpdb->prefix . AEGIS_System::RECEIPT_ITEM_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $sql = $wpdb->prepare(
            "SELECT ri.*, c.code, c.ean, s.product_name FROM {$item_table} ri JOIN {$code_table} c ON ri.code_id = c.id LEFT JOIN {$sku_table} s ON c.ean = s.ean WHERE ri.receipt_id = %d ORDER BY ri.id ASC",
            $receipt_id
        );
        return $wpdb->get_results($sql);
    }

    protected static function get_summary($receipt_id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RECEIPT_ITEM_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT COUNT(1) AS total, COUNT(DISTINCT ean) AS sku_count FROM {$table} WHERE receipt_id = %d", $receipt_id));
    }

    protected static function group_by_sku($items) {
        $result = [];
        foreach ($items as $item) {
            $key = $item->ean ?: '未知';
            if (!isset($result[$key])) {
                $result[$key] = [
                    'ean'          => $item->ean,
                    'product_name' => $item->product_name,
                    'count'        => 0,
                ];
            }
            $result[$key]['count']++;
        }
        return array_values($result);
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
}
