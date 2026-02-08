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

$total_items = count($items);
$pass_items = 0;
$fail_items = 0;
$pass_override_items = 0;
foreach ($items as $item) {
    $validation_status = (string) ($item->validation_status ?? 'pending');
    if ('pass' === $validation_status) {
        $pass_items++;
    } elseif ('pass_override' === $validation_status) {
        $pass_override_items++;
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
    <?php elseif ($show_create || $show_edit) : ?>
        <div class="aegis-returns-layout">
            <section class="aegis-card aegis-portal-card">
                <?php if ($show_create) : ?>
                    <div class="aegis-card-header">
                        <div class="aegis-card-title aegis-t-a4">新建退货（草稿）</div>
                    </div>
                    <?php if ($dealer_blocked) : ?>
                        <p class="aegis-t-a6" style="color:#d63638;">经销商账号不可创建退货申请，请联系管理员。</p>
                    <?php else : ?>
                        <form method="post" class="aegis-t-a6">
                            <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                            <input type="hidden" name="returns_action" value="create_draft" />
                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                            <input type="hidden" name="contact_name" value="" />
                            <input type="hidden" name="contact_phone" value="" />
                            <input type="hidden" name="reason_code" value="" />

                            <div class="aegis-t-a6" style="margin-bottom:10px;">经销商：<?php echo esc_html($dealer ? $dealer->dealer_name : ''); ?></div>

                            <div class="aegis-returns-code-entry">
                                <input id="aegis_returns_code_input" class="aegis-portal-input" type="text" placeholder="请输入/扫码防伪码，回车加入" autocomplete="off" />
                                <button type="button" class="aegis-portal-button is-primary" id="aegis_returns_add_btn">加入</button>
                            </div>
                            <div class="aegis-returns-mini" style="margin-top:6px;">提示：本阶段需点击“保存草稿”后进行系统校验与展示结果。</div>

                            <details style="margin-top:12px;">
                                <summary class="aegis-portal-button is-secondary">批量录入（可选）</summary>
                                <div style="margin-top:10px;">
                                    <textarea class="aegis-returns-code-textarea" name="code_values" id="aegis_returns_code_values" placeholder="一行一个，或用空格/逗号分隔；最多 500 条"><?php echo esc_textarea($code_text); ?></textarea>
                                    <div class="aegis-returns-mini">支持换行/空格/逗号分隔，最多 500 条。</div>
                                </div>
                            </details>

                            <details style="margin-top:12px;">
                                <summary>备注（可选）</summary>
                                <textarea name="remark" rows="3" style="width:100%; margin-top:8px;"></textarea>
                            </details>

                            <button type="submit" class="button button-primary" style="margin-top:12px;">保存草稿</button>
                        </form>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="aegis-card-header">
                        <div class="aegis-card-title aegis-t-a4"><?php echo $can_edit ? '编辑草稿' : '查看退货单'; ?></div>
                    </div>
                    <?php if (empty($request)) : ?>
                        <p class="aegis-t-a6" style="color:#d63638;">单据不存在或无权限。</p>
                    <?php else : ?>
                        <?php $status_label = $status_labels[$request->status] ?? $request->status; ?>
                        <div class="aegis-t-a6" style="margin-bottom:8px;">单号：<?php echo esc_html($request->request_no); ?></div>
                        <div class="aegis-t-a6" style="margin-bottom:8px;">状态：<?php echo esc_html($status_label); ?></div>
                        <div class="aegis-t-a6" style="margin-bottom:8px;">创建时间：<?php echo esc_html($request->created_at); ?></div>
                        <div class="aegis-t-a6" style="margin-bottom:12px;">更新时间：<?php echo esc_html($request->updated_at); ?></div>

                        <?php if (!$can_edit) : ?>
                            <p class="aegis-t-a6" style="color:#d63638;">已锁定不可编辑。</p>
                            <details style="margin-top:12px;">
                                <summary class="aegis-portal-button is-secondary">已录入防伪码</summary>
                                <div style="margin-top:10px;">
                                    <textarea rows="6" class="aegis-returns-code-textarea" readonly><?php echo esc_textarea($code_text); ?></textarea>
                                </div>
                            </details>
                        <?php else : ?>
                            <?php
                            $has_invalid_item = false;
                            foreach ($items as $item) {
                                if (!in_array(($item->validation_status ?? 'pending'), ['pass', 'pass_override'], true)) {
                                    $has_invalid_item = true;
                                    break;
                                }
                            }
                            ?>
                            <form method="post" class="aegis-t-a6">
                                <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                <input type="hidden" name="returns_action" value="update_draft" />
                                <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                                <input type="hidden" name="contact_name" value="" />
                                <input type="hidden" name="contact_phone" value="" />
                                <input type="hidden" name="reason_code" value="" />

                                <div class="aegis-returns-code-entry">
                                    <input id="aegis_returns_code_input" class="aegis-portal-input" type="text" placeholder="请输入/扫码防伪码，回车加入" autocomplete="off" />
                                    <button type="button" class="aegis-portal-button is-primary" id="aegis_returns_add_btn">加入</button>
                                </div>
                                <div class="aegis-returns-mini" style="margin-top:6px;">提示：本阶段需点击“保存草稿”后进行系统校验与展示结果。</div>

                                <details style="margin-top:12px;">
                                    <summary class="aegis-portal-button is-secondary">批量录入（可选）</summary>
                                    <div style="margin-top:10px;">
                                        <textarea class="aegis-returns-code-textarea" name="code_values" id="aegis_returns_code_values" placeholder="一行一个，或用空格/逗号分隔；最多 500 条"><?php echo esc_textarea($code_text); ?></textarea>
                                        <div class="aegis-returns-mini">支持换行/空格/逗号分隔，最多 500 条。</div>
                                    </div>
                                </details>

                                <details style="margin-top:12px;">
                                    <summary>备注（可选）</summary>
                                    <textarea name="remark" rows="3" style="width:100%; margin-top:8px;"><?php echo esc_textarea($request->remark); ?></textarea>
                                </details>

                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
                                    <button type="submit" class="button button-primary">保存草稿</button>
                                </div>
                            </form>
                            <form method="post" class="aegis-t-a6" style="margin-top:10px;" onsubmit="return confirm('提交后将锁定草稿内容，确认提交？');">
                                <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                <input type="hidden" name="returns_action" value="submit_request" />
                                <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
                                <button type="submit" class="button" <?php disabled($has_invalid_item, true); ?>>提交（提交后将锁定，需销售审核）</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <aside class="aegis-card aegis-portal-card">
                <div class="aegis-card-header">
                    <div class="aegis-card-title aegis-t-a4">退货清单（校验结果）</div>
                </div>
                <div class="aegis-returns-kpis">
                    <div class="aegis-returns-kpi"><div class="label">总条目</div><div class="value"><?php echo esc_html((string) $total_items); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">通过</div><div class="value"><?php echo esc_html((string) $pass_items); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">不通过</div><div class="value"><?php echo esc_html((string) $fail_items); ?></div></div>
                    <div class="aegis-returns-kpi"><div class="label">特批通过</div><div class="value"><?php echo esc_html((string) $pass_override_items); ?></div></div>
                </div>

                <div class="aegis-table-wrap">
                    <table class="aegis-portal-table aegis-returns-results-table aegis-t-a6" style="width:100%; margin-top:12px;">
                        <thead>
                            <tr>
                                <th class="col-code">防伪码</th>
                                <th class="col-result">结果</th>
                                <th>原因</th>
                                <th class="col-outbound">出库时间</th>
                                <th class="col-deadline">截止时间</th>
                                <th>操作（特批）</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)) : ?>
                                <tr><td colspan="6">暂无条目。</td></tr>
                            <?php else : ?>
                                <?php foreach ($items as $item) : ?>
                                    <?php
                                    $validation_status = (string) ($item->validation_status ?? 'pending');
                                    $is_pass = in_array($validation_status, ['pass', 'pass_override'], true);
                                    $badge = '<span class="status-badge">待校验</span>';
                                    if ('pass' === $validation_status) {
                                        $badge = '<span class="status-badge is-active">通过</span>';
                                    } elseif ('pass_override' === $validation_status) {
                                        $badge = '<span class="status-badge is-active">特批通过</span>';
                                    } elseif ('fail' === $validation_status) {
                                        $badge = '<span class="status-badge is-inactive">不通过</span>';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html(AEGIS_System::format_code_display($item->code_value ?? '')); ?></td>
                                        <td><?php echo wp_kses_post($badge); ?></td>
                                        <td><?php echo esc_html($item->fail_reason_msg ?? ''); ?></td>
                                        <td><?php echo esc_html($item->outbound_scanned_at ?? ''); ?></td>
                                        <td><?php echo esc_html($item->after_sales_deadline_at ?? ''); ?></td>
                                        <td>
                                            <?php if (!$is_pass && $can_edit && $show_edit && !empty($request)) : ?>
                                                <form method="post" class="inline-form" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                                    <?php wp_nonce_field('aegis_returns_action', 'aegis_returns_nonce'); ?>
                                                    <input type="hidden" name="returns_action" value="apply_override" />
                                                    <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request->id); ?>" />
                                                    <input type="hidden" name="code_value" value="<?php echo esc_attr((string) $item->code_value); ?>" />
                                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                                    <input type="text" name="override_plain_code" placeholder="输入特批码" required />
                                                    <button type="submit" class="button">验证并放行</button>
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

