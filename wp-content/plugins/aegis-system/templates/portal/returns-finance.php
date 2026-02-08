<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url = $context_data['base_url'] ?? '';
$messages = $context_data['messages'] ?? [];
$errors = $context_data['errors'] ?? [];
$status_filter = $context_data['status_filter'] ?? AEGIS_Returns::STATUS_WAREHOUSE_APPROVED;
$status_options = $context_data['status_options'] ?? [];
$is_hq = !empty($context_data['is_hq']);
$view_mode = $context_data['view_mode'] ?? 'list';
$requests = $context_data['requests'] ?? [];
$counts = $context_data['counts'] ?? [];
$request = $context_data['request'] ?? null;
$items = $context_data['items'] ?? [];
$idempotency = $context_data['idempotency'] ?? wp_generate_uuid4();

$list_url = add_query_arg(['status' => $status_filter], $base_url);
$status_label = static function ($status, $options) {
    return $options[$status] ?? $status;
};

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
        <div class="aegis-t-a2">退货财务审核</div>
        <div class="aegis-returns-header-actions">
            <?php if ($is_hq) : ?>
                <?php
                $sales_url = add_query_arg('stage', 'sales', add_query_arg('m', 'returns', remove_query_arg(['request_id'], $base_url)));
                $finance_url = add_query_arg('stage', 'finance', add_query_arg('m', 'returns', remove_query_arg(['request_id'], $base_url)));
                ?>
                <a class="aegis-portal-button" href="<?php echo esc_url($sales_url); ?>">切换到销售视图</a>
                <a class="aegis-portal-button is-primary" href="<?php echo esc_url($finance_url); ?>">财务视图</a>
            <?php endif; ?>
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
            <?php if ($is_hq) : ?><input type="hidden" name="stage" value="finance" /><?php endif; ?>
            <label>状态
                <select name="status">
                    <?php foreach ($status_options as $status_key => $status_name) : ?>
                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>><?php echo esc_html($status_name); ?></option>
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
                    <thead><tr><th>单号</th><th>经销商</th><th>条目数</th><th>仓库通过时间</th><th>状态</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php if (empty($requests)) : ?>
                            <tr><td colspan="6">暂无数据。</td></tr>
                        <?php else : ?>
                            <?php foreach ($requests as $row) : ?>
                                <?php $detail_url = add_query_arg(['request_id' => (int) $row->id, 'status' => $status_filter], $base_url); ?>
                                <tr>
                                    <td><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($row->request_no ?? ''); ?></a></td>
                                    <td><?php echo esc_html($row->dealer_name ?? '-'); ?></td>
                                    <td><?php echo esc_html((string) ($counts[(int) $row->id] ?? 0)); ?></td>
                                    <td><?php echo esc_html($row->warehouse_checked_at ?? ''); ?></td>
                                    <td><?php echo esc_html($status_label($row->status ?? '', $status_options)); ?></td>
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
            <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">单据详情</div></div>
            <div class="aegis-card-body aegis-t-a6">
                <p><strong>单号：</strong><?php echo esc_html($request->request_no ?? ''); ?></p>
                <p><strong>经销商：</strong><?php echo esc_html($request->dealer_name ?? ''); ?></p>
                <p><strong>提交时间：</strong><?php echo esc_html($request->submitted_at ?? ''); ?></p>
                <p><strong>销售同意时间：</strong><?php echo esc_html($request->sales_audited_at ?? ''); ?></p>
                <p><strong>仓库通过时间：</strong><?php echo esc_html($request->warehouse_checked_at ?? ''); ?></p>
                <p><strong>当前状态：</strong><?php echo esc_html($status_label($request->status ?? '', $status_options)); ?></p>
                <?php if (!empty($request->finance_comment)) : ?><p><strong>财务备注：</strong><?php echo esc_html($request->finance_comment); ?></p><?php endif; ?>
                <?php if (!empty($request->finance_audited_at)) : ?><p><strong>财务处理时间：</strong><?php echo esc_html($request->finance_audited_at); ?></p><?php endif; ?>
            </div>
        </section>

        <section class="aegis-card">
            <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">条目列表</div></div>
            <div class="aegis-table-wrap">
                <table class="aegis-table aegis-returns-results-table aegis-t-a6" style="width:100%;">
                    <thead><tr><th>防伪码</th><th>出库时间</th><th>截止时间</th><th>校验结果</th><th>采样原因</th></tr></thead>
                    <tbody>
                        <?php if (empty($items)) : ?>
                            <tr><td colspan="5">暂无条目。</td></tr>
                        <?php else : ?>
                            <?php foreach ($items as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html(AEGIS_System::format_code_display($item->code_value ?? '')); ?></td>
                                    <td><?php echo esc_html($item->outbound_scanned_at ?? ''); ?></td>
                                    <td><?php echo esc_html($item->after_sales_deadline_at ?? ''); ?></td>
                                    <td><?php echo esc_html($item->validation_status ?? ''); ?></td>
                                    <td><?php echo esc_html($format_sample_reason($item)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (AEGIS_Returns::STATUS_WAREHOUSE_APPROVED === ($request->status ?? '')) : ?>
                <div class="aegis-card-body" style="padding-top:12px;">
                    <div style="display:grid; gap:12px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">
                        <form method="post" class="aegis-t-a6" onsubmit="return confirm('确认批准结单？');">
                            <?php wp_nonce_field('aegis_returns_fin_action', 'aegis_returns_fin_nonce'); ?>
                            <input type="hidden" name="returns_action" value="finance_approve" />
                            <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                            <label style="display:block; margin-bottom:8px;">财务备注（可选）<textarea name="finance_comment" rows="4" style="width:100%;"></textarea></label>
                            <button type="submit" class="aegis-portal-button is-primary">批准结单</button>
                        </form>
                        <form method="post" class="aegis-t-a6" onsubmit="return confirm('确认驳回该退货单？');">
                            <?php wp_nonce_field('aegis_returns_fin_action', 'aegis_returns_fin_nonce'); ?>
                            <input type="hidden" name="returns_action" value="finance_reject" />
                            <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                            <label style="display:block; margin-bottom:8px;">驳回原因（必填）<textarea name="finance_comment" rows="4" style="width:100%;" required></textarea></label>
                            <button type="submit" class="aegis-portal-button">驳回</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
