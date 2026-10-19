<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosSmsHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_sms_history(): void
    {
        $this->get('/backoffice/pos/sms-history')->assertRedirect(route('login'));
    }

    public function test_employee_without_register_access_cannot_open_sms_history(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get('/backoffice/pos/sms-history');

        $response->assertForbidden();
    }

    public function test_register_access_alone_cannot_open_sms_history(): void
    {
        $role = Role::factory()->withPermissions(['pos.access'])->create();
        $employee = Employee::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAsEmployee($employee)->get('/backoffice/pos/sms-history');

        $response->assertForbidden();
    }

    public function test_authenticated_employee_sees_all_sms_history(): void
    {
        SmsMessage::factory()->create([
            'number' => '09170000000',
            'employee_name' => 'Hazel Brosas',
            'status' => 'Sent',
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/pos/sms-history');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pos/SmsHistory/Index')
            ->where('messages.data.0.number', '09170000000')
            ->where('messages.data.0.sender', 'Hazel Brosas · #10001')
            ->where('messages.data.0.status', 'Sent'));
    }
}
