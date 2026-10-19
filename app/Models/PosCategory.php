<?php

namespace App\Models;

use Database\Factories\PosCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sort_order', 'is_active'])]
class PosCategory extends Model
{
    /** @use HasFactory<PosCategoryFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CatalogItemVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(CatalogItemVariant::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function shopCategories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('name')->orderBy('id');
    }
}
