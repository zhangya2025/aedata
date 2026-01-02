<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url     = $context_data['base_url'] ?? '';
$can_generate = !empty($context_data['can_generate']);
$can_export   = !empty($context_data['can_export']);
$messages     = $context_data['messages'] ?? [];
$errors       = $context_data['errors'] ?? [];
$sku_options  = $context_data['sku_options'] ?? [];
$filters      = $context_data['filters'] ?? [];
$batches      = $context_data['batches'] ?? [];
$view         = $context_data['view'] ?? [];

$start_date   = $filters['start_date'] ?? '';
$end_date     = $filters['end_date'] ?? '';
$per_page     = $filters['per_page'] ?? 20;
$paged        = $filters['paged'] ?? 1;
$total        = $filters['total'] ?? 0;
$total_pages  = $filters['total_pages'] ?? 1;
$per_options  = $filters['per_options'] ?? [20, 50, 100];

$view_batch   = $view['batch'] ?? null;
$view_codes   = $view['codes'] ?? [];
$codes_page   = $view['page'] ?? 1;
$codes_per    = $view['per_page'] ?? 20;
$codes_total  = $view['total'] ?? 0;
$codes_pages  = $view['total_pages'] ?? 1;

$prefill_items = [];
if (!empty($_POST['items']) && is_array($_POST['items'])) {
    foreach ($_POST['items'] as $row) {
        $prefill_items[] = [
            'ean'      => isset($row['ean']) ? sanitize_text_field(wp_unslash($row['ean'])) : '',
            'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : '',
        ];
    }
}
if (empty($prefill_items)) {
    $prefill_items[] = ['ean' => '', 'quantity' => ''];
}
?>

<?php foreach ($messages as $msg) : ?>
    <div class="aegis-portal-notice is-success aegis-t-a6"><?php echo esc_html($msg); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $msg) : ?>
    <div class="aegis-portal-notice is-error aegis-t-a6"><?php echo esc_html($msg); ?></div>
<?php endforeach; ?>

<?php if ($can_generate) : ?>
    <div class="aegis-portal-card" style="margin-bottom:16px;">
        <div class="portal-action-bar">
            <div>
                <div class="aegis-t-a4" style="margin:0;">生成防伪码批次</div>
                <div class="aegis-t-a6" style="color:#555;">单 SKU ≤ 100，单次总量 ≤ 300，最多 3 个 SKU。</div>
            </div>
        </div>
        <form method="post" class="aegis-t-a6 aegis-codes-form" data-max-rows="3">
            <?php wp_nonce_field('aegis_codes_portal', 'aegis_codes_nonce'); ?>
            <input type="hidden" name="codes_action" value="generate" />
            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
            <div class="aegis-code-rows">
                <?php foreach ($prefill_items as $index => $row) : ?>
                    <div class="aegis-code-row" data-index="<?php echo esc_attr($index); ?>">
                        <label class="aegis-portal-field">
                            <span>SKU</span>
                            <input class="aegis-portal-input code-ean" list="aegis-codes-skus" name="items[<?php echo esc_attr($index); ?>][ean]" value="<?php echo esc_attr($row['ean']); ?>" placeholder="输入 EAN 或产品名搜索" required />
                        </label>
                        <label class="aegis-portal-field">
                            <span>数量</span>
                            <input class="aegis-portal-input code-qty" type="number" min="1" max="100" name="items[<?php echo esc_attr($index); ?>][quantity]" value="<?php echo esc_attr($row['quantity']); ?>" required />
                        </label>
                        <button type="button" class="aegis-portal-button is-ghost aegis-code-remove">删除</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="aegis-code-actions">
                <button type="button" class="aegis-portal-button aegis-code-add">新增 SKU 行</button>
                <div class="aegis-t-a6 limit-note">前端校验仅作提示，后台已强校验数量与状态。</div>
            </div>
            <div style="margin-top:12px;">
                <button type="submit" class="aegis-portal-button is-primary">生成批次</button>
            </div>
        </form>
        <datalist id="aegis-codes-skus">
            <?php foreach ($sku_options as $sku) : ?>
                <option value="<?php echo esc_attr($sku->ean); ?>"><?php echo esc_html($sku->product_name); ?></option>
            <?php endforeach; ?>
        </datalist>
    </div>
<?php endif; ?>

