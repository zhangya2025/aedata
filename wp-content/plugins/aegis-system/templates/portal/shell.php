<?php
if (!defined('ABSPATH')) {
    exit;
}

$modules       = $context_data['modules'] ?? [];
$current       = $context_data['current'] ?? '';
$portal_url    = $context_data['portal_url'] ?? '';
$user          = $context_data['user'] ?? null;
$logout_url    = $context_data['logout_url'] ?? '';
$current_panel = $context_data['current_panel'] ?? '';
$role_labels   = $context_data['role_labels'] ?? '';
?>
<div class="aegis-system-root aegis-portal-root aegis-t-a5">
    <div class="aegis-portal-header">
        <div class="aegis-portal-title">
            <h2 class="aegis-t-a3">AEGIS-SYSTEM Portal</h2>
            <p class="aegis-t-a5 portal-user">当前用户：<?php echo esc_html($user ? $user->user_login : ''); ?></p>
            <p class="aegis-t-a6 portal-roles">角色：<?php echo esc_html($role_labels); ?></p>
        </div>
        <?php if (is_user_logged_in()) : ?>
        <div class="aegis-portal-actions">
            <a class="aegis-portal-logout aegis-t-a6" href="<?php echo esc_url($logout_url); ?>">退出</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="aegis-portal-layout">
        <div class="aegis-portal-nav aegis-t-a6">
            <?php foreach ($modules as $slug => $info) :
                $enabled = !empty($info['enabled']);
                $label = ($info['label'] ?? $slug) . '（' . $slug . '）';
                $status = $enabled ? '已启用' : '未启用';
                $classes = ['nav-item'];
                if ($slug === $current) {
                    $classes[] = 'is-active';
                }
                if (!$enabled) {
                    $classes[] = 'is-disabled';
                }
            ?>
                <?php if ($enabled) : ?>
                    <a class="<?php echo esc_attr(implode(' ', $classes)); ?>" href="<?php echo esc_url(add_query_arg('m', $slug, $portal_url)); ?>">
                        <span class="nav-label"><?php echo esc_html($label); ?></span>
                        <span class="nav-status"><?php echo esc_html($status); ?></span>
                    </a>
                <?php else : ?>
                    <span class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                        <span class="nav-label"><?php echo esc_html($label); ?></span>
                        <span class="nav-status"><?php echo esc_html($status); ?></span>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="aegis-portal-panel aegis-t-a6">
            <?php echo $current_panel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>
</div>
