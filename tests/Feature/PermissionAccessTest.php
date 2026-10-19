<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\PosSale;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_employee_can_open_the_dashboard_without_feature_permissions(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get('/backoffice/dashboard');

        $response->assertOk();
    }

    #[DataProvider('featureRoutes')]
    public function test_employee_without_permission_is_forbidden(string $url): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get($url);

        $response->assertForbidden();
    }

    #[DataProvider('featureRoutes')]
    public function test_employee_with_permission_can_open_the_feature(string $url, string $permission): void
    {
        $role = Role::factory()->withPermissions([$permission])->create();
        $employee = Employee::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAsEmployee($employee)->get($url);

        $response->assertOk();
    }

    public function test_employee_without_register_access_is_forbidden(): void
    {
        $branch = Branch::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get("/backoffice/pos/register/{$branch->id}");

        $response->assertForbidden();
    }

    public function test_employee_with_register_access_can_open_the_register(): void
    {
        $branch = Branch::factory()->create();
        $role = Role::factory()->withPermissions(['pos.access'])->create();
        $employee = Employee::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAsEmployee($employee)->get("/backoffice/pos/register/{$branch->id}");

        $response->assertOk();
    }

    public function test_employee_who_can_view_sales_cannot_refund_without_permission(): void
    {
        $sale = PosSale::factory()->create();
        $role = Role::factory()->withPermissions(['sales.view'])->create();
        $employee = Employee::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAsEmployee($employee)->post("/backoffice/sales/receipts/{$sale->id}/refund", [
            'reason' => 'Wrong item',
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function featureRoutes(): array
    {
        return [
            'sales' => ['/backoffice/sales', 'sales.view'],
            'receipts' => ['/backoffice/sales/receipts', 'sales.view'],
            'sold items' => ['/backoffice/sales/items', 'sales.view'],
            'shifts' => ['/backoffice/sales/shifts', 'sales.view'],
            'catalog' => ['/backoffice/shop/catalog', 'shop.catalog'],
            'categories and brands' => ['/backoffice/shop/catalog-meta', 'shop.catalog-meta'],
            'modifiers' => ['/backoffice/shop/modifiers', 'shop.modifiers'],
            'branches' => ['/backoffice/shop/branches', 'shop.branches'],
            'shop settings' => ['/backoffice/shop/settings', 'shop.settings'],
            'inventory' => ['/backoffice/inventory', 'inventory.view'],
            'purchase orders' => ['/backoffice/inventory/purchase-orders', 'inventory.purchase-orders'],
            'delivery orders' => ['/backoffice/inventory/delivery-orders', 'inventory.delivery-orders'],
            'stock assignments' => ['/backoffice/inventory/stock-assignments', 'inventory.stock-assignments'],
            'suppliers' => ['/backoffice/inventory/suppliers', 'inventory.suppliers'],
            'pos categories' => ['/backoffice/pos/categories', 'pos.categories'],
            'pos settings' => ['/backoffice/pos/settings', 'pos.settings'],
            'staff' => ['/backoffice/staff', 'staff.manage'],
            'roles' => ['/backoffice/staff/roles', 'roles.manage'],
        ];
    }
}
