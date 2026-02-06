<?php
if (!defined('ABSPATH')) {
    exit;
}

class AEGIS_System_Roles {
    /**
     * 确保角色与能力存在且幂等。
     */
    public static function sync_roles() {
        $definitions = self::get_role_definitions();

        foreach ($definitions as $role_key => $def) {
            $role = get_role($role_key);
            if (!$role) {
                $role = add_role($role_key, $def['label'], ['read' => true]);
            }

            if (!$role) {
                continue;
            }

            foreach ($def['caps'] as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                }
            }

            if ('aegis_sales' === $role_key) {
                $role->remove_cap(AEGIS_System::CAP_ACCESS_ROOT);
                $role->remove_cap(AEGIS_System::CAP_ORDERS_PAYMENT_REVIEW);
                $role->remove_cap(AEGIS_System::CAP_ORDERS_MANAGE_ALL);
                $role->remove_cap(AEGIS_System::CAP_MANAGE_SYSTEM);
                $role->remove_cap(AEGIS_System::CAP_MANAGE_WAREHOUSE);
                $role->remove_cap(AEGIS_System::CAP_USE_WAREHOUSE);
                $role->remove_cap(AEGIS_System::CAP_RESET_B);
            }
        }

        $admin_role = get_role('administrator');
        if ($admin_role) {
            $fallback_caps = [
                AEGIS_System::CAP_ACCESS_ROOT,
                AEGIS_System::CAP_MANAGE_SYSTEM,
                AEGIS_System::CAP_MANAGE_WAREHOUSE,
                AEGIS_System::CAP_USE_WAREHOUSE,
                AEGIS_System::CAP_RESET_B,
                AEGIS_System::CAP_ORDERS_VIEW_ALL,
                AEGIS_System::CAP_ORDERS_INITIAL_REVIEW,
                AEGIS_System::CAP_ORDERS_PAYMENT_REVIEW,
                AEGIS_System::CAP_ORDERS_MANAGE_ALL,
                AEGIS_System::CAP_RETURNS_DEALER_APPLY,
                AEGIS_System::CAP_RETURNS_DEALER_SUBMIT,
                AEGIS_System::CAP_RETURNS_SALES_REVIEW,
                AEGIS_System::CAP_RETURNS_OVERRIDE_ISSUE,
                AEGIS_System::CAP_RETURNS_OVERRIDE_REVOKE,
                AEGIS_System::CAP_RETURNS_WAREHOUSE_CHECK,
                AEGIS_System::CAP_RETURNS_FINANCE_REVIEW,
            ];

            foreach ($fallback_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * 当前用户是否具备系统访问根权限。
     *
     * @return bool
     */
    public static function user_can_access_root() {
        return current_user_can(AEGIS_System::CAP_ACCESS_ROOT)
            || current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM)
            || current_user_can(AEGIS_System::CAP_MANAGE_WAREHOUSE)
            || current_user_can(AEGIS_System::CAP_USE_WAREHOUSE)
            || current_user_can(AEGIS_System::CAP_RESET_B)
            || current_user_can(AEGIS_System::CAP_ORDERS_VIEW_ALL)
            || current_user_can(AEGIS_System::CAP_ORDERS_INITIAL_REVIEW)
            || current_user_can(AEGIS_System::CAP_ORDERS_PAYMENT_REVIEW)
            || current_user_can(AEGIS_System::CAP_ORDERS_MANAGE_ALL);
    }

    public static function user_can_manage_system() {
        return current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM);
    }

    public static function user_can_manage_warehouse() {
        return current_user_can(AEGIS_System::CAP_MANAGE_WAREHOUSE) || current_user_can(AEGIS_System::CAP_MANAGE_SYSTEM);
    }

    public static function user_can_use_warehouse() {
        return current_user_can(AEGIS_System::CAP_USE_WAREHOUSE)
            || self::user_can_manage_warehouse();
    }

    public static function user_can_reset_b() {
        $user = wp_get_current_user();
        if (!$user || empty($user->roles)) {
            return false;
        }

        $roles = (array) $user->roles;
        if (in_array('aegis_hq_admin', $roles, true) || self::user_can_manage_system()) {
            return true;
        }

        if (in_array('aegis_dealer', $roles, true)) {
            return true;
        }

        return false;
    }

    /**
     * 获取用户角色列表。
     *
     * @param WP_User|null $user
     * @return array
     */
    public static function get_user_roles($user = null) {
        if (null === $user) {
            $user = wp_get_current_user();
        }

        if (!$user || empty($user->roles)) {
            return [];
        }

        return (array) $user->roles;
    }

    /**
     * 是否为总部管理员。
     *
     * @param WP_User|null $user
     * @return bool
     */
    public static function is_hq_admin($user = null) {
        return in_array('aegis_hq_admin', self::get_user_roles($user), true);
    }

    /**
     * 是否为仓库管理员角色。
     *
     * @param WP_User|null $user
     * @return bool
     */
    public static function is_warehouse_manager($user = null) {
        return in_array('aegis_warehouse_manager', self::get_user_roles($user), true);
    }

    /**
     * 是否为仓库员工角色。
     *
     * @param WP_User|null $user
     * @return bool
     */
    public static function is_warehouse_staff($user = null) {
        return in_array('aegis_warehouse_staff', self::get_user_roles($user), true);
    }

