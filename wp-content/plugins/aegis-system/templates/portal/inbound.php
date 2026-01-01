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
?>
<div class="aegis-t-a4">
    <div class="aegis-t-a2" style="margin-bottom:12px;">扫码入库</div>
    <p class="aegis-t-a6">逐码扫码/手输入库，完成后可导出单据明细（仅本单，最多 300 条）。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <?php if (!$receipt) : ?>
        <form method="post" class="aegis-t-a5" style="margin:12px 0;">
            <?php wp_nonce_field('aegis_inbound_action', 'aegis_inbound_nonce'); ?>
            <input type="hidden" name="inbound_action" value="start" />
            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
            <label class="aegis-t-a6">备注（可选）：<input type="text" name="note" /></label>
            <p style="margin-top:10px;"><button type="submit" class="button button-primary">开始入库</button></p>
        </form>
    <?php else : ?>
        <div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #d9dce3; border-radius:8px; background:#f8f9fb; margin-bottom:12px;">
            <div class="aegis-t-a4">入库单信息</div>
            <div class="aegis-t-a6">入库单号：<?php echo esc_html($receipt->receipt_no); ?> | 入库时间：<?php echo esc_html($receipt->created_at); ?> | 入库人：<?php echo esc_html(get_userdata($receipt->created_by)->user_login ?? ''); ?></div>
            <div class="aegis-t-a6" style="margin-top:8px;">本次总码数：<?php echo esc_html((int) ($summary->total ?? 0)); ?>，SKU 种类数：<?php echo esc_html((int) ($summary->sku_count ?? 0)); ?></div>
            <div style="margin-top:10px;">
                <a class="button" href="<?php echo esc_url(add_query_arg(['inbound_action' => 'print', 'receipt' => $receipt->id], $base_url)); ?>" target="_blank">打印汇总</a>
                <a class="button" href="<?php echo esc_url(add_query_arg(['inbound_action' => 'export', 'receipt' => $receipt->id], $base_url)); ?>">导出明细</a>
            </div>
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

        <div class="aegis-t-a5" style="margin-bottom:12px;">
            <div class="aegis-t-a4">扫码/手输</div>
            <form method="post" style="margin-top:8px; display:flex; gap:8px; align-items:center;">
                <?php wp_nonce_field('aegis_inbound_action', 'aegis_inbound_nonce'); ?>
                <input type="hidden" name="inbound_action" value="add" />
                <input type="hidden" name="receipt_id" value="<?php echo esc_attr($receipt->id); ?>" />
                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                <input type="text" name="code" class="regular-text" placeholder="扫码或输入防伪码" required />
                <button type="submit" class="button button-primary">加入入库单</button>
            </form>
        </div>

        <div class="aegis-t-a5" style="margin-bottom:12px;">
            <div class="aegis-t-a4">防伪码明细</div>
            <table class="aegis-table" style="width:100%; margin-top:8px;">
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
            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field('aegis_inbound_action', 'aegis_inbound_nonce'); ?>
                <input type="hidden" name="inbound_action" value="complete" />
                <input type="hidden" name="receipt_id" value="<?php echo esc_attr($receipt->id); ?>" />
                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                <button type="submit" class="button button-secondary">完成入库</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="aegis-t-a4" style="margin-top:16px;">入库单列表（最近 7 天）</div>
    <form method="get" class="aegis-t-a6" style="margin:8px 0;">
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
    <table class="aegis-table" style="width:100%;">
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
                        <td><a class="button" href="<?php echo esc_url(add_query_arg('receipt', $row->id, $base_url)); ?>">查看</a></td>
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
</div>
