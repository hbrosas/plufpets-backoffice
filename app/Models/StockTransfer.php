<?php

namespace App\Models;

use App\Models\Concerns\HidesTestTransactions;
use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['from_branch_id', 'to_branch_id', 'catalog_item_variant_id', 'quantity', 'notes'])]
class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use HasFactory, HidesTestTransactions;

    /**
     * @return list<string>
     */
    public function testBranchColumns(): array
    {
        return ['from_branch_id', 'to_branch_id'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogItemVariant::class, 'catalog_item_variant_id');
    }
}
