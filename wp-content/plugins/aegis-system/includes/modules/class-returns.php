<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Returns {
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_SALES_REJECTED = 'sales_rejected';
    const STATUS_SALES_APPROVED = 'sales_approved';
    const STATUS_WAREHOUSE_CHECKING = 'warehouse_checking';
    const STATUS_WAREHOUSE_REJECTED = 'warehouse_rejected';
    const STATUS_WAREHOUSE_APPROVED = 'warehouse_approved';
    const STATUS_FINANCE_REJECTED = 'finance_rejected';
    const STATUS_CLOSED = 'closed';

    const FAIL_INVALID_CODE_FORMAT = 'INVALID_CODE_FORMAT';
    const FAIL_CODE_NOT_FOUND = 'CODE_NOT_FOUND';
    const FAIL_OUTBOUND_TIME_MISSING = 'OUTBOUND_TIME_MISSING';
    const FAIL_NOT_OWNED_BY_DEALER = 'NOT_OWNED_BY_DEALER';
    const FAIL_AFTER_SALES_EXPIRED = 'AFTER_SALES_EXPIRED';
    const FAIL_CODE_ALREADY_IN_RETURN_PROCESS = 'CODE_ALREADY_IN_RETURN_PROCESS';

    const PER_PAGE_DEFAULT = 20;

    /**
     * 渲染退货申请设置页。
     */
    public static function render_admin_settings() {
        if (!AEGIS_System_Roles::user_can_manage_system()) {
            wp_die('您无权访问该页面。');
        }

        $stored = get_option(AEGIS_System::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $registered = AEGIS_System::get_registered_modules();
        $returns_enabled = false;
        if (isset($stored['returns'])) {
            if (is_array($stored['returns']) && array_key_exists('enabled', $stored['returns'])) {
                $returns_enabled = !empty($stored['returns']['enabled']);
            } else {
                $returns_enabled = !empty($stored['returns']);
            }
        } elseif (isset($registered['returns'])) {
            $returns_enabled = !empty($registered['returns']['default']);
        }
        $after_sales_days = (int) get_option(AEGIS_System::RETURNS_AFTER_SALES_DAYS_OPTION, 30);

        $validation = ['success' => true, 'message' => ''];
        $notice_message = '';
        $notice_class = '';
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_SYSTEM,
                    'nonce_field'     => 'aegis_returns_settings_nonce',
                    'nonce_action'    => 'aegis_returns_settings_save',
                    'whitelist'       => [
                        'aegis_returns_settings_nonce',
                        '_wp_http_referer',
                        '_aegis_idempotency',
                        'returns_enabled',
                        AEGIS_System::RETURNS_AFTER_SALES_DAYS_OPTION,
                        'submit',
                    ],
                    'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                ]
            );
        }

        if ($validation['success'] && 'POST' === $_SERVER['REQUEST_METHOD']) {
            $new_enabled = isset($_POST['returns_enabled']);
            $new_days = isset($_POST[AEGIS_System::RETURNS_AFTER_SALES_DAYS_OPTION])
                ? absint($_POST[AEGIS_System::RETURNS_AFTER_SALES_DAYS_OPTION])
                : 0;

            if ($new_days < 1 || $new_days > 3650) {
                $notice_message = '售后天数需在 1 到 3650 之间。';
                $notice_class = 'error';
            } else {
                $clean_states = [];
                foreach ($registered as $slug => $module) {
                    if ($slug === 'core_manager') {
                        if (isset($stored[$slug]) && is_array($stored[$slug])) {
                            $clean_states[$slug] = $stored[$slug];
                            $clean_states[$slug]['enabled'] = true;
                        } else {
                            $clean_states[$slug] = true;
                        }
                        continue;
                    }

                    $existing_enabled = null;
                    if (isset($stored[$slug])) {
                        if (is_array($stored[$slug]) && array_key_exists('enabled', $stored[$slug])) {
                            $existing_enabled = !empty($stored[$slug]['enabled']);
                        } elseif (!is_array($stored[$slug])) {
                            $existing_enabled = !empty($stored[$slug]);
                        }
                    }
                    if (null === $existing_enabled) {
                        $existing_enabled = !empty($module['default']);
                    }

                    if (isset($stored[$slug]) && is_array($stored[$slug])) {
                        $clean_states[$slug] = $stored[$slug];
                        $clean_states[$slug]['enabled'] = $existing_enabled;
                    } else {
                        $clean_states[$slug] = $existing_enabled;
                    }
                }

                $previous_enabled = $returns_enabled;
                if (isset($clean_states['returns']) && is_array($clean_states['returns'])) {
                    $clean_states['returns']['enabled'] = $new_enabled;
                } else {
                    $clean_states['returns'] = $new_enabled;
                }

                update_option(AEGIS_System::OPTION_KEY, $clean_states, true);
                update_option(AEGIS_System::RETURNS_AFTER_SALES_DAYS_OPTION, $new_days, true);

                if ($new_enabled !== $previous_enabled) {
                    $action = $new_enabled ? AEGIS_System::ACTION_MODULE_ENABLE : AEGIS_System::ACTION_MODULE_DISABLE;
                    AEGIS_Access_Audit::record_event(
                        $action,
                        'SUCCESS',
                        [
                            'module' => 'returns',
                        ]
                    );
                }

                AEGIS_Access_Audit::record_event(
                    AEGIS_System::ACTION_SETTINGS_UPDATE,
                    'SUCCESS',
                    [
                        'entity_type'      => 'returns_settings',
                        'enabled'          => $new_enabled,
                        'after_sales_days' => $new_days,
                    ]
                );

                $notice_message = '退货申请设置已保存。';
                $notice_class = 'updated';

                $stored = get_option(AEGIS_System::OPTION_KEY, []);
                if (!is_array($stored)) {
                    $stored = [];
                }
                $returns_enabled = $new_enabled;
                $after_sales_days = $new_days;
            }
        } elseif (!empty($validation['message'])) {
            $notice_message = $validation['message'];
            $notice_class = 'error';
        }

        echo '<div class="wrap">';
        echo '<h1>退货申请设置</h1>';

        if ($notice_message) {
            echo '<div class="' . esc_attr($notice_class) . '"><p>' . esc_html($notice_message) . '</p></div>';
        }

        $idempotency_key = wp_generate_uuid4();
        echo '<form method="post">';
        wp_nonce_field('aegis_returns_settings_save', 'aegis_returns_settings_nonce');
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row">模块开关</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="returns_enabled" ' . checked($returns_enabled, true, false) . ' /> 启用退货申请模块</label>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">售后天数</th>';
        echo '<td>';
        echo '<input type="number" class="small-text" name="' . esc_attr(AEGIS_System::RETURNS_AFTER_SALES_DAYS_OPTION) . '" min="1" max="3650" value="' . esc_attr($after_sales_days) . '" />';
        echo '<p class="description">退货申请可在出货后多少天内发起。</p>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';
        submit_button('保存配置');
        echo '</form>';
        echo '</div>';
    }

    /**
     * 渲染退货申请占位页。
     *
     * @param string $portal_url
     * @return string
     */
    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System::is_module_enabled('returns')) {
            return '<div class="aegis-t-a5">模块未启用。</div>';
        }

        nocache_headers();

        $user = wp_get_current_user();
        $roles = (array) ($user ? $user->roles : []);

        if (in_array('aegis_dealer', $roles, true)) {
            return self::render_dealer_panel($portal_url, $user);
        }

        $is_hq = AEGIS_System_Roles::is_hq_admin($user);
        $is_sales = in_array('aegis_sales', $roles, true);
        $is_finance = in_array('aegis_finance', $roles, true);
        $is_wh_mgr = in_array('aegis_warehouse_manager', $roles, true);
        $is_wh_staff = in_array('aegis_warehouse_staff', $roles, true);
        if ($is_hq) {
            $stage = isset($_GET['stage']) ? sanitize_key(wp_unslash($_GET['stage'])) : 'sales';
            if ('finance' === $stage) {
                return self::render_finance_panel($portal_url, $user);
            }
            if ('override' === $stage) {
                return self::render_override_panel($portal_url, $user);
            }
            return self::render_sales_panel($portal_url, $user);
        }

        if ($is_sales) {
            $stage = isset($_GET['stage']) ? sanitize_key(wp_unslash($_GET['stage'])) : '';
            if ('override' === $stage && current_user_can(AEGIS_System::CAP_RETURNS_OVERRIDE_ISSUE)) {
                return self::render_override_panel($portal_url, $user);
            }
            return self::render_sales_panel($portal_url, $user);
        }

        if ($is_wh_mgr || $is_wh_staff) {
            return self::render_warehouse_panel($portal_url, $user);
        }

        if ($is_finance) {
            return self::render_finance_panel($portal_url, $user);
        }

        return '<div class="aegis-t-a5">该角色功能将在后续 PR 实现。</div>';
    }

    protected static function render_sales_panel($portal_url, $user) {
        global $wpdb;

        $is_hq = AEGIS_System_Roles::is_hq_admin($user);
        if (!$is_hq && !current_user_can(AEGIS_System::CAP_RETURNS_SALES_REVIEW)) {
            status_header(403);
            return '<div class="aegis-t-a5">您无权访问该页面。</div>';
        }

        $base_url = add_query_arg('m', 'returns', $portal_url);
        $request_id = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'submitted';
        $allowed_status = [self::STATUS_SUBMITTED, self::STATUS_SALES_APPROVED, self::STATUS_SALES_REJECTED];
        if (!in_array($status_filter, $allowed_status, true)) {
            $status_filter = self::STATUS_SUBMITTED;
        }

        $messages = [];
        $errors = [];
        if (isset($_GET['aegis_returns_message'])) {
            $messages[] = sanitize_text_field(wp_unslash($_GET['aegis_returns_message']));
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['returns_action']) ? sanitize_key(wp_unslash($_POST['returns_action'])) : '';
            if (in_array($action, ['sales_approve', 'sales_reject'], true)) {
                $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_SALES_REVIEW,
                        'nonce_field'     => 'aegis_returns_sales_nonce',
                        'nonce_action'    => 'aegis_returns_sales_action',
                        'whitelist'       => [
                            'returns_action',
                            'request_id',
                            'sales_comment',
                            '_aegis_idempotency',
                            '_wp_http_referer',
                            'aegis_returns_sales_nonce',
                        ],
                        'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $sales_comment = isset($_POST['sales_comment']) ? sanitize_textarea_field(wp_unslash($_POST['sales_comment'])) : '';
                    $result = 'sales_approve' === $action
                        ? self::handle_sales_approve($request_id, $sales_comment, $user)
                        : self::handle_sales_reject($request_id, $sales_comment, $user);

                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $redirect_url = add_query_arg(
                            [
                                'aegis_returns_message' => (string) $result,
                            ],
                            $base_url
                        );
                        wp_safe_redirect($redirect_url);
                        exit;
                    }
                }
            }
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $current_user_id = (int) $user->ID;

        if ($is_hq) {
            $requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, d.dealer_name\n                     FROM {$req_table} r\n                     LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id\n                     WHERE r.status = %s\n                     ORDER BY r.submitted_at DESC, r.id DESC\n                     LIMIT 50",
                    $status_filter
                )
            );
        } else {
            $requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, d.dealer_name\n                     FROM {$req_table} r\n                     LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id\n                     WHERE r.status = %s AND d.sales_user_id = %d\n                     ORDER BY r.submitted_at DESC, r.id DESC\n                     LIMIT 50",
                    $status_filter,
                    $current_user_id
                )
            );
        }

        $counts_map = [];
        $request_ids = array_map('intval', wp_list_pluck($requests, 'id'));
        if (!empty($request_ids)) {
            $in_sql = implode(',', array_fill(0, count($request_ids), '%d'));
            $count_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT request_id, COUNT(*) AS cnt FROM {$item_table} WHERE request_id IN ({$in_sql}) GROUP BY request_id",
                    $request_ids
                )
            );
            foreach ($count_rows as $count_row) {
                $counts_map[(int) $count_row->request_id] = (int) $count_row->cnt;
            }
        }

        $current_request = null;
        $current_items = [];
        if ($request_id > 0) {
            if ($is_hq) {
                $current_request = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT r.*, d.dealer_name
                         FROM {$req_table} r
                         LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id
                         WHERE r.id = %d
                         LIMIT 1",
                        $request_id
                    )
                );
            } else {
                $current_request = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT r.*, d.dealer_name
                         FROM {$req_table} r
                         LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id
                         WHERE r.id = %d AND d.sales_user_id = %d
                         LIMIT 1",
                        $request_id,
                        $current_user_id
                    )
                );
            }

            if ($current_request) {
                $current_items = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$item_table} WHERE request_id = %d ORDER BY id ASC",
                        $request_id
                    )
                );
            } else {
                $errors[] = '单据不存在或无权限。';
            }
        }

        return AEGIS_Portal::render_portal_template(
            'returns-sales',
            [
                'base_url' => $base_url,
                'messages' => $messages,
                'errors' => $errors,
                'status_filter' => $status_filter,
                'status_options' => [
                    self::STATUS_SUBMITTED => '待审核',
                    self::STATUS_SALES_APPROVED => '已同意',
                    self::STATUS_SALES_REJECTED => '已驳回',
                ],
                'is_hq' => $is_hq,
                'view_mode' => ($request_id > 0 ? 'detail' : 'list'),
                'requests' => $requests,
                'counts' => $counts_map,
                'request' => $current_request,
                'items' => $current_items,
                'idempotency' => wp_generate_uuid4(),
            ]
        );
    }

    protected static function render_override_panel($portal_url, $user) {
        global $wpdb;

        $is_hq = AEGIS_System_Roles::is_hq_admin($user);
        if (!$is_hq && !current_user_can(AEGIS_System::CAP_RETURNS_OVERRIDE_ISSUE)) {
            status_header(403);
            return '<div class="aegis-t-a5">您无权访问该页面。</div>';
        }

        $base_url = add_query_arg('m', 'returns', $portal_url);
        if ($is_hq || current_user_can(AEGIS_System::CAP_RETURNS_OVERRIDE_ISSUE)) {
            $base_url = add_query_arg('stage', 'override', $base_url);
        }

        $messages = [];
        $errors = [];
        $status_filter = isset($_GET['status_filter']) ? sanitize_key(wp_unslash($_GET['status_filter'])) : 'active';
        $allowed_status = ['active', 'used', 'expired', 'revoked'];
        if (!in_array($status_filter, $allowed_status, true)) {
            $status_filter = 'active';
        }
        $search_code = isset($_GET['search_code']) ? AEGIS_System::normalize_code_value(wp_unslash($_GET['search_code'])) : '';

        if (isset($_GET['aegis_returns_message'])) {
            $messages[] = sanitize_text_field(wp_unslash($_GET['aegis_returns_message']));
        }
        if (isset($_GET['aegis_returns_error'])) {
            $errors[] = sanitize_text_field(wp_unslash($_GET['aegis_returns_error']));
        }

        $issued_token = isset($_GET['issued_token']) ? sanitize_text_field(wp_unslash($_GET['issued_token'])) : '';
        if ('' !== $issued_token) {
            $issued_plain = get_transient('aegis_returns_override_plain_' . $issued_token);
            delete_transient('aegis_returns_override_plain_' . $issued_token);
            if (is_string($issued_plain) && '' !== $issued_plain) {
                $messages[] = sprintf('特批码已生成：%s（请复制发送给经销商）', $issued_plain);
            }
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['returns_action']) ? sanitize_key(wp_unslash($_POST['returns_action'])) : '';
            if (in_array($action, ['issue_override', 'revoke_override'], true)) {
                $capability = 'issue_override' === $action
                    ? AEGIS_System::CAP_RETURNS_OVERRIDE_ISSUE
                    : AEGIS_System::CAP_RETURNS_OVERRIDE_REVOKE;
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => $capability,
                        'nonce_field'     => 'aegis_returns_override_nonce',
                        'nonce_action'    => 'aegis_returns_override_action',
                        'whitelist'       => [
                            'returns_action',
                            'override_id',
                            'code_value',
                            'dealer_id',
                            'reason_text',
                            'expires_hours',
                            'search_code',
                            'status',
                            '_aegis_idempotency',
                            '_wp_http_referer',
                            'aegis_returns_override_nonce',
                        ],
                        'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif ('issue_override' === $action) {
                    $code_value = isset($_POST['code_value']) ? AEGIS_System::normalize_code_value(wp_unslash($_POST['code_value'])) : '';
                    $dealer_id = isset($_POST['dealer_id']) ? (int) $_POST['dealer_id'] : 0;
                    $reason_text = isset($_POST['reason_text']) ? sanitize_textarea_field(wp_unslash($_POST['reason_text'])) : '';
                    $expires_hours = isset($_POST['expires_hours']) ? (int) $_POST['expires_hours'] : 0;
                    $result = self::handle_override_issue($code_value, $dealer_id, $reason_text, $expires_hours, $user);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $redirect_url = add_query_arg(
                            [
                                'issued_token' => $result['issued_token'],
                                'status_filter' => $status_filter,
                                'search_code' => $search_code,
                            ],
                            $base_url
                        );
                        wp_safe_redirect($redirect_url);
                        exit;
                    }
                } else {
                    $override_id = isset($_POST['override_id']) ? (int) $_POST['override_id'] : 0;
                    $result = self::handle_override_revoke($override_id, $user);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $redirect_url = add_query_arg(
                            [
                                'aegis_returns_message' => (string) $result,
                                'status_filter' => $status_filter,
                                'search_code' => $search_code,
                            ],
                            $base_url
                        );
                        wp_safe_redirect($redirect_url);
                        exit;
                    }
                }
            }
        }

        $override_table = $wpdb->prefix . AEGIS_System::RETURN_OVERRIDE_CODE_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $now = current_time('mysql');
        $where_sql = '';
        $where_args = [];

        if ('active' === $status_filter) {
            $where_sql = 'o.status = %s AND o.expires_at > %s';
            $where_args = ['active', $now];
        } elseif ('expired' === $status_filter) {
            $where_sql = 'o.status = %s AND o.expires_at <= %s';
            $where_args = ['active', $now];
        } elseif ('used' === $status_filter) {
            $where_sql = 'o.status = %s';
            $where_args = ['used'];
        } else {
            $where_sql = 'o.status = %s';
            $where_args = ['revoked'];
        }
        if ('' !== $search_code) {
            $where_sql .= ' AND o.code_value = %s';
            $where_args[] = $search_code;
        }

        $query = "SELECT o.*, d.dealer_name
                  FROM {$override_table} o
                  LEFT JOIN {$dealer_table} d ON d.id = o.dealer_id
                  WHERE {$where_sql}
                  ORDER BY o.issued_at DESC
                  LIMIT 50";
        $override_rows = $wpdb->get_results($wpdb->prepare($query, $where_args));

        return AEGIS_Portal::render_portal_template(
            'returns-override',
            [
                'base_url' => $base_url,
                'messages' => $messages,
                'errors' => $errors,
                'status_filter' => $status_filter,
                'status_options' => ['active' => 'ACTIVE', 'used' => 'USED', 'expired' => 'EXPIRED', 'revoked' => 'REVOKED'],
                'search_code' => $search_code,
                'rows' => $override_rows,
                'idempotency' => wp_generate_uuid4(),
                'is_hq' => $is_hq,
                'can_revoke' => ($is_hq || current_user_can(AEGIS_System::CAP_RETURNS_OVERRIDE_REVOKE)),
                'nav' => [
                    'sales_url' => $is_hq ? add_query_arg(['m' => 'returns', 'stage' => 'sales'], $portal_url) : '',
                    'override_url' => $base_url,
                    'finance_url' => $is_hq ? add_query_arg(['m' => 'returns', 'stage' => 'finance'], $portal_url) : '',
                ],
            ]
        );
    }

    protected static function load_request_for_sales($request_id, $user) {
        global $wpdb;

        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return null;
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $is_hq = AEGIS_System_Roles::is_hq_admin($user);

        if ($is_hq) {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT r.*, d.dealer_name
                     FROM {$req_table} r
                     LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id
                     WHERE r.id = %d
                     LIMIT 1",
                    $request_id
                )
            );
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, d.dealer_name
                 FROM {$req_table} r
                 LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id
                 WHERE r.id = %d AND d.sales_user_id = %d
                 LIMIT 1",
                $request_id,
                (int) $user->ID
            )
        );
    }

    protected static function handle_sales_approve($request_id, $comment, $user) {
        global $wpdb;

        $req = self::load_request_for_sales($request_id, $user);
        if (!$req) {
            return new WP_Error('not_found', '单据不存在或无权限。');
        }

        if (self::STATUS_SUBMITTED !== $req->status) {
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
        $now = current_time('mysql');

        $wpdb->query('START TRANSACTION');
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$req_table}
                 SET status = %s,
                     sales_audited_by = %d,
                     sales_audited_at = %s,
                     sales_comment = %s,
                     hard_locked_at = %s,
                     updated_at = %s
                 WHERE id = %d AND status = %s",
                self::STATUS_SALES_APPROVED,
                (int) get_current_user_id(),
                $now,
                $comment,
                $now,
                $now,
                (int) $request_id,
                self::STATUS_SUBMITTED
            )
        );

        if (1 !== (int) $updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$lock_table} SET lock_status = %s, updated_at = %s WHERE request_id = %d",
                self::STATUS_SALES_APPROVED,
                $now,
                (int) $request_id
            )
        );
        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_SALES_APPROVE',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'entity_id' => (int) $request_id,
                'request_no' => $req->request_no,
                'dealer_id' => (int) $req->dealer_id,
                'dealer_name' => $req->dealer_name ?? '',
                'actor_user_id' => (int) get_current_user_id(),
            ]
        );

        return '已同意，等待仓库核对。';
    }

    protected static function handle_sales_reject($request_id, $comment, $user) {
        global $wpdb;

        $comment = trim((string) $comment);
        if ('' === $comment) {
            return new WP_Error('comment_required', '驳回必须填写原因。');
        }

        $req = self::load_request_for_sales($request_id, $user);
        if (!$req) {
            return new WP_Error('not_found', '单据不存在或无权限。');
        }

        if (self::STATUS_SUBMITTED !== $req->status) {
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
        $now = current_time('mysql');

        $wpdb->query('START TRANSACTION');
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$req_table}
                 SET status = %s,
                     sales_audited_by = %d,
                     sales_audited_at = %s,
                     sales_comment = %s,
                     updated_at = %s
                 WHERE id = %d AND status = %s",
                self::STATUS_SALES_REJECTED,
                (int) get_current_user_id(),
                $now,
                $comment,
                $now,
                (int) $request_id,
                self::STATUS_SUBMITTED
            )
        );
        if (1 !== (int) $updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$lock_table} WHERE request_id = %d",
                (int) $request_id
            )
        );

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_SALES_REJECT',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'entity_id' => (int) $request_id,
                'request_no' => $req->request_no,
                'dealer_id' => (int) $req->dealer_id,
                'dealer_name' => $req->dealer_name ?? '',
                'actor_user_id' => (int) get_current_user_id(),
                'comment' => $comment,
            ]
        );

        return '已驳回。';
    }

    protected static function render_warehouse_panel($portal_url, $user) {
        global $wpdb;

        $is_hq = AEGIS_System_Roles::is_hq_admin($user);
        if (!$is_hq && !current_user_can(AEGIS_System::CAP_RETURNS_WAREHOUSE_CHECK)) {
            status_header(403);
            return '<div class="aegis-t-a5">您无权访问该页面。</div>';
        }

        $base_url = add_query_arg('m', 'returns', $portal_url);
        $request_id = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : self::STATUS_SALES_APPROVED;
        $allowed_status = [
            self::STATUS_SALES_APPROVED,
            self::STATUS_WAREHOUSE_CHECKING,
            self::STATUS_WAREHOUSE_APPROVED,
            self::STATUS_WAREHOUSE_REJECTED,
        ];
        if (!in_array($status_filter, $allowed_status, true)) {
            $status_filter = self::STATUS_SALES_APPROVED;
        }

        $messages = [];
        $errors = [];
        if (isset($_GET['aegis_returns_message'])) {
            $messages[] = sanitize_text_field(wp_unslash($_GET['aegis_returns_message']));
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['returns_action']) ? sanitize_key(wp_unslash($_POST['returns_action'])) : '';
            if (in_array($action, ['warehouse_start', 'warehouse_scan', 'warehouse_approve', 'warehouse_reject'], true)) {
                $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_WAREHOUSE_CHECK,
                        'nonce_field'     => 'aegis_returns_wh_nonce',
                        'nonce_action'    => 'aegis_returns_wh_action',
                        'whitelist'       => [
                            'returns_action',
                            'request_id',
                            'scan_code',
                            'warehouse_comment',
                            'reject_reason',
                            '_aegis_idempotency',
                            '_wp_http_referer',
                            'aegis_returns_wh_nonce',
                        ],
                        'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $scan_code = isset($_POST['scan_code']) ? sanitize_text_field(wp_unslash($_POST['scan_code'])) : '';
                    $reject_reason = isset($_POST['reject_reason']) ? sanitize_textarea_field(wp_unslash($_POST['reject_reason'])) : '';
                    $warehouse_comment = isset($_POST['warehouse_comment']) ? sanitize_textarea_field(wp_unslash($_POST['warehouse_comment'])) : '';

                    if ('warehouse_start' === $action) {
                        $result = self::handle_warehouse_start($request_id, $user);
                    } elseif ('warehouse_scan' === $action) {
                        $result = self::handle_warehouse_scan($request_id, $scan_code, $user);
                    } elseif ('warehouse_approve' === $action) {
                        $result = self::handle_warehouse_approve($request_id, $warehouse_comment, $user);
                    } else {
                        $result = self::handle_warehouse_reject($request_id, $reject_reason, $user);
                    }

                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $redirect_url = add_query_arg(
                            [
                                'request_id' => $request_id,
                                'status' => $status_filter,
                                'aegis_returns_message' => (string) $result,
                            ],
                            $base_url
                        );
                        wp_safe_redirect($redirect_url);
                        exit;
                    }
                }
            }
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $check_table = $wpdb->prefix . AEGIS_System::RETURN_WAREHOUSE_CHECK_TABLE;
        $scan_table = $wpdb->prefix . AEGIS_System::RETURN_WAREHOUSE_SCAN_TABLE;

        $requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, d.dealer_name
                 FROM {$req_table} r
                 LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id
                 WHERE r.status = %s
                 ORDER BY r.updated_at DESC, r.id DESC
                 LIMIT 50",
                $status_filter
            )
        );

        $counts_map = [];
        $request_ids = array_map('intval', wp_list_pluck($requests, 'id'));
        if (!empty($request_ids)) {
            $in_sql = implode(',', array_fill(0, count($request_ids), '%d'));
            $count_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT request_id, COUNT(*) AS cnt FROM {$item_table} WHERE request_id IN ({$in_sql}) GROUP BY request_id",
                    $request_ids
                )
            );
            foreach ($count_rows as $count_row) {
                $counts_map[(int) $count_row->request_id] = (int) $count_row->cnt;
            }
        }

        $current_request = null;
        $current_items = [];
        $check_row = null;
        $scan_rows = [];
        $expected_codes = [];
        $matched_codes = [];
        $missing_codes = [];
        $bad_scans_count = 0;
        $dup_count = 0;

        if ($request_id > 0) {
            $current_request = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT r.*, d.dealer_name
                     FROM {$req_table} r
                     LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id
                     WHERE r.id = %d
                     LIMIT 1",
                    $request_id
                )
            );

            if ($current_request) {
                $current_items = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$item_table} WHERE request_id = %d ORDER BY id ASC",
                        $request_id
                    )
                );

                $check_row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$check_table} WHERE request_id = %d LIMIT 1",
                        $request_id
                    )
                );

                if ($check_row) {
                    $scan_rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$scan_table} WHERE warehouse_check_id = %d ORDER BY id DESC LIMIT 200",
                            (int) $check_row->id
                        )
                    );
                }

                foreach ($current_items as $item) {
                    $code_value = isset($item->code_value) ? AEGIS_System::normalize_code_value($item->code_value) : '';
                    if ('' !== $code_value) {
                        $expected_codes[$code_value] = true;
                    }
                }

                foreach ($scan_rows as $scan_row) {
                    $scan_code_value = isset($scan_row->code_value) ? AEGIS_System::normalize_code_value($scan_row->code_value) : '';
                    if ('MATCH' === ($scan_row->scan_result ?? '') && '' !== $scan_code_value) {
                        $matched_codes[$scan_code_value] = true;
                    }
                    if (in_array(($scan_row->scan_result ?? ''), ['NOT_IN_REQUEST', 'CONFLICT', 'INVALID'], true)) {
                        $bad_scans_count++;
                    }
                    if ('DUPLICATE' === ($scan_row->scan_result ?? '')) {
                        $dup_count++;
                    }
                }

                $missing_codes = array_values(array_diff(array_keys($expected_codes), array_keys($matched_codes)));
            } else {
                $errors[] = '单据不存在。';
                $request_id = 0;
            }
        }

        $summary = [
            'expected_total' => count($expected_codes),
            'matched_total' => count($matched_codes),
            'missing_total' => count($missing_codes),
            'bad_total' => $bad_scans_count,
            'dup_total' => $dup_count,
        ];

        $request_status = $current_request->status ?? '';
        $checking_states = [self::STATUS_SALES_APPROVED, self::STATUS_WAREHOUSE_CHECKING];
        $can_approve = in_array($request_status, $checking_states, true) && 0 === $summary['missing_total'] && 0 === $summary['bad_total'];

        $context = [
            'base_url' => $base_url,
            'messages' => $messages,
            'errors' => $errors,
            'status_filter' => $status_filter,
            'status_options' => [
                self::STATUS_SALES_APPROVED => '待核对',
                self::STATUS_WAREHOUSE_CHECKING => '核对中',
                self::STATUS_WAREHOUSE_APPROVED => '已通过',
                self::STATUS_WAREHOUSE_REJECTED => '已驳回',
            ],
            'view_mode' => ($request_id > 0 ? 'detail' : 'list'),
            'requests' => $requests,
            'counts' => $counts_map,
            'request' => $current_request,
            'items' => $current_items,
            'warehouse_check' => $check_row,
            'scans' => $scan_rows,
            'summary' => $summary,
            'missing_codes' => $missing_codes,
            'matched_codes' => array_keys($matched_codes),
            'can_start' => self::STATUS_SALES_APPROVED === $request_status && empty($check_row),
            'can_scan' => in_array($request_status, $checking_states, true),
            'can_approve' => $can_approve,
            'can_reject' => in_array($request_status, $checking_states, true),
            'idempotency' => wp_generate_uuid4(),
        ];

        return AEGIS_Portal::render_portal_template('returns-warehouse', $context);
    }

    protected static function handle_warehouse_start($request_id, $user) {
        global $wpdb;

        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return new WP_Error('invalid_request', '无效的退货单。');
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $check_table = $wpdb->prefix . AEGIS_System::RETURN_WAREHOUSE_CHECK_TABLE;
        $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
        $now = current_time('mysql');

        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$req_table} WHERE id = %d LIMIT 1", $request_id));
        if (!$request) {
            return new WP_Error('not_found', '单据不存在。');
        }
        if (self::STATUS_SALES_APPROVED !== $request->status) {
            return new WP_Error('state_changed', '当前状态不允许开始核对。');
        }

        $locks = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$lock_table} WHERE request_id = %d", $request_id));
        if ($locks <= 0) {
            return new WP_Error('lock_missing', '未找到锁码记录，无法开始核对。');
        }

        $wpdb->query('START TRANSACTION');
        $check_exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$check_table} WHERE request_id = %d LIMIT 1", $request_id));
        if ($check_exists <= 0) {
            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$check_table} (request_id, status, started_by, started_at, created_at, updated_at)
                     VALUES (%d, %s, %d, %s, %s, %s)",
                    $request_id,
                    'checking',
                    (int) $user->ID,
                    $now,
                    $now,
                    $now
                )
            );
            if (false === $inserted) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', '创建核对任务失败。');
            }
        }

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$req_table} SET status = %s, updated_at = %s WHERE id = %d AND status = %s",
                self::STATUS_WAREHOUSE_CHECKING,
                $now,
                $request_id,
                self::STATUS_SALES_APPROVED
            )
        );
        if (false === $updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '更新单据状态失败。');
        }

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_WAREHOUSE_START',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'entity_id' => $request_id,
                'actor_user_id' => (int) $user->ID,
            ]
        );

        return '已开始核对。';
    }

    protected static function handle_warehouse_scan($request_id, $scan_code, $user) {
        global $wpdb;

        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return new WP_Error('invalid_request', '无效的退货单。');
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
        $check_table = $wpdb->prefix . AEGIS_System::RETURN_WAREHOUSE_CHECK_TABLE;
        $scan_table = $wpdb->prefix . AEGIS_System::RETURN_WAREHOUSE_SCAN_TABLE;
        $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $now = current_time('mysql');

        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$req_table} WHERE id = %d LIMIT 1", $request_id));
        if (!$request) {
            return new WP_Error('not_found', '单据不存在。');
        }
        if (!in_array($request->status, [self::STATUS_SALES_APPROVED, self::STATUS_WAREHOUSE_CHECKING], true)) {
            return new WP_Error('state_changed', '当前状态不允许扫码。');
        }

        $code_value = AEGIS_System::normalize_code_value($scan_code);
        if ('' === $code_value) {
            return new WP_Error('scan_required', '请输入有效防伪码。');
        }

        $expected_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT code_value, code_id FROM {$item_table} WHERE request_id = %d",
                $request_id
            )
        );
        $expected_map = [];
        foreach ($expected_rows as $row) {
            $expected_value = AEGIS_System::normalize_code_value($row->code_value ?? '');
            if ('' !== $expected_value) {
                $expected_map[$expected_value] = (int) $row->code_id;
            }
        }

        $wpdb->query('START TRANSACTION');

        $check_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$check_table} WHERE request_id = %d LIMIT 1", $request_id));
        if (!$check_row) {
            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$check_table} (request_id, status, started_by, started_at, created_at, updated_at)
                     VALUES (%d, %s, %d, %s, %s, %s)",
                    $request_id,
                    'checking',
                    (int) $user->ID,
                    $now,
                    $now,
                    $now
                )
            );
            if (false === $inserted) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', '创建核对任务失败。');
            }
            $check_id = (int) $wpdb->insert_id;
        } else {
            $check_id = (int) $check_row->id;
        }

        $scan_result = 'MATCH';
        $scan_message = '匹配';
        $matched_code_id = null;

        if (isset($expected_map[$code_value])) {
            $matched_code_id = (int) $expected_map[$code_value];
            $match_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$scan_table}
                     WHERE warehouse_check_id = %d AND code_value = %s AND scan_result = 'MATCH'",
                    $check_id,
                    $code_value
                )
            );
            if ($match_count > 0) {
                $scan_result = 'DUPLICATE';
                $scan_message = '重复扫码';
            }
        } else {
            $matched_code_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$code_table} WHERE code = %s LIMIT 1", $code_value));
            if ($matched_code_id <= 0) {
                $matched_code_id = null;
                $scan_result = 'INVALID';
                $scan_message = '防伪码不存在/无效';
            } else {
                $locked_request_id = (int) $wpdb->get_var($wpdb->prepare("SELECT request_id FROM {$lock_table} WHERE code_id = %d LIMIT 1", $matched_code_id));
                if ($locked_request_id > 0 && $locked_request_id !== $request_id) {
                    $scan_result = 'CONFLICT';
                    $scan_message = '该码已被其他退货单占用';
                } else {
                    $scan_result = 'NOT_IN_REQUEST';
                    $scan_message = '不在本申请单内';
                }
            }
        }

        $inserted_scan = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$scan_table}
                (warehouse_check_id, code_id, code_value, scan_result, scan_message, scanned_by, scanned_at, meta)
                VALUES (%d, %s, %s, %s, %s, %d, %s, %s)",
                $check_id,
                null === $matched_code_id ? null : $matched_code_id,
                $code_value,
                $scan_result,
                $scan_message,
                (int) $user->ID,
                $now,
                wp_json_encode(
                    [
                        'request_id' => $request_id,
                        'scan_raw' => $scan_code,
                    ]
                )
            )
        );
        if (false === $inserted_scan) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '扫码记录写入失败。');
        }

        $status_updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$req_table} SET status = %s, updated_at = %s WHERE id = %d AND status = %s",
                self::STATUS_WAREHOUSE_CHECKING,
                $now,
                $request_id,
                self::STATUS_SALES_APPROVED
            )
        );
        if (false === $status_updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '状态更新失败。');
        }

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_WAREHOUSE_SCAN',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'entity_id' => $request_id,
                'actor_user_id' => (int) $user->ID,
                'scan_result' => $scan_result,
            ]
        );

        return $scan_message;
    }

    protected static function handle_warehouse_approve($request_id, $warehouse_comment, $user) {
        global $wpdb;

        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return new WP_Error('invalid_request', '无效的退货单。');
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
        $check_table = $wpdb->prefix . AEGIS_System::RETURN_WAREHOUSE_CHECK_TABLE;
        $scan_table = $wpdb->prefix . AEGIS_System::RETURN_WAREHOUSE_SCAN_TABLE;
        $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
        $now = current_time('mysql');

        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$req_table} WHERE id = %d LIMIT 1", $request_id));
        if (!$request) {
            return new WP_Error('not_found', '单据不存在。');
        }
        if (!in_array($request->status, [self::STATUS_SALES_APPROVED, self::STATUS_WAREHOUSE_CHECKING], true)) {
            return new WP_Error('state_changed', '当前状态不允许通过。');
        }

        $check_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$check_table} WHERE request_id = %d LIMIT 1", $request_id));
        if (!$check_row) {
            return new WP_Error('not_ready', '尚未开始核对。');
        }

        $expected_rows = $wpdb->get_results($wpdb->prepare("SELECT code_value FROM {$item_table} WHERE request_id = %d", $request_id));
        $expected_codes = [];
        foreach ($expected_rows as $row) {
            $expected_value = AEGIS_System::normalize_code_value($row->code_value ?? '');
            if ('' !== $expected_value) {
                $expected_codes[$expected_value] = true;
            }
        }

        $matched_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT code_value FROM {$scan_table} WHERE warehouse_check_id = %d AND scan_result = 'MATCH'",
                (int) $check_row->id
            )
        );
        $matched_codes = [];
        foreach ($matched_rows as $row) {
            $matched_value = AEGIS_System::normalize_code_value($row->code_value ?? '');
            if ('' !== $matched_value) {
                $matched_codes[$matched_value] = true;
            }
        }

        $missing_codes = array_diff(array_keys($expected_codes), array_keys($matched_codes));
        $bad_scans_total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$scan_table}
                 WHERE warehouse_check_id = %d AND scan_result IN ('NOT_IN_REQUEST','CONFLICT','INVALID')",
                (int) $check_row->id
            )
        );
        if (!empty($missing_codes) || $bad_scans_total > 0) {
            return new WP_Error('not_ready', '存在缺失或异常扫码，无法通过。');
        }

        $comment = sanitize_textarea_field((string) $warehouse_comment);
        $wpdb->query('START TRANSACTION');

        $updated_check = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$check_table}
                 SET status = %s, finished_by = %d, finished_at = %s, updated_at = %s
                 WHERE request_id = %d",
                'approved',
                (int) $user->ID,
                $now,
                $now,
                $request_id
            )
        );
        if (false === $updated_check) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '核对状态更新失败。');
        }

        $updated_req = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$req_table}
                 SET status = %s,
                     warehouse_checked_by = %d,
                     warehouse_checked_at = %s,
                     warehouse_comment = %s,
                     updated_at = %s
                 WHERE id = %d AND status IN (%s, %s)",
                self::STATUS_WAREHOUSE_APPROVED,
                (int) $user->ID,
                $now,
                $comment,
                $now,
                $request_id,
                self::STATUS_SALES_APPROVED,
                self::STATUS_WAREHOUSE_CHECKING
            )
        );
        if (1 !== (int) $updated_req) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $updated_lock = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$lock_table} SET lock_status = %s, updated_at = %s WHERE request_id = %d",
                self::STATUS_WAREHOUSE_APPROVED,
                $now,
                $request_id
            )
        );
        if (false === $updated_lock) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '锁码状态更新失败。');
        }

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_WAREHOUSE_APPROVE',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'entity_id' => $request_id,
                'actor_user_id' => (int) $user->ID,
            ]
        );

        return '核对通过，已提交财务审核。';
    }

    protected static function handle_warehouse_reject($request_id, $reject_reason, $user) {
        global $wpdb;

        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return new WP_Error('invalid_request', '无效的退货单。');
        }

        $reason = trim((string) $reject_reason);
        if ('' === $reason) {
            return new WP_Error('reject_reason_required', '驳回必须填写原因。');
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $check_table = $wpdb->prefix . AEGIS_System::RETURN_WAREHOUSE_CHECK_TABLE;
        $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
        $now = current_time('mysql');

        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$req_table} WHERE id = %d LIMIT 1", $request_id));
        if (!$request) {
            return new WP_Error('not_found', '单据不存在。');
        }
        if (!in_array($request->status, [self::STATUS_SALES_APPROVED, self::STATUS_WAREHOUSE_CHECKING], true)) {
            return new WP_Error('state_changed', '当前状态不允许驳回。');
        }

        $wpdb->query('START TRANSACTION');

        $check_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$check_table} WHERE request_id = %d LIMIT 1", $request_id));
        if (!$check_row) {
            $inserted = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$check_table} (request_id, status, started_by, started_at, created_at, updated_at)
                     VALUES (%d, %s, %d, %s, %s, %s)",
                    $request_id,
                    'checking',
                    (int) $user->ID,
                    $now,
                    $now,
                    $now
                )
            );
            if (false === $inserted) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', '创建核对任务失败。');
            }
        }

        $updated_check = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$check_table}
                 SET status = %s,
                     reject_reason = %s,
                     finished_by = %d,
                     finished_at = %s,
                     updated_at = %s
                 WHERE request_id = %d",
                'rejected',
                $reason,
                (int) $user->ID,
                $now,
                $now,
                $request_id
            )
        );
        if (false === $updated_check) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '核对状态更新失败。');
        }

        $updated_req = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$req_table}
                 SET status = %s,
                     warehouse_checked_by = %d,
                     warehouse_checked_at = %s,
                     warehouse_comment = %s,
                     updated_at = %s
                 WHERE id = %d AND status IN (%s, %s)",
                self::STATUS_WAREHOUSE_REJECTED,
                (int) $user->ID,
                $now,
                $reason,
                $now,
                $request_id,
                self::STATUS_SALES_APPROVED,
                self::STATUS_WAREHOUSE_CHECKING
            )
        );
        if (1 !== (int) $updated_req) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $deleted_locks = $wpdb->query($wpdb->prepare("DELETE FROM {$lock_table} WHERE request_id = %d", $request_id));
        if (false === $deleted_locks) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '释放锁码失败。');
        }

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_WAREHOUSE_REJECT',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'entity_id' => $request_id,
                'actor_user_id' => (int) $user->ID,
                'reject_reason' => $reason,
            ]
        );

        return '已驳回并释放锁码。';
    }

    protected static function render_finance_panel($portal_url, $user) {
        global $wpdb;

        $is_hq = AEGIS_System_Roles::is_hq_admin($user);
        if (!$is_hq && !current_user_can(AEGIS_System::CAP_RETURNS_FINANCE_REVIEW)) {
            status_header(403);
            return '<div class="aegis-t-a5">您无权访问该页面。</div>';
        }

        $base_url = add_query_arg('m', 'returns', $portal_url);
        if ($is_hq) {
            $base_url = add_query_arg('stage', 'finance', $base_url);
        }

        $request_id = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : self::STATUS_WAREHOUSE_APPROVED;
        $allowed_status = [self::STATUS_WAREHOUSE_APPROVED, self::STATUS_CLOSED, self::STATUS_FINANCE_REJECTED];
        if (!in_array($status_filter, $allowed_status, true)) {
            $status_filter = self::STATUS_WAREHOUSE_APPROVED;
        }

        $messages = [];
        $errors = [];
        if (isset($_GET['aegis_returns_message'])) {
            $messages[] = sanitize_text_field(wp_unslash($_GET['aegis_returns_message']));
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['returns_action']) ? sanitize_key(wp_unslash($_POST['returns_action'])) : '';
            if (in_array($action, ['finance_approve', 'finance_reject'], true)) {
                $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability' => AEGIS_System::CAP_RETURNS_FINANCE_REVIEW,
                        'nonce_field' => 'aegis_returns_fin_nonce',
                        'nonce_action' => 'aegis_returns_fin_action',
                        'whitelist' => [
                            'returns_action',
                            'request_id',
                            'finance_comment',
                            '_aegis_idempotency',
                            '_wp_http_referer',
                            'aegis_returns_fin_nonce',
                        ],
                        'idempotency_key' => isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $finance_comment = isset($_POST['finance_comment']) ? sanitize_textarea_field(wp_unslash($_POST['finance_comment'])) : '';
                    $result = ('finance_approve' === $action)
                        ? self::handle_finance_approve($request_id, $finance_comment, $user)
                        : self::handle_finance_reject($request_id, $finance_comment, $user);

                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        $redirect_url = add_query_arg(
                            [
                                'status' => $status_filter,
                                'aegis_returns_message' => (string) $result,
                            ],
                            $base_url
                        );
                        wp_safe_redirect($redirect_url);
                        exit;
                    }
                }
            }
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;

        $requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, d.dealer_name
                 FROM {$req_table} r
                 LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id
                 WHERE r.status = %s
                 ORDER BY r.warehouse_checked_at DESC, r.id DESC
                 LIMIT 50",
                $status_filter
            )
        );

        $counts_map = [];
        $request_ids = array_map('intval', wp_list_pluck($requests, 'id'));
        if (!empty($request_ids)) {
            $in_sql = implode(',', array_fill(0, count($request_ids), '%d'));
            $count_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT request_id, COUNT(*) AS cnt FROM {$item_table} WHERE request_id IN ({$in_sql}) GROUP BY request_id",
                    $request_ids
                )
            );
            foreach ($count_rows as $count_row) {
                $counts_map[(int) $count_row->request_id] = (int) $count_row->cnt;
            }
        }

        $current_request = null;
        $current_items = [];
        if ($request_id > 0) {
            $current_request = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT r.*, d.dealer_name
                     FROM {$req_table} r
                     LEFT JOIN {$dealer_table} d ON d.id = r.dealer_id
                     WHERE r.id = %d
                     LIMIT 1",
                    $request_id
                )
            );

            if ($current_request) {
                $current_items = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$item_table} WHERE request_id = %d ORDER BY id ASC",
                        $request_id
                    )
                );
            } else {
                $errors[] = '单据不存在。';
                $request_id = 0;
            }
        }

        $context = [
            'base_url' => $base_url,
            'messages' => $messages,
            'errors' => $errors,
            'status_filter' => $status_filter,
            'status_options' => [
                self::STATUS_WAREHOUSE_APPROVED => '待财务审核',
                self::STATUS_CLOSED => '已结单',
                self::STATUS_FINANCE_REJECTED => '财务驳回',
            ],
            'is_hq' => $is_hq,
            'view_mode' => ($request_id > 0 ? 'detail' : 'list'),
            'requests' => $requests,
            'counts' => $counts_map,
            'request' => $current_request,
            'items' => $current_items,
            'idempotency' => wp_generate_uuid4(),
        ];

        return AEGIS_Portal::render_portal_template('returns-finance', $context);
    }

    protected static function handle_finance_approve($request_id, $comment, $user) {
        global $wpdb;

        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return new WP_Error('invalid_request', '无效的退货单。');
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
        $now = current_time('mysql');
        $comment = trim((string) $comment);

        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$req_table} WHERE id = %d LIMIT 1", $request_id));
        if (!$req) {
            return new WP_Error('not_found', '单据不存在。');
        }
        if (self::STATUS_WAREHOUSE_APPROVED !== $req->status) {
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $wpdb->query('START TRANSACTION');

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$req_table}
                 SET status = %s,
                     finance_audited_by = %d,
                     finance_audited_at = %s,
                     finance_comment = %s,
                     closed_at = %s,
                     updated_at = %s
                 WHERE id = %d AND status = %s",
                self::STATUS_CLOSED,
                (int) $user->ID,
                $now,
                $comment,
                $now,
                $now,
                $request_id,
                self::STATUS_WAREHOUSE_APPROVED
            )
        );
        if (1 !== (int) $updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $updated_lock = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$lock_table} SET lock_status = %s, updated_at = %s WHERE request_id = %d",
                self::STATUS_CLOSED,
                $now,
                $request_id
            )
        );
        if (false === $updated_lock) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '锁码状态更新失败。');
        }

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_FINANCE_APPROVE',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'entity_id' => $request_id,
                'request_no' => $req->request_no,
                'dealer_id' => (int) $req->dealer_id,
                'actor_user_id' => (int) get_current_user_id(),
            ]
        );

        return '已批准结单。';
    }

    protected static function handle_finance_reject($request_id, $comment, $user) {
        global $wpdb;

        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return new WP_Error('invalid_request', '无效的退货单。');
        }

        $comment = trim((string) $comment);
        if ('' === $comment) {
            return new WP_Error('comment_required', '驳回必须填写原因。');
        }

        $req_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
        $now = current_time('mysql');

        $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$req_table} WHERE id = %d LIMIT 1", $request_id));
        if (!$req) {
            return new WP_Error('not_found', '单据不存在。');
        }
        if (self::STATUS_WAREHOUSE_APPROVED !== $req->status) {
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $wpdb->query('START TRANSACTION');

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$req_table}
                 SET status = %s,
                     finance_audited_by = %d,
                     finance_audited_at = %s,
                     finance_comment = %s,
                     updated_at = %s
                 WHERE id = %d AND status = %s",
                self::STATUS_FINANCE_REJECTED,
                (int) $user->ID,
                $now,
                $comment,
                $now,
                $request_id,
                self::STATUS_WAREHOUSE_APPROVED
            )
        );
        if (1 !== (int) $updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('state_changed', '单据状态已变化，请刷新后重试。');
        }

        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$lock_table} WHERE request_id = %d", $request_id));
        if (false === $deleted) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '释放锁码失败。');
        }

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_FINANCE_REJECT',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'entity_id' => $request_id,
                'request_no' => $req->request_no,
                'dealer_id' => (int) $req->dealer_id,
                'actor_user_id' => (int) get_current_user_id(),
                'comment' => $comment,
            ]
        );

        return '已驳回。';
    }

    protected static function render_dealer_panel($portal_url, $user) {
        global $wpdb;

        $dealer_state = AEGIS_Dealer::evaluate_dealer_access($user);
        $dealer = $dealer_state['dealer'] ?? null;
        $dealer_id = $dealer ? (int) $dealer->id : 0;
        $dealer_blocked = empty($dealer_state['allowed']) || $dealer_id <= 0;

        $base_url = add_query_arg('m', 'returns', $portal_url);
        $show_create = !empty($_GET['create']);
        $request_id = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
        if ($request_id > 0) {
            $view_mode = 'edit';
        } elseif ($show_create) {
            $view_mode = 'create';
        } else {
            $view_mode = 'list';
        }

        $messages = [];
        $errors = [];
        if (isset($_GET['aegis_returns_message'])) {
            $messages[] = sanitize_text_field(wp_unslash($_GET['aegis_returns_message']));
        }
        if (isset($_GET['aegis_returns_error'])) {
            $errors[] = sanitize_text_field(wp_unslash($_GET['aegis_returns_error']));
        }
        $pending_decision = null;

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['returns_action']) ? sanitize_key(wp_unslash($_POST['returns_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $action_whitelist = [
                'returns_action',
                'request_id',
                'code_input',
                'contact_name',
                'contact_phone',
                'reason_code',
                'remark',
                'code_values',
                'code_value',
                'item_id',
                'sample_reason',
                'sample_reason_text',
                'override_plain_code',
                '_aegis_idempotency',
                '_wp_http_referer',
                'aegis_returns_nonce',
                'submit',
            ];
            if ('create_empty_draft' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } elseif ($dealer_blocked) {
                    $errors[] = '当前经销商账号不可创建退货申请。';
                } else {
                    $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
                    $request_no = self::generate_request_no();
                    $now = current_time('mysql');
                    $inserted = $wpdb->insert(
                        $request_table,
                        [
                            'request_no'    => $request_no,
                            'dealer_id'     => $dealer_id,
                            'status'        => self::STATUS_DRAFT,
                            'contact_name'  => '',
                            'contact_phone' => '',
                            'reason_code'   => '',
                            'remark'        => '',
                            'created_at'    => $now,
                            'updated_at'    => $now,
                        ],
                        ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                    );
                    if (!$inserted) {
                        $errors[] = '创建草稿失败，请重试。';
                    } else {
                        $new_request_id = (int) $wpdb->insert_id;
                        wp_safe_redirect(add_query_arg(['request_id' => $new_request_id, 'aegis_returns_message' => '已创建草稿'], $base_url));
                        exit;
                    }
                }
            } elseif ('validate_code' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
                    $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
                    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d", $request_id, $dealer_id));
                    if (!$request || !self::can_edit_request($request)) {
                        $errors[] = '仅草稿可录入防伪码。';
                    } else {
                        $code_input = isset($_POST['code_input']) ? sanitize_text_field(wp_unslash($_POST['code_input'])) : '';
                        $code_value = AEGIS_System::normalize_code_value($code_input);
                        if ('' === $code_value) {
                            $errors[] = '请输入防伪码。';
                        } else {
                            $item_rows = self::build_item_rows_for_codes([$code_value], $dealer_id, $request_id);
                            $row = $item_rows[$code_value] ?? [
                                'code_id' => null,
                                'code_value' => $code_value,
                                'ean' => null,
                                'batch_id' => null,
                                'outbound_scanned_at' => null,
                                'after_sales_deadline_at' => null,
                                'validation_status' => 'fail',
                                'fail_reason_code' => self::FAIL_INVALID_CODE_FORMAT,
                                'fail_reason_msg' => '防伪码格式无效，请检查后重试。',
                            ];
                            if ('pass' === (string) $row['validation_status']) {
                                $item_inserted = $wpdb->insert(
                                    $item_table,
                                    [
                                        'request_id'               => $request_id,
                                        'code_id'                  => $row['code_id'],
                                        'code_value'               => $row['code_value'],
                                        'ean'                      => $row['ean'],
                                        'batch_id'                 => $row['batch_id'],
                                        'outbound_scanned_at'      => $row['outbound_scanned_at'],
                                        'after_sales_deadline_at'  => $row['after_sales_deadline_at'],
                                        'validation_status'        => 'pass',
                                        'override_id'              => null,
                                        'fail_reason_code'         => null,
                                        'fail_reason_msg'          => null,
                                        'created_at'               => current_time('mysql'),
                                        'meta'                     => null,
                                    ],
                                    ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
                                );
                                if ($item_inserted) {
                                    wp_safe_redirect(add_query_arg(['request_id' => $request_id, 'aegis_returns_message' => '已加入清单（通过）'], $base_url));
                                    exit;
                                }
                                $errors[] = '该防伪码已在清单中。';
                            } else {
                                if (self::is_overridable_fail_reason((string) ($row['fail_reason_code'] ?? ''))) {
                                    $pending_decision = [
                                        'code_value' => $code_value,
                                        'fail_reason_code' => (string) ($row['fail_reason_code'] ?? ''),
                                        'fail_reason_msg' => (string) ($row['fail_reason_msg'] ?? ''),
                                    ];
                                    $request_id = (int) $request->id;
                                    $view_mode = 'edit';
                                } else {
                                    $errors[] = ((string) ($row['fail_reason_msg'] ?? '该防伪码不可录入。')) . ' 该码不可录入。';
                                }
                            }
                        }
                    }
                }
            } elseif ('add_need_override' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
                    $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
                    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d", $request_id, $dealer_id));
                    $code_value = AEGIS_System::normalize_code_value(isset($_POST['code_value']) ? sanitize_text_field(wp_unslash($_POST['code_value'])) : '');
                    if (!$request || !self::can_edit_request($request)) {
                        $errors[] = '仅草稿可录入防伪码。';
                    } elseif ('' === $code_value) {
                        $errors[] = '防伪码不能为空。';
                    } else {
                        $item_rows = self::build_item_rows_for_codes([$code_value], $dealer_id, $request_id);
                        $row = $item_rows[$code_value] ?? null;
                        if (!$row) {
                            $errors[] = '防伪码不可录入。';
                        } else {
                            $status = 'pass' === (string) $row['validation_status'] ? 'pass' : 'need_override';
                            if ('need_override' === $status && !self::is_overridable_fail_reason((string) ($row['fail_reason_code'] ?? ''))) {
                                $errors[] = ((string) ($row['fail_reason_msg'] ?? '该码不可录入。')) . ' 该码不可录入。';
                            } else {
                                $item_inserted = $wpdb->insert(
                                    $item_table,
                                    [
                                        'request_id'               => $request_id,
                                        'code_id'                  => $row['code_id'],
                                        'code_value'               => $row['code_value'],
                                        'ean'                      => $row['ean'],
                                        'batch_id'                 => $row['batch_id'],
                                        'outbound_scanned_at'      => $row['outbound_scanned_at'],
                                        'after_sales_deadline_at'  => $row['after_sales_deadline_at'],
                                        'validation_status'        => $status,
                                        'override_id'              => null,
                                        'fail_reason_code'         => 'pass' === $status ? null : $row['fail_reason_code'],
                                        'fail_reason_msg'          => 'pass' === $status ? null : $row['fail_reason_msg'],
                                        'created_at'               => current_time('mysql'),
                                        'meta'                     => null,
                                    ],
                                    ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
                                );
                                if ($item_inserted) {
                                    $msg = 'pass' === $status ? '已加入清单（通过）' : '已加入清单（需特批）';
                                    wp_safe_redirect(add_query_arg(['request_id' => $request_id, 'aegis_returns_message' => $msg], $base_url));
                                    exit;
                                }
                                $errors[] = '该防伪码已在清单中。';
                            }
                        }
                    }
                }
            } elseif ('remove_item' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
                    $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
                    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                    $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
                    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d", $request_id, $dealer_id));
                    if (!$request || !self::can_edit_request($request)) {
                        $errors[] = '仅草稿可移除条目。';
                    } else {
                        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$item_table} WHERE id = %d AND request_id = %d", $item_id, $request_id));
                        if (false === $deleted) {
                            $errors[] = '移除失败，请重试。';
                        } else {
                            wp_safe_redirect(add_query_arg(['request_id' => $request_id, 'aegis_returns_message' => '已移除'], $base_url));
                            exit;
                        }
                    }
                }
            } elseif ('bulk_add_codes' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
                    $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
                    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d", $request_id, $dealer_id));
                    if (!$request || !self::can_edit_request($request)) {
                        $errors[] = '仅草稿可批量录入。';
                    } else {
                        $raw_codes = isset($_POST['code_values']) ? sanitize_textarea_field(wp_unslash($_POST['code_values'])) : '';
                        $codes = self::parse_code_values_from_text($raw_codes);
                        if (is_wp_error($codes)) {
                            $errors[] = $codes->get_error_message();
                        } else {
                            $item_rows = self::build_item_rows_for_codes($codes, $dealer_id, $request_id);
                            $pass_count = 0;
                            $need_override_count = 0;
                            $skipped_count = 0;
                            foreach ($codes as $code_value) {
                                $row = $item_rows[$code_value] ?? null;
                                if (!$row) {
                                    $skipped_count++;
                                    continue;
                                }
                                $status = '';
                                $fail_reason_code = null;
                                $fail_reason_msg = null;
                                if ('pass' === (string) $row['validation_status']) {
                                    $status = 'pass';
                                } elseif (self::is_overridable_fail_reason((string) ($row['fail_reason_code'] ?? ''))) {
                                    $status = 'need_override';
                                    $fail_reason_code = $row['fail_reason_code'];
                                    $fail_reason_msg = $row['fail_reason_msg'];
                                } else {
                                    $skipped_count++;
                                    continue;
                                }
                                $inserted = $wpdb->insert(
                                    $item_table,
                                    [
                                        'request_id'               => $request_id,
                                        'code_id'                  => $row['code_id'],
                                        'code_value'               => $row['code_value'],
                                        'ean'                      => $row['ean'],
                                        'batch_id'                 => $row['batch_id'],
                                        'outbound_scanned_at'      => $row['outbound_scanned_at'],
                                        'after_sales_deadline_at'  => $row['after_sales_deadline_at'],
                                        'validation_status'        => $status,
                                        'override_id'              => null,
                                        'fail_reason_code'         => $fail_reason_code,
                                        'fail_reason_msg'          => $fail_reason_msg,
                                        'created_at'               => current_time('mysql'),
                                        'meta'                     => null,
                                    ],
                                    ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
                                );
                                if (!$inserted) {
                                    $skipped_count++;
                                    continue;
                                }
                                if ('pass' === $status) {
                                    $pass_count++;
                                } else {
                                    $need_override_count++;
                                }
                            }
                            $msg = sprintf('批量加入完成：通过 %d 条，需特批 %d 条，跳过 %d 条。', $pass_count, $need_override_count, $skipped_count);
                            wp_safe_redirect(add_query_arg(['request_id' => $request_id, 'aegis_returns_message' => $msg], $base_url));
                            exit;
                        }
                    }
                }
            } elseif (in_array($action, ['create_draft', 'update_draft', 'delete_draft'], true)) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );

                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
                    $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
                    $contact_name = isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash($_POST['contact_name'])) : '';
                    $contact_phone = isset($_POST['contact_phone']) ? sanitize_text_field(wp_unslash($_POST['contact_phone'])) : '';
                    $reason_code = isset($_POST['reason_code']) ? sanitize_text_field(wp_unslash($_POST['reason_code'])) : '';
                    $remark = isset($_POST['remark']) ? sanitize_textarea_field(wp_unslash($_POST['remark'])) : '';
                    $raw_codes = isset($_POST['code_values']) ? sanitize_textarea_field(wp_unslash($_POST['code_values'])) : '';
                    $codes = self::parse_code_values_from_text($raw_codes);

                    if (is_wp_error($codes)) {
                        $errors[] = $codes->get_error_message();
                    } elseif ('create_draft' === $action) {
                        if ($dealer_blocked) {
                            $errors[] = '当前经销商账号不可创建退货申请。';
                        } else {
                            $request_no = self::generate_request_no();
                            $now = current_time('mysql');
                            $wpdb->query('START TRANSACTION');
                            $inserted = $wpdb->insert(
                                $request_table,
                                [
                                    'request_no'    => $request_no,
                                    'dealer_id'     => $dealer_id,
                                    'status'        => self::STATUS_DRAFT,
                                    'contact_name'  => $contact_name,
                                    'contact_phone' => $contact_phone,
                                    'reason_code'   => $reason_code,
                                    'remark'        => $remark,
                                    'created_at'    => $now,
                                    'updated_at'    => $now,
                                ],
                                ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                            );
                            if (!$inserted) {
                                $wpdb->query('ROLLBACK');
                                $errors[] = '写入退货单失败，请重试。';
                            } else {
                                $new_request_id = (int) $wpdb->insert_id;
                                $item_rows = self::build_item_rows_for_codes($codes, $dealer_id, 0);
                                $item_count = 0;
                                $pass_count = 0;
                                $fail_count = 0;
                                foreach ($codes as $code_value) {
                                    $row = $item_rows[$code_value] ?? [
                                        'code_id'                 => null,
                                        'code_value'              => $code_value,
                                        'ean'                     => null,
                                        'batch_id'                => null,
                                        'outbound_scanned_at'     => null,
                                        'after_sales_deadline_at' => null,
                                        'validation_status'       => 'fail',
                                        'fail_reason_code'        => self::FAIL_INVALID_CODE_FORMAT,
                                        'fail_reason_msg'         => '防伪码格式无效，请检查后重试。',
                                    ];
                                    $item_inserted = $wpdb->insert(
                                        $item_table,
                                        [
                                            'request_id'               => $new_request_id,
                                            'code_id'                  => $row['code_id'],
                                            'code_value'               => $row['code_value'],
                                            'ean'                      => $row['ean'],
                                            'batch_id'                 => $row['batch_id'],
                                            'outbound_scanned_at'      => $row['outbound_scanned_at'],
                                            'after_sales_deadline_at'  => $row['after_sales_deadline_at'],
                                            'validation_status'        => $row['validation_status'],
                                            'fail_reason_code'         => $row['fail_reason_code'],
                                            'fail_reason_msg'          => $row['fail_reason_msg'],
                                            'created_at'               => $now,
                                            'meta'                     => null,
                                        ],
                                        ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                                    );
                                    if (!$item_inserted) {
                                        $wpdb->query('ROLLBACK');
                                        $errors[] = '写入退货条目失败，请重试。';
                                        break;
                                    }
                                    $item_count++;
                                    if (in_array($row['validation_status'], ['pass', 'pass_override'], true)) {
                                        $pass_count++;
                                    } else {
                                        $fail_count++;
                                    }
                                }

                                if (empty($errors)) {
                                    $wpdb->query('COMMIT');
                                    AEGIS_Access_Audit::record_event(
                                        'RETURNS_DRAFT_CREATE',
                                        'SUCCESS',
                                        [
                                            'request_id' => $new_request_id,
                                            'dealer_id'  => $dealer_id,
                                            'item_count' => $item_count,
                                            'pass_count' => $pass_count,
                                            'fail_count' => $fail_count,
                                        ]
                                    );
                                    $redirect_url = add_query_arg(
                                        [
                                            'request_id'            => $new_request_id,
                                            'aegis_returns_message' => sprintf('草稿已保存：通过 %d 条，未通过 %d 条。', $pass_count, $fail_count),
                                        ],
                                        $base_url
                                    );
                                    wp_safe_redirect($redirect_url);
                                    exit;
                                }
                            }
                        }
                    } elseif ('update_draft' === $action) {
                        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                        if ($request_id <= 0) {
                            $errors[] = '单据不存在或无权限。';
                        } else {
                            $request = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d",
                                    $request_id,
                                    $dealer_id
                                )
                            );
                            if (!$request) {
                                $errors[] = '单据不存在或无权限。';
                            } elseif (!self::is_request_editable($request)) {
                                $errors[] = '单据已锁定，无法修改。';
                            } else {
                                $now = current_time('mysql');
                                $wpdb->query('START TRANSACTION');
                                $updated = $wpdb->update(
                                    $request_table,
                                    [
                                        'contact_name'  => $contact_name,
                                        'contact_phone' => $contact_phone,
                                        'reason_code'   => $reason_code,
                                        'remark'        => $remark,
                                        'updated_at'    => $now,
                                    ],
                                    [
                                        'id'        => $request_id,
                                        'dealer_id' => $dealer_id,
                                        'status'    => self::STATUS_DRAFT,
                                    ],
                                    ['%s', '%s', '%s', '%s', '%s'],
                                    ['%d', '%d', '%s']
                                );
                                if (false === $updated) {
                                    $wpdb->query('ROLLBACK');
                                    $errors[] = '更新退货单失败，请重试。';
                                } else {
                                    $override_rows = $wpdb->get_results(
                                        $wpdb->prepare(
                                            "SELECT code_value, override_id FROM {$item_table} WHERE request_id = %d AND override_id IS NOT NULL",
                                            $request_id
                                        )
                                    );
                                    $override_map = [];
                                    foreach ($override_rows as $override_row) {
                                        $override_map[(string) $override_row->code_value] = (int) $override_row->override_id;
                                    }

                                    $wpdb->query($wpdb->prepare("DELETE FROM {$item_table} WHERE request_id = %d", $request_id));
                                    $item_rows = self::build_item_rows_for_codes($codes, $dealer_id, $request_id);
                                    foreach ($item_rows as $item_code => &$item_row) {
                                        if (!isset($override_map[$item_code])) {
                                            continue;
                                        }
                                        $item_row['validation_status'] = 'pass_override';
                                        $item_row['override_id'] = (int) $override_map[$item_code];
                                        $item_row['fail_reason_code'] = null;
                                        $item_row['fail_reason_msg'] = null;
                                    }
                                    unset($item_row);
                                    $item_count = 0;
                                    $pass_count = 0;
                                    $fail_count = 0;
                                    foreach ($codes as $code_value) {
                                        $row = $item_rows[$code_value] ?? [
                                            'code_id'                 => null,
                                            'code_value'              => $code_value,
                                            'ean'                     => null,
                                            'batch_id'                => null,
                                            'outbound_scanned_at'     => null,
                                            'after_sales_deadline_at' => null,
                                            'validation_status'       => 'fail',
                                            'fail_reason_code'        => self::FAIL_INVALID_CODE_FORMAT,
                                            'fail_reason_msg'         => '防伪码格式无效，请检查后重试。',
                                        ];
                                        $item_inserted = $wpdb->insert(
                                            $item_table,
                                            [
                                                'request_id'               => $request_id,
                                                'code_id'                  => $row['code_id'],
                                                'code_value'               => $row['code_value'],
                                                'ean'                      => $row['ean'],
                                                'batch_id'                 => $row['batch_id'],
                                                'outbound_scanned_at'      => $row['outbound_scanned_at'],
                                                'after_sales_deadline_at'  => $row['after_sales_deadline_at'],
                                                'validation_status'        => $row['validation_status'],
                                                'override_id'              => isset($row['override_id']) ? (int) $row['override_id'] : null,
                                                'fail_reason_code'         => $row['fail_reason_code'],
                                                'fail_reason_msg'          => $row['fail_reason_msg'],
                                                'created_at'               => $now,
                                                'meta'                     => null,
                                            ],
                                            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
                                        );
                                        if (!$item_inserted) {
                                            $wpdb->query('ROLLBACK');
                                            $errors[] = '写入退货条目失败，请重试。';
                                            break;
                                        }
                                        $item_count++;
                                        if (in_array($row['validation_status'], ['pass', 'pass_override'], true)) {
                                            $pass_count++;
                                        } else {
                                            $fail_count++;
                                        }
                                    }

                                    if (empty($errors)) {
                                        $wpdb->query('COMMIT');
                                        AEGIS_Access_Audit::record_event(
                                            'RETURNS_DRAFT_UPDATE',
                                            'SUCCESS',
                                            [
                                                'request_id' => $request_id,
                                                'dealer_id'  => $dealer_id,
                                                'item_count' => $item_count,
                                                'pass_count' => $pass_count,
                                                'fail_count' => $fail_count,
                                            ]
                                        );
                                        $redirect_url = add_query_arg(
                                            [
                                                'request_id'            => $request_id,
                                                'aegis_returns_message' => sprintf('草稿已保存：通过 %d 条，未通过 %d 条。', $pass_count, $fail_count),
                                            ],
                                            $base_url
                                        );
                                        wp_safe_redirect($redirect_url);
                                        exit;
                                    }
                                }
                            }
                        }
                    } elseif ('delete_draft' === $action) {
                        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                        if ($request_id <= 0) {
                            $errors[] = '单据不存在或无权限。';
                        } else {
                            $request = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d AND status = %s",
                                    $request_id,
                                    $dealer_id,
                                    self::STATUS_DRAFT
                                )
                            );
                            if (!$request) {
                                $errors[] = '单据不存在或无权限。';
                            } elseif (!self::is_request_editable($request)) {
                                $errors[] = '单据已锁定，无法删除。';
                            } else {
                                $item_count = (int) $wpdb->get_var(
                                    $wpdb->prepare(
                                        "SELECT COUNT(1) FROM {$item_table} WHERE request_id = %d",
                                        $request_id
                                    )
                                );
                                $wpdb->query('START TRANSACTION');
                                $wpdb->query($wpdb->prepare("DELETE FROM {$item_table} WHERE request_id = %d", $request_id));
                                $deleted = $wpdb->delete(
                                    $request_table,
                                    [
                                        'id'        => $request_id,
                                        'dealer_id' => $dealer_id,
                                        'status'    => self::STATUS_DRAFT,
                                    ],
                                    ['%d', '%d', '%s']
                                );
                                if (!$deleted) {
                                    $wpdb->query('ROLLBACK');
                                    $errors[] = '删除退货单失败，请重试。';
                                } else {
                                    $wpdb->query('COMMIT');
                                    AEGIS_Access_Audit::record_event(
                                        'RETURNS_DRAFT_DELETE',
                                        'SUCCESS',
                                        [
                                            'request_id' => $request_id,
                                            'dealer_id'  => $dealer_id,
                                            'item_count' => $item_count,
                                        ]
                                    );
                                    $redirect_url = add_query_arg(
                                        [
                                            'aegis_returns_message' => '草稿已删除',
                                        ],
                                        $base_url
                                    );
                                    wp_safe_redirect($redirect_url);
                                    exit;
                                }
                            }
                        }
                    }
                }
            } elseif ('copy_to_new_draft' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => [
                            'returns_action',
                            'request_id',
                            '_aegis_idempotency',
                            '_wp_http_referer',
                            'aegis_returns_nonce',
                        ],
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $source_request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                    $copy_result = self::handle_dealer_copy_to_new_draft($source_request_id, $dealer_id, $user);
                    if (is_wp_error($copy_result)) {
                        $redirect_url = add_query_arg(
                            [
                                'request_id' => $source_request_id,
                                'aegis_returns_error' => $copy_result->get_error_message(),
                            ],
                            $base_url
                        );
                        wp_safe_redirect($redirect_url);
                        exit;
                    }

                    $redirect_url = add_query_arg(
                        [
                            'request_id' => (int) $copy_result,
                            'aegis_returns_message' => '已复制为新草稿，请检查并提交。',
                        ],
                        $base_url
                    );
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            } elseif ('update_item_reason' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                    $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
                    $sample_reason = isset($_POST['sample_reason']) ? sanitize_key(wp_unslash($_POST['sample_reason'])) : '';
                    $sample_reason_text = isset($_POST['sample_reason_text']) ? sanitize_text_field(wp_unslash($_POST['sample_reason_text'])) : '';

                    $result = self::handle_update_item_reason($request_id, $item_id, $dealer_id, $sample_reason, $sample_reason_text);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } else {
                        wp_safe_redirect(add_query_arg(['request_id' => $request_id, 'aegis_returns_message' => '已保存原因。'], $base_url));
                        exit;
                    }
                }
            } elseif ('apply_override' === $action) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                    $code_value = isset($_POST['code_value']) ? AEGIS_System::normalize_code_value(wp_unslash($_POST['code_value'])) : '';
                    $override_plain_code = isset($_POST['override_plain_code']) ? sanitize_text_field(wp_unslash($_POST['override_plain_code'])) : '';
                    $result = self::handle_dealer_apply_override($request_id, $code_value, $override_plain_code, $dealer_id);
                    if (is_wp_error($result)) {
                        $redirect_url = add_query_arg(
                            [
                                'request_id' => $request_id,
                                'aegis_returns_error' => $result->get_error_message(),
                            ],
                            $base_url
                        );
                        wp_safe_redirect($redirect_url);
                        exit;
                    }

                    $redirect_url = add_query_arg(
                        [
                            'request_id' => $request_id,
                            'aegis_returns_message' => '特批码验证成功，该条已强制通过。',
                        ],
                        $base_url
                    );
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            } elseif (in_array($action, ['submit_request', 'withdraw_request'], true)) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_SUBMIT,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => $action_whitelist,
                        'idempotency_key' => $idempotency,
                    ]
                );
                if (!$validation['success']) {
                    $errors[] = $validation['message'];
                } else {
                    $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
                    $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
                    $version_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_VERSION_TABLE;
                    $lock_table = $wpdb->prefix . AEGIS_System::RETURN_CODE_LOCK_TABLE;
                    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
                    if ($request_id <= 0) {
                        $errors[] = '单据不存在或无权限。';
                    } else {
                        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d", $request_id, $dealer_id));
                        if (!$request) {
                            $errors[] = '单据不存在或无权限。';
                        } elseif ('submit_request' === $action) {
                            if (!self::can_edit_request($request)) {
                                $errors[] = '单据已锁定或已提交，无法再次提交。';
                            } else {
                                $stats = self::revalidate_items_for_request($request_id, $dealer_id);
                                if (is_wp_error($stats)) {
                                    $errors[] = $stats->get_error_message();
                                } elseif ($stats['need_override'] > 0) {
                                    $redirect_url = add_query_arg(
                                        [
                                            'request_id' => $request_id,
                                            'aegis_returns_error' => '存在需特批条目未验证，无法提交。请在清单行内输入特批码验证后再提交。',
                                        ],
                                        $base_url
                                    );
                                    wp_safe_redirect($redirect_url);
                                    exit;
                                } elseif ($stats['total'] <= 0 || $stats['fail'] > 0 || $stats['pending'] > 0) {
                                    $redirect_url = add_query_arg(
                                        [
                                            'request_id' => $request_id,
                                            'aegis_returns_error' => sprintf('存在未通过条目（通过%d/未通过%d/需特批%d/待校验%d），无法提交。请删除不通过条目或联系销售/HQ处理。', $stats['pass'], $stats['fail'], $stats['need_override'], $stats['pending']),
                                        ],
                                        $base_url
                                    );
                                    wp_safe_redirect($redirect_url);
                                    exit;
                                } else {
                                    $items_for_submit = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$item_table} WHERE request_id = %d ORDER BY id ASC", $request_id));
                                    foreach ($items_for_submit as $submit_item) {
                                        if (empty($submit_item->code_id)) {
                                            $errors[] = '存在无法识别的防伪码，无法提交。';
                                            break;
                                        }
                                    }
                                    if (empty($errors)) {
                                        $now = current_time('mysql');
                                        $snapshot = [
                                            'request' => [
                                                'request_no' => $request->request_no,
                                                'dealer_id' => (int) $request->dealer_id,
                                                'contact_name' => $request->contact_name,
                                                'contact_phone' => $request->contact_phone,
                                                'reason_code' => $request->reason_code,
                                                'remark' => $request->remark,
                                            ],
                                            'items' => array_map(
                                                static function ($item_row) {
                                                    return [
                                                        'code_value' => $item_row->code_value,
                                                        'code_id' => (int) $item_row->code_id,
                                                        'ean' => $item_row->ean,
                                                        'batch_id' => $item_row->batch_id,
                                                        'outbound_scanned_at' => $item_row->outbound_scanned_at,
                                                        'after_sales_deadline_at' => $item_row->after_sales_deadline_at,
                                                        'validation_status' => $item_row->validation_status,
                                                    ];
                                                },
                                                $items_for_submit
                                            ),
                                            'generated_at' => $now,
                                            'after_sales_days' => absint(get_option(AEGIS_System::RETURNS_AFTER_SALES_DAYS_OPTION, 30)),
                                        ];
                                        $version_no = self::get_next_version_no($request_id);

                                        $wpdb->query('START TRANSACTION');
                                        $version_inserted = $wpdb->insert(
                                            $version_table,
                                            [
                                                'request_id' => $request_id,
                                                'version_no' => $version_no,
                                                'snapshot_json' => wp_json_encode($snapshot),
                                                'created_by' => get_current_user_id(),
                                                'created_at' => $now,
                                            ],
                                            ['%d', '%d', '%s', '%d', '%s']
                                        );
                                        if (!$version_inserted) {
                                            $wpdb->query('ROLLBACK');
                                            $errors[] = '写入版本快照失败。';
                                        } else {
                                            $lock_error = '';
                                            foreach ($items_for_submit as $submit_item) {
                                                $lock_inserted = $wpdb->insert(
                                                    $lock_table,
                                                    [
                                                        'code_id' => (int) $submit_item->code_id,
                                                        'code_value' => $submit_item->code_value,
                                                        'request_id' => $request_id,
                                                        'lock_status' => 'submitted',
                                                        'created_at' => $now,
                                                        'updated_at' => $now,
                                                    ],
                                                    ['%d', '%s', '%d', '%s', '%s', '%s']
                                                );
                                                if (!$lock_inserted) {
                                                    $conflict = $wpdb->get_row(
                                                        $wpdb->prepare(
                                                            "SELECT l.request_id, r.request_no, r.status FROM {$lock_table} l JOIN {$request_table} r ON r.id = l.request_id WHERE l.code_id = %d LIMIT 1",
                                                            (int) $submit_item->code_id
                                                        )
                                                    );
                                                    $lock_error = $conflict
                                                        ? sprintf('防伪码 %s 已在退货单 %s（状态 %s）处理中，无法提交。', $submit_item->code_value, $conflict->request_no, $conflict->status)
                                                        : '提交失败，请重试。';
                                                    break;
                                                }
                                            }
                                            if ($lock_error) {
                                                $wpdb->query('ROLLBACK');
                                                wp_safe_redirect(add_query_arg(['request_id' => $request_id, 'aegis_returns_error' => $lock_error], $base_url));
                                                exit;
                                            }

                                            $updated = $wpdb->query(
                                                $wpdb->prepare(
                                                    "UPDATE {$request_table} SET status = %s, submitted_at = %s, content_locked_at = %s, updated_at = %s WHERE id = %d AND dealer_id = %d AND status = %s AND (hard_locked_at IS NULL OR hard_locked_at = '')",
                                                    self::STATUS_SUBMITTED,
                                                    $now,
                                                    $now,
                                                    $now,
                                                    $request_id,
                                                    $dealer_id,
                                                    self::STATUS_DRAFT
                                                )
                                            );
                                            if (!$updated) {
                                                $wpdb->query('ROLLBACK');
                                                $errors[] = '提交失败，请重试。';
                                            } else {
                                                $wpdb->query('COMMIT');
                                                AEGIS_Access_Audit::record_event('RETURNS_SUBMIT', 'SUCCESS', [
                                                    'entity_type' => 'return_request',
                                                    'entity_id' => $request_id,
                                                    'dealer_id' => $dealer_id,
                                                    'request_no' => $request->request_no,
                                                    'total' => $stats['total'],
                                                    'pass' => $stats['pass'],
                                                ]);
                                                wp_safe_redirect(add_query_arg(['aegis_returns_message' => '已提交，等待销售审核。'], $base_url));
                                                exit;
                                            }
                                        }
                                    }
                                }
                            }
                        } elseif ('withdraw_request' === $action) {
                            if (!self::can_withdraw_request($request)) {
                                $errors[] = '单据已进入审核流程，无法撤回。';
                            } else {
                                $now = current_time('mysql');
                                $wpdb->query('START TRANSACTION');
                                $wpdb->query($wpdb->prepare("DELETE FROM {$lock_table} WHERE request_id = %d", $request_id));
                                $updated = $wpdb->query(
                                    $wpdb->prepare(
                                        "UPDATE {$request_table} SET status = %s, submitted_at = NULL, content_locked_at = NULL, updated_at = %s WHERE id = %d AND dealer_id = %d AND status = %s AND (hard_locked_at IS NULL OR hard_locked_at = '')",
                                        self::STATUS_DRAFT,
                                        $now,
                                        $request_id,
                                        $dealer_id,
                                        self::STATUS_SUBMITTED
                                    )
                                );
                                if (!$updated) {
                                    $wpdb->query('ROLLBACK');
                                    $errors[] = '撤回失败，请重试。';
                                } else {
                                    $wpdb->query('COMMIT');
                                    AEGIS_Access_Audit::record_event('RETURNS_WITHDRAW', 'SUCCESS', [
                                        'entity_type' => 'return_request',
                                        'entity_id' => $request_id,
                                        'dealer_id' => $dealer_id,
                                        'request_no' => $request->request_no,
                                    ]);
                                    wp_safe_redirect(add_query_arg(['request_id' => $request_id, 'aegis_returns_message' => '已撤回为草稿，可继续修改。'], $base_url));
                                    exit;
                                }
                            }
                        }
                    }
                }
            }
        }

        $requests = [];
        $counts_map = [];
        $current_request = null;
        $current_items = [];
        $textarea_string = '';

        if ('list' === $view_mode && $dealer_id > 0) {
            $requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}" . AEGIS_System::RETURN_REQUEST_TABLE . " WHERE dealer_id = %d ORDER BY updated_at DESC LIMIT %d",
                    $dealer_id,
                    self::PER_PAGE_DEFAULT
                )
            );
            if ($requests) {
                $request_ids = array_map('intval', wp_list_pluck($requests, 'id'));
                if (!empty($request_ids)) {
                    $placeholders = implode(',', array_fill(0, count($request_ids), '%d'));
                    $count_rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT request_id, COUNT(1) AS item_count FROM {$wpdb->prefix}" . AEGIS_System::RETURN_REQUEST_ITEM_TABLE . " WHERE request_id IN ({$placeholders}) GROUP BY request_id",
                            $request_ids
                        )
                    );
                    foreach ($count_rows as $row) {
                        $counts_map[(int) $row->request_id] = (int) $row->item_count;
                    }
                }
            }
        }

        if ('edit' === $view_mode && $dealer_id > 0 && $request_id > 0) {
            $current_request = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}" . AEGIS_System::RETURN_REQUEST_TABLE . " WHERE id = %d AND dealer_id = %d",
                    $request_id,
                    $dealer_id
                )
            );
            if ($current_request) {
                $current_items = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}" . AEGIS_System::RETURN_REQUEST_ITEM_TABLE . " WHERE request_id = %d ORDER BY id ASC",
                        $request_id
                    )
                );
                if ($current_items) {
                    $textarea_string = implode("\n", wp_list_pluck($current_items, 'code_value'));
                }
            }
        }

        $can_edit_current = self::can_edit_request($current_request);
        $can_withdraw_current = self::can_withdraw_request($current_request);

        $context = [
            'base_url'       => $base_url,
            'portal_url'     => $portal_url,
            'messages'       => $messages,
            'errors'         => $errors,
            'dealer'         => $dealer,
            'dealer_blocked' => $dealer_blocked,
            'view_mode'      => $view_mode,
            'requests'       => $requests,
            'counts'         => $counts_map,
            'request'        => $current_request,
            'items'          => $current_items,
            'code_text'      => $textarea_string,
            'can_edit'       => $can_edit_current,
            'can_withdraw'   => $can_withdraw_current,
            'idempotency'    => wp_generate_uuid4(),
            'status_labels'  => self::get_status_labels(),
            'pending_decision' => $pending_decision,
        ];

        return AEGIS_Portal::render_portal_template('returns', $context);
    }

    protected static function parse_code_values_from_text($raw): array {
        $raw = (string) $raw;
        if ('' === $raw) {
            return [];
        }
        $parts = preg_split('/[\s,，;；]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $unique = [];
        foreach ($parts as $part) {
            $normalized = AEGIS_System::normalize_code_value($part);
            if ('' === $normalized) {
                continue;
            }
            $unique[$normalized] = true;
            if (count($unique) > 500) {
                return new WP_Error('returns_codes_limit', '一次最多录入 500 条防伪码。');
            }
        }
        return array_keys($unique);
    }

    protected static function is_overridable_fail_reason($code): bool {
        return in_array(
            (string) $code,
            [
                self::FAIL_AFTER_SALES_EXPIRED,
                self::FAIL_NOT_OWNED_BY_DEALER,
                self::FAIL_OUTBOUND_TIME_MISSING,
            ],
            true
        );
    }

    protected static function build_item_rows_for_codes(array $code_values, int $dealer_id, int $exclude_request_id = 0): array {
        global $wpdb;

        $after_sales_days = absint(get_option(AEGIS_System::RETURNS_AFTER_SALES_DAYS_OPTION, 30));
        if ($after_sales_days < 1) {
            $after_sales_days = 30;
        }
        if ($after_sales_days > 3650) {
            $after_sales_days = 3650;
        }

        $normalized_codes = [];
        foreach ($code_values as $code_value) {
            $normalized = AEGIS_System::normalize_code_value($code_value);
            if ('' === $normalized) {
                continue;
            }
            $normalized_codes[$normalized] = true;
        }
        $codes = array_keys($normalized_codes);
        if (empty($codes)) {
            return [];
        }

        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $return_request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $return_request_item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;

        $code_placeholders = implode(',', array_fill(0, count($codes), '%s'));
        $code_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, code, ean, batch_id FROM {$code_table} WHERE code IN ({$code_placeholders})",
                $codes
            )
        );
        $code_map = [];
        $code_ids = [];
        foreach ($code_rows as $row) {
            $code_key = (string) $row->code;
            $code_map[$code_key] = $row;
            $code_ids[] = (int) $row->id;
        }

        $ship_map = [];
        if (!empty($code_ids)) {
            $code_id_placeholders = implode(',', array_fill(0, count($code_ids), '%d'));
            $ship_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT i.code_id, i.scanned_at, s.dealer_id AS ship_dealer_id
                    FROM {$shipment_item_table} i
                    JOIN {$shipment_table} s ON s.id = i.shipment_id
                    WHERE i.code_id IN ({$code_id_placeholders})",
                    $code_ids
                )
            );
            foreach ($ship_rows as $row) {
                $ship_map[(int) $row->code_id] = $row;
            }
        }

        $dup_map = [];
        $dup_args = $codes;
        $dup_args[] = $exclude_request_id;
        $dup_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.code_value, r.id AS request_id, r.request_no, r.status
                FROM {$return_request_item_table} i
                JOIN {$return_request_table} r ON r.id = i.request_id
                WHERE i.code_value IN ({$code_placeholders})
                  AND r.id <> %d
                  AND r.status NOT IN ('sales_rejected','warehouse_rejected','finance_rejected','closed')",
                $dup_args
            )
        );
        foreach ($dup_rows as $row) {
            $dup_code_value = (string) $row->code_value;
            if (!isset($dup_map[$dup_code_value])) {
                $dup_map[$dup_code_value] = $row;
            }
        }

        $tz = wp_timezone();
        $now_dt = new DateTime('now', $tz);
        $results = [];
        foreach ($codes as $code_value) {
            $code_row = $code_map[$code_value] ?? null;
            $code_id = $code_row ? (int) $code_row->id : null;
            $ean = $code_row ? $code_row->ean : null;
            $batch_id = $code_row ? (int) $code_row->batch_id : null;
            $shipment_row = $code_id ? ($ship_map[$code_id] ?? null) : null;
            $outbound_scanned_at = $shipment_row ? $shipment_row->scanned_at : null;

            $result = [
                'code_id'                 => $code_id,
                'code_value'              => $code_value,
                'ean'                     => $ean,
                'batch_id'                => $batch_id,
                'outbound_scanned_at'     => $outbound_scanned_at,
                'after_sales_deadline_at' => null,
                'validation_status'       => 'fail',
                'fail_reason_code'        => null,
                'fail_reason_msg'         => null,
            ];

            if (isset($dup_map[$code_value])) {
                $dup = $dup_map[$code_value];
                $result['fail_reason_code'] = self::FAIL_CODE_ALREADY_IN_RETURN_PROCESS;
                $result['fail_reason_msg'] = sprintf('该防伪码已存在退货单 %s（%s），请勿重复申请。', $dup->request_no, $dup->status);
                $results[$code_value] = $result;
                continue;
            }

            if (!$code_row) {
                $result['fail_reason_code'] = self::FAIL_CODE_NOT_FOUND;
                $result['fail_reason_msg'] = '防伪码不存在，请联系销售/HQ处理。';
                $results[$code_value] = $result;
                continue;
            }

            if (!$shipment_row || empty($shipment_row->scanned_at)) {
                $result['fail_reason_code'] = self::FAIL_OUTBOUND_TIME_MISSING;
                $result['fail_reason_msg'] = '未查询到出库扫码时间，无法判定售后期，请联系销售/HQ处理。';
                $results[$code_value] = $result;
                continue;
            }

            if ((int) $shipment_row->ship_dealer_id !== $dealer_id) {
                $result['fail_reason_code'] = self::FAIL_NOT_OWNED_BY_DEALER;
                $result['fail_reason_msg'] = '该防伪码不属于当前经销商名下，请联系销售/HQ处理。';
                $results[$code_value] = $result;
                continue;
            }

            $scan_dt = new DateTime($shipment_row->scanned_at, $tz);
            $deadline_dt = (clone $scan_dt)->add(new DateInterval('P' . $after_sales_days . 'D'));
            $deadline = $deadline_dt->format('Y-m-d H:i:s');
            $result['after_sales_deadline_at'] = $deadline;

            if ($now_dt > $deadline_dt) {
                $result['fail_reason_code'] = self::FAIL_AFTER_SALES_EXPIRED;
                $result['fail_reason_msg'] = sprintf(
                    '已超售后期（出库：%s，截止：%s），请联系销售/HQ处理。',
                    $shipment_row->scanned_at,
                    $deadline
                );
                $results[$code_value] = $result;
                continue;
            }

            $result['validation_status'] = 'pass';
            $results[$code_value] = $result;
        }

        return $results;
    }

    protected static function generate_request_no() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        for ($i = 0; $i < 5; $i++) {
            $request_no = 'RET-' . gmdate('Ymd-His', current_time('timestamp')) . '-' . wp_rand(100, 999);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE request_no = %s", $request_no));
            if (!$exists) {
                return $request_no;
            }
        }
        return uniqid('RET-', false);
    }

    protected static function generate_override_plain_code(): string {
        return wp_generate_password(12, false, false);
    }

    protected static function hash_override_plain_code($plain): string {
        return hash_hmac('sha256', (string) $plain, wp_salt('nonce'));
    }

    public static function get_sample_reason_options(): array {
        return [
            '' => '（不填写）',
            'expiry' => '临期/超期',
            'damaged' => '破损',
            'wrong_delivery' => '错发/漏发',
            'slow_moving' => '滞销',
            'quality' => '质量问题',
            'other' => '其他',
        ];
    }

    protected static function handle_override_issue($code_value, $dealer_id, $reason_text, $expires_hours, $user) {
        global $wpdb;

        $code_value = AEGIS_System::normalize_code_value($code_value);
        if ('' === $code_value) {
            return new WP_Error('invalid_code', '防伪码不能为空。');
        }
        $dealer_id = (int) $dealer_id;
        if ($dealer_id <= 0) {
            return new WP_Error('invalid_dealer', '经销商ID无效。');
        }
        $reason_text = trim((string) $reason_text);
        if ('' === $reason_text) {
            return new WP_Error('invalid_reason', '通过原因不能为空。');
        }

        $expires_hours = absint($expires_hours);
        if ($expires_hours <= 0) {
            $expires_hours = 48;
        }
        if ($expires_hours < 1 || $expires_hours > 168) {
            return new WP_Error('invalid_hours', '有效期需在 1~168 小时之间。');
        }

        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $override_table = $wpdb->prefix . AEGIS_System::RETURN_OVERRIDE_CODE_TABLE;

        $code_row = $wpdb->get_row($wpdb->prepare("SELECT id, code, ean, batch_id FROM {$code_table} WHERE code = %s LIMIT 1", $code_value));
        if (!$code_row) {
            return new WP_Error('code_not_found', '防伪码不存在，不能发放特批码。');
        }
        $dealer_row = $wpdb->get_row($wpdb->prepare("SELECT id, dealer_name FROM {$dealer_table} WHERE id = %d LIMIT 1", $dealer_id));
        if (!$dealer_row) {
            return new WP_Error('dealer_not_found', '经销商不存在。');
        }

        $tz = wp_timezone();
        $now_dt = new DateTime('now', $tz);
        $expires_dt = (clone $now_dt)->add(new DateInterval('PT' . $expires_hours . 'H'));
        $expires_at = $expires_dt->format('Y-m-d H:i:s');
        $now = current_time('mysql');
        $issued_role = AEGIS_System_Roles::is_hq_admin($user) ? 'hq' : 'sales';

        $inserted = false;
        $plain_code = '';
        for ($i = 0; $i < 3; $i++) {
            $plain_code = self::generate_override_plain_code();
            $code_hash = self::hash_override_plain_code($plain_code);
            $inserted = $wpdb->insert(
                $override_table,
                [
                    'code_hash' => $code_hash,
                    'code_hint' => substr($plain_code, -4),
                    'code_id' => (int) $code_row->id,
                    'code_value' => (string) $code_row->code,
                    'dealer_id' => $dealer_id,
                    'status' => 'active',
                    'expires_at' => $expires_at,
                    'reason_text' => $reason_text,
                    'issued_by' => (int) $user->ID,
                    'issued_role' => $issued_role,
                    'issued_at' => $now,
                    'meta' => null,
                ],
                ['%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
            );
            if ($inserted) {
                break;
            }
        }
        if (!$inserted) {
            return new WP_Error('issue_failed', '特批码生成失败，请稍后重试。');
        }

        AEGIS_Access_Audit::record_event('RETURNS_OVERRIDE_ISSUE', 'SUCCESS', [
            'entity_type' => 'return_override',
            'code_value' => $code_value,
            'code_id' => (int) $code_row->id,
            'dealer_id' => $dealer_id,
            'expires_at' => $expires_at,
        ]);

        $token = wp_generate_uuid4();
        set_transient('aegis_returns_override_plain_' . $token, $plain_code, 5 * MINUTE_IN_SECONDS);
        return ['issued_token' => $token];
    }

    protected static function handle_override_revoke($override_id, $user) {
        global $wpdb;

        $override_id = (int) $override_id;
        if ($override_id <= 0) {
            return new WP_Error('invalid_override', '特批记录不存在。');
        }

        $override_table = $wpdb->prefix . AEGIS_System::RETURN_OVERRIDE_CODE_TABLE;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$override_table} WHERE id = %d LIMIT 1", $override_id));
        if (!$row) {
            return new WP_Error('not_found', '特批记录不存在。');
        }
        if ('active' !== (string) $row->status) {
            return new WP_Error('not_active', '仅 ACTIVE 状态特批码可撤销。');
        }

        $now = current_time('mysql');
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$override_table} SET status = %s, revoked_at = %s, revoked_by = %d WHERE id = %d AND status = %s",
                'revoked',
                $now,
                (int) $user->ID,
                $override_id,
                'active'
            )
        );
        if (1 !== (int) $updated) {
            return new WP_Error('revoke_failed', '撤销失败，记录状态可能已变化。');
        }

        AEGIS_Access_Audit::record_event('RETURNS_OVERRIDE_REVOKE', 'SUCCESS', [
            'entity_type' => 'return_override',
            'entity_id' => $override_id,
            'code_value' => (string) $row->code_value,
            'dealer_id' => (int) $row->dealer_id,
        ]);

        return '特批码已撤销。';
    }

    protected static function handle_dealer_apply_override($request_id, $code_value, $override_plain_code, $dealer_id) {
        global $wpdb;

        $request_id = (int) $request_id;
        $dealer_id = (int) $dealer_id;
        $code_value = AEGIS_System::normalize_code_value($code_value);
        $override_plain_code = trim((string) $override_plain_code);

        if ($request_id <= 0 || '' === $code_value) {
            return new WP_Error('invalid_params', '参数无效。');
        }
        if ('' === $override_plain_code) {
            return new WP_Error('empty_override', '请输入特批码。');
        }

        $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
        $override_table = $wpdb->prefix . AEGIS_System::RETURN_OVERRIDE_CODE_TABLE;

        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d LIMIT 1", $request_id, $dealer_id));
        if (!$request || !self::can_edit_request($request)) {
            return new WP_Error('not_editable', '仅 DRAFT 草稿允许使用特批码。');
        }

        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$item_table} WHERE request_id = %d AND code_value = %s LIMIT 1",
                $request_id,
                $code_value
            )
        );
        if (!$item) {
            return new WP_Error('item_not_found', '条目不存在。');
        }
        if (self::FAIL_CODE_ALREADY_IN_RETURN_PROCESS === (string) $item->fail_reason_code) {
            return new WP_Error('lock_conflict', '该防伪码已在其他退货流程中，不能使用特批码放行。');
        }
        if (empty($item->code_id)) {
            return new WP_Error('code_missing', '防伪码无法识别，不能使用特批码。');
        }

        $hash = self::hash_override_plain_code($override_plain_code);
        $override = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$override_table} WHERE code_hash = %s AND status = %s LIMIT 1", $hash, 'active'));
        if (!$override) {
            return new WP_Error('invalid_override', '特批码无效、已过期或不匹配该防伪码。');
        }

        $tz = wp_timezone();
        $now_dt = new DateTime('now', $tz);
        $expires_dt = new DateTime((string) $override->expires_at, $tz);
        if ((int) $override->dealer_id !== $dealer_id || (int) $override->code_id !== (int) $item->code_id || $expires_dt <= $now_dt) {
            return new WP_Error('override_mismatch', '特批码无效、已过期或不匹配该防伪码。');
        }

        $now = current_time('mysql');
        $wpdb->query('START TRANSACTION');
        $used = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$override_table}
                 SET status = %s, used_at = %s, used_by_dealer_id = %d, used_in_request_id = %d
                 WHERE id = %d AND status = %s",
                'used',
                $now,
                $dealer_id,
                $request_id,
                (int) $override->id,
                'active'
            )
        );
        if (1 !== (int) $used) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('override_used', '特批码已被使用或已失效。');
        }

        $item_updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$item_table}
                 SET validation_status = %s,
                     override_id = %d,
                     fail_reason_code = NULL,
                     fail_reason_msg = NULL
                 WHERE request_id = %d AND code_value = %s",
                'pass_override',
                (int) $override->id,
                $request_id,
                $code_value
            )
        );
        if (1 !== (int) $item_updated) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('item_update_failed', '条目更新失败，请重试。');
        }

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event('RETURNS_OVERRIDE_USE', 'SUCCESS', [
            'entity_type' => 'return_request_item',
            'request_id' => $request_id,
            'dealer_id' => $dealer_id,
            'code_value' => $code_value,
            'override_id' => (int) $override->id,
        ]);

        return true;
    }

    protected static function handle_update_item_reason(int $request_id, int $item_id, int $dealer_id, string $sample_reason, string $sample_reason_text) {
        global $wpdb;

        if ($request_id <= 0 || $item_id <= 0 || $dealer_id <= 0) {
            return new WP_Error('invalid_param', '参数无效。');
        }

        $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;

        $request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d",
                $request_id,
                $dealer_id
            )
        );
        if (!$request) {
            return new WP_Error('not_found', '单据不存在或无权限。');
        }
        if (!self::can_edit_request($request)) {
            return new WP_Error('not_editable', '当前单据不可编辑。');
        }

        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, meta FROM {$item_table} WHERE id = %d AND request_id = %d",
                $item_id,
                $request_id
            )
        );
        if (!$item) {
            return new WP_Error('item_not_found', '条目不存在或无权限。');
        }

        $reason_options = self::get_sample_reason_options();
        if (!array_key_exists($sample_reason, $reason_options)) {
            return new WP_Error('invalid_reason', '采样原因无效。');
        }

        $sample_reason_text = sanitize_text_field($sample_reason_text);
        if ('other' === $sample_reason) {
            $sample_reason_text = mb_substr($sample_reason_text, 0, 200);
        } else {
            $sample_reason_text = '';
        }

        $meta = json_decode((string) $item->meta, true);
        if (!is_array($meta)) {
            $meta = [];
        }

        if ('' === $sample_reason) {
            unset($meta['sample_reason'], $meta['sample_reason_text']);
        } else {
            $meta['sample_reason'] = $sample_reason;
            if ('other' === $sample_reason && '' !== $sample_reason_text) {
                $meta['sample_reason_text'] = $sample_reason_text;
            } else {
                unset($meta['sample_reason_text']);
            }
        }

        $updated = $wpdb->update(
            $item_table,
            [
                'meta' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
            ],
            [
                'id' => $item_id,
                'request_id' => $request_id,
            ],
            ['%s'],
            ['%d', '%d']
        );
        if (false === $updated) {
            return new WP_Error('db_error', '保存采样原因失败，请重试。');
        }

        AEGIS_Access_Audit::record_event('RETURNS_ITEM_REASON_UPDATE', 'SUCCESS', [
            'entity_type' => 'return_request_item',
            'request_id' => $request_id,
            'item_id' => $item_id,
            'dealer_id' => $dealer_id,
            'sample_reason' => $sample_reason,
        ]);

        return true;
    }

    protected static function is_request_editable($request) {
        return self::can_edit_request($request);
    }

    protected static function is_copyable_status($status): bool {
        return in_array(
            $status,
            [
                self::STATUS_SALES_REJECTED,
                self::STATUS_WAREHOUSE_REJECTED,
                self::STATUS_FINANCE_REJECTED,
            ],
            true
        );
    }

    protected static function handle_dealer_copy_to_new_draft(int $source_request_id, int $dealer_id, WP_User $user): int|WP_Error {
        global $wpdb;

        $request_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_TABLE;
        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;

        if ($source_request_id <= 0 || $dealer_id <= 0) {
            return new WP_Error('not_found', '单据不存在或无权限。');
        }

        $req = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$request_table} WHERE id = %d AND dealer_id = %d LIMIT 1",
                $source_request_id,
                $dealer_id
            )
        );
        if (!$req) {
            return new WP_Error('not_found', '单据不存在或无权限。');
        }

        if (!self::is_copyable_status($req->status)) {
            return new WP_Error('not_allowed', '该状态不允许复制为新草稿。');
        }

        $source_codes = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT code_value FROM {$item_table} WHERE request_id = %d ORDER BY id ASC",
                $source_request_id
            )
        );
        $codes = [];
        foreach ($source_codes as $source_code_value) {
            $normalized = AEGIS_System::normalize_code_value((string) $source_code_value);
            if ('' !== $normalized) {
                $codes[$normalized] = $normalized;
            }
        }
        $codes = array_values($codes);
        if (empty($codes)) {
            return new WP_Error('empty', '原单无防伪码条目，无法复制。');
        }

        $item_rows = self::build_item_rows_for_codes($codes, $dealer_id, 0);
        $request_no = self::generate_request_no();
        $now = current_time('mysql');
        $origin_meta = wp_json_encode(
            [
                'origin_request_id' => $source_request_id,
                'origin_request_no' => $req->request_no,
            ]
        );

        $wpdb->query('START TRANSACTION');
        $inserted = $wpdb->insert(
            $request_table,
            [
                'request_no'    => $request_no,
                'dealer_id'     => $dealer_id,
                'status'        => self::STATUS_DRAFT,
                'contact_name'  => $req->contact_name,
                'contact_phone' => $req->contact_phone,
                'reason_code'   => $req->reason_code,
                'remark'        => $req->remark,
                'created_at'    => $now,
                'updated_at'    => $now,
                'meta'          => $origin_meta,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        if (!$inserted) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', '写入退货单失败，请重试。');
        }

        $new_request_id = (int) $wpdb->insert_id;
        foreach ($codes as $code_value) {
            $row = $item_rows[$code_value] ?? [
                'code_id'                 => null,
                'code_value'              => $code_value,
                'ean'                     => null,
                'batch_id'                => null,
                'outbound_scanned_at'     => null,
                'after_sales_deadline_at' => null,
                'validation_status'       => 'fail',
                'fail_reason_code'        => self::FAIL_INVALID_CODE_FORMAT,
                'fail_reason_msg'         => '防伪码格式无效，请检查后重试。',
            ];

            $item_inserted = $wpdb->insert(
                $item_table,
                [
                    'request_id'               => $new_request_id,
                    'code_id'                  => $row['code_id'],
                    'code_value'               => $row['code_value'],
                    'ean'                      => $row['ean'],
                    'batch_id'                 => $row['batch_id'],
                    'outbound_scanned_at'      => $row['outbound_scanned_at'],
                    'after_sales_deadline_at'  => $row['after_sales_deadline_at'],
                    'validation_status'        => $row['validation_status'],
                    'override_id'              => null,
                    'fail_reason_code'         => $row['fail_reason_code'],
                    'fail_reason_msg'          => $row['fail_reason_msg'],
                    'created_at'               => $now,
                    'meta'                     => null,
                ],
                ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );

            if (!$item_inserted) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', '写入退货条目失败，请重试。');
            }
        }

        $wpdb->query('COMMIT');

        AEGIS_Access_Audit::record_event(
            'RETURNS_COPY_NEW_DRAFT',
            'SUCCESS',
            [
                'entity_type' => 'return_request',
                'source_request_id' => $source_request_id,
                'new_request_id' => $new_request_id,
                'dealer_id' => $dealer_id,
                'source_request_no' => $req->request_no,
                'actor_user_id' => (int) $user->ID,
            ]
        );

        return $new_request_id;
    }

    protected static function can_edit_request($request): bool {
        if (!$request) {
            return false;
        }

        return self::STATUS_DRAFT === $request->status
            && empty($request->hard_locked_at)
            && empty($request->content_locked_at);
    }

    protected static function can_withdraw_request($request): bool {
        if (!$request) {
            return false;
        }

        $sales_audited_at = property_exists($request, 'sales_audited_at') ? $request->sales_audited_at : null;

        return self::STATUS_SUBMITTED === $request->status
            && empty($request->hard_locked_at)
            && empty($sales_audited_at);
    }

    protected static function get_next_version_no($request_id): int {
        global $wpdb;

        $version_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_VERSION_TABLE;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(version_no), 0) + 1 FROM {$version_table} WHERE request_id = %d",
                $request_id
            )
        );
    }

    protected static function revalidate_items_for_request(int $request_id, int $dealer_id) {
        global $wpdb;

        $item_table = $wpdb->prefix . AEGIS_System::RETURN_REQUEST_ITEM_TABLE;
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, code_value, override_id FROM {$item_table} WHERE request_id = %d ORDER BY id ASC",
                $request_id
            )
        );
        if (empty($items)) {
            return new WP_Error('empty_items', '请先录入防伪码。');
        }

        $code_values = [];
        $override_map = [];
        foreach ($items as $item) {
            $code_value = (string) $item->code_value;
            $code_values[] = $code_value;
            if (!empty($item->override_id)) {
                $override_map[$code_value] = (int) $item->override_id;
            }
        }
        $item_rows = self::build_item_rows_for_codes($code_values, $dealer_id, $request_id);

        $pass = 0;
        $fail = 0;
        $pending = 0;
        $need_override = 0;
        foreach ($code_values as $code_value) {
            $row = $item_rows[$code_value] ?? [
                'code_id'                 => null,
                'ean'                     => null,
                'batch_id'                => null,
                'outbound_scanned_at'     => null,
                'after_sales_deadline_at' => null,
                'validation_status'       => 'fail',
                'fail_reason_code'        => self::FAIL_INVALID_CODE_FORMAT,
                'fail_reason_msg'         => '防伪码格式无效，请检查后重试。',
            ];

            if (!empty($override_map[$code_value])) {
                $row['validation_status'] = 'pass_override';
                $row['override_id'] = (int) $override_map[$code_value];
                $row['fail_reason_code'] = null;
                $row['fail_reason_msg'] = null;
            } elseif ('fail' === (string) ($row['validation_status'] ?? 'pending')
                && self::is_overridable_fail_reason((string) ($row['fail_reason_code'] ?? ''))
            ) {
                $row['validation_status'] = 'need_override';
            }

            $validation_status = (string) ($row['validation_status'] ?? 'pending');
            if (in_array($validation_status, ['pass', 'pass_override'], true)) {
                $pass++;
            } elseif ('need_override' === $validation_status) {
                $need_override++;
            } elseif ('pending' === $validation_status) {
                $pending++;
            } else {
                $fail++;
            }

            $wpdb->update(
                $item_table,
                [
                    'code_id' => $row['code_id'],
                    'ean' => $row['ean'],
                    'batch_id' => $row['batch_id'],
                    'outbound_scanned_at' => $row['outbound_scanned_at'],
                    'after_sales_deadline_at' => $row['after_sales_deadline_at'],
                    'validation_status' => $validation_status,
                    'override_id' => isset($row['override_id']) ? (int) $row['override_id'] : null,
                    'fail_reason_code' => $row['fail_reason_code'],
                    'fail_reason_msg' => $row['fail_reason_msg'],
                ],
                [
                    'request_id' => $request_id,
                    'code_value' => $code_value,
                ],
                ['%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s'],
                ['%d', '%s']
            );
        }

        return [
            'rows' => $item_rows,
            'total' => count($code_values),
            'pass' => $pass,
            'fail' => $fail,
            'need_override' => $need_override,
            'pending' => $pending,
        ];
    }

    protected static function get_status_labels() {
        return [
            self::STATUS_DRAFT              => '草稿',
            self::STATUS_SUBMITTED          => '已提交',
            self::STATUS_SALES_REJECTED     => '销售驳回',
            self::STATUS_SALES_APPROVED     => '销售通过',
            self::STATUS_WAREHOUSE_CHECKING => '仓库核验中',
            self::STATUS_WAREHOUSE_REJECTED => '仓库驳回',
            self::STATUS_WAREHOUSE_APPROVED => '仓库通过',
            self::STATUS_FINANCE_REJECTED   => '财务驳回',
            self::STATUS_CLOSED             => '已结单',
        ];
    }
}
