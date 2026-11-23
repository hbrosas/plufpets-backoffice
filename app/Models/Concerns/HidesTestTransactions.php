<?php

namespace App\Models\Concerns;

use App\Support\TestingMode;
use Illuminate\Database\Eloquent\Builder;

trait HidesTestTransactions
{
    public static function bootHidesTestTransactions(): void
    {
        static::addGlobalScope(TestingMode::SCOPE, function (Builder $query): void {
            if (TestingMode::includesTestData()) {
                return;
            }

            $model = $query->getModel();
            $table = $model->getTable();

            foreach ($model->testBranchColumns() as $column) {
                TestingMode::constrainBranch($query, $table.'.'.$column);
            }

            foreach ($model->testSupplierColumns() as $column) {
                TestingMode::constrainSupplier($query, $table.'.'.$column);
            }

            if (method_exists($model, 'hideTestRelations')) {
                $model->hideTestRelations($query);
            }
        });
    }

    /**
     * @return list<string>
     */
    public function testBranchColumns(): array
    {
        return ['branch_id'];
    }

    /**
     * @return list<string>
     */
    public function testSupplierColumns(): array
    {
        return [];
    }
}
