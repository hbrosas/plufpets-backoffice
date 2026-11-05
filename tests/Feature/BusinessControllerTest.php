<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CatalogItem;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\PosSale;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_business_information(): void
    {
        $response = $this->get('/backoffice/business');

        $response->assertRedirect(route('login'));
    }

    public function test_empty_name_is_rejected(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/business')
            ->post('/backoffice/business', [
                'name' => '   ',
            ]);

        $response->assertRedirect('/backoffice/business');
        $response->assertSessionHasErrors([
            'name' => 'Enter the business name.',
        ]);
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/business')
            ->post('/backoffice/business', [
                'name' => 'Harbor Grooming',
                'email' => 'not-an-email',
            ]);

        $response->assertRedirect('/backoffice/business');
        $response->assertSessionHasErrors([
            'email' => 'Enter a valid email address.',
        ]);
    }

    public function test_non_image_logo_is_rejected(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()
            ->from('/backoffice/business')
            ->post('/backoffice/business', [
                'name' => 'Harbor Grooming',
                'logo' => UploadedFile::fake()->create('logo.pdf', 20, 'application/pdf'),
            ]);

        $response->assertRedirect('/backoffice/business');
        $response->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_business_information_logo_and_favicon_can_be_saved(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()
            ->from('/backoffice/business')
            ->post('/backoffice/business', [
                'name' => 'Harbor Grooming',
                'address' => 'Ayala Avenue',
                'phone' => '09171234567',
                'email' => 'hello@harbor.test',
                'tin' => '123-456-789-00000',
                'logo' => UploadedFile::fake()->image('logo.png'),
                'favicon' => UploadedFile::fake()->image('favicon.png'),
            ]);

        $response->assertRedirect('/backoffice/business');
        $response->assertSessionHas('status', 'Business information saved.');
        $this->assertDatabaseCount('businesses', 1);

        $business = Business::query()->first();
        $this->assertNotNull($business);
        $this->assertSame('Harbor Grooming', $business->name);
        $this->assertSame('Ayala Avenue', $business->address);
        $this->assertSame('09171234567', $business->phone);
        $this->assertSame('hello@harbor.test', $business->email);
        $this->assertSame('123-456-789-00000', $business->tin);
        Storage::disk('public')->assertExists($business->logo_path);
        Storage::disk('public')->assertExists($business->favicon_path);

        $this->actingAsEmployee()
            ->get('/backoffice/business')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Business/Edit')
                ->where('business.name', 'Harbor Grooming')
                ->where('business.tin', '123-456-789-00000')
                ->where('business.logo_url', $business->logoUrl())
                ->where('business.favicon_url', $business->faviconUrl()));

        $this->get('/backoffice/business')
            ->assertOk()
            ->assertSee('<link rel="icon" href="'.$business->faviconUrl().'">', false);
    }

    public function test_saving_again_updates_the_same_record_and_can_remove_the_logo(): void
    {
        Storage::fake('public');
        $logo = UploadedFile::fake()->image('logo.png')->store('business', 'public');
        $business = Business::factory()->create([
            'name' => 'Harbor Grooming',
            'logo_path' => $logo,
        ]);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/business')
            ->post('/backoffice/business', [
                'name' => 'Harbor Pets',
                'remove_logo' => '1',
            ]);

        $response->assertRedirect('/backoffice/business');
        $this->assertDatabaseCount('businesses', 1);
        $business->refresh();
        $this->assertSame('Harbor Pets', $business->name);
        $this->assertNull($business->logo_path);
        Storage::disk('public')->assertMissing($logo);
    }

    public function test_payroll_and_purchase_order_documents_use_the_business_profile(): void
    {
        Storage::fake('public');
        $logo = UploadedFile::fake()->image('logo.png')->store('business', 'public');
        $business = Business::factory()->create([
            'name' => 'Harbor Grooming',
            'address' => 'Ayala Avenue',
            'phone' => '09171234567',
            'email' => 'hello@harbor.test',
            'tin' => '999-888-777-00000',
            'logo_path' => $logo,
        ]);
        $branch = Branch::factory()->create([
            'name' => 'Makati Branch',
            'registered_business_name' => 'Branch Trading',
            'address' => 'Branch Street',
            'tin' => '111-222-333-00000',
        ]);
        $order = PurchaseOrder::factory()->create(['branch_id' => $branch->id]);
        $employee = Employee::factory()->create();
        EmployeeProfile::factory()->create(['employee_id' => $employee->id]);

        $seen = null;
        View::composer('staff.payroll', function ($view) use (&$seen): void {
            $seen = $view->getData()['letterhead']['name'] ?? null;
        });

        $this->actingAsEmployee()
            ->get('/backoffice/staff/'.$employee->id.'/payroll/download?'.http_build_query([
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-15',
                'days_worked' => '1',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'overnight_nights' => '0',
                'deduct_government' => 0,
                'worked_holiday_ids' => [],
                'adjustments' => [],
            ]))
            ->assertOk();

        $this->assertSame('Harbor Grooming', $seen);

        $html = view('staff.payroll', [
            'employeeName' => $employee->name,
            'jobTitle' => null,
            'employmentStatus' => null,
            'dateHired' => $employee->start_date->toDateString(),
            'payroll' => [
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-15',
                'days_worked' => 1,
                'earnings' => [],
                'deductions' => [],
                'gross' => '0',
                'total_deductions' => '0',
                'net' => '0',
                'employer_shares' => [],
            ],
            'letterhead' => $business->letterhead(),
        ])->render();

        $this->assertStringContainsString('Harbor Grooming', $html);
        $this->assertStringContainsString('Ayala Avenue', $html);
        $this->assertStringContainsString('TIN 999-888-777-00000', $html);
        $this->assertStringContainsString('data:image/', $html);

        $this->actingAsEmployee()
            ->get("/backoffice/inventory/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('purchaseOrder.business.registered_business_name', 'Harbor Grooming')
                ->where('purchaseOrder.business.address', 'Ayala Avenue')
                ->where('purchaseOrder.business.tin', '999-888-777-00000')
                ->missing('purchaseOrder.business.branch')
                ->where('purchaseOrder.business.logo_url', $business->logoUrl()));

        $this->actingAsEmployee()
            ->get("/backoffice/inventory/purchase-orders/{$order->id}/print")
            ->assertOk()
            ->assertSee('Harbor Grooming', false)
            ->assertSee('Ayala Avenue', false)
            ->assertSee('TIN 999-888-777-00000', false)
            ->assertDontSee('Makati Branch', false)
            ->assertDontSee('Branch Trading', false)
            ->assertDontSee('Branch Street', false);
    }

    public function test_guest_cannot_reset_the_account(): void
    {
        $this->post('/backoffice/business/reset', [
            'confirmation' => 'DELETE ACCOUNT',
        ])->assertRedirect(route('login'));
    }

    public function test_employee_without_business_access_cannot_reset_the_account(): void
    {
        $employee = Employee::factory()->create();
        CatalogItem::factory()->create();

        $this->actingAsEmployee($employee)
            ->post('/backoffice/business/reset', [
                'confirmation' => 'DELETE ACCOUNT',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('catalog_items', 1);
    }

    public function test_reset_requires_the_delete_account_phrase(): void
    {
        CatalogItem::factory()->create();

        $this->actingAsEmployee()
            ->from('/backoffice/business')
            ->post('/backoffice/business/reset', [
                'confirmation' => 'delete account',
            ])
            ->assertRedirect('/backoffice/business')
            ->assertSessionHasErrors([
                'confirmation' => 'Type DELETE ACCOUNT to reset the account.',
            ]);

        $this->assertDatabaseCount('catalog_items', 1);
    }

    public function test_reset_deletes_every_record_and_signs_out(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('catalog-images/kibble.webp', 'image');
        Storage::disk('public')->put('business/logo.png', 'logo');

        CatalogItem::factory()->create();
        PosSale::factory()->create();
        Business::factory()->create(['name' => 'Harbor Grooming']);
        Employee::factory()->create(['name' => 'Ada Groomer']);

        $this->actingAsEmployee()
            ->post('/backoffice/business/reset', [
                'confirmation' => 'DELETE ACCOUNT',
            ])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('catalog_items', 0);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('employees', [
            'employee_number' => '10001',
            'name' => 'Sample Employee',
        ]);
        $this->assertDatabaseMissing('employees', ['name' => 'Ada Groomer']);
        Storage::disk('public')->assertMissing('catalog-images/kibble.webp');
        Storage::disk('public')->assertMissing('business/logo.png');

        $this->get('/backoffice')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.status', 'The account was reset. Sign in with employee number 10001 and PIN 1234.'));
    }
}
