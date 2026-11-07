<?php

namespace App\Actions;

use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BulkUpdateCatalogItems
{
    /**
     * @param  list<int>  $ids
     * @param  array{category_name?: string|null, brand_name?: string|null, is_active?: bool, allows_preorder?: bool}  $attributes
     */
    public function handle(array $ids, string $action, array $attributes): void
    {
        if ($action === 'delete') {
            $this->delete($ids);

            return;
        }

        $changes = match ($action) {
            'category' => [
                'category_id' => Category::query()->where('name', $attributes['category_name'])->firstOrFail()->id,
            ],
            'brand' => [
                'brand_id' => filled($attributes['brand_name'] ?? null)
                    ? Brand::query()->where('name', $attributes['brand_name'])->firstOrFail()->id
                    : null,
            ],
            'status' => [
                'is_active' => (bool) $attributes['is_active'],
            ],
            'preorder' => [
                'allows_preorder' => (bool) $attributes['allows_preorder'],
            ],
            default => throw ValidationException::withMessages([
                'action' => 'Choose an action.',
            ]),
        };

        CatalogItem::query()->whereIn('id', $ids)->update($changes);
    }

    /**
     * @param  list<int>  $ids
     */
    private function delete(array $ids): void
    {
        $items = CatalogItem::query()->with('variants')->whereIn('id', $ids)->get();
        $imagePaths = $items
            ->flatMap(fn (CatalogItem $item) => $item->variants->pluck('image_path'))
            ->filter()
            ->values()
            ->all();

        try {
            DB::transaction(function () use ($items): void {
                $items->each->delete();
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'ids' => 'A selected product is used on a purchase order or stock record and cannot be deleted.',
            ]);
        }

        foreach ($imagePaths as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
