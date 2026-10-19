<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use Database\Factories\CatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type',
    'name',
    'category_id',
    'brand_id',
    'description',
    'is_active',
    'has_branch_pricing',
])]
class CatalogItem extends Model
{
    /** @use HasFactory<CatalogItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'has_branch_pricing' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'is_active' => 'boolean',
            'has_branch_pricing' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(CatalogItemVariant::class)->orderBy('name')->orderBy('id');
    }

    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(Modifier::class)
            ->withTimestamps()
            ->orderBy('modifiers.name')
            ->orderBy('modifiers.id');
    }

    public function modifierOptionPrices(): HasMany
    {
        return $this->hasMany(CatalogItemModifierOptionPrice::class)
            ->orderBy('option')
            ->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        $variant = $this->relationLoaded('variants')
            ? $this->variants->first(fn (CatalogItemVariant $variant): bool => filled($variant->image_path))
            : $this->variants()->whereNotNull('image_path')->first();

        return $variant?->imageUrl();
    }
}
