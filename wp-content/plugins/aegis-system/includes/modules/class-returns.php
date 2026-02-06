<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Returns {
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
            return '<div class="aegis-t-a5">模块未启用，请联系管理员。</div>';
        }

        $user = wp_get_current_user();
        $roles = $user ? (array) $user->roles : [];
        $sections = [];

        if (in_array('aegis_dealer', $roles, true)) {
            $sections[] = '经销商：未来这里发起退货申请。';
        }
        if (in_array('aegis_sales', $roles, true)) {
            $sections[] = '销售：未来这里审核退货申请。';
        }
        if (in_array('aegis_warehouse_manager', $roles, true) || in_array('aegis_warehouse_staff', $roles, true)) {
            $sections[] = '仓库：未来这里扫码核对退货。';
        }
        if (in_array('aegis_finance', $roles, true)) {
            $sections[] = '财务：未来这里完成结单审核。';
        }
        if (AEGIS_System_Roles::is_hq_admin($user)) {
            $sections[] = 'HQ：未来这里发放特批码。';
        }

        if (empty($sections)) {
            $sections[] = '功能待后续 PR 实现。';
        }

        $back_url = esc_url($portal_url);
        $items = '<ul style="margin:12px 0 0 18px;">';
        foreach ($sections as $line) {
            $items .= '<li class="aegis-t-a6" style="margin-bottom:6px;">' . esc_html($line) . '</li>';
        }
        $items .= '</ul>';

        return sprintf(
            '<div class="aegis-t-a3" style="margin-bottom:12px;">退货申请（模块占位）</div>
            <div class="aegis-t-a5" style="color:#555;">以下功能正在建设中：</div>
            %s
            <div style="margin-top:16px;">
                <a class="aegis-portal-button is-primary" href="%s">返回 Portal 首页</a>
            </div>',
            $items,
            $back_url
        );
    }
}
