<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url = $context_data['base_url'] ?? '';
$portal_url = $context_data['portal_url'] ?? '';
$messages = $context_data['messages'] ?? [];
$errors = $context_data['errors'] ?? [];
$dealer = $context_data['dealer'] ?? null;
$dealer_blocked = !empty($context_data['dealer_blocked']);
$view_mode = $context_data['view_mode'] ?? 'list';
$requests = $context_data['requests'] ?? [];
$counts = $context_data['counts'] ?? [];
$request = $context_data['request'] ?? null;
$items = $context_data['items'] ?? [];
$code_text = $context_data['code_text'] ?? '';
$can_edit = !empty($context_data['can_edit']);
$can_withdraw = !empty($context_data['can_withdraw']);
$idempotency = $context_data['idempotency'] ?? wp_generate_uuid4();
$status_labels = $context_data['status_labels'] ?? [];
$copyable_statuses = [
    AEGIS_Returns::STATUS_SALES_REJECTED,
    AEGIS_Returns::STATUS_WAREHOUSE_REJECTED,
    AEGIS_Returns::STATUS_FINANCE_REJECTED,
];

$list_url = $base_url;
$create_url = add_query_arg('create', '1', $base_url);
$show_create = 'create' === $view_mode;
$show_edit = 'edit' === $view_mode;
?>
<div class="aegis-t-a4" style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
    <div class="aegis-t-a2">退货申请</div>
    <div>
        <?php if ('list' === $view_mode) : ?>
            <a class="button" href="<?php echo esc_url($create_url); ?>">新建退货单</a>
        <?php else : ?>
            <a class="button" href="<?php echo esc_url($list_url); ?>">返回列表</a>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($messages as $msg) : ?>
    <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
<?php endforeach; ?>
<?php foreach ($errors as $msg) : ?>
    <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
<?php endforeach; ?>

<?php if ('list' === $view_mode) : ?>
    <div class="aegis-portal-table" style="overflow:auto;">
        <table class="aegis-t-a6" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="text-align:left; border-bottom:1px solid #e5e5e5;">
                    <th style="padding:8px;">单号</th>
                    <th style="padding:8px;">状态</th>
                    <th style="padding:8px;">条目数</th>
                    <th style="padding:8px;">更新时间</th>
                    <th style="padding:8px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)) : ?>
                    <tr><td colspan="5" class="aegis-t-a6" style="padding:12px;">暂无退货单。</td></tr>
                <?php else : ?>
                    <?php foreach ($requests as $row) : ?>
                        <?php $edit_url = add_query_arg('request_id', (int) $row->id, $base_url); ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:8px; white-space:nowrap;"><?php echo esc_html($row->request_no); ?></td>
                            <td style="padding:8px;"><?php echo esc_html($status_labels[$row->status] ?? $row->status); ?></td>
                            <td style="padding:8px;"><?php echo esc_html($counts[(int) $row->id] ?? 0); ?></td>
                            <td style="padding:8px; white-space:nowrap;"><?php echo esc_html($row->updated_at); ?></td>
                            <td style="padding:8px; white-space:nowrap;">
                                <?php if (AEGIS_Returns::STATUS_DRAFT === $row->status) : ?>
                                    <a class="button" href="<?php echo esc_url($edit_url); ?>">编辑</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除该草稿吗？');">
                                        <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                        <input type="hidden" name="returns_action" value="delete_draft" />
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $row->id); ?>" />
                                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                                        <button type="submit" class="button">删除</button>
                                    </form>
                                <?php elseif (AEGIS_Returns::STATUS_SUBMITTED === $row->status) : ?>
                                    <a class="button" href="<?php echo esc_url($edit_url); ?>">查看</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确认撤回该已提交单据？');">
                                        <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                        <input type="hidden" name="returns_action" value="withdraw_request" />
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $row->id); ?>" />
                                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                                        <button type="submit" class="button">撤回</button>
                                    </form>
                                <?php elseif (in_array($row->status, $copyable_statuses, true)) : ?>
                                    <a class="button" href="<?php echo esc_url($edit_url); ?>">查看</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确认复制为新草稿？');">
                                        <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                        <input type="hidden" name="returns_action" value="copy_to_new_draft" />
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $row->id); ?>" />
                                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                        <button type="submit" class="button">复制为新草稿</button>
                                    </form>
                                <?php else : ?>
                                    <a class="button" href="<?php echo esc_url($edit_url); ?>">查看</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($show_create) : ?>
    <section class="aegis-card">
        <div class="aegis-card-header">
            <div class="aegis-card-title aegis-t-a4">新建退货单</div>
        </div>
        <div class="aegis-t-a6" style="margin-bottom:12px; color:#555;">保存草稿会自动校验：归属经销商、出库时间、售后期。未通过需联系销售/HQ，后续支持特批码。</div>
        <?php if ($dealer_blocked) : ?>
            <p class="aegis-t-a6" style="color:#d63638;">经销商账号不可创建退货申请，请联系管理员。</p>
        <?php else : ?>
            <form method="post" class="aegis-t-a6">
                <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                <input type="hidden" name="returns_action" value="create_draft" />
                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                <div class="aegis-t-a6" style="margin-bottom:8px;">经销商：<?php echo esc_html($dealer ? $dealer->dealer_name : ''); ?></div>
                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">联系人
                    <input type="text" name="contact_name" style="width:100%;" />
                </label>
                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">联系电话
                    <input type="text" name="contact_phone" style="width:100%;" />
                </label>
                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">退货原因
                    <input type="text" name="reason_code" style="width:100%;" />
                </label>
                <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">备注
                    <textarea name="remark" rows="3" style="width:100%;"></textarea>
                </label>
                <label class="aegis-t-a6" style="display:block; margin-bottom:12px;">防伪码列表
                    <textarea name="code_values" rows="6" style="width:100%;" placeholder="一行一个，或用空格/逗号分隔；最多 500 条"></textarea>
                </label>
                <button type="submit" class="button button-primary">创建草稿</button>
            </form>
        <?php endif; ?>
    </section>
