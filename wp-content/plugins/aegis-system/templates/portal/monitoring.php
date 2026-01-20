<?php
/** @var array $context */
$base_url = $context['base_url'];
$messages = $context['messages'];
$errors = $context['errors'];
$filters = $context['filters'];
$dealers = $context['dealers'];
$rows = $context['rows'];
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
    <div class="aegis-t-a2" style="margin-bottom:12px;">监控</div>
    <p class="aegis-t-a6">默认展示最近 7 天的监控统计。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <form method="get" class="aegis-t-a6" style="margin:12px 0; display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
        <input type="hidden" name="m" value="monitoring" />
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
            <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge($filter_args, ['monitoring_action' => 'export']), $base_url), 'aegis_monitoring_export')); ?>">导出 CSV</a>
        <?php endif; ?>
    </form>

    <div class="aegis-t-a4" style="margin-top:12px;">经销商监控排行</div>
    <table class="aegis-table" style="width:100%; margin-top:8px;">
        <thead><tr><th>经销商</th><th>查询次数</th><th>清零次数</th><th>出库数量</th></tr></thead>
        <tbody>
            <?php if (empty($rows)) : ?>
                <tr><td colspan="4">暂无数据</td></tr>
            <?php else : ?>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['dealer_name']); ?></td>
                        <td><?php echo esc_html($row['query_count']); ?></td>
                        <td><?php echo esc_html($row['reset_count']); ?></td>
                        <td><?php echo esc_html($row['shipment_qty']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($filters['total_pages'] > 1) : ?>
        <div class="tablenav"><div class="tablenav-pages">
            <?php if ($filters['paged'] > 1) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($filter_args, ['paged' => $filters['paged'] - 1]), $base_url)); ?>">上一页</a>
            <?php endif; ?>
            <span class="aegis-t-a6">第 <?php echo esc_html($filters['paged']); ?> / <?php echo esc_html($filters['total_pages']); ?> 页</span>
            <?php if ($filters['paged'] < $filters['total_pages']) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($filter_args, ['paged' => $filters['paged'] + 1]), $base_url)); ?>">下一页</a>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>
</div>
