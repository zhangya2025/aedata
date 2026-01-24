<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Reset_B {
    const DEALER_META_KEY = 'aegis_dealer_id';
    const PER_PAGE_DEFAULT = 20;

    /**
     * Portal 渲染。
     *
     * @param string $portal_url
     * @return string
     */
    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('reset_b')) {
            return '<div class="aegis-t-a5">清零模块未启用，请联系管理员。</div>';
        }

        $actor = self::get_actor_context();
        if (!$actor['can_access']) {
            return '<div class="aegis-t-a5">当前账号无权访问清零模块。</div>';
        }

        $messages = [];
        $errors = [];
        $query_result = null;
        $reset_result = null;

        $base_url = add_query_arg('m', 'reset_b', $portal_url);
        $per_page_options = [20, 50, 100];
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : self::PER_PAGE_DEFAULT;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = self::PER_PAGE_DEFAULT;
        }
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_ACCESS_ROOT,
                    'nonce_field'     => 'aegis_reset_b_nonce',
                    'nonce_action'    => 'aegis_reset_b_action',
                    'whitelist'       => ['aegis_reset_b_nonce', 'reset_b_action', 'code_value', 'reason', 'confirm_reset', '_wp_http_referer', '_aegis_idempotency'],
                    'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } else {
                $action = isset($_POST['reset_b_action']) ? sanitize_key(wp_unslash($_POST['reset_b_action'])) : '';
                $code_value = isset($_POST['code_value']) ? sanitize_text_field(wp_unslash($_POST['code_value'])) : '';
                $code_value = AEGIS_System::normalize_code_value($code_value);
                $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

                if ('query' === $action) {
                    $query_result = self::handle_portal_query($code_value, $actor);
                    if (is_wp_error($query_result)) {
                        $errors[] = $query_result->get_error_message();
                        $query_result = null;
                    }
                } elseif ('reset' === $action) {
                    $reset_result = self::handle_portal_reset($code_value, $reason, $actor, !empty($_POST['confirm_reset']));
                    if (is_wp_error($reset_result)) {
                        $errors[] = $reset_result->get_error_message();
                        $reset_result = null;
                    } else {
                        $messages[] = 'B 查询次数已清零。';
                        $query_result = self::handle_portal_query($code_value, $actor);
                        if (is_wp_error($query_result)) {
                            $query_result = null;
                        }
                    }
                }
            }
        }

        $total_logs = 0;
        $logs = self::get_reset_logs($actor, $per_page, $paged, $total_logs);

        $context = [
            'base_url'       => $base_url,
            'messages'       => $messages,
            'errors'         => $errors,
            'query_result'   => $query_result,
            'reset_result'   => $reset_result,
            'actor'          => $actor,
            'per_page'       => $per_page,
            'per_page_opts'  => $per_page_options,
            'paged'          => $paged,
            'logs'           => $logs,
            'total_logs'     => $total_logs,
            'idempotency'    => wp_generate_uuid4(),
        ];

        ob_start();
        include AEGIS_SYSTEM_PATH . '/templates/portal/reset-b.php';
        return ob_get_clean();
    }

    /**
     * 后台页面保留：复用门户处理逻辑。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_reset_b()) {
            wp_die(__('您无权访问该页面。'));
        }

        $portal_url = admin_url('admin.php?page=aegis-system-reset-b');
        echo '<div class="wrap aegis-system-root">';
        echo self::render_portal_panel($portal_url);
        echo '</div>';
    }

    /**
     * 查询当前用户上下文。
     *
     * @return array
     */
    protected static function get_actor_context() {
        $user = wp_get_current_user();
        $roles = $user ? (array) $user->roles : [];
        $dealer = self::get_current_dealer();
        $is_hq = in_array('aegis_hq_admin', $roles, true) || AEGIS_System_Roles::user_can_manage_system();
        $is_dealer = in_array('aegis_dealer', $roles, true);
        $is_warehouse_manager = in_array('aegis_warehouse_manager', $roles, true);
        $is_warehouse_staff = in_array('aegis_warehouse_staff', $roles, true);

        $can_access = $is_hq || $is_dealer || $is_warehouse_manager || $is_warehouse_staff;

        return [
            'user'                => $user,
            'user_id'             => $user ? (int) $user->ID : 0,
            'roles'               => $roles,
            'is_hq'               => $is_hq,
            'is_dealer'           => $is_dealer,
            'is_warehouse'        => $is_warehouse_manager || $is_warehouse_staff,
            'dealer'              => $dealer,
            'dealer_active'       => $dealer && 'active' === $dealer->status,
            'can_access'          => $can_access,
            'warehouse_readonly'  => ($is_warehouse_manager || $is_warehouse_staff) && !$is_hq,
        ];
    }

    /**
     * 处理查询。
     *
     * @param string $code_value
     * @param array  $actor
     * @return array|WP_Error
     */
    protected static function handle_portal_query($code_value, $actor) {
        $code_value = AEGIS_System::normalize_code_value($code_value);
        $formatted_code = AEGIS_System::format_code_display($code_value);
        if ('' === $code_value) {
            return new WP_Error('empty_code', '请输入防伪码。');
        }

        $record = self::get_code_record($code_value);
        if (!$record) {
            return new WP_Error('code_not_found', '未找到对应防伪码：' . $formatted_code . '。');
        }

        $counts = self::calculate_b_display($record);
        $shipment = self::get_latest_shipment((int) $record->id);
        $dealer_label = $shipment && $shipment->dealer_name ? $shipment->dealer_name : self::get_hq_label();

        $permission = self::evaluate_permission($actor, $record, $shipment);
        if ($actor['is_dealer'] && $permission['can_reset']) {
            $dealer_label = '本经销商 / 已归属';
        }

        return [
            'code'             => AEGIS_System::format_code_display($record->code),
            'ean'              => $record->ean,
            'b_display'        => $counts['b_display'],
            'dealer_label'     => $dealer_label,
            'owner_dealer_id'  => $shipment ? (int) $shipment->dealer_id : 0,
            'owner_dealer_name'=> $shipment && $shipment->dealer_name ? $shipment->dealer_name : '',
            'can_reset'        => $permission['can_reset'],
            'restriction'      => $permission['reason'],
            'before_b'         => $counts['b_display'],
            'stock_status'     => $record->stock_status ? $record->stock_status : 'generated',
        ];
    }

    /**
     * 处理清零。
     *
     * @param string $code_value
     * @param string $reason
     * @param array  $actor
     * @param bool   $confirmed
     * @return array|WP_Error
     */
    protected static function handle_portal_reset($code_value, $reason, $actor, $confirmed) {
        if (!$confirmed) {
            return new WP_Error('confirm_required', '请确认后再执行清零。');
        }

        $code_value = AEGIS_System::normalize_code_value($code_value);
        $formatted_code = AEGIS_System::format_code_display($code_value);
        if ('' === $code_value) {
            return new WP_Error('empty_code', '请输入防伪码。');
        }

        $record = self::get_code_record($code_value);
        if (!$record) {
            AEGIS_Access_Audit::log(
                AEGIS_System::ACTION_RESET_B,
                [
                    'result'      => 'FAIL',
                    'entity_type' => 'code',
                    'entity_id'   => $code_value,
                    'message'     => 'not_found',
                ]
            );
            return new WP_Error('code_not_found', '未找到对应防伪码：' . $formatted_code . '。');
        }

        $shipment = self::get_latest_shipment((int) $record->id);
        $permission = self::evaluate_permission($actor, $record, $shipment);
        if (!$permission['can_reset']) {
            AEGIS_Access_Audit::log(
                AEGIS_System::ACTION_RESET_B,
                [
                    'result'      => 'BLOCKED',
                    'entity_type' => 'code',
                    'entity_id'   => $code_value,
                    'message'     => $permission['reason'],
                    'meta'        => [
                        'code_id'  => (int) $record->id,
                        'actor_id' => $actor['user_id'],
                    ],
                ]
            );
            return new WP_Error('not_allowed', $permission['reason']);
        }

        $counts = self::calculate_b_display($record);
        $raw_b = (int) $record->query_b_count;
        $table = self::get_code_table();

        global $wpdb;
        $updated = $wpdb->update(
            $table,
            ['query_b_offset' => $raw_b],
            ['id' => (int) $record->id],
            ['%d'],
            ['%d']
        );

        if (false === $updated) {
            AEGIS_Access_Audit::log(
                AEGIS_System::ACTION_RESET_B,
                [
                    'result'      => 'FAIL',
                    'entity_type' => 'code',
                    'entity_id'   => $code_value,
                    'meta'        => [
                        'code_id'  => (int) $record->id,
                        'db_error' => $wpdb->last_error,
                    ],
                ]
            );
            return new WP_Error('db_error', '清零失败：数据库错误。');
        }

        $after_b = 0;
        $log_payload = [
            'code_id'       => (int) $record->id,
            'code_value'    => $code_value,
            'dealer_id'     => $shipment ? (int) $shipment->dealer_id : null,
            'actor_user_id' => $actor['user_id'],
            'actor_role'    => implode(',', $actor['roles']),
            'before_b'      => $counts['b_display'],
            'after_b'       => $after_b,
            'reason'        => $reason,
            'ip'            => self::get_client_ip(),
        ];

        self::insert_reset_log($log_payload);

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_RESET_B,
            'SUCCESS',
            array_merge($log_payload, [
                'raw_b'        => $raw_b,
                'prior_offset' => (int) $record->query_b_offset,
            ])
        );

        return [
            'code'           => $code_value,
            'before_b'       => $counts['b_display'],
            'after_b'        => $after_b,
            'dealer_label'   => $permission['dealer_label'] ?? ($shipment && $shipment->dealer_name ? $shipment->dealer_name : ''),
        ];
    }

    /**
     * 权限判断。
     *
     * @param array      $actor
     * @param object     $record
     * @param object|nil $shipment
     * @return array
     */
    protected static function evaluate_permission($actor, $record, $shipment) {
        $dealer_label = $shipment && $shipment->dealer_name ? $shipment->dealer_name : self::get_hq_label();
        $reason = '';
        $can_reset = false;

        if ($actor['is_hq']) {
            $can_reset = true;
        } elseif ($actor['is_dealer']) {
            if (!$actor['dealer']) {
                $reason = '未找到经销商资料，禁止清零。';
            } elseif (!$actor['dealer_active']) {
                $reason = '经销商已停用，禁止清零。';
            } elseif ('shipped' !== ($record->stock_status ? $record->stock_status : 'generated') || !$shipment) {
                $reason = '未出库不可清零。';
            } elseif ((int) $shipment->dealer_id !== (int) $actor['dealer']->id) {
                $reason = '该防伪码未出库至本经销商，禁止清零。';
            } else {
                $can_reset = true;
            }
        } else {
            $reason = '当前角色不可清零。';
        }

        if (!$can_reset && '' === $reason) {
            $reason = '无权限执行清零。';
        }

        return [
            'can_reset'    => $can_reset,
            'reason'       => $reason,
            'dealer_label' => $dealer_label,
        ];
    }

    /**
     * 计算 B 显示值。
     *
     * @param object $record
     * @return array
     */
    protected static function calculate_b_display($record) {
        $raw_b = (int) $record->query_b_count;
        $offset = (int) $record->query_b_offset;
        return [
            'b_display' => max(0, $raw_b - $offset),
            'raw_b'     => $raw_b,
            'offset'    => $offset,
        ];
    }

    /**
     * 查询日志。
     *
     * @param array $actor
     * @param int   $per_page
     * @param int   $paged
     * @param int   $total
     * @return array
     */
    protected static function get_reset_logs($actor, $per_page, $paged, &$total) {
        if ($actor['warehouse_readonly']) {
            $total = 0;
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RESET_LOG_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $code_table = self::get_code_table();

        $where = '1=1';
        $params = [];
        if (!$actor['is_hq'] && $actor['user_id']) {
            $where .= ' AND rl.actor_user_id = %d';
            $params[] = $actor['user_id'];
        }

        $offset = ($paged - 1) * $per_page;
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} rl WHERE {$where}", $params));

        $sql = "SELECT rl.*, d.dealer_name, c.ean FROM {$table} rl
            LEFT JOIN {$dealer_table} d ON rl.dealer_id = d.id
            LEFT JOIN {$code_table} c ON rl.code_id = c.id
            WHERE {$where}
            ORDER BY rl.reset_at DESC
            LIMIT %d OFFSET %d";

        $params_with_limit = array_merge($params, [$per_page, $offset]);
        return $wpdb->get_results($wpdb->prepare($sql, $params_with_limit));
    }

    /**
     * 写入清零日志。
     *
     * @param array $payload
     */
    protected static function insert_reset_log($payload) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RESET_LOG_TABLE;
        $wpdb->insert(
            $table,
            [
                'code_id'       => $payload['code_id'],
                'code_value'    => $payload['code_value'],
                'dealer_id'     => $payload['dealer_id'],
                'actor_user_id' => $payload['actor_user_id'],
                'actor_role'    => $payload['actor_role'],
                'reset_at'      => current_time('mysql'),
                'before_b'      => $payload['before_b'],
                'after_b'       => $payload['after_b'],
                'reason'        => $payload['reason'],
                'ip'            => $payload['ip'],
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * 获取当前用户绑定的经销商。
     */
    protected static function get_current_dealer() {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return null;
        }
        $dealer_id = get_user_meta($user->ID, self::DEALER_META_KEY, true);
        $dealer_id = (int) $dealer_id;
        if ($dealer_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $dealer_id));
    }

    /**
     * 获取码记录。
     */
    protected static function get_code_record($code_value) {
        global $wpdb;
        $table = self::get_code_table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s", $code_value));
    }

    /**
     * 获取最近一次出库归属。
     */
    protected static function get_latest_shipment($code_id) {
        global $wpdb;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT si.shipment_id, si.scanned_at, si.code_id, si.ean, sh.dealer_id, d.dealer_name FROM {$shipment_item_table} si LEFT JOIN {$shipment_table} sh ON si.shipment_id = sh.id LEFT JOIN {$dealer_table} d ON sh.dealer_id = d.id WHERE si.code_id = %d ORDER BY si.scanned_at DESC LIMIT 1",
                $code_id
            )
        );
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
     * 客户端 IP。
     *
     * @return string
     */
    protected static function get_client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
        return sanitize_text_field($ip);
    }

    /**
     * 码表名。
     *
     * @return string
     */
    protected static function get_code_table() {
        global $wpdb;
        return $wpdb->prefix . AEGIS_System::CODE_TABLE;
    }
}