<?php if ($show_create || $show_edit) : ?>
<script>
(function(){
  var input = document.getElementById('aegis_returns_code_input');
  var btn = document.getElementById('aegis_returns_add_btn');
  var ta = document.getElementById('aegis_returns_code_values');
  if(!input || !btn || !ta) return;

  function splitTokens(raw){
    if(!raw) return [];
    return raw
      .replace(/，/g, ',')
      .replace(/；/g, ';')
      .split(/[\s,\n;\r]+/g)
      .map(function(s){ return (s||'').trim(); })
      .filter(Boolean);
  }

  function getSetFromTextarea(){
    var lines = splitTokens(ta.value || '');
    var set = Object.create(null);
    lines.forEach(function(x){ set[x] = true; });
    return set;
  }

  function appendTokens(tokens){
    if(!tokens.length) return;
    var set = getSetFromTextarea();
    var added = [];
    tokens.forEach(function(t){
      if(!set[t]){
        set[t] = true;
        added.push(t);
      }
    });
    if(!added.length) return;
    var current = (ta.value || '').trim();
    ta.value = current ? (current + "\n" + added.join("\n")) : added.join("\n");
  }

  function onAdd(){
    var raw = input.value || '';
    var tokens = splitTokens(raw);
    appendTokens(tokens);
    input.value = '';
    input.focus();
  }

  btn.addEventListener('click', onAdd);
  input.addEventListener('keydown', function(e){
    if(e.key === 'Enter'){
      e.preventDefault();
      onAdd();
    }
  });
})();
</script>
<?php endif; ?>
