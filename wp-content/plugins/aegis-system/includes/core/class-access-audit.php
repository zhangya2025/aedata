<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Access_Audit {
    protected static $request_id = '';

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
        }

        $result = [
            'success' => true,
            'message' => '',
        ];

        if (!empty($config['idempotency_key'])) {
            $idem_key = 'aegis_idem_' . md5($config['idempotency_key']);
            set_transient($idem_key, 1, MINUTE_IN_SECONDS * 10);
        }

        return $result;
    }

    /**
     * 写入审计事件。
     *
     * @param string $action
     * @param string $result
     * @param array  $object_data
     */
    public static function record_event($action, $result, $object_data = []) {
        $context = [
            'result' => $result,
            'meta'   => $object_data,
        ];

        if (isset($object_data['entity_type'])) {
            $context['entity_type'] = $object_data['entity_type'];
        }
        if (isset($object_data['entity_id'])) {
            $context['entity_id'] = $object_data['entity_id'];
        }

        return self::log($action, $context);
    }

    /**
     * 标准化写入审计事件。
     *
     * @param string $event_key
     * @param array  $context
     * @return bool
     */
    public static function log($event_key, $context = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . AEGIS_System::AUDIT_TABLE;

        $defaults = [
            'severity'       => '',
            'result'         => '',
            'message'        => '',
            'entity_type'    => '',
            'entity_id'      => '',
            'meta'           => null,
            'actor_user_id'  => null,
            'actor_role'     => '',
            'request_id'     => '',
            'request_path'   => '',
            'ip_hash'        => '',
            'user_agent_hash'=> '',
            'created_at'     => current_time('mysql'),
        ];

        $data = wp_parse_args($context, $defaults);

        try {
            $current_user = wp_get_current_user();
            if (null === $data['actor_user_id'] && $current_user && $current_user->ID) {
                $data['actor_user_id'] = (int) $current_user->ID;
            }
            if (empty($data['actor_role']) && $current_user && !empty($current_user->roles)) {
                $data['actor_role'] = implode(',', array_slice((array) $current_user->roles, 0, 5));
            }
            $actor_login = $current_user && $current_user->user_login ? $current_user->user_login : '';

            if (empty($data['request_id'])) {
                $data['request_id'] = self::get_request_id();
            }
            if (empty($data['request_path']) && isset($_SERVER['REQUEST_URI'])) {
                $data['request_path'] = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
            }

            if (empty($data['ip_hash']) && !empty($_SERVER['REMOTE_ADDR'])) {
                $data['ip_hash'] = self::hash_value(wp_unslash($_SERVER['REMOTE_ADDR']));
            }
            if (empty($data['user_agent_hash']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
                $data['user_agent_hash'] = self::hash_value(wp_unslash($_SERVER['HTTP_USER_AGENT']));
            }

            $result = strtoupper($data['result']);
            $severity = $data['severity'] ? strtoupper($data['severity']) : ($result === 'FAIL' || $result === 'ERROR' ? 'ERROR' : 'INFO');

            $meta_json = null;
            if (!empty($data['meta'])) {
                $meta_json = wp_json_encode($data['meta']);
            }

            $inserted = $wpdb->insert(
                $table_name,
                [
                    'event_key'       => $event_key,
                    'severity'        => $severity,
                    'actor_user_id'   => $data['actor_user_id'],
                    'actor_role'      => $data['actor_role'],
                    'actor_login'     => $actor_login,
                    'ip_hash'         => $data['ip_hash'],
                    'user_agent_hash' => $data['user_agent_hash'],
                    'request_path'    => $data['request_path'],
                    'request_id'      => $data['request_id'],
                    'entity_type'     => $data['entity_type'],
                    'entity_id'       => $data['entity_id'],
                    'result'          => $result,
                    'message'         => $data['message'],
                    'meta_json'       => $meta_json,
                    'created_at'      => $data['created_at'],
                ],
                [
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );

            if (false === $inserted && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AEGIS] Failed to insert audit event: ' . $wpdb->last_error);
            }

            return false !== $inserted;
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AEGIS] Audit log error: ' . $e->getMessage());
            }
            return false;
        }
    }

    protected static function get_request_id() {
        if (!empty(self::$request_id)) {
            return self::$request_id;
        }

        $seed = uniqid('aegis_', true) . wp_rand();
        self::$request_id = substr(md5($seed), 0, 32);

        return self::$request_id;
    }

    protected static function hash_value($value) {
        return hash('sha256', (string) $value);
    }

    public static function query_events($args = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . AEGIS_System::AUDIT_TABLE;

        $defaults = [
            'start_date' => gmdate('Y-m-d', strtotime('-7 days')),
            'end_date'   => gmdate('Y-m-d'),
            'event_key'  => '',
            'actor_role' => '',
            'result'     => '',
            'per_page'   => 20,
            'page'       => 1,
        ];
        $args = wp_parse_args($args, $defaults);

        $conditions = [];
        $params = [];

        if (!empty($args['start_date'])) {
            $conditions[] = 'created_at >= %s';
            $params[] = $args['start_date'] . ' 00:00:00';
        }
        if (!empty($args['end_date'])) {
            $conditions[] = 'created_at <= %s';
            $params[] = $args['end_date'] . ' 23:59:59';
        }
        if (!empty($args['event_key'])) {
            $conditions[] = 'event_key = %s';
            $params[] = $args['event_key'];
        }
        if (!empty($args['actor_role'])) {
            $conditions[] = 'actor_role LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['actor_role']) . '%';
        }
        if (!empty($args['result'])) {
            $conditions[] = 'result = %s';
            $params[] = strtoupper($args['result']);
        }

        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $per_page = max(1, (int) $args['per_page']);
        $page = max(1, (int) $args['page']);
        $offset = ($page - 1) * $per_page;

        $total_sql = "SELECT COUNT(*) FROM {$table_name} {$where}";
        $query_sql = "SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";

        $count_params = $params;
        $query_params = $params;
        $query_params[] = $per_page;
        $query_params[] = $offset;

        $total_sql_final = empty($count_params) ? $total_sql : $wpdb->prepare($total_sql, $count_params);
        $query_sql_final = $wpdb->prepare($query_sql, $query_params);

        $total = (int) $wpdb->get_var($total_sql_final);
        $rows = $wpdb->get_results($query_sql_final);

        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $row->meta = [];
            if (!empty($row->meta_json)) {
                $decoded = json_decode($row->meta_json, true);
                $row->meta = is_array($decoded) ? $decoded : [];
            }
        }

        return [
            'items'    => $rows,
            'total'    => $total,
            'per_page' => $per_page,
            'page'     => $page,
        ];
    }
}
