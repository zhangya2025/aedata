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
$processing_lock = $context['processing_lock'] ?? null;
$is_processing_locked = !empty($processing_lock['locked']);
$cancel_request = $context['cancel_request'] ?? null;
$cancel_form_error = $context['cancel_form_error'] ?? '';
$cancel_decision_error = $context['cancel_decision_error'] ?? '';
$cancel_success_map = $context['cancel_success_map'] ?? [];
$auto_open_drawer = !empty($context['auto_open_drawer']);
$drawer_order_url = '';
if ($order) {
    $drawer_params = ['order_id' => $order->id];
    if ($queue_mode) {
        $drawer_params['view'] = $view_mode;
    }
    $drawer_order_url = add_query_arg($drawer_params, $base_url);
}
$draft_status = AEGIS_Orders::STATUS_DRAFT;
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

    <?php
    $show_sales_toggle = $role_flags['is_sales'] && $role_flags['can_initial_review'] && !$role_flags['can_payment_review'] && !$role_flags['can_manage_all'];
    ?>
    <?php if ($role_flags['can_manage_all'] || ($role_flags['can_initial_review'] && $role_flags['can_payment_review']) || $show_sales_toggle) : ?>
        <div class="aegis-t-a6" style="margin:8px 0; display:flex; gap:8px; align-items:center;">
            <span>视图：</span>
            <?php $review_url = add_query_arg(['view' => 'review'], $base_url); ?>
            <?php $list_url = add_query_arg(['view' => 'list'], $base_url); ?>
            <?php $payment_review_url = add_query_arg(['view' => 'payment_review'], $base_url); ?>
            <a class="button <?php echo $view_mode === 'review' ? 'button-primary' : ''; ?>" href="<?php echo esc_url($review_url); ?>">待初审队列</a>
            <?php if (!$show_sales_toggle) : ?>
                <a class="button <?php echo $view_mode === 'payment_review' ? 'button-primary' : ''; ?>" href="<?php echo esc_url($payment_review_url); ?>">待审核队列</a>
            <?php endif; ?>
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
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                    <div class="aegis-t-a6" style="margin-bottom:8px;">经销商：<?php echo esc_html($dealer ? $dealer->dealer_name : ''); ?></div>
                    <div class="aegis-note-field">
                        <button type="button" class="aegis-note-toggle" aria-expanded="false">添加备注</button>
                        <div class="aegis-note-panel" hidden>
                            <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">备注（可选）<br />
                                <input type="text" name="note" style="width:100%;" />
                            </label>
                        </div>
                    </div>
                    <div class="aegis-t-a6" style="margin-bottom:8px;">订单明细（价格自动带出，不可手改）</div>
                    <div id="aegis-order-items" class="aegis-t-a6" style="display:flex; flex-direction:column; gap:8px;">
                        <div class="order-item-row" style="display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; align-items:end;">
                            <label class="aegis-t-a6">SKU
                                <input list="aegis-sku-list" name="order_item_ean[]" required />
                            </label>
                            <label class="aegis-t-a6">数量
                                <input type="number" name="order_item_qty[]" min="1" step="1" value="1" required />
                            </label>
                            <div class="aegis-t-a6">单价
                                <div class="aegis-t-a6 order-item-price" style="font-weight:bold;">—</div>
                            </div>
                            <div class="aegis-order-row-actions">
                                <button type="button" class="aegis-row-add" aria-label="新增一行">＋</button>
                                <button type="button" class="aegis-row-del" aria-label="删除此行">－</button>
                            </div>
                        </div>
                    </div>
                    <datalist id="aegis-sku-list">
                        <?php foreach ($skus as $sku) : ?>
                            <option value="<?php echo esc_attr($sku->ean); ?>"><?php echo esc_html($sku->ean . ' / ' . $sku->product_name); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="aegis-orders-actions">
                        <button type="submit" name="order_action" value="save_draft" class="button">保存订单</button>
                        <button type="submit" name="order_action" value="submit_order" class="button button-primary">保存并提交</button>
                    </div>
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
            <colgroup>
                <col class="col-order-no" />
                <?php if ($role_flags['can_view_all']) : ?><col class="col-dealer" /><?php endif; ?>
                <col class="col-created-at" />
                <?php if ($queue_mode === 'payment_review') : ?><col class="col-payment-submitted" /><?php endif; ?>
                <col class="col-status" />
                <?php if ($role_flags['can_view_all']) : ?><col class="col-payment-status" /><?php endif; ?>
                <col class="col-sku-count" />
                <col class="col-total-qty" />
                <?php if ($role_flags['can_view_all']) : ?><col class="col-amount" /><?php endif; ?>
                <col class="col-actions" />
            </colgroup>
            <thead><tr>
                <th class="col-text col-order-no">订单号</th>
                <?php if ($role_flags['can_view_all']) : ?><th class="col-text col-dealer">经销商</th><?php endif; ?>
                <th class="col-text col-created-at">下单时间</th>
                <?php if ($queue_mode === 'payment_review') : ?><th class="col-text col-payment-submitted">提交确认时间</th><?php endif; ?>
                <th class="col-text col-status">状态</th>
                <?php if ($role_flags['can_view_all']) : ?><th class="col-text col-payment-status">付款状态</th><?php endif; ?>
                <th class="col-number col-sku-count">SKU 种类数</th>
                <th class="col-number col-total-qty">总数量</th>
                <?php if ($role_flags['can_view_all']) : ?><th class="col-number col-amount">金额</th><?php endif; ?>
                <th class="col-actions">操作</th></tr></thead>
            <tbody>
                <?php if (empty($orders)) : ?>
                    <tr><td colspan="<?php echo esc_attr($table_colspan); ?>">暂无订单</td></tr>
                <?php else : ?>
                    <?php foreach ($orders as $row) : ?>
                        <?php
                        $status_text = $status_labels[$row->status] ?? $row->status;
                        $cancel_pending = false;
                        $cancel_decision = '';
                        if (!empty($row->meta)) {
                            $row_meta = json_decode($row->meta, true);
                            $cancel_pending = !empty($row_meta['cancel']['requested']) && ('pending' === ($row_meta['cancel']['decision'] ?? ''));
                            $cancel_decision = $row_meta['cancel']['decision'] ?? '';
                        }
                        $can_delete = $role_flags['is_dealer']
                            && $row->status === AEGIS_Orders::STATUS_CANCELLED
                            && !empty($cancel_success_map[$row->id])
                            && empty($row->deleted_at);
                        ?>
                        <?php $row_link = add_query_arg(['order_id' => $row->id], $base_url); ?>
                        <?php if ($role_flags['queue_view']) { $row_link = add_query_arg(['view' => $view_mode, 'order_id' => $row->id], $base_url); } ?>
                        <?php $payment_state_text = $row->payment_status && isset($payment_status_labels[$row->payment_status]) ? $payment_status_labels[$row->payment_status] : '-'; ?>
                        <tr data-order-id="<?php echo esc_attr($row->id); ?>">
                        <td class="col-text col-order-no"><?php echo esc_html($row->order_no); ?></td>
                        <?php if ($role_flags['can_view_all']) : ?><td class="col-text col-dealer"><?php echo esc_html($row->dealer_name ?? ''); ?></td><?php endif; ?>
                        <td class="col-text col-created-at"><?php echo esc_html($row->created_at); ?></td>
                        <?php if ($queue_mode === 'payment_review') : ?><td class="col-text col-payment-submitted"><?php echo esc_html($row->payment_submitted_at ?? '-'); ?></td><?php endif; ?>
                        <td class="col-text col-status">
                            <?php echo esc_html($status_text); ?>
                            <?php if ($cancel_pending) : ?>
                                <span class="aegis-t-a6" style="margin-left:6px; color:#d97706;">撤销申请中</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($role_flags['can_view_all']) : ?><td class="col-text col-payment-status"><?php echo esc_html($payment_state_text); ?></td><?php endif; ?>
                        <td class="col-number col-sku-count"><?php echo esc_html((int) ($row->sku_count ?? 0)); ?></td>
                        <td class="col-number col-total-qty"><?php echo esc_html((int) ($row->total_qty ?? 0)); ?></td>
                        <?php if ($role_flags['can_view_all']) : ?><td class="col-number col-amount"><?php echo esc_html('¥' . number_format((float) ($row->total_amount ?? 0), 2)); ?></td><?php endif; ?>
                        <td class="col-actions">
                            <?php if ($role_flags['is_dealer']) : ?>
                                <button type="button" class="button aegis-orders-open-drawer" data-order-url="<?php echo esc_url($row_link); ?>" data-mode="view">查看</button>
                                <?php if ($row->status === $draft_status) : ?>
                                    <button type="button" class="button aegis-orders-open-drawer" data-order-url="<?php echo esc_url($row_link); ?>" data-mode="edit">编辑</button>
                                    <form method="post" class="aegis-orders-inline-form" style="display:inline-block;">
                                        <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                        <input type="hidden" name="order_action" value="submit_draft" />
                                        <input type="hidden" name="order_id" value="<?php echo esc_attr($row->id); ?>" />
                                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                        <button type="submit" class="button button-primary">提交</button>
                                    </form>
                                <?php else : ?>
                                    <?php
                                    $dealer_cancel_allowed = in_array($row->status, ['pending_initial_review', 'pending_dealer_confirm', 'pending_hq_payment_review', 'approved_pending_fulfillment'], true);
                                    ?>
                                    <?php if ($dealer_cancel_allowed && !$cancel_pending) : ?>
                                        <button type="button" class="button aegis-orders-open-drawer" data-order-url="<?php echo esc_url($row_link); ?>" data-mode="view">申请撤销</button>
                                    <?php endif; ?>
                                    <?php if ($can_delete) : ?>
                                        <form method="post" class="aegis-orders-inline-form" style="display:inline-block;">
                                            <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                            <input type="hidden" name="order_action" value="delete_order" />
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($row->id); ?>" />
                                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                            <button type="submit" class="button" onclick="return confirm('确认删除该订单吗？');">删除</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else : ?>
                                <button type="button" class="button aegis-orders-open-drawer" data-order-url="<?php echo esc_url($row_link); ?>" data-mode="view">查看</button>
                                <?php if (($role_flags['can_initial_review'] && $row->status === $pending_initial_status)
                                    || ($role_flags['can_payment_review'] && $row->status === 'pending_hq_payment_review')) : ?>
                                    <button type="button" class="button aegis-orders-open-drawer" data-order-url="<?php echo esc_url($row_link); ?>" data-mode="edit">编辑</button>
                                <?php endif; ?>
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

