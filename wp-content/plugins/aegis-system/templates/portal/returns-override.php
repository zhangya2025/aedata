<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url = $context_data['base_url'] ?? '';
$messages = $context_data['messages'] ?? [];
$errors = $context_data['errors'] ?? [];
$status_filter = $context_data['status_filter'] ?? 'active';
$status_options = $context_data['status_options'] ?? [];
$search_code = $context_data['search_code'] ?? '';
$rows = $context_data['rows'] ?? [];
$idempotency = $context_data['idempotency'] ?? wp_generate_uuid4();
$is_hq = !empty($context_data['is_hq']);
$can_revoke = !empty($context_data['can_revoke']);
$nav = $context_data['nav'] ?? [];
?>

<div class="aegis-t-a4" style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
    <div class="aegis-t-a2">特批码管理（限时通过码）</div>
</div>

<?php if ($is_hq) : ?>
    <div class="aegis-t-a6" style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <a class="button" href="<?php echo esc_url($nav['sales_url'] ?? $base_url); ?>">销售审核</a>
        <a class="button button-primary" href="<?php echo esc_url($nav['override_url'] ?? $base_url); ?>">特批码管理</a>
        <a class="button" href="<?php echo esc_url($nav['finance_url'] ?? $base_url); ?>">财务审核</a>
    </div>
<?php endif; ?>

<?php foreach ($messages as $msg) : ?>
    <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
<?php endforeach; ?>
<?php foreach ($errors as $msg) : ?>
    <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
<?php endforeach; ?>

<section class="aegis-card" style="margin-bottom:16px;">
    <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">生成特批码</div></div>
    <div class="aegis-card-body">
        <form method="post" class="aegis-t-a6" style="display:grid; gap:10px; max-width:680px;">
            <?php wp_nonce_field('aegis_returns_override_action', 'aegis_returns_override_nonce'); ?>
            <input type="hidden" name="returns_action" value="issue_override" />
            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
            <label>防伪码 code_value
                <input type="text" name="code_value" required style="width:100%;" />
            </label>
            <label>经销商ID dealer_id
                <input type="number" name="dealer_id" min="1" required style="width:100%;" />
            </label>
            <label>有效期（小时）
                <input type="number" name="expires_hours" min="1" max="168" value="48" style="width:100%;" />
            </label>
            <label>通过原因
                <textarea name="reason_text" rows="3" required style="width:100%;"></textarea>
            </label>
            <div><button type="submit" class="button button-primary">生成特批码</button></div>
        </form>
    </div>
</section>

<section class="aegis-card">
    <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">特批码列表</div></div>
    <div class="aegis-card-body">
        <form method="get" class="aegis-t-a6" style="margin-bottom:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="m" value="returns" />
            <input type="hidden" name="stage" value="override" />
            <label>状态
                <select name="status_filter">
                    <?php foreach ($status_options as $status_key => $status_label) : ?>
                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>><?php echo esc_html($status_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>防伪码
                <input type="text" name="search_code" value="<?php echo esc_attr($search_code); ?>" placeholder="精确匹配 code_value" />
            </label>
            <button type="submit" class="button">筛选</button>
        </form>

        <div class="aegis-portal-table" style="overflow:auto;">
            <table class="aegis-t-a6" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e5e5e5;">
                        <th style="padding:8px;">ID</th>
                        <th style="padding:8px;">防伪码</th>
                        <th style="padding:8px;">经销商</th>
                        <th style="padding:8px;">状态</th>
                        <th style="padding:8px;">expires_at</th>
                        <th style="padding:8px;">issued_at / issued_by</th>
                        <th style="padding:8px;">used_at / used_in_request_id</th>
                        <th style="padding:8px;">原因</th>
                        <th style="padding:8px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="9" style="padding:12px;">暂无记录。</td></tr>
                    <?php else : ?>
                        <?php $now_ts = current_time('timestamp'); ?>
                        <?php foreach ($rows as $row) : ?>
                            <?php
                            $is_expired = !empty($row->expires_at) && strtotime((string) $row->expires_at) <= $now_ts;
                            $ui_status = strtoupper((string) ($row->status ?? ''));
                            if ('ACTIVE' === $ui_status && $is_expired) {
                                $ui_status = 'EXPIRED';
                            }
                            $is_active_not_expired = ('active' === (string) $row->status) && !$is_expired;
                            ?>
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                <td style="padding:8px;"><?php echo esc_html((string) ((int) $row->id)); ?></td>
                                <td style="padding:8px;"><?php echo esc_html(AEGIS_System::format_code_display((string) ($row->code_value ?? ''))); ?></td>
                                <td style="padding:8px;"><?php echo esc_html(($row->dealer_name ?? '-') . ' (#' . (int) $row->dealer_id . ')'); ?></td>
                                <td style="padding:8px;"><?php echo esc_html($ui_status); ?></td>
                                <td style="padding:8px; white-space:nowrap;"><?php echo esc_html((string) ($row->expires_at ?? '')); ?></td>
                                <td style="padding:8px; white-space:nowrap;"><?php echo esc_html((string) ($row->issued_at ?? '')); ?> / #<?php echo esc_html((string) ((int) $row->issued_by)); ?></td>
                                <td style="padding:8px; white-space:nowrap;"><?php echo esc_html((string) ($row->used_at ?? '')); ?> / #<?php echo esc_html((string) ((int) $row->used_in_request_id)); ?></td>
                                <td style="padding:8px;"><?php echo esc_html(wp_trim_words((string) ($row->reason_text ?? ''), 20, '...')); ?></td>
                                <td style="padding:8px; white-space:nowrap;">
                                    <?php if ($can_revoke && $is_active_not_expired) : ?>
                                        <form method="post" onsubmit="return confirm('确认撤销该特批码？');">
                                            <?php wp_nonce_field('aegis_returns_override_action', 'aegis_returns_override_nonce'); ?>
                                            <input type="hidden" name="returns_action" value="revoke_override" />
                                            <input type="hidden" name="override_id" value="<?php echo esc_attr((int) $row->id); ?>" />
                                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                            <button type="submit" class="button">撤销</button>
                                        </form>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
