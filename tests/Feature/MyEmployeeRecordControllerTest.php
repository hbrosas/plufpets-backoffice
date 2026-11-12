<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\PayBasis;
use App\Enums\PayLineKind;
use App\Models\Employee;
use App\Models\EmployeePayLine;
use App\Models\EmployeeProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyEmployeeRecordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_my_employee_201(): void
    {
        $this->get('/backoffice/me/employee-201')->assertRedirect(route('login'));
        $this->put('/backoffice/me/employee-201')->assertRedirect(route('login'));
    }

    public function test_session_without_an_employee_cannot_update_a_201_file(): void
    {
        $this->actingAsEmployee()
            ->get('/backoffice/me/employee-201')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Employee201')
                ->where('account', false)
                ->where('profile', null));

        $this->actingAsEmployee()
            ->put('/backoffice/me/employee-201', [
                'first_name' => 'Ada',
            ])
            ->assertForbidden();
    }

    public function test_signed_in_employee_without_a_record_is_marked_for_the_menu(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->get('/backoffice/me/employee-201')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('employee.has_record', false));
    }

    public function test_signed_in_employee_with_a_record_is_marked_for_the_menu(): void
    {
        $employee = Employee::factory()->create();
        EmployeeProfile::factory()->create(['employee_id' => $employee->id]);

        $this->actingAsEmployee($employee)
            ->get('/backoffice/me/employee-201')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('employee.has_record', true));
    }

    public function test_employee_sees_only_their_201_file(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'Ada Groomer',
            'employee_number' => '30001',
        ]);
        EmployeeProfile::factory()->create([
            'employee_id' => $employee->id,
            'first_name' => 'Ada',
            'mobile' => '09170000001',
            'job_title' => 'Groomer',
        ]);
        $other = Employee::factory()->create(['name' => 'Ben Cashier']);
        EmployeeProfile::factory()->create([
            'employee_id' => $other->id,
            'first_name' => 'Ben',
            'mobile' => '09170000002',
        ]);

        $this->actingAsEmployee($employee)
            ->get('/backoffice/me/employee-201')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Employee201')
                ->where('account', true)
                ->where('staff.name', 'Ada Groomer')
                ->where('staff.employee_number', '30001')
                ->where('profile.first_name', 'Ada')
                ->where('profile.mobile', '09170000001')
                ->where('profile.job_title', 'Groomer'));
    }

    public function test_employee_updates_their_201_file_without_changing_pay(): void
    {
        $employee = Employee::factory()->create();
        $profile = EmployeeProfile::factory()->create([
            'employee_id' => $employee->id,
            'pay_basis' => PayBasis::Month,
            'overnight_allowance' => '150.00',
            'job_title' => 'Groomer',
            'employment_status' => EmploymentStatus::Probationary,
        ]);
        EmployeePayLine::factory()->create([
            'employee_profile_id' => $profile->id,
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '20000',
        ]);

        $this->actingAsEmployee($employee)
            ->from('/backoffice/me/employee-201')
            ->put('/backoffice/me/employee-201', [
                'first_name' => 'Ada',
                'last_name' => 'Groomer',
                'gender' => Gender::Female->value,
                'address' => '12 Mabini Street',
                'mobile' => '09171234567',
                'email' => 'ada@example.com',
                'job_title' => 'Senior groomer',
                'employment_status' => EmploymentStatus::Regular->value,
                'emergency_contact_name' => 'Nora Groomer',
                'emergency_contact_relationship' => 'Mother',
                'emergency_contact_phone' => '09180000000',
            ])
            ->assertRedirect('/backoffice/me/employee-201')
            ->assertSessionHas('status', 'Employee 201 saved.');

        $profile->refresh();
        $this->assertSame('Ada', $profile->first_name);
        $this->assertSame('Groomer', $profile->last_name);
        $this->assertSame(Gender::Female, $profile->gender);
        $this->assertSame('12 Mabini Street', $profile->address);
        $this->assertSame('09171234567', $profile->mobile);
        $this->assertSame('ada@example.com', $profile->email);
        $this->assertSame('Groomer', $profile->job_title);
        $this->assertSame(EmploymentStatus::Probationary, $profile->employment_status);
        $this->assertSame('Nora Groomer', $profile->emergency_contact_name);
        $this->assertSame(PayBasis::Month, $profile->pay_basis);
        $this->assertSame('150.00', $profile->overnight_allowance);
        $this->assertDatabaseHas('employee_pay_lines', [
            'employee_profile_id' => $profile->id,
            'name' => 'Basic salary',
            'amount' => '20000.00',
        ]);
    }

    public function test_employee_201_rejects_an_invalid_email(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->from('/backoffice/me/employee-201')
            ->put('/backoffice/me/employee-201', [
                'email' => 'not-an-email',
            ])
            ->assertRedirect('/backoffice/me/employee-201')
            ->assertSessionHasErrors(['email' => 'Enter a valid email address.']);
    }
}
