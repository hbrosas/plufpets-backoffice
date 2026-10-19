<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SmsTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSmsTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_templates(): void
    {
        $response = $this->getJson('/backoffice/pos/sms/templates');

        $response->assertUnauthorized();
    }

    public function test_employee_without_register_access_cannot_manage_templates(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->getJson('/backoffice/pos/sms/templates');

        $response->assertForbidden();
    }

    public function test_template_can_be_created_and_listed(): void
    {
        $response = $this->actingAsEmployee()->postJson('/backoffice/pos/sms/templates', [
            'name' => 'Pickup ready',
            'body' => 'Your order is ready for pickup.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('template.name', 'Pickup ready');
        $this->assertDatabaseHas('sms_templates', [
            'name' => 'Pickup ready',
            'body' => 'Your order is ready for pickup.',
        ]);

        $this->actingAsEmployee()
            ->getJson('/backoffice/pos/sms/templates')
            ->assertOk()
            ->assertJsonPath('templates.0.name', 'Pickup ready');
    }

    public function test_template_can_be_updated_and_deleted(): void
    {
        $template = SmsTemplate::factory()->create(['name' => 'Old']);

        $this->actingAsEmployee()
            ->putJson("/backoffice/pos/sms/templates/{$template->id}", [
                'name' => 'Grooming done',
                'body' => 'Your pet is ready.',
            ])
            ->assertOk()
            ->assertJsonPath('template.name', 'Grooming done');

        $this->actingAsEmployee()
            ->deleteJson("/backoffice/pos/sms/templates/{$template->id}")
            ->assertOk();

        $this->assertDatabaseMissing('sms_templates', ['id' => $template->id]);
    }
}
