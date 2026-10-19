<?php

namespace App\Support;

class PermissionCatalog
{
    /**
     * Features an employee can be allowed to use.
     *
     * @return array<int, array{key: string, name: string, group: string}>
     */
    public static function all(): array
    {
        return [
            ['key' => 'sales.view', 'name' => 'View sales', 'group' => 'Sales'],
            ['key' => 'sales.refund', 'name' => 'Refund receipts', 'group' => 'Sales'],
            ['key' => 'shop.catalog', 'name' => 'Manage catalog', 'group' => 'Shop'],
            ['key' => 'shop.catalog-meta', 'name' => 'Manage categories and brands', 'group' => 'Shop'],
            ['key' => 'shop.modifiers', 'name' => 'Manage modifiers', 'group' => 'Shop'],
            ['key' => 'shop.branches', 'name' => 'Manage branches', 'group' => 'Shop'],
            ['key' => 'shop.settings', 'name' => 'Manage shop settings', 'group' => 'Shop'],
            ['key' => 'inventory.view', 'name' => 'View inventory', 'group' => 'Inventory'],
            ['key' => 'inventory.adjust', 'name' => 'Transfer and convert stock', 'group' => 'Inventory'],
            ['key' => 'inventory.purchase-orders', 'name' => 'Manage purchase orders', 'group' => 'Inventory'],
            ['key' => 'inventory.delivery-orders', 'name' => 'Receive deliveries', 'group' => 'Inventory'],
            ['key' => 'inventory.stock-assignments', 'name' => 'Assign stock', 'group' => 'Inventory'],
            ['key' => 'inventory.suppliers', 'name' => 'Manage suppliers', 'group' => 'Inventory'],
            ['key' => 'pos.access', 'name' => 'Use the register', 'group' => 'POS'],
            ['key' => 'pos.open-drawer', 'name' => 'Open the cash drawer', 'group' => 'POS'],
            ['key' => 'pos.categories', 'name' => 'Manage POS categories', 'group' => 'POS'],
            ['key' => 'pos.settings', 'name' => 'Manage POS settings', 'group' => 'POS'],
            ['key' => 'staff.manage', 'name' => 'Manage staff', 'group' => 'Staff'],
            ['key' => 'roles.manage', 'name' => 'Manage roles', 'group' => 'Staff'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }
}
