<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url = $context_data['base_url'] ?? '';
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
$pending_decision = $context_data['pending_decision'] ?? null;
$copyable_statuses = [
    AEGIS_Returns::STATUS_SALES_REJECTED,
    AEGIS_Returns::STATUS_WAREHOUSE_REJECTED,
    AEGIS_Returns::STATUS_FINANCE_REJECTED,
];

$list_url = $base_url;
$create_url = add_query_arg('create', '1', $base_url);
$show_create = 'create' === $view_mode;
$show_edit = 'edit' === $view_mode;

$total_items = count($items);
$pass_items = 0;
$fail_items = 0;
$need_override_items = 0;
$pass_override_items = 0;
foreach ($items as $item) {
    $validation_status = (string) ($item->validation_status ?? 'pending');
    if ('pass' === $validation_status) {
        $pass_items++;
    } elseif ('pass_override' === $validation_status) {
        $pass_override_items++;
    } elseif ('need_override' === $validation_status) {
        $need_override_items++;
    } elseif ('fail' === $validation_status) {
        $fail_items++;
    }
}
?>
<div class="aegis-t-a4 aegis-returns-page">
    <div class="aegis-returns-header">
        <div class="aegis-t-a2">退货申请</div>
        <div class="aegis-returns-header-actions">
            <?php if ('list' === $view_mode) : ?>
                <a class="button" href="<?php echo esc_url($create_url); ?>">新建退货单</a>
            <?php else : ?>
                <a class="button" href="<?php echo esc_url($list_url); ?>">返回列表</a>
                <?php if ($show_edit && !empty($request) && $can_withdraw) : ?>
                    <form method="post" class="inline-form" onsubmit="return confirm('确认撤回该已提交单据？');">
                        <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                        <input type="hidden" name="returns_action" value="withdraw_request" />
                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                        <button type="submit" class="button">撤回</button>
                    </form>
                <?php endif; ?>
                <?php if ($show_edit && !empty($request) && in_array($request->status, $copyable_statuses, true)) : ?>
                    <form method="post" class="inline-form" onsubmit="return confirm('确认复制为新草稿？');">
                        <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                        <input type="hidden" name="returns_action" value="copy_to_new_draft" />
                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                        <button type="submit" class="button">复制为新草稿</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <p class="aegis-returns-help aegis-t-a6">系统按出库扫码时间 + 售后有效期自动校验；不通过条目可删除或联系 HQ/销售获取特批码后在行内验证。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <?php if ('list' === $view_mode) : ?>
        <section class="aegis-card">
            <div class="aegis-table-wrap">
                <table class="aegis-table aegis-returns-table aegis-t-a6" style="width:100%;">
                    <thead>
                        <tr>
                            <th>单号</th>
                            <th>状态</th>
                            <th>条目数</th>
                            <th>更新时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)) : ?>
                            <tr><td colspan="5" class="aegis-t-a6" style="padding:12px;">暂无退货单。</td></tr>
                        <?php else : ?>
                            <?php foreach ($requests as $row) : ?>
                                <?php $edit_url = add_query_arg('request_id', (int) $row->id, $base_url); ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?php echo esc_html($row->request_no); ?></td>
                                    <td><?php echo esc_html($status_labels[$row->status] ?? $row->status); ?></td>
                                    <td><?php echo esc_html($counts[(int) $row->id] ?? 0); ?></td>
                                    <td style="white-space:nowrap;"><?php echo esc_html($row->updated_at); ?></td>
                                    <td style="white-space:nowrap;">
                                        <div class="table-actions">
                                            <?php if (AEGIS_Returns::STATUS_DRAFT === $row->status) : ?>
                                                <a class="button" href="<?php echo esc_url($edit_url); ?>">编辑</a>
                                                <form method="post" class="inline-form" onsubmit="return confirm('确定删除该草稿吗？');">
                                                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                                    <input type="hidden" name="returns_action" value="delete_draft" />
                                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $row->id); ?>" />
                                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                                                    <button type="submit" class="button">删除</button>
                                                </form>
                                            <?php elseif (AEGIS_Returns::STATUS_SUBMITTED === $row->status) : ?>
                                                <a class="button" href="<?php echo esc_url($edit_url); ?>">查看</a>
                                                <form method="post" class="inline-form" onsubmit="return confirm('确认撤回该已提交单据？');">
                                                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                                    <input type="hidden" name="returns_action" value="withdraw_request" />
                                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $row->id); ?>" />
                                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                                                    <button type="submit" class="button">撤回</button>
                                                </form>
                                            <?php elseif (in_array($row->status, $copyable_statuses, true)) : ?>
                                                <a class="button" href="<?php echo esc_url($edit_url); ?>">查看</a>
                                                <form method="post" class="inline-form" onsubmit="return confirm('确认复制为新草稿？');">
                                                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                                    <input type="hidden" name="returns_action" value="copy_to_new_draft" />
                                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $row->id); ?>" />
                                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                                    <button type="submit" class="button">复制为新草稿</button>
                                                </form>
                                            <?php else : ?>
                                                <a class="button" href="<?php echo esc_url($edit_url); ?>">查看</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php elseif ($show_create) : ?>
        <section class="aegis-card aegis-portal-card">
            <div class="aegis-card-header">
                <div class="aegis-card-title aegis-t-a4">开始申请退货</div>
            </div>
            <?php if ($dealer_blocked) : ?>
                <p class="aegis-t-a6" style="color:#d63638;">经销商账号不可创建退货申请，请联系管理员。</p>
            <?php else : ?>
                <form method="post" class="aegis-t-a6">
                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                    <input type="hidden" name="returns_action" value="create_empty_draft" />
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                    <button type="submit" class="button button-primary">创建草稿并开始录入</button>
                </form>
            <?php endif; ?>
        </section>
    <?php elseif ($show_edit) : ?>
        <div class="aegis-returns-layout">
            <section class="aegis-card aegis-portal-card">
                <div class="aegis-card-header">
                    <div class="aegis-card-title aegis-t-a4"><?php echo $can_edit ? '编辑草稿' : '查看退货单'; ?></div>
                </div>
                <?php if (empty($request)) : ?>
                    <p class="aegis-t-a6" style="color:#d63638;">单据不存在或无权限。</p>
                <?php else : ?>
                    <div class="aegis-t-a6" style="margin-bottom:8px;">单号：<?php echo esc_html($request->request_no); ?></div>
                    <div class="aegis-t-a6" style="margin-bottom:12px;">状态：<?php echo esc_html($status_labels[$request->status] ?? $request->status); ?></div>

                    <?php if ($can_edit) : ?>
                        <form method="post" class="aegis-t-a6">
                            <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                            <input type="hidden" name="returns_action" value="validate_code" />
                            <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                            <div class="aegis-returns-code-entry">
                                <input name="code_input" class="aegis-portal-input" type="text" placeholder="请输入/扫码防伪码，回车校验并加入" autocomplete="off" />
                                <button class="aegis-portal-button is-primary" type="submit">加入</button>
                            </div>
                        </form>

                        <?php if (!empty($pending_decision)) : ?>
                            <div class="notice notice-warning" style="margin-top:12px;">
                                <p class="aegis-t-a6">该防伪码不符合自动规则：<?php echo esc_html($pending_decision['fail_reason_msg'] ?? ''); ?></p>
                                <p class="aegis-t-a6">请选择：放弃录入 或 申请特批录入（需联系 HQ/销售获取特批码）。</p>
                                <div class="aegis-returns-header-actions" style="margin-top:8px;">
                                    <a class="button" href="<?php echo esc_url(add_query_arg('request_id', (int) $request->id, $base_url)); ?>">放弃录入</a>
                                    <form method="post" class="inline-form">
                                        <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                        <input type="hidden" name="returns_action" value="add_need_override" />
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                        <input type="hidden" name="code_value" value="<?php echo esc_attr((string) ($pending_decision['code_value'] ?? '')); ?>" />
                                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                        <button type="submit" class="button">申请特批录入</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <details style="margin-top:12px;">
                            <summary class="aegis-portal-button is-secondary">批量录入（可选）</summary>
                            <form method="post" style="margin-top:10px;">
                                <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                <input type="hidden" name="returns_action" value="bulk_add_codes" />
                                <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <textarea class="aegis-returns-code-textarea" name="code_values" id="aegis_returns_code_values" placeholder="一行一个，或用空格/逗号分隔；最多 500 条"><?php echo esc_textarea($code_text); ?></textarea>
                                <div class="aegis-returns-mini">支持换行/空格/逗号分隔，最多 500 条。</div>
                                <button type="submit" class="button" style="margin-top:10px;">批量加入清单</button>
                            </form>
                        </details>
                    <?php else : ?>
                        <p class="aegis-t-a6" style="color:#d63638;">已锁定不可编辑。</p>
                    <?php endif; ?>

                    <div style="margin-top:14px;">
                        <?php if ($need_override_items > 0) : ?>
                            <p class="aegis-t-a6" style="color:#d63638; margin-bottom:8px;">存在需特批条目未验证，无法提交。请在清单行内输入特批码验证。</p>
                        <?php endif; ?>
                        <?php if ($can_edit) : ?>
                            <form method="post" class="aegis-t-a6" onsubmit="return confirm('提交后将锁定草稿内容，确认提交？');">
                                <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                <input type="hidden" name="returns_action" value="submit_request" />
                                <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <button type="submit" class="button" <?php disabled($need_override_items > 0 || $fail_items > 0, true); ?>>提交申请</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="aegis-card aegis-portal-card">
                <div class="aegis-card-header">
                    <div class="aegis-card-title aegis-t-a4">退货清单（校验结果）</div>
                </div>
                <div class="aegis-returns-kpis">
                    <div class="aegis-returns-kpi"><div class="label">总条目</div><div class="value"><?php echo esc_html((string) $total_items); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">通过</div><div class="value"><?php echo esc_html((string) ($pass_items + $pass_override_items)); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">需特批</div><div class="value"><?php echo esc_html((string) $need_override_items); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">不通过</div><div class="value"><?php echo esc_html((string) $fail_items); ?></div></div>
                </div>

                <div class="aegis-table-wrap">
                    <table class="aegis-portal-table aegis-returns-results-table aegis-t-a6" style="width:100%; margin-top:12px;">
                        <thead>
                            <tr>
                                <th>防伪码</th>
                                <th>出库时间</th>
                                <th>截止时间</th>
                                <th>状态</th>
                                <th>原因</th>
                                <th>特批码</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)) : ?>
                                <tr><td colspan="7">暂无条目。</td></tr>
                            <?php else : ?>
                                <?php foreach ($items as $item) : ?>
                                    <?php $status = (string) ($item->validation_status ?? 'pending'); ?>
                                    <tr>
                                        <td><?php echo esc_html(AEGIS_System::format_code_display($item->code_value ?? '')); ?></td>
                                        <td><?php echo esc_html($item->outbound_scanned_at ?? ''); ?></td>
                                        <td><?php echo esc_html($item->after_sales_deadline_at ?? ''); ?></td>
                                        <td>
                                            <?php if ('pass' === $status) : ?>
                                                <span class="status-badge is-active">通过</span>
                                            <?php elseif ('pass_override' === $status) : ?>
                                                <span class="status-badge is-active">特批通过</span>
                                            <?php elseif ('need_override' === $status) : ?>
                                                <span class="status-badge is-inactive">需特批</span>
                                                <div class="aegis-returns-mini">联系 HQ/销售获取特批码</div>
                                            <?php elseif ('fail' === $status) : ?>
                                                <span class="status-badge is-inactive">不可提交</span>
                                            <?php else : ?>
                                                <span class="status-badge">待校验</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo in_array($status, ['need_override', 'fail'], true) ? esc_html($item->fail_reason_msg ?? '') : '-'; ?></td>
                                        <td>
                                            <?php if ('need_override' === $status && $can_edit && !empty($request)) : ?>
                                                <form method="post" class="inline-form" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                                    <input type="hidden" name="returns_action" value="apply_override" />
                                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                                    <input type="hidden" name="code_value" value="<?php echo esc_attr((string) $item->code_value); ?>" />
                                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                                    <input type="text" name="override_plain_code" placeholder="输入特批码" required />
                                                    <button type="submit" class="button">验证</button>
                                                </form>
                                            <?php else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($can_edit && !empty($request)) : ?>
                                                <form method="post" class="inline-form" onsubmit="return confirm('确认移除该条目？');">
                                                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                                    <input type="hidden" name="returns_action" value="remove_item" />
                                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                                    <input type="hidden" name="item_id" value="<?php echo esc_attr((int) $item->id); ?>" />
                                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                                    <button type="submit" class="button">移除</button>
                                                </form>
                                            <?php else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>
