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
$idempotency = $context_data['idempotency'] ?? wp_generate_uuid4();
$status_labels = $context_data['status_labels'] ?? [];

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
    <div class="aegis-t-a6" style="margin-bottom:12px; color:#555;">本期仅支持草稿创建与编辑，提交/审核/仓库/财务将在后续 PR 实现。</div>
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
                    <tr><td colspan="5" class="aegis-t-a6" style="padding:12px;">暂无草稿。</td></tr>
                <?php else : ?>
                    <?php foreach ($requests as $row) : ?>
                        <?php $edit_url = add_query_arg('request_id', (int) $row->id, $base_url); ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:8px; white-space:nowrap;"><?php echo esc_html($row->request_no); ?></td>
                            <td style="padding:8px;">
                                <?php echo esc_html($status_labels[$row->status] ?? $row->status); ?>
                            </td>
                            <td style="padding:8px;">
                                <?php echo esc_html($counts[(int) $row->id] ?? 0); ?>
                            </td>
                            <td style="padding:8px; white-space:nowrap;"><?php echo esc_html($row->updated_at); ?></td>
                            <td style="padding:8px; white-space:nowrap;">
                                <a class="button" href="<?php echo esc_url($edit_url); ?>">编辑</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定删除该草稿吗？');">
                                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                    <input type="hidden" name="returns_action" value="delete_draft" />
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $row->id); ?>" />
                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                                    <button type="submit" class="button">删除</button>
                                </form>
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
            <?php
            $status_label = $status_labels[$request->status] ?? $request->status;
            $is_editable = AEGIS_Returns::STATUS_DRAFT === $request->status
                && empty($request->hard_locked_at)
                && empty($request->content_locked_at);
            ?>
            <div class="aegis-t-a6" style="margin-bottom:8px;">单号：<?php echo esc_html($request->request_no); ?></div>
            <div class="aegis-t-a6" style="margin-bottom:8px;">状态：<?php echo esc_html($status_label); ?></div>
            <div class="aegis-t-a6" style="margin-bottom:8px;">创建时间：<?php echo esc_html($request->created_at); ?></div>
            <div class="aegis-t-a6" style="margin-bottom:12px;">更新时间：<?php echo esc_html($request->updated_at); ?></div>

            <?php if (!$is_editable) : ?>
                <p class="aegis-t-a6" style="color:#d63638;">已锁定不可编辑。</p>
                <label class="aegis-t-a6" style="display:block;">防伪码列表
                    <textarea rows="6" style="width:100%;" readonly><?php echo esc_textarea($code_text); ?></textarea>
                </label>
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
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
