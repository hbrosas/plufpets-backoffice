<?php

namespace App\Actions;

use App\Models\Brand;
use App\Support\CsvImportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportBrands
{
    public const MAX_ROWS = 1000;

    /**
     * @var list<string>
     */
    private const REQUIRED_COLUMNS = [
        'name',
    ];

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>}
     */
    public function handle(UploadedFile $file): array
    {
        $parsed = CsvImportFile::read($file, self::REQUIRED_COLUMNS, [], self::MAX_ROWS, 'brand rows');
        $existing = Brand::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Brand $brand): array => [Str::lower($brand->name) => true])
            ->all();
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

            $seen[$key] = true;

            if (isset($existing[$key])) {
                $errors[] = ['row' => $row['number'], 'message' => 'That brand already exists.'];

                continue;
            }

            $accepted[] = $name;
        }

        DB::transaction(function () use ($accepted): void {
            foreach ($accepted as $name) {
                Brand::query()->create(['name' => $name]);
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
            'name.required' => 'Enter a brand name.',
            'name.max' => 'Enter a brand name that is 255 characters or fewer.',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        return null;
    }
}
