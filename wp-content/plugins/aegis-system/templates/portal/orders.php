<?php
/** @var array $context */
$base_url = $context['base_url'];
$messages = $context['messages'];
$errors = $context['errors'];
$orders = $context['orders'];
$order = $context['order'];
$items = $context['items'];
$filters = $context['filters'];
$skus = $context['skus'];
$dealer = $context['dealer'];
$dealer_blocked = $context['dealer_blocked'];
$role_flags = $context['role_flags'];
?>
<div class="aegis-t-a4">
    <div class="aegis-t-a2" style="margin-bottom:12px;">订单</div>
    <p class="aegis-t-a6">模块默认关闭，需在模块管理启用后才向经销商展示。当前版本与出库未关联。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <?php if ($role_flags['is_dealer']) : ?>
        <div class="aegis-t-a5" style="border:1px solid #d9dce3; padding:12px; border-radius:8px; background:#f8f9fb; margin-bottom:16px;">
            <div class="aegis-t-a4" style="margin-bottom:8px;">新增订单</div>
            <?php if ($dealer_blocked) : ?>
                <p class="aegis-t-a6" style="color:#d63638;">经销商账号已停用或授权到期，暂不可下单。</p>
            <?php else : ?>
                <form method="post" class="aegis-t-a6" id="aegis-order-create-form">
                    <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                    <input type="hidden" name="order_action" value="create_order" />
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                    <div class="aegis-t-a6" style="margin-bottom:8px;">经销商：<?php echo esc_html($dealer ? $dealer->dealer_name : ''); ?></div>
                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">备注（可选）<br />
                        <input type="text" name="note" style="width:100%;" />
                    </label>
                    <div class="aegis-t-a6" style="margin-bottom:8px;">订单明细（SKU 必须处于启用状态）</div>
                    <div id="aegis-order-items" class="aegis-t-a6" style="display:flex; flex-direction:column; gap:8px;">
                        <div class="order-item-row" style="display:grid; grid-template-columns:2fr 1fr; gap:8px;">
                            <label class="aegis-t-a6">SKU
                                <input list="aegis-sku-list" name="order_item_ean[]" required />
                            </label>
                            <label class="aegis-t-a6">数量
                                <input type="number" name="order_item_qty[]" min="1" step="1" value="1" required />
                            </label>
                        </div>
                    </div>
                    <div style="margin:8px 0;">
                        <button type="button" class="button" id="add-order-item">新增一行</button>
                    </div>
                    <datalist id="aegis-sku-list">
                        <?php foreach ($skus as $sku) : ?>
                            <option value="<?php echo esc_attr($sku->ean); ?>"><?php echo esc_html($sku->ean . ' / ' . $sku->product_name); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                    <button type="submit" class="button button-primary">提交订单</button>
                </form>
                <script>
                    (function() {
                        const container = document.getElementById('aegis-order-items');
                        const addBtn = document.getElementById('add-order-item');
                        if (container && addBtn) {
                            addBtn.addEventListener('click', function() {
                                const row = document.createElement('div');
                                row.className = 'order-item-row';
                                row.style.display = 'grid';
                                row.style.gridTemplateColumns = '2fr 1fr';
                                row.style.gap = '8px';
                                row.innerHTML = '<label class="aegis-t-a6">SKU <input list="aegis-sku-list" name="order_item_ean[]" required /></label>' +
                                    '<label class="aegis-t-a6">数量 <input type="number" name="order_item_qty[]" min="1" step="1" value="1" required /></label>';
                                container.appendChild(row);
                            });
                        }
                    })();
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="aegis-t-a4" style="margin-top:8px;">订单列表</div>
    <form method="get" class="aegis-t-a6" style="margin:8px 0; display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
        <input type="hidden" name="m" value="orders" />
        <label>开始 <input type="date" name="start_date" value="<?php echo esc_attr($filters['start_date']); ?>" /></label>
        <label>结束 <input type="date" name="end_date" value="<?php echo esc_attr($filters['end_date']); ?>" /></label>
        <label>订单号 <input type="text" name="order_no" value="<?php echo esc_attr($filters['order_no']); ?>" /></label>
        <label>每页 <select name="per_page">
            <?php foreach ($filters['per_options'] as $opt) : ?>
                <option value="<?php echo esc_attr($opt); ?>" <?php selected($filters['per_page'], $opt); ?>><?php echo esc_html($opt); ?></option>
            <?php endforeach; ?>
        </select></label>
        <button type="submit" class="button">筛选</button>
    </form>

    <table class="aegis-table" style="width:100%;">
        <thead><tr><th>订单号</th><th>下单时间</th><th>状态</th><th>SKU 种类数</th><th>总数量</th><th>操作</th></tr></thead>
        <tbody>
            <?php if (empty($orders)) : ?>
                <tr><td colspan="6">暂无订单</td></tr>
            <?php else : ?>
                <?php foreach ($orders as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row->order_no); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td><?php echo esc_html($row->status); ?></td>
                        <td><?php echo esc_html((int) ($row->sku_count ?? 0)); ?></td>
                        <td><?php echo esc_html((int) ($row->total_qty ?? 0)); ?></td>
                        <td><a class="button" href="<?php echo esc_url(add_query_arg('order_id', $row->id, $base_url)); ?>">查看</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($filters['total_pages'] > 1) : ?>
        <div class="tablenav"><div class="tablenav-pages">
            <?php if ($filters['paged'] > 1) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(['paged' => $filters['paged'] - 1], $base_url)); ?>">上一页</a>
            <?php endif; ?>
            <span class="aegis-t-a6">第 <?php echo esc_html($filters['paged']); ?> / <?php echo esc_html($filters['total_pages']); ?> 页</span>
            <?php if ($filters['paged'] < $filters['total_pages']) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(['paged' => $filters['paged'] + 1], $base_url)); ?>">下一页</a>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>

    <?php if ($order) : ?>
        <div class="aegis-t-a5" style="margin-top:16px; border:1px solid #d9dce3; padding:12px; border-radius:8px;">
            <div class="aegis-t-a4" style="margin-bottom:8px;">订单详情</div>
            <div class="aegis-t-a6">订单号：<?php echo esc_html($order->order_no); ?> | 状态：<?php echo esc_html($order->status); ?></div>
            <div class="aegis-t-a6" style="margin-top:4px;">下单时间：<?php echo esc_html($order->created_at); ?></div>
            <div class="aegis-t-a6" style="margin-top:4px;">经销商：<?php echo esc_html($order->snapshot_dealer_name ?: $order->dealer_id); ?></div>
            <?php if ($order->confirmed_at) : ?>
                <div class="aegis-t-a6" style="margin-top:4px;">确认时间：<?php echo esc_html($order->confirmed_at); ?></div>
            <?php endif; ?>
            <?php if (!empty($order->note)) : ?>
                <div class="aegis-t-a6" style="margin-top:4px;">备注：<?php echo esc_html($order->note); ?></div>
            <?php endif; ?>

            <table class="aegis-table" style="width:100%; margin-top:10px;">
                <thead><tr><th>EAN</th><th>产品名</th><th>数量</th></tr></thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="3">暂无明细</td></tr>
                    <?php else : ?>
                        <?php foreach ($items as $line) : ?>
                            <tr>
                                <td><?php echo esc_html($line->ean); ?></td>
                                <td><?php echo esc_html($line->product_name_snapshot); ?></td>
                                <td><?php echo esc_html((int) $line->quantity); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($role_flags['can_manage'] && !$role_flags['staff_readonly'] && in_array($order->status, ['submitted', 'confirmed'], true)) : ?>
                <form method="post" style="margin-top:10px; display:flex; gap:8px; align-items:center;">
                    <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                    <input type="hidden" name="order_action" value="change_status" />
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                    <?php if ('submitted' === $order->status) : ?>
                        <button class="button button-primary" name="target_status" value="confirmed" type="submit">确认订单</button>
                    <?php endif; ?>
                    <?php if (in_array($order->status, ['submitted', 'confirmed'], true)) : ?>
                        <button class="button" name="target_status" value="cancelled" type="submit">取消订单</button>
                    <?php endif; ?>
                    <?php if ('confirmed' === $order->status) : ?>
                        <button class="button" name="target_status" value="closed" type="submit">关闭订单</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
