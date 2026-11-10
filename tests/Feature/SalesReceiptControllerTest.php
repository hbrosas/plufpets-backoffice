<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use App\Models\PosSalePayment;
use App\Models\PosShift;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesReceiptControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_receipts(): void
    {
        $response = $this->get('/backoffice/sales/receipts');

        $response->assertRedirect(route('login'));
    }

    public function test_receipts_list_today_sales_and_can_search_by_number(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);
        $visible = $this->recordSale($branch, $shift, ['number' => 'POS-KEEP', 'sold_at' => now()]);
        $this->recordSale($branch, $shift, ['number' => 'POS-HIDE', 'sold_at' => now()]);
        $this->recordSale($branch, $shift, ['number' => 'POS-OLD', 'sold_at' => now()->subDay()]);

        $response = $this->actingAsEmployee()->get('/backoffice/sales/receipts?search=KEEP');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Receipts/Index')
            ->has('receipts.data', 1)
            ->where('receipts.data.0.number', 'POS-KEEP')
            ->where('receipts.data.0.branch', 'Greenheights')
            ->where('receipts.data.0.is_refunded', false)
            ->where('receipts.data.0.total', '₱'.$this->peso($visible->total))
            ->where('receipts.data.0.items.0.product', 'The Full Pluf')
            ->where('receipts.data.0.payments.0.name', 'Cash')
            ->where('receipt', null));
    }

    public function test_receipt_show_includes_items_and_payments(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        $sale = $this->recordSale(
            $branch,
            PosShift::factory()->create(['branch_id' => $branch->id]),
            ['number' => 'POS-SHOW', 'sold_at' => now()],
        );

        $response = $this->actingAsEmployee()->get('/backoffice/sales/receipts/'.$sale->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Receipts/Index')
            ->where('receipt.number', 'POS-SHOW')
            ->where('receipts.data.0.number', 'POS-SHOW')
            ->where('receipt.branch', 'Greenheights')
            ->where('receipt.items.0.product', 'The Full Pluf')
            ->where('receipt.payments.0.name', 'Cash'));
    }

    public function test_guest_cannot_waive_a_commission(): void
    {
        $sale = PosSale::factory()->create();

        $this->post("/backoffice/sales/receipts/{$sale->id}/commissions/waive", [
            'employee_id' => 'all',
        ])->assertRedirect(route('login'));
    }

    public function test_waiving_a_commission_requires_the_permission(): void
    {
        $role = Role::factory()->withPermissions(['sales.view'])->create();
        $employee = Employee::factory()->create(['role_id' => $role->id]);
        $sale = $this->saleWithCommission(Employee::factory()->create([
            'name' => 'Ada Groomer',
            'employee_number' => '30001',
        ]), '40.00');

        $this->actingAsEmployee($employee)
            ->post("/backoffice/sales/receipts/{$sale->id}/commissions/waive", [
                'employee_id' => 'all',
            ])
            ->assertForbidden();

        $this->assertNull($sale->items()->first()->commissions()->first()->waived_at);
    }

    public function test_admin_can_waive_one_employees_commission_on_a_receipt(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09 03:00:00', 'UTC'));

        $ada = Employee::factory()->create([
            'name' => 'Ada Groomer',
            'employee_number' => '30001',
        ]);
        $ben = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);
        $sale = $this->saleWithCommission($ada, '40.00');
        $item = $sale->items()->first();
        PosSaleItemCommission::factory()->create([
            'pos_sale_item_id' => $item->id,
            'employee_id' => $ben->id,
            'employee_number' => '30002',
            'employee_name' => 'Ben Cashier',
            'amount' => '15.00',
        ]);

        $response = $this->actingAsEmployee()
            ->post("/backoffice/sales/receipts/{$sale->id}/commissions/waive", [
                'employee_id' => (string) $ada->id,
            ]);

        $response->assertRedirect(route('sales.receipts.show', $sale));
        $response->assertSessionHas('status', 'Commission waived.');

        $adaCommission = $item->commissions()->where('employee_id', $ada->id)->first();
        $benCommission = $item->commissions()->where('employee_id', $ben->id)->first();
        $this->assertNotNull($adaCommission->waived_at);
        $this->assertSame('10001', $adaCommission->waived_by_employee_number);
        $this->assertSame('Sample Employee', $adaCommission->waived_by_name);
        $this->assertSame('40.00', $adaCommission->amount);
        $this->assertNull($benCommission->waived_at);

        $this->actingAsEmployee()
            ->get("/backoffice/sales/receipts/{$sale->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_waive_commissions', true)
                ->where('receipt.commissions.0.employee_name', 'Ada Groomer')
                ->where('receipt.commissions.0.waived', true)
                ->where('receipt.commissions.0.waived_by', 'Sample Employee')
                ->where('receipt.commissions.1.employee_name', 'Ben Cashier')
                ->where('receipt.commissions.1.waived', false));

        $this->actingAsEmployee()
            ->get('/backoffice/reports/commissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.total', '₱15.00')
                ->has('report.employees', 1)
                ->where('report.employees.0.name', 'Ben Cashier'));
    }

    public function test_admin_can_waive_every_commission_on_a_receipt(): void
    {
        $ada = Employee::factory()->create([
            'name' => 'Ada Groomer',
            'employee_number' => '30001',
        ]);
        $ben = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);
        $sale = $this->saleWithCommission($ada, '40.00');
        PosSaleItemCommission::factory()->create([
            'pos_sale_item_id' => $sale->items()->first()->id,
            'employee_id' => $ben->id,
            'employee_number' => '30002',
            'employee_name' => 'Ben Cashier',
            'amount' => '15.00',
        ]);

        $this->actingAsEmployee()
            ->post("/backoffice/sales/receipts/{$sale->id}/commissions/waive", [
                'employee_id' => 'all',
            ])
            ->assertRedirect(route('sales.receipts.show', $sale))
            ->assertSessionHas('status', 'Commission waived.');

        $this->assertSame(2, $sale->items()->first()->commissions()->whereNotNull('waived_at')->count());
    }

    public function test_waive_rejects_an_employee_who_is_not_on_the_receipt(): void
    {
        $ada = Employee::factory()->create();
        $other = Employee::factory()->create();
        $sale = $this->saleWithCommission($ada, '40.00');

        $this->actingAsEmployee()
            ->from("/backoffice/sales/receipts/{$sale->id}")
            ->post("/backoffice/sales/receipts/{$sale->id}/commissions/waive", [
                'employee_id' => (string) $other->id,
            ])
            ->assertRedirect("/backoffice/sales/receipts/{$sale->id}")
            ->assertSessionHasErrors([
                'employee_id' => 'Choose an employee on this receipt.',
            ]);

        $this->assertNull($sale->items()->first()->commissions()->first()->waived_at);
    }

    public function test_waiving_an_already_waived_commission_is_rejected(): void
    {
        $ada = Employee::factory()->create();
        $sale = $this->saleWithCommission($ada, '40.00');

        $this->actingAsEmployee()
            ->post("/backoffice/sales/receipts/{$sale->id}/commissions/waive", [
                'employee_id' => (string) $ada->id,
            ])
            ->assertSessionHas('status', 'Commission waived.');

        $this->actingAsEmployee()
            ->from("/backoffice/sales/receipts/{$sale->id}")
            ->post("/backoffice/sales/receipts/{$sale->id}/commissions/waive", [
                'employee_id' => (string) $ada->id,
            ])
            ->assertRedirect("/backoffice/sales/receipts/{$sale->id}")
            ->assertSessionHasErrors([
                'employee_id' => 'Choose an employee who still has a commission on this receipt.',
            ]);
    }

    public function test_a_refunded_receipt_cannot_have_its_commission_waived(): void
    {
        $ada = Employee::factory()->create();
        $sale = $this->saleWithCommission($ada, '40.00', refunded: true);

        $this->actingAsEmployee()
            ->from("/backoffice/sales/receipts/{$sale->id}")
            ->post("/backoffice/sales/receipts/{$sale->id}/commissions/waive", [
                'employee_id' => 'all',
            ])
            ->assertRedirect("/backoffice/sales/receipts/{$sale->id}")
            ->assertSessionHasErrors([
                'employee_id' => 'This receipt is refunded.',
            ]);
    }

    /**
     * @param  array<string, mixed>  $sale
     */
    private function recordSale(Branch $branch, PosShift $shift, array $sale = []): PosSale
    {
        $recorded = PosSale::factory()->create([
            'pos_shift_id' => $shift->id,
            'branch_id' => $branch->id,
            'total' => 112,
            ...$sale,
        ]);
        PosSaleItem::factory()->create([
            'pos_sale_id' => $recorded->id,
            'product_name' => 'The Full Pluf',
            'variant_name' => 'Small',
            'quantity' => 1,
            'line_total' => $recorded->total,
        ]);
        PosSalePayment::factory()->create([
            'pos_sale_id' => $recorded->id,
            'payment_method_name' => 'Cash',
            'is_cash' => true,
            'amount' => $recorded->total,
        ]);

        return $recorded;
    }

    private function saleWithCommission(Employee $employee, string $amount, bool $refunded = false): PosSale
    {
        $branch = Branch::factory()->create();
        $sale = PosSale::factory()->create([
            'branch_id' => $branch->id,
            'sold_at' => now(),
            'refunded_at' => $refunded ? now() : null,
        ]);
        $item = PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'product_name' => 'Bath',
            'variant_name' => 'Default',
        ]);
        PosSaleItemCommission::factory()->create([
            'pos_sale_item_id' => $item->id,
            'employee_id' => $employee->id,
            'employee_number' => (string) $employee->employee_number,
            'employee_name' => $employee->name,
            'amount' => $amount,
        ]);

        return $sale;
    }

    private function peso(mixed $amount): string
    {
        return number_format((float) $amount, 2);
    }
}
