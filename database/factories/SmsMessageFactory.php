<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\SmsMessage;
use App\Support\SmsDisclaimer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsMessage>
 */
class SmsMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'number' => '09171234567',
            'body' => SmsDisclaimer::append('Pickup is ready.'),
            'employee_number' => '10001',
            'employee_name' => 'Sample Employee',
            'status' => 'Queued',
            'sent_at' => now(),
        ];
    }
}
