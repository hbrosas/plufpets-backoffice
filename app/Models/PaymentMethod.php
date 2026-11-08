<?php

namespace App\Models;

use App\Support\InstructionImages;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'instructions', 'instructions_for_all_branches', 'instruction_images', 'fee', 'is_cash', 'asks_for_change', 'is_active'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'instructions_for_all_branches' => true,
        'is_cash' => false,
        'asks_for_change' => false,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'instructions_for_all_branches' => 'boolean',
            'instruction_images' => 'array',
            'fee' => 'decimal:2',
            'is_cash' => 'boolean',
            'asks_for_change' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PaymentMethodInstruction, $this>
     */
    public function branchInstructions(): HasMany
    {
        return $this->hasMany(PaymentMethodInstruction::class);
    }

    public function instructionFor(?int $branchId): ?string
    {
        if ($this->instructions_for_all_branches || $branchId === null) {
            return $this->present($this->instructions);
        }

        if (! $this->relationLoaded('branchInstructions')) {
            $this->load('branchInstructions');
        }

        return $this->present($this->branchInstructions->firstWhere('branch_id', $branchId)?->instructions);
    }

    /**
     * @return list<string>
     */
    public function instructionImageUrls(?int $branchId): array
    {
        if ($this->instructions_for_all_branches || $branchId === null) {
            return InstructionImages::urls($this->instruction_images);
        }

        if (! $this->relationLoaded('branchInstructions')) {
            $this->load('branchInstructions');
        }

        return InstructionImages::urls($this->branchInstructions->firstWhere('branch_id', $branchId)?->images);
    }

    private function present(mixed $instructions): ?string
    {
        return is_string($instructions) && $instructions !== '' ? $instructions : null;
    }
}
