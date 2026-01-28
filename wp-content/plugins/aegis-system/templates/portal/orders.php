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
$pending_initial_status = AEGIS_Orders::STATUS_PENDING_INITIAL_REVIEW;
$show_create = !empty($_GET['create']);
$payment_status_labels = [
    'none'      => '未提交',
    'submitted' => '已提交，待审核',
    'approved'  => '已通过',
    'rejected'  => '已驳回',
    'need_more' => '需补充',
];
?>
<div class="aegis-t-a4 aegis-orders-page">
    <div class="aegis-orders-header">
        <div class="aegis-t-a2">订单</div>
        <div class="aegis-orders-header-actions">
            <?php if ($role_flags['is_dealer']) : ?>
                <?php $create_url = add_query_arg('create', '1', $base_url); ?>
                <?php $list_url = add_query_arg('view', $view_mode, $base_url); ?>
                <?php if ($show_create) : ?>
                    <a class="button" href="<?php echo esc_url($list_url); ?>">返回列表</a>
                <?php else : ?>
                    <a class="button" href="<?php echo esc_url($create_url); ?>">新建订单</a>
                <?php endif; ?>
            <?php endif; ?>
            <button type="button" class="aegis-help-btn button" aria-controls="aegis-help-panel" aria-expanded="false">? 帮助</button>
        </div>
    </div>

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

    <?php if ($role_flags['is_dealer'] && $show_create) : ?>
        <section class="aegis-card aegis-orders-create">
            <div class="aegis-card-header">
                <div class="aegis-card-title aegis-t-a4">新建订单</div>
            </div>
            <?php if (!$role_flags['can_create_order']) : ?>
                <p class="aegis-t-a6" style="color:#d63638;">当前账号无下单权限。</p>
            <?php elseif ($dealer_blocked) : ?>
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
        </section>
    <?php endif; ?>

    <section class="aegis-card aegis-orders-list">
        <form method="get" class="aegis-t-a6 aegis-orders-filters">
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
        <table class="aegis-table aegis-orders-table" style="width:100%;">
            <thead><tr>
                <th class="col-text">订单号</th>
                <?php if ($role_flags['can_view_all']) : ?><th class="col-text">经销商</th><?php endif; ?>
                <th class="col-text">下单时间</th>
                <?php if ($queue_mode === 'payment_review') : ?><th class="col-text">提交确认时间</th><?php endif; ?>
                <th class="col-text">状态</th>
                <?php if ($role_flags['can_view_all']) : ?><th class="col-text">付款状态</th><?php endif; ?>
                <th class="col-number">SKU 种类数</th>
                <th class="col-number">总数量</th>
                <?php if ($role_flags['can_view_all']) : ?><th class="col-number">金额</th><?php endif; ?>
                <th class="col-actions">操作</th></tr></thead>
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
                        <td class="col-text"><?php echo esc_html($row->order_no); ?></td>
                        <?php if ($role_flags['can_view_all']) : ?><td class="col-text"><?php echo esc_html($row->dealer_name ?? ''); ?></td><?php endif; ?>
                        <td class="col-text"><?php echo esc_html($row->created_at); ?></td>
                        <?php if ($queue_mode === 'payment_review') : ?><td class="col-text"><?php echo esc_html($row->payment_submitted_at ?? '-'); ?></td><?php endif; ?>
                        <td class="col-text"><?php echo esc_html($status_text); ?></td>
                        <?php if ($role_flags['can_view_all']) : ?><td class="col-text"><?php echo esc_html($payment_state_text); ?></td><?php endif; ?>
                        <td class="col-number"><?php echo esc_html((int) ($row->sku_count ?? 0)); ?></td>
                        <td class="col-number"><?php echo esc_html((int) ($row->total_qty ?? 0)); ?></td>
                        <?php if ($role_flags['can_view_all']) : ?><td class="col-number"><?php echo esc_html('¥' . number_format((float) ($row->total_amount ?? 0), 2)); ?></td><?php endif; ?>
                        <td class="col-actions">
                            <button type="button" class="button aegis-orders-open-drawer" data-order-url="<?php echo esc_url($row_link); ?>" data-mode="view">查看</button>
                            <?php if (($role_flags['is_dealer'] && $row->status === $pending_initial_status)
                                || ($role_flags['can_initial_review'] && $row->status === $pending_initial_status)
                                || ($role_flags['can_payment_review'] && $row->status === 'pending_hq_payment_review')) : ?>
                                <button type="button" class="button aegis-orders-open-drawer" data-order-url="<?php echo esc_url($row_link); ?>" data-mode="edit">编辑</button>
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
    </section>

