<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url       = $context_data['base_url'] ?? '';
$action         = $context_data['action'] ?? '';
$can_edit       = !empty($context_data['can_edit']);
$assets_enabled = !empty($context_data['assets_enabled']);
$can_edit_pricing = !empty($context_data['can_edit_pricing']);
$messages       = $context_data['messages'] ?? [];
$errors         = $context_data['errors'] ?? [];
$skus           = $context_data['skus'] ?? [];
$status_labels  = $context_data['status_labels'] ?? [];
$current_sku    = $context_data['current_sku'] ?? null;
$current_media  = $context_data['current_media'] ?? [];
$list           = $context_data['list'] ?? [];
$search         = $list['search'] ?? '';
$page           = $list['page'] ?? 1;
$per_page       = $list['per_page'] ?? 20;
$total          = $list['total'] ?? 0;
$total_pages    = $list['total_pages'] ?? 1;
$per_options    = $list['per_options'] ?? [20, 50, 100];

$form_action = $action === 'edit' || ($can_edit && $action === 'create') ? $action : '';
$editing_id = $current_sku ? (int) $current_sku->id : 0;
$create_url = add_query_arg([
    'm'      => 'sku',
    'action' => 'create',
], $base_url);
$list_url = add_query_arg('m', 'sku', $base_url);
?>

<?php foreach ($messages as $msg) : ?>
    <div class="aegis-portal-notice is-success aegis-t-a6"><?php echo esc_html($msg); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $msg) : ?>
    <div class="aegis-portal-notice is-error aegis-t-a6"><?php echo esc_html($msg); ?></div>
<?php endforeach; ?>

<?php if ($form_action || $current_sku) :
    $is_edit = ($form_action === 'edit' || ($current_sku && 'create' !== $form_action));
    $ean_value = $current_sku ? $current_sku->ean : '';
    $product_name = $current_sku ? $current_sku->product_name : '';
    $size_label = $current_sku ? $current_sku->size_label : '';
    $color_label = $current_sku ? $current_sku->color_label : '';
    $price_agent = $current_sku ? $current_sku->price_tier_agent : '';
    $price_dealer = $current_sku ? $current_sku->price_tier_dealer : '';
    $price_core = $current_sku ? $current_sku->price_tier_core : '';
    $status_value = $current_sku ? $current_sku->status : 'active';
    $image_record = $current_media['product_image'] ?? null;
    $certificate_record = $current_media['certificate'] ?? null;
    $image_url = $image_record ? AEGIS_SKU::get_media_gateway_url($image_record->id) : '';
    $certificate_url = $certificate_record ? AEGIS_SKU::get_media_gateway_url($certificate_record->id) : '';
    $certificate_visibility = isset($_POST['certificate_visibility']) ? sanitize_key(wp_unslash($_POST['certificate_visibility'])) : ($certificate_record ? $certificate_record->visibility : 'private');
    $ean_readonly = $is_edit || !$can_edit;
