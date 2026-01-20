<?php
/** @var array $context */
$base_url = $context['base_url'];
$messages = $context['messages'];
$errors = $context['errors'];
$filters = $context['filters'];
$dealers = $context['dealers'];
$receipts = $context['receipts'];
$shipments = $context['shipments'];
$receipt_detail = $context['receipt_detail'];
$receipt_items = $context['receipt_items'];
$receipt_summary = $context['receipt_summary'];
$shipment_detail = $context['shipment_detail'];
$shipment_items = $context['shipment_items'];
$can_export = $context['can_export'];
$filter_args = [
    'start_date' => $filters['start_date'],
    'end_date'   => $filters['end_date'],
    'dealer_id'  => $filters['dealer_id'],
    'sku'        => $filters['sku'],
    'per_page'   => $filters['per_page'],
];
?>
<div class="aegis-t-a4">
    <div class="aegis-t-a2" style="margin-bottom:12px;">报表</div>
    <p class="aegis-t-a6">默认展示最近 7 天报表数据，可按经销商/SKU 筛选。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <form method="get" class="aegis-t-a6" style="margin:12px 0; display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
        <input type="hidden" name="m" value="reports" />
        <label>开始 <input type="date" name="start_date" value="<?php echo esc_attr($filters['start_date']); ?>" /></label>
        <label>结束 <input type="date" name="end_date" value="<?php echo esc_attr($filters['end_date']); ?>" /></label>
        <label>经销商
            <select name="dealer_id">
                <option value="0">全部</option>
                <?php foreach ($dealers as $dealer) : ?>
                    <option value="<?php echo esc_attr($dealer->id); ?>" <?php selected($filters['dealer_id'], $dealer->id); ?>><?php echo esc_html($dealer->dealer_name); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>SKU <input type="text" name="sku" value="<?php echo esc_attr($filters['sku']); ?>" placeholder="EAN 或名称" /></label>
        <label>每页
            <select name="per_page">
                <?php foreach ($filters['per_options'] as $opt) : ?>
                    <option value="<?php echo esc_attr($opt); ?>" <?php selected($filters['per_page'], $opt); ?>><?php echo esc_html($opt); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button">筛选</button>
        <?php if ($can_export) : ?>
            <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge($filter_args, ['reports_action' => 'export']), $base_url), 'aegis_reports_export')); ?>">导出 CSV</a>
        <?php endif; ?>
    </form>

    <div class="aegis-t-a4" style="margin-top:12px;">入库单报表</div>
    <table class="aegis-table" style="width:100%; margin-top:8px;">
        <thead><tr><th>入库单号</th><th>时间</th><th>入库人</th><th>总数量</th><th>SKU 种类数</th><th>操作</th></tr></thead>
        <tbody>
            <?php if (empty($receipts)) : ?>
                <tr><td colspan="6">暂无入库单</td></tr>
            <?php else : ?>
                <?php foreach ($receipts as $row) : ?>
                    <?php $user = $row->created_by ? get_userdata($row->created_by) : null; ?>
                    <tr>
                        <td><?php echo esc_html($row->receipt_no); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td><?php echo esc_html($user ? $user->user_login : '-'); ?></td>
                        <td><?php echo esc_html((int) $row->item_count); ?></td>
                        <td><?php echo esc_html((int) $row->sku_count); ?></td>
                        <td>
                            <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($filter_args, ['receipt_id' => $row->id]), $base_url)); ?>">查看</a>
                            <?php if ($can_export) : ?>
                                <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge($filter_args, ['reports_action' => 'export_receipt_detail', 'receipt_id' => $row->id]), $base_url), 'aegis_reports_receipt_export_' . $row->id)); ?>">导出明细</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($filters['receipt_total_pages'] > 1) : ?>
        <div class="tablenav"><div class="tablenav-pages">
            <?php if ($filters['receipt_page'] > 1) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($filter_args, ['receipt_page' => $filters['receipt_page'] - 1]), $base_url)); ?>">上一页</a>
            <?php endif; ?>
            <span class="aegis-t-a6">第 <?php echo esc_html($filters['receipt_page']); ?> / <?php echo esc_html($filters['receipt_total_pages']); ?> 页</span>
            <?php if ($filters['receipt_page'] < $filters['receipt_total_pages']) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($filter_args, ['receipt_page' => $filters['receipt_page'] + 1]), $base_url)); ?>">下一页</a>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>

    <?php if ($receipt_detail) : ?>
        <?php $receipt_user = $receipt_detail->created_by ? get_userdata($receipt_detail->created_by) : null; ?>
        <div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #d9dce3; border-radius:8px; background:#f8f9fb; margin-top:16px;">
            <div class="aegis-t-a4">入库单详情</div>
            <div class="aegis-t-a6">入库单号：<?php echo esc_html($receipt_detail->receipt_no); ?> | 入库时间：<?php echo esc_html($receipt_detail->created_at); ?> | 入库人：<?php echo esc_html($receipt_user ? $receipt_user->user_login : '-'); ?></div>
            <div class="aegis-t-a6" style="margin-top:6px;">总数量：<?php echo esc_html((int) ($receipt_summary->total ?? 0)); ?>，SKU 种类数：<?php echo esc_html((int) ($receipt_summary->sku_count ?? 0)); ?></div>
            <table class="aegis-table" style="width:100%; margin-top:10px;">
                <thead><tr><th>#</th><th>Code</th><th>EAN</th><th>产品名</th><th>入库时间</th></tr></thead>
                <tbody>
                    <?php if (empty($receipt_items)) : ?>
                        <tr><td colspan="5">暂无明细</td></tr>
                    <?php else : ?>
                        <?php foreach ($receipt_items as $index => $item) : ?>
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
    <?php endif; ?>

    <div class="aegis-t-a4" style="margin-top:20px;">出库单报表</div>
    <table class="aegis-table" style="width:100%; margin-top:8px;">
        <thead><tr><th>出库单号</th><th>时间</th><th>经销商</th><th>出库人</th><th>总数量</th><th>SKU 种类数</th><th>操作</th></tr></thead>
        <tbody>
            <?php if (empty($shipments)) : ?>
                <tr><td colspan="7">暂无出库单</td></tr>
            <?php else : ?>
                <?php foreach ($shipments as $row) : ?>
                    <?php $user = $row->created_by ? get_userdata($row->created_by) : null; ?>
                    <tr>
                        <td><?php echo esc_html($row->shipment_no); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td><?php echo esc_html($row->dealer_name ?: '-'); ?></td>
                        <td><?php echo esc_html($user ? $user->user_login : '-'); ?></td>
                        <td><?php echo esc_html((int) $row->item_count); ?></td>
                        <td><?php echo esc_html((int) $row->sku_count); ?></td>
                        <td>
                            <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($filter_args, ['shipment_id' => $row->id]), $base_url)); ?>">查看</a>
                            <?php if ($can_export) : ?>
                                <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge($filter_args, ['reports_action' => 'export_shipment_detail', 'shipment_id' => $row->id]), $base_url), 'aegis_reports_shipment_export_' . $row->id)); ?>">导出明细</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($filters['shipment_total_pages'] > 1) : ?>
        <div class="tablenav"><div class="tablenav-pages">
            <?php if ($filters['shipment_page'] > 1) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($filter_args, ['shipment_page' => $filters['shipment_page'] - 1]), $base_url)); ?>">上一页</a>
            <?php endif; ?>
            <span class="aegis-t-a6">第 <?php echo esc_html($filters['shipment_page']); ?> / <?php echo esc_html($filters['shipment_total_pages']); ?> 页</span>
            <?php if ($filters['shipment_page'] < $filters['shipment_total_pages']) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($filter_args, ['shipment_page' => $filters['shipment_page'] + 1]), $base_url)); ?>">下一页</a>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>

    <?php if ($shipment_detail) : ?>
        <?php $shipment_user = $shipment_detail->created_by ? get_userdata($shipment_detail->created_by) : null; ?>
        <div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #d9dce3; border-radius:8px; background:#f8f9fb; margin-top:16px;">
            <div class="aegis-t-a4">出库单详情</div>
            <div class="aegis-t-a6">出库单号：<?php echo esc_html($shipment_detail->shipment_no); ?> | 出库时间：<?php echo esc_html($shipment_detail->created_at); ?> | 出库人：<?php echo esc_html($shipment_user ? $shipment_user->user_login : '-'); ?></div>
            <table class="aegis-table" style="width:100%; margin-top:10px;">
                <thead><tr><th>#</th><th>Code</th><th>EAN</th><th>产品名</th><th>出库时间</th></tr></thead>
                <tbody>
                    <?php if (empty($shipment_items)) : ?>
                        <tr><td colspan="5">暂无明细</td></tr>
                    <?php else : ?>
                        <?php foreach ($shipment_items as $index => $item) : ?>
                            <tr>
                                <td><?php echo esc_html($index + 1); ?></td>
                                <td><?php echo esc_html($item->code_value); ?></td>
                                <td><?php echo esc_html($item->ean); ?></td>
                                <td><?php echo esc_html($item->product_name); ?></td>
                                <td><?php echo esc_html($item->scanned_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
