<?php

namespace Tests\Unit\Actions;

use App\Actions\SendSemaphoreSms;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SendSemaphoreSmsTest extends TestCase
{
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
