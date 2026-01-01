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
$dealer_notice = $context_data['dealer_notice'] ?? null;
?>
<div class="aegis-portal-shell">
    <div class="aegis-system-root aegis-portal-root aegis-t-a5">
        <header class="aegis-portal-topbar">
            <div class="topbar-spacer" aria-hidden="true"></div>
            <div class="aegis-portal-title aegis-t-a3">AEGISMAX 管理系统 · AEGIS SYSTEM V2026版</div>
            <div class="aegis-portal-actions">
                <?php if (is_user_logged_in()) : ?>
                    <a class="aegis-portal-logout aegis-t-a6" href="<?php echo esc_url($logout_url); ?>">退出</a>
                <?php endif; ?>
            </div>
        </header>
        <div class="aegis-portal-body">
            <aside class="aegis-portal-sidebar">
                <div class="portal-user-card">
                    <div class="portal-user aegis-t-a4"><?php echo esc_html($user ? $user->user_login : ''); ?></div>
                    <div class="portal-roles aegis-t-a6"><?php echo esc_html($role_labels); ?></div>
                </div>
                <div class="portal-nav-list aegis-t-a6">
                    <?php foreach ($modules as $slug => $info) :
                        if (empty($info['enabled'])) {
                            continue;
                        }
                        $label = $info['label'] ?? $slug;
                        $classes = ['nav-item'];
                        if ($slug === $current) {
                            $classes[] = 'is-active';
                        }
                        $href = $info['href'] ?? add_query_arg('m', $slug, $portal_url);
                    ?>
                        <a class="<?php echo esc_attr(implode(' ', $classes)); ?>" href="<?php echo esc_url($href); ?>">
                            <span class="nav-label aegis-t-a5"><?php echo esc_html($label); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
            <main class="aegis-portal-main">
                <div class="aegis-portal-panel aegis-t-a6">
                    <?php if (!empty($dealer_notice)) : ?>
                        <div class="dealer-auth-banner">
                            <div class="aegis-t-a5">授权有效期：<?php echo esc_html($dealer_notice['range'] ?? ''); ?></div>
                            <?php if (!empty($dealer_notice['warning'])) : ?>
                                <div class="dealer-auth-warning aegis-t-a6"><?php echo esc_html($dealer_notice['warning']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php echo $current_panel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </main>
        </div>
    </div>
</div>
