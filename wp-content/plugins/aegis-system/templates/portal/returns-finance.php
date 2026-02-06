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
?>

<div class="aegis-t-a4" style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
    <div class="aegis-t-a2">退货财务审核</div>
    <div style="display:flex; gap:8px; align-items:center;">
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

<form method="get" class="aegis-t-a6" style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
    <input type="hidden" name="m" value="returns" />
    <?php if ($is_hq) : ?>
        <input type="hidden" name="stage" value="finance" />
    <?php endif; ?>
    <label>
        状态
        <select name="status">
            <?php foreach ($status_options as $status_key => $status_name) : ?>
                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status_filter, $status_key); ?>><?php echo esc_html($status_name); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="aegis-portal-button is-primary">筛选</button>
</form>

<?php if ('list' === $view_mode) : ?>
    <div class="aegis-portal-table" style="overflow:auto;">
        <table class="aegis-t-a6" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="text-align:left; border-bottom:1px solid #e5e5e5;">
                    <th style="padding:8px;">单号</th>
                    <th style="padding:8px;">经销商</th>
                    <th style="padding:8px;">条目数</th>
                    <th style="padding:8px;">仓库通过时间</th>
                    <th style="padding:8px;">状态</th>
                    <th style="padding:8px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)) : ?>
                    <tr><td colspan="6" style="padding:12px;">暂无数据。</td></tr>
                <?php else : ?>
                    <?php foreach ($requests as $row) : ?>
                        <?php
                        $detail_url = add_query_arg(
                            [
                                'request_id' => (int) $row->id,
                                'status' => $status_filter,
                            ],
                            $base_url
                        );
                        ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:8px;"><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($row->request_no ?? ''); ?></a></td>
                            <td style="padding:8px;"><?php echo esc_html($row->dealer_name ?? '-'); ?></td>
                            <td style="padding:8px;"><?php echo esc_html((string) ($counts[(int) $row->id] ?? 0)); ?></td>
                            <td style="padding:8px;"><?php echo esc_html($row->warehouse_checked_at ?? ''); ?></td>
                            <td style="padding:8px;"><?php echo esc_html($status_label($row->status ?? '', $status_options)); ?></td>
                            <td style="padding:8px;"><a class="aegis-portal-button" href="<?php echo esc_url($detail_url); ?>">查看</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($request) : ?>
    <section class="aegis-card">
        <div class="aegis-card-header">
            <div class="aegis-card-title aegis-t-a4">单据详情</div>
        </div>
        <div class="aegis-card-body aegis-t-a6">
            <p><strong>单号：</strong><?php echo esc_html($request->request_no ?? ''); ?></p>
            <p><strong>经销商：</strong><?php echo esc_html($request->dealer_name ?? ''); ?></p>
            <p><strong>提交时间：</strong><?php echo esc_html($request->submitted_at ?? ''); ?></p>
            <p><strong>销售同意时间：</strong><?php echo esc_html($request->sales_audited_at ?? ''); ?></p>
            <p><strong>仓库通过时间：</strong><?php echo esc_html($request->warehouse_checked_at ?? ''); ?></p>
            <p><strong>当前状态：</strong><?php echo esc_html($status_label($request->status ?? '', $status_options)); ?></p>
            <?php if (!empty($request->finance_comment)) : ?>
                <p><strong>财务备注：</strong><?php echo esc_html($request->finance_comment); ?></p>
            <?php endif; ?>
            <?php if (!empty($request->finance_audited_at)) : ?>
                <p><strong>财务处理时间：</strong><?php echo esc_html($request->finance_audited_at); ?></p>
            <?php endif; ?>

            <div class="aegis-portal-table" style="overflow:auto; margin-top:12px;">
                <table class="aegis-t-a6" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid #e5e5e5;">
                            <th style="padding:8px;">防伪码</th>
                            <th style="padding:8px;">出库时间</th>
                            <th style="padding:8px;">截止时间</th>
                            <th style="padding:8px;">校验结果</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)) : ?>
                            <tr><td colspan="4" style="padding:12px;">暂无条目。</td></tr>
                        <?php else : ?>
                            <?php foreach ($items as $item) : ?>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:8px;"><?php echo esc_html(AEGIS_System::format_code_display($item->code_value ?? '')); ?></td>
                                    <td style="padding:8px;"><?php echo esc_html($item->outbound_scanned_at ?? ''); ?></td>
                                    <td style="padding:8px;"><?php echo esc_html($item->after_sales_deadline_at ?? ''); ?></td>
                                    <td style="padding:8px;"><?php echo esc_html($item->validation_status ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (AEGIS_Returns::STATUS_WAREHOUSE_APPROVED === ($request->status ?? '')) : ?>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px;">
                    <form method="post" class="aegis-t-a6" onsubmit="return confirm('确认批准结单？');">
                        <?php wp_nonce_field('aegis_returns_fin_action', 'aegis_returns_fin_nonce'); ?>
                        <input type="hidden" name="returns_action" value="finance_approve" />
                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                        <label style="display:block; margin-bottom:8px;">财务备注（可选）
                            <textarea name="finance_comment" rows="4" style="width:100%;"></textarea>
                        </label>
                        <button type="submit" class="aegis-portal-button is-primary">批准结单</button>
                    </form>

                    <form method="post" class="aegis-t-a6" onsubmit="return confirm('确认驳回该退货单？');">
                        <?php wp_nonce_field('aegis_returns_fin_action', 'aegis_returns_fin_nonce'); ?>
                        <input type="hidden" name="returns_action" value="finance_reject" />
                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                        <label style="display:block; margin-bottom:8px;">驳回原因（必填）
                            <textarea name="finance_comment" rows="4" style="width:100%;" required></textarea>
                        </label>
                        <button type="submit" class="aegis-portal-button">驳回</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
