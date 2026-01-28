<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Sales {
    const META_STATUS_KEY = 'aegis_account_status';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Portal 渲染。
     *
     * @param string $portal_url
     * @return string
     */
    public static function render_portal_panel($portal_url) {
        if (!AEGIS_System_Roles::is_hq_admin() && !current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM)) {
            AEGIS_Access_Audit::record_event(
                'ACCESS_DENIED',
                'FAIL',
                [
                    'reason_code' => 'sales_master_forbidden',
                    'user_id'     => (int) get_current_user_id(),
                    'roles'       => (array) wp_get_current_user()->roles,
                    'path'        => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
                ]
            );
            status_header(403);
            return '<div class="aegis-t-a5">当前账号无权访问销售管理。</div>';
        }

        $base_url = add_query_arg('m', 'sales_master', $portal_url);
        $messages = [];
        $errors = [];
        $current_user = null;
        $current_note = '';

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['sales_action']) ? sanitize_key(wp_unslash($_POST['sales_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $whitelist = [
                'sales_action',
                'target_user_id',
                'target_status',
                'display_name',
                'user_email',
                'sales_note',
                '_wp_http_referer',
                '_aegis_idempotency',
                'aegis_sales_nonce',
            ];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_SYSTEM,
                    'nonce_field'     => 'aegis_sales_nonce',
                    'nonce_action'    => 'aegis_sales_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => $idempotency,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } elseif ('toggle_status' === $action) {
                $result = self::handle_portal_status_toggle($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages[] = $result['message'];
                }
            } elseif ('save_sales' === $action) {
                $result = self::handle_portal_save($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages[] = $result['message'];
                }
            }
        }

        $current_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        if ($current_id > 0) {
            $current_user = get_user_by('id', $current_id);
            if (!$current_user || !in_array('aegis_sales', (array) $current_user->roles, true)) {
                $errors[] = '未找到对应的销售账号。';
                $current_user = null;
            } else {
                $current_note = (string) get_user_meta($current_id, 'aegis_sales_note', true);
            }
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        $per_options = [20, 50, 100];
        if (!in_array($per_page, $per_options, true)) {
            $per_page = 20;
        }
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $query = new WP_User_Query([
            'role'           => 'aegis_sales',
            'number'         => $per_page,
            'paged'          => $paged,
            'orderby'        => 'registered',
            'order'          => 'DESC',
            'search'         => $search ? '*' . $search . '*' : '',
            'search_columns' => $search ? ['user_login', 'display_name', 'user_email'] : [],
        ]);

        $users = $query->get_results();
        $total = (int) $query->get_total();
        $total_pages = $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1;

        $status_map = [];
        foreach ($users as $user) {
            $status_map[$user->ID] = self::get_account_status((int) $user->ID);
        }
        if ($current_user) {
            $status_map[$current_user->ID] = self::get_account_status((int) $current_user->ID);
        }

        $context = [
            'base_url'      => $base_url,
            'messages'      => $messages,
            'errors'        => $errors,
            'users'         => $users,
            'status_map'    => $status_map,
            'current_user'  => $current_user,
            'current_note'  => $current_note,
            'list'          => [
                'search'      => $search,
                'per_page'    => $per_page,
                'per_options' => $per_options,
                'paged'       => $paged,
                'total'       => $total,
                'total_pages' => $total_pages,
            ],
        ];

        return AEGIS_Portal::render_portal_template('sales', $context);
    }

    /**
     * 获取销售账号状态。
     *
     * @param int $user_id
     * @return string
     */
    public static function get_account_status($user_id) {
        $value = get_user_meta((int) $user_id, self::META_STATUS_KEY, true);
        if (!$value || !in_array($value, [self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
            return self::STATUS_ACTIVE;
        }
        return $value;
    }

    /**
     * 切换销售账号状态。
     *
     * @param array $post
     * @return array|WP_Error
     */
    protected static function handle_portal_status_toggle($post) {
        $target_user_id = isset($post['target_user_id']) ? (int) $post['target_user_id'] : 0;
        $target_status = isset($post['target_status']) ? sanitize_key($post['target_status']) : '';

        if ($target_user_id <= 0) {
            return new WP_Error('invalid_user', '未找到对应的销售账号。');
        }

        if (!in_array($target_status, [self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
            return new WP_Error('invalid_status', '无效的状态。');
        }

        $user = get_user_by('id', $target_user_id);
        if (!$user || !in_array('aegis_sales', (array) $user->roles, true)) {
            return new WP_Error('invalid_user', '未找到对应的销售账号。');
        }

        update_user_meta($target_user_id, self::META_STATUS_KEY, $target_status);

        AEGIS_Access_Audit::record_event(
            'SALES_STATUS_TOGGLE',
            'SUCCESS',
            [
                'target_user_id' => $target_user_id,
                'target_status'  => $target_status,
                'actor_user_id'  => (int) get_current_user_id(),
                'roles'          => (array) wp_get_current_user()->roles,
                'path'           => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
            ]
        );

        return [
            'message' => self::STATUS_ACTIVE === $target_status ? '销售账号已启用。' : '销售账号已停用。',
        ];
    }

    /**
     * 保存销售账号信息。
     *
     * @param array $post
     * @return array|WP_Error
     */
    protected static function handle_portal_save($post) {
        $target_user_id = isset($post['target_user_id']) ? (int) $post['target_user_id'] : 0;
        if ($target_user_id <= 0) {
            return new WP_Error('invalid_user', '未找到对应的销售账号。');
        }

        $user = get_user_by('id', $target_user_id);
        if (!$user || !in_array('aegis_sales', (array) $user->roles, true)) {
            return new WP_Error('invalid_user', '未找到对应的销售账号。');
        }

        $display_name = isset($post['display_name']) ? sanitize_text_field(wp_unslash($post['display_name'])) : '';
        $user_email = isset($post['user_email']) ? sanitize_email(wp_unslash($post['user_email'])) : '';
        $note = isset($post['sales_note']) ? sanitize_textarea_field(wp_unslash($post['sales_note'])) : '';

        if ($user_email && !is_email($user_email)) {
            return new WP_Error('invalid_email', '邮箱格式不正确。');
        }

        if ($user_email) {
            $existing = email_exists($user_email);
            if ($existing && (int) $existing !== $target_user_id) {
                return new WP_Error('email_exists', '邮箱已存在，请更换。');
            }
        }

        $changes = [];
        $payload = ['ID' => $target_user_id];
        if ($display_name !== $user->display_name) {
            $payload['display_name'] = $display_name;
            $changes['display_name'] = ['from' => $user->display_name, 'to' => $display_name];
        }
        if ($user_email && $user_email !== $user->user_email) {
            $payload['user_email'] = $user_email;
            $changes['user_email'] = ['from' => $user->user_email, 'to' => $user_email];
        }

        if (count($payload) > 1) {
            $updated = wp_update_user($payload);
            if (is_wp_error($updated)) {
                return $updated;
            }
        }

        $existing_note = (string) get_user_meta($target_user_id, 'aegis_sales_note', true);
        if ($note !== $existing_note) {
            update_user_meta($target_user_id, 'aegis_sales_note', $note);
            $changes['note'] = ['from' => $existing_note, 'to' => $note];
        }

        AEGIS_Access_Audit::record_event(
            'SALES_EDIT',
            'SUCCESS',
            [
                'target_user_id' => $target_user_id,
                'changed_fields' => $changes,
                'actor_user_id'  => (int) get_current_user_id(),
                'roles'          => (array) wp_get_current_user()->roles,
                'path'           => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
            ]
        );

        return ['message' => '销售账号已更新。'];
    }
}
