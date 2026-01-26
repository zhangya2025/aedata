<?php
/** @var array $context */
$base_url = $context['base_url'];
$messages = $context['messages'];
$errors = $context['errors'];
$orders = $context['orders'];
$order = $context['order'];
$items = $context['items'];
$payment = $context['payment'];
$filters = $context['filters'];
$skus = $context['skus'];
$dealer = $context['dealer'];
$dealer_blocked = $context['dealer_blocked'];
$role_flags = $context['role_flags'];
$price_map = $context['price_map'];
$view_mode = $context['view_mode'];
$queue_mode = $context['queue_mode'];
$status_labels = $context['status_labels'];
$payment_status_labels = [
    'none'      => '未提交',
    'submitted' => '已提交，待审核',
    'approved'  => '已通过',
    'rejected'  => '已驳回',
    'need_more' => '需补充',
];
?>
<div class="aegis-t-a4">
    <div class="aegis-t-a2" style="margin-bottom:12px;">订单</div>
    <p class="aegis-t-a6">当前流程：经销商下单 → 待初审（HQ删减匹配）→ 待确认（上传付款凭证）→ 待审核 → 已通过（待出库）/撤销/作废。价格来自等级/覆盖价快照，不影响其他系统。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <?php if ($role_flags['can_manage_all'] || ($role_flags['can_initial_review'] && $role_flags['can_payment_review'])) : ?>
        <div class="aegis-t-a6" style="margin:8px 0; display:flex; gap:8px; align-items:center;">
            <span>视图：</span>
            <?php $review_url = add_query_arg(['view' => 'review'], $base_url); ?>
            <?php $payment_review_url = add_query_arg(['view' => 'payment_review'], $base_url); ?>
            <?php $list_url = add_query_arg(['view' => 'list'], $base_url); ?>
            <a class="button <?php echo $view_mode === 'review' ? 'button-primary' : ''; ?>" href="<?php echo esc_url($review_url); ?>">待初审队列</a>
            <a class="button <?php echo $view_mode === 'payment_review' ? 'button-primary' : ''; ?>" href="<?php echo esc_url($payment_review_url); ?>">待审核队列</a>
            <a class="button <?php echo $view_mode === 'list' ? 'button-primary' : ''; ?>" href="<?php echo esc_url($list_url); ?>">全部订单</a>
        </div>
    <?php endif; ?>

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
                    <div class="aegis-t-a6" style="margin-bottom:8px;">订单明细（价格自动带出，不可手改）</div>
                    <div id="aegis-order-items" class="aegis-t-a6" style="display:flex; flex-direction:column; gap:8px;">
                        <div class="order-item-row" style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:8px; align-items:end;">
                            <label class="aegis-t-a6">SKU
                                <input list="aegis-sku-list" name="order_item_ean[]" required />
                            </label>
                            <label class="aegis-t-a6">数量
                                <input type="number" name="order_item_qty[]" min="1" step="1" value="1" required />
                            </label>
                            <div class="aegis-t-a6">单价
                                <div class="aegis-t-a6 order-item-price" style="font-weight:bold;">-</div>
                            </div>
                        </div>
                    </div>
                    <div style="margin:8px 0; display:flex; gap:8px;">
                        <button type="button" class="button" id="add-order-item">新增一行</button>
                        <button type="button" class="button" id="remove-order-item">删除末行</button>
                    </div>
                    <datalist id="aegis-sku-list">
                        <?php foreach ($skus as $sku) : ?>
                            <option value="<?php echo esc_attr($sku->ean); ?>"><?php echo esc_html($sku->ean . ' / ' . $sku->product_name); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                    <button type="submit" class="button button-primary">提交订单</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="aegis-t-a4" style="margin-top:8px;">
        <?php
        if ($queue_mode === 'review') {
            echo '待初审订单队列';
        } elseif ($queue_mode === 'payment_review') {
            echo '待审核订单队列';
        } else {
            echo '订单列表';
        }
        ?>
    </div>
    <form method="get" class="aegis-t-a6" style="margin:8px 0; display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
        <input type="hidden" name="m" value="orders" />
        <?php if (!empty($filters['dealer_id'])) : ?>
            <input type="hidden" name="dealer_id" value="<?php echo esc_attr($filters['dealer_id']); ?>" />
        <?php endif; ?>
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

    <?php $table_colspan = $role_flags['can_view_all'] ? ($queue_mode === 'payment_review' ? 10 : 9) : 6; ?>
    <table class="aegis-table" style="width:100%;">
        <thead><tr>
            <th>订单号</th>
            <?php if ($role_flags['can_view_all']) : ?><th>经销商</th><?php endif; ?>
            <th>下单时间</th>
            <?php if ($queue_mode === 'payment_review') : ?><th>提交确认时间</th><?php endif; ?>
            <th>状态</th>
            <?php if ($role_flags['can_view_all']) : ?><th>付款状态</th><?php endif; ?>
            <th>SKU 种类数</th>
            <th>总数量</th>
            <?php if ($role_flags['can_view_all']) : ?><th>金额</th><?php endif; ?>
            <th>操作</th></tr></thead>
        <tbody>
            <?php if (empty($orders)) : ?>
                <tr><td colspan="<?php echo esc_attr($table_colspan); ?>">暂无订单</td></tr>
            <?php else : ?>
                <?php foreach ($orders as $row) : ?>
                    <?php $status_text = $status_labels[$row->status] ?? $row->status; ?>
                    <?php $row_link = add_query_arg(['order_id' => $row->id], $base_url); ?>
                    <?php if ($role_flags['queue_view']) { $row_link = add_query_arg(['view' => $view_mode, 'order_id' => $row->id], $base_url); } ?>
                    <?php $payment_state_text = $row->payment_status && isset($payment_status_labels[$row->payment_status]) ? $payment_status_labels[$row->payment_status] : '-'; ?>
                    <tr>
                        <td><?php echo esc_html($row->order_no); ?></td>
                        <?php if ($role_flags['can_view_all']) : ?><td><?php echo esc_html($row->dealer_name ?? ''); ?></td><?php endif; ?>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <?php if ($queue_mode === 'payment_review') : ?><td><?php echo esc_html($row->payment_submitted_at ?? '-'); ?></td><?php endif; ?>
                        <td><?php echo esc_html($status_text); ?></td>
                        <?php if ($role_flags['can_view_all']) : ?><td><?php echo esc_html($payment_state_text); ?></td><?php endif; ?>
                        <td><?php echo esc_html((int) ($row->sku_count ?? 0)); ?></td>
                        <td><?php echo esc_html((int) ($row->total_qty ?? 0)); ?></td>
                        <?php if ($role_flags['can_view_all']) : ?><td><?php echo esc_html('¥' . number_format((float) ($row->total_amount ?? 0), 2)); ?></td><?php endif; ?>
                        <td>
                            <a class="button" href="<?php echo esc_url($row_link); ?>">查看</a>
                            <?php if ($role_flags['is_dealer'] && $row->status === 'pending_initial_review') : ?>
                                <a class="button" href="<?php echo esc_url(add_query_arg('order_id', $row->id, $base_url)); ?>#order-edit">编辑</a>
                            <?php elseif ($role_flags['can_initial_review'] && $row->status === 'pending_initial_review') : ?>
                                <a class="button button-primary" href="<?php echo esc_url(add_query_arg(['view' => 'review', 'order_id' => $row->id], $base_url)); ?>">审核</a>
                            <?php elseif ($role_flags['can_payment_review'] && $row->status === 'pending_hq_payment_review') : ?>
                                <a class="button button-primary" href="<?php echo esc_url(add_query_arg(['view' => 'payment_review', 'order_id' => $row->id], $base_url)); ?>">审核付款</a>
                            <?php endif; ?>
                        </td>
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
        <div class="aegis-t-a5" style="margin-top:16px; border:1px solid #d9dce3; padding:12px; border-radius:8px;" id="order-detail">
            <div class="aegis-t-a4" style="margin-bottom:8px;">订单详情</div>
            <?php $status_text = $status_labels[$order->status] ?? $order->status; ?>
            <div class="aegis-t-a6">订单号：<?php echo esc_html($order->order_no); ?> | 状态：<?php echo esc_html($status_text); ?></div>
            <div class="aegis-t-a6" style="margin-top:4px;">下单时间：<?php echo esc_html($order->created_at); ?></div>
            <div class="aegis-t-a6" style="margin-top:4px;">经销商：<?php echo esc_html($order->dealer_name_snapshot ?: $order->dealer_id); ?></div>
            <?php if (!empty($order->note)) : ?>
                <div class="aegis-t-a6" style="margin-top:4px;">备注：<?php echo esc_html($order->note); ?></div>
            <?php endif; ?>
            <?php if (!empty($order->review_note)) : ?>
                <div class="aegis-t-a6" style="margin-top:4px;">初审备注：<?php echo esc_html($order->review_note); ?></div>
            <?php endif; ?>
            <?php if (!empty($order->void_reason)) : ?>
                <div class="aegis-t-a6" style="margin-top:4px;">作废原因：<?php echo esc_html($order->void_reason); ?></div>
            <?php endif; ?>

            <table class="aegis-table" style="width:100%; margin-top:10px;">
                <thead><tr><th>EAN</th><th>产品名</th><th>数量</th><th>单价快照</th><th>价格来源</th><th>等级快照</th></tr></thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="6">暂无明细</td></tr>
                    <?php else : ?>
                        <?php foreach ($items as $line) : ?>
                            <tr>
                                <td><?php echo esc_html($line->ean); ?></td>
                                <td><?php echo esc_html($line->product_name_snapshot); ?></td>
                                <td><?php echo esc_html((int) $line->qty); ?></td>
                                <td><?php echo esc_html(number_format((float) $line->unit_price_snapshot, 2)); ?></td>
                                <td><?php echo esc_html($line->price_source); ?></td>
                                <td><?php echo esc_html($line->price_level_snapshot); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="aegis-t-a6" style="margin-top:12px; padding-top:8px; border-top:1px solid #d9dce3;">
                <div class="aegis-t-a5" style="margin-bottom:8px;">付款凭证</div>
                <?php $has_payment = $payment && !empty($payment->media_id); ?>
                <?php $payment_url = $has_payment ? AEGIS_Orders::get_media_gateway_url($payment->media_id) : ''; ?>
                <?php $payment_status_text = $payment && isset($payment_status_labels[$payment->status]) ? $payment_status_labels[$payment->status] : ($has_payment ? '已上传' : '未上传'); ?>
                <?php if ($order->status === 'pending_initial_review') : ?>
                    <p class="aegis-t-a6" style="color:#6b7280;">等待 HQ 初审通过后可上传付款凭证。</p>
                <?php elseif ($role_flags['is_dealer'] && $order->status === 'pending_dealer_confirm') : ?>
                    <?php if ($dealer_blocked) : ?>
                        <p class="aegis-t-a6" style="color:#d63638;">经销商账号当前不可操作，请联系管理员。</p>
                    <?php else : ?>
                        <?php if ($payment && $payment->status === 'rejected' && !empty($payment->review_note)) : ?>
                            <p class="aegis-t-a6" style="color:#d63638;">上次审核驳回原因：<?php echo esc_html($payment->review_note); ?></p>
                        <?php endif; ?>
                        <form method="post" enctype="multipart/form-data" class="aegis-t-a6" style="margin-bottom:8px;">
                            <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                            <input type="hidden" name="order_action" value="upload_payment" />
                            <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                            <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">选择付款凭证（图片或 PDF）<br />
                                <input type="file" name="payment_file" accept="image/*,.pdf" required />
                            </label>
                            <button type="submit" class="button">上传凭证</button>
                            <?php if ($has_payment) : ?>
                                <span class="aegis-t-a6" style="margin-left:8px;">当前：<a href="<?php echo esc_url($payment_url); ?>" target="_blank">查看凭证</a>（<?php echo esc_html($payment_status_text); ?>）</span>
                            <?php endif; ?>
                        </form>
                        <form method="post" class="aegis-t-a6">
                            <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                            <input type="hidden" name="order_action" value="submit_payment" />
                            <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                            <button type="submit" class="button button-primary" <?php echo $has_payment ? '' : 'disabled'; ?>>提交确认（待审核）</button>
                            <?php if (!$has_payment) : ?>
                                <span class="aegis-t-a6" style="margin-left:8px; color:#d63638;">请先上传凭证后再提交。</span>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                <?php else : ?>
                    <?php if ($has_payment) : ?>
                        <p class="aegis-t-a6" style="margin-bottom:4px;">凭证状态：<?php echo esc_html($payment_status_text); ?> <?php if ($payment_url) : ?><a class="button" href="<?php echo esc_url($payment_url); ?>" target="_blank">查看凭证</a><?php endif; ?></p>
                    <?php else : ?>
                        <p class="aegis-t-a6" style="color:#6b7280;">暂无付款凭证。</p>
                    <?php endif; ?>
                    <?php if ($payment && $payment->status === 'rejected' && !empty($payment->review_note)) : ?>
                        <p class="aegis-t-a6" style="color:#d63638;">驳回原因：<?php echo esc_html($payment->review_note); ?></p>
                    <?php endif; ?>
                    <?php if ($order->status === 'pending_hq_payment_review') : ?>
                        <p class="aegis-t-a6" style="color:#6b7280;">已提交确认，等待审核。</p>
                    <?php elseif ($order->status === 'approved_pending_fulfillment') : ?>
                        <p class="aegis-t-a6" style="color:#15803d;">付款审核已通过，等待出库。</p>
                    <?php elseif ($order->status === 'voided_by_hq' || $order->status === 'cancelled_by_dealer') : ?>
                        <p class="aegis-t-a6" style="color:#6b7280;">订单已终止，凭证仅供查看。</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($role_flags['can_payment_review'] && $order->status === 'pending_hq_payment_review') : ?>
                <div class="aegis-t-a6" style="margin-top:12px; padding-top:8px; border-top:1px solid #d9dce3;">
                    <div class="aegis-t-a5" style="margin-bottom:8px;">付款审核</div>
                    <form method="post" class="aegis-t-a6" style="margin-bottom:8px;">
                        <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                        <input type="hidden" name="order_action" value="review_payment" />
                        <input type="hidden" name="decision" value="approve" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                        <button type="submit" class="button button-primary">审核通过（待出库）</button>
                    </form>
                    <form method="post" class="aegis-t-a6">
                        <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                        <input type="hidden" name="order_action" value="review_payment" />
                        <input type="hidden" name="decision" value="reject" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                        <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">驳回原因（必填）<br />
                            <input type="text" name="review_note" required style="width:100%;" />
                        </label>
                        <button type="submit" class="button" onclick="return confirm('确认驳回并退回经销商重新提交吗？');">驳回并退回经销商</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($role_flags['can_initial_review'] && $order->status === 'pending_initial_review') : ?>
                <div class="aegis-t-a6" style="margin-top:12px; padding-top:8px; border-top:1px solid #d9dce3;">
                    <div class="aegis-t-a5" style="margin-bottom:8px;">HQ 初审（可删减/下调数量，不改价）</div>
                    <form method="post" id="aegis-order-review-form">
                        <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                        <input type="hidden" name="order_action" value="review_order" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                        <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">初审备注（可选）<br />
                            <input type="text" name="review_note" value="<?php echo esc_attr($order->review_note ?? ''); ?>" style="width:100%;" />
                        </label>
                        <div id="aegis-order-review-items" class="aegis-t-a6" style="display:flex; flex-direction:column; gap:8px;">
                            <?php foreach ($items as $line) : ?>
                                <div class="order-review-row" style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:8px; align-items:end;">
                                    <label class="aegis-t-a6">SKU
                                        <input type="text" name="order_item_ean[]" value="<?php echo esc_attr($line->ean); ?>" readonly />
                                    </label>
                                    <label class="aegis-t-a6">数量（可下调）
                                        <input type="number" name="order_item_qty[]" min="1" max="<?php echo esc_attr((int) $line->qty); ?>" step="1" value="<?php echo esc_attr((int) $line->qty); ?>" required />
                                        <span class="aegis-t-a6" style="display:block; color:#6b7280;">当前最大：<?php echo esc_html((int) $line->qty); ?></span>
                                    </label>
                                    <div class="aegis-t-a6">单价快照
                                        <div class="aegis-t-a6" style="font-weight:bold;">¥<?php echo esc_html(number_format((float) $line->unit_price_snapshot, 2)); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin:8px 0; display:flex; gap:8px;">
                            <button type="button" class="button" id="review-remove-order-item">删除末行</button>
                        </div>
                        <button type="submit" class="button button-primary">匹配通过（待确认）</button>
                    </form>
                    <form method="post" style="margin-top:8px;" onsubmit="return confirm('确认作废该订单吗？作废后不可恢复。');">
                        <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                        <input type="hidden" name="order_action" value="void_order" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                        <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">作废原因（可选）<br />
                            <input type="text" name="void_reason" style="width:100%;" />
                        </label>
                        <button type="submit" class="button">作废订单</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($role_flags['can_manage_all'] && in_array($order->status, ['pending_dealer_confirm', 'pending_hq_payment_review'], true)) : ?>
                <form method="post" class="aegis-t-a6" style="margin-top:12px;" onsubmit="return confirm('确认作废该订单吗？作废后不可恢复。');">
                    <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                    <input type="hidden" name="order_action" value="void_order" />
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">作废原因（可选）<br />
                        <input type="text" name="void_reason" style="width:100%;" />
                    </label>
                    <button type="submit" class="button">删除/作废订单</button>
                </form>
            <?php endif; ?>

            <?php if ($role_flags['is_dealer'] && $order->status === 'pending_initial_review') : ?>
                <div id="order-edit" class="aegis-t-a6" style="margin-top:12px; padding-top:8px; border-top:1px solid #d9dce3;">
                    <div class="aegis-t-a5" style="margin-bottom:8px;">编辑订单（待初审可编辑）</div>
                    <form method="post" id="aegis-order-edit-form">
                        <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                        <input type="hidden" name="order_action" value="update_order" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                        <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">备注（可选）<br />
                            <input type="text" name="note" value="<?php echo esc_attr($order->note); ?>" style="width:100%;" />
                        </label>
                        <div id="aegis-order-edit-items" class="aegis-t-a6" style="display:flex; flex-direction:column; gap:8px;">
                            <?php if (!empty($items)) : ?>
                                <?php foreach ($items as $line) : ?>
                                    <div class="order-item-row" style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:8px; align-items:end;">
                                        <label class="aegis-t-a6">SKU
                                            <input list="aegis-sku-list" name="order_item_ean[]" value="<?php echo esc_attr($line->ean); ?>" required />
                                        </label>
                                        <label class="aegis-t-a6">数量
                                            <input type="number" name="order_item_qty[]" min="1" step="1" value="<?php echo esc_attr((int) $line->qty); ?>" required />
                                        </label>
                                        <div class="aegis-t-a6">单价
                                            <div class="aegis-t-a6 order-item-price" style="font-weight:bold;">-</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div style="margin:8px 0; display:flex; gap:8px;">
                            <button type="button" class="button" id="edit-add-order-item">新增一行</button>
                            <button type="button" class="button" id="edit-remove-order-item">删除末行</button>
                        </div>
                        <button type="submit" class="button button-primary">保存修改</button>
                    </form>
                    <form method="post" style="margin-top:8px;" onsubmit="return confirm('确认撤销该订单吗？撤销后不可再编辑。');">
                        <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                        <input type="hidden" name="order_action" value="cancel_order" />
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                        <button type="submit" class="button">撤销订单</button>
                    </form>
                </div>
            <?php elseif ($role_flags['is_dealer'] && $order->status === 'pending_dealer_confirm') : ?>
                <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">订单已由 HQ 调整并待确认，当前内容只读。</p>
            <?php elseif ($role_flags['is_dealer'] && $order->status === 'pending_hq_payment_review') : ?>
                <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">已提交付款凭证，等待审核，当前内容只读。</p>
            <?php elseif ($role_flags['is_dealer'] && $order->status === 'approved_pending_fulfillment') : ?>
                <p class="aegis-t-a6" style="margin-top:8px; color:#15803d;">付款已通过，等待出库，内容只读。</p>
            <?php elseif ($order->status === 'cancelled_by_dealer') : ?>
                <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">订单已撤销，明细仅供查看。</p>
            <?php elseif ($order->status === 'voided_by_hq') : ?>
                <p class="aegis-t-a6" style="margin-top:8px; color:#d63638;">订单已作废，无法继续操作。</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($role_flags['is_dealer'] && !empty($price_map)) : ?>
