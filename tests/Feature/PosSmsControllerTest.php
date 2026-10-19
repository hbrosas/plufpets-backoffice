<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\SmsMessage;
use App\Support\SmsDisclaimer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PosSmsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_send_a_text(): void
    {
        Http::preventStrayRequests();
        $branch = Branch::factory()->create();

        $response = $this->postJson("/backoffice/pos/register/{$branch->id}/sms", [
            'number' => '09171234567',
            'message' => 'Pickup is ready.',
        ]);

        $response->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_employee_without_register_access_cannot_send_a_text(): void
    {
        Http::preventStrayRequests();
        $branch = Branch::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->postJson("/backoffice/pos/register/{$branch->id}/sms", [
            'number' => '09171234567',
            'message' => 'Pickup is ready.',
        ]);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_empty_payload_is_rejected(): void
    {
        Http::preventStrayRequests();
        $this->configureSemaphore();
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()->postJson("/backoffice/pos/register/{$branch->id}/sms", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'number' => 'Enter a mobile number.',
            'message' => 'Enter a message.',
        ]);
        Http::assertNothingSent();
    }

    public function test_text_is_sent_in_the_background_with_disclaimer_and_history(): void
    {
        Http::preventStrayRequests();
        $this->configureSemaphore();
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                ['message_id' => 1, 'status' => 'Queued'],
            ]),
        ]);

        $response = $this->actingAsEmployee()->postJson("/backoffice/pos/register/{$branch->id}/sms", [
            'number' => '0917 123 4567',
            'message' => 'Pickup is ready.',
        ]);

        $body = "Pickup is ready.\n\nDo not reply to this message.";

        $response->assertOk();
        $response->assertJsonPath('status', 'Text sent.');
        Http::assertSent(function ($request) use ($body): bool {
            return $request->url() === 'https://api.semaphore.co/api/v4/messages'
                && $request['apikey'] === 'test-key'
                && $request['number'] === '09171234567'
                && $request['message'] === $body
                && $request['sendername'] === 'PLUFPETS';
        });
        $this->assertDatabaseHas('sms_messages', [
            'branch_id' => $branch->id,
            'number' => '09171234567',
            'body' => $body,
            'employee_number' => '10001',
            'employee_name' => 'Sample Employee',
        ]);
    }

    public function test_guest_cannot_view_history(): void
    {
        $response = $this->getJson('/backoffice/pos/sms/history');

        $response->assertUnauthorized();
    }

    public function test_history_lists_branch_and_sender(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        SmsMessage::factory()->create([
            'branch_id' => $branch->id,
            'number' => '09170000000',
            'body' => SmsDisclaimer::append('Your groom is done.'),
            'employee_number' => '10001',
            'employee_name' => 'Hazel Brosas',
        ]);

        $response = $this->actingAsEmployee()->getJson('/backoffice/pos/sms/history');

        $response->assertOk();
        $response->assertJsonPath('messages.0.number', '09170000000');
        $response->assertJsonPath('messages.0.branch', 'Greenheights');
        $response->assertJsonPath('messages.0.sender', 'Hazel Brosas · #10001');
    }

    public function test_semaphore_error_is_shown(): void
    {
        Http::preventStrayRequests();
        $this->configureSemaphore();
        $branch = Branch::factory()->create();
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                'message' => 'Invalid API key.',
            ], 401),
        ]);

        $response = $this->actingAsEmployee()->postJson("/backoffice/pos/register/{$branch->id}/sms", [
            'number' => '09171234567',
            'message' => 'Pickup is ready.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'status' => 'Invalid API key.',
        ]);
        $this->assertDatabaseCount('sms_messages', 0);
    }

    private function configureSemaphore(): void
    {
        config([
            'services.semaphore.key' => 'test-key',
            'services.semaphore.sender' => 'PLUFPETS',
        ]);
    }
}
