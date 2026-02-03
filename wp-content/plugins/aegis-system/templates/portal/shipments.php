<?php
/** @var array $context */
$base_url = $context['base_url'];
$messages = $context['messages'];
$errors = $context['errors'];
$shipment = $context['shipment'];
$items = $context['items'];
$summary = $context['summary'];
$sku_summary = $context['sku_summary'];
$dealers = $context['dealers'];
$filters = $context['filters'];
$shipments = is_array($context['shipments'] ?? null) ? $context['shipments'] : [];
$pending_orders = $context['pending_orders'] ?? [];
$prefill = $context['prefill'] ?? ['dealer_id' => 0, 'order_ref' => ''];
$order_link_enabled = $context['order_link_enabled'] ?? false;
$cancel_pending = $context['cancel_pending'] ?? false;
$portal_url = $context['portal_url'] ?? '';
$view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
$is_list_view = 'list' === $view;
$can_manage_system = AEGIS_System_Roles::user_can_manage_system();
?>
<div class="aegis-t-a4 aegis-shipments-page">
    <div class="aegis-t-a2" style="margin-bottom:12px;">扫码出库</div>
    <p class="aegis-t-a6 aegis-helptext">逐码扫码/手输，仅允许已入库码出库；经销商停用则不可选择。导出汇总/明细仅限仓库与 HQ。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <?php if (!$shipment) : ?>
        <form method="post" class="aegis-t-a5 aegis-start-form" style="margin:12px 0;">
            <?php wp_nonce_field('aegis_shipments_action', 'aegis_shipments_nonce'); ?>
            <input type="hidden" name="shipments_action" value="start" />
            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
            <div class="aegis-start-actions" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                <label class="aegis-t-a6">经销商：
                    <select name="dealer_id" required class="aegis-action-select">
                        <option value="">请选择经销商</option>
                        <?php foreach ($dealers as $dealer) : ?>
                            <option value="<?php echo esc_attr($dealer->id); ?>" <?php selected((int) $prefill['dealer_id'], (int) $dealer->id); ?>><?php echo esc_html($dealer->dealer_name . '（' . $dealer->auth_code . '）'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($order_link_enabled) : ?>
                    <label class="aegis-t-a6">关联订单号：
                        <input type="text" name="order_ref" value="<?php echo esc_attr($prefill['order_ref']); ?>" placeholder="待出库订单号" />
                    </label>
                <?php endif; ?>
                <button type="button" class="button aegis-note-toggle aegis-action-note" id="aegis-shipments-note-toggle" aria-expanded="false" aria-controls="aegis-shipments-note-field">备注</button>
                <div id="aegis-shipments-note-field" class="aegis-note-field" style="display:none; min-width:240px;">
                    <label class="aegis-t-a6" style="display:block;">备注（可选）：<input type="text" name="note" /></label>
                </div>
                <button type="submit" class="button button-primary aegis-action-start">
                    <svg class="aegis-btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor">
                        <rect x="3" y="4" width="2" height="16"></rect>
                        <rect x="7" y="4" width="1" height="16"></rect>
                        <rect x="10" y="4" width="2" height="16"></rect>
                        <rect x="14" y="4" width="1" height="16"></rect>
                        <rect x="17" y="4" width="2" height="16"></rect>
                    </svg>
                    <span class="aegis-btn-label">开始出库</span>
                </button>
            </div>
        </form>
        <script>
            (function() {
                var toggle = document.getElementById('aegis-shipments-note-toggle');
                var field = document.getElementById('aegis-shipments-note-field');
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
        <div id="aegis-shipments-table-anchor"></div>
        <div class="aegis-shipments-table-wrapper">
            <table class="aegis-table aegis-shipments-table" style="width:100%;">
                <thead><tr><th>ID</th><th>出库单号</th><th>经销商</th><th>数量</th><th>创建人</th><th>时间</th><th>操作</th></tr></thead>
                <tbody>
                    <?php if (!$shipments) : ?>
                        <tr><td colspan="7">暂无出库单</td></tr>
                    <?php else : ?>
                        <?php foreach ($shipments as $row) : ?>
                            <?php $user = $row->created_by ? get_userdata($row->created_by) : null; ?>
                            <?php
                            $dealer = null;
                            if ($row->dealer_id) {
                                foreach ($dealers as $d) {
                                    if ((int) $d->id === (int) $row->dealer_id) {
                                        $dealer = $d;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($row->id); ?></td>
                                <td><?php echo esc_html($row->shipment_no); ?></td>
                                <td><?php echo esc_html($dealer ? $dealer->dealer_name : '-'); ?></td>
                                <td><?php echo esc_html((int) ($row->qty ?? $row->item_count)); ?></td>
                                <td><?php echo esc_html($user ? $user->user_login : '-'); ?></td>
                                <td><?php echo esc_html($row->created_at); ?></td>
                                <td>
                                    <a class="button" href="<?php echo esc_url(add_query_arg('shipment', $row->id, $base_url)); ?>">查看</a>
                                    <?php if ($can_manage_system) : ?>
                                        <form method="post" style="display:inline-block; margin-left:6px;">
                                            <?php wp_nonce_field('aegis_shipments_action', 'aegis_shipments_nonce'); ?>
                                            <input type="hidden" name="shipments_action" value="delete_shipment" />
                                            <input type="hidden" name="shipment_id" value="<?php echo esc_attr($row->id); ?>" />
                                            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                            <button type="submit" class="button" onclick="return confirm('确认删除该出库单？仅空单/草稿可删除。');">删除</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #d9dce3; border-radius:8px; background:#f8f9fb; margin-bottom:12px;">
            <div class="aegis-t-a4">出库单信息</div>
            <?php
            $dealer = null;
            if ($shipment->dealer_id) {
                foreach ($dealers as $d) {
                    if ((int) $d->id === (int) $shipment->dealer_id) {
                        $dealer = $d;
                        break;
                    }
                }
            }
            ?>
            <div class="aegis-t-a6">出库单号：<?php echo esc_html($shipment->shipment_no); ?> | 出库时间：<?php echo esc_html($shipment->created_at); ?> | 经销商：<?php echo esc_html($dealer ? $dealer->dealer_name : ''); ?></div>
            <?php if (!empty($shipment->note)) : ?>
                <div class="aegis-t-a6" style="margin-top:6px;">备注：<?php echo esc_html($shipment->note); ?></div>
            <?php endif; ?>
            <div class="aegis-t-a6" style="margin-top:8px;">本次总码数：<?php echo esc_html((int) ($summary->total ?? 0)); ?>，SKU 种类数：<?php echo esc_html((int) ($summary->sku_count ?? 0)); ?></div>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                <a class="button" href="<?php echo esc_url(add_query_arg(['shipments_action' => 'print', 'shipment' => $shipment->id], $base_url)); ?>" target="_blank">打印汇总</a>
                <a class="button" href="<?php echo esc_url(add_query_arg(['shipments_action' => 'export_summary', 'shipment' => $shipment->id], $base_url)); ?>">导出汇总</a>
                <a class="button" href="<?php echo esc_url(add_query_arg(['shipments_action' => 'export_detail', 'shipment' => $shipment->id], $base_url)); ?>">导出明细</a>
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
                <?php wp_nonce_field('aegis_shipments_action', 'aegis_shipments_nonce'); ?>
                <input type="hidden" name="shipments_action" value="add" />
                <input type="hidden" name="shipment_id" value="<?php echo esc_attr($shipment->id); ?>" />
                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                <button type="button" class="button button-primary aegis-scan-trigger aegis-action-scan" data-aegis-scan="1" data-target-input="#aegis-shipments-scan-input" data-target-submit="#aegis-shipments-add-submit">相机扫码</button>
                <input type="text" name="code" id="aegis-shipments-scan-input" class="regular-text aegis-scan-input" placeholder="扫码或输入防伪码" required />
                <button type="submit" class="button button-secondary aegis-action-add" id="aegis-shipments-add-submit">加入出库单</button>
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

        <div class="aegis-t-a5 aegis-collapsible aegis-mobile-collapsible is-collapsed" id="aegis-shipments-detail" style="margin-bottom:12px;">
            <button type="button" class="aegis-t-a4 aegis-collapsible__toggle" aria-expanded="false" aria-controls="aegis-shipments-detail-content">防伪码明细</button>
            <div class="aegis-collapsible__content" id="aegis-shipments-detail-content">
                <div class="aegis-table-wrap">
                    <table class="aegis-table aegis-codes-table" style="width:100%; margin-top:8px;">
                        <thead><tr><th>#</th><th>Code</th><th>EAN</th><th>产品名</th><th>扫码时间</th></tr></thead>
                        <tbody>
                            <?php if (empty($items)) : ?>
                                <tr><td colspan="5">暂无数据</td></tr>
                            <?php else : ?>
                                <?php foreach ($items as $index => $item) : ?>
                                    <tr>
                                        <td><?php echo esc_html($index + 1); ?></td>
                                        <td><?php echo esc_html(AEGIS_System::format_code_display($item->code_value)); ?></td>
                                        <td><?php echo esc_html($item->ean); ?></td>
                                        <td><?php echo esc_html($item->product_name); ?></td>
                                        <td><?php echo esc_html($item->scanned_at ?? $item->created_at ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <form method="post" style="margin-top:10px;">
                    <?php wp_nonce_field('aegis_shipments_action', 'aegis_shipments_nonce'); ?>
                    <input type="hidden" name="shipments_action" value="complete" />
                    <input type="hidden" name="shipment_id" value="<?php echo esc_attr($shipment->id); ?>" />
                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                    <?php if ($cancel_pending) : ?>
                        <div class="aegis-t-a6" style="color:#d63638; margin-bottom:8px;">关联订单撤销申请处理中，出库已冻结。</div>
                    <?php endif; ?>
                    <button type="submit" class="button button-secondary" <?php disabled($cancel_pending); ?>>完成出库</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php if (!$is_list_view) : ?>
    <script>
        (function() {
            var input = document.getElementById('aegis-shipments-scan-input');
            if (input) {
                input.focus();
                input.select();
            }
        })();
    </script>
<?php endif; ?>