<?php elseif ($show_edit) : ?>
    <section class="aegis-card">
        <div class="aegis-card-header">
            <div class="aegis-card-title aegis-t-a4">编辑退货单</div>
        </div>
        <?php if (empty($request)) : ?>
            <p class="aegis-t-a6" style="color:#d63638;">单据不存在或无权限。</p>
        <?php else : ?>
            <?php $status_label = $status_labels[$request->status] ?? $request->status; ?>
            <div class="aegis-t-a6" style="margin-bottom:8px;">单号：<?php echo esc_html($request->request_no); ?></div>
            <div class="aegis-t-a6" style="margin-bottom:8px;">状态：<?php echo esc_html($status_label); ?></div>
            <div class="aegis-t-a6" style="margin-bottom:8px;">创建时间：<?php echo esc_html($request->created_at); ?></div>
            <div class="aegis-t-a6" style="margin-bottom:12px;">更新时间：<?php echo esc_html($request->updated_at); ?></div>
            <div class="aegis-t-a6" style="margin-bottom:12px; color:#555;">
                当前状态：<?php echo esc_html($status_label); ?>。
                <?php if (AEGIS_Returns::STATUS_DRAFT === $request->status) : ?>草稿：可编辑，可提交。<?php else : ?>已提交：不可编辑<?php echo $can_withdraw ? '，可撤回。' : '。'; ?><?php endif; ?>
            </div>

            <?php if (!$can_edit) : ?>
                <p class="aegis-t-a6" style="color:#d63638;">已锁定不可编辑。</p>
                <?php if (in_array($request->status, $copyable_statuses, true)) : ?>
                    <p class="aegis-t-a6" style="margin-bottom:12px; color:#555;">该单已驳回/不通过，内容不可修改。可复制为新草稿重新发起。</p>
                <?php endif; ?>
                <label class="aegis-t-a6" style="display:block;">防伪码列表
                    <textarea rows="6" style="width:100%;" readonly><?php echo esc_textarea($code_text); ?></textarea>
                </label>
                <?php if ($can_withdraw) : ?>
                    <form method="post" class="aegis-t-a6" style="margin-top:12px;" onsubmit="return confirm('确认撤回该已提交单据？');">
                        <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                        <input type="hidden" name="returns_action" value="withdraw_request" />
                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                        <button type="submit" class="button">撤回</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($request->status, $copyable_statuses, true)) : ?>
                    <form method="post" class="aegis-t-a6" style="margin-top:12px;" onsubmit="return confirm('确认复制为新草稿？');">
                        <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                        <input type="hidden" name="returns_action" value="copy_to_new_draft" />
                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                        <button type="submit" class="button">复制为新草稿</button>
                    </form>
                <?php endif; ?>
            <?php else : ?>
                <form method="post" class="aegis-t-a6">
                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                    <input type="hidden" name="returns_action" value="update_draft" />
                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">联系人
                        <input type="text" name="contact_name" value="<?php echo esc_attr($request->contact_name); ?>" style="width:100%;" />
                    </label>
                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">联系电话
                        <input type="text" name="contact_phone" value="<?php echo esc_attr($request->contact_phone); ?>" style="width:100%;" />
                    </label>
                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">退货原因
                        <input type="text" name="reason_code" value="<?php echo esc_attr($request->reason_code); ?>" style="width:100%;" />
                    </label>
                    <label class="aegis-t-a6" style="display:block; margin-bottom:8px;">备注
                        <textarea name="remark" rows="3" style="width:100%;"><?php echo esc_textarea($request->remark); ?></textarea>
                    </label>
                    <label class="aegis-t-a6" style="display:block; margin-bottom:12px;">防伪码列表
                        <textarea name="code_values" rows="6" style="width:100%;" placeholder="一行一个，或用空格/逗号分隔；最多 500 条"><?php echo esc_textarea($code_text); ?></textarea>
                    </label>
                    <button type="submit" class="button button-primary">保存草稿</button>
                </form>
                <p class="aegis-t-a6" style="margin-top:8px; color:#555;">未通过条目如已获得 HQ 特批码，可在对应行输入特批码强制通过。</p>
                <?php
                $has_invalid_item = false;
                foreach ($items as $item) {
                    if (!in_array(($item->validation_status ?? 'pending'), ['pass', 'pass_override'], true)) {
                        $has_invalid_item = true;
                        break;
                    }
                }
                ?>
                <form method="post" class="aegis-t-a6" style="margin-top:10px;" onsubmit="return confirm('提交后将锁定草稿内容，确认提交？');">
                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                    <input type="hidden" name="returns_action" value="submit_request" />
                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                    <button type="submit" class="button" <?php disabled($has_invalid_item, true); ?>>提交（提交后将锁定，需销售审核）</button>
                </form>
            <?php endif; ?>

            <?php if (!empty($items)) : ?>
                <div class="aegis-portal-table" style="overflow:auto; margin-top:16px;">
                    <table class="aegis-t-a6" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; border-bottom:1px solid #e5e5e5;">
                                <th style="padding:8px;">防伪码</th>
                                <th style="padding:8px;">结果</th>
                                <th style="padding:8px;">原因</th>
                                <th style="padding:8px;">出库时间</th>
                                <th style="padding:8px;">截止时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item) : ?>
                                <?php
                                $validation_status = (string) ($item->validation_status ?? 'pending');
                                $is_override = 'pass_override' === $validation_status;
                                $is_pass = in_array($validation_status, ['pass', 'pass_override'], true);
                                ?>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:8px; white-space:nowrap;"><?php echo esc_html(AEGIS_System::format_code_display($item->code_value ?? '')); ?></td>
                                    <td style="padding:8px; white-space:nowrap; color:<?php echo $is_pass ? '#1a7f37' : '#d63638'; ?>;">
                                        <?php echo $is_pass ? ($is_override ? '特批通过' : '通过') : '不通过'; ?>
                                    </td>
                                    <td style="padding:8px;">
                                        <?php echo esc_html($item->fail_reason_msg ?? ''); ?>
                                        <?php if (!$is_pass && $can_edit) : ?>
                                            <form method="post" style="margin-top:8px; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                                <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                                <input type="hidden" name="returns_action" value="apply_override" />
                                                <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                                <input type="hidden" name="code_value" value="<?php echo esc_attr((string) $item->code_value); ?>" />
                                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                                <input type="text" name="override_plain_code" placeholder="输入特批码" style="min-width:220px;" required />
                                                <button type="submit" class="button">验证并放行</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px; white-space:nowrap;"><?php echo esc_html($item->outbound_scanned_at ?? ''); ?></td>
                                    <td style="padding:8px; white-space:nowrap;"><?php echo esc_html($item->after_sales_deadline_at ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
