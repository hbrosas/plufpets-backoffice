<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SendSemaphoreSms
{
    public function handle(string $number, string $message): string
    {
        $key = config('services.semaphore.key');

        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages([
                'status' => 'SMS is not configured.',
            ]);
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://api.semaphore.co/api/v4/messages', [
                    'apikey' => $key,
                    'number' => $number,
                    'message' => $message,
                    'sendername' => config('services.semaphore.sender'),
                ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'status' => 'Could not reach Semaphore. Try again.',
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'status' => $this->errorMessage($response->json()),
            ]);
        }

        return $this->deliveryStatus($response->json());
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $payload
     */
    private function errorMessage(mixed $payload): string
    {
        if (is_array($payload) && isset($payload['message']) && is_string($payload['message']) && $payload['message'] !== '') {
            return $payload['message'];
        }

        return 'Could not send the text.';
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $payload
     */
    private function deliveryStatus(mixed $payload): string
    {
        $first = is_array($payload) ? ($payload[0] ?? $payload) : null;

        if (is_array($first) && isset($first['status']) && is_string($first['status']) && $first['status'] !== '') {
            return $first['status'];
        }

        return 'Sent';
    }
}
