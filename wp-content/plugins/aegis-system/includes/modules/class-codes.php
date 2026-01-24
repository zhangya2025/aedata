<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Codes {
    const STATUS_UNUSED = 'unused';
    const STATUS_USED = 'used';

    /**
     * 渲染防伪码管理页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('codes')) {
            echo '<div class="wrap"><h1>防伪码生成</h1><div class="notice notice-warning"><p>请先在模块管理中启用编码管理模块。</p></div></div>';
            return;
        }

        $messages = [];
        $errors = [];

        if (isset($_GET['codes_action'])) {
            $action = sanitize_key(wp_unslash($_GET['codes_action']));
            $batch_id = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
            if ('export' === $action) {
                $result = self::handle_export($batch_id);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            } elseif ('print' === $action) {
                $result = self::handle_print($batch_id);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            }
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $whitelist = ['codes_action', 'ean', 'quantity', 'batch_note', '_wp_http_referer', '_aegis_idempotency', 'aegis_codes_nonce'];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                    'nonce_field'     => 'aegis_codes_nonce',
                    'nonce_action'    => 'aegis_codes_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => $idempotency,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } else {
                $result = self::handle_generate_request($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages = array_merge($messages, $result['messages']);
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
        $batches = self::query_batches($start_datetime, $end_datetime, $per_page, $paged, $total);
        $sku_options = self::get_sku_options();
        $view_batch = isset($_GET['view']) ? (int) $_GET['view'] : 0;
        $view_codes = $view_batch ? self::get_codes_for_batch($view_batch) : [];
        $view_batch_row = $view_batch ? self::get_batch($view_batch) : null;

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a2">防伪码生成</h1>';
        echo '<p class="aegis-t-a6">按 SKU 生成防伪码批次，默认显示最近 7 天记录。</p>';

        foreach ($messages as $msg) {
            echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $msg) {
            echo '<div class="error"><p>' . esc_html($msg) . '</p></div>';
        }

        self::render_form($sku_options);
        self::render_filters($start_date, $end_date, $per_page, $per_page_options);
        self::render_batches_table($batches, $per_page, $paged, $total, $start_date, $end_date);

        if ($view_batch && $view_batch_row) {
            self::render_codes_table($view_batch_row, $view_codes);
        }

        echo '</div>';
    }

    /**
     * 渲染生成表单。
     */
    protected static function render_form($sku_options) {
        $idempotency_key = wp_generate_uuid4();
        echo '<div class="aegis-t-a5" style="margin-top:20px;">';
        echo '<h2 class="aegis-t-a3">生成新批次</h2>';
        echo '<form method="post" class="aegis-t-a5">';
        wp_nonce_field('aegis_codes_action', 'aegis_codes_nonce');
        echo '<input type="hidden" name="codes_action" value="generate" />';
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';

        echo '<table class="form-table">';
        echo '<tr><th><label for="aegis-code-ean">SKU (EAN)</label></th><td>';
        echo '<select id="aegis-code-ean" name="ean" required>';
        echo '<option value="">选择 SKU</option>';
        foreach ($sku_options as $sku) {
            $label = $sku->ean . ' - ' . $sku->product_name;
            echo '<option value="' . esc_attr($sku->ean) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th><label for="aegis-code-qty">数量</label></th><td>';
        echo '<input type="number" id="aegis-code-qty" name="quantity" min="1" max="100" step="1" required />';
        echo '<p class="description aegis-t-a6">单 SKU 最多 100，单次提交总量不超过 300。</p>';
        echo '</td></tr>';

        echo '<tr><th><label for="aegis-code-note">备注（可选）</label></th><td>';
        echo '<input type="text" id="aegis-code-note" name="batch_note" class="regular-text" />';
        echo '</td></tr>';
        echo '</table>';

        submit_button('生成');
        echo '</form>';
        echo '</div>';
    }

    /**
     * 渲染筛选与分页设置。
     */
    protected static function render_filters($start_date, $end_date, $per_page, $options) {
        echo '<form method="get" class="aegis-t-a6" style="margin-top:15px;">';
        echo '<input type="hidden" name="page" value="aegis-system-codes" />';
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
     * 渲染批次列表。
     */
    protected static function render_batches_table($batches, $per_page, $paged, $total, $start_date, $end_date) {
        echo '<h2 class="aegis-t-a3" style="margin-top:20px;">批次列表</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>EAN</th><th>数量</th><th>创建人</th><th>创建时间</th><th>操作</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($batches)) {
            echo '<tr><td colspan="6">暂无批次</td></tr>';
        }
        foreach ($batches as $batch) {
            $user = $batch->created_by ? get_userdata($batch->created_by) : null;
            $user_label = $user ? $user->user_login : '-';
            $export_nonce = wp_create_nonce('aegis_codes_export_' . $batch->id);
            $print_nonce = wp_create_nonce('aegis_codes_print_' . $batch->id);
            $view_url = esc_url(add_query_arg([
                'page'       => 'aegis-system-codes',
                'view'       => $batch->id,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'per_page'   => $per_page,
            ], admin_url('admin.php')));
            $export_url = esc_url(wp_nonce_url(add_query_arg([
                'page'        => 'aegis-system-codes',
                'codes_action'=> 'export',
                'batch_id'    => $batch->id,
            ], admin_url('admin.php')), 'aegis_codes_export_' . $batch->id));
            $print_url = esc_url(wp_nonce_url(add_query_arg([
                'page'        => 'aegis-system-codes',
                'codes_action'=> 'print',
                'batch_id'    => $batch->id,
            ], admin_url('admin.php')), 'aegis_codes_print_' . $batch->id));

            echo '<tr>';
            echo '<td>' . esc_html($batch->id) . '</td>';
            echo '<td>' . esc_html($batch->ean) . '</td>';
            echo '<td>' . esc_html($batch->quantity) . '</td>';
            echo '<td>' . esc_html($user_label) . '</td>';
            echo '<td>' . esc_html($batch->created_at) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . $view_url . '">查看</a> ';
            echo '<a class="button button-small" href="' . $export_url . '">导出 CSV</a> ';
            echo '<a class="button button-small" href="' . $print_url . '" target="_blank">打印</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        $total_pages = $per_page > 0 ? ceil($total / $per_page) : 1;
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            if ($paged > 1) {
                $prev_url = esc_url(add_query_arg([
                    'page'       => 'aegis-system-codes',
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
                    'page'       => 'aegis-system-codes',
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
     * 渲染批次内码列表。
     */
    protected static function render_codes_table($batch, $codes) {
        echo '<h2 class="aegis-t-a3" style="margin-top:20px;">批次 #' . esc_html($batch->id) . ' 代码列表</h2>';
        echo '<p class="aegis-t-a6">EAN：' . esc_html($batch->ean) . '，数量：' . esc_html($batch->quantity) . '</p>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>Code</th><th>状态</th><th>创建时间</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($codes)) {
            echo '<tr><td colspan="4">无数据</td></tr>';
        }
        foreach ($codes as $code) {
            echo '<tr>';
            echo '<td>' . esc_html($code->id) . '</td>';
            echo '<td>' . esc_html(AEGIS_System::format_code_display($code->code)) . '</td>';
            echo '<td>' . esc_html($code->status) . '</td>';
            echo '<td>' . esc_html($code->created_at) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * 处理生成请求。
     */
    protected static function handle_generate_request($post) {
        $ean = isset($post['ean']) ? sanitize_text_field(wp_unslash($post['ean'])) : '';
        $quantity = isset($post['quantity']) ? absint($post['quantity']) : 0;
        $batch_note = isset($post['batch_note']) ? sanitize_text_field(wp_unslash($post['batch_note'])) : '';
        $items = [
            [
                'ean'      => $ean,
                'quantity' => $quantity,
            ],
        ];

        $result = self::create_batch_with_codes(
            $items,
            [
                'batch_note' => $batch_note,
                'source'     => 'admin',
                'max_skus'   => 1,
            ]
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'messages' => ['批次 #' . $result['batch_id'] . ' 已生成，共 ' . $result['total_quantity'] . ' 条。'],
        ];
    }

    /**
     * 核心生成入口：校验输入、校验 SKU、事务插入批次和码。
     *
     * @param array $items [['ean' => string, 'quantity' => int], ...]
     * @param array $options
     * @return array|WP_Error
     */
    protected static function create_batch_with_codes($items, $options = []) {
        global $wpdb;

        $defaults = [
            'batch_note' => '',
            'source'     => 'portal',
            'max_per_sku'=> 100,
            'max_total'  => 300,
            'max_skus'   => 3,
        ];
        $options = wp_parse_args($options, $defaults);

        $prepared = self::prepare_generation_items($items, $options['max_per_sku'], $options['max_total'], $options['max_skus']);
        if (is_wp_error($prepared)) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_CODE_BATCH_CREATE,
                'FAIL',
                [
                    'reason'   => 'validation',
                    'message'  => $prepared->get_error_message(),
                    'sku_count'=> 0,
                    'total'    => 0,
                    'source'   => $options['source'],
                ]
            );
            return $prepared;
        }

        $sku_items = self::load_active_skus_by_ean($prepared);
        if (is_wp_error($sku_items)) {
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_CODE_BATCH_CREATE,
                'FAIL',
                [
                    'reason'   => 'sku_validation',
                    'message'  => $sku_items->get_error_message(),
                    'sku_count'=> count($prepared),
                    'total'    => array_sum(wp_list_pluck($prepared, 'quantity')),
                    'source'   => $options['source'],
                ]
            );
            return $sku_items;
        }

        $total_quantity = 0;
        foreach ($sku_items as $item) {
            $total_quantity += (int) $item['quantity'];
        }

        $batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $now = current_time('mysql');
        $primary_ean = (count($sku_items) === 1) ? $sku_items[0]['ean'] : 'MULTI';

        $meta_data = [
            'items'  => array_values($sku_items),
            'note'   => $options['batch_note'] ? $options['batch_note'] : null,
            'source' => $options['source'],
        ];
        $meta_data = array_filter($meta_data, static function ($value) {
            return null !== $value && $value !== '' && $value !== [];
        });
        $meta_json = !empty($meta_data) ? wp_json_encode($meta_data) : null;

        $transaction_started = $wpdb->query('START TRANSACTION');
        $use_transaction = ($transaction_started !== false);
        $inserted_codes = [];

        $batch_inserted = $wpdb->insert(
            $batch_table,
            [
                'ean'        => $primary_ean,
                'quantity'   => $total_quantity,
                'created_by' => get_current_user_id(),
                'created_at' => $now,
                'meta'       => $meta_json,
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );

        if (false === $batch_inserted) {
            if ($use_transaction) {
                $wpdb->query('ROLLBACK');
            }

            $error = new WP_Error('batch_insert_fail', '创建批次失败，请重试。');
            AEGIS_Access_Audit::record_event(
                AEGIS_System::ACTION_CODE_BATCH_CREATE,
                'FAIL',
                [
                    'reason'   => 'db_insert',
                    'message'  => $wpdb->last_error,
                    'sku_count'=> count($sku_items),
                    'total'    => $total_quantity,
                    'source'   => $options['source'],
                ]
            );
            return $error;
        }

        $batch_id = (int) $wpdb->insert_id;

        foreach ($sku_items as $item) {
            $ean = $item['ean'];
            for ($i = 0; $i < $item['quantity']; $i++) {
                $code_result = self::insert_code_with_retry($code_table, $batch_id, $ean, $now);
                if (is_wp_error($code_result)) {
                    if ($use_transaction) {
                        $wpdb->query('ROLLBACK');
                    } else {
                        self::cleanup_batch_records($batch_id);
                    }

                    AEGIS_Access_Audit::record_event(
                        AEGIS_System::ACTION_CODE_BATCH_CREATE,
                        'FAIL',
                        [
                            'reason'    => 'code_insert',
                            'message'   => $code_result->get_error_message(),
                            'sku_count' => count($sku_items),
                            'total'     => $total_quantity,
                            'source'    => $options['source'],
                        ]
                    );
                    return $code_result;
                }
                $inserted_codes[] = $code_result['id'];
            }
        }

        if ($use_transaction) {
            $wpdb->query('COMMIT');
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_CODE_BATCH_CREATE,
            'SUCCESS',
            [
                'batch_id' => $batch_id,
                'sku_count'=> count($sku_items),
                'total'    => $total_quantity,
                'source'   => $options['source'],
            ]
        );

        return [
            'batch_id'       => $batch_id,
            'sku_count'      => count($sku_items),
            'total_quantity' => $total_quantity,
        ];
    }

    /**
     * 解析并聚合生成行，校验数量限制。
     *
     * @param array $items
     * @param int   $max_per_sku
     * @param int   $max_total
     * @param int|null $max_skus
     * @return array|WP_Error
     */
    protected static function prepare_generation_items($items, $max_per_sku, $max_total, $max_skus = null) {
        $aggregated = [];

        foreach ($items as $item) {
            $ean = isset($item['ean']) ? sanitize_text_field(wp_unslash($item['ean'])) : '';
            $quantity = isset($item['quantity']) ? absint($item['quantity']) : 0;

            if ('' === $ean && 0 === $quantity) {
                continue;
            }

            if ('' === $ean) {
                return new WP_Error('ean_missing', '请选择 SKU。');
            }

            if ($quantity < 1) {
                return new WP_Error('quantity_invalid', '数量需大于 0。');
            }

            if (!isset($aggregated[$ean])) {
                $aggregated[$ean] = 0;
            }
            $aggregated[$ean] += $quantity;
        }

        if (empty($aggregated)) {
            return new WP_Error('items_empty', '请至少填写一行 SKU 与数量。');
        }

        if (null !== $max_skus && count($aggregated) > $max_skus) {
            return new WP_Error('sku_limit', '单次最多选择 ' . $max_skus . ' 个 SKU。');
        }

        $prepared = [];
        $total = 0;
        foreach ($aggregated as $ean => $qty) {
            if ($qty > $max_per_sku) {
                return new WP_Error('quantity_exceed', '单个 SKU 生成数量不得超过 ' . $max_per_sku . '。');
            }
            $total += $qty;
            $prepared[] = [
                'ean'      => $ean,
                'quantity' => $qty,
            ];
        }

        if ($total > $max_total) {
            return new WP_Error('total_exceed', '单次生成总量不得超过 ' . $max_total . '。');
        }

        return $prepared;
    }

    /**
     * 校验 SKU 是否存在且为启用状态，并补充名称。
     *
     * @param array $items
     * @return array|WP_Error
     */
    protected static function load_active_skus_by_ean($items) {
        global $wpdb;
        if (empty($items)) {
            return new WP_Error('sku_missing', '未找到对应 SKU。');
        }

        $ean_values = wp_list_pluck($items, 'ean');
        $ean_values = array_values(array_unique($ean_values));
        $placeholders = implode(', ', array_fill(0, count($ean_values), '%s'));
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT ean, product_name, status FROM {$sku_table} WHERE ean IN ({$placeholders})", $ean_values),
            OBJECT_K
        );

        $result = [];
        foreach ($items as $item) {
            $ean = $item['ean'];
            if (!isset($rows[$ean])) {
                return new WP_Error('sku_missing', '未找到对应 SKU。');
            }

            $row = $rows[$ean];
            if (AEGIS_SKU::STATUS_ACTIVE !== $row->status) {
                return new WP_Error('sku_inactive', 'SKU 已停用，无法生成防伪码。');
            }

            $result[] = [
                'ean'          => $ean,
                'quantity'     => (int) $item['quantity'],
                'product_name' => $row->product_name,
            ];
        }

        return $result;
    }

    /**
     * 生成强随机码。
     *
     * @return string
     */
    protected static function generate_secure_code_value() {
        $brand = 'AM';
        $version = 'A';
        $year_alphabet = ['A','B','C','D','E','F','G','H','J','K','M','N','P','Q','R','S','T','V','W','X'];
        $year = (int) gmdate('Y', current_time('timestamp'));
        $year_index = (($year - 2026) % 20 + 20) % 20;
        $year_code = $year_alphabet[$year_index];
        $charset = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $random = '';
        $max_index = strlen($charset) - 1;
        for ($i = 0; $i < 16; $i++) {
            $random .= $charset[random_int(0, $max_index)];
        }

        return $brand . $version . $year_code . $random;
    }

    /**
     * 带唯一性重试的插入。
     *
     * @param string $table
     * @param int    $batch_id
     * @param string $ean
     * @param string $now
     * @param int    $max_attempts
     * @return array|WP_Error
     */
    protected static function insert_code_with_retry($table, $batch_id, $ean, $now, $max_attempts = 12) {
        global $wpdb;

        for ($i = 0; $i < $max_attempts; $i++) {
            $code = self::generate_secure_code_value();
            $inserted = $wpdb->insert(
                $table,
                [
                    'batch_id'   => $batch_id,
                    'ean'        => $ean,
                    'code'       => $code,
                    'status'     => self::STATUS_UNUSED,
                    'stock_status' => 'generated',
                    'stocked_at'   => null,
                    'stocked_by'   => null,
                    'receipt_id'   => null,
                    'created_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if (false !== $inserted) {
                return [
                    'id'   => (int) $wpdb->insert_id,
                    'code' => $code,
                ];
            }

            $error = strtolower($wpdb->last_error);
            if (false === strpos($error, 'duplicate')) {
                return new WP_Error('code_insert_db', '写入防伪码失败，请重试。');
            }
        }

        return new WP_Error('code_generate_fail', '生成唯一编码失败，请重试。');
    }

    /**
     * 清理失败批次的残留数据。
     *
     * @param int $batch_id
     */
    protected static function cleanup_batch_records($batch_id) {
        global $wpdb;
        $batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;

        $wpdb->delete($code_table, ['batch_id' => $batch_id], ['%d']);
        $wpdb->delete($batch_table, ['id' => $batch_id], ['%d']);
    }

    /**
     * 解析批次 meta，补充 SKU 行数和 items 数据。
     *
     * @param object $batch
     * @return object
     */
    protected static function enrich_batch_row($batch) {
        $batch->items_data = [];
        $batch->total_quantity = (int) $batch->quantity;

        if (!empty($batch->meta)) {
            $meta = json_decode($batch->meta, true);
            if (is_array($meta) && !empty($meta['items']) && is_array($meta['items'])) {
                foreach ($meta['items'] as $item) {
                    if (empty($item['ean'])) {
                        continue;
                    }
                    $batch->items_data[] = [
                        'ean'          => sanitize_text_field($item['ean']),
                        'quantity'     => isset($item['quantity']) ? (int) $item['quantity'] : 0,
                        'product_name' => isset($item['product_name']) ? sanitize_text_field($item['product_name']) : '',
                    ];
                }
            }
        }

        if (empty($batch->items_data)) {
            $batch->items_data[] = [
                'ean'      => $batch->ean,
                'quantity' => (int) $batch->quantity,
            ];
        }

        $batch->sku_count = count($batch->items_data);

        return $batch;
    }

    /**
     * 查询批次。
     */
    protected static function query_batches($start, $end, $per_page, $paged, &$total) {
        global $wpdb;
        $batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        $offset = ($paged - 1) * $per_page;
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$batch_table} WHERE created_at BETWEEN %s AND %s",
                $start,
                $end
            )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$batch_table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $start,
                $end,
                $per_page,
                $offset
            )
        );

        foreach ($rows as $row) {
            self::enrich_batch_row($row);
        }

        return $rows;
    }

    /**
     * 获取批次详情。
     */
    protected static function get_batch($batch_id) {
        global $wpdb;
        $batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$batch_table} WHERE id = %d", $batch_id));
        if ($row) {
            self::enrich_batch_row($row);
        }

        return $row;
    }

    /**
     * 获取批次内码列表。
     */
    protected static function get_codes_for_batch($batch_id, $per_page = null, $paged = 1, &$total = null, $with_products = false) {
        global $wpdb;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $paged = max(1, (int) $paged);
        $limit_clause = '';
        if (null !== $per_page) {
            $per_page = max(1, (int) $per_page);
            $offset = ($paged - 1) * $per_page;
            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$code_table} WHERE batch_id = %d", $batch_id));
            $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $per_page, $offset);
        }

        $fields = 'c.id, c.code, c.status, c.ean, c.created_at';
        $join = '';
        if ($with_products) {
            $fields .= ', s.product_name';
            $join = " LEFT JOIN {$sku_table} s ON c.ean = s.ean";
        }

        $sql = $wpdb->prepare(
            "SELECT {$fields} FROM {$code_table} c{$join} WHERE c.batch_id = %d ORDER BY c.id DESC{$limit_clause}",
            $batch_id
        );

        return $wpdb->get_results($sql);
    }

    /**
     * 获取 SKU 选项。
     */
    protected static function get_sku_options() {
        global $wpdb;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_results("SELECT ean, product_name FROM {$sku_table} ORDER BY created_at DESC");
    }

    /**
     * 获取启用的 SKU 选项。
     */
    protected static function get_active_sku_options() {
        global $wpdb;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ean, product_name FROM {$sku_table} WHERE status = %s ORDER BY product_name ASC, created_at DESC",
                AEGIS_SKU::STATUS_ACTIVE
            )
        );
    }

    /**
     * 处理导出。
     */
    protected static function handle_export($batch_id) {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_EXPORT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'capability']);
            return new WP_Error('forbidden', '权限不足');
        }

        if (!AEGIS_System::is_module_enabled('codes')) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_EXPORT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'module_disabled']);
            return new WP_Error('module_disabled', '模块未启用');
        }

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'aegis_codes_export_' . $batch_id)) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_EXPORT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'nonce']);
            return new WP_Error('nonce', '安全校验失败');
        }

        $batch = self::get_batch($batch_id);
        if (!$batch) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_EXPORT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'not_found']);
            return new WP_Error('not_found', '批次不存在');
        }

        $codes = self::get_codes_for_batch($batch_id, null, 1, $count, true);
        $filename = 'aegis-codes-batch-' . $batch_id . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputcsv($output, ['code', 'ean', 'product_name']);
        foreach ($codes as $code) {
            $product = isset($code->product_name) ? $code->product_name : '';
            fputcsv($output, [$code->code, $code->ean, $product]);
        }
        fclose($output);

        global $wpdb;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $wpdb->update($code_table, ['exported_at' => current_time('mysql')], ['batch_id' => $batch_id]);

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_CODE_EXPORT,
            'SUCCESS',
            [
                'batch_id'  => $batch_id,
                'count'     => count($codes),
                'sku_count' => $batch->sku_count,
            ]
        );
        exit;
    }

    /**
     * 处理打印视图。
     */
    protected static function handle_print($batch_id) {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_PRINT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'capability']);
            return new WP_Error('forbidden', '权限不足');
        }

        if (!AEGIS_System::is_module_enabled('codes')) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_PRINT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'module_disabled']);
            return new WP_Error('module_disabled', '模块未启用');
        }

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'aegis_codes_print_' . $batch_id)) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_PRINT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'nonce']);
            return new WP_Error('nonce', '安全校验失败');
        }

        $batch = self::get_batch($batch_id);
        if (!$batch) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_CODE_PRINT, 'FAIL', ['batch_id' => $batch_id, 'reason' => 'not_found']);
            return new WP_Error('not_found', '批次不存在');
        }

        $codes = self::get_codes_for_batch($batch_id, null, 1, $count, true);

        global $wpdb;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $wpdb->update($code_table, ['printed_at' => current_time('mysql')], ['batch_id' => $batch_id]);

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_CODE_PRINT,
            'SUCCESS',
            [
                'batch_id'  => $batch_id,
                'count'     => count($codes),
                'sku_count' => $batch->sku_count,
            ]
        );

        echo '<html><head><meta charset="utf-8"><title>批次打印</title>';
        echo '<style>.aegis-print{font-family:Arial;margin:20px;} .aegis-print h1{font-size:20px;} .aegis-print table{width:100%;border-collapse:collapse;} .aegis-print th,.aegis-print td{border:1px solid #ddd;padding:6px;text-align:left;}</style>';
        echo '</head><body class="aegis-print">';
        echo '<h1>批次 #' . esc_html($batch->id) . ' 防伪码</h1>';
        echo '<p>创建时间：' . esc_html($batch->created_at) . ' · 总量：' . esc_html($batch->quantity) . '</p>';
        echo '<table><thead><tr><th>ID</th><th>Code</th><th>EAN</th><th>产品</th></tr></thead><tbody>';
        foreach ($codes as $code) {
            $product = isset($code->product_name) ? $code->product_name : '';
            echo '<tr><td>' . esc_html($code->id) . '</td><td>' . esc_html(AEGIS_System::format_code_display($code->code)) . '</td><td>' . esc_html($code->ean) . '</td><td>' . esc_html($product) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</body></html>';
        exit;
    }

    /**
     * Portal 前台面板。
     */
    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('codes')) {
            return '<div class="aegis-t-a5">防伪码模块未启用，请联系管理员。</div>';
        }

        if (!AEGIS_System_Roles::user_can_use_warehouse()) {
            return '<div class="aegis-t-a5">当前账号无权访问防伪码模块。</div>';
        }

        $can_generate = AEGIS_System_Roles::user_can_manage_warehouse();
        $can_export = $can_generate;
        $base_url = add_query_arg('m', 'codes', $portal_url);
        $messages = [];
        $errors = [];

        wp_enqueue_script(
            'aegis-system-portal-codes',
            AEGIS_SYSTEM_URL . 'assets/js/portal-codes.js',
            [],
            AEGIS_Assets_Media::get_asset_version('assets/js/portal-codes.js'),
            true
        );

        $requested_action = isset($_GET['codes_action']) ? sanitize_key(wp_unslash($_GET['codes_action'])) : '';
        $action_batch_id = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
        if ($requested_action && $action_batch_id) {
            if (in_array($requested_action, ['export', 'print'], true)) {
                if (!$can_export) {
                    $errors[] = '当前账号无权导出或打印。';
                } else {
                    $result = ('export' === $requested_action) ? self::handle_export($action_batch_id) : self::handle_print($action_batch_id);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    }
                }
            }
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            if (!$can_generate) {
                $errors[] = '当前账号无权生成防伪码。';
            } else {
                $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                        'nonce_field'     => 'aegis_codes_nonce',
                        'nonce_action'    => 'aegis_codes_portal',
                        'whitelist'       => ['codes_action', 'items', 'aegis_codes_nonce', '_wp_http_referer', '_aegis_idempotency'],
                        'idempotency_key' => $idempotency,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $result = self::handle_portal_generate_request($_POST);
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
        $batches = self::query_batches($start_datetime, $end_datetime, $per_page, $paged, $total);
        $sku_options = self::get_active_sku_options();

        $view_batch = isset($_GET['view']) ? (int) $_GET['view'] : 0;
        $codes_per_page = isset($_GET['codes_per_page']) ? (int) $_GET['codes_per_page'] : 20;
        if (!in_array($codes_per_page, $per_page_options, true)) {
            $codes_per_page = 20;
        }
        $codes_page = isset($_GET['codes_page']) ? max(1, (int) $_GET['codes_page']) : 1;
        $view_batch_row = $view_batch ? self::get_batch($view_batch) : null;
        $codes_total = 0;
        $codes_total_pages = 1;
        $view_codes = [];
        if ($view_batch && $view_batch_row) {
            $view_codes = self::get_codes_for_batch($view_batch, $codes_per_page, $codes_page, $codes_total, true);
            $codes_total_pages = $codes_per_page > 0 ? max(1, (int) ceil($codes_total / $codes_per_page)) : 1;
        } elseif ($view_batch && !$view_batch_row) {
            $errors[] = '未找到指定的批次。';
        }

        $context = [
            'base_url'       => $base_url,
            'can_generate'   => $can_generate,
            'can_export'     => $can_export,
            'messages'       => $messages,
            'errors'         => $errors,
            'sku_options'    => $sku_options,
            'filters'        => [
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'per_page'   => $per_page,
                'paged'      => $paged,
                'total'      => $total,
                'per_options'=> $per_page_options,
                'total_pages'=> $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1,
            ],
            'batches'        => $batches,
            'view'           => [
                'batch'       => $view_batch_row,
                'codes'       => $view_codes,
                'page'        => $codes_page,
                'per_page'    => $codes_per_page,
                'total'       => $codes_total,
                'total_pages' => $codes_total_pages,
            ],
        ];

        return AEGIS_Portal::render_portal_template('codes', $context);
    }

    /**
     * 处理 Portal 端生成请求。
     */
    protected static function handle_portal_generate_request($post) {
        $items_input = isset($post['items']) && is_array($post['items']) ? $post['items'] : [];
        $items = [];
        foreach ($items_input as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'ean'      => isset($item['ean']) ? $item['ean'] : '',
                'quantity' => isset($item['quantity']) ? $item['quantity'] : 0,
            ];
        }

        $result = self::create_batch_with_codes(
            $items,
            [
                'batch_note' => '',
                'source'     => 'portal',
                'max_skus'   => 3,
            ]
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'message' => '批次 #' . $result['batch_id'] . ' 已生成，共 ' . $result['total_quantity'] . ' 条（SKU 行数 ' . $result['sku_count'] . '）。',
        ];
    }

    /**
     * 日期边界格式化。
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

