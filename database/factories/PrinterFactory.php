<?php

namespace Database\Factories;

use App\Enums\PrinterConnection;
use App\Enums\PrinterRole;
use App\Models\Branch;
use App\Models\Printer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Printer>
 */
class PrinterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->unique()->words(2, true),
            'role' => PrinterRole::Receipt,
            'connection_type' => PrinterConnection::Browser,
            'paper_width_mm' => 80,
            'paper_height_mm' => null,
            'baud_rate' => 9600,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function label(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => PrinterRole::Label,
            'connection_type' => PrinterConnection::Browser,
            'paper_width_mm' => 40,
            'paper_height_mm' => 30,
        ]);
    }

    public function serial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'connection_type' => PrinterConnection::Serial,
        ]);
    }

    public function bluetooth(): static
    {
        return $this->state(fn (array $attributes): array => [
            'connection_type' => PrinterConnection::Bluetooth,
        ]);
    }
}
