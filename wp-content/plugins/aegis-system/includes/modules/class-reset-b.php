<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Reset_B {
    const DEALER_META_KEY = 'aegis_dealer_id';

    /**
     * 渲染清零系统页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_reset_b()) {
            wp_die(__('您无权访问该页面。'));
        }

        $module_enabled = AEGIS_System::is_module_enabled('reset_b');
        $messages = [];
        $errors = [];
        $result = null;
        $dealer = self::get_current_dealer();
        $is_dealer_user = AEGIS_System_Roles::is_dealer_only();

        if (!$module_enabled) {
            $errors[] = '清零系统模块未启用。';
        }

        if ($is_dealer_user && !$dealer) {
            $errors[] = '未找到您的经销商档案，无法执行清零。';
        } elseif ($is_dealer_user && $dealer && $dealer->status !== 'active') {
            $errors[] = '当前经销商已停用，禁止清零操作。';
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'   => AEGIS_System::CAP_RESET_B,
                    'nonce_field'  => 'aegis_reset_b_nonce',
                    'nonce_action' => 'aegis_reset_b_action',
                    'whitelist'    => ['aegis_reset_b_nonce', 'reset_b_action', 'code_value', 'reason', 'confirm_reset', '_wp_http_referer', '_aegis_idempotency'],
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } elseif (!$module_enabled) {
                $errors[] = '清零系统模块未启用。';
            } elseif ($is_dealer_user && (!$dealer || $dealer->status !== 'active')) {
                $errors[] = '经销商状态异常，禁止清零。';
            } elseif (empty($_POST['confirm_reset'])) {
                $errors[] = '请勾选确认后再执行清零。';
            } else {
                $result = self::handle_reset($_POST, $dealer, $is_dealer_user);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                    $result = null;
                } else {
                    $messages[] = 'B 清零成功。';
                }
            }
        }

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a3">清零系统</h1>';

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

        if (!$module_enabled) {
            echo '</div>';
            return;
        }

        $idempotency_key = wp_generate_uuid4();
        echo '<form method="post" class="aegis-t-a5" style="max-width:720px;">';
        wp_nonce_field('aegis_reset_b_action', 'aegis_reset_b_nonce');
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';
        echo '<input type="hidden" name="reset_b_action" value="reset" />';

        if ($is_dealer_user && $dealer) {
            echo '<p class="aegis-t-a6"><strong>经销商：</strong>' . esc_html($dealer->dealer_name) . '（授权码 ' . esc_html($dealer->auth_code) . '）</p>';
        }

        echo '<p class="aegis-t-a6">请输入要清零 B 计数的防伪码，确认后将仅重置 B（内部计数），A 计数保持不变。</p>';
        echo '<table class="form-table">';
        echo '<tr><th><label class="aegis-t-a5" for="code_value">防伪码</label></th><td><input type="text" id="code_value" name="code_value" class="regular-text" required /></td></tr>';
        echo '<tr><th><label class="aegis-t-a5" for="reason">原因（可选）</label></th><td><input type="text" id="reason" name="reason" class="regular-text" /></td></tr>';
        echo '<tr><th></th><td><label class="aegis-t-a6"><input type="checkbox" name="confirm_reset" value="1" /> 我确认仅清零 B 计数，操作将被审计。</label></td></tr>';
        echo '</table>';
        submit_button('执行清零');
        echo '</form>';

        if ($result) {
            echo '<h2 class="aegis-t-a4">清零结果</h2>';
            echo '<p class="aegis-t-a6">防伪码：' . esc_html($result['code_value']) . '</p>';
            echo '<p class="aegis-t-a6">清零前 B：' . esc_html($result['before_b']) . '，清零后 B：' . esc_html($result['after_b']) . '</p>';
            if (!empty($result['dealer_name'])) {
                echo '<p class="aegis-t-a6">归属经销商：' . esc_html($result['dealer_name']) . '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * 执行清零逻辑。
     */
    protected static function handle_reset($post, $dealer, $is_dealer_user) {
        global $wpdb;
        $code_value = isset($post['code_value']) ? sanitize_text_field(wp_unslash($post['code_value'])) : '';
        $reason = isset($post['reason']) ? sanitize_text_field(wp_unslash($post['reason'])) : '';

        if ('' === $code_value) {
            return new WP_Error('empty_code', '请输入防伪码。');
        }

        $record = self::get_code_record($code_value);
        if (!$record) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code' => $code_value, 'reason' => 'not_found']);
            return new WP_Error('code_not_found', '未找到对应防伪码。');
        }

        $shipment = self::get_latest_shipment($record->id);

        if ($is_dealer_user) {
            if (!$dealer) {
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code_id' => (int) $record->id, 'reason' => 'dealer_missing']);
                return new WP_Error('dealer_missing', '未配置经销商资料，无法清零。');
            }
            if ($dealer->status !== 'active') {
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code_id' => (int) $record->id, 'reason' => 'dealer_inactive', 'dealer_id' => (int) $dealer->id]);
                return new WP_Error('dealer_inactive', '经销商已停用，禁止清零。');
            }
            if (!$shipment || (int) $shipment->dealer_id !== (int) $dealer->id) {
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code_id' => (int) $record->id, 'reason' => 'not_owned', 'dealer_id' => (int) $dealer->id]);
                return new WP_Error('not_owned', '该防伪码未出库至您或尚未出库，禁止清零。');
            }
        }

        $table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $raw_b = (int) $record->query_b_count;
        $offset = (int) $record->query_b_offset;
        $before_b = max(0, $raw_b - $offset);
        $update = $wpdb->update($table, ['query_b_offset' => $raw_b], ['id' => $record->id], ['%d'], ['%d']);

        if (false === $update) {
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'FAIL', ['code_id' => (int) $record->id, 'reason' => 'db_error', 'db_error' => $wpdb->last_error]);
            return new WP_Error('db_error', '清零失败：数据库错误。');
        }

        $audit_payload = [
            'code_id'        => (int) $record->id,
            'code'           => $code_value,
            'before_b'       => $before_b,
            'after_b'        => 0,
            'raw_b'          => $raw_b,
            'prior_offset'   => $offset,
            'reason'         => $reason,
            'shipment_id'    => $shipment ? (int) $shipment->shipment_id : null,
            'dealer_id'      => $shipment ? (int) $shipment->dealer_id : null,
            'dealer_name'    => $shipment && isset($shipment->dealer_name) ? $shipment->dealer_name : null,
            'actor_dealer'   => $dealer ? (int) $dealer->id : null,
        ];

        AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_RESET_B, 'SUCCESS', $audit_payload);

        return [
            'code_value' => $code_value,
            'before_b'   => $before_b,
            'after_b'    => 0,
            'dealer_name'=> $shipment && isset($shipment->dealer_name) ? $shipment->dealer_name : '',
        ];
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
        $table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
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
}

new AEGIS_System();
