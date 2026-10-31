<?php

namespace App\Models;

use Database\Factories\EmployeePayrollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'employee_name',
    'employee_number',
    'period_start',
    'period_end',
    'net',
    'snapshot',
])]
class EmployeePayroll extends Model
{
    /** @use HasFactory<EmployeePayrollFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'net' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
