<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url       = $context_data['base_url'] ?? '';
$action         = $context_data['action'] ?? '';
$can_edit       = !empty($context_data['can_edit']);
$assets_enabled = !empty($context_data['assets_enabled']);
$messages       = $context_data['messages'] ?? [];
$errors         = $context_data['errors'] ?? [];
$dealers        = $context_data['dealers'] ?? [];
$status_labels  = $context_data['status_labels'] ?? [];
$current_dealer = $context_data['current_dealer'] ?? null;
$current_media  = $context_data['current_media'] ?? null;
$list           = $context_data['list'] ?? [];
$search         = $list['search'] ?? '';
$page           = $list['page'] ?? 1;
$per_page       = $list['per_page'] ?? 20;
$total          = $list['total'] ?? 0;
$total_pages    = $list['total_pages'] ?? 1;
$per_options    = $list['per_options'] ?? [20, 50, 100];

$form_action = ($action === 'edit') ? 'edit' : (($can_edit && $action === 'create') ? 'create' : '');
$editing_id = $current_dealer ? (int) $current_dealer->id : 0;
$create_url = add_query_arg([
    'm'      => 'dealer_master',
    'action' => 'create',
], $base_url);
$list_url = add_query_arg('m', 'dealer_master', $base_url);
?>

<?php foreach ($messages as $msg) : ?>
    <div class="aegis-portal-notice is-success aegis-t-a6"><?php echo esc_html($msg); ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $msg) : ?>
    <div class="aegis-portal-notice is-error aegis-t-a6"><?php echo esc_html($msg); ?></div>
<?php endforeach; ?>

<div class="portal-action-bar">
    <div>
        <div class="aegis-t-a4" style="margin:0;">经销商管理</div>
        <div class="aegis-t-a6" style="color:#555;">维护经销商主数据，授权编码创建后不可修改。</div>
    </div>
    <?php if ($can_edit) : ?>
        <a class="aegis-portal-button is-primary" href="<?php echo esc_url($create_url); ?>">新增经销商</a>
    <?php endif; ?>
</div>

<?php if ($form_action || $current_dealer) :
    $is_edit = ($form_action === 'edit');
    $auth_code = $current_dealer ? $current_dealer->auth_code : '';
    $dealer_name = $current_dealer ? $current_dealer->dealer_name : '';
    $contact_name = $current_dealer ? $current_dealer->contact_name : '';
    $phone = $current_dealer ? $current_dealer->phone : '';
    $address = $current_dealer ? $current_dealer->address : '';
    $auth_start_date = $current_dealer ? AEGIS_Dealer::format_date_input($current_dealer->auth_start_date) : '';
    $auth_end_date = $current_dealer ? AEGIS_Dealer::format_date_input($current_dealer->auth_end_date) : '';
    $license_url = $current_media ? AEGIS_Dealer::get_media_gateway_url($current_media->id) : '';
