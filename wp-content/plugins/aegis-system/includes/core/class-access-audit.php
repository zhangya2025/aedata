<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Access_Audit {
    /**
     * 验证写入口：统一鉴权、nonce、白名单、可选幂等键。
     *
     * @param array $params
     * @param array $config
     * @return array
     */
    public static function validate_write_request($params, $config = []) {
        $defaults = [
            'capability'      => AEGIS_System::CAP_MANAGE_SYSTEM,
            'nonce_field'     => null,
            'nonce_action'    => null,
            'whitelist'       => [],
            'idempotency_key' => null,
        ];
        $config = wp_parse_args($config, $defaults);

        if (!current_user_can($config['capability'])) {
            self::record_event('ACCESS_DENIED', 'FAIL', ['reason' => 'capability']);
            return [
                'success' => false,
                'message' => '权限不足。',
            ];
        }

        if ($config['nonce_field'] && $config['nonce_action']) {
            $nonce_value = isset($params[$config['nonce_field']]) ? $params[$config['nonce_field']] : '';
            if (!wp_verify_nonce($nonce_value, $config['nonce_action'])) {
                self::record_event('NONCE_INVALID', 'FAIL', ['nonce_field' => $config['nonce_field']]);
                return [
                    'success' => false,
                    'message' => '安全校验失败，请重试。',
                ];
            }
        }

        if (!empty($config['whitelist'])) {
            $extra_keys = array_diff(array_keys($params), $config['whitelist']);
            if (!empty($extra_keys)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[AEGIS] Blocked params: ' . wp_json_encode(array_values($extra_keys)));
                }
                self::record_event('PARAMS_NOT_ALLOWED', 'FAIL', ['keys' => array_values($extra_keys)]);
                return [
                    'success' => false,
                    'message' => '请求参数不被允许。',
                ];
            }
        }

        if (!empty($config['idempotency_key'])) {
            $idem_key = 'aegis_idem_' . md5($config['idempotency_key']);
            if (get_transient($idem_key)) {
                self::record_event('IDEMPOTENT_REPLAY', 'FAIL', ['key' => $config['idempotency_key']]);
                return [
                    'success' => false,
                    'message' => '请求已处理，请勿重复提交。',
                ];
            }
            set_transient($idem_key, 1, MINUTE_IN_SECONDS * 10);
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    /**
     * 写入审计事件。
     *
     * @param string $action
     * @param string $result
     * @param array  $object_data
     */
    public static function record_event($action, $result, $object_data = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . AEGIS_System::AUDIT_TABLE;

        $current_user = wp_get_current_user();
        $actor_id = $current_user && $current_user->ID ? (int) $current_user->ID : null;
        $actor_login = $current_user && $current_user->user_login ? $current_user->user_login : null;

        $wpdb->insert(
            $table_name,
            [
                'actor_id'    => $actor_id,
                'actor_login' => $actor_login,
                'action'      => $action,
                'result'      => $result,
                'object_data' => !empty($object_data) ? wp_json_encode($object_data) : null,
                'created_at'  => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );
    }
}

