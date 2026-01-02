<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_Pricing {
    const LEVEL_AGENT = 'agent';
    const LEVEL_DEALER = 'dealer';
    const LEVEL_CORE = 'core';

    /**
     * 价格等级标签。
     *
     * @return array
     */
    public static function get_price_levels() {
        return [
            self::LEVEL_AGENT  => '一级代理商',
            self::LEVEL_DEALER => '一级经销商',
            self::LEVEL_CORE   => '核心合作商',
        ];
    }

    /**
     * 解析价格输入，返回格式化字符串或 null。
     *
     * @param mixed $value
     * @return array
     */
    public static function normalize_price_input($value) {
        if ('' === $value || null === $value) {
            return ['valid' => true, 'value' => null];
        }

        $string = is_string($value) ? trim($value) : $value;
        if (!is_numeric($string)) {
            return ['valid' => false, 'message' => '价格必须为数字。'];
        }

        $number = (float) $string;
        if ($number < 0) {
            return ['valid' => false, 'message' => '价格不可为负。'];
        }

        $formatted = number_format($number, 2, '.', '');
        $parts = explode('.', $formatted);
        if (count($parts) === 2 && strlen($parts[1]) > 2) {
            return ['valid' => false, 'message' => '价格最多保留两位小数。'];
        }

        return ['valid' => true, 'value' => $formatted];
    }

    /**
     * 获取经销商覆盖价记录。
     *
     * @param int $dealer_id
     * @param string $ean
     * @return object|null
     */
    public static function get_dealer_override($dealer_id, $ean) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_PRICE_TABLE;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE dealer_id = %d AND ean = %s",
                $dealer_id,
                $ean
            )
        );
    }

    /**
     * 获取经销商覆盖价列表。
     *
     * @param int $dealer_id
     * @return array
     */
    public static function list_overrides($dealer_id) {
        global $wpdb;
        $table = $wpdb->prefix . AEGIS_System::DEALER_PRICE_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $sql = "SELECT p.*, s.product_name FROM {$table} AS p LEFT JOIN {$sku_table} AS s ON p.ean = s.ean WHERE p.dealer_id = %d ORDER BY p.updated_at DESC";
        return $wpdb->get_results($wpdb->prepare($sql, $dealer_id));
    }

    /**
     * 可复用的报价查询。
     *
     * @param int $dealer_id
     * @param string $ean
     * @return array|WP_Error
     */
    public static function get_quote($dealer_id, $ean) {
        global $wpdb;
        $dealer_table = $wpdb->prefix . AEGIS_System::DEALER_TABLE;
        $sku_table = $wpdb->prefix . AEGIS_System::SKU_TABLE;
        $dealer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$dealer_table} WHERE id = %d", $dealer_id));
        if (!$dealer) {
            return new WP_Error('dealer_missing', '未找到经销商。');
        }

        $sku = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sku_table} WHERE ean = %s", $ean));
        if (!$sku) {
            return new WP_Error('sku_missing', '未找到对应 SKU。');
        }

        $override = self::get_dealer_override($dealer_id, $ean);
        if ($override && null !== $override->price_override) {
            return [
                'unit_price'       => number_format((float) $override->price_override, 2, '.', ''),
                'price_source'     => 'override',
                'price_level_used' => $dealer->price_level,
            ];
        }

        $level = $dealer->price_level;
        $tier_map = [
            self::LEVEL_AGENT  => 'price_tier_agent',
            self::LEVEL_DEALER => 'price_tier_dealer',
            self::LEVEL_CORE   => 'price_tier_core',
        ];
        $column = isset($tier_map[$level]) ? $tier_map[$level] : null;
        $tier_value = ($column && isset($sku->$column)) ? $sku->$column : null;

        if (null !== $tier_value && $tier_value !== '') {
            return [
                'unit_price'       => number_format((float) $tier_value, 2, '.', ''),
                'price_source'     => 'tier',
                'price_level_used' => $level,
            ];
        }

        return [
            'unit_price'       => null,
            'price_source'     => null,
            'price_level_used' => $level,
            'reason'           => 'missing_price',
        ];
    }
}