<script>
(function() {
    const priceMap = <?php echo wp_json_encode($price_map); ?>;
    function updateRowPrice(row) {
        const input = row.querySelector('input[name="order_item_ean[]"]');
        const priceBox = row.querySelector('.order-item-price');
        if (!input || !priceBox) return;
        const val = input.value;
        if (priceMap[val]) {
            priceBox.textContent = priceMap[val].label;
            priceBox.style.color = '';
        } else {
            priceBox.textContent = '无价，禁止下单';
            priceBox.style.color = '#d63638';
        }
    }
    function attach(row) {
        const input = row.querySelector('input[name="order_item_ean[]"]');
        if (input) {
            input.addEventListener('change', function() { updateRowPrice(row); });
            input.addEventListener('blur', function() { updateRowPrice(row); });
        }
        updateRowPrice(row);
    }

    function addRow(container) {
        const row = document.createElement('div');
        row.className = 'order-item-row';
        row.style.display = 'grid';
        row.style.gridTemplateColumns = '2fr 1fr 1fr';
        row.style.gap = '8px';
        row.style.alignItems = 'end';
        row.innerHTML = '<label class="aegis-t-a6">SKU <input list="aegis-sku-list" name="order_item_ean[]" required /></label>' +
            '<label class="aegis-t-a6">数量 <input type="number" name="order_item_qty[]" min="1" step="1" value="1" required /></label>' +
            '<div class="aegis-t-a6">单价<div class="aegis-t-a6 order-item-price" style="font-weight:bold;">-</div></div>';
        container.appendChild(row);
        attach(row);
    }

    function removeLast(container) {
        if (container.children.length > 1) {
            container.removeChild(container.lastElementChild);
        }
    }

    const createContainer = document.getElementById('aegis-order-items');
    if (createContainer) {
        attach(createContainer.querySelector('.order-item-row'));
        const addBtn = document.getElementById('add-order-item');
        const removeBtn = document.getElementById('remove-order-item');
        if (addBtn) addBtn.addEventListener('click', function() { addRow(createContainer); });
        if (removeBtn) removeBtn.addEventListener('click', function() { removeLast(createContainer); });
    }

    const editContainer = document.getElementById('aegis-order-edit-items');
    if (editContainer) {
        editContainer.querySelectorAll('.order-item-row').forEach(attach);
        const addBtn = document.getElementById('edit-add-order-item');
        const removeBtn = document.getElementById('edit-remove-order-item');
        if (addBtn) addBtn.addEventListener('click', function() { addRow(editContainer); });
        if (removeBtn) removeBtn.addEventListener('click', function() { removeLast(editContainer); });
    }
})();
</script>
<?php endif; ?>

<script>
(function() {
    const reviewContainer = document.getElementById('aegis-order-review-items');
    if (reviewContainer) {
        const removeBtn = document.getElementById('review-remove-order-item');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (reviewContainer.children.length > 1) {
                    reviewContainer.removeChild(reviewContainer.lastElementChild);
                }
            });
        }
    }
})();
</script>
