<?php
/** @var array $context */
$base_url = $context['base_url'];
$messages = $context['messages'];
$errors = $context['errors'];
$receipt = $context['receipt'];
$items = $context['items'];
$summary = $context['summary'];
$sku_summary = $context['sku_summary'];
$filters = $context['filters'];
$receipts = $context['receipts'];
$view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
$is_list_view = 'list' === $view;
$can_manage_system = AEGIS_System_Roles::user_can_manage_system();
?>
<div class="aegis-t-a4 aegis-inbound-page">
    <div class="aegis-t-a2" style="margin-bottom:12px;">扫码入库</div>
    <p class="aegis-t-a6 aegis-helptext">逐码扫码/手输入库，完成后可导出单据明细（仅本单，最多 300 条）。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <?php if (!$receipt) : ?>
        <form method="post" class="aegis-t-a5 aegis-start-form" style="margin:12px 0;">
            <?php wp_nonce_field('aegis_inbound_action', 'aegis_inbound_nonce'); ?>
            <input type="hidden" name="inbound_action" value="start" />
            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
            <div class="aegis-start-actions" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                <button type="submit" class="button button-primary aegis-action-start">
                    <svg class="aegis-btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <rect x="3" y="4" width="2" height="16"></rect>
                        <rect x="7" y="4" width="1" height="16"></rect>
                        <rect x="10" y="4" width="2" height="16"></rect>
                        <rect x="14" y="4" width="1" height="16"></rect>
                        <rect x="17" y="4" width="2" height="16"></rect>
                    </svg>
                    <span class="aegis-btn-label">开始入库</span>
                </button>
                <button type="button" class="button aegis-note-toggle aegis-action-note" id="aegis-inbound-note-toggle" aria-expanded="false" aria-controls="aegis-inbound-note-field">备注</button>
                <div id="aegis-inbound-note-field" class="aegis-note-field" style="display:none; min-width:240px;">
                    <label class="aegis-t-a6" style="display:block;">备注（可选）：<input type="text" name="note" class="regular-text" /></label>
                </div>
            </div>
        </form>
        <script>
            (function() {
                var toggle = document.getElementById('aegis-inbound-note-toggle');
                var field = document.getElementById('aegis-inbound-note-field');
                if (!toggle || !field) {
                    return;
                }
                toggle.addEventListener('click', function() {
                    var expanded = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    field.style.display = expanded ? 'none' : 'block';
                });
            })();
        </script>
    <?php else : ?>
        <div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #d9dce3; border-radius:8px; background:#f8f9fb; margin-bottom:12px;">
            <div class="aegis-t-a4">入库单信息</div>
            <div class="aegis-t-a6">入库单号：<?php echo esc_html($receipt->receipt_no); ?> | 入库时间：<?php echo esc_html($receipt->created_at); ?> | 入库人：<?php echo esc_html(get_userdata($receipt->created_by)->user_login ?? ''); ?></div>
            <div class="aegis-t-a6" style="margin-top:8px;">本次总码数：<?php echo esc_html((int) ($summary->total ?? 0)); ?>，SKU 种类数：<?php echo esc_html((int) ($summary->sku_count ?? 0)); ?></div>
            <div style="margin-top:10px;">
                <a class="button" href="<?php echo esc_url(add_query_arg(['inbound_action' => 'print', 'receipt' => $receipt->id], $base_url)); ?>" target="_blank">打印汇总</a>
                <a class="button" href="<?php echo esc_url(add_query_arg(['inbound_action' => 'export', 'receipt' => $receipt->id], $base_url)); ?>">导出明细</a>
            </div>
            <div class="aegis-table-wrap">
                <table class="aegis-table" style="margin-top:12px; width:100%;">
                    <thead><tr><th>EAN</th><th>产品名</th><th>数量</th></tr></thead>
                    <tbody>
                        <?php if (empty($sku_summary)) : ?>
                            <tr><td colspan="3">暂无汇总</td></tr>
                        <?php else : ?>
                            <?php foreach ($sku_summary as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row['ean']); ?></td>
                                    <td><?php echo esc_html($row['product_name']); ?></td>
                                    <td><?php echo esc_html($row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="aegis-t-a5 aegis-scan-actions-band">
            <div class="aegis-t-a4">扫码/手输</div>
            <form method="post" class="aegis-scan-form aegis-scan-actions">
                <?php wp_nonce_field('aegis_inbound_action', 'aegis_inbound_nonce'); ?>
                <input type="hidden" name="inbound_action" value="add" />
                <input type="hidden" name="receipt_id" value="<?php echo esc_attr($receipt->id); ?>" />
                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                <button type="button" class="button button-primary aegis-scan-trigger aegis-action-scan" data-aegis-scan="1" data-target-input="#aegis-inbound-scan-input" data-target-submit="#aegis-inbound-add-submit">相机扫码</button>
                <input type="text" name="code" id="aegis-inbound-scan-input" class="regular-text aegis-scan-input" placeholder="扫码或输入防伪码" required />
                <button type="submit" class="button button-secondary aegis-action-add" id="aegis-inbound-add-submit">加入入库单</button>
            </form>
        </div>
        <div class="aegis-scan-overlay" hidden>
            <div class="aegis-scan-header">
                <span>相机扫码</span>
                <button type="button" class="aegis-scan-close" aria-label="关闭">×</button>
            </div>
            <video class="aegis-scan-video" playsinline></video>
            <div class="aegis-scan-frame"></div>
            <div class="aegis-scan-hint">对准条码，自动识别</div>
            <div class="aegis-scan-status" role="status" aria-live="polite"></div>
        </div>

        <div class="aegis-t-a5 aegis-collapsible aegis-mobile-collapsible is-collapsed" id="aegis-inbound-detail" style="margin-bottom:12px;">
            <button type="button" class="aegis-t-a4 aegis-collapsible__toggle" aria-expanded="false" aria-controls="aegis-inbound-detail-content">防伪码明细</button>
            <div class="aegis-collapsible__content" id="aegis-inbound-detail-content">
                <div class="aegis-table-wrap">
                    <table class="aegis-table aegis-codes-table" style="width:100%; margin-top:8px;">
                        <thead><tr><th>#</th><th>Code</th><th>EAN</th><th>产品名</th><th>入库时间</th></tr></thead>
                        <tbody>
                            <?php if (empty($items)) : ?>
                                <tr><td colspan="5">暂无数据</td></tr>
                            <?php else : ?>
                                <?php foreach ($items as $index => $item) : ?>
                                    <tr>
                                        <td><?php echo esc_html($index + 1); ?></td>
                                        <td><?php echo esc_html($item->code); ?></td>
                                        <td><?php echo esc_html($item->ean); ?></td>
                                        <td><?php echo esc_html($item->product_name); ?></td>
                                        <td><?php echo esc_html($item->created_at); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <form method="post" style="margin-top:10px;">
                    <?php wp_nonce_field('aegis_inbound_action', 'aegis_inbound_nonce'); ?>
                    <input type="hidden" name="inbound_action" value="complete" />
                    <input type="hidden" name="receipt_id" value="<?php echo esc_attr($receipt->id); ?>" />
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                    <button type="submit" class="button button-secondary">完成入库</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div id="aegis-inbound-list"></div>
    <div class="aegis-t-a5 aegis-collapsible aegis-mobile-collapsible is-collapsed aegis-list-section" id="aegis-inbound-receipts" style="margin-top:16px;">
        <button type="button" class="aegis-t-a4 aegis-collapsible__toggle" aria-expanded="false" aria-controls="aegis-inbound-receipts-content">入库单列表（最近 7 天）</button>
        <div class="aegis-collapsible__content" id="aegis-inbound-receipts-content">
            <div class="aegis-collapsible aegis-mobile-collapsible is-collapsed aegis-filter-section">
                <button type="button" class="aegis-t-a6 aegis-collapsible__toggle" aria-expanded="false" aria-controls="aegis-inbound-filter-content">筛选条件</button>
                <div class="aegis-collapsible__content" id="aegis-inbound-filter-content">
                    <form method="get" class="aegis-t-a6 aegis-filter-form" style="margin:8px 0;">
                        <input type="hidden" name="m" value="inbound" />
                        <label>开始 <input type="date" name="start_date" value="<?php echo esc_attr($filters['start_date']); ?>" /></label>
                        <label>结束 <input type="date" name="end_date" value="<?php echo esc_attr($filters['end_date']); ?>" /></label>
                        <label>每页 <select name="per_page">
                            <?php foreach ($filters['per_options'] as $opt) : ?>
                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($filters['per_page'], $opt); ?>><?php echo esc_html($opt); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <button type="submit" class="button">筛选</button>
                    </form>
                </div>
            </div>
            <div class="aegis-table-wrap">
                <table class="aegis-table aegis-receipts-table" style="width:100%;">
                    <thead><tr><th>ID</th><th>入库单号</th><th>数量</th><th>创建人</th><th>时间</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php if (empty($receipts)) : ?>
                            <tr><td colspan="6">暂无入库单</td></tr>
                        <?php else : ?>
                            <?php foreach ($receipts as $row) : ?>
                                <?php $user = $row->created_by ? get_userdata($row->created_by) : null; ?>
                                <tr>
                                    <td><?php echo esc_html($row->id); ?></td>
                                    <td><?php echo esc_html($row->receipt_no); ?></td>
                                    <td><?php echo esc_html((int) $row->qty); ?></td>
                                    <td><?php echo esc_html($user ? $user->user_login : '-'); ?></td>
                                    <td><?php echo esc_html($row->created_at); ?></td>
                                    <td>
                                        <a class="button" href="<?php echo esc_url(add_query_arg('receipt', $row->id, $base_url)); ?>">查看</a>
                                        <?php if ($can_manage_system) : ?>
                                            <form method="post" style="display:inline-block; margin-left:6px;">
                                                <?php wp_nonce_field('aegis_inbound_action', 'aegis_inbound_nonce'); ?>
                                                <input type="hidden" name="inbound_action" value="delete_receipt" />
                                                <input type="hidden" name="receipt_id" value="<?php echo esc_attr($row->id); ?>" />
                                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                                <button type="submit" class="button" onclick="return confirm('确认删除该入库单？仅空单可删除。');">删除</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
        </div>
    </div>
</div>
<script>
    (function() {
        var toggles = document.querySelectorAll('.aegis-inbound-page .aegis-collapsible__toggle');
        toggles.forEach(function(toggle) {
            var targetId = toggle.getAttribute('aria-controls');
            var wrapper = toggle.closest('.aegis-collapsible');
            if (!targetId || !wrapper) {
                return;
            }
            var content = document.getElementById(targetId);
            if (!content) {
                return;
            }
            toggle.addEventListener('click', function() {
                var isOpen = wrapper.classList.contains('is-open');
                wrapper.classList.toggle('is-open', !isOpen);
                toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });
        });
    })();
</script>
<?php if (!$is_list_view) : ?>
    <script>
        (function() {
            var input = document.getElementById('aegis-inbound-scan-input');
            if (input) {
                input.focus();
                input.select();
            }
        })();
    </script>
<?php else : ?>
    <script>
        (function() {
            var anchor = document.getElementById('aegis-inbound-list');
            if (anchor) {
                anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        })();
    </script>
<?php endif; ?>
