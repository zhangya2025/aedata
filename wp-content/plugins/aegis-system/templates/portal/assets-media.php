<?php
if (!defined('ABSPATH')) {
    exit;
}

$portal_url  = $context_data['portal_url'] ?? '';
$base_dir    = $context_data['base_dir'] ?? '';
$allowed     = $context_data['allowed'] ?? [];
$max_size_mb = $context_data['max_size_mb'] ?? 0;
$checks      = $context_data['checks'] ?? [];
?>
<div class="aegis-t-a4" style="margin-bottom:12px;">资产与媒体</div>
<div class="aegis-t-a6" style="margin-bottom:16px; color:#555;">上传、存储与访问均通过网关控制，遵循内部/sensitive/public 的可见性策略。</div>
<div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #e5e5e5; border-radius:8px; background:#fafafa; margin-bottom:16px;">
    <div class="aegis-t-a6" style="margin-bottom:8px;">上传根路径</div>
    <div class="aegis-t-a6" style="font-family:monospace; word-break:break-all; color:#111;">
        <?php echo esc_html($base_dir); ?>
    </div>
</div>
<div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #e5e5e5; border-radius:8px; background:#fff; margin-bottom:16px;">
    <div class="aegis-t-a6" style="margin-bottom:6px;">上传规则</div>
    <ul class="aegis-t-a6" style="margin:0; padding-left:18px; color:#111; line-height:1.6;">
        <li>允许类型：<?php echo esc_html(implode(', ', $allowed)); ?></li>
        <li>单文件大小上限：<?php echo esc_html($max_size_mb); ?> MB</li>
        <li>存储桶：uploads/aegis-system/{owner_type}/{YYYY}/{MM}</li>
        <li>文件名采用随机/哈希前缀，避免直链暴露</li>
    </ul>
</div>
<div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #e5e5e5; border-radius:8px; background:#fff; margin-bottom:16px;">
    <div class="aegis-t-a6" style="margin-bottom:6px;">网关访问策略</div>
    <ul class="aegis-t-a6" style="margin:0; padding-left:18px; color:#111; line-height:1.6;">
        <li>public：证书可游客访问（经网关放行），不暴露真实路径</li>
        <li>internal：内部角色可见（HQ/仓库），经销商与游客不可访问</li>
        <li>sensitive：营业执照、付款凭证等仅 HQ 与归属对象可访问，其余拒绝</li>
    </ul>
</div>
<div class="aegis-t-a5" style="padding:12px 16px; border:1px solid #e5e5e5; border-radius:8px; background:#fff;">
    <div class="aegis-t-a6" style="margin-bottom:6px;">自检</div>
    <ul class="aegis-t-a6" style="margin:0; padding-left:18px; color:#111; line-height:1.6;">
        <li>上传目录存在：<?php echo !empty($checks['path_exists']) ? '是' : '否'; ?></li>
        <li>上传目录可写：<?php echo !empty($checks['path_writable']) ? '是' : '否'; ?></li>
        <li>索引表存在：<?php echo !empty($checks['table_exists']) ? '是' : '否'; ?></li>
    </ul>
</div>