<div class="aegis-portal-card" style="margin-bottom:16px;">
    <div class="portal-action-bar" style="margin-bottom:10px;">
        <div>
            <div class="aegis-t-a4" style="margin:0;">生成批次列表</div>
            <div class="aegis-t-a6" style="color:#555;">默认显示最近 7 天。</div>
        </div>
    </div>
    <form method="get" class="aegis-t-a6" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
        <input type="hidden" name="m" value="codes" />
        <label>开始日期 <input class="aegis-portal-input" type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" /></label>
        <label>结束日期 <input class="aegis-portal-input" type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" /></label>
        <label>每页
            <select class="aegis-portal-select" name="per_page">
                <?php foreach ($per_options as $opt) : ?>
                    <option value="<?php echo esc_attr($opt); ?>" <?php selected($per_page, $opt); ?>><?php echo esc_html($opt); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="aegis-portal-button">筛选</button>
    </form>
    <div class="aegis-table-wrap">
        <table class="aegis-portal-table">
            <thead>
                <tr class="aegis-t-a6">
                    <th>批次号</th>
                    <th>创建时间</th>
                    <th>SKU 行数</th>
                    <th>总数量</th>
                    <th>创建人</th>
                    <th class="col-ops">操作</th>
                </tr>
            </thead>
            <tbody class="aegis-t-a6">
                <?php if (empty($batches)) : ?>
                    <tr><td colspan="6">暂无数据</td></tr>
                <?php endif; ?>
                <?php foreach ($batches as $batch) :
                    $user = $batch->created_by ? get_userdata($batch->created_by) : null;
                    $creator = $user ? $user->user_login : '-';
                    $view_url = add_query_arg([
                        'm'           => 'codes',
                        'view'        => $batch->id,
                        'start_date'  => $start_date,
                        'end_date'    => $end_date,
                        'per_page'    => $per_page,
                        'paged'       => $paged,
                    ], $base_url);
                    $export_url = wp_nonce_url(add_query_arg([
                        'm'            => 'codes',
                        'codes_action' => 'export',
                        'batch_id'     => $batch->id,
                    ], $base_url), 'aegis_codes_export_' . $batch->id);
                    $print_url = wp_nonce_url(add_query_arg([
                        'm'            => 'codes',
                        'codes_action' => 'print',
                        'batch_id'     => $batch->id,
                    ], $base_url), 'aegis_codes_print_' . $batch->id);
                ?>
                    <tr>
                        <td><?php echo esc_html($batch->id); ?></td>
                        <td><?php echo esc_html($batch->created_at); ?></td>
                        <td><?php echo esc_html($batch->sku_count ?? 1); ?></td>
                        <td><?php echo esc_html($batch->quantity); ?></td>
                        <td><?php echo esc_html($creator); ?></td>
                        <td>
                            <a class="aegis-portal-button is-ghost" href="<?php echo esc_url($view_url); ?>">明细</a>
                            <?php if ($can_export) : ?>
                                <a class="aegis-portal-button is-ghost" href="<?php echo esc_url($export_url); ?>">导出</a>
                                <a class="aegis-portal-button is-ghost" href="<?php echo esc_url($print_url); ?>" target="_blank" rel="noreferrer">打印</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1) :
        $prev_url = $paged > 1 ? add_query_arg([
            'm'          => 'codes',
            'paged'      => $paged - 1,
            'per_page'   => $per_page,
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ], $base_url) : '';
        $next_url = $paged < $total_pages ? add_query_arg([
            'm'          => 'codes',
            'paged'      => $paged + 1,
            'per_page'   => $per_page,
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ], $base_url) : '';
    ?>
        <div class="aegis-t-a6" style="margin-top:10px; display:flex; align-items:center; gap:10px;">
            <?php if ($prev_url) : ?><a class="aegis-portal-button is-ghost" href="<?php echo esc_url($prev_url); ?>">上一页</a><?php endif; ?>
            <span>第 <?php echo esc_html($paged); ?> / <?php echo esc_html($total_pages); ?> 页</span>
            <?php if ($next_url) : ?><a class="aegis-portal-button is-ghost" href="<?php echo esc_url($next_url); ?>">下一页</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($view_batch) : ?>
    <div class="aegis-portal-card">
        <div class="portal-action-bar" style="margin-bottom:10px;">
            <div>
                <div class="aegis-t-a4" style="margin:0;">批次 #<?php echo esc_html($view_batch->id); ?> 明细</div>
                <div class="aegis-t-a6" style="color:#555;">创建于 <?php echo esc_html($view_batch->created_at); ?> · 总量 <?php echo esc_html($view_batch->quantity); ?> 条</div>
            </div>
            <div class="portal-actions">
                <?php if ($can_export) :
                    $export_url = wp_nonce_url(add_query_arg([
                        'm'            => 'codes',
                        'codes_action' => 'export',
                        'batch_id'     => $view_batch->id,
                    ], $base_url), 'aegis_codes_export_' . $view_batch->id);
                    $print_url = wp_nonce_url(add_query_arg([
                        'm'            => 'codes',
                        'codes_action' => 'print',
                        'batch_id'     => $view_batch->id,
                    ], $base_url), 'aegis_codes_print_' . $view_batch->id);
                ?>
                    <a class="aegis-portal-button is-ghost" href="<?php echo esc_url($export_url); ?>">导出</a>
                    <a class="aegis-portal-button is-ghost" href="<?php echo esc_url($print_url); ?>" target="_blank" rel="noreferrer">打印</a>
                <?php endif; ?>
                <a class="aegis-portal-button is-ghost" href="<?php echo esc_url($base_url); ?>">返回列表</a>
            </div>
        </div>
        <div class="codes-summary aegis-t-a6">
            <?php if (!empty($view_batch->items_data)) : ?>
                <?php foreach ($view_batch->items_data as $item) :
                    $label = $item['ean'];
                    if (!empty($item['product_name'])) {
                        $label .= ' · ' . $item['product_name'];
                    }
                    $label .= ' × ' . ($item['quantity'] ?? 0);
                ?>
                    <div class="chip"><?php echo esc_html($label); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="aegis-table-wrap">
            <table class="aegis-portal-table">
                <thead>
                    <tr class="aegis-t-a6">
                        <th>Code</th>
                        <th>EAN</th>
                        <th>产品</th>
                        <th>生成时间</th>
                    </tr>
                </thead>
                <tbody class="aegis-t-a6">
                    <?php if (empty($view_codes)) : ?>
                        <tr><td colspan="4">暂无数据</td></tr>
                    <?php endif; ?>
                    <?php foreach ($view_codes as $code) : ?>
                        <tr>
                            <td style="font-family:monospace;"><?php echo esc_html($code->code); ?></td>
                            <td><?php echo esc_html($code->ean); ?></td>
                            <td><?php echo esc_html($code->product_name ?? ''); ?></td>
                            <td><?php echo esc_html($code->created_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($codes_pages > 1) :
            $codes_prev = $codes_page > 1 ? add_query_arg([
                'm'              => 'codes',
                'view'           => $view_batch->id,
                'codes_page'     => $codes_page - 1,
                'codes_per_page' => $codes_per,
            ], $base_url) : '';
            $codes_next = $codes_page < $codes_pages ? add_query_arg([
                'm'              => 'codes',
                'view'           => $view_batch->id,
                'codes_page'     => $codes_page + 1,
                'codes_per_page' => $codes_per,
            ], $base_url) : '';
        ?>
            <div class="aegis-t-a6" style="margin-top:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <form method="get" class="aegis-t-a6" style="display:inline-flex; gap:8px; align-items:center;">
                    <input type="hidden" name="m" value="codes" />
                    <input type="hidden" name="view" value="<?php echo esc_attr($view_batch->id); ?>" />
                    <label>每页
                        <select class="aegis-portal-select" name="codes_per_page">
                            <?php foreach ($per_options as $opt) : ?>
                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($codes_per, $opt); ?>><?php echo esc_html($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="aegis-portal-button">刷新</button>
                </form>
                <div style="display:flex; gap:8px; align-items:center;">
                    <?php if ($codes_prev) : ?><a class="aegis-portal-button is-ghost" href="<?php echo esc_url($codes_prev); ?>">上一页</a><?php endif; ?>
                    <span>第 <?php echo esc_html($codes_page); ?> / <?php echo esc_html($codes_pages); ?> 页</span>
                    <?php if ($codes_next) : ?><a class="aegis-portal-button is-ghost" href="<?php echo esc_url($codes_next); ?>">下一页</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
