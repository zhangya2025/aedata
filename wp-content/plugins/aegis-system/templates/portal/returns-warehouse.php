<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url = $context_data['base_url'] ?? '';
$messages = $context_data['messages'] ?? [];
$errors = $context_data['errors'] ?? [];
$status_filter = $context_data['status_filter'] ?? AEGIS_Returns::STATUS_SALES_APPROVED;
$status_options = $context_data['status_options'] ?? [];
$view_mode = $context_data['view_mode'] ?? 'list';
$requests = $context_data['requests'] ?? [];
$counts = $context_data['counts'] ?? [];
$request = $context_data['request'] ?? null;
$items = $context_data['items'] ?? [];
$warehouse_check = $context_data['warehouse_check'] ?? null;
$scans = $context_data['scans'] ?? [];
$summary = $context_data['summary'] ?? [];
$missing_codes = $context_data['missing_codes'] ?? [];
$matched_codes = $context_data['matched_codes'] ?? [];
$can_start = !empty($context_data['can_start']);
$can_scan = !empty($context_data['can_scan']);
$can_approve = !empty($context_data['can_approve']);
$can_reject = !empty($context_data['can_reject']);
$idempotency = $context_data['idempotency'] ?? wp_generate_uuid4();

$list_url = add_query_arg(['status' => $status_filter], $base_url);
$matched_map = [];
foreach ($matched_codes as $code) {
    $matched_map[(string) $code] = true;
}
$abnormal_scans = array_values(array_filter(
    $scans,
    static function ($scan_row) {
        return 'MATCH' !== ($scan_row->scan_result ?? '');
    }
));

