<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Access_Audit_Module {
    public static function render_portal_panel($portal_url) {
        $user = wp_get_current_user();
        if (!$user || !AEGIS_System_Roles::user_can_access_root()) {
            return '<div class="aegis-t-a5">当前账号无权访问审计日志。</div>';
        }

        $filters = self::read_filters();
        $can_export = in_array('aegis_hq_admin', (array) $user->roles, true) || AEGIS_System_Roles::user_can_manage_system();

        if (!empty($_GET['export']) && 'csv' === sanitize_key(wp_unslash($_GET['export']))) {
            if ($can_export) {
                self::stream_csv($filters);
            }
        }

        $result = AEGIS_Access_Audit::query_events($filters);
        $total_pages = $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 1;

        $context = [
            'portal_url'  => $portal_url,
            'filters'     => $filters,
            'events'      => $result['items'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
            'total_pages' => $total_pages,
            'can_export'  => $can_export,
        ];

        return AEGIS_Portal::render_portal_template('audit', $context);
    }

    protected static function read_filters() {
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        if (!in_array($per_page, [20, 50, 100], true)) {
            $per_page = 20;
        }

        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $filters = [
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : gmdate('Y-m-d', strtotime('-7 days')),
            'end_date'   => isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : gmdate('Y-m-d'),
            'event_key'  => isset($_GET['event_key']) ? sanitize_text_field(wp_unslash($_GET['event_key'])) : '',
            'actor_role' => isset($_GET['actor_role']) ? sanitize_text_field(wp_unslash($_GET['actor_role'])) : '',
            'result'     => isset($_GET['result']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['result']))) : '',
            'per_page'   => $per_page,
            'page'       => $page,
        ];

        return $filters;
    }

    protected static function stream_csv($filters) {
        $filters['per_page'] = 1000;
        $filters['page'] = 1;
        $data = AEGIS_Access_Audit::query_events($filters);

        $filename = 'aegis-audit-' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        $out = fopen('php://output', 'w');
        fputcsv($out, ['时间', '事件', '结果', '角色', '用户', '实体类型', '实体ID', '路径', '消息', 'Meta']);

        foreach ($data['items'] as $row) {
            $meta = !empty($row->meta_json) ? $row->meta_json : '';
            fputcsv($out, [
                $row->created_at,
                $row->event_key,
                $row->result,
                $row->actor_role,
                $row->actor_login,
                $row->entity_type,
                $row->entity_id,
                $row->request_path,
                $row->message,
                $meta,
            ]);
        }

        fclose($out);
        exit;
    }
}
