<?php
if (!defined('ABSPATH')) {
    exit;
}

$base_url       = $context_data['base_url'] ?? '';
$action         = $context_data['action'] ?? '';
$can_edit       = !empty($context_data['can_edit']);
$can_edit_pricing = !empty($context_data['can_edit_pricing']);
$can_bind_user  = !empty($context_data['can_bind_user']);
$assets_enabled = !empty($context_data['assets_enabled']);
$messages       = $context_data['messages'] ?? [];
$errors         = $context_data['errors'] ?? [];
$dealers        = $context_data['dealers'] ?? [];
$status_labels  = $context_data['status_labels'] ?? [];
$current_dealer = $context_data['current_dealer'] ?? null;
$current_media  = $context_data['current_media'] ?? null;
$price_levels   = $context_data['price_levels'] ?? [];
$sales_users    = $context_data['sales_users'] ?? [];
$sales_user_map = $context_data['sales_user_map'] ?? [];
$sku_choices    = $context_data['sku_choices'] ?? [];
$sku_search     = $context_data['sku_search'] ?? '';
$overrides      = $context_data['overrides'] ?? [];
$dealer_user    = $context_data['dealer_user'] ?? null;
$one_time_password = $context_data['one_time_password'] ?? '';
$price_lookup   = $context_data['price_lookup'] ?? null;
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
    $price_level_value = $current_dealer ? $current_dealer->price_level : '';
    $sales_user_id_value = $current_dealer ? (int) $current_dealer->sales_user_id : 0;
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
            <label class="aegis-portal-field">
                <span>价格等级</span>
                <select class="aegis-portal-select" name="price_level" <?php disabled(!$can_edit_pricing); ?>>
                    <option value="">— 未设置 —</option>
                    <?php foreach ($price_levels as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($price_level_value, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="aegis-t-a6" style="color:#666;">仅 HQ 可编辑，用于默认取价。</span>
            </label>
            <label class="aegis-portal-field">
                <span>所属销售人员</span>
                <select class="aegis-portal-select" name="sales_user_id" <?php disabled(!$can_edit_pricing); ?>>
                    <option value="">— 未分配 —</option>
                    <?php foreach ($sales_users as $user) :
                        $display = $user->display_name ? $user->display_name : $user->user_login;
                    ?>
                        <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($sales_user_id_value, (int) $user->ID); ?>><?php echo esc_html($display); ?></option>
                    <?php endforeach; ?>
                </select>
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

<div class="aegis-portal-card" style="margin-top:16px;">
    <div class="portal-action-bar">
        <div>
            <div class="aegis-t-a4" style="margin:0;">账号绑定</div>
            <div class="aegis-t-a6" style="color:#555;">用于为该经销商创建登录账号并绑定（仅总部管理员）。</div>
        </div>
    </div>
    <?php if ($can_bind_user) : ?>
        <?php if ($one_time_password && $dealer_user) : ?>
            <div class="aegis-portal-notice is-success aegis-t-a6">
                <div>账号已创建：<?php echo esc_html($dealer_user->user_login); ?></div>
                <div>初始密码：<?php echo esc_html($one_time_password); ?>（请立即复制保存，仅显示一次）</div>
            </div>
        <?php endif; ?>
        <?php if ($dealer_user) : ?>
            <div class="aegis-t-a6" style="margin-top:8px;">已绑定：<?php echo esc_html($dealer_user->user_login); ?> / <?php echo esc_html($dealer_user->user_email); ?></div>
        <?php elseif (!$current_dealer) : ?>
            <div class="aegis-t-a6" style="margin-top:8px;">请先保存经销商后再创建账号并绑定。</div>
        <?php else : ?>
            <form method="post" class="aegis-portal-form-grid" style="margin-top:12px;">
                <?php wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce'); ?>
                <input type="hidden" name="dealer_action" value="create_dealer_user_bind" />
                <input type="hidden" name="dealer_id" value="<?php echo esc_attr($editing_id); ?>" />
                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                <label class="aegis-portal-field">
                    <span>用户名</span>
                    <input class="aegis-portal-input" type="text" name="dealer_user_login" required />
                </label>
                <label class="aegis-portal-field">
                    <span>邮箱</span>
                    <input class="aegis-portal-input" type="email" name="dealer_user_email" required />
                </label>
                <div class="aegis-portal-field" style="align-self:flex-end;">
                    <button type="submit" class="aegis-portal-button is-primary" onclick="return confirm('确认创建经销商账号并绑定？创建后将生成初始密码（仅显示一次）。')">创建账号并绑定</button>
                </div>
            </form>
        <?php endif; ?>
    <?php else : ?>
        <div class="aegis-t-a6" style="margin-top:8px;">仅总部管理员可创建/绑定账号。</div>
    <?php endif; ?>
</div>

<?php if ($current_dealer) : ?>
<div class="aegis-portal-card">
    <div class="portal-action-bar">
        <div>
            <div class="aegis-t-a4" style="margin:0;">专属价格</div>
            <div class="aegis-t-a6" style="color:#555;">同一经销商同一 SKU 仅一条覆盖价，优先于等级价。</div>
        </div>
    </div>

    <div class="aegis-t-a6" style="margin-bottom:10px; color:#666;">
        <?php echo $can_edit_pricing ? 'HQ 可维护；仓库管理员仅查看。' : '当前账号仅可查看，需 HQ 编辑。'; ?>
    </div>

    <div class="aegis-table-wrap">
        <table class="aegis-portal-table aegis-t-a6">
            <thead>
                <tr>
                    <th>EAN</th>
                    <th>产品名称</th>
                    <th>覆盖价</th>
                    <th>更新时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($overrides)) : ?>
                    <tr><td colspan="5" class="aegis-t-a6" style="text-align:center;">尚未配置专属价</td></tr>
                <?php endif; ?>
                <?php foreach ($overrides as $override) : ?>
                    <tr>
                        <td class="aegis-t-a5" style="font-weight:600;">&nbsp;<?php echo esc_html($override->ean); ?></td>
                        <td><?php echo esc_html($override->product_name ?: ''); ?></td>
                        <td><?php echo esc_html(number_format((float) $override->price_override, 2)); ?></td>
                        <td><?php echo esc_html(mysql2date('Y-m-d H:i', $override->updated_at)); ?></td>
                        <td>
                            <?php if ($can_edit_pricing) : ?>
                                <div class="table-actions">
                                    <form method="post" class="inline-form">
                                        <?php wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce'); ?>
                                        <input type="hidden" name="dealer_action" value="update_override" />
                                        <input type="hidden" name="dealer_id" value="<?php echo esc_attr($current_dealer->id); ?>" />
                                        <input type="hidden" name="override_id" value="<?php echo esc_attr($override->id); ?>" />
                                        <input type="hidden" name="override_ean" value="<?php echo esc_attr($override->ean); ?>" />
                                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                        <input class="aegis-portal-input" style="width:120px;" type="number" step="0.01" min="0" name="price_override" value="<?php echo esc_attr(number_format((float) $override->price_override, 2, '.', '')); ?>" />
                                        <button type="submit" class="aegis-portal-button is-secondary">更新</button>
                                    </form>
                                    <form method="post" class="inline-form" onsubmit="return confirm('确认删除该专属价？');">
                                        <?php wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce'); ?>
                                        <input type="hidden" name="dealer_action" value="delete_override" />
                                        <input type="hidden" name="dealer_id" value="<?php echo esc_attr($current_dealer->id); ?>" />
                                        <input type="hidden" name="override_id" value="<?php echo esc_attr($override->id); ?>" />
                                        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                        <button type="submit" class="aegis-portal-button is-link">删除</button>
                                    </form>
                                </div>
                            <?php else : ?>
                                <span class="aegis-t-a6" style="color:#666;">仅 HQ 可编辑</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($can_edit_pricing) : ?>
        <div class="aegis-portal-subtitle aegis-t-a5" style="margin-top:14px;">新增专属价</div>
        <form method="post" class="aegis-portal-form-grid">
            <?php wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce'); ?>
            <input type="hidden" name="dealer_action" value="add_override" />
            <input type="hidden" name="dealer_id" value="<?php echo esc_attr($current_dealer->id); ?>" />
            <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
            <label class="aegis-portal-field">
                <span>SKU EAN</span>
                <input class="aegis-portal-input" list="override-skus" type="text" name="override_ean" placeholder="输入或选择 EAN" />
            </label>
            <label class="aegis-portal-field">
                <span>覆盖价</span>
                <input class="aegis-portal-input" type="number" step="0.01" min="0" name="price_override" required />
            </label>
            <div class="aegis-portal-field" style="align-self:flex-end;">
                <button type="submit" class="aegis-portal-button is-primary">新增专属价</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="aegis-portal-subtitle aegis-t-a5" style="margin-top:16px;">报价查询</div>
    <form method="post" class="aegis-portal-form-grid" style="margin-bottom:8px;">
        <?php wp_nonce_field('aegis_dealer_action', 'aegis_dealer_nonce'); ?>
        <input type="hidden" name="dealer_action" value="lookup_price" />
        <input type="hidden" name="dealer_id" value="<?php echo esc_attr($current_dealer->id); ?>" />
        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
        <label class="aegis-portal-field">
            <span>SKU EAN</span>
            <input class="aegis-portal-input" list="override-skus" type="text" name="price_lookup_ean" placeholder="输入 EAN" />
        </label>
        <div class="aegis-portal-field" style="align-self:flex-end;">
            <button type="submit" class="aegis-portal-button">查询报价</button>
        </div>
    </form>
    <?php
    if ($price_lookup && (int) $price_lookup['dealer_id'] === (int) $current_dealer->id) {
        $quote = $price_lookup['quote'];
        $price_value = isset($quote['unit_price']) ? $quote['unit_price'] : null;
        $source = $quote['price_source'] ?? '';
        $source_label = 'tier' === $source ? '等级价' : ('override' === $source ? '专属价' : '未配置');
        $level_used = $quote['price_level_used'] ?? '';
        echo '<div class="aegis-t-a6" style="color:#333;">';
        echo 'SKU ' . esc_html($price_lookup['ean']) . ' 报价：';
        if (null === $price_value) {
            echo '<strong>未配置</strong>（不可下单）';
        } else {
            echo '<strong>' . esc_html(number_format((float) $price_value, 2)) . '</strong> · 来源：' . esc_html($source_label);
        }
        if ($level_used) {
        echo ' · 等级：' . esc_html($level_used);
        }
        echo '</div>';
    }
    ?>
    <datalist id="override-skus">
        <?php foreach ($sku_choices as $choice) : ?>
            <option value="<?php echo esc_attr($choice->ean); ?>"><?php echo esc_html($choice->ean . ' / ' . $choice->product_name); ?></option>
        <?php endforeach; ?>
    </datalist>
    <?php if ($sku_search) : ?>
        <div class="aegis-t-a6" style="color:#666;">SKU 过滤：<?php echo esc_html($sku_search); ?></div>
    <?php endif; ?>
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
    <table class="aegis-portal-table aegis-table aegis-dealer-table aegis-t-a6">
        <thead>
            <tr>
                <th class="col-auth-code">授权编码</th>
                <th>经销商名称</th>
                <th class="col-contact">联系人</th>
                <th class="col-phone">联系电话</th>
                <th class="col-address">地址</th>
                <th>价格等级</th>
                <th>销售归属</th>
                <th class="col-date">授权开始</th>
                <th class="col-date">授权截止</th>
                <th>状态</th>
                <th>更新时间</th>
                <th class="col-license">营业执照</th>
                <th class="col-actions">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dealers)) : ?>
                <tr><td colspan="13" class="aegis-t-a6" style="text-align:center;">暂无记录</td></tr>
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
                    <td class="col-address" title="<?php echo esc_attr($dealer->address); ?>"><?php echo esc_html($dealer->address); ?></td>
                    <td><?php echo esc_html($dealer->price_level_label ?: '—'); ?></td>
                    <td><?php echo esc_html($dealer->sales_user_name ?: '—'); ?></td>
                    <td class="col-date"><?php echo esc_html(AEGIS_Dealer::format_date_display($dealer->auth_start_date)); ?></td>
                    <td class="col-date"><?php echo esc_html(AEGIS_Dealer::format_date_display($dealer->auth_end_date)); ?></td>
                    <td><span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                    <td><?php echo esc_html(mysql2date('Y-m-d H:i', $dealer->updated_at)); ?></td>
                    <td class="col-license">
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
                    <td class="col-actions">
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