?>
<div class="aegis-portal-card sku-form-card">
    <div class="portal-action-bar">
        <div>
            <div class="aegis-t-a4" style="margin:0;"><?php echo $is_edit ? '编辑经销商' : '新增经销商'; ?></div>
            <div class="aegis-t-a6" style="color:#555;">授权编码不可修改，如录入错误请停用后新建。</div>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="aegis-t-a6">
        <?php wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce'); ?>
        <input type="hidden" name="dealer_action" value="save" />
        <input type="hidden" name="dealer_id" value="<?php echo esc_attr($editing_id); ?>" />
        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
        <div class="aegis-portal-form-grid">
            <label class="aegis-portal-field">
                <span>授权编码</span>
                <input class="aegis-portal-input" type="text" name="auth_code" value="<?php echo esc_attr($auth_code); ?>" <?php echo $is_edit || !$can_edit ? 'readonly' : ''; ?> required />
            </label>
            <label class="aegis-portal-field">
                <span>经销商名称</span>
                <input class="aegis-portal-input" type="text" name="dealer_name" value="<?php echo esc_attr($dealer_name); ?>" required <?php disabled(!$can_edit); ?> />
            </label>
            <label class="aegis-portal-field">
                <span>联系人</span>
                <input class="aegis-portal-input" type="text" name="contact_name" value="<?php echo esc_attr($contact_name); ?>" <?php disabled(!$can_edit); ?> />
            </label>
            <label class="aegis-portal-field">
                <span>联系电话</span>
                <input class="aegis-portal-input" type="text" name="phone" value="<?php echo esc_attr($phone); ?>" <?php disabled(!$can_edit); ?> />
            </label>
            <label class="aegis-portal-field">
                <span>地址</span>
                <input class="aegis-portal-input" type="text" name="address" value="<?php echo esc_attr($address); ?>" <?php disabled(!$can_edit); ?> />
            </label>
            <label class="aegis-portal-field">
                <span>授权开始日期</span>
                <input class="aegis-portal-input" type="date" name="auth_start_date" value="<?php echo esc_attr($auth_start_date); ?>" <?php disabled(!$can_edit); ?> required />
            </label>
            <label class="aegis-portal-field">
                <span>授权截止日期</span>
                <input class="aegis-portal-input" type="date" name="auth_end_date" value="<?php echo esc_attr($auth_end_date); ?>" <?php disabled(!$can_edit); ?> required />
            </label>
        </div>
        <div class="aegis-portal-form-grid" style="margin-top:12px;">
            <div class="aegis-portal-field">
                <span>营业执照</span>
                <?php if ($assets_enabled && $can_edit) : ?>
                    <input class="aegis-portal-input" type="file" name="business_license" accept="image/*,application/pdf" />
                <?php elseif (!$assets_enabled) : ?>
                    <div class="aegis-t-a6" style="color:#c00;">需启用“资产与媒体”模块才能上传附件。</div>
                <?php endif; ?>
                <?php if ($current_media) : ?>
                    <div class="aegis-t-a6" style="margin-top:6px;">已关联：#<?php echo esc_html($current_media->id); ?><?php if ($license_url) : ?> · <a class="aegis-portal-button is-link" href="<?php echo esc_url($license_url); ?>" target="_blank" rel="noreferrer">查看</a><?php endif; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($can_edit) : ?>
            <div style="margin-top:12px;">
                <button type="submit" class="aegis-portal-button is-primary">保存</button>
            </div>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<form method="get" class="aegis-portal-filters aegis-t-a6" action="<?php echo esc_url($list_url); ?>">
    <input type="hidden" name="m" value="dealer_master" />
    <div class="aegis-portal-form-grid">
        <label class="aegis-portal-field">
            <span class="aegis-t-a6">搜索（授权编码 / 名称）</span>
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
                <th class="col-auth-code">授权编码</th>
                <th>经销商名称</th>
                <th class="col-contact">联系人</th>
                <th class="col-phone">联系电话</th>
                <th>地址</th>
                <th class="col-date">授权开始</th>
                <th class="col-date">授权截止</th>
                <th>状态</th>
                <th>更新时间</th>
                <th>营业执照</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dealers)) : ?>
                <tr><td colspan="10" class="aegis-t-a6" style="text-align:center;">暂无记录</td></tr>
            <?php endif; ?>
            <?php foreach ($dealers as $dealer) :
                $status_label = isset($status_labels[$dealer->status]) ? $status_labels[$dealer->status] : $dealer->status;
                $status_class = $dealer->status === 'active' ? 'is-active' : 'is-inactive';
                $target_status = $dealer->status === 'active' ? 'inactive' : 'active';
                $edit_url = add_query_arg([
                    'm'      => 'dealer_master',
                    'action' => 'edit',
                    'id'     => $dealer->id,
                    'page'   => $page,
                    'per_page' => $per_page,
                    's'      => $search,
                ], $base_url);
                $license_url = !empty($dealer->license_url) ? $dealer->license_url : '';
                $license_mime = !empty($dealer->license_mime) ? $dealer->license_mime : '';
                $is_pdf = $license_mime && stripos($license_mime, 'pdf') !== false;
                $is_image = $license_mime && stripos($license_mime, 'image/') === 0;
            ?>
                <tr>
                    <td class="aegis-t-a5 col-auth-code" style="font-weight:600;">&nbsp;<?php echo esc_html($dealer->auth_code); ?></td>
                    <td class="aegis-t-a5"><?php echo esc_html($dealer->dealer_name); ?></td>
                    <td class="col-contact"><?php echo esc_html($dealer->contact_name); ?></td>
                    <td class="col-phone"><?php echo esc_html($dealer->phone); ?></td>
                    <td style="max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo esc_attr($dealer->address); ?>"><?php echo esc_html($dealer->address); ?></td>
                    <td class="col-date"><?php echo esc_html(AEGIS_Dealer::format_date_display($dealer->auth_start_date)); ?></td>
                    <td class="col-date"><?php echo esc_html(AEGIS_Dealer::format_date_display($dealer->auth_end_date)); ?></td>
                    <td><span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                    <td><?php echo esc_html(mysql2date('Y-m-d H:i', $dealer->updated_at)); ?></td>
                    <td>
                        <?php if ($license_url) : ?>
                            <div class="table-actions" style="gap:6px;">
                                <?php if ($is_image) : ?>
                                    <button type="button" class="aegis-portal-button is-link aegis-preview-trigger" data-preview-url="<?php echo esc_attr($license_url); ?>" data-preview-type="image">预览</button>
                                <?php endif; ?>
                                <?php if ($is_pdf || !$is_image) : ?>
                                    <a class="aegis-portal-button is-link" href="<?php echo esc_url($license_url); ?>" target="_blank" rel="noreferrer">新窗口查看</a>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <span class="aegis-t-a6" style="color:#666;">未上传</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($can_edit) : ?>
                            <div class="table-actions">
                                <a class="aegis-portal-button is-link" href="<?php echo esc_url($edit_url); ?>">编辑</a>
                                <form method="post" class="inline-form">
                                    <?php wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce'); ?>
                                    <input type="hidden" name="dealer_action" value="toggle_status" />
                                    <input type="hidden" name="dealer_id" value="<?php echo esc_attr($dealer->id); ?>" />
                                    <input type="hidden" name="target_status" value="<?php echo esc_attr($target_status); ?>" />
                                    <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                    <button type="submit" class="aegis-portal-button is-secondary"><?php echo $dealer->status === 'active' ? '停用' : '启用'; ?></button>
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
                'm'        => 'dealer_master',
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

<div class="sku-preview-modal" aria-hidden="true">
    <div class="sku-preview-backdrop" role="presentation"></div>
    <div class="sku-preview-dialog" role="dialog" aria-modal="true">
        <button type="button" class="sku-preview-close" aria-label="关闭预览">×</button>
        <img class="sku-preview-img" src="" alt="营业执照预览" />
    </div>
</div>