$sample_reason_options = AEGIS_Returns::get_sample_reason_options();
$format_sample_reason = static function ($item) use ($sample_reason_options) {
    $meta = [];
    if (!empty($item->meta)) {
        $decoded = json_decode((string) $item->meta, true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    $reason = isset($meta['sample_reason']) ? (string) $meta['sample_reason'] : '';
    $reason_text = isset($meta['sample_reason_text']) ? (string) $meta['sample_reason_text'] : '';
    $label = isset($sample_reason_options[$reason]) ? (string) $sample_reason_options[$reason] : '';

    if ('' === $reason) {
        return '—';
    }
    if ('other' === $reason) {
        return '' !== $reason_text ? ('其他：' . $reason_text) : '其他';
    }

    return '' !== $label ? $label : '—';
};
?>

<div class="aegis-t-a4 aegis-returns-page">
    <div class="aegis-returns-header">
        <div class="aegis-t-a2">退货收货核对</div>
        <div class="aegis-returns-header-actions">
            <?php if ('detail' === $view_mode) : ?>
                <a class="aegis-portal-button" href="<?php echo esc_url($list_url); ?>">返回列表</a>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <section class="aegis-card">
        <form method="get" class="aegis-t-a6" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="m" value="returns" />
            <label>状态
                <select name="status" onchange="this.form.submit()">
                    <?php foreach ($status_options as $status_key => $status_label) : ?>
                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>><?php echo esc_html($status_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="aegis-portal-button is-primary">筛选</button>
        </form>
    </section>

    <?php if ('list' === $view_mode) : ?>
        <section class="aegis-card">
            <div class="aegis-table-wrap">
                <table class="aegis-table aegis-t-a6" style="width:100%;">
                    <thead><tr><th>单号</th><th>经销商</th><th>条目数</th><th>销售同意时间</th><th>状态</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php if (empty($requests)) : ?>
                            <tr><td colspan="6">暂无数据。</td></tr>
                        <?php else : ?>
                            <?php foreach ($requests as $row) : ?>
                                <?php $detail_url = add_query_arg(['request_id' => (int) $row->id, 'status' => $status_filter], $base_url); ?>
                                <tr>
                                    <td><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($row->request_no); ?></a></td>
                                    <td><?php echo esc_html($row->dealer_name ?? ''); ?></td>
                                    <td><?php echo esc_html((string) ($counts[(int) $row->id] ?? 0)); ?></td>
                                    <td><?php echo esc_html($row->sales_audited_at ?? ''); ?></td>
                                    <td><?php echo esc_html($status_options[$row->status] ?? $row->status); ?></td>
                                    <td><a class="aegis-portal-button" href="<?php echo esc_url($detail_url); ?>">查看</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php elseif ($request) : ?>
        <section class="aegis-card">
            <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">单据信息与摘要</div></div>
            <div class="aegis-card-body aegis-t-a6">
                <p><strong>单号：</strong><?php echo esc_html($request->request_no ?? ''); ?></p>
                <p><strong>经销商：</strong><?php echo esc_html($request->dealer_name ?? ''); ?></p>
                <p><strong>提交时间：</strong><?php echo esc_html($request->submitted_at ?? ''); ?></p>
                <p><strong>销售同意时间：</strong><?php echo esc_html($request->sales_audited_at ?? ''); ?></p>
                <p><strong>当前状态：</strong><?php echo esc_html($status_options[$request->status] ?? $request->status); ?></p>
                <?php if ($warehouse_check) : ?><p><strong>核对记录状态：</strong><?php echo esc_html($warehouse_check->status ?? ''); ?></p><?php endif; ?>
                <div class="aegis-returns-kpis" style="margin-top:12px;">
                    <div class="aegis-returns-kpi"><div class="label">应收</div><div class="value"><?php echo esc_html((string) ($summary['expected_total'] ?? 0)); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">已匹配</div><div class="value"><?php echo esc_html((string) ($summary['matched_total'] ?? 0)); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">缺失</div><div class="value"><?php echo esc_html((string) ($summary['missing_total'] ?? 0)); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">异常</div><div class="value"><?php echo esc_html((string) ($summary['bad_total'] ?? 0)); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">重复</div><div class="value"><?php echo esc_html((string) ($summary['dup_total'] ?? 0)); ?></div></div>
                </div>
                <?php if (!empty($missing_codes)) : ?>
                    <p style="margin-top:10px;"><strong>缺失码：</strong><?php echo esc_html(implode(', ', array_map(['AEGIS_System', 'format_code_display'], $missing_codes))); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="aegis-card">
            <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">扫码核对</div></div>
            <div class="aegis-card-body aegis-t-a6" style="display:grid; gap:12px;">
                <?php if ($can_start) : ?>
                    <form method="post">
                        <?php wp_nonce_field('aegis_returns_wh_action', 'aegis_returns_wh_nonce'); ?>
                        <input type="hidden" name="returns_action" value="warehouse_start" />
                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                        <button type="submit" class="aegis-portal-button is-primary">开始核对</button>
                    </form>
                <?php endif; ?>
                <?php if ($can_scan) : ?>
                    <form method="post" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                        <?php wp_nonce_field('aegis_returns_wh_action', 'aegis_returns_wh_nonce'); ?>
                        <input type="hidden" name="returns_action" value="warehouse_scan" />
                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                        <label style="flex:1; min-width:280px;">扫码录入<input type="text" name="scan_code" style="width:100%;" required /></label>
                        <button type="submit" class="aegis-portal-button is-primary">扫码录入</button>
                    </form>
                <?php endif; ?>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <?php if ($can_approve) : ?>
                        <form method="post" onsubmit="return confirm('确认核对通过并流转到财务？');">
                            <?php wp_nonce_field('aegis_returns_wh_action', 'aegis_returns_wh_nonce'); ?>
                            <input type="hidden" name="returns_action" value="warehouse_approve" />
                            <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                            <label style="display:block; margin-bottom:8px;">备注（可选）<textarea name="warehouse_comment" rows="3" style="width:100%;"></textarea></label>
                            <button type="submit" class="aegis-portal-button is-primary">核对通过</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($can_reject) : ?>
                        <form method="post" onsubmit="return confirm('确认核对不通过并释放锁码？');">
                            <?php wp_nonce_field('aegis_returns_wh_action', 'aegis_returns_wh_nonce'); ?>
                            <input type="hidden" name="returns_action" value="warehouse_reject" />
                            <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                            <label style="display:block; margin-bottom:8px;">驳回原因（必填）<textarea name="reject_reason" rows="3" style="width:100%;" required></textarea></label>
                            <button type="submit" class="aegis-portal-button">核对不通过</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="aegis-card">
            <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">应收清单</div></div>
            <div class="aegis-table-wrap">
                <table class="aegis-table aegis-t-a6" style="width:100%;">
                    <thead><tr><th>防伪码</th><th>出库时间</th><th>截止时间</th><th>是否已收</th><th>采样原因</th></tr></thead>
                    <tbody>
                        <?php if (empty($items)) : ?>
                            <tr><td colspan="5">暂无条目。</td></tr>
                        <?php else : ?>
                            <?php foreach ($items as $item) : ?>
                                <?php $normalized = AEGIS_System::normalize_code_value($item->code_value ?? ''); ?>
                                <tr>
                                    <td><?php echo esc_html(AEGIS_System::format_code_display($item->code_value ?? '')); ?></td>
                                    <td><?php echo esc_html($item->outbound_scanned_at ?? ''); ?></td>
                                    <td><?php echo esc_html($item->after_sales_deadline_at ?? ''); ?></td>
                                    <td><?php echo esc_html(!empty($matched_map[$normalized]) ? '已收' : '未收'); ?></td>
                                    <td><?php echo esc_html($format_sample_reason($item)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="aegis-card">
            <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">异常扫码明细</div></div>
            <div class="aegis-table-wrap">
                <table class="aegis-table aegis-t-a6" style="width:100%;">
                    <thead><tr><th>时间</th><th>码</th><th>结果</th><th>信息</th></tr></thead>
                    <tbody>
                        <?php if (empty($abnormal_scans)) : ?>
                            <tr><td colspan="4">暂无异常扫码。</td></tr>
                        <?php else : ?>
                            <?php foreach ($abnormal_scans as $scan_row) : ?>
                                <tr>
                                    <td><?php echo esc_html($scan_row->scanned_at ?? ''); ?></td>
                                    <td><?php echo esc_html(AEGIS_System::format_code_display($scan_row->code_value ?? '')); ?></td>
                                    <td><?php echo esc_html($scan_row->scan_result ?? ''); ?></td>
                                    <td><?php echo esc_html($scan_row->scan_message ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
