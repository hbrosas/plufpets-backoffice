<?php

namespace App\Models;

use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\PayBasis;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'first_name',
    'middle_name',
    'last_name',
    'gender',
    'civil_status',
    'address',
    'mobile',
    'email',
    'tin',
    'sss',
    'philhealth',
    'pagibig',
    'job_title',
    'employment_status',
    'emergency_contact_name',
    'emergency_contact_relationship',
    'emergency_contact_phone',
    'pay_basis',
    'overnight_allowance',
])]
class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'pay_basis' => 'month',
        'overnight_allowance' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'civil_status' => CivilStatus::class,
            'employment_status' => EmploymentStatus::class,
            'pay_basis' => PayBasis::class,
            'overnight_allowance' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<EmployeePayLine, $this>
     */
    public function payLines(): HasMany
    {
        return $this->hasMany(EmployeePayLine::class)->orderBy('sort')->orderBy('id');
    }
}
