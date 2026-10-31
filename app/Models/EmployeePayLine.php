<?php

namespace App\Models;

use App\Enums\PayLineKind;
use Database\Factories\EmployeePayLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_profile_id', 'kind', 'name', 'amount', 'employee_contribution', 'employer_contribution', 'sort'])]
class EmployeePayLine extends Model
{
    /** @use HasFactory<EmployeePayLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PayLineKind::class,
            'amount' => 'decimal:2',
            'employee_contribution' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EmployeeProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }
}