    /**
     * 是否为仓库相关角色。
     *
     * @param WP_User|null $user
     * @return bool
     */
    public static function is_warehouse_user($user = null) {
        $roles = self::get_user_roles($user);
        return in_array('aegis_warehouse_manager', $roles, true)
            || in_array('aegis_warehouse_staff', $roles, true);
    }

    /**
     * 是否为仅经销商角色。
     *
     * @return bool
     */
    public static function is_dealer_only() {
        $roles = self::get_user_roles();
        return 1 === count($roles) && in_array('aegis_dealer', $roles, true);
    }

    /**
     * 业务角色集合。
     *
     * @return array
     */
    public static function get_business_roles() {
        return [
            'aegis_hq_admin',
            'aegis_warehouse_manager',
            'aegis_warehouse_staff',
            'aegis_dealer',
            'aegis_sales',
            'aegis_finance',
        ];
    }

    /**
     * 是否为业务角色用户。
     *
     * @param WP_User|null $user
     * @return bool
     */
    public static function is_business_user($user = null) {
        if (null === $user) {
            $user = wp_get_current_user();
        }

        if (!$user || empty($user->roles)) {
            return false;
        }

        $roles = (array) $user->roles;
        return !empty(array_intersect($roles, self::get_business_roles()));
    }

    /**
     * 角色定义。
     *
     * @return array
     */
    protected static function get_role_definitions() {
        return [
            'aegis_hq_admin'          => [
                'label' => 'AEGIS HQ 管理员',
                'caps'  => [
                    'read'                                 => true,
                    AEGIS_System::CAP_ACCESS_ROOT          => true,
                    AEGIS_System::CAP_MANAGE_SYSTEM        => true,
                    AEGIS_System::CAP_MANAGE_WAREHOUSE     => true,
                    AEGIS_System::CAP_USE_WAREHOUSE        => true,
                    AEGIS_System::CAP_RESET_B              => true,
                    AEGIS_System::CAP_ORDERS               => true,
                    AEGIS_System::CAP_ORDERS_VIEW_ALL      => true,
                    AEGIS_System::CAP_ORDERS_INITIAL_REVIEW => true,
                    AEGIS_System::CAP_ORDERS_PAYMENT_REVIEW => true,
                    AEGIS_System::CAP_ORDERS_MANAGE_ALL    => true,
                    AEGIS_System::CAP_ACCESS_AUDIT_VIEW    => true,
                    AEGIS_System::CAP_RETURNS_DEALER_APPLY => true,
                    AEGIS_System::CAP_RETURNS_DEALER_SUBMIT => true,
                    AEGIS_System::CAP_RETURNS_SALES_REVIEW => true,
                    AEGIS_System::CAP_RETURNS_OVERRIDE_ISSUE => true,
                    AEGIS_System::CAP_RETURNS_OVERRIDE_REVOKE => true,
                    AEGIS_System::CAP_RETURNS_WAREHOUSE_CHECK => true,
                    AEGIS_System::CAP_RETURNS_FINANCE_REVIEW => true,
                ],
            ],
            'aegis_sales'            => [
                'label' => 'AEGIS 销售人员',
                'caps'  => [
                    'read'                                   => true,
                    AEGIS_System::CAP_ORDERS_VIEW_ALL        => true,
                    AEGIS_System::CAP_ORDERS_INITIAL_REVIEW  => true,
                    AEGIS_System::CAP_RETURNS_SALES_REVIEW   => true,
                ],
            ],
            'aegis_finance'          => [
                'label' => 'AEGIS 财务人员',
                'caps'  => [
                    'read'                                   => true,
                    AEGIS_System::CAP_ACCESS_ROOT            => true,
                    AEGIS_System::CAP_ORDERS_VIEW_ALL        => true,
                    AEGIS_System::CAP_ORDERS_PAYMENT_REVIEW  => true,
                    AEGIS_System::CAP_RETURNS_FINANCE_REVIEW => true,
                ],
            ],
            'aegis_warehouse_manager' => [
                'label' => 'AEGIS 仓库管理员',
                'caps'  => [
                    'read'                                 => true,
                    AEGIS_System::CAP_ACCESS_ROOT          => true,
                    AEGIS_System::CAP_MANAGE_WAREHOUSE     => true,
                    AEGIS_System::CAP_USE_WAREHOUSE        => true,
                    AEGIS_System::CAP_RETURNS_WAREHOUSE_CHECK => true,
                ],
            ],
            'aegis_warehouse_staff'   => [
                'label' => 'AEGIS 仓库员工',
                'caps'  => [
                    'read'                                 => true,
                    AEGIS_System::CAP_ACCESS_ROOT          => true,
                    AEGIS_System::CAP_USE_WAREHOUSE        => true,
                    AEGIS_System::CAP_RETURNS_WAREHOUSE_CHECK => true,
                ],
            ],
            'aegis_dealer'            => [
                'label' => 'AEGIS 经销商',
                'caps'  => [
                    'read'                                 => true,
                    AEGIS_System::CAP_ACCESS_ROOT          => true,
                    AEGIS_System::CAP_RESET_B              => true,
                    AEGIS_System::CAP_ORDERS_CREATE         => true,
                    AEGIS_System::CAP_RETURNS_DEALER_APPLY  => true,
                    AEGIS_System::CAP_RETURNS_DEALER_SUBMIT => true,
                ],
            ],
        ];
    }
}
