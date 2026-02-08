<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url = $context_data['base_url'] ?? '';
$messages = $context_data['messages'] ?? [];
$errors = $context_data['errors'] ?? [];
$status_filter = $context_data['status_filter'] ?? AEGIS_Returns::STATUS_SUBMITTED;
$status_options = $context_data['status_options'] ?? [];
$view_mode = $context_data['view_mode'] ?? 'list';
$requests = $context_data['requests'] ?? [];
$counts = $context_data['counts'] ?? [];
$request = $context_data['request'] ?? null;
$items = $context_data['items'] ?? [];
$idempotency = $context_data['idempotency'] ?? wp_generate_uuid4();
$is_hq = !empty($context_data['is_hq']);

$list_url = add_query_arg(['status' => $status_filter], $base_url);
?>

<div class="aegis-t-a4 aegis-returns-page">
    <div class="aegis-returns-header">
        <div class="aegis-t-a2">退货审核</div>
        <div class="aegis-returns-header-actions">
            <?php if ($is_hq) : ?>
                <a class="aegis-portal-button" href="<?php echo esc_url(add_query_arg(['m' => 'returns', 'stage' => 'sales'], $base_url)); ?>">销售审核</a>
                <a class="aegis-portal-button" href="<?php echo esc_url(add_query_arg(['m' => 'returns', 'stage' => 'override'], $base_url)); ?>">特批码管理</a>
                <a class="aegis-portal-button" href="<?php echo esc_url(add_query_arg(['m' => 'returns', 'stage' => 'finance'], $base_url)); ?>">财务审核</a>
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
            <label>状态
                <select name="status">
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
                    <thead>
                        <tr>
                            <th>单号</th>
                            <th>经销商</th>
                            <th>条目数</th>
                            <th>提交时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)) : ?>
                            <tr><td colspan="6">暂无数据。</td></tr>
                        <?php else : ?>
                            <?php foreach ($requests as $row) : ?>
                                <?php $detail_url = add_query_arg(['request_id' => (int) $row->id, 'status' => $status_filter], $base_url); ?>
                                <tr>
                                    <td><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($row->request_no); ?></a></td>
                                    <td><?php echo esc_html(($row->dealer_name ?? '-') . ' (#' . (int) $row->dealer_id . ')'); ?></td>
                                    <td><?php echo esc_html((string) ($counts[(int) $row->id] ?? 0)); ?></td>
                                    <td><?php echo esc_html($row->submitted_at ?? ''); ?></td>
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
            <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">单据详情</div></div>
            <div class="aegis-card-body aegis-t-a6">
                <p><strong>单号：</strong><?php echo esc_html($request->request_no ?? ''); ?></p>
                <p><strong>经销商：</strong><?php echo esc_html($request->dealer_name ?? ''); ?></p>
                <p><strong>联系人：</strong><?php echo esc_html($request->contact_name ?? ''); ?> / <?php echo esc_html($request->contact_phone ?? ''); ?></p>
                <p><strong>退货原因：</strong><?php echo esc_html($request->reason_code ?? ''); ?></p>
                <p><strong>备注：</strong><?php echo esc_html($request->remark ?? ''); ?></p>
                <p><strong>提交时间：</strong><?php echo esc_html($request->submitted_at ?? ''); ?></p>
            </div>
        </section>

        <section class="aegis-card">
            <div class="aegis-card-header"><div class="aegis-card-title aegis-t-a4">条目列表</div></div>
            <div class="aegis-table-wrap">
                <table class="aegis-table aegis-returns-results-table aegis-t-a6" style="width:100%;">
                    <thead>
                        <tr>
                            <th>防伪码</th>
                            <th>出库时间</th>
                            <th>截止时间</th>
                            <th>校验结果</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)) : ?>
                            <tr><td colspan="4">暂无条目。</td></tr>
                        <?php else : ?>
                            <?php foreach ($items as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html(AEGIS_System::format_code_display($item->code_value ?? '')); ?></td>
                                    <td><?php echo esc_html($item->outbound_scanned_at ?? ''); ?></td>
                                    <td><?php echo esc_html($item->after_sales_deadline_at ?? ''); ?></td>
                                    <td><?php echo esc_html($item->validation_status ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (AEGIS_Returns::STATUS_SUBMITTED === ($request->status ?? '')) : ?>
                <div class="aegis-card-body" style="padding-top:12px;">
                    <div style="display:grid; gap:12px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">
                        <form method="post" class="aegis-t-a6" onsubmit="return confirm('确认同意该退货单？');">
                            <?php wp_nonce_field('aegis_returns_sales_action', 'aegis_returns_sales_nonce'); ?>
                            <input type="hidden" name="returns_action" value="sales_approve" />
                            <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                            <label style="display:block; margin-bottom:8px;">审核备注（可选）
                                <textarea name="sales_comment" rows="4" style="width:100%;"></textarea>
                            </label>
                            <button type="submit" class="aegis-portal-button is-primary">同意</button>
                        </form>
                        <form method="post" class="aegis-t-a6" onsubmit="return confirm('确认驳回该退货单？');">
                            <?php wp_nonce_field('aegis_returns_sales_action', 'aegis_returns_sales_nonce'); ?>
                            <input type="hidden" name="returns_action" value="sales_reject" />
                            <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                            <label style="display:block; margin-bottom:8px;">驳回原因（必填）
                                <textarea name="sales_comment" rows="4" style="width:100%;" required></textarea>
                            </label>
                            <button type="submit" class="aegis-portal-button">驳回</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