</div>

<aside id="aegis-help-panel" class="aegis-help-panel" hidden aria-hidden="true"></aside>

<aside id="aegis-orders-drawer" class="aegis-orders-drawer" hidden aria-hidden="true">
    <div class="aegis-orders-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="aegis-orders-drawer-title">
        <div class="aegis-orders-drawer-header">
            <div class="aegis-t-a4" id="aegis-orders-drawer-title">订单详情</div>
            <button type="button" class="button aegis-orders-drawer-close" aria-label="关闭">关闭</button>
        </div>
        <div class="aegis-orders-drawer-body">
            <div id="aegis-orders-drawer-content">
                <?php if ($order) : ?>
                    <?php $status_text = $status_labels[$order->status] ?? $order->status; ?>
                    <?php
                    $is_hq = current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM)
                        || current_user_can(AEGIS_System::CAP_ACCESS_ROOT)
                        || AEGIS_System_Roles::is_hq_admin();
                    $rollback_to_status = $is_hq ? AEGIS_Orders::get_prev_status($order->status) : null;
                    ?>
                    <section class="aegis-orders-drawer-section">
                        <div class="aegis-orders-section-title aegis-t-a5">基础信息</div>
                        <div class="aegis-t-a6">订单号：<?php echo esc_html($order->order_no); ?></div>
                        <div class="aegis-t-a6">状态：<?php echo esc_html($status_text); ?></div>
                        <div class="aegis-t-a6">下单时间：<?php echo esc_html($order->created_at); ?></div>
                        <div class="aegis-t-a6">经销商：<?php echo esc_html($order->dealer_name_snapshot ?: $order->dealer_id); ?></div>
                        <?php if (!empty($order->note)) : ?>
                            <div class="aegis-t-a6">备注：<?php echo esc_html($order->note); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($order->review_note)) : ?>
                            <div class="aegis-t-a6">初审备注：<?php echo esc_html($order->review_note); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($order->void_reason)) : ?>
                            <div class="aegis-t-a6">作废原因：<?php echo esc_html($order->void_reason); ?></div>
                        <?php endif; ?>
                        <?php if ($is_hq && $rollback_to_status) : ?>
                            <form method="post" class="aegis-t-a6 aegis-orders-inline-form" style="margin-top:12px; padding-top:8px; border-top:1px solid #d9dce3;">
                                <?php wp_nonce_field('aegis_orders_rollback_' . $order->id, 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="rollback_step" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">退回原因（必填）<br />
                                    <textarea name="rollback_reason" required style="width:100%; min-height:72px;"></textarea>
                                </label>
                                <?php if ($order->status === 'shipped') : ?>
                                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">输入 ROLLBACK 以确认高风险退回<br />
                                        <input type="text" name="rollback_confirm" required style="width:100%;" />
                                    </label>
                                <?php endif; ?>
                                <button type="submit" class="button" onclick="return confirm('确认退回到上一环节？退回原因必须填写。');">退回上一环节</button>
                                <span class="aegis-t-a6" style="margin-left:8px; color:#6b7280;">当前：<?php echo esc_html($order->status); ?> → 退回后：<?php echo esc_html($rollback_to_status); ?></span>
                            </form>
                        <?php endif; ?>
                    </section>

                    <section class="aegis-orders-drawer-section">
                        <div class="aegis-orders-section-title aegis-t-a5">明细</div>
                        <table class="aegis-table aegis-orders-table" style="width:100%;">
                            <thead><tr>
                                <th class="col-text">EAN</th>
                                <th class="col-text">产品名</th>
                                <th class="col-number">数量</th>
                                <th class="col-number">单价</th>
                                <th class="col-text">来源</th>
                                <th class="col-text">等级快照</th>
                            </tr></thead>
                            <tbody>
                                <?php if (empty($items)) : ?>
                                    <tr><td colspan="6">暂无明细</td></tr>
                                <?php else : ?>
                                    <?php foreach ($items as $line) : ?>
                                        <tr>
                                            <td class="col-text"><?php echo esc_html($line->ean); ?></td>
                                            <td class="col-text"><?php echo esc_html($line->product_name_snapshot); ?></td>
                                            <td class="col-number"><?php echo esc_html((int) $line->qty); ?></td>
                                            <td class="col-number"><?php echo esc_html(number_format((float) $line->unit_price_snapshot, 2)); ?></td>
                                            <td class="col-text"><?php echo esc_html($line->price_source); ?></td>
                                            <td class="col-text"><?php echo esc_html($line->price_level_snapshot); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>

                    <section class="aegis-orders-drawer-section">
                        <div class="aegis-orders-section-title aegis-t-a5">付款凭证</div>
                        <?php $has_payment = $payment && !empty($payment->media_id); ?>
                        <?php $payment_url = $has_payment ? AEGIS_Orders::get_media_gateway_url($payment->media_id) : ''; ?>
                        <?php $payment_status_text = $payment && isset($payment_status_labels[$payment->status]) ? $payment_status_labels[$payment->status] : ($has_payment ? '已上传' : '未上传'); ?>
                        <?php if ($order->status === $pending_initial_status) : ?>
                            <p class="aegis-t-a6" style="color:#6b7280;">等待 HQ 初审通过后可上传付款凭证。</p>
                        <?php elseif ($role_flags['is_dealer'] && $order->status === 'pending_dealer_confirm') : ?>
                            <?php if ($dealer_blocked) : ?>
                                <p class="aegis-t-a6" style="color:#d63638;">经销商账号当前不可操作，请联系管理员。</p>
                            <?php else : ?>
                                <?php if ($payment && $payment->status === 'rejected' && !empty($payment->review_note)) : ?>
                                    <p class="aegis-t-a6" style="color:#d63638;">上次审核驳回原因：<?php echo esc_html($payment->review_note); ?></p>
                                <?php endif; ?>
                                <form method="post" enctype="multipart/form-data" class="aegis-t-a6 aegis-orders-inline-form" style="margin-bottom:8px;">
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
                                <form method="post" class="aegis-t-a6 aegis-orders-inline-form">
                                    <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                    <input type="hidden" name="order_action" value="submit_payment" />
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                    <button type="submit" class="button button-primary aegis-orders-primary-action" <?php echo $has_payment ? '' : 'disabled'; ?>>提交确认（待审核）</button>
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
                    </section>

                    <?php if ($role_flags['can_payment_review'] && $order->status === 'pending_hq_payment_review') : ?>
                        <section class="aegis-orders-drawer-section">
                            <div class="aegis-orders-section-title aegis-t-a5">付款审核</div>
                            <form method="post" class="aegis-t-a6 aegis-orders-inline-form" style="margin-bottom:8px;">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="review_payment" />
                                <input type="hidden" name="decision" value="approve" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <button type="submit" class="button button-primary aegis-orders-primary-action">审核通过（待出库）</button>
                            </form>
                            <form method="post" class="aegis-t-a6 aegis-orders-inline-form">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="review_payment" />
                                <input type="hidden" name="decision" value="reject" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">驳回原因（必填）<br />
                                    <input type="text" name="review_note" required style="width:100%;" />
                                </label>
                                <button type="submit" class="button aegis-orders-secondary-action" onclick="return confirm('确认驳回并退回经销商重新提交吗？');">驳回并退回经销商</button>
                            </form>
                        </section>
                    <?php elseif ($role_flags['can_payment_review']) : ?>
                        <p class="aegis-t-a6" style="margin-top:12px; color:#6b7280;">订单已进入其他环节，当前不可进行付款审核。</p>
                    <?php endif; ?>

                    <?php if ($role_flags['can_initial_review'] && $order->status === $pending_initial_status) : ?>
                        <section class="aegis-orders-drawer-section">
                            <div class="aegis-orders-section-title aegis-t-a5">初审（可删减/下调数量，不改价）</div>
                            <form method="post" id="aegis-order-review-form">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">初审备注（可选）<br />
                                    <input type="text" name="review_note" value="<?php echo esc_attr($order->review_note ?? ''); ?>" style="width:100%;" />
                                </label>
                                <div id="aegis-order-review-items" class="aegis-t-a6" style="display:flex; flex-direction:column; gap:8px;">
                                    <?php foreach ($items as $line) : ?>
                                        <div class="order-review-row" style="display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; align-items:end;">
                                            <label class="aegis-t-a6">SKU
                                                <input type="text" name="order_item_ean[]" value="<?php echo esc_attr($line->ean); ?>" readonly />
                                            </label>
                                            <label class="aegis-t-a6">数量（可下调/删减）
                                                <input type="number" name="order_item_qty[]" min="0" max="<?php echo esc_attr((int) $line->qty); ?>" step="1" value="<?php echo esc_attr((int) $line->qty); ?>" required />
                                                <span class="aegis-t-a6" style="display:block; color:#6b7280;">当前最大：<?php echo esc_html((int) $line->qty); ?></span>
                                            </label>
                                            <div class="aegis-t-a6">单价快照
                                                <div class="aegis-t-a6" style="font-weight:bold;">¥<?php echo esc_html(number_format((float) $line->unit_price_snapshot, 2)); ?></div>
                                            </div>
                                            <div>
                                                <button type="button" class="button order-review-remove">删除</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <button type="submit" class="button aegis-orders-secondary-action" name="order_action" value="save_review_draft">保存草稿</button>
                                    <button type="submit" class="button button-primary aegis-orders-primary-action" name="order_action" value="submit_initial_review" onclick="return confirm('确认提交初审并通知经销商确认吗？');">提交初审并通知经销商确认</button>
                                </div>
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
                        </section>
                    <?php elseif ($role_flags['can_initial_review']) : ?>
                        <p class="aegis-t-a6" style="margin-top:12px; color:#6b7280;">订单已进入下一环节，当前不可编辑初审内容。</p>
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

                    <?php if ($role_flags['is_dealer'] && $order->status === $pending_initial_status) : ?>
                        <section id="order-edit" class="aegis-t-a6 aegis-orders-drawer-section">
                            <div class="aegis-orders-section-title aegis-t-a5">编辑订单（待初审可编辑）</div>
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
                                <button type="submit" class="button aegis-orders-secondary-action">保存修改</button>
                            </form>
                            <form method="post" style="margin-top:8px;" onsubmit="return confirm('确认撤销该订单吗？撤销后不可再编辑。');">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="cancel_order" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <button type="submit" class="button">撤销订单</button>
                            </form>
                        </section>
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
                <?php else : ?>
                    <div class="aegis-t-a6" style="color:#6b7280;">请选择一条订单以查看详情。</div>
                <?php endif; ?>
            </div>
            <div class="aegis-orders-drawer-extension" aria-hidden="true"></div>
        </div>
        <div class="aegis-orders-drawer-footer">
            <button type="button" class="button aegis-orders-drawer-secondary" disabled>保存修改</button>
            <button type="button" class="button button-primary aegis-orders-drawer-primary" disabled>提交/确认</button>
            <button type="button" class="button aegis-orders-drawer-close">关闭</button>
        </div>
    </div>
</aside>

<aside id="aegis-help-panel" class="aegis-help-panel" hidden>
    <div class="aegis-help-panel-header">
        <h2 class="aegis-t-a4">订单帮助</h2>
        <button type="button" class="button aegis-help-close" aria-label="关闭帮助">关闭</button>
    </div>
    <div class="aegis-help-panel-body">
        <h3 class="aegis-t-a5">本页用途</h3>
        <p class="aegis-t-a6">这里用于创建、查看与处理订单的完整流程。</p>
        <h3 class="aegis-t-a5">操作流程</h3>
        <p class="aegis-t-a6">创建订单后，按队列完成初审、确认付款与审核出库。</p>
        <h3 class="aegis-t-a5">注意事项</h3>
        <p class="aegis-t-a6">价格与等级来自系统快照，编辑时仅可调整数量。</p>
        <h3 class="aegis-t-a5">常见问题</h3>
        <p class="aegis-t-a6">常见问题将在后续补充。</p>
    </div>
</aside>

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

    function init(container) {
        if (!container) {
            return;
        }
        const createContainer = container.querySelector('#aegis-order-items');
        if (createContainer) {
            attach(createContainer.querySelector('.order-item-row'));
            const addBtn = container.querySelector('#add-order-item');
            const removeBtn = container.querySelector('#remove-order-item');
            if (addBtn) addBtn.addEventListener('click', function() { addRow(createContainer); });
            if (removeBtn) removeBtn.addEventListener('click', function() { removeLast(createContainer); });
        }

        const editContainer = container.querySelector('#aegis-order-edit-items');
        if (editContainer) {
            editContainer.querySelectorAll('.order-item-row').forEach(attach);
            const addBtn = container.querySelector('#edit-add-order-item');
            const removeBtn = container.querySelector('#edit-remove-order-item');
            if (addBtn) addBtn.addEventListener('click', function() { addRow(editContainer); });
            if (removeBtn) removeBtn.addEventListener('click', function() { removeLast(editContainer); });
        }
    }

    window.AegisOrders = window.AegisOrders || {};
    window.AegisOrders.initPriceMap = init;

    init(document);
})();
</script>
<?php endif; ?>

