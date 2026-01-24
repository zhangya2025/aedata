<?php
/** @var array $context */
$messages = $context['messages'];
$errors = $context['errors'];
$query_result = $context['query_result'];
$reset_result = $context['reset_result'];
$actor = $context['actor'];
$per_page = $context['per_page'];
$per_page_opts = $context['per_page_opts'];
$paged = $context['paged'];
$logs = $context['logs'];
$total_logs = $context['total_logs'];
$base_url = $context['base_url'];
$idempotency = $context['idempotency'];
?>
<div class="aegis-system-root aegis-reset-b">
    <div class="aegis-t-a3" style="margin-bottom:8px;">清零查询次数</div>
    <div class="aegis-t-a6" style="color:#506176; margin-bottom:12px;">按防伪码逐个清零消费者查询次数（B），A 计数保持不变。</div>

    <?php if (!empty($messages)) : ?>
        <?php foreach ($messages as $msg) : ?>
            <div class="aegis-alert success aegis-t-a6"><?php echo esc_html($msg); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <?php foreach ($errors as $err) : ?>
            <div class="aegis-alert error aegis-t-a6"><?php echo esc_html($err); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" class="aegis-reset-form">
        <?php wp_nonce_field('aegis_reset_b_action', 'aegis_reset_b_nonce'); ?>
        <input type="hidden" name="_aegis_idempotency" value="<?php echo esc_attr($idempotency); ?>" />
        <input type="hidden" name="reset_b_action" value="query" />

        <div class="aegis-form-row">
            <label class="aegis-t-a6" for="aegis-reset-code">防伪码</label>
            <input id="aegis-reset-code" type="text" name="code_value" value="" placeholder="请输入或扫码防伪码" required />
        </div>

        <div class="aegis-form-row">
            <label class="aegis-t-a6" for="aegis-reset-reason">原因（可选）</label>
            <input id="aegis-reset-reason" type="text" name="reason" value="" placeholder="请输入清零原因（可留空）" />
        </div>

        <div class="aegis-form-actions">
            <button type="submit" class="aegis-btn aegis-t-a6">查询</button>
            <button type="submit" class="aegis-btn primary aegis-t-a6" name="reset_b_action" value="reset" onclick="return confirm('确认清零该防伪码的查询次数？');" <?php echo $actor['warehouse_readonly'] ? 'disabled' : ''; ?>>清零</button>
            <label class="aegis-t-a6" style="margin-left:12px;">
                <input type="checkbox" name="confirm_reset" value="1" /> 我确认仅清零消费者查询次数
            </label>
            <?php if ($actor['warehouse_readonly']) : ?>
                <span class="aegis-t-a6" style="margin-left:12px; color:#8a9099;">当前角色仅可查询，禁止清零。</span>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($query_result) : ?>
        <div class="aegis-card" style="margin-top:16px;">
            <div class="aegis-t-a4" style="margin-bottom:8px;">查询结果</div>
            <div class="aegis-t-a6 aegis-reset-grid">
                <div><span class="aegis-label">防伪码：</span><?php echo esc_html(AEGIS_System::format_code_display($query_result['code'])); ?></div>
                <div><span class="aegis-label">查询次数：</span><?php echo esc_html((int) $query_result['b_display']); ?></div>
                <div><span class="aegis-label">经销商：</span><?php echo esc_html($query_result['dealer_label']); ?></div>
                <?php if ($actor['is_hq'] || $actor['is_warehouse']) : ?>
                    <div><span class="aegis-label">SKU：</span><?php echo esc_html($query_result['ean']); ?></div>
                <?php endif; ?>
                <div><span class="aegis-label">可清零：</span><?php echo $query_result['can_reset'] && !$actor['warehouse_readonly'] ? '<span class="status-ok">是</span>' : '<span class="status-warn">否</span>'; ?></div>
                <?php if (!$query_result['can_reset'] || $actor['warehouse_readonly']) : ?>
                    <div><span class="aegis-label">原因：</span><?php echo esc_html($query_result['restriction']); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($reset_result) : ?>
        <div class="aegis-card" style="margin-top:12px; border-color:#58bd7d;">
            <div class="aegis-t-a5" style="color:#207245;">已清零</div>
            <div class="aegis-t-a6">防伪码：<?php echo esc_html(AEGIS_System::format_code_display($reset_result['code'])); ?></div>
            <div class="aegis-t-a6">清零前：<?php echo esc_html((int) $reset_result['before_b']); ?>，清零后：<?php echo esc_html((int) $reset_result['after_b']); ?></div>
            <?php if (!empty($reset_result['dealer_label'])) : ?>
                <div class="aegis-t-a6">归属：<?php echo esc_html($reset_result['dealer_label']); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$actor['warehouse_readonly']) : ?>
        <div class="aegis-card" style="margin-top:20px;">
            <div class="aegis-t-a4" style="margin-bottom:8px;">清零记录</div>
            <?php if (!empty($logs)) : ?>
                <div class="aegis-reset-table">
                    <div class="aegis-reset-head aegis-t-a6">
                        <div>时间</div>
                        <div>防伪码</div>
                        <div>查询次数（前→后）</div>
                        <div>经销商</div>
                        <div>操作人</div>
                    </div>
                    <?php foreach ($logs as $row) : ?>
                        <div class="aegis-reset-row aegis-t-a6">
                            <div><?php echo esc_html($row->reset_at); ?></div>
                            <div><?php echo esc_html(AEGIS_System::format_code_display($row->code_value)); ?></div>
                            <div><?php echo esc_html((int) $row->before_b); ?> → <?php echo esc_html((int) $row->after_b); ?></div>
                            <div><?php echo esc_html($row->dealer_name ? $row->dealer_name : ''); ?></div>
                            <div><?php echo esc_html($row->actor_role); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_logs > $per_page) : ?>
                    <div class="aegis-pagination aegis-t-a6">
                        <?php
                        $total_pages = (int) ceil($total_logs / $per_page);
                        for ($i = 1; $i <= $total_pages; $i++) {
                            $url = add_query_arg([
                                'm'        => 'reset_b',
                                'paged'    => $i,
                                'per_page' => $per_page,
                            ], $base_url);
                            $class = $i === $paged ? 'active' : '';
                            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($i) . '</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="aegis-t-a6" style="color:#8a9099;">暂无清零记录。</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
