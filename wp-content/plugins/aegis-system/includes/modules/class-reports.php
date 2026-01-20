<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Reports {
    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('reports')) {
            return '<div class="aegis-t-a5">报表模块未启用，请联系管理员。</div>';
        }

        $user = wp_get_current_user();
        if (self::is_dealer($user)) {
            return '<div class="aegis-t-a5">当前账号无权访问报表模块。</div>';
        }

        if (!self::user_can_view($user)) {
            return '<div class="aegis-t-a5">当前账号无权访问报表模块。</div>';
        }

        $base_url = add_query_arg('m', 'reports', $portal_url);
        $messages = [];
        $errors = [];
        $can_export = self::user_can_export($user);

        $filters = self::parse_filters();

        if (isset($_GET['reports_action'])) {
            $action = sanitize_key(wp_unslash($_GET['reports_action']));
            if ('export' === $action) {
                $result = self::handle_export_summary($filters, $can_export);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            } elseif ('export_receipt_detail' === $action) {
                $receipt_id = isset($_GET['receipt_id']) ? (int) $_GET['receipt_id'] : 0;
                $result = self::handle_export_receipt_detail($receipt_id, $can_export);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            } elseif ('export_shipment_detail' === $action) {
                $shipment_id = isset($_GET['shipment_id']) ? (int) $_GET['shipment_id'] : 0;
                $result = self::handle_export_shipment_detail($shipment_id, $can_export);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            }
        }

        $receipts_total = 0;
        $receipts = self::query_receipts(
            $filters['start_datetime'],
            $filters['end_datetime'],
            $filters['sku'],
            $filters['per_page'],
            $filters['receipt_page'],
            $receipts_total
        );

        $shipments_total = 0;
        $shipments = self::query_shipments(
            $filters['start_datetime'],
            $filters['end_datetime'],
            $filters['dealer_id'],
            $filters['sku'],
            $filters['per_page'],
            $filters['shipment_page'],
            $shipments_total
        );

        $receipt_detail = null;
        $receipt_items = [];
        $receipt_summary = null;
        $receipt_id = isset($_GET['receipt_id']) ? (int) $_GET['receipt_id'] : 0;
        if ($receipt_id) {
            $receipt_detail = self::get_receipt($receipt_id);
            if ($receipt_detail) {
                $receipt_items = self::get_receipt_items($receipt_id);
                $receipt_summary = self::get_receipt_summary($receipt_id);
            }
        }

        $shipment_detail = null;
        $shipment_items = [];
        $shipment_id = isset($_GET['shipment_id']) ? (int) $_GET['shipment_id'] : 0;
        if ($shipment_id) {
            $shipment_detail = self::get_shipment($shipment_id);
            if ($shipment_detail) {
                $shipment_items = self::get_shipment_items($shipment_id, true);
            }
        }

        $dealers = self::get_dealers();

        $filters['receipt_total'] = $receipts_total;
        $filters['shipment_total'] = $shipments_total;
        $filters['receipt_total_pages'] = $filters['per_page'] > 0 ? max(1, (int) ceil($receipts_total / $filters['per_page'])) : 1;
        $filters['shipment_total_pages'] = $filters['per_page'] > 0 ? max(1, (int) ceil($shipments_total / $filters['per_page'])) : 1;

        $context = [
            'base_url'        => $base_url,
            'messages'        => $messages,
            'errors'          => $errors,
            'filters'         => $filters,
            'dealers'         => $dealers,
            'receipts'        => $receipts,
            'shipments'       => $shipments,
            'receipt_detail'  => $receipt_detail,
            'receipt_items'   => $receipt_items,
            'receipt_summary' => $receipt_summary,
            'shipment_detail' => $shipment_detail,
            'shipment_items'  => $shipment_items,
            'can_export'      => $can_export,
        ];

        return AEGIS_Portal::render_portal_template('reports', $context);
    }

    protected static function is_dealer($user) {
        if (!$user || empty($user->roles)) {
            return false;
        }
        return in_array('aegis_dealer', (array) $user->roles, true);
    }

    protected static function user_can_view($user) {
        return AEGIS_System_Roles::user_can_manage_warehouse() || AEGIS_System_Roles::user_can_manage_system();
    }

    protected static function user_can_export($user) {
        return AEGIS_System_Roles::user_can_manage_system() || in_array('aegis_hq_admin', (array) $user->roles, true);
    }

    protected static function parse_filters() {
        $default_start = gmdate('Y-m-d', current_time('timestamp') - 6 * DAY_IN_SECONDS);
        $default_end = gmdate('Y-m-d', current_time('timestamp'));

        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : '';
        if ('' === $start_date) {
            $start_date = $default_start;
        }
        if ('' === $end_date) {
            $end_date = $default_end;
        }

        $sku = isset($_GET['sku']) ? sanitize_text_field(wp_unslash($_GET['sku'])) : '';
        $dealer_id = isset($_GET['dealer_id']) ? (int) $_GET['dealer_id'] : 0;

        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }

        $receipt_page = isset($_GET['receipt_page']) ? max(1, (int) $_GET['receipt_page']) : 1;
        $shipment_page = isset($_GET['shipment_page']) ? max(1, (int) $_GET['shipment_page']) : 1;

        return [
            'start_date'       => $start_date,
            'end_date'         => $end_date,
            'start_datetime'   => self::normalize_date_boundary($start_date, 'start'),
            'end_datetime'     => self::normalize_date_boundary($end_date, 'end'),
            'sku'              => $sku,
            'dealer_id'        => $dealer_id,
            'per_page'         => $per_page,
            'per_options'      => $per_page_options,
            'receipt_page'     => $receipt_page,
            'shipment_page'    => $shipment_page,
        ];
    }

    protected static function get_filter_query_args($filters) {
        return [
            'start_date' => $filters['start_date'],
            'end_date'   => $filters['end_date'],
            'dealer_id'  => $filters['dealer_id'],
            'sku'        => $filters['sku'],
            'per_page'   => $filters['per_page'],
        ];
    }

    protected static function handle_export_summary($filters, $can_export) {
        if (!$can_export) {
            return new WP_Error('export_denied', '当前账号无权导出报表。');
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aegis_reports_export')) {
            return new WP_Error('bad_nonce', '安全校验失败。');
        }

        $receipt_total = 0;
        $shipment_total = 0;
        $receipts = self::query_receipts(
            $filters['start_datetime'],
            $filters['end_datetime'],
            $filters['sku'],
            0,
            1,
            $receipt_total,
            true
        );
        $shipments = self::query_shipments(
            $filters['start_datetime'],
            $filters['end_datetime'],
            $filters['dealer_id'],
            $filters['sku'],
            0,
            1,
            $shipment_total,
            true
        );

        $rows_exported = count($receipts) + count($shipments);
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_REPORT_EXPORT, 'SUCCESS', [
            'scope'       => 'summary',
            'filters'     => self::get_filter_query_args($filters),
            'row_count'   => $rows_exported,
        ]);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reports-summary.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['type', 'order_no', 'time', 'operator', 'dealer', 'total_qty', 'sku_count']);
        foreach ($receipts as $receipt) {
            $user = $receipt->created_by ? get_userdata($receipt->created_by) : null;
            fputcsv($output, [
                'receipt',
                $receipt->receipt_no,
                $receipt->created_at,
                $user ? $user->user_login : '',
                '',
                (int) $receipt->item_count,
                (int) $receipt->sku_count,
            ]);
        }
        foreach ($shipments as $shipment) {
            $user = $shipment->created_by ? get_userdata($shipment->created_by) : null;
            fputcsv($output, [
                'shipment',
                $shipment->shipment_no,
                $shipment->created_at,
                $user ? $user->user_login : '',
                $shipment->dealer_name,
                (int) $shipment->item_count,
                (int) $shipment->sku_count,
            ]);
        }
        fclose($output);
        exit;
    }

    protected static function handle_export_receipt_detail($receipt_id, $can_export) {
        if (!$can_export) {
            return new WP_Error('export_denied', '当前账号无权导出入库明细。');
        }
        if ($receipt_id <= 0) {
            return new WP_Error('invalid_receipt', '入库单不存在。');
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aegis_reports_receipt_export_' . $receipt_id)) {
            return new WP_Error('bad_nonce', '安全校验失败。');
        }

        $receipt = self::get_receipt($receipt_id);
        if (!$receipt) {
            return new WP_Error('missing_receipt', '入库单不存在。');
        }

        $items = self::get_receipt_items($receipt_id, true);
        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_REPORT_EXPORT, 'SUCCESS', [
            'scope'      => 'receipt_detail',
            'receipt_id' => $receipt_id,
            'row_count'  => count($items),
        ]);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="receipt-' . $receipt->receipt_no . '-detail.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['receipt_no', 'code', 'ean', 'product_name', 'stocked_at']);
        foreach ($items as $item) {
            fputcsv($output, [$receipt->receipt_no, $item->code, $item->ean, $item->product_name, $item->created_at]);
        }
        fclose($output);
        exit;
    }

    protected static function handle_export_shipment_detail($shipment_id, $can_export) {
        if (!$can_export) {
            return new WP_Error('export_denied', '当前账号无权导出出库明细。');
        }
        if ($shipment_id <= 0) {
            return new WP_Error('invalid_shipment', '出库单不存在。');
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aegis_reports_shipment_export_' . $shipment_id)) {
            return new WP_Error('bad_nonce', '安全校验失败。');
        }

        $shipment = self::get_shipment($shipment_id);
        if (!$shipment) {
            return new WP_Error('missing_shipment', '出库单不存在。');
        }

        $items = self::get_shipment_items($shipment_id, true);
        $dealer_name = self::get_dealer_name($shipment->dealer_id);

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_REPORT_EXPORT, 'SUCCESS', [
            'scope'       => 'shipment_detail',
            'shipment_id' => $shipment_id,
            'row_count'   => count($items),
        ]);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="shipment-' . $shipment->shipment_no . '-detail.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['shipment_no', 'dealer', 'code', 'ean', 'product_name', 'scanned_at']);
        foreach ($items as $item) {
            fputcsv($output, [$shipment->shipment_no, $dealer_name, $item->code_value, $item->ean, $item->product_name, $item->scanned_at]);
        }
        fclose($output);
        exit;
    }

    protected static function query_receipts($start, $end, $sku_search, $per_page, $paged, &$total, $for_export = false) {
        global $wpdb;
        $receipt_table = $wpdb->prefix . AEGIS_System::RECEIPT_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RECEIPT_ITEM_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $where_parts = ['r.created_at BETWEEN %s AND %s'];
        $params = [$start, $end];
        if ($sku_search) {
            $like = '%' . $wpdb->esc_like($sku_search) . '%';
            $where_parts[] = "r.id IN (SELECT ri2.receipt_id FROM {$item_table} ri2 LEFT JOIN {$sku_table} s2 ON ri2.ean = s2.ean WHERE (ri2.ean LIKE %s OR s2.product_name LIKE %s))";
            $params[] = $like;
            $params[] = $like;
        }
        $where = implode(' AND ', $where_parts);

        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$receipt_table} r WHERE {$where}", $params));

        $sql = "SELECT r.*, COUNT(ri.id) as item_count, COUNT(DISTINCT ri.ean) as sku_count
            FROM {$receipt_table} r
            LEFT JOIN {$item_table} ri ON r.id = ri.receipt_id
            WHERE {$where}
            GROUP BY r.id
            ORDER BY r.created_at DESC";

        if (!$for_export) {
            $offset = ($paged - 1) * $per_page;
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = $per_page;
            $params[] = $offset;
        }

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    protected static function query_shipments($start, $end, $dealer_id, $sku_search, $per_page, $paged, &$total, $for_export = false) {
        global $wpdb;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $where_parts = ['s.created_at BETWEEN %s AND %s'];
        $params = [$start, $end];
        if ($dealer_id > 0) {
            $where_parts[] = 's.dealer_id = %d';
            $params[] = $dealer_id;
        }
        if ($sku_search) {
            $like = '%' . $wpdb->esc_like($sku_search) . '%';
            $where_parts[] = "s.id IN (SELECT si2.shipment_id FROM {$item_table} si2 JOIN {$code_table} c2 ON si2.code_id = c2.id LEFT JOIN {$sku_table} sk2 ON c2.ean = sk2.ean WHERE (c2.ean LIKE %s OR sk2.product_name LIKE %s))";
            $params[] = $like;
            $params[] = $like;
        }
        $where = implode(' AND ', $where_parts);

        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$shipment_table} s WHERE {$where}", $params));

        $sql = "SELECT s.*, d.dealer_name, COUNT(si.id) as item_count, COUNT(DISTINCT si.ean) as sku_count
            FROM {$shipment_table} s
            LEFT JOIN {$dealer_table} d ON s.dealer_id = d.id
            LEFT JOIN {$item_table} si ON s.id = si.shipment_id
            WHERE {$where}
            GROUP BY s.id
            ORDER BY s.created_at DESC";

        if (!$for_export) {
            $offset = ($paged - 1) * $per_page;
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = $per_page;
            $params[] = $offset;
        }

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    protected static function get_receipt($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RECEIPT_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    protected static function get_receipt_items($receipt_id, $with_code = true) {
        global $wpdb;
        $item_table = $wpdb->prefix . AEGIS_System::RECEIPT_ITEM_TABLE;
        if (!$with_code) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$item_table} WHERE receipt_id = %d ORDER BY id ASC", $receipt_id));
        }
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $sql = $wpdb->prepare(
            "SELECT ri.*, c.code, c.ean, s.product_name FROM {$item_table} ri JOIN {$code_table} c ON ri.code_id = c.id LEFT JOIN {$sku_table} s ON c.ean = s.ean WHERE ri.receipt_id = %d ORDER BY ri.id ASC",
            $receipt_id
        );
        return $wpdb->get_results($sql);
    }

    protected static function get_receipt_summary($receipt_id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RECEIPT_ITEM_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT COUNT(1) AS total, COUNT(DISTINCT ean) AS sku_count FROM {$table} WHERE receipt_id = %d", $receipt_id));
    }

    protected static function get_shipment($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    protected static function get_shipment_items($shipment_id, $with_product = false) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        if ($with_product) {
            $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
            $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
            $sql = $wpdb->prepare(
                "SELECT si.*, c.code as code_value, c.ean, s.product_name FROM {$table} si JOIN {$code_table} c ON si.code_id = c.id LEFT JOIN {$sku_table} s ON c.ean = s.ean WHERE si.shipment_id = %d ORDER BY si.id ASC",
                $shipment_id
            );
            return $wpdb->get_results($sql);
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE shipment_id = %d ORDER BY scanned_at DESC", $shipment_id));
    }

    protected static function get_dealer_name($dealer_id) {
        if ($dealer_id <= 0) {
            return '';
        }
        global $wpdb;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $name = $wpdb->get_var($wpdb->prepare("SELECT dealer_name FROM {$dealer_table} WHERE id = %d", $dealer_id));
        return $name ? $name : '';
    }

    protected static function get_dealers() {
        global $wpdb;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_results("SELECT id, dealer_name FROM {$dealer_table} ORDER BY dealer_name ASC");
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
