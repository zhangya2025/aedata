<?php
if (!defined('ABSPATH')) {
    exit;
}

$portal_url = $context_data['portal_url'] ?? '';
$dealers = $context_data['dealers'] ?? [];
$status_labels = $context_data['status_labels'] ?? [];
?>

<div class="aegis-t-a4">
    <div class="aegis-t-a2" style="margin-bottom:12px;">我的经销商</div>
    <p class="aegis-t-a6">仅展示分配给当前账号的经销商。</p>

    <table class="aegis-table" style="width:100%; margin-top:12px;">
        <thead>
            <tr>
                <th>经销商名称</th>
                <th>授权编码</th>
                <th>联系人</th>
                <th>电话</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dealers)) : ?>
                <tr>
                    <td colspan="6">你名下暂无经销商。</td>
                </tr>
            <?php else : ?>
                <?php foreach ($dealers as $dealer) : ?>
                    <?php
                    $status_label = $status_labels[$dealer->status] ?? $dealer->status;
                    $orders_url = add_query_arg(
                        [
                            'm' => 'orders',
                            'dealer_id' => (int) $dealer->id,
                        ],
                        $portal_url
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html($dealer->dealer_name); ?></td>
                        <td><?php echo esc_html($dealer->auth_code); ?></td>
                        <td><?php echo esc_html($dealer->contact_name); ?></td>
                        <td><?php echo esc_html($dealer->phone); ?></td>
                        <td><?php echo esc_html($status_label); ?></td>
                        <td><a class="aegis-portal-button is-primary" href="<?php echo esc_url($orders_url); ?>">查看订单</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
