<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\PosCategory;
use App\Support\CsvImportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportCategories
{
    public const MAX_ROWS = 1000;

    /**
     * @var list<string>
     */
    private const REQUIRED_COLUMNS = [
        'name',
        'pos_category',
    ];

    /**
     * @var list<string>
     */
    private const OPTIONAL_COLUMNS = [
        'show_on_shop',
    ];

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>}
     */
    public function handle(UploadedFile $file): array
    {
        $parsed = CsvImportFile::read(
            $file,
            self::REQUIRED_COLUMNS,
            self::OPTIONAL_COLUMNS,
            self::MAX_ROWS,
            'category rows',
        );
        $hasShopColumn = array_key_exists('show_on_shop', $parsed['columns']);
        $existing = [];

        foreach (Category::query()->orderBy('id')->get() as $category) {
            $key = Str::lower($category->name);

            if (! isset($existing[$key])) {
                $existing[$key] = $category;
            }
        }

        $posCategories = [];

        foreach (PosCategory::query()->orderBy('id')->get() as $posCategory) {
            $key = Str::lower($posCategory->name);

            if (! isset($posCategories[$key])) {
                $posCategories[$key] = $posCategory;
            }
        }

        $seen = [];
        $accepted = [];
        $errors = [];

        foreach ($parsed['rows'] as $row) {
            $name = $row['cells']['name'];
            $key = Str::lower($name);
            $error = $this->nameError($name);

            if ($error !== null) {
                $errors[] = ['row' => $row['number'], 'message' => $error];

                continue;
            }

            if (isset($seen[$key])) {
                $errors[] = ['row' => $row['number'], 'message' => 'This name is duplicated in the file.'];

                continue;
            }

            $posName = $row['cells']['pos_category'];

            if ($posName === '') {
                $errors[] = ['row' => $row['number'], 'message' => 'Choose a POS category.'];

                continue;
            }

            $posCategory = $posCategories[Str::lower($posName)] ?? null;

            if (! $posCategory instanceof PosCategory) {
                $errors[] = ['row' => $row['number'], 'message' => 'That POS category does not exist.'];

                continue;
            }

            $seen[$key] = true;
            $accepted[] = [
                'name' => $name,
                'key' => $key,
                'pos_category_id' => $posCategory->id,
                'show_on_shop' => $this->showOnShop($hasShopColumn ? ($row['cells']['show_on_shop'] ?? '') : ''),
            ];
        }

        DB::transaction(function () use ($accepted, $existing): void {
            foreach ($accepted as $row) {
                $category = $existing[$row['key']] ?? null;

                if ($category instanceof Category) {
                    $category->update([
                        'pos_category_id' => $row['pos_category_id'],
                        'show_on_shop' => $row['show_on_shop'],
                    ]);

                    continue;
                }

                Category::query()->create([
                    'name' => $row['name'],
                    'pos_category_id' => $row['pos_category_id'],
                    'show_on_shop' => $row['show_on_shop'],
                ]);
            }
        });

        return [
            'imported' => count($accepted),
            'errors' => $errors,
        ];
    }

    private function nameError(string $name): ?string
    {
        $validator = Validator::make(['name' => $name], [
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Enter a category name.',
            'name.max' => 'Enter a category name that is 255 characters or fewer.',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        return null;
    }

    private function showOnShop(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        return in_array(Str::lower($value), ['1', 'true', 'yes', 'active', 'shown'], true);
    }
}
