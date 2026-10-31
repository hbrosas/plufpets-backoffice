<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\PayBasis;
use App\Models\CatalogItem;
use App\Models\Employee;
use App\Models\EmployeePayLine;
use App\Models\EmployeeServiceCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_employee_records(): void
    {
        $response = $this->get('/backoffice/staff/records');

        $response->assertRedirect(route('login'));
    }

    public function test_records_list_shows_generated_profiles_in_name_order(): void
    {
        $mina = Employee::factory()->create([
            'name' => 'Mina Cruz',
            'employee_number' => 31002,
        ]);
        $mina->profile()->create([
            'pay_basis' => PayBasis::Month,
            'job_title' => 'Groomer',
            'employment_status' => EmploymentStatus::Regular,
            'mobile' => '09171234567',
        ]);

        $ana = Employee::factory()->create([
            'name' => 'Ana Reyes',
            'employee_number' => 31001,
        ]);
        $ana->profile()->create([
            'pay_basis' => PayBasis::Month,
            'job_title' => 'Bather',
            'employment_status' => EmploymentStatus::Probationary,
        ]);

        Employee::factory()->create([
            'name' => 'No Record',
            'employee_number' => 31003,
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/staff/records');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Records/Index')
            ->has('records', 2)
            ->where('records.0.name', 'Ana Reyes')
            ->where('records.0.employee_number', '31001')
            ->where('records.0.job_title', 'Bather')
            ->where('records.0.employment_status', 'Probationary')
            ->where('records.0.mobile', '')
            ->where('records.1.name', 'Mina Cruz')
            ->where('records.1.employee_number', '31002')
            ->where('records.1.job_title', 'Groomer')
            ->where('records.1.employment_status', 'Regular')
            ->where('records.1.mobile', '09171234567'));
    }

    public function test_guest_is_redirected_from_the_employee_record(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->get("/backoffice/staff/{$employee->id}/record");

        $response->assertRedirect(route('login'));
    }

    public function test_employee_without_staff_access_cannot_open_a_record(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get("/backoffice/staff/{$employee->id}/record");

        $response->assertForbidden();
    }

    public function test_generating_a_record_opens_the_profile(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();

        $response = $this->actingAsEmployee()->post("/backoffice/staff/{$employee->id}/record");

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHas('status', 'Employee record created.');
        $this->assertDatabaseHas('employee_profiles', [
            'employee_id' => $employee->id,
            'pay_basis' => 'month',
        ]);
    }

    public function test_generating_again_keeps_the_same_profile(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $this->actingAsEmployee()->post("/backoffice/staff/{$employee->id}/record");

        $response = $this->actingAsEmployee()->post("/backoffice/staff/{$employee->id}/record");

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHas('status', 'Employee record opened.');
        $this->assertSame(1, $employee->profile()->count());
    }

    public function test_record_page_shows_the_staff_account_and_services(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->profile()->create(['pay_basis' => 'month']);
        CatalogItem::factory()->service()->create(['name' => 'Full groom']);

        $response = $this->actingAsEmployee()->get("/backoffice/staff/{$employee->id}/record");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Record')
            ->where('staff.name', 'Sample Employee')
            ->where('staff.employee_number', '10001')
            ->where('profile.pay_basis', 'month')
            ->where('profile.compensation_lines', [])
            ->where('profile.government_lines', [])
            ->where('services.0.name', 'Full groom')
            ->has('payBases', 2)
            ->has('employmentStatuses', 4));
    }

    public function test_missing_profile_is_not_found(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();

        $this->actingAsEmployee()
            ->get("/backoffice/staff/{$employee->id}/record")
            ->assertNotFound();
    }

    public function test_record_requires_a_pay_basis(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->profile()->create(['pay_basis' => 'month']);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->put("/backoffice/staff/{$employee->id}/record", []);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'pay_basis' => 'Choose per day or per month.',
        ]);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->profile()->create(['pay_basis' => 'month']);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->put("/backoffice/staff/{$employee->id}/record", [
                ...$this->profilePayload(),
                'email' => 'not-an-email',
            ]);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'email' => 'Enter a valid email address.',
        ]);
    }

    public function test_record_saves_the_201_pay_lines_and_service_rates(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->profile()->create(['pay_basis' => 'month']);
        $service = CatalogItem::factory()->service()->create(['name' => 'Full groom']);
        CatalogItem::factory()->create(['name' => 'Kibble']);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->put("/backoffice/staff/{$employee->id}/record", [
                ...$this->profilePayload(),
                'pay_basis' => 'day',
                'overnight_allowance' => '150',
                'compensation_lines' => [
                    ['name' => 'Basic salary', 'amount' => '700'],
                    ['name' => 'COLA', 'amount' => '50'],
                ],
                'benefit_lines' => [
                    ['name' => 'Rice allowance', 'amount' => '40'],
                ],
                'government_lines' => [
                    ['name' => 'SSS', 'employee_contribution' => '1750', 'employer_contribution' => '3500'],
                    ['name' => 'PhilHealth', 'employee_contribution' => '500', 'employer_contribution' => '500'],
                    ['name' => 'Pag-IBIG', 'employee_contribution' => '200', 'employer_contribution' => '200'],
                ],
                'commissions' => [
                    [
                        'catalog_item_id' => $service->id,
                        'assigned_rate' => '10',
                        'assisted_rate' => '5',
                    ],
                ],
            ]);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHas('status', 'Employee record saved.');
        $this->assertDatabaseHas('employee_profiles', [
            'employee_id' => $employee->id,
            'first_name' => 'Mina',
            'middle_name' => 'L',
            'last_name' => 'Cruz',
            'gender' => 'female',
            'civil_status' => 'single',
            'employment_status' => 'regular',
            'pay_basis' => 'day',
            'overnight_allowance' => '150.00',
            'tin' => '123-456-789-000',
        ]);
        $this->assertDatabaseHas('employee_pay_lines', [
            'kind' => 'compensation',
            'name' => 'Basic salary',
            'amount' => '700.00',
        ]);
        $this->assertDatabaseHas('employee_pay_lines', [
            'kind' => 'benefit',
            'name' => 'Rice allowance',
            'amount' => '40.00',
        ]);
        $this->assertDatabaseHas('employee_pay_lines', [
            'kind' => 'government',
            'name' => 'SSS',
            'amount' => null,
            'employee_contribution' => '1750.00',
            'employer_contribution' => '3500.00',
        ]);
        $this->actingAsEmployee()
            ->get("/backoffice/staff/{$employee->id}/record")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.government_lines', [
                    ['name' => 'SSS', 'employee_contribution' => '1750.00', 'employer_contribution' => '3500.00'],
                    ['name' => 'PhilHealth', 'employee_contribution' => '500.00', 'employer_contribution' => '500.00'],
                    ['name' => 'Pag-IBIG', 'employee_contribution' => '200.00', 'employer_contribution' => '200.00'],
                ]));
        $this->assertSame(0, EmployeePayLine::query()->where('kind', 'government')->whereNotNull('amount')->count());
        $this->assertDatabaseHas('employee_service_commissions', [
            'employee_id' => $employee->id,
            'catalog_item_id' => $service->id,
            'assigned_rate' => '10.00',
            'assisted_rate' => '5.00',
        ]);
        $this->assertSame(6, EmployeePayLine::query()->count());
    }

    public function test_commission_for_a_product_is_rejected(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->profile()->create(['pay_basis' => 'month']);
        $product = CatalogItem::factory()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->put("/backoffice/staff/{$employee->id}/record", [
                ...$this->profilePayload(),
                'commissions' => [[
                    'catalog_item_id' => $product->id,
                    'assigned_rate' => '10',
                    'assisted_rate' => '0',
                ]],
            ]);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'commissions.0.catalog_item_id' => 'Choose an active service.',
        ]);
        $this->assertSame(0, EmployeeServiceCommission::query()->count());
    }

    public function test_negative_government_contribution_is_rejected(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->profile()->create(['pay_basis' => 'month']);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->put("/backoffice/staff/{$employee->id}/record", [
                ...$this->profilePayload(),
                'government_lines' => [
                    ['name' => 'SSS', 'employee_contribution' => '-1', 'employer_contribution' => '-5'],
                ],
            ]);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'government_lines.0.employee_contribution' => 'The employee contribution cannot be negative.',
            'government_lines.0.employer_contribution' => 'The employer contribution cannot be negative.',
        ]);
        $this->assertSame(0, EmployeePayLine::query()->count());
    }

    public function test_government_contributions_are_required(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->profile()->create(['pay_basis' => 'month']);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->put("/backoffice/staff/{$employee->id}/record", [
                ...$this->profilePayload(),
                'government_lines' => [
                    ['name' => 'PhilHealth', 'employee_contribution' => '', 'employer_contribution' => ''],
                ],
            ]);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'government_lines.0.employee_contribution' => 'Enter the employee contribution.',
            'government_lines.0.employer_contribution' => 'Enter the employer contribution.',
        ]);
        $this->assertSame(0, EmployeePayLine::query()->count());
    }

    public function test_negative_compensation_is_rejected(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->profile()->create(['pay_basis' => 'month']);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->put("/backoffice/staff/{$employee->id}/record", [
                ...$this->profilePayload(),
                'compensation_lines' => [
                    ['name' => 'Basic salary', 'amount' => '-1'],
                ],
            ]);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'compensation_lines.0.amount' => 'Compensation cannot be negative.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(): array
    {
        return [
            'first_name' => 'Mina',
            'middle_name' => 'L',
            'last_name' => 'Cruz',
            'gender' => 'female',
            'civil_status' => 'single',
            'address' => '12 Rizal Street',
            'mobile' => '09171234567',
            'email' => 'mina@example.com',
            'tin' => '123-456-789-000',
            'sss' => '01-2345678-9',
            'philhealth' => '12-345678901-2',
            'pagibig' => '1234-5678-9012',
            'job_title' => 'Groomer',
            'employment_status' => 'regular',
            'emergency_contact_name' => 'Ana Cruz',
            'emergency_contact_relationship' => 'Mother',
            'emergency_contact_phone' => '09179876543',
            'pay_basis' => 'month',
            'compensation_lines' => [],
            'benefit_lines' => [],
            'government_lines' => [],
            'commissions' => [],
        ];
    }
}