<script>
(function() {
    function init(container) {
        const reviewContainer = container.querySelector('#aegis-order-review-items');
        if (reviewContainer) {
            reviewContainer.querySelectorAll('.order-review-row').forEach(function(row) {
                const removeBtn = row.querySelector('.order-review-remove');
                const qtyInput = row.querySelector('input[name="order_item_qty[]"]');
                if (removeBtn && qtyInput) {
                    removeBtn.addEventListener('click', function() {
                        qtyInput.value = '0';
                        row.style.opacity = '0.5';
                    });
                }
            });
        }
    }

    window.AegisOrders = window.AegisOrders || {};
    window.AegisOrders.initReview = init;

    init(document);
})();
</script>

<script>
(function() {
    const ordersPage = document.querySelector('.aegis-orders-page');
    if (!ordersPage) return;

    const drawer = document.getElementById('aegis-orders-drawer');
    const drawerContent = document.getElementById('aegis-orders-drawer-content');
    const drawerCloseButtons = drawer ? drawer.querySelectorAll('.aegis-orders-drawer-close') : [];
    const footerSecondary = drawer ? drawer.querySelector('.aegis-orders-drawer-secondary') : null;
    const footerPrimary = drawer ? drawer.querySelector('.aegis-orders-drawer-primary') : null;

    if (!drawer || !drawerContent) {
        return;
    }

    const setFooterState = () => {
        const primaryAction = drawerContent.querySelector('.aegis-orders-primary-action');
        const secondaryAction = drawerContent.querySelector('.aegis-orders-secondary-action');

        if (footerPrimary) {
            footerPrimary.disabled = !primaryAction || primaryAction.disabled;
            footerPrimary.onclick = () => {
                if (primaryAction && !primaryAction.disabled) {
                    primaryAction.click();
                }
            };
        }

        if (footerSecondary) {
            footerSecondary.disabled = !secondaryAction || secondaryAction.disabled;
            footerSecondary.onclick = () => {
                if (secondaryAction && !secondaryAction.disabled) {
                    secondaryAction.click();
                }
            };
        }
    };

    const applyMode = (mode) => {
        drawer.dataset.mode = mode;
        const isReadOnly = mode === 'view';
        drawerContent.querySelectorAll('input, select, textarea, button').forEach((field) => {
            if (!field.dataset.aegisOriginalDisabled) {
                field.dataset.aegisOriginalDisabled = field.disabled ? '1' : '0';
            }
            if (isReadOnly) {
                field.disabled = true;
            } else if (field.dataset.aegisOriginalDisabled === '0') {
                field.disabled = false;
            }
        });

        if (footerPrimary) {
            footerPrimary.disabled = isReadOnly || footerPrimary.disabled;
        }
        if (footerSecondary) {
            footerSecondary.disabled = isReadOnly || footerSecondary.disabled;
        }
    };

    const openDrawer = (mode) => {
        drawer.hidden = false;
        drawer.setAttribute('aria-hidden', 'false');
        applyMode(mode);
        setFooterState();
    };

    const closeDrawer = () => {
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
        const url = new URL(window.location.href);
        url.searchParams.delete('order_id');
        window.history.replaceState({}, '', url.toString());
    };

    drawerCloseButtons.forEach((button) => button.addEventListener('click', closeDrawer));

    ordersPage.querySelectorAll('.aegis-orders-open-drawer').forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.getAttribute('data-order-url');
            const mode = button.getAttribute('data-mode') || 'view';
            if (!url) return;

            fetch(url, { credentials: 'same-origin' })
                .then((response) => response.text())
                .then((html) => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const nextContent = doc.querySelector('#aegis-orders-drawer-content');
                    if (nextContent) {
                        drawerContent.innerHTML = nextContent.innerHTML;
                        if (window.AegisOrders && typeof window.AegisOrders.initPriceMap === 'function') {
                            window.AegisOrders.initPriceMap(drawerContent);
                        }
                        if (window.AegisOrders && typeof window.AegisOrders.initReview === 'function') {
                            window.AegisOrders.initReview(drawerContent);
                        }
                    }
                    openDrawer(mode);
                    window.history.replaceState({}, '', url);
                })
                .catch(() => {
                    openDrawer(mode);
                });
        });
    });

    if (window.location.search.includes('order_id=')) {
        openDrawer('view');
    }
})();
</script>

