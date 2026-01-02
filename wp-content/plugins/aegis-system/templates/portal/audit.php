<?php
if (!defined('ABSPATH')) {
    exit;
}

$portal_url  = $context_data['portal_url'] ?? '';
$filters     = $context_data['filters'] ?? [];
$events      = $context_data['events'] ?? [];
$total       = $context_data['total'] ?? 0;
$page        = $context_data['page'] ?? 1;
$per_page    = $context_data['per_page'] ?? 20;
$total_pages = $context_data['total_pages'] ?? 1;
$can_export  = !empty($context_data['can_export']);

$base_url = add_query_arg('m', 'access_audit', $portal_url);
?>
<div class="aegis-t-a4" style="margin-bottom:12px;">访问审计</div>
<div class="aegis-t-a6" style="margin-bottom:16px; color:#555;">查看最近访问与操作轨迹，支持筛选与导出。</div>
<form method="get" action="<?php echo esc_url($portal_url); ?>" class="aegis-t-a6" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:flex-end; margin-bottom:16px;">
    <input type="hidden" name="m" value="access_audit" />
    <label style="display:block;">
        <span class="aegis-t-a6">开始日期</span>
        <input type="date" name="start_date" value="<?php echo esc_attr($filters['start_date'] ?? ''); ?>" style="width:100%;margin-top:4px;" />
    </label>
    <label style="display:block;">
        <span class="aegis-t-a6">结束日期</span>
        <input type="date" name="end_date" value="<?php echo esc_attr($filters['end_date'] ?? ''); ?>" style="width:100%;margin-top:4px;" />
    </label>
    <label style="display:block;">
        <span class="aegis-t-a6">事件</span>
        <input type="text" name="event_key" value="<?php echo esc_attr($filters['event_key'] ?? ''); ?>" placeholder="如 ORDER_CREATE" style="width:100%;margin-top:4px;" />
    </label>
    <label style="display:block;">
        <span class="aegis-t-a6">角色</span>
        <input type="text" name="actor_role" value="<?php echo esc_attr($filters['actor_role'] ?? ''); ?>" placeholder="如 aegis_sales" style="width:100%;margin-top:4px;" />
    </label>
    <label style="display:block;">
        <span class="aegis-t-a6">结果</span>
        <select name="result" style="width:100%;margin-top:4px;">
            <?php $current_result = $filters['result'] ?? ''; ?>
            <option value="" <?php selected($current_result, ''); ?>>全部</option>
            <option value="SUCCESS" <?php selected($current_result, 'SUCCESS'); ?>>SUCCESS</option>
            <option value="FAIL" <?php selected($current_result, 'FAIL'); ?>>FAIL</option>
            <option value="BLOCKED" <?php selected($current_result, 'BLOCKED'); ?>>BLOCKED</option>
            <option value="ERROR" <?php selected($current_result, 'ERROR'); ?>>ERROR</option>
        </select>
    </label>
    <label style="display:block;">
        <span class="aegis-t-a6">每页</span>
        <select name="per_page" style="width:100%;margin-top:4px;">
            <?php foreach ([20, 50, 100] as $size) : ?>
                <option value="<?php echo esc_attr($size); ?>" <?php selected((int) $per_page, $size); ?>><?php echo esc_html($size); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div style="display:flex; gap:8px; align-items:flex-end;">
        <button type="submit" class="aegis-t-a6" style="padding:8px 16px; background:#2271b1; border:1px solid #1c5a8e; color:#fff; border-radius:4px; cursor:pointer;">筛选</button>
        <?php if ($can_export) : ?>
            <a class="aegis-t-a6" style="padding:8px 16px; background:#f6f7f7; border:1px solid #c3c4c7; color:#111; border-radius:4px; text-decoration:none;" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export' => 'csv', 'paged' => 1]), $base_url)); ?>">导出 CSV</a>
        <?php endif; ?>
    </div>
</form>
<div class="aegis-t-a6" style="margin-bottom:8px; color:#555;">共 <?php echo esc_html($total); ?> 条记录</div>
<div class="aegis-portal-table" style="overflow:auto;">
    <table class="aegis-t-a6" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #e5e5e5;">
                <th style="padding:8px;">时间</th>
                <th style="padding:8px;">事件</th>
                <th style="padding:8px;">结果</th>
                <th style="padding:8px;">角色</th>
                <th style="padding:8px;">用户</th>
                <th style="padding:8px;">实体</th>
                <th style="padding:8px;">路径</th>
                <th style="padding:8px;">消息</th>
                <th style="padding:8px;">Meta</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($events)) : ?>
                <tr><td colspan="9" class="aegis-t-a6" style="padding:12px;">暂无数据。</td></tr>
            <?php else : ?>
                <?php foreach ($events as $row) : ?>
                    <tr style="border-bottom:1px solid #f0f0f0;">
                        <td style="padding:8px; white-space:nowrap;"><?php echo esc_html($row->created_at); ?></td>
                        <td style="padding:8px;"><?php echo esc_html($row->event_key); ?></td>
                        <td style="padding:8px;"><?php echo esc_html($row->result); ?></td>
                        <td style="padding:8px;"><?php echo esc_html($row->actor_role); ?></td>
                        <td style="padding:8px;"><?php echo esc_html($row->actor_login); ?></td>
                        <td style="padding:8px;">
                            <?php echo esc_html(trim(($row->entity_type ?? '') . ' ' . ($row->entity_id ?? ''))); ?>
                        </td>
                        <td style="padding:8px;"><?php echo esc_html($row->request_path); ?></td>
                        <td style="padding:8px;"><?php echo esc_html($row->message); ?></td>
                        <td style="padding:8px;">
                            <?php if (!empty($row->meta_json)) : ?>
                                <details>
                                    <summary class="aegis-t-a6" style="cursor:pointer;">展开</summary>
                                    <pre class="aegis-t-a6" style="white-space:pre-wrap; background:#f6f7f7; padding:8px; border:1px solid #e5e5e5; margin-top:6px;">
<?php echo esc_html($row->meta_json); ?>
                                    </pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($total_pages > 1) : ?>
    <div class="aegis-t-a6" style="margin-top:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
            <?php $link = add_query_arg(array_merge($_GET, ['paged' => $i]), $base_url); ?>
            <?php if ($i === (int) $page) : ?>
                <span style="padding:6px 10px; border:1px solid #2271b1; background:#2271b1; color:#fff; border-radius:4px;">第 <?php echo esc_html($i); ?> 页</span>
            <?php else : ?>
                <a class="aegis-t-a6" href="<?php echo esc_url($link); ?>" style="padding:6px 10px; border:1px solid #c3c4c7; background:#fff; color:#111; border-radius:4px; text-decoration:none;">第 <?php echo esc_html($i); ?> 页</a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>
