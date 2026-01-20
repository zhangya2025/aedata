<?php
if (!defined('ABSPATH')) {
    exit;
}

$portal_url = $context_data['portal_url'] ?? '';

$entries = [
    [
        'title' => '扫码入库',
        'desc'  => '扫码模式',
        'icon'  => '📥',
        'href'  => add_query_arg('m', 'inbound', $portal_url),
    ],
    [
        'title' => '扫码出库',
        'desc'  => '扫码模式',
        'icon'  => '📤',
        'href'  => add_query_arg('m', 'shipments', $portal_url),
    ],
    [
        'title' => '入库单',
        'desc'  => '单据列表',
        'icon'  => '🧾',
        'href'  => add_query_arg(['m' => 'inbound', 'view' => 'list'], $portal_url),
    ],
    [
        'title' => '出库单',
        'desc'  => '单据列表',
        'icon'  => '📄',
        'href'  => add_query_arg(['m' => 'shipments', 'view' => 'list'], $portal_url),
    ],
];
?>
<div class="aegis-workbench">
    <div class="aegis-t-a3" style="margin-bottom:8px;">工作台</div>
    <div class="aegis-t-a6" style="margin-bottom:16px; color:#666;">请选择入口开始仓库作业。</div>
    <div class="aegis-workbench-grid">
        <?php foreach ($entries as $entry) : ?>
            <a class="aegis-workbench-tile" href="<?php echo esc_url($entry['href']); ?>">
                <span class="workbench-icon" aria-hidden="true"><?php echo esc_html($entry['icon']); ?></span>
                <span class="workbench-title aegis-t-a4"><?php echo esc_html($entry['title']); ?></span>
                <span class="workbench-desc aegis-t-a6"><?php echo esc_html($entry['desc']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
