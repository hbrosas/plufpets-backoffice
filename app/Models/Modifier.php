<?php

namespace App\Models;

use Database\Factories\ModifierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'options', 'is_active'])]
class Modifier extends Model
{
    /** @use HasFactory<ModifierFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'options' => '[]',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function catalogItems(): BelongsToMany
    {
        return $this->belongsToMany(CatalogItem::class)->withTimestamps();
    }
}
