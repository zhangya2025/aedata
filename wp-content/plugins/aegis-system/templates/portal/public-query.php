<?php
/** @var array $context */
$base_url = $context['base_url'];
$public_url = $context['public_url'];
$query_code = $context['query_code'];
$result = $context['result'];
$has_query = '' !== $query_code;
$shipment = is_array($result) && isset($result['shipment']) ? $result['shipment'] : null;
$last_query_at = is_array($result) && isset($result['counts']['last_query_at']) ? $result['counts']['last_query_at'] : '';
$product_label = '';
if (is_array($result)) {
    $product_label = $result['product'];
    if (!empty($result['sku_meta'])) {
        $product_label .= '（' . $result['sku_meta'] . '）';
    }
}
?>
<div class="aegis-t-a4 aegis-public-query-page">
    <div class="aegis-public-query-header">
        <div>
            <div class="aegis-t-a2">公共查询</div>
            <div class="aegis-public-query-subtitle aegis-t-a6">用于核验防伪码真伪及流转状态。</div>
            <div class="aegis-public-query-subtitle aegis-t-a6">支持带短横/不带短横输入，系统将自动规范化。</div>
        </div>
        <div class="aegis-public-query-entry aegis-t-a6">
            公共查询入口：
            <?php if ($public_url) : ?>
                <a href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener">访问防伪码公共查询</a>
            <?php else : ?>
                <span>公共查询页面未就绪</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="aegis-portal-card aegis-public-query-card">
        <div class="aegis-t-a5" style="margin-bottom:8px;">请输入防伪码</div>
        <form method="get" action="<?php echo esc_url($base_url); ?>" class="aegis-public-query-form">
            <input type="text" name="code" value="<?php echo esc_attr($query_code); ?>" placeholder="输入防伪码（支持 AMAA-XXXX… 或不带短横）" class="aegis-portal-input" />
            <button type="submit" class="aegis-portal-button is-primary">查询</button>
            <div class="aegis-portal-hint aegis-t-a6">支持带短横/不带短横；系统会自动规范化。</div>
        </form>
    </div>

    <div class="aegis-portal-card aegis-public-query-card is-result">
        <div class="aegis-t-a5" style="margin-bottom:8px;">查询结果</div>
        <?php if (!$has_query) : ?>
            <div class="aegis-portal-notice">请输入防伪码后点击查询。</div>
        <?php elseif (is_wp_error($result)) : ?>
            <?php if ('code_not_found' === $result->get_error_code()) : ?>
                <div class="aegis-portal-notice is-error">未找到该防伪码。</div>
                <div class="aegis-portal-hint aegis-t-a6">请检查输入是否正确，或尝试去掉/添加短横后再试。</div>
            <?php else : ?>
                <div class="aegis-portal-notice is-error"><?php echo esc_html($result->get_error_message()); ?></div>
            <?php endif; ?>
        <?php elseif (is_array($result)) : ?>
            <div class="aegis-public-query-grid">
                <div class="aegis-public-query-item">
                    <span class="label">防伪码</span>
                    <span class="value"><?php echo esc_html($result['code']); ?></span>
                </div>
                <div class="aegis-public-query-item">
                    <span class="label">状态</span>
                    <span class="value"><?php echo esc_html($result['status_label']); ?></span>
                </div>
                <div class="aegis-public-query-item">
                    <span class="label">产品信息</span>
                    <span class="value"><?php echo esc_html($result['ean']); ?> / <?php echo esc_html($product_label); ?></span>
                </div>
                <div class="aegis-public-query-item">
                    <span class="label">最近动作时间</span>
                    <span class="value">
                        <?php if (is_array($shipment) && !empty($shipment['scanned_at'])) : ?>
                            <?php echo esc_html($shipment['scanned_at']); ?>（出库）
                        <?php elseif (!empty($last_query_at)) : ?>
                            <?php echo esc_html($last_query_at); ?>（查询）
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
