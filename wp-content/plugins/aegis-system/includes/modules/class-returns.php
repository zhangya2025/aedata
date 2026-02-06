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

        return '<div class="aegis-t-a5">该角色功能将在后续 PR 实现。</div>';
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

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['returns_action']) ? sanitize_key(wp_unslash($_POST['returns_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            if (in_array($action, ['create_draft', 'update_draft', 'delete_draft'], true)) {
                $validation = AEGIS_Access_Audit::validate_write_request(
                    $_POST,
                    [
                        'capability'      => AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                        'nonce_field'     => 'aegis_returns_nonce',
                        'nonce_action'    => 'aegis_returns_action',
                        'whitelist'       => [
                            'returns_action',
                            'request_id',
                            'contact_name',
                            'contact_phone',
                            'reason_code',
                            'remark',
                            'code_values',
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
                                    if ('pass' === $row['validation_status']) {
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
                                    $wpdb->query($wpdb->prepare("DELETE FROM {$item_table} WHERE request_id = %d", $request_id));
                                    $item_rows = self::build_item_rows_for_codes($codes, $dealer_id, $request_id);
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
                                        if ('pass' === $row['validation_status']) {
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
            'idempotency'    => wp_generate_uuid4(),
            'status_labels'  => self::get_status_labels(),
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

    protected static function is_request_editable($request) {
        if (!$request) {
            return false;
        }
        if (self::STATUS_DRAFT !== $request->status) {
            return false;
        }
        if (!empty($request->hard_locked_at) || !empty($request->content_locked_at)) {
            return false;
        }
        return true;
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
