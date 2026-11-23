<?php

namespace App\Support;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Exists;

class TestingMode
{
    public const SCOPE = 'hide_test_transactions';

    public static function includesTestData(): bool
    {
        $request = request();

        if ($request->attributes->has('testing_mode.includes')) {
            return (bool) $request->attributes->get('testing_mode.includes');
        }

        $includes = (bool) Business::query()->value('testing_mode');
        $request->attributes->set('testing_mode.includes', $includes);

        return $includes;
    }

    public static function constrainBranch(Builder $query, string $column): void
    {
        self::constrain($query, 'branches', $column);
    }

    public static function constrainSupplier(Builder $query, string $column): void
    {
        self::constrain($query, 'suppliers', $column);
    }

    public static function existsBranch(bool $activeOnly = false): Exists
    {
        return self::exists('branches', $activeOnly);
    }

    public static function existsSupplier(bool $activeOnly = false): Exists
    {
        return self::exists('suppliers', $activeOnly);
    }

    private static function constrain(Builder $query, string $table, string $column): void
    {
        $query->whereNotExists(function ($subquery) use ($table, $column): void {
            $subquery->selectRaw('1')
                ->from($table)
                ->whereColumn($table.'.id', $column)
                ->where($table.'.for_test_use', true);
        });
    }

    private static function exists(string $table, bool $activeOnly): Exists
    {
        $rule = new Exists($table, 'id');

        if ($activeOnly) {
            $rule->where('is_active', true);
        }

        if (! self::includesTestData()) {
            $rule->where('for_test_use', false);
        }

        return $rule;
    }
}