?>
<div class="aegis-portal-card sku-form-card">
    <div class="portal-action-bar">
        <div>
            <div class="aegis-t-a4" style="margin:0;"><?php echo $is_edit ? '编辑 SKU' : '新增 SKU'; ?></div>
            <div class="aegis-t-a6" style="color:#555;">EAN 一旦创建不可修改，如录入错误请停用后重新创建。</div>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="aegis-t-a6">
        <?php wp_nonce_field('aegis_sku_action', 'aegis_sku_nonce'); ?>
        <input type="hidden" name="sku_action" value="save" />
        <input type="hidden" name="sku_id" value="<?php echo esc_attr($editing_id); ?>" />
        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
        <div class="aegis-portal-form-grid sku-form-grid sku-form-grid--main">
            <label class="aegis-portal-field sku-field sku-field--ean">
                <span>EAN</span>
                <input class="aegis-portal-input" type="text" name="ean" value="<?php echo esc_attr($ean_value); ?>" <?php echo $ean_readonly ? 'readonly' : ''; ?> required />
            </label>
            <label class="aegis-portal-field sku-field sku-field--name">
                <span>产品名称</span>
                <input class="aegis-portal-input" type="text" name="product_name" value="<?php echo esc_attr($product_name); ?>" required <?php disabled(!$can_edit); ?> />
            </label>
            <label class="aegis-portal-field sku-field sku-field--size">
                <span>尺码</span>
                <input class="aegis-portal-input" type="text" name="size_label" value="<?php echo esc_attr($size_label); ?>" <?php disabled(!$can_edit); ?> />
            </label>
            <label class="aegis-portal-field sku-field sku-field--color">
                <span>颜色</span>
                <input class="aegis-portal-input" type="text" name="color_label" value="<?php echo esc_attr($color_label); ?>" <?php disabled(!$can_edit); ?> />
            </label>
            <label class="aegis-portal-field sku-field sku-field--price-agent">
                <span>一级代理商价</span>
                <input class="aegis-portal-input" type="number" step="0.01" min="0" name="price_tier_agent" value="<?php echo esc_attr($price_agent); ?>" <?php disabled(!$can_edit_pricing); ?> />
                <span class="aegis-t-a6" style="color:#666;">仅 HQ 可编辑，留空表示不可下单。</span>
            </label>
            <label class="aegis-portal-field sku-field sku-field--price-dealer">
                <span>一级经销商价</span>
                <input class="aegis-portal-input" type="number" step="0.01" min="0" name="price_tier_dealer" value="<?php echo esc_attr($price_dealer); ?>" <?php disabled(!$can_edit_pricing); ?> />
            </label>
            <label class="aegis-portal-field sku-field sku-field--price-core">
                <span>核心合作商价</span>
                <input class="aegis-portal-input" type="number" step="0.01" min="0" name="price_tier_core" value="<?php echo esc_attr($price_core); ?>" <?php disabled(!$can_edit_pricing); ?> />
            </label>
            <label class="aegis-portal-field sku-field sku-field--status">
                <span>状态</span>
                <select class="aegis-portal-select" name="status" <?php disabled(!$can_edit); ?>>
                    <?php foreach ($status_labels as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($status_value, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="aegis-portal-form-grid" style="margin-top:12px;">
            <div class="aegis-portal-field">
                <span>产品图片</span>
                <?php if ($assets_enabled && $can_edit) : ?>
                    <input class="aegis-portal-input" type="file" name="product_image" accept="image/*" />
                <?php elseif (!$assets_enabled) : ?>
                    <div class="aegis-t-a6" style="color:#c00;">需启用“资产与媒体”模块才能上传附件。</div>
                <?php endif; ?>
                <?php if ($image_record) : ?>
                    <div class="aegis-t-a6" style="margin-top:6px;">已关联：#<?php echo esc_html($image_record->id); ?><?php if ($image_url) : ?> · <a class="aegis-portal-button is-link" href="<?php echo esc_url($image_url); ?>">下载</a><?php endif; ?></div>
                <?php endif; ?>
            </div>
            <div class="aegis-portal-field">
                <span>质检证书</span>
                <?php if ($assets_enabled && $can_edit) : ?>
                    <input class="aegis-portal-input" type="file" name="certificate_file" />
                    <div class="aegis-portal-radios">
                        <label><input type="radio" name="certificate_visibility" value="private" <?php checked($certificate_visibility, 'private'); ?> /> 内部</label>
                        <label><input type="radio" name="certificate_visibility" value="public" <?php checked($certificate_visibility, 'public'); ?> /> 公开</label>
                    </div>
                <?php elseif (!$assets_enabled) : ?>
                    <div class="aegis-t-a6" style="color:#c00;">需启用“资产与媒体”模块才能上传证书。</div>
                <?php endif; ?>
                <?php if ($certificate_record) :
                    $certificate_vis = $certificate_record->visibility === 'public' ? '公开' : '内部';
                ?>
                    <div class="aegis-t-a6" style="margin-top:6px;">已关联：#<?php echo esc_html($certificate_record->id); ?>（<?php echo esc_html($certificate_vis); ?>）<?php if ($certificate_url) : ?> · <a class="aegis-portal-button is-link" href="<?php echo esc_url($certificate_url); ?>">下载</a><?php endif; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($can_edit) : ?>
            <div style="margin-top:16px;">
                <button type="submit" class="aegis-portal-button is-primary">保存</button>
                <a class="aegis-portal-button" href="<?php echo esc_url($list_url); ?>">返回列表</a>
            </div>
        <?php else : ?>
            <div class="aegis-t-a6" style="margin-top:12px; color:#666;">当前账号为只读，可查看但不可修改。</div>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<div class="aegis-portal-card">
    <div class="portal-action-bar">
        <div>
            <div class="aegis-t-a3" style="margin:0;">SKU 管理</div>
            <div class="aegis-t-a6" style="color:#555;">维护产品主数据，确保 EAN 唯一且不可变更，停用即视为无效 SKU。</div>
        </div>
        <?php if ($can_edit) : ?>
            <a class="aegis-portal-button is-primary" href="<?php echo esc_url($create_url); ?>">新增 SKU</a>
        <?php endif; ?>
    </div>

    <form method="get" class="aegis-portal-filters aegis-t-a6" action="<?php echo esc_url($list_url); ?>">
        <input type="hidden" name="m" value="sku" />
        <div class="aegis-portal-form-grid">
            <label class="aegis-portal-field">
                <span class="aegis-t-a6">搜索（EAN / 名称）</span>
                <input class="aegis-portal-input" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="输入关键字" />
            </label>
            <label class="aegis-portal-field" style="max-width:180px;">
                <span class="aegis-t-a6">每页</span>
                <select class="aegis-portal-select" name="per_page">
                    <?php foreach ($per_options as $opt) : ?>
                        <option value="<?php echo esc_attr($opt); ?>" <?php selected((int) $per_page, (int) $opt); ?>><?php echo esc_html($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="aegis-portal-field" style="align-self:flex-end;">
                <button type="submit" class="aegis-portal-button">筛选</button>
            </div>
        </div>
    </form>

    <div class="aegis-table-wrap">
        <table class="aegis-portal-table aegis-t-a6">
            <thead>
                <tr>
                    <th style="width:78px;">图片</th>
                    <th>EAN</th>
                    <th>产品名称</th>
                    <th>尺码</th>
                    <th>颜色</th>
                    <th>状态</th>
                    <th>更新时间</th>
                    <th>附件</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($skus)) : ?>
                    <tr><td colspan="9" class="aegis-t-a6" style="text-align:center;">暂无记录</td></tr>
                <?php endif; ?>
                <?php foreach ($skus as $sku) :
                    $status_label = isset($status_labels[$sku->status]) ? $status_labels[$sku->status] : $sku->status;
                    $status_class = $sku->status === 'active' ? 'is-active' : 'is-inactive';
                    $target_status = $sku->status === 'active' ? 'inactive' : 'active';
                    $thumb_url = !empty($sku->product_image_url) ? $sku->product_image_url : '';
                    $edit_url = add_query_arg([
                        'm'      => 'sku',
                        'action' => 'edit',
                        'id'     => $sku->id,
                        'page'   => $page,
                        'per_page' => $per_page,
                        's'      => $search,
                    ], $base_url);
                ?>
                    <tr>
                        <td>
                            <?php if ($thumb_url) : ?>
                                <button type="button" class="sku-thumb" data-full="<?php echo esc_url($thumb_url); ?>">
                                    <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($sku->product_name ?: $sku->ean); ?>" />
                                </button>
                            <?php else : ?>
                                <div class="sku-thumb placeholder aegis-t-a6">无图</div>
                            <?php endif; ?>
                        </td>
                        <td class="aegis-t-a5" style="font-weight:600;">&nbsp;<?php echo esc_html($sku->ean); ?></td>
                        <td class="aegis-t-a5"><?php echo esc_html($sku->product_name); ?></td>
                        <td><?php echo esc_html($sku->size_label); ?></td>
                        <td><?php echo esc_html($sku->color_label); ?></td>
                        <td><span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                        <td><?php echo esc_html(mysql2date('Y-m-d H:i', $sku->updated_at)); ?></td>
                        <td>
                            <?php if ($sku->product_image_id) : ?>
                                <div class="aegis-t-a6">产品图 #<?php echo esc_html($sku->product_image_id); ?></div>
                            <?php endif; ?>
                            <?php if ($sku->certificate_id) : ?>
                                <div class="aegis-t-a6">证书 #<?php echo esc_html($sku->certificate_id); ?></div>
                            <?php endif; ?>
                            <?php if (!$sku->product_image_id && !$sku->certificate_id) : ?>
                                <span class="aegis-t-a6" style="color:#666;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($can_edit) : ?>
                                <div class="table-actions">
                                    <a class="aegis-portal-button is-link" href="<?php echo esc_url($edit_url); ?>">编辑</a>
                                    <form method="post" class="inline-form">
                                        <?php wp_nonce_field('aegis_sku_action', 'aegis_sku_nonce'); ?>
                                        <input type="hidden" name="sku_action" value="toggle_status" />
                                        <input type="hidden" name="sku_id" value="<?php echo esc_attr($sku->id); ?>" />
                                        <input type="hidden" name="target_status" value="<?php echo esc_attr($target_status); ?>" />
                                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                        <button type="submit" class="aegis-portal-button is-secondary"><?php echo $sku->status == 'active' ? '停用' : '启用'; ?></button>
                                    </form>
                                </div>
                            <?php else : ?>
                                <a class="aegis-portal-button is-link" href="<?php echo esc_url($edit_url); ?>">查看</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="aegis-portal-pagination aegis-t-a6">
        <div>共 <?php echo esc_html($total); ?> 条，页码 <?php echo esc_html($page); ?>/<?php echo esc_html($total_pages); ?></div>
        <div class="page-links">
            <?php for ($i = 1; $i <= $total_pages; $i++) :
                $page_url = add_query_arg([
                    'm'        => 'sku',
                    'page'     => $i,
                    'per_page' => $per_page,
                    's'        => $search,
                ], $base_url);
                $classes = $i === (int) $page ? 'is-active' : '';
            ?>
                <a class="page-link <?php echo esc_attr($classes); ?>" href="<?php echo esc_url($page_url); ?>"><?php echo esc_html($i); ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div class="sku-preview-modal" aria-hidden="true">
    <div class="sku-preview-backdrop" role="presentation"></div>
    <div class="sku-preview-dialog" role="dialog" aria-modal="true">
        <button type="button" class="sku-preview-close" aria-label="关闭预览">×</button>
        <img class="sku-preview-img" src="" alt="产品图片预览" />
    </div>
</div>
