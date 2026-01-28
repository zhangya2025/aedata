<?php
/** @var array $context */
$base_url = $context['base_url'];
$messages = $context['messages'];
$errors = $context['errors'];
$users = $context['users'];
$status_map = $context['status_map'];
$current_user = $context['current_user'];
$current_note = $context['current_note'];
$list = $context['list'];
?>

<div class="aegis-t-a4">
    <div class="aegis-t-a2" style="margin-bottom:12px;">仓库人员管理</div>
    <p class="aegis-t-a6">仅 HQ 可维护仓库账号状态，停用将阻断该账号访问仓库功能。</p>

    <?php foreach ($messages as $msg) : ?>
        <div class="notice notice-success"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg) : ?>
        <div class="notice notice-error"><p class="aegis-t-a6"><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <?php if ($current_user) : ?>
        <?php
        $current_status = $status_map[$current_user->ID] ?? 'active';
        $toggle_status = $current_status === 'active' ? 'inactive' : 'active';
        $edit_url = add_query_arg(['m' => 'warehouse_master'], $base_url);
        $roles = (array) $current_user->roles;
        $role_label = in_array('aegis_warehouse_manager', $roles, true) ? '仓库管理员' : '仓库员工';
        ?>
        <div class="aegis-t-a5" style="border:1px solid #d9dce3; padding:16px; border-radius:8px; background:#f8f9fb; margin-bottom:16px;">
            <div class="aegis-t-a4" style="margin-bottom:8px;">编辑仓库账号</div>
            <form method="post" class="aegis-t-a6" style="display:grid; gap:12px;">
                <?php wp_nonce_field('aegis_warehouse_action', 'aegis_warehouse_nonce'); ?>
                <input type="hidden" name="warehouse_action" value="save_warehouse" />
                <input type="hidden" name="target_user_id" value="<?php echo esc_attr($current_user->ID); ?>" />
                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <label class="aegis-t-a6">用户名
                        <input type="text" class="aegis-portal-input" value="<?php echo esc_attr($current_user->user_login); ?>" readonly />
                    </label>
                    <label class="aegis-t-a6">用户 ID
                        <input type="text" class="aegis-portal-input" value="<?php echo esc_attr($current_user->ID); ?>" readonly />
                    </label>
                    <label class="aegis-t-a6">注册时间
                        <input type="text" class="aegis-portal-input" value="<?php echo esc_attr(mysql2date('Y-m-d H:i', $current_user->user_registered)); ?>" readonly />
                    </label>
                    <label class="aegis-t-a6">角色
                        <input type="text" class="aegis-portal-input" value="<?php echo esc_attr($role_label); ?>" readonly />
                    </label>
                    <label class="aegis-t-a6">显示名称
                        <input type="text" name="display_name" class="aegis-portal-input" value="<?php echo esc_attr($current_user->display_name); ?>" />
                    </label>
                    <label class="aegis-t-a6">邮箱
                        <input type="email" name="user_email" class="aegis-portal-input" value="<?php echo esc_attr($current_user->user_email); ?>" />
                    </label>
                    <label class="aegis-t-a6">状态
                        <input type="text" class="aegis-portal-input" value="<?php echo esc_attr($current_status === 'inactive' ? '停用' : '启用'); ?>" readonly />
                    </label>
                </div>
                <label class="aegis-t-a6">备注
                    <textarea name="warehouse_note" class="aegis-portal-input" rows="3"><?php echo esc_textarea($current_note); ?></textarea>
                </label>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="submit" class="aegis-portal-button is-secondary">保存</button>
                    <a class="aegis-portal-button is-link" href="<?php echo esc_url($edit_url); ?>">返回列表</a>
                </div>
            </form>
            <form method="post" class="inline-form" style="margin-top:8px;">
                <?php wp_nonce_field('aegis_warehouse_action', 'aegis_warehouse_nonce'); ?>
                <input type="hidden" name="warehouse_action" value="toggle_status" />
                <input type="hidden" name="target_user_id" value="<?php echo esc_attr($current_user->ID); ?>" />
                <input type="hidden" name="target_status" value="<?php echo esc_attr($toggle_status); ?>" />
                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                <button type="submit" class="aegis-portal-button is-secondary"><?php echo $current_status === 'active' ? '停用' : '启用'; ?></button>
            </form>
        </div>
    <?php endif; ?>

    <form method="get" class="aegis-portal-filters aegis-t-a6" action="<?php echo esc_url($base_url); ?>">
        <input type="hidden" name="m" value="warehouse_master" />
        <div class="aegis-portal-form-grid">
            <label class="aegis-portal-field">
                <span class="aegis-t-a6">搜索（用户名 / 名称 / 邮箱）</span>
                <input class="aegis-portal-input" type="search" name="s" value="<?php echo esc_attr($list['search']); ?>" placeholder="输入关键字" />
            </label>
            <label class="aegis-portal-field" style="max-width:180px;">
                <span class="aegis-t-a6">每页</span>
                <select class="aegis-portal-select" name="per_page">
                    <?php foreach ($list['per_options'] as $opt) : ?>
                        <option value="<?php echo esc_attr($opt); ?>" <?php selected((int) $list['per_page'], (int) $opt); ?>><?php echo esc_html($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="aegis-portal-field" style="align-self:flex-end;">
                <button type="submit" class="aegis-portal-button">筛选</button>
            </div>
        </div>
    </form>

    <div class="aegis-table-wrap">
        <table class="aegis-portal-table aegis-table aegis-t-a6">
            <thead>
                <tr>
                    <th>用户名</th>
                    <th>姓名</th>
                    <th>邮箱</th>
                    <th>角色</th>
                    <th>创建时间</th>
                    <th>状态</th>
                    <th class="col-actions">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)) : ?>
                    <tr><td colspan="7" class="aegis-t-a6" style="text-align:center;">暂无记录</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $user) :
                    $status = $status_map[$user->ID] ?? 'active';
                    $status_label = $status === 'inactive' ? '停用' : '启用';
                    $target_status = $status === 'active' ? 'inactive' : 'active';
                    $status_class = $status === 'active' ? 'is-active' : 'is-inactive';
                    $roles = (array) $user->roles;
                    $role_label = in_array('aegis_warehouse_manager', $roles, true) ? '仓库管理员' : '仓库员工';
                    $edit_link = add_query_arg(['m' => 'warehouse_master', 'user_id' => $user->ID], $base_url);
                ?>
                    <tr>
                        <td class="aegis-t-a5"><?php echo esc_html($user->user_login); ?></td>
                        <td><?php echo esc_html($user->display_name ?: '—'); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo esc_html($role_label); ?></td>
                        <td><?php echo esc_html(mysql2date('Y-m-d H:i', $user->user_registered)); ?></td>
                        <td><span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                        <td class="col-actions">
                            <a class="aegis-portal-button is-link" href="<?php echo esc_url($edit_link); ?>">编辑</a>
                            <form method="post" class="inline-form">
                                <?php wp_nonce_field('aegis_warehouse_action', 'aegis_warehouse_nonce'); ?>
                                <input type="hidden" name="warehouse_action" value="toggle_status" />
                                <input type="hidden" name="target_user_id" value="<?php echo esc_attr($user->ID); ?>" />
                                <input type="hidden" name="target_status" value="<?php echo esc_attr($target_status); ?>" />
                                <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr(wp_generate_uuid4()); ?>" />
                                <button type="submit" class="aegis-portal-button is-secondary"><?php echo $status === 'active' ? '停用' : '启用'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="aegis-portal-pagination aegis-t-a6">
        <div>共 <?php echo esc_html($list['total']); ?> 条，页码 <?php echo esc_html($list['paged']); ?>/<?php echo esc_html($list['total_pages']); ?></div>
        <div class="page-links">
            <?php for ($i = 1; $i <= $list['total_pages']; $i++) :
                $page_url = add_query_arg([
                    'm'        => 'warehouse_master',
                    'paged'    => $i,
                    'per_page' => $list['per_page'],
                    's'        => $list['search'],
                ], $base_url);
                $classes = $i === (int) $list['paged'] ? 'is-active' : '';
            ?>
                <a class="page-link <?php echo esc_attr($classes); ?>" href="<?php echo esc_url($page_url); ?>"><?php echo esc_html($i); ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
