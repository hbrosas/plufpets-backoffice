<?php

namespace Tests\Unit\Actions;

use App\Actions\SendSemaphoreSms;
use App\Models\SmsCreditBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SendSemaphoreSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_api_key_is_rejected(): void
    {
        Http::preventStrayRequests();
        config(['services.semaphore.key' => null]);

        try {
            (new SendSemaphoreSms)->handle('09171234567', 'Pickup is ready.');
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(['SMS is not configured.'], $exception->errors()['status']);
        }

        Http::assertNothingSent();
    }

    public function test_successful_send_returns_the_provider_status(): void
    {
        Http::preventStrayRequests();
        config([
            'services.semaphore.key' => 'test-key',
            'services.semaphore.sender' => 'PLUFPETS',
        ]);
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                ['message_id' => 1, 'status' => 'Queued'],
            ]),
            'https://api.semaphore.co/api/v4/account*' => Http::response([
                'credit_balance' => 1250,
            ]),
        ]);

        $status = (new SendSemaphoreSms)->handle('09171234567', 'Pickup is ready.');

        $this->assertSame('Queued', $status);
        $this->assertSame(1250, SmsCreditBalance::query()->value('balance'));
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.semaphore.co/api/v4/account?apikey=test-key'
                && $request->method() === 'GET';
        });
    }

    public function test_a_failed_balance_lookup_keeps_the_sent_text(): void
    {
        Http::preventStrayRequests();
        config([
            'services.semaphore.key' => 'test-key',
            'services.semaphore.sender' => 'PLUFPETS',
        ]);
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                ['message_id' => 1, 'status' => 'Queued'],
            ]),
            'https://api.semaphore.co/api/v4/account*' => Http::failedConnection(),
        ]);

        $status = (new SendSemaphoreSms)->handle('09171234567', 'Pickup is ready.');

        $this->assertSame('Queued', $status);
        $this->assertDatabaseCount('sms_credit_balances', 0);
    }

    public function test_connection_failure_is_rejected(): void
    {
        Http::preventStrayRequests();
        config([
            'services.semaphore.key' => 'test-key',
            'services.semaphore.sender' => 'PLUFPETS',
        ]);
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::failedConnection(),
        ]);

        try {
            (new SendSemaphoreSms)->handle('09171234567', 'Pickup is ready.');
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Could not reach Semaphore. Try again.'], $exception->errors()['status']);
        }
    }
}
