<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Public_Query {
    const CONTEXT_PUBLIC = 'public';
    const CONTEXT_INTERNAL = 'internal';
    const PAGE_SLUG = 'aegis-query';
    const PAGE_TITLE = '防伪码查询';

    /**
     * 确保公共查询页面存在。
     *
     * @param bool $force
     * @return int|null
     */
    public static function ensure_public_page($force = false) {
        $page = get_page_by_path(self::PAGE_SLUG);
        if ($page && 'trash' === $page->post_status) {
            wp_untrash_post($page->ID);
            $page = get_post($page->ID);
        }

        if (!$page) {
            $page_id = wp_insert_post(
                [
                    'post_title'   => self::PAGE_TITLE,
                    'post_name'    => self::PAGE_SLUG,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_content' => '[aegis_query]',
                ],
                true
            );

            if (!is_wp_error($page_id)) {
                return (int) $page_id;
            }

            return null;
        }

        if ($force && false === strpos((string) $page->post_content, '[aegis_query]')) {
            wp_update_post(
                [
                    'ID'           => $page->ID,
                    'post_content' => '[aegis_query]',
                ]
            );
        }

        return (int) $page->ID;
    }

    /**
     * 获取公共查询页链接。
     *
     * @return string
     */
    public static function get_public_page_url() {
        $page_id = self::ensure_public_page();
        if ($page_id) {
            $link = get_permalink($page_id);
            if ($link) {
                return $link;
            }
        }

        return home_url('/' . self::PAGE_SLUG . '/');
    }

    /**
     * Portal 查询面板。
     *
     * @param string $portal_url
     * @return string
     */
    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('public_query')) {
            return '<div class="aegis-t-a5">公共查询模块未启用，请联系管理员。</div>';
        }

        $base_url = add_query_arg('m', 'public_query', $portal_url);
        $public_url = self::get_public_page_url();
        $raw_code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $normalized_code = self::normalize_code_value($raw_code);
        $result = null;

        if ('' !== $raw_code) {
            $result = self::handle_portal_query($normalized_code);
        }

        $context = [
            'base_url'   => $base_url,
            'public_url' => $public_url,
            'query_code' => $raw_code,
            'result'     => $result,
        ];

        return AEGIS_Portal::render_portal_template('public-query', $context);
    }

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
            self::render_result($result);
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
        $is_internal = false;
        if (is_user_logged_in() && AEGIS_System_Roles::user_can_manage_warehouse()) {
            $context = self::CONTEXT_INTERNAL;
            $is_internal = true;
        }
        $is_public_user = self::is_public_user();

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $nonce = isset($_POST['aegis_public_query_nonce']) ? $_POST['aegis_public_query_nonce'] : '';
            if (!wp_verify_nonce($nonce, 'aegis_public_query_action')) {
                $errors[] = '安全校验失败，请重试。';
            } else {
                $code_value = isset($_POST['code_value']) ? sanitize_text_field(wp_unslash($_POST['code_value'])) : '';
                $handled = self::handle_query($code_value, $context, self::CONTEXT_PUBLIC === $context);
                if (is_wp_error($handled)) {
                    if ($is_public_user && 'code_not_found' === $handled->get_error_code()) {
                        $result = self::build_public_result(null, 0, true);
                    } else {
                        $errors[] = $handled->get_error_message();
                    }
                } else {
                    $result = $handled;
                    if ($is_public_user) {
                        $display_value = 0;
                        if (isset($result['counts']['b'])) {
                            $display_value = max(0, (int) $result['counts']['b']);
                        }
                        $result = self::build_public_result($handled, $display_value, false);
                    }
                }
            }
        }

        ob_start();
        echo '<div class="aegis-system-root aegis-query-root">';
        echo '<div class="aegis-query-card">';
        echo '<div class="aegis-t-a3" style="margin-bottom:4px;">防伪码查询</div>';
        echo '<div class="aegis-t-a6" style="color:#506176;">输入或扫码防伪码，查询出库经销商与产品信息。</div>';

        if (!empty($messages)) {
            foreach ($messages as $msg) {
                echo '<div class="updated aegis-t-a6" style="margin-top:10px;"><p>' . esc_html($msg) . '</p></div>';
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $err) {
                echo '<div class="aegis-query-error aegis-t-a6">' . esc_html($err) . '</div>';
            }
        }

        echo '<form method="post" class="aegis-query-form" novalidate>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_nonce_field('aegis_public_query_action', 'aegis_public_query_nonce');
        echo '<input type="hidden" name="public_query_action" value="query" />';
        echo '<label class="aegis-t-a6" for="aegis-code-input">防伪码</label>';
        echo '<input id="aegis-code-input" type="text" name="code_value" value="" placeholder="请输入或扫码防伪码" required />';
        echo '<div class="aegis-query-actions">';
        echo '<button type="submit" class="aegis-t-a6">查询</button>';
        if ($is_internal) {
            echo '<div class="aegis-query-helper">内部模式：仅计入 A 计数</div>';
        }
        echo '</div>';
        echo '</form>';

        if ($result) {
            self::render_result($result);
        }

        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * 展示查询结果。
     */
    protected static function render_result($result) {
        if (!empty($result['public_mode'])) {
            $display_value = isset($result['display_value']) ? (int) $result['display_value'] : 0;
            echo '<div class="aegis-query-result">';
            if (!empty($result['not_found'])) {
                echo '<div class="aegis-t-a4" style="margin-top:8px;">' . esc_html($display_value) . '</div>';
                echo '</div>';
                return;
            }

            echo '<div class="aegis-t-a5" style="margin-top:4px;">已查询到产品</div>';
            echo '<div class="aegis-query-meta aegis-t-a6">';
            echo '<div><span class="aegis-label">产品名称：</span>' . esc_html($result['product_label']) . '</div>';
            echo '<div><span class="aegis-label">防伪码：</span>' . esc_html(AEGIS_System::format_code_display($result['code'])) . '</div>';
            echo '<div><span class="aegis-label">SKU：</span>' . esc_html($result['ean']) . '</div>';
            echo '<div><span class="aegis-label">查询次数：</span>' . esc_html($display_value) . '</div>';
            echo '<div><span class="aegis-label">经销商：</span>' . esc_html($result['dealer_label']) . '</div>';
            if (!empty($result['certificate'])) {
                echo '<div class="aegis-query-cert"><span class="aegis-label">质检证书：</span><a href="' . esc_url($result['certificate']['url']) . '" target="_blank" rel="noopener">查看质检证书</a></div>';
            }
            echo '</div>';
            echo '</div>';
            return;
        }

        $status_class = !empty($result['status_class']) ? $result['status_class'] : 'status-warn';
        echo '<div class="aegis-query-result">';
        echo '<div class="aegis-status-badge ' . esc_attr($status_class) . ' aegis-t-a6">' . esc_html($result['status_label']) . '</div>';
        echo '<div class="aegis-t-a5" style="margin-top:8px;">防伪码：' . esc_html(AEGIS_System::format_code_display($result['code'])) . '</div>';
        echo '<div class="aegis-query-meta aegis-t-a6">';
        echo '<div><span class="aegis-label">产品：</span>' . esc_html($result['product']);
        if (!empty($result['sku_meta'])) {
            echo '（' . esc_html($result['sku_meta']) . '）';
        }
        echo '</div>';
        echo '<div><span class="aegis-label">EAN：</span>' . esc_html($result['ean']) . '</div>';
        echo '<div><span class="aegis-label">经销商：</span>' . esc_html($result['dealer_label']) . '</div>';
        echo '<div><span class="aegis-label">查询计数：</span>A=' . esc_html($result['counts']['a']) . ' / B=' . esc_html($result['counts']['b']) . '</div>';
        if (!empty($result['certificate'])) {
            echo '<div class="aegis-query-cert"><span class="aegis-label">证书：</span><a href="' . esc_url($result['certificate']['url']) . '" target="_blank" rel="noopener">查看证书</a></div>';
        }
        echo '</div>';
        echo '<div class="aegis-query-helper aegis-t-a6">' . esc_html($result['message']) . '</div>';
        echo '</div>';
    }

    /**
     * 查询处理。
     */
    protected static function handle_query($code_value, $context, $enforce_rate_limit) {
        return self::query_code($code_value, $context, $enforce_rate_limit, false);
    }

    /**
     * Portal 只读查询。
     *
     * @param string $code_value
     * @return array|WP_Error
     */
    public static function handle_portal_query($code_value) {
        return self::query_code($code_value, self::CONTEXT_INTERNAL, false, true);
    }

    /**
     * 查询处理核心逻辑。
     *
     * @param string $code_value
     * @param string $context
     * @param bool   $enforce_rate_limit
     * @param bool   $read_only
     * @return array|WP_Error
     */
    protected static function query_code($code_value, $context, $enforce_rate_limit, $read_only) {
        $code_value = self::normalize_code_value($code_value);
        $formatted_code = AEGIS_System::format_code_display($code_value);
        if ('' === $code_value) {
            return new WP_Error('empty_code', '请输入防伪码。');
        }

        if ($enforce_rate_limit && !self::check_rate_limit()) {
            if (!$read_only) {
                AEGIS_Access_Audit::record_event(
                    AEGIS_System::ACTION_PUBLIC_QUERY_RATE_LIMIT,
                    'FAIL',
                    ['ip' => self::get_client_ip()]
                );
            }
            return new WP_Error('rate_limited', '请求过于频繁，请稍后再试。');
        }

        $record = self::get_code_record($code_value);
        if (!$record) {
            if (!$read_only) {
                AEGIS_Access_Audit::record_event(
                    AEGIS_System::ACTION_PUBLIC_QUERY,
                    'FAIL',
                    ['code' => $code_value, 'reason' => 'not_found', 'context' => $context]
                );
            }
            return new WP_Error('code_not_found', '未查询到该防伪码：' . $formatted_code . '。');
        }

        $counts = $read_only
            ? self::build_counts_from_record($record)
            : self::increment_counters((int) $record->id, $record->code, $context);
        $sku = self::get_sku($record->ean);
        $certificate = self::get_public_certificate($sku ? $sku->certificate_id : 0);

        $stock_status = $record->stock_status ? $record->stock_status : 'generated';
        $result_type = 'not_shipped';
        $dealer_label = self::get_hq_label();
        $shipment = null;
        $shipment_info = null;

        if ('shipped' === $stock_status) {
            $shipment = self::get_latest_shipment((int) $record->id);
            if ($shipment && $shipment->dealer_name) {
                $dealer_label = $shipment->dealer_name;
            }
            if ($shipment) {
                $shipment_info = [
                    'scanned_at'  => $shipment->scanned_at,
                    'shipment_no' => $shipment->shipment_no,
                    'dealer_name' => $shipment->dealer_name,
                ];
            }
            $result_type = 'shipped';
        }

        $product_name = $sku ? $sku->product_name : '未知产品';
        $sku_meta_parts = [];
        if ($sku && !empty($sku->size_label)) {
            $sku_meta_parts[] = $sku->size_label;
        }
        if ($sku && !empty($sku->color_label)) {
            $sku_meta_parts[] = $sku->color_label;
        }

        $status_label = 'shipped' === $stock_status ? '已出库' : '未出库';
        $status_class = 'shipped' === $stock_status ? 'status-safe' : 'status-warn';
        $message = 'shipped' === $stock_status ? '该防伪码已出库。' : '该防伪码已生成但未出库。';

        $result = [
            'code'         => AEGIS_System::format_code_display($record->code),
            'ean'          => $record->ean,
            'product'      => $product_name,
            'sku_meta'     => implode(' / ', $sku_meta_parts),
            'status_label' => $status_label,
            'status_class' => $status_class,
            'dealer_label' => $dealer_label,
            'counts'       => $counts,
            'certificate'  => $certificate,
            'message'      => $message,
            'result_type'  => $result_type,
            'shipment'     => $shipment_info,
            'stock_status' => $stock_status,
        ];

        if (!$read_only) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_PUBLIC_QUERY,
                'SUCCESS',
                [
                    'code_id'     => (int) $record->id,
                    'context'     => $context,
                    'ean'         => $record->ean,
                    'dealer'      => $shipment ? $shipment->dealer_id : null,
                    'counts'      => $counts,
                    'result_type' => $result_type,
                ]
            );
        }

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
     * 只读计数读取。
     *
     * @param object $record
     * @return array
     */
    protected static function build_counts_from_record($record) {
        $raw_b = isset($record->query_b_count) ? (int) $record->query_b_count : 0;
        $offset = isset($record->query_b_offset) ? (int) $record->query_b_offset : 0;
        $b_effective = max(0, $raw_b - $offset);

        return [
            'a'             => isset($record->query_a_count) ? (int) $record->query_a_count : 0,
            'b'             => $b_effective,
            'raw_b'         => $raw_b,
            'last_query_at' => isset($record->last_query_at) ? $record->last_query_at : '',
        ];
    }

    /**
     * 防伪码统一规范化。
     *
     * @param string $code_value
     * @return string
     */
    protected static function normalize_code_value($code_value) {
        if (method_exists('AEGIS_System', 'normalize_code_value')) {
            return AEGIS_System::normalize_code_value($code_value);
        }

        $code_value = preg_replace('/[^a-zA-Z0-9]/', '', (string) $code_value);
        return strtoupper((string) $code_value);
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
     * 构建公共用户展示数据。
     *
     * @param array|null $handled
     * @param int        $display_value
     * @param bool       $not_found
     * @return array
     */
    protected static function build_public_result($handled, $display_value, $not_found) {
        if ($not_found || !$handled) {
            return [
                'public_mode'   => true,
                'not_found'     => true,
                'display_value' => (int) $display_value,
            ];
        }

        $product_label = isset($handled['product']) ? (string) $handled['product'] : '';
        if (!empty($handled['sku_meta'])) {
            $product_label .= '（' . $handled['sku_meta'] . '）';
        }

        return [
            'public_mode'   => true,
            'not_found'     => false,
            'display_value' => (int) $display_value,
            'product_label' => $product_label,
            'code'          => isset($handled['code']) ? $handled['code'] : '',
            'ean'           => isset($handled['ean']) ? $handled['ean'] : '',
            'dealer_label'  => isset($handled['dealer_label']) ? $handled['dealer_label'] : self::get_hq_label(),
            'certificate'   => !empty($handled['certificate']) ? $handled['certificate'] : null,
        ];
    }

    /**
     * 获取客户端 IP。
     */
    protected static function get_client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        return sanitize_text_field($ip);
    }

    /**
     * 是否为公共访问用户（游客或非业务角色）。
     *
     * @return bool
     */
    protected static function is_public_user() {
        if (!is_user_logged_in()) {
            return true;
        }

        $user = wp_get_current_user();
        return !AEGIS_System_Roles::is_business_user($user);
    }
}
