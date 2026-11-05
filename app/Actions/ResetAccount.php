<?php

namespace App\Actions;

use App\Support\AccessSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ResetAccount
{
    public function handle(): void
    {
        $tables = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $table): bool => in_array($table, [
                'migrations',
                'sqlite_sequence',
                'spatial_ref_sys',
                'geometry_columns',
                'geography_columns',
                'raster_columns',
                'raster_overviews',
            ], true))
            ->values();

        if (DB::getDriverName() === 'pgsql') {
            $list = $tables
                ->map(fn (string $table): string => '"'.str_replace('"', '""', $table).'"')
                ->implode(', ');

            DB::statement('TRUNCATE '.$list.' RESTART IDENTITY CASCADE');
        } else {
            foreach ($this->tablesInDeleteOrder($tables->all()) as $table) {
                DB::table($table)->delete();
            }
        }

        foreach (['catalog-images', 'business', 'brand-logos'] as $directory) {
            Storage::disk('public')->deleteDirectory($directory);
        }

        AccessSetup::install();
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function tablesInDeleteOrder(array $tables): array
    {
        $references = [];

        foreach ($tables as $table) {
            $references[$table] = [];

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $referenced = $foreignKey['foreign_table'];

                if ($referenced !== $table && in_array($referenced, $tables, true)) {
                    $references[$table][] = $referenced;
                }
            }
        }

        $remaining = $tables;
        $ordered = [];

        while ($remaining !== []) {
            $ready = array_values(array_filter(
                $remaining,
                function (string $table) use ($remaining, $references): bool {
                    foreach ($remaining as $other) {
                        if ($other !== $table && in_array($table, $references[$other], true)) {
                            return false;
                        }
                    }

                    return true;
                },
            ));

            if ($ready === []) {
                return [...$ordered, ...$remaining];
            }

            array_push($ordered, ...$ready);
            $remaining = array_values(array_diff($remaining, $ready));
        }

        return $ordered;
    }
}
