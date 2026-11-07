<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InstructionImages
{
    public const Limit = 4;

    /**
     * @return list<UploadedFile>
     */
    public static function uploads(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }

    /**
     * @param  list<string>  $existing
     * @param  list<UploadedFile>  $uploads
     * @param  list<mixed>  $remove
     * @return list<string>
     */
    public static function replace(array $existing, array $uploads, array $remove, string $errorKey = 'images'): array
    {
        $existing = array_values(array_filter($existing, fn (mixed $path): bool => is_string($path) && $path !== ''));
        $remove = array_values(array_filter(
            $remove,
            fn (mixed $path): bool => is_string($path) && in_array($path, $existing, true),
        ));
        $kept = array_values(array_filter(
            $existing,
            fn (string $path): bool => ! in_array($path, $remove, true),
        ));
        $uploads = array_values(array_filter(
            $uploads,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        if (count($kept) + count($uploads) > self::Limit) {
            throw ValidationException::withMessages([
                $errorKey => 'You can add up to 4 photos.',
            ]);
        }

        foreach ($remove as $path) {
            Storage::disk('public')->delete($path);
        }

        foreach ($uploads as $file) {
            $kept[] = $file->store('instruction-images', 'public');
        }

        return $kept;
    }

    /**
     * @param  list<string>|null  $paths
     * @return list<string>
     */
    public static function urls(?array $paths): array
    {
        return collect($paths ?? [])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>|null  $paths
     * @return list<array{path: string, url: string}>
     */
    public static function previews(?array $paths): array
    {
        return collect($paths ?? [])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): array => [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ])
            ->values()
            ->all();
    }

    public static function storeShared(Model $method, mixed $files, mixed $remove): void
    {
        $uploads = self::uploads($files);
        $remove = is_array($remove) ? $remove : [];

        if ($uploads === [] && $remove === []) {
            return;
        }

        $method->update([
            'instruction_images' => self::replace(
                is_array($method->instruction_images) ? $method->instruction_images : [],
                $uploads,
                $remove,
            ),
        ]);
    }

    public static function deleteStored(Model $method): void
    {
        self::forget(is_array($method->getAttribute('instruction_images')) ? $method->getAttribute('instruction_images') : []);

        if (! method_exists($method, 'branchInstructions')) {
            return;
        }

        $method->loadMissing('branchInstructions');

        foreach ($method->branchInstructions as $instruction) {
            self::forget(is_array($instruction->images) ? $instruction->images : []);
        }
    }

    /**
     * @param  list<mixed>  $files
     */
    public static function forget(array $files): void
    {
        foreach ($files as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
