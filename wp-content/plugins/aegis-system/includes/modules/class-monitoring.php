<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Monitoring {
    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('monitoring')) {
            return '<div class="aegis-t-a5">监控模块未启用，请联系管理员。</div>';
        }

        $user = wp_get_current_user();
        if (self::is_dealer($user)) {
            return '<div class="aegis-t-a5">当前账号无权访问监控模块。</div>';
        }

        if (!self::user_can_view($user)) {
            return '<div class="aegis-t-a5">当前账号无权访问监控模块。</div>';
        }

        $base_url = add_query_arg('m', 'monitoring', $portal_url);
        $messages = [];
        $errors = [];
        $can_export = self::user_can_export($user);

        $filters = self::parse_filters();

        if (isset($_GET['monitoring_action'])) {
            $action = sanitize_key(wp_unslash($_GET['monitoring_action']));
            if ('export' === $action) {
                $result = self::handle_export($filters, $can_export);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            }
        }

        $rows = self::get_monitoring_rows($filters);
        $total_rows = count($rows);
        $filters['total_rows'] = $total_rows;
        $filters['total_pages'] = $filters['per_page'] > 0 ? max(1, (int) ceil($total_rows / $filters['per_page'])) : 1;
        $offset = ($filters['paged'] - 1) * $filters['per_page'];
        $paged_rows = array_slice($rows, $offset, $filters['per_page']);

        $dealers = self::get_dealers();

        $context = [
            'base_url'   => $base_url,
            'messages'   => $messages,
            'errors'     => $errors,
            'filters'    => $filters,
            'dealers'    => $dealers,
            'rows'       => $paged_rows,
            'can_export' => $can_export,
        ];

        return AEGIS_Portal::render_portal_template('monitoring', $context);
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

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        return [
            'start_date'       => $start_date,
            'end_date'         => $end_date,
            'start_datetime'   => self::normalize_date_boundary($start_date, 'start'),
            'end_datetime'     => self::normalize_date_boundary($end_date, 'end'),
            'sku'              => $sku,
            'dealer_id'        => $dealer_id,
            'per_page'         => $per_page,
            'per_options'      => $per_page_options,
            'paged'            => $paged,
        ];
    }

    protected static function handle_export($filters, $can_export) {
        if (!$can_export) {
            return new WP_Error('export_denied', '当前账号无权导出监控表。');
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aegis_monitoring_export')) {
            return new WP_Error('bad_nonce', '安全校验失败。');
        }

        $rows = self::get_monitoring_rows($filters);

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_MONITOR_EXPORT, 'SUCCESS', [
            'filters'   => [
                'start_date' => $filters['start_date'],
                'end_date'   => $filters['end_date'],
                'dealer_id'  => $filters['dealer_id'],
                'sku'        => $filters['sku'],
            ],
            'row_count' => count($rows),
        ]);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="monitoring.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['dealer', 'query_count', 'reset_count', 'shipment_qty']);
        foreach ($rows as $row) {
            fputcsv($output, [$row['dealer_name'], $row['query_count'], $row['reset_count'], $row['shipment_qty']]);
        }
        fclose($output);
        exit;
    }

    protected static function get_monitoring_rows($filters) {
        $query_counts = self::get_query_counts($filters);
        $reset_counts = self::get_reset_counts($filters);
        $shipment_counts = self::get_shipment_counts($filters);
        $dealer_map = self::get_dealer_map();

        $dealer_ids = array_unique(array_merge(array_keys($query_counts), array_keys($reset_counts), array_keys($shipment_counts)));
        if (empty($dealer_ids)) {
            $dealer_ids = [];
        }

        $rows = [];
        foreach ($dealer_ids as $dealer_id) {
            if ($filters['dealer_id'] > 0 && (int) $dealer_id !== (int) $filters['dealer_id']) {
                continue;
            }
            $label = $dealer_id && isset($dealer_map[$dealer_id]) ? $dealer_map[$dealer_id] : '总部销售';
            $rows[] = [
                'dealer_id'    => (int) $dealer_id,
                'dealer_name'  => $label,
                'query_count'  => isset($query_counts[$dealer_id]) ? (int) $query_counts[$dealer_id] : 0,
                'reset_count'  => isset($reset_counts[$dealer_id]) ? (int) $reset_counts[$dealer_id] : 0,
                'shipment_qty' => isset($shipment_counts[$dealer_id]) ? (int) $shipment_counts[$dealer_id] : 0,
            ];
        }

        usort($rows, static function ($a, $b) {
            if ($a['query_count'] === $b['query_count']) {
                return $b['reset_count'] <=> $a['reset_count'];
            }
            return $b['query_count'] <=> $a['query_count'];
        });

        return $rows;
    }

    protected static function get_query_counts($filters) {
        global $wpdb;
        $query_table = $wpdb->prefix . AEGIS_System::QUERY_LOG_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $joins = "LEFT JOIN {$shipment_item_table} si ON ql.code_id = si.code_id LEFT JOIN {$shipment_table} s ON si.shipment_id = s.id";
        $where_parts = ['ql.query_channel = %s', 'ql.created_at BETWEEN %s AND %s'];
        $params = ['B', $filters['start_datetime'], $filters['end_datetime']];

        if ($filters['sku']) {
            $like = '%' . $wpdb->esc_like($filters['sku']) . '%';
            $joins .= " JOIN {$code_table} c ON ql.code_id = c.id LEFT JOIN {$sku_table} sk ON c.ean = sk.ean";
            $where_parts[] = '(c.ean LIKE %s OR sk.product_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $where = implode(' AND ', $where_parts);
        $sql = "SELECT COALESCE(s.dealer_id, 0) as dealer_id, COUNT(ql.id) as query_count FROM {$query_table} ql {$joins} WHERE {$where} GROUP BY dealer_id";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row->dealer_id] = (int) $row->query_count;
        }
        return $counts;
    }

    protected static function get_reset_counts($filters) {
        global $wpdb;
        $reset_table = $wpdb->prefix . AEGIS_System::RESET_LOG_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $joins = '';
        $where_parts = ['reset_at BETWEEN %s AND %s'];
        $params = [$filters['start_datetime'], $filters['end_datetime']];

        if ($filters['sku']) {
            $like = '%' . $wpdb->esc_like($filters['sku']) . '%';
            $joins = " JOIN {$code_table} c ON r.code_id = c.id LEFT JOIN {$sku_table} sk ON c.ean = sk.ean";
            $where_parts[] = '(c.ean LIKE %s OR sk.product_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $where = implode(' AND ', $where_parts);
        $sql = "SELECT COALESCE(r.dealer_id, 0) as dealer_id, COUNT(r.id) as reset_count FROM {$reset_table} r {$joins} WHERE {$where} GROUP BY dealer_id";
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row->dealer_id] = (int) $row->reset_count;
        }
        return $counts;
    }

    protected static function get_shipment_counts($filters) {
        global $wpdb;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        if ($filters['sku']) {
            $like = '%' . $wpdb->esc_like($filters['sku']) . '%';
            $sql = "SELECT COALESCE(s.dealer_id, 0) as dealer_id, COUNT(si.id) as shipment_qty
                FROM {$shipment_item_table} si
                JOIN {$shipment_table} s ON si.shipment_id = s.id
                JOIN {$code_table} c ON si.code_id = c.id
                LEFT JOIN {$sku_table} sk ON c.ean = sk.ean
                WHERE s.created_at BETWEEN %s AND %s AND (c.ean LIKE %s OR sk.product_name LIKE %s)
                GROUP BY dealer_id";
            $results = $wpdb->get_results($wpdb->prepare($sql, [$filters['start_datetime'], $filters['end_datetime'], $like, $like]));
        } else {
            $sql = "SELECT COALESCE(s.dealer_id, 0) as dealer_id, SUM(s.qty) as shipment_qty
                FROM {$shipment_table} s
                WHERE s.created_at BETWEEN %s AND %s
                GROUP BY dealer_id";
            $results = $wpdb->get_results($wpdb->prepare($sql, [$filters['start_datetime'], $filters['end_datetime']]));
        }

        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row->dealer_id] = (int) $row->shipment_qty;
        }
        return $counts;
    }

    protected static function get_dealers() {
        global $wpdb;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_results("SELECT id, dealer_name FROM {$dealer_table} ORDER BY dealer_name ASC");
    }

    protected static function get_dealer_map() {
        $dealers = self::get_dealers();
        $map = [];
        foreach ($dealers as $dealer) {
            $map[(int) $dealer->id] = $dealer->dealer_name;
        }
        return $map;
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
