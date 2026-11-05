<?php

namespace App\Actions;

use App\Models\SmsCreditBalance;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        $this->refreshCreditBalance($key);

        return $this->deliveryStatus($response->json());
    }

    /**
     * A failed balance lookup must not undo a text that Semaphore already accepted.
     */
    private function refreshCreditBalance(string $key): void
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->get('https://api.semaphore.co/api/v4/account', [
                    'apikey' => $key,
                ]);
        } catch (Throwable) {
            return;
        }

        if ($response->failed()) {
            return;
        }

        $balance = $response->json('credit_balance');

        if (! is_numeric($balance) || (float) $balance < 0) {
            return;
        }

        SmsCreditBalance::record((int) $balance);
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
