<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Returns {
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
