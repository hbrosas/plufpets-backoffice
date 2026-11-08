<?php

namespace App\Models;

use App\Support\InstructionImages;
use Database\Factories\DeliveryMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'fee', 'notes', 'instructions_for_all_branches', 'instruction_images', 'asks_for_address', 'uses_delivery_schedule', 'is_active'])]
class DeliveryMethod extends Model
{
    /** @use HasFactory<DeliveryMethodFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'instructions_for_all_branches' => true,
        'asks_for_address' => false,
        'uses_delivery_schedule' => false,
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
            'asks_for_address' => 'boolean',
            'uses_delivery_schedule' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DeliveryMethodInstruction, $this>
     */
    public function branchInstructions(): HasMany
    {
        return $this->hasMany(DeliveryMethodInstruction::class);
    }

    public function instructionFor(?int $branchId): ?string
    {
        if ($this->instructions_for_all_branches || $branchId === null) {
            return $this->present($this->notes);
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