<script>
(function() {
    const ordersPage = document.querySelector('.aegis-orders-page');
    if (!ordersPage) return;

    const createCard = ordersPage.querySelector('.aegis-orders-create');
    const toggleCreate = ordersPage.querySelector('.aegis-toggle-create');
    if (createCard && toggleCreate) {
        toggleCreate.addEventListener('click', function() {
            const isCollapsed = createCard.classList.toggle('is-collapsed');
            toggleCreate.setAttribute('aria-expanded', String(!isCollapsed));
            toggleCreate.textContent = isCollapsed ? '展开' : '收起';
        });
    }

    const helpBtn = ordersPage.querySelector('.aegis-help-btn');
    const helpPanel = document.getElementById('aegis-help-panel');
    const helpClose = helpPanel ? helpPanel.querySelector('.aegis-help-close') : null;
    if (helpBtn && helpPanel) {
        const closeHelp = function() {
            helpPanel.hidden = true;
            helpBtn.setAttribute('aria-expanded', 'false');
        };
        helpBtn.addEventListener('click', function() {
            const isOpen = !helpPanel.hidden;
            helpPanel.hidden = isOpen;
            helpBtn.setAttribute('aria-expanded', String(!isOpen));
        });
        if (helpClose) {
            helpClose.addEventListener('click', closeHelp);
        }
    }
})();
</script>
