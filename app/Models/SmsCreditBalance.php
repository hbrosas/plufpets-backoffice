<?php

namespace App\Models;

use Database\Factories\SmsCreditBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['balance', 'checked_at'])]
class SmsCreditBalance extends Model
{
    /** @use HasFactory<SmsCreditBalanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public static function record(int $balance): void
    {
        $current = static::query()->orderBy('id')->first();

        if ($current instanceof self) {
            $current->update([
                'balance' => $balance,
                'checked_at' => now(),
            ]);

            return;
        }

        static::query()->create([
            'balance' => $balance,
            'checked_at' => now(),
        ]);
    }

    /**
     * @return array{balance: int, label: string}|null
     */
    public static function shared(): ?array
    {
        $current = static::query()->orderByDesc('id')->first();

        if (! $current instanceof self) {
            return null;
        }

        return [
            'balance' => $current->balance,
            'label' => number_format($current->balance),
        ];
    }
}
