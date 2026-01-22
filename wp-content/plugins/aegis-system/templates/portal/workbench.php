<?php
if (!defined('ABSPATH')) {
    exit;
}

$entries = $context_data['entries'] ?? [];
?>
<div class="aegis-workbench">
    <div class="aegis-t-a3" style="margin-bottom:8px;">工作台</div>
    <div class="aegis-t-a6" style="margin-bottom:16px; color:#666;">请选择入口开始操作。</div>
    <?php if (empty($entries)) : ?>
        <div class="aegis-portal-notice is-error">当前暂无可用入口，请联系管理员启用相关模块。</div>
    <?php else : ?>
        <div class="aegis-workbench-grid">
            <?php foreach ($entries as $entry) : ?>
                <a class="aegis-workbench-tile" href="<?php echo esc_url($entry['href']); ?>">
                    <span class="workbench-icon" aria-hidden="true"><?php echo esc_html($entry['icon']); ?></span>
                    <span class="workbench-title aegis-t-a4"><?php echo esc_html($entry['title']); ?></span>
                    <span class="workbench-desc aegis-t-a6"><?php echo esc_html($entry['desc']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="aegis-t-a6" style="margin-top:16px; color:#666;">
        Android 可通过浏览器菜单“安装应用”。iOS 请使用 Safari 分享菜单“添加到主屏幕”。
    </div>
</div>
