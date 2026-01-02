<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_System_Schema {
    const OPTION_KEY = 'aegis_schema_version';

    /**
     * 根据版本执行安装或升级。
     */
    public static function maybe_upgrade() {
        global $wpdb;
        $installed = get_option(self::OPTION_KEY, '0');
        if (!is_string($installed) || $installed === '') {
            $installed = '0';
        }

        if (version_compare($installed, AEGIS_System::SCHEMA_VERSION, '>=')) {
            return;
        }

        $wpdb->last_error = '';
        $executed = self::install_tables();
        $result = empty($wpdb->last_error) ? 'SUCCESS' : 'FAIL';

        if ($result === 'SUCCESS') {
            update_option(self::OPTION_KEY, AEGIS_System::SCHEMA_VERSION, true);
        }

        AEGIS_Access_Audit::record_event(
            AEGIS_System::ACTION_SCHEMA_UPGRADE,
            $result,
            [
                'from_version' => $installed,
                'to_version'   => AEGIS_System::SCHEMA_VERSION,
                'statements'   => $executed,
                'db_error'     => $wpdb->last_error,
            ]
        );
    }

    /**
     * 返回建表 SQL 集合。
     *
     * @param string $charset_collate
     * @return array
     */
    protected static function get_table_sql($charset_collate) {
        global $wpdb;
        $audit_table = $wpdb->prefix . AEGIS_System::AUDIT_TABLE;
        $media_table = $wpdb->prefix . AEGIS_System::MEDIA_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $code_batch_table = $wpdb->prefix . AEGIS_System::CODE_BATCH_TABLE;
        $code_table = $wpdb->prefix . AEGIS_System::CODE_TABLE;
        $shipment_table = $wpdb->prefix . AEGIS_System::SHIPMENT_TABLE;
        $shipment_item_table = $wpdb->prefix . AEGIS_System::SHIPMENT_ITEM_TABLE;
        $receipt_table = $wpdb->prefix . AEGIS_System::RECEIPT_TABLE;
        $receipt_item_table = $wpdb->prefix . AEGIS_System::RECEIPT_ITEM_TABLE;
        $query_log_table = $wpdb->prefix . AEGIS_System::QUERY_LOG_TABLE;
        $order_table = $wpdb->prefix . AEGIS_System::ORDER_TABLE;
        $order_item_table = $wpdb->prefix . AEGIS_System::ORDER_ITEM_TABLE;
        $payment_table = $wpdb->prefix . AEGIS_System::PAYMENT_TABLE;

        $audit_sql = "CREATE TABLE {$audit_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_id BIGINT(20) UNSIGNED NULL,
            actor_login VARCHAR(60) NULL,
            action VARCHAR(64) NOT NULL,
            result VARCHAR(20) NOT NULL,
            object_data LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $media_sql = "CREATE TABLE {$media_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_type VARCHAR(64) NOT NULL,
            owner_id BIGINT(20) UNSIGNED NULL,
            file_path TEXT NOT NULL,
            mime VARCHAR(191) NULL,
            file_hash VARCHAR(128) NULL,
            visibility VARCHAR(32) NOT NULL DEFAULT 'private',
            uploaded_by BIGINT(20) UNSIGNED NULL,
            uploaded_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY owner (owner_type, owner_id),
            KEY visibility (visibility),
            KEY uploaded_at (uploaded_at)
        ) {$charset_collate};";

        $sku_sql = "CREATE TABLE {$sku_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ean VARCHAR(64) NOT NULL,
            product_name VARCHAR(191) NOT NULL,
            size_label VARCHAR(100) NULL,
            color_label VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            product_image_id BIGINT(20) UNSIGNED NULL,
            certificate_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ean (ean),
            KEY status (status),
            KEY created_at (created_at),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $dealer_sql = "CREATE TABLE {$dealer_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            auth_code VARCHAR(64) NOT NULL,
            dealer_name VARCHAR(191) NOT NULL,
            contact_name VARCHAR(191) NULL,
            phone VARCHAR(64) NULL,
            address VARCHAR(255) NULL,
            auth_start_date DATE NULL,
            auth_end_date DATE NULL,
            authorized_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            business_license_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY auth_code (auth_code),
            KEY status (status),
            KEY auth_start_date (auth_start_date),
            KEY auth_end_date (auth_end_date),
            KEY authorized_at (authorized_at),
            KEY created_at (created_at),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $code_batch_sql = "CREATE TABLE {$code_batch_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ean VARCHAR(64) NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 0,
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY ean (ean),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $code_sql = "CREATE TABLE {$code_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id BIGINT(20) UNSIGNED NOT NULL,
            ean VARCHAR(64) NOT NULL,
            code VARCHAR(128) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'unused',
            stock_status VARCHAR(32) NOT NULL DEFAULT 'generated',
            stocked_at DATETIME NULL,
            stocked_by BIGINT(20) UNSIGNED NULL,
            receipt_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            printed_at DATETIME NULL,
            exported_at DATETIME NULL,
            query_a_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            query_b_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            query_b_offset BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            last_query_at DATETIME NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY batch_id (batch_id),
            KEY ean (ean),
            KEY status (status),
            KEY stock_status (stock_status),
            KEY created_at (created_at),
            KEY last_query_at (last_query_at)
        ) {$charset_collate};";

        $shipment_sql = "CREATE TABLE {$shipment_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shipment_no VARCHAR(100) NOT NULL,
            dealer_id BIGINT(20) UNSIGNED NOT NULL,
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            qty INT(11) NOT NULL DEFAULT 0,
            note VARCHAR(255) NULL,
            order_ref VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'created',
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY shipment_no (shipment_no),
            KEY dealer_id (dealer_id),
            KEY created_at (created_at),
            KEY status (status)
        ) {$charset_collate};";

        $shipment_item_sql = "CREATE TABLE {$shipment_item_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            shipment_id BIGINT(20) UNSIGNED NOT NULL,
            code_id BIGINT(20) UNSIGNED NOT NULL,
            code_value VARCHAR(128) NOT NULL,
            ean VARCHAR(64) NOT NULL,
            scanned_at DATETIME NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_unique (code_id),
            KEY shipment_id (shipment_id),
            KEY code_value (code_value),
            KEY ean (ean),
            KEY scanned_at (scanned_at)
        ) {$charset_collate};";

        $receipt_sql = "CREATE TABLE {$receipt_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            receipt_no VARCHAR(120) NOT NULL,
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            qty INT(11) NOT NULL DEFAULT 0,
            note VARCHAR(255) NULL,
            batch_id BIGINT(20) UNSIGNED NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY receipt_no (receipt_no),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $receipt_item_sql = "CREATE TABLE {$receipt_item_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            receipt_id BIGINT(20) UNSIGNED NOT NULL,
            code_id BIGINT(20) UNSIGNED NOT NULL,
            ean VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_unique (code_id),
            KEY receipt_id (receipt_id),
            KEY ean (ean)
        ) {$charset_collate};";

        $query_log_sql = "CREATE TABLE {$query_log_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            code_id BIGINT(20) UNSIGNED NOT NULL,
            code_value VARCHAR(128) NOT NULL,
            query_channel VARCHAR(10) NOT NULL,
            context VARCHAR(20) NOT NULL,
            client_ip VARCHAR(100) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY code_channel (code_id, query_channel),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $order_sql = "CREATE TABLE {$order_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_no VARCHAR(120) NOT NULL,
            dealer_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            total_amount DECIMAL(20,4) NULL,
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_no (order_no),
            KEY dealer_id (dealer_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $order_item_sql = "CREATE TABLE {$order_item_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            ean VARCHAR(64) NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 1,
            status VARCHAR(40) NOT NULL DEFAULT 'open',
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY ean (ean),
            KEY status (status)
        ) {$charset_collate};";

        $payment_sql = "CREATE TABLE {$payment_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            dealer_id BIGINT(20) UNSIGNED NOT NULL,
            media_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'submitted',
            created_by BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY dealer_id (dealer_id),
            KEY media_id (media_id),
            KEY status (status)
        ) {$charset_collate};";

        return [$audit_sql, $media_sql, $sku_sql, $dealer_sql, $code_batch_sql, $code_sql, $shipment_sql, $shipment_item_sql, $receipt_sql, $receipt_item_sql, $query_log_sql, $order_sql, $order_item_sql, $payment_sql];
    }

    /**
     * 执行建表并返回执行列表。
     *
     * @return array
     */
    protected static function install_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $GLOBALS['wpdb']->get_charset_collate();
        $sqls = self::get_table_sql($charset_collate);
        $executed = [];

        foreach ($sqls as $sql) {
            $result = dbDelta($sql);
            if (is_array($result)) {
                $executed = array_merge($executed, array_values($result));
            }
        }

        return $executed;
    }
}