<aside id="aegis-orders-drawer" class="aegis-orders-drawer" hidden aria-hidden="true" <?php echo $auto_open_drawer ? 'data-auto-open="1"' : ''; ?><?php echo $drawer_order_url ? ' data-order-url="' . esc_url($drawer_order_url) . '"' : ''; ?>>
    <div class="aegis-orders-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="aegis-orders-drawer-title">
        <div class="aegis-orders-drawer-header">
            <div class="aegis-t-a4" id="aegis-orders-drawer-title">订单详情</div>
            <button type="button" class="button aegis-orders-drawer-close" aria-label="关闭">关闭</button>
        </div>
        <div class="aegis-orders-drawer-body">
            <div id="aegis-orders-drawer-content">
                <?php if ($order) : ?>
                    <?php
                    $status_text = $status_labels[$order->status] ?? $order->status;
                    $payment_status_text = $payment && isset($payment_status_labels[$payment->status]) ? $payment_status_labels[$payment->status] : '';
                    $initial_reviewed_at = $order->reviewed_at ?? '';
                    $payment_submitted_at = $payment->submitted_at ?? '';
                    $payment_reviewed_at = $payment->reviewed_at ?? ($order->payment_reviewed_at ?? '');
                    ?>
                    <?php
                    $is_hq = current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM)
                        || current_user_can(AEGIS_System::CAP_ACCESS_ROOT)
                        || AEGIS_System_Roles::is_hq_admin();
                    $rollback_to_status = $is_hq ? AEGIS_Orders::get_prev_status($order->status) : null;
                    ?>
                    <?php
                    $cancel_requested = !empty($cancel_request['requested']) && ('pending' === ($cancel_request['decision'] ?? ''));
                    $cancel_reason = $cancel_request['reason'] ?? '';
                    $cancel_decision_note = $cancel_request['decision_note'] ?? '';
                    $cancel_decision = $cancel_request['decision'] ?? '';
                    $cancel_reason_input = $cancel_reason;
                    $cancel_decision_note_input = $cancel_decision_note;
                    $cancel_submitted = !empty($_GET['cancel_submitted']) && (int) ($_GET['order_id'] ?? 0) === (int) $order->id;
                    if ('POST' === $_SERVER['REQUEST_METHOD']) {
                        $post_action = isset($_POST['order_action']) ? sanitize_key(wp_unslash($_POST['order_action'])) : '';
                        $post_order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
                        if ('request_cancel' === $post_action && $post_order_id === (int) $order->id) {
                            $cancel_form_error = $cancel_form_error ?: ($errors[0] ?? '');
                            $cancel_reason_input = isset($_POST['cancel_reason']) ? sanitize_text_field(wp_unslash($_POST['cancel_reason'])) : '';
                        } elseif ('cancel_decision' === $post_action && $post_order_id === (int) $order->id) {
                            $cancel_decision_error = $cancel_decision_error ?: ($errors[0] ?? '');
                            $cancel_decision_note_input = isset($_POST['decision_note']) ? sanitize_text_field(wp_unslash($_POST['decision_note'])) : '';
                        }
                    }
                    $current_user_id = get_current_user_id();
                    $current_roles = AEGIS_System_Roles::get_user_roles();
                    $is_dealer = in_array('aegis_dealer', $current_roles, true);
                    $current_user_can_approve_cancel = $cancel_requested
                        && (AEGIS_Orders::can_force_cancel() || AEGIS_Orders::can_approve_cancel($order));
                    $cancel_requested_by = $cancel_request['requested_by'] ?? 0;
                    $can_cancel_decide = $current_user_can_approve_cancel
                        && !$is_dealer
                        && (!$cancel_requested_by || (int) $cancel_requested_by !== (int) $current_user_id);
                    $cancel_requested_at = $cancel_request['requested_at'] ?? '';
                    $cancel_requested_user = $cancel_requested_by ? get_userdata((int) $cancel_requested_by) : null;
                    $cancel_requested_name = $cancel_requested_user ? ($cancel_requested_user->display_name ?: $cancel_requested_user->user_login) : '';
                    $cancel_requested_time = $cancel_requested_at ? wp_date('Y-m-d H:i', strtotime($cancel_requested_at)) : '';
                    $cancel_decided_label = '';
                    if ('approved' === $cancel_decision) {
                        $cancel_decided_label = '已批准撤销';
                    } elseif ('rejected' === $cancel_decision) {
                        $cancel_decided_label = '已驳回撤销';
                    }
                    $cancel_pending_label = '';
                    if ($order->status === $pending_initial_status || $order->status === 'pending_dealer_confirm') {
                        $cancel_pending_label = '销售/HQ';
                    } elseif ($order->status === 'pending_hq_payment_review') {
                        $cancel_pending_label = 'HQ';
                    } elseif ($order->status === 'approved_pending_fulfillment') {
                        $cancel_pending_label = '仓库/HQ';
                    }
                    ?>
                    <?php if ($cancel_submitted) : ?>
                        <div class="notice notice-success" style="margin-bottom:12px;">
                            <p class="aegis-t-a6">撤销申请已提交，等待审批。</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($cancel_requested) : ?>
                        <div class="notice notice-warning" style="margin-bottom:12px;">
                            <p class="aegis-t-a6">经销商申请撤销订单（原因：<?php echo esc_html($cancel_reason ?: '未填写'); ?>）。</p>
                            <p class="aegis-t-a6">状态：撤销申请中<?php echo $cancel_pending_label ? '（待' . esc_html($cancel_pending_label) . '审批）' : ''; ?>。</p>
                        </div>
                    <?php elseif ($cancel_decided_label) : ?>
                        <div class="notice notice-success" style="margin-bottom:12px;">
                            <p class="aegis-t-a6"><?php echo esc_html($cancel_decided_label); ?>。</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($can_cancel_decide) : ?>
                        <section class="aegis-orders-drawer-section">
                            <div class="aegis-orders-section-title aegis-t-a5">撤销审批</div>
                            <div class="aegis-t-a6">撤销原因：<?php echo esc_html($cancel_reason ?: '未填写'); ?></div>
                            <?php if ($cancel_requested_name) : ?>
                                <div class="aegis-t-a6">申请人：<?php echo esc_html($cancel_requested_name); ?></div>
                            <?php endif; ?>
                            <?php if ($cancel_requested_time) : ?>
                                <div class="aegis-t-a6">申请时间：<?php echo esc_html($cancel_requested_time); ?></div>
                            <?php endif; ?>
                            <?php if ($can_cancel_decide) : ?>
                                <form method="post" class="aegis-t-a6 aegis-orders-inline-form" style="margin-top:8px;">
                                    <?php
                                    $nonce_field = wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce', true, false);
                                    $nonce_field = str_replace('name="aegis_orders_nonce"', 'name="aegis_orders_nonce" data-aegis-allow-readonly="1"', $nonce_field);
                                    $nonce_field = str_replace('name="_wp_http_referer"', 'name="_wp_http_referer" data-aegis-allow-readonly="1"', $nonce_field);
                                    echo $nonce_field;
                                    ?>
                                    <input type="hidden" name="order_action" value="cancel_decision" data-aegis-allow-readonly="1" />
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" data-aegis-allow-readonly="1" />
                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" data-aegis-allow-readonly="1" />
                                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">审批备注（可选）<br />
                                        <textarea name="decision_note" style="width:100%; min-height:72px;" data-aegis-allow-readonly="1"><?php echo esc_textarea($cancel_decision_note_input); ?></textarea>
                                    </label>
                                    <?php if ($cancel_decision_error) : ?>
                                        <p class="aegis-t-a6" style="margin-bottom:8px; color:#d63638;"><?php echo esc_html($cancel_decision_error); ?></p>
                                    <?php endif; ?>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <button type="submit" class="button button-primary" name="decision" value="approve" data-aegis-allow-readonly="1" onclick="return confirm('确认批准撤销订单吗？');">批准撤销</button>
                                        <button type="submit" class="button" name="decision" value="reject" data-aegis-allow-readonly="1" onclick="return confirm('确认驳回撤销申请吗？');">驳回撤销</button>
                                    </div>
                                </form>
                            <?php else : ?>
                                <div class="aegis-t-a6" style="margin-top:8px;">状态：<?php echo esc_html($cancel_decided_label); ?></div>
                                <?php if (!empty($cancel_decision_note)) : ?>
                                    <div class="aegis-t-a6">审批备注：<?php echo esc_html($cancel_decision_note); ?></div>
                                <?php endif; ?>
                                <div style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                                    <button type="button" class="button button-primary" disabled>批准撤销</button>
                                    <button type="button" class="button" disabled>驳回撤销</button>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
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
                        <?php if (!empty($cancel_decision_note) && !$cancel_requested) : ?>
                            <div class="aegis-t-a6">撤销处理意见：<?php echo esc_html($cancel_decision_note); ?></div>
                        <?php endif; ?>
                        <?php if ($is_hq && $rollback_to_status) : ?>
                            <form method="post" class="aegis-t-a6 aegis-orders-inline-form" style="margin-top:12px; padding-top:8px; border-top:1px solid #d9dce3;">
                                <?php wp_nonce_field('aegis_orders_rollback_' . $order->id, 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="rollback_step" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">退回原因（必填）<br />
                                    <textarea name="rollback_reason" required style="width:100%; min-height:72px;" data-aegis-edit-field="1"></textarea>
                                </label>
                                <?php if ($order->status === 'shipped') : ?>
                                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">输入 ROLLBACK 以确认高风险退回<br />
                                        <input type="text" name="rollback_confirm" required style="width:100%;" data-aegis-edit-field="1" />
                                    </label>
                                <?php endif; ?>
                                <button type="submit" class="button" data-aegis-edit-action="1" onclick="return confirm('确认退回到上一环节？退回原因必须填写。');">退回上一环节</button>
                                <span class="aegis-t-a6" style="margin-left:8px; color:#6b7280;">当前：<?php echo esc_html($order->status); ?> → 退回后：<?php echo esc_html($rollback_to_status); ?></span>
                            </form>
                        <?php endif; ?>
                    </section>

                    <section class="aegis-orders-drawer-section">
                        <div class="aegis-orders-section-title aegis-t-a5">状态进度</div>
                        <div class="aegis-t-a6">下单：<?php echo esc_html($order->created_at ?: '—'); ?></div>
                        <div class="aegis-t-a6">初审通过：<?php echo esc_html($initial_reviewed_at ?: '—'); ?></div>
                        <div class="aegis-t-a6">付款凭证提交：<?php echo esc_html($payment_submitted_at ?: '—'); ?></div>
                        <div class="aegis-t-a6">财务审核：<?php echo esc_html($payment_reviewed_at ?: ($order->status === 'pending_hq_payment_review' ? '审核中' : '—')); ?></div>
                        <?php if ($payment_status_text) : ?>
                            <div class="aegis-t-a6">付款审核状态：<?php echo esc_html($payment_status_text); ?></div>
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
                                    <button type="submit" class="button button-primary">上传并提交审核</button>
                                    <?php if ($has_payment) : ?>
                                        <span class="aegis-t-a6" style="margin-left:8px;">当前：<a href="<?php echo esc_url($payment_url); ?>" target="_blank">查看凭证</a>（<?php echo esc_html($payment_status_text); ?>）</span>
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
                                <p class="aegis-t-a6" style="color:#6b7280;">已提交付款凭证，等待审核。</p>
                            <?php elseif ($order->status === 'approved_pending_fulfillment') : ?>
                                <p class="aegis-t-a6" style="color:#15803d;">付款审核已通过，等待出库。</p>
                            <?php elseif ($order->status === AEGIS_Orders::STATUS_FULFILLED) : ?>
                                <p class="aegis-t-a6" style="color:#15803d;">已完成出库，订单结束。</p>
                            <?php elseif (in_array($order->status, ['voided_by_hq', 'cancelled_by_dealer', AEGIS_Orders::STATUS_CANCELLED], true)) : ?>
                                <p class="aegis-t-a6" style="color:#6b7280;">订单已终止，凭证仅供查看。</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>

                    <?php if ($role_flags['can_payment_review'] && $order->status === 'pending_hq_payment_review') : ?>
                        <section class="aegis-orders-drawer-section">
                            <div class="aegis-orders-section-title aegis-t-a5">付款审核</div>
                            <form method="post" class="aegis-t-a6 aegis-orders-inline-form" style="margin-bottom:8px;">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="review_payment" data-aegis-allow-readonly="1" />
                                <input type="hidden" name="decision" value="approve" data-aegis-allow-readonly="1" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" data-aegis-allow-readonly="1" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" data-aegis-allow-readonly="1" />
                                <button type="submit" class="button button-primary aegis-orders-primary-action" data-aegis-allow-readonly="1">审核通过（待出库）</button>
                            </form>
                            <form method="post" class="aegis-t-a6 aegis-orders-inline-form">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="review_payment" data-aegis-allow-readonly="1" />
                                <input type="hidden" name="decision" value="reject" data-aegis-allow-readonly="1" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" data-aegis-allow-readonly="1" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" data-aegis-allow-readonly="1" />
                                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">驳回原因（必填）<br />
                                    <input type="text" name="review_note" required style="width:100%;" data-aegis-allow-readonly="1" />
                                </label>
                                <button type="submit" class="button aegis-orders-secondary-action" data-aegis-allow-readonly="1" onclick="return confirm('确认驳回并退回经销商重新提交吗？');">驳回并退回经销商</button>
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
                                    <input type="text" name="review_note" value="<?php echo esc_attr($order->review_note ?? ''); ?>" style="width:100%;" data-aegis-edit-field="1" />
                                </label>
                                <div id="aegis-order-review-items" class="aegis-t-a6" style="display:flex; flex-direction:column; gap:8px;">
                                    <?php foreach ($items as $line) : ?>
                                        <div class="order-review-row" style="display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; align-items:end;">
                                            <label class="aegis-t-a6">SKU
                                                <input type="text" name="order_item_ean[]" value="<?php echo esc_attr($line->ean); ?>" readonly data-aegis-edit-field="1" />
                                            </label>
                                            <label class="aegis-t-a6">数量（可下调/删减）
                                                <input type="number" name="order_item_qty[]" min="0" max="<?php echo esc_attr((int) $line->qty); ?>" step="1" value="<?php echo esc_attr((int) $line->qty); ?>" required data-aegis-edit-field="1" />
                                                <span class="aegis-t-a6" style="display:block; color:#6b7280;">当前最大：<?php echo esc_html((int) $line->qty); ?></span>
                                            </label>
                                            <div class="aegis-t-a6">单价快照
                                                <div class="aegis-t-a6" style="font-weight:bold;">¥<?php echo esc_html(number_format((float) $line->unit_price_snapshot, 2)); ?></div>
                                            </div>
                                            <div>
                                                <button type="button" class="button order-review-remove" data-aegis-edit-action="1">删除</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <button type="submit" class="button aegis-orders-secondary-action" name="order_action" value="save_review_draft" data-aegis-edit-action="1">保存草稿</button>
                                    <button type="submit" class="button button-primary aegis-orders-primary-action" name="order_action" value="submit_initial_review" data-aegis-edit-action="1" onclick="return confirm('确认提交初审并通知经销商确认吗？');">提交初审并通知经销商确认</button>
                                </div>
                            </form>
                            <form method="post" style="margin-top:8px;" onsubmit="return confirm('确认作废该订单吗？作废后不可恢复。');">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="void_order" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">作废原因（可选）<br />
                                    <input type="text" name="void_reason" style="width:100%;" data-aegis-edit-field="1" />
                                </label>
                                <button type="submit" class="button" data-aegis-edit-action="1">作废订单</button>
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
                                <input type="text" name="void_reason" style="width:100%;" data-aegis-edit-field="1" />
                            </label>
                            <button type="submit" class="button" data-aegis-edit-action="1">删除/作废订单</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($role_flags['is_dealer'] && $order->status === $draft_status) : ?>
                        <section id="order-edit" class="aegis-t-a6 aegis-orders-drawer-section">
                            <div class="aegis-orders-section-title aegis-t-a5">编辑订单（草稿可编辑）</div>
                            <form method="post" id="aegis-order-edit-form">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="update_order" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <div class="aegis-note-field">
                                    <button type="button" class="aegis-note-toggle" aria-expanded="false">添加备注</button>
                                    <div class="aegis-note-panel" hidden>
                                        <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">备注（可选）<br />
                                            <input type="text" name="note" value="<?php echo esc_attr($order->note); ?>" style="width:100%;" data-aegis-edit-field="1" />
                                        </label>
                                    </div>
                                </div>
                                <div id="aegis-order-edit-items" class="aegis-t-a6" style="display:flex; flex-direction:column; gap:8px;">
                                    <?php if (!empty($items)) : ?>
                                        <?php foreach ($items as $line) : ?>
                                            <div class="order-item-row" style="display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; align-items:end;">
                                                <label class="aegis-t-a6">SKU
                                                    <input list="aegis-sku-list" name="order_item_ean[]" value="<?php echo esc_attr($line->ean); ?>" required data-aegis-edit-field="1" />
                                                </label>
                                                <label class="aegis-t-a6">数量
                                                    <input type="number" name="order_item_qty[]" min="1" step="1" value="<?php echo esc_attr((int) $line->qty); ?>" required data-aegis-edit-field="1" />
                                                </label>
                                                <div class="aegis-t-a6">单价
                                                    <div class="aegis-t-a6 order-item-price" style="font-weight:bold;">—</div>
                                                </div>
                                                <div class="aegis-order-row-actions">
                                                    <button type="button" class="aegis-row-add" aria-label="新增一行" data-aegis-edit-action="1">＋</button>
                                                    <button type="button" class="aegis-row-del" aria-label="删除此行" data-aegis-edit-action="1">－</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="aegis-order-edit-actions">
                                    <button type="submit" class="button aegis-orders-secondary-action" data-aegis-edit-action="1">保存修改</button>
                                </div>
                            </form>
                            <form method="post" class="aegis-order-edit-actions" onsubmit="return confirm('确认撤销该草稿吗？撤销后不可再编辑。');">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="cancel_order" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <button type="submit" class="button" data-aegis-edit-action="1">撤销草稿</button>
                            </form>
                            <form method="post" class="aegis-order-edit-actions" onsubmit="return confirm('确认提交该草稿进入待初审吗？');">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="submit_draft" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <button type="submit" class="button button-primary aegis-orders-primary-action" data-aegis-edit-action="1">提交草稿</button>
                            </form>
                        </section>
                    <?php elseif ($role_flags['is_dealer'] && $order->status === $pending_initial_status) : ?>
                        <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">订单已提交初审，当前内容只读。</p>
                        <?php if ($is_processing_locked) : ?>
                            <p class="aegis-t-a6" style="margin-top:8px; color:#d63638;">订单处理中，暂不可撤回。</p>
                        <?php else : ?>
                            <form method="post" class="aegis-order-edit-actions" onsubmit="return confirm('确认撤回提交并恢复为草稿吗？');">
                                <?php wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce'); ?>
                                <input type="hidden" name="order_action" value="withdraw_order" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <button type="submit" class="button" data-aegis-edit-action="1">撤回提交</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($role_flags['is_dealer'] && $order->status === 'pending_dealer_confirm') : ?>
                        <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">订单已由 HQ 调整并待确认，当前内容只读。</p>
                    <?php elseif ($role_flags['is_dealer'] && $order->status === 'pending_hq_payment_review') : ?>
                        <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">已提交付款凭证，等待审核，当前内容只读。</p>
                    <?php elseif ($role_flags['is_dealer'] && $order->status === 'approved_pending_fulfillment') : ?>
                        <p class="aegis-t-a6" style="margin-top:8px; color:#15803d;">付款已通过，等待出库，内容只读。</p>
                    <?php elseif ($role_flags['is_dealer'] && $order->status === AEGIS_Orders::STATUS_FULFILLED) : ?>
                        <p class="aegis-t-a6" style="margin-top:8px; color:#15803d;">已完成出库，订单结束。</p>
                    <?php elseif ($role_flags['is_dealer'] && $order->status === AEGIS_Orders::STATUS_CANCELLED) : ?>
                        <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">订单已撤销，明细仅供查看。</p>
                    <?php elseif ($order->status === 'cancelled_by_dealer') : ?>
                        <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">订单已撤销，明细仅供查看。</p>
                    <?php elseif ($order->status === 'voided_by_hq') : ?>
                        <p class="aegis-t-a6" style="margin-top:8px; color:#d63638;">订单已作废，无法继续操作。</p>
                    <?php endif; ?>
                    <?php if ($role_flags['is_dealer']) : ?>
                        <?php
                        $dealer_cancel_allowed = in_array($order->status, ['pending_initial_review', 'pending_dealer_confirm', 'pending_hq_payment_review', 'approved_pending_fulfillment'], true);
                        ?>
                        <?php if ($order->status === AEGIS_Orders::STATUS_FULFILLED) : ?>
                            <p class="aegis-t-a6" style="margin-top:8px; color:#6b7280;">订单已完成不可撤销。</p>
                        <?php elseif ($dealer_cancel_allowed && !$cancel_requested) : ?>
                            <form method="post" class="aegis-t-a6 aegis-orders-inline-form aegis-cancel-form" style="margin-top:12px;">
                                <?php
                                $nonce_field = wp_nonce_field('aegis_orders_action', 'aegis_orders_nonce', true, false);
                                $nonce_field = str_replace('name="aegis_orders_nonce"', 'name="aegis_orders_nonce" data-aegis-allow-readonly="1"', $nonce_field);
                                $nonce_field = str_replace('name="_wp_http_referer"', 'name="_wp_http_referer" data-aegis-allow-readonly="1"', $nonce_field);
                                echo $nonce_field;
                                ?>
                                <input type="hidden" name="order_action" value="request_cancel" data-aegis-allow-readonly="1" />
                                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>" data-aegis-allow-readonly="1" />
                                <?php if ($cancel_form_error) : ?>
                                    <p class="aegis-t-a6" style="margin-bottom:8px; color:#d63638;"><?php echo esc_html($cancel_form_error); ?></p>
                                <?php endif; ?>
                                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">撤销原因（必填）<br />
                                    <input type="text" name="cancel_reason" required style="width:100%;" data-aegis-allow-readonly="1" value="<?php echo esc_attr($cancel_reason_input); ?>" />
                                </label>
                                <button type="submit" class="button button-primary aegis-cancel-submit" data-aegis-allow-readonly="1">申请撤销订单</button>
                            </form>
                        <?php elseif ($cancel_requested) : ?>
                            <p class="aegis-t-a6" style="margin-top:8px; color:#d97706;">撤销申请中，请等待审批。</p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="aegis-t-a6" style="color:#6b7280;">请选择一条订单以查看详情。</div>
                <?php endif; ?>
            </div>
            <div class="aegis-orders-drawer-extension" aria-hidden="true"></div>
        </div>
        <div class="aegis-orders-drawer-footer">
            <span class="aegis-t-a6 aegis-orders-drawer-hint" hidden style="margin-right:auto; color:#d97706;">撤销申请请使用上方按钮提交</span>
            <button type="button" class="button aegis-orders-drawer-secondary" disabled>保存修改</button>
            <button type="button" class="button button-primary aegis-orders-drawer-primary" disabled>提交/确认</button>
            <button type="button" class="button aegis-orders-drawer-close">关闭</button>
        </div>
    </div>
</aside>

<aside id="aegis-help-panel" class="aegis-help-panel" hidden aria-hidden="true">
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
        const val = input.value.trim();
        if (!val) {
            priceBox.textContent = '—';
            priceBox.style.color = '';
            return;
        }
        if (priceMap[val]) {
            priceBox.textContent = priceMap[val].label;
            priceBox.style.color = '';
        } else {
            priceBox.textContent = '无价，禁止下单';
            priceBox.style.color = '#d63638';
        }
    }
    function attach(row, container) {
        const input = row.querySelector('input[name="order_item_ean[]"]');
        if (input) {
            input.addEventListener('change', function() { updateRowPrice(row); });
            input.addEventListener('blur', function() { updateRowPrice(row); });
        }
        const addBtn = row.querySelector('.aegis-row-add');
        if (addBtn) {
            addBtn.addEventListener('click', function() {
                if (!container) return;
                const newRow = buildRow();
                row.after(newRow);
                attach(newRow, container);
                syncRowControls(container);
            });
        }
        const delBtn = row.querySelector('.aegis-row-del');
        if (delBtn) {
            delBtn.addEventListener('click', function() {
                if (!container || container.children.length <= 1) {
                    return;
                }
                row.remove();
                syncRowControls(container);
            });
        }
        updateRowPrice(row);
    }

    function buildRow() {
        const row = document.createElement('div');
        row.className = 'order-item-row';
        row.style.display = 'grid';
        row.style.gridTemplateColumns = '2fr 1fr 1fr auto';
        row.style.gap = '8px';
        row.style.alignItems = 'end';
        row.innerHTML = '<label class="aegis-t-a6">SKU <input list="aegis-sku-list" name="order_item_ean[]" required /></label>' +
            '<label class="aegis-t-a6">数量 <input type="number" name="order_item_qty[]" min="1" step="1" value="1" required /></label>' +
            '<div class="aegis-t-a6">单价<div class="aegis-t-a6 order-item-price" style="font-weight:bold;">—</div></div>' +
            '<div class="aegis-order-row-actions">' +
            '<button type="button" class="aegis-row-add" aria-label="新增一行">＋</button>' +
            '<button type="button" class="aegis-row-del" aria-label="删除此行">－</button>' +
            '</div>';
        return row;
    }

    function syncRowControls(container) {
        if (!container) return;
        const rows = container.querySelectorAll('.order-item-row');
        const disableDelete = rows.length <= 1;
        rows.forEach((row) => {
            const delBtn = row.querySelector('.aegis-row-del');
            if (delBtn) {
                delBtn.disabled = disableDelete;
            }
        });
    }

    function init(container) {
        if (!container) {
            return;
        }
        const createContainer = container.querySelector('#aegis-order-items');
        if (createContainer) {
            createContainer.querySelectorAll('.order-item-row').forEach((row) => attach(row, createContainer));
            syncRowControls(createContainer);
        }

        const editContainer = container.querySelector('#aegis-order-edit-items');
        if (editContainer) {
            editContainer.querySelectorAll('.order-item-row').forEach((row) => attach(row, editContainer));
            syncRowControls(editContainer);
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
    const footerHint = drawer ? drawer.querySelector('.aegis-orders-drawer-hint') : null;

    if (!drawer || !drawerContent) {
        return;
    }

    const canonicalizeUrl = (inputUrl) => {
        try {
            const url = new URL(inputUrl, window.location.href);
            if (url.pathname.includes('/index.php/')) {
                url.pathname = url.pathname.replace('/index.php/', '/');
            }
            return url.toString();
        } catch (error) {
            return inputUrl;
        }
    };

    const canonicalLocation = canonicalizeUrl(window.location.href);
    if (canonicalLocation !== window.location.href) {
        window.history.replaceState(null, '', canonicalLocation);
    }

    const setFooterState = () => {
        const primaryAction = drawerContent.querySelector('.aegis-orders-primary-action');
        const secondaryAction = drawerContent.querySelector('.aegis-orders-secondary-action');
        const cancelForm = drawerContent.querySelector('.aegis-cancel-form');

        if (footerHint) {
            footerHint.hidden = !cancelForm;
        }

        if (cancelForm) {
            if (footerPrimary) {
                footerPrimary.disabled = true;
                footerPrimary.onclick = null;
            }
            if (footerSecondary) {
                footerSecondary.disabled = true;
                footerSecondary.onclick = null;
            }
            return;
        }

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

            const allowReadonly = field.dataset.aegisAllowReadonly === '1' || field.type === 'hidden';
            const isEditField = field.dataset.aegisEditField === '1';
            const isEditAction = field.dataset.aegisEditAction === '1';

            if (isReadOnly) {
                if (allowReadonly) {
                    field.disabled = false;
                } else if (isEditField || isEditAction) {
                    field.disabled = true;
                } else if (field.dataset.aegisOriginalDisabled === '1') {
                    field.disabled = true;
                } else {
                    field.disabled = false;
                }
            } else if (field.dataset.aegisOriginalDisabled === '0') {
                field.disabled = false;
            }
        });

        if (footerPrimary) {
            const primaryAction = drawerContent.querySelector('.aegis-orders-primary-action');
            const allowPrimary = primaryAction && primaryAction.dataset.aegisAllowReadonly === '1';
            footerPrimary.disabled = isReadOnly ? !allowPrimary : footerPrimary.disabled;
        }
        if (footerSecondary) {
            const secondaryAction = drawerContent.querySelector('.aegis-orders-secondary-action');
            const allowSecondary = secondaryAction && secondaryAction.dataset.aegisAllowReadonly === '1';
            footerSecondary.disabled = isReadOnly ? !allowSecondary : footerSecondary.disabled;
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
        window.history.replaceState({}, '', canonicalizeUrl(url.toString()));
    };

    drawerCloseButtons.forEach((button) => button.addEventListener('click', closeDrawer));

    const refreshDrawer = (url, mode) => {
        if (!url) {
            openDrawer(mode);
            return;
        }

        const canonicalUrl = canonicalizeUrl(url);
        const fetchUrl = new URL(canonicalUrl, window.location.href);
        fetchUrl.searchParams.set('_drawer_ts', Date.now().toString());

        fetch(fetchUrl.toString(), { cache: 'no-store', credentials: 'same-origin' })
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
                    if (window.AegisOrders && typeof window.AegisOrders.initNotes === 'function') {
                        window.AegisOrders.initNotes(drawerContent);
                    }
                }
                openDrawer(mode);
                window.history.replaceState({}, '', canonicalUrl);
            })
            .catch(() => {
                openDrawer(mode);
            });
    };

    ordersPage.querySelectorAll('.aegis-orders-open-drawer').forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.getAttribute('data-order-url');
            const mode = button.getAttribute('data-mode') || 'view';
            refreshDrawer(canonicalizeUrl(url), mode);
        });
    });

    if (window.location.search.includes('order_id=')) {
        openDrawer('view');
    } else if (drawer.dataset.autoOpen === '1') {
        refreshDrawer(canonicalizeUrl(drawer.dataset.orderUrl), 'view');
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

    const initNotes = (container) => {
        if (!container) return;
        container.querySelectorAll('.aegis-note-field').forEach((field) => {
            if (field.dataset.aegisNoteBound) {
                return;
            }
            field.dataset.aegisNoteBound = '1';
            const toggle = field.querySelector('.aegis-note-toggle');
            const panel = field.querySelector('.aegis-note-panel');
            if (!toggle || !panel) return;
            const input = panel.querySelector('input, textarea');
            const setOpen = (isOpen) => {
                panel.hidden = !isOpen;
                toggle.setAttribute('aria-expanded', String(isOpen));
            };
            toggle.addEventListener('click', function() {
                setOpen(panel.hidden);
            });
            if (input && input.value.trim()) {
                setOpen(true);
            }
        });
    };

    window.AegisOrders = window.AegisOrders || {};
    window.AegisOrders.initNotes = initNotes;

    initNotes(ordersPage);

    const helpBtn = ordersPage.querySelector('.aegis-help-btn');
    const helpPanel = document.getElementById('aegis-help-panel');
    const helpClose = helpPanel ? helpPanel.querySelector('.aegis-help-close') : null;
    if (helpBtn && helpPanel) {
        const closeHelp = function() {
            helpPanel.hidden = true;
            helpBtn.setAttribute('aria-expanded', 'false');
            helpPanel.setAttribute('aria-hidden', 'true');
        };
        helpBtn.addEventListener('click', function() {
            const isOpen = !helpPanel.hidden;
            helpPanel.hidden = isOpen;
            helpBtn.setAttribute('aria-expanded', String(!isOpen));
            helpPanel.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
        });
        if (helpClose) {
            helpClose.addEventListener('click', closeHelp);
        }
    }
})();
</script>
