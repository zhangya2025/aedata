<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_SKU {
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * 渲染 SKU 管理页面。
     */
    public static function render_admin_page() {
        if (!AEGIS_System_Roles::user_can_manage_warehouse()) {
            wp_die(__('您无权访问该页面。'));
        }

        if (!AEGIS_System::is_module_enabled('sku')) {
            echo '<div class="wrap"><h1>SKU 管理</h1><div class="notice notice-warning"><p>请先在模块管理中启用 SKU 模块。</p></div></div>';
            return;
        }

        $assets_enabled = AEGIS_System::is_module_enabled('assets_media');
        $messages = [];
        $errors = [];
        $order_link_enabled = AEGIS_Orders::is_shipment_link_enabled();
        $current_edit = null;

        if (isset($_GET['edit'])) {
            $current_edit = self::get_sku((int) $_GET['edit']);
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $action = isset($_POST['sku_action']) ? sanitize_key(wp_unslash($_POST['sku_action'])) : '';
            $idempotency = isset($_POST['_aegis_idempotency']) ? sanitize_text_field(wp_unslash($_POST['_aegis_idempotency'])) : null;
            $whitelist = ['sku_action', 'sku_id', 'ean', 'product_name', 'size_label', 'color_label', 'status', 'ean_correct', 'ean_correct_confirm', 'certificate_visibility', 'target_status', 'aegis_sku_nonce', '_wp_http_referer', '_aegis_idempotency'];
            $validation = AEGIS_Access_Audit::validate_write_request(
                $_POST,
                [
                    'capability'      => AEGIS_System::CAP_MANAGE_WAREHOUSE,
                    'nonce_field'     => 'aegis_sku_nonce',
                    'nonce_action'    => 'aegis_sku_action',
                    'whitelist'       => $whitelist,
                    'idempotency_key' => $idempotency,
                ]
            );

            if (!$validation['success']) {
                $errors[] = $validation['message'];
            } elseif ('save' === $action) {
                $result = self::handle_save_request($_POST, $_FILES, $assets_enabled);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages = array_merge($messages, $result['messages']);
                    $current_edit = $result['sku'];
                }
            } elseif ('toggle_status' === $action) {
                $result = self::handle_status_toggle($_POST);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $messages[] = $result['message'];
                }
            }
        }

        $skus = self::list_skus();

        echo '<div class="wrap aegis-system-root">';
        echo '<h1 class="aegis-t-a2">SKU 管理</h1>';
        echo '<p class="aegis-t-a6">维护产品主数据，启停状态将影响后续业务规则。</p>';

        foreach ($messages as $msg) {
            echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
        }
        foreach ($errors as $msg) {
            echo '<div class="error"><p>' . esc_html($msg) . '</p></div>';
        }

        if (!$assets_enabled) {
            echo '<div class="notice notice-warning"><p class="aegis-t-a6">附件上传依赖“资产与媒体”模块，请确保已启用。</p></div>';
        }

        self::render_form($current_edit, $assets_enabled);
        self::render_table($skus);
        echo '</div>';
    }

    /**
     * 渲染新增/编辑表单。
     *
     * @param object|null $sku
     * @param bool        $assets_enabled
     */
    protected static function render_form($sku, $assets_enabled) {
        $id = $sku ? (int) $sku->id : 0;
        $ean = $sku ? $sku->ean : '';
        $product_name = $sku ? $sku->product_name : '';
        $size_label = $sku ? $sku->size_label : '';
        $color_label = $sku ? $sku->color_label : '';
        $status = $sku ? $sku->status : self::STATUS_ACTIVE;
        $idempotency_key = wp_generate_uuid4();

        echo '<div class="aegis-t-a5" style="margin-top:20px;">';
        echo '<h2 class="aegis-t-a3">' . ($sku ? '编辑 SKU' : '新增 SKU') . '</h2>';
        echo '<form method="post" enctype="multipart/form-data" class="aegis-t-a5">';
        wp_nonce_field('aegis_sku_action', 'aegis_sku_nonce');
        echo '<input type="hidden" name="sku_action" value="save" />';
        echo '<input type="hidden" name="sku_id" value="' . esc_attr($id) . '" />';
        echo '<input type="hidden" name="_aegis_idempotency" value="' . esc_attr($idempotency_key) . '" />';

        echo '<table class="form-table">';
        echo '<tr><th><label for="aegis-ean">EAN</label></th><td>';
        echo '<input type="text" id="aegis-ean" name="ean" value="' . esc_attr($ean) . '" ' . ($sku ? 'readonly' : '') . ' class="regular-text" />';
        if ($sku) {
            echo '<p class="description aegis-t-a6">常规编辑不可修改 EAN。</p>';
            echo '<div class="aegis-t-a6" style="margin-top:8px;">';
            echo '<label><input type="checkbox" name="ean_correct_confirm" value="1" /> 启用受控更正</label><br />';
            echo '<input type="text" name="ean_correct" placeholder="新的 EAN" class="regular-text" />';
            echo '<p class="description">仅限总部管理员，提交将写入审计。</p>';
            echo '</div>';
        }
        echo '</td></tr>';

        echo '<tr><th><label for="aegis-name">产品名称</label></th><td><input type="text" id="aegis-name" name="product_name" value="' . esc_attr($product_name) . '" class="regular-text" required /></td></tr>';
        echo '<tr><th><label for="aegis-size">尺码</label></th><td><input type="text" id="aegis-size" name="size_label" value="' . esc_attr($size_label) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="aegis-color">颜色</label></th><td><input type="text" id="aegis-color" name="color_label" value="' . esc_attr($color_label) . '" class="regular-text" /></td></tr>';

        echo '<tr><th>状态</th><td><select name="status">';
        foreach (self::get_status_labels() as $value => $label) {
            $selected = selected($status, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        if ($assets_enabled) {
            echo '<tr><th>产品图片</th><td>';
            echo '<input type="file" name="product_image" accept="image/*" />';
            if ($sku && $sku->product_image_id) {
                echo '<p class="description aegis-t-a6">已关联媒体 ID：' . esc_html($sku->product_image_id) . '</p>';
            }
            echo '</td></tr>';

            echo '<tr><th>证书上传</th><td>';
            echo '<input type="file" name="certificate_file" />';
            $visibility = isset($_POST['certificate_visibility']) ? sanitize_key(wp_unslash($_POST['certificate_visibility'])) : AEGIS_Assets_Media::VISIBILITY_PRIVATE;
            echo '<p class="aegis-t-a6">证书可设置公开（public）或内部可见（internal=private）。</p>';
            echo '<label><input type="radio" name="certificate_visibility" value="private" ' . checked($visibility, 'private', false) . ' /> 内部</label> ';
            echo '<label><input type="radio" name="certificate_visibility" value="public" ' . checked($visibility, 'public', false) . ' /> 公开</label>';
            if ($sku && $sku->certificate_id) {
                echo '<p class="description aegis-t-a6" style="margin-top:6px;">已关联证书媒体 ID：' . esc_html($sku->certificate_id) . '</p>';
            }
            echo '</td></tr>';
        }

        echo '</table>';
        submit_button($sku ? '保存 SKU' : '新增 SKU');
        echo '</form>';
        echo '</div>';
    }

    /**
     * 处理保存请求。
     *
     * @param array $post
     * @param array $files
     * @param bool  $assets_enabled
     * @return array|WP_Error
     */
    protected static function handle_save_request($post, $files, $assets_enabled) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;

        $sku_id = isset($post['sku_id']) ? (int) $post['sku_id'] : 0;
        $ean_input = isset($post['ean']) ? sanitize_text_field(wp_unslash($post['ean'])) : '';
        $product_name = isset($post['product_name']) ? sanitize_text_field(wp_unslash($post['product_name'])) : '';
        $size_label = isset($post['size_label']) ? sanitize_text_field(wp_unslash($post['size_label'])) : '';
        $color_label = isset($post['color_label']) ? sanitize_text_field(wp_unslash($post['color_label'])) : '';
        $status_raw = isset($post['status']) ? sanitize_key($post['status']) : '';
        $status = $status_raw && array_key_exists($status_raw, self::get_status_labels()) ? $status_raw : self::STATUS_ACTIVE;

        $now = current_time('mysql');
        $is_new = $sku_id === 0;
        $existing = $is_new ? null : self::get_sku($sku_id);
        if (!$is_new && !$existing) {
            return new WP_Error('not_found', '未找到对应的 SKU。');
        }

        $ean_to_use = $is_new ? $ean_input : $existing->ean;
        $ean_correct = isset($post['ean_correct']) ? sanitize_text_field(wp_unslash($post['ean_correct'])) : '';
        $ean_confirm = !empty($post['ean_correct_confirm']);

        if ($is_new && '' === $ean_to_use) {
            return new WP_Error('ean_required', '请填写 EAN。');
        }

        if (!$is_new && $ean_confirm && $ean_correct) {
            $ean_to_use = $ean_correct;
        }

        if (self::ean_exists($ean_to_use, $sku_id)) {
            return new WP_Error('ean_exists', 'EAN 已存在，无法重复。');
        }

        $data = [
            'product_name' => $product_name,
            'size_label'   => $size_label,
            'color_label'  => $color_label,
            'status'       => $status,
            'updated_at'   => $now,
        ];

        $messages = [];

        if ($is_new) {
            $data['ean'] = $ean_to_use;
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            $sku_id = (int) $wpdb->insert_id;
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SKU_CREATE, 'SUCCESS', ['id' => $sku_id, 'ean' => $ean_to_use]);
            $messages[] = 'SKU 已创建。';
        } else {
            $wpdb->update($table, $data, ['id' => $sku_id]);
            AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SKU_UPDATE, 'SUCCESS', ['id' => $sku_id]);
            $messages[] = 'SKU 已更新。';

            if ($existing->status !== $status) {
                $action = self::STATUS_ACTIVE === $status ? AEGIS_System::ACTION_SKU_ENABLE : AEGIS_System::ACTION_SKU_DISABLE;
                AEGIS_Access_Audit::record_event($action, 'SUCCESS', ['id' => $sku_id, 'from' => $existing->status, 'to' => $status]);
            }

            if ($ean_to_use !== $existing->ean) {
                $wpdb->update($table, ['ean' => $ean_to_use], ['id' => $sku_id]);
                AEGIS_Access_Audit::record_event(AEGIS_System::ACTION_SKU_EAN_CORRECT, 'SUCCESS', ['id' => $sku_id, 'from' => $existing->ean, 'to' => $ean_to_use]);
                $messages[] = 'EAN 已完成受控更正。';
            }
        }

        if ($assets_enabled) {
            if (isset($files['product_image']) && is_array($files['product_image']) && !empty($files['product_image']['name'])) {
                $upload = AEGIS_Assets_Media::handle_admin_upload($files['product_image'], [
                    'bucket'     => 'sku',
                    'owner_type' => 'sku_image',
                    'owner_id'   => $sku_id,
                    'visibility' => AEGIS_Assets_Media::VISIBILITY_PRIVATE,
                    'meta'       => ['type' => 'product_image', 'sku' => $ean_to_use],
                ]);

                if (is_wp_error($upload)) {
                    $messages[] = '产品图片上传失败：' . $upload->get_error_message();
                } else {
                    $wpdb->update($table, ['product_image_id' => $upload['id']], ['id' => $sku_id]);
                    $messages[] = '产品图片已上传并关联。';
                }
            }

            if (isset($files['certificate_file']) && is_array($files['certificate_file']) && !empty($files['certificate_file']['name'])) {
                $visibility = isset($post['certificate_visibility']) && 'public' === sanitize_key($post['certificate_visibility']) ? AEGIS_Assets_Media::VISIBILITY_PUBLIC : AEGIS_Assets_Media::VISIBILITY_PRIVATE;
                $upload = AEGIS_Assets_Media::handle_admin_upload($files['certificate_file'], [
                    'bucket'     => 'certificate',
                    'owner_type' => 'certificate',
                    'owner_id'   => $sku_id,
                    'visibility' => $visibility,
                    'meta'       => ['type' => 'sku_certificate', 'sku' => $ean_to_use],
                ]);

                if (is_wp_error($upload)) {
                    $messages[] = '证书上传失败：' . $upload->get_error_message();
                } else {
                    $wpdb->update($table, ['certificate_id' => $upload['id']], ['id' => $sku_id]);
                    $messages[] = '证书已上传并关联。';
                }
            }
        }

        $sku = self::get_sku($sku_id);

        return [
            'sku'      => $sku,
            'messages' => $messages,
        ];
    }

    /**
     * 处理状态切换。
     *
     * @param array $post
     * @return array|WP_Error
     */
    protected static function handle_status_toggle($post) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $sku_id = isset($post['sku_id']) ? (int) $post['sku_id'] : 0;
        $target = isset($post['target_status']) ? sanitize_key($post['target_status']) : '';

        if (!array_key_exists($target, self::get_status_labels())) {
            return new WP_Error('bad_status', '无效的状态。');
        }

        $sku = self::get_sku($sku_id);
        if (!$sku) {
            return new WP_Error('not_found', '未找到对应的 SKU。');
        }

        $wpdb->update($table, ['status' => $target, 'updated_at' => current_time('mysql')], ['id' => $sku_id]);
        $action = self::STATUS_ACTIVE === $target ? AEGIS_System::ACTION_SKU_ENABLE : AEGIS_System::ACTION_SKU_DISABLE;
        AEGIS_Access_Audit::record_event($action, 'SUCCESS', ['id' => $sku_id, 'from' => $sku->status, 'to' => $target]);

        return ['message' => '状态已更新。'];
    }

    /**
     * 列出所有 SKU。
     *
     * @return array
     */
    protected static function list_skus() {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
    }

    /**
     * 获取单个 SKU。
     *
     * @param int $id
     * @return object|null
     */
    protected static function get_sku($id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /**
     * 检查 EAN 是否已存在。
     *
     * @param string $ean
     * @param int    $exclude_id
     * @return bool
     */
    protected static function ean_exists($ean, $exclude_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $sql = "SELECT COUNT(*) FROM {$table} WHERE ean = %s";
        $params = [$ean];
        if ($exclude_id > 0) {
            $sql .= ' AND id != %d';
            $params[] = $exclude_id;
        }
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        return ((int) $count) > 0;
    }

    /**
     * 状态字典。
     *
     * @return array
     */
    protected static function get_status_labels() {
        return [
            self::STATUS_ACTIVE   => '启用',
            self::STATUS_INACTIVE => '停用',
        ];
    }

    /**
     * 渲染列表。
     *
     * @param array $skus
     */
    protected static function render_table($skus) {
        echo '<h2 class="aegis-t-a3" style="margin-top:24px;">SKU 列表</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr class="aegis-t-a6"><th>ID</th><th>EAN</th><th>名称</th><th>尺码</th><th>颜色</th><th>状态</th><th>附件</th><th>操作</th></tr></thead>';
        echo '<tbody class="aegis-t-a6">';
        if (empty($skus)) {
            echo '<tr><td colspan="8">暂无记录。</td></tr>';
        }

        foreach ($skus as $sku) {
            $status_label = isset(self::get_status_labels()[$sku->status]) ? self::get_status_labels()[$sku->status] : $sku->status;
            echo '<tr>';
            echo '<td>' . esc_html($sku->id) . '</td>';
            echo '<td>' . esc_html($sku->ean) . '</td>';
            echo '<td>' . esc_html($sku->product_name) . '</td>';
            echo '<td>' . esc_html($sku->size_label) . '</td>';
            echo '<td>' . esc_html($sku->color_label) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '<td>';
            if ($sku->product_image_id) {
                echo '<div>图：' . esc_html($sku->product_image_id) . '</div>';
            }
            if ($sku->certificate_id) {
                echo '<div>证：' . esc_html($sku->certificate_id) . '</div>';
            }
            echo '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url(add_query_arg(['page' => 'aegis-system-sku', 'edit' => $sku->id], admin_url('admin.php'))) . '">编辑</a> ';
            $target_status = self::STATUS_ACTIVE === $sku->status ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field('aegis_sku_action', 'aegis_sku_nonce');
            echo '<input type="hidden" name="sku_action" value="toggle_status" />';
            echo '<input type="hidden" name="sku_id" value="' . esc_attr($sku->id) . '" />';
            echo '<input type="hidden" name="target_status" value="' . esc_attr($target_status) . '" />';
            echo '<button type="submit" class="button button-small">' . (self::STATUS_ACTIVE === $sku->status ? '停用' : '启用') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
}

