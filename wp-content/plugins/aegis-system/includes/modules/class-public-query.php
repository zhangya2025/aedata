<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Public_Query {
    const CONTEXT_PUBLIC = 'public';
    const CONTEXT_INTERNAL = 'internal';

    /**
     * 后台内部查询页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('public_query')) {
            echo '<div class="wrap aegis-system-root"><h1 class="aegis-t-a3">防伪码查询</h1><div class="notice notice-warning"><p class="aegis-t-a6">请先启用公开查询模块。</p></div></div>';
            return;
        }

        $messages = [];
        $errors = [];
        $result = null;

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $whitelist = ['public_query_action', 'code_value', 'aegis_public_query_nonce', '_wp_http_referer'];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'   => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                    'nonce_field'  => 'aegis_public_query_nonce',
                    'nonce_action' => 'aegis_public_query_action',
                    'whitelist'    => $whitelist,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } else {
                $code_value = isset($_POST['code_value']) ? sanitize_text_field(wp_unslash($_POST['code_value'])) : '';
                $result = self::handle_query($code_value, self::CONTEXT_INTERNAL, false);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                    $result = null;
                }
            }
        }

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a3">防伪码查询</h1>';
        if (!empty($messages)) {
            foreach ($messages as $msg) {
                echo '<div class="updated"><p class="aegis-t-a6">' . esc_html($msg) . '</p></div>';
            }
        }
        if (!empty($errors)) {
            foreach ($errors as $err) {
                echo '<div class="error"><p class="aegis-t-a6">' . esc_html($err) . '</p></div>';
            }
        }

        echo '<form method="post" class="aegis-t-a6" style="margin-top:12px;">';
        wp_nonce_field('aegis_public_query_action', 'aegis_public_query_nonce');
        echo '<input type="hidden" name="public_query_action" value="query" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">防伪码</th><td><input type="text" name="code_value" value="" class="regular-text" required /></td></tr>';
        echo '</tbody></table>';
        submit_button('查询');
        echo '</form>';

        if ($result) {
            self::render_result($result, true);
        }

        echo '</div>';
    }

    /**
     * 前台短码渲染。
     */
    public static function render_shortcode($atts = []) {
        if (!AEGIS_System::is_module_enabled('public_query')) {
            return '';
        }

        AEGIS_Assets_Media::enqueue_typography_style('aegis-system-frontend-style');

        $messages = [];
        $errors = [];
        $result = null;
        $context = self::CONTEXT_PUBLIC;
        $is_internal = is_user_logged_in() && AEGIS_System_Roles::user_can_manage_warehouse() && isset($_GET['internal']);
        if ($is_internal) {
            $context = self::CONTEXT_INTERNAL;
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $nonce = isset($_POST['aegis_public_query_nonce']) ? $_POST['aegis_public_query_nonce'] : '';
            if (!wp_verify_nonce($nonce, 'aegis_public_query_action')) {
                $errors[] = '安全校验失败，请重试。';
            } else {
                $code_value = isset($_POST['code_value']) ? sanitize_text_field(wp_unslash($_POST['code_value'])) : '';
                $result = self::handle_query($code_value, $context, self::CONTEXT_PUBLIC === $context);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                    $result = null;
                }
            }
        }

        ob_start();
        echo '<div class="aegis-system-root aegis-t-a6" style="padding:12px;">';
        echo '<h2 class="aegis-t-a4">防伪码查询</h2>';
        foreach ($messages as $msg) {
            echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $err) {
            echo '<div class="error"><p>' . esc_html($err) . '</p></div>';
        }
        echo '<form method="post" class="aegis-public-query-form" style="margin-top:8px;">';
        wp_nonce_field('aegis_public_query_action', 'aegis_public_query_nonce');
        echo '<label class="aegis-t-a6" for="aegis-code-input">请输入防伪码：</label><br />';
        echo '<input id="aegis-code-input" type="text" name="code_value" required style="min-width:260px;" /> ';
        echo '<button type="submit">查询</button>';
        echo '</form>';

        if ($result) {
            self::render_result($result, false);
        }
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * 展示查询结果。
     */
    protected static function render_result($result, $is_internal) {
        echo '<div class="aegis-public-query-result" style="margin-top:16px;">';
        echo '<h3 class="aegis-t-a4">查询结果</h3>';
        echo '<ul class="aegis-t-a6">';
        echo '<li><strong>防伪码：</strong>' . esc_html($result['code']) . '</li>';
        echo '<li><strong>产品：</strong>' . esc_html($result['product']) . '</li>';
        echo '<li><strong>状态：</strong>' . esc_html($result['status_label']) . '</li>';
        echo '<li><strong>经销商：</strong>' . esc_html($result['dealer_label']) . '</li>';
        if (!empty($result['shipment_no'])) {
            echo '<li><strong>出库单号：</strong>' . esc_html($result['shipment_no']) . '</li>';
            echo '<li><strong>出库时间：</strong>' . esc_html($result['shipment_time']) . '</li>';
        }
        echo '<li><strong>A 计数：</strong>' . esc_html($result['counts']['a']) . '</li>';
        echo '<li><strong>B 计数：</strong>' . esc_html($result['counts']['b']) . '</li>';
        if (!empty($result['last_query_at'])) {
            echo '<li><strong>最近查询：</strong>' . esc_html($result['last_query_at']) . '</li>';
        }
        if (!empty($result['certificate'])) {
            echo '<li><strong>证书：</strong><a href="' . esc_url($result['certificate']['url']) . '" target="_blank" rel="noopener">下载</a></li>';
        }
        if ($is_internal && !empty($result['raw_b'])) {
            echo '<li><strong>B 原始累积：</strong>' . esc_html($result['raw_b']) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    /**
     * 查询处理。
     */
    protected static function handle_query($code_value, $context, $enforce_rate_limit) {
        $code_value = trim((string) $code_value);
        if ('' === $code_value) {
            return new WP_Error('empty_code', '请输入防伪码。');
        }

        if ($enforce_rate_limit && !self::check_rate_limit()) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_PUBLIC_QUERY_RATE_LIMIT,
                'FAIL',
                ['ip' => self::get_client_ip()]
            );
            return new WP_Error('rate_limited', '查询过于频繁，请稍后再试。');
        }

        $record = self::get_code_record($code_value);
        if (!$record) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_PUBLIC_QUERY,
                'FAIL',
                ['code' => $code_value, 'reason' => 'not_found', 'context' => $context]
            );
            return new WP_Error('code_not_found', '未找到对应防伪码。');
        }

        $counts = self::increment_counters((int) $record->id, $record->code, $context);
        $shipment = self::get_latest_shipment((int) $record->id);
        $sku = self::get_sku($record->ean);
        $certificate = self::get_public_certificate($sku ? $sku->certificate_id : 0);

        $dealer_label = self::get_hq_label();
        $shipment_no = '';
        $shipment_time = '';
        $dealer_id = null;
        if ($shipment) {
            $dealer_label = $shipment->dealer_name ? $shipment->dealer_name : $dealer_label;
            $shipment_no = $shipment->shipment_no;
            $shipment_time = $shipment->scanned_at;
            $dealer_id = $shipment->dealer_id;
        }

        $result = [
            'code'          => $record->code,
            'ean'           => $record->ean,
            'product'       => $sku ? $sku->product_name : '未知产品',
            'status_label'  => 'used' === $record->status ? '已出库' : '未出库',
            'dealer_label'  => $dealer_label,
            'shipment_no'   => $shipment_no,
            'shipment_time' => $shipment_time,
            'counts'        => $counts,
            'raw_b'         => $counts['raw_b'],
            'last_query_at' => $counts['last_query_at'],
            'certificate'   => $certificate,
        ];

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_PUBLIC_QUERY,
            'SUCCESS',
            [
                'code_id'  => (int) $record->id,
                'context'  => $context,
                'ean'      => $record->ean,
                'dealer'   => $dealer_id,
                'counts'   => $counts,
            ]
        );

        return $result;
    }

    /**
     * 拉取码数据。
     */
    protected static function get_code_record($code_value) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s", $code_value));
    }

    /**
     * 获取 SKU 数据。
     */
    protected static function get_sku($ean) {
        if (!$ean) {
            return null;
        }
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE ean = %s", $ean));
    }

    /**
     * 取最新出库信息。
     */
    protected static function get_latest_shipment($code_id) {
        global $wpdb;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT si.scanned_at, si.code_id, si.ean, sh.shipment_no, sh.dealer_id, d.dealer_name FROM {$shipment_item_table} si LEFT JOIN {$shipment_table} sh ON si.shipment_id = sh.id LEFT JOIN {$dealer_table} d ON sh.dealer_id = d.id WHERE si.code_id = %d ORDER BY si.scanned_at DESC LIMIT 1",
                $code_id
            )
        );
    }

    /**
     * 仅返回公开证书。
     */
    protected static function get_public_certificate($media_id) {
        $media_id = (int) $media_id;
        if ($media_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND visibility = %s AND owner_type = %s AND deleted_at IS NULL",
                $media_id,
                AEGIS_Assets_Media::VISIBILITY_PUBLIC,
                'certificate'
            )
        );

        if (!$record) {
            return null;
        }

        return [
            'id'  => (int) $record->id,
            'url' => rest_url('aegis-system/v1/media/download/' . (int) $record->id),
        ];
    }

    /**
     * 计数累加与日志记录。
     */
    protected static function increment_counters($code_id, $code_value, $context) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $now = current_time('mysql');
        $is_public = self::CONTEXT_PUBLIC === $context;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET query_a_count = query_a_count + 1, query_b_count = query_b_count + %d, last_query_at = %s WHERE id = %d",
                $is_public ? 1 : 0,
                $now,
                $code_id
            )
        );

        self::insert_query_log($code_id, $code_value, 'A', $context);
        if ($is_public) {
            self::insert_query_log($code_id, $code_value, 'B', $context);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT query_a_count, query_b_count, query_b_offset, last_query_at FROM {$table} WHERE id = %d", $code_id));
        $b_effective = 0;
        $raw_b = 0;
        if ($row) {
            $raw_b = (int) $row->query_b_count;
            $b_effective = max(0, $raw_b - (int) $row->query_b_offset);
        }

        return [
            'a'             => $row ? (int) $row->query_a_count : 0,
            'b'             => $b_effective,
            'raw_b'         => $raw_b,
            'last_query_at' => $row ? $row->last_query_at : '',
        ];
    }

    /**
     * 写入查询日志。
     */
    protected static function insert_query_log($code_id, $code_value, $channel, $context) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::QUERY_LOG_TABLE;
        $wpdb->insert(
            $table,
            [
                'code_id'      => $code_id,
                'code_value'   => $code_value,
                'query_channel'=> $channel,
                'context'      => $context,
                'client_ip'    => self::get_client_ip(),
                'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? wp_strip_all_tags((string) wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * 简单频率限制。
     */
    protected static function check_rate_limit() {
        $ip = self::get_client_ip();
        if ('' === $ip) {
            return true;
        }
        $key = 'aegis_query_rate_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= 5) {
            return false;
        }
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * 获取总部显示名。
     */
    protected static function get_hq_label() {
        $label = get_option(AEGIS_System::HQ_DISPLAY_OPTION, '总部销售');
        if (!is_string($label) || '' === trim($label)) {
            $label = '总部销售';
        }
        return $label;
    }

    /**
     * 获取客户端 IP。
     */
    protected static function get_client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        return sanitize_text_field($ip);
    }
}

