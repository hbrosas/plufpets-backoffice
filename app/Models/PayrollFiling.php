<?php

namespace App\Models;

use App\Enums\PayrollFilingStatus;
use App\Enums\PayrollFilingType;
use App\Support\Peso;
use Database\Factories\PayrollFilingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'type',
    'status',
    'starts_on',
    'ends_on',
    'hours',
    'nights',
    'amount',
    'reason',
    'deducted_payroll_id',
    'reviewed_by_name',
    'reviewed_at',
    'review_note',
])]
class PayrollFiling extends Model
{
    /** @use HasFactory<PayrollFilingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayrollFilingType::class,
            'status' => PayrollFilingStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'hours' => 'decimal:2',
            'nights' => 'integer',
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<EmployeePayroll, $this>
     */
    public function deductedPayroll(): BelongsTo
    {
        return $this->belongsTo(EmployeePayroll::class, 'deducted_payroll_id');
    }

    public function detail(): string
    {
        $start = $this->starts_on?->format('M j, Y') ?? '';

        return match ($this->type) {
            PayrollFilingType::Leave => $start.' – '.$this->ends_on?->format('M j, Y'),
            PayrollFilingType::Overtime => $start.' · '.$this->quantityLabel((float) $this->hours).' hours',
            PayrollFilingType::Overnight => $start.' · '.$this->nights.' '.((int) $this->nights === 1 ? 'night' : 'nights'),
            PayrollFilingType::CashAdvance => $start.' · '.Peso::format($this->amount),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toList(): array
    {
        return [
            'id' => $this->id,
            'employee' => $this->employee?->name,
            'employee_number' => $this->employee?->employee_number,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'detail' => $this->detail(),
            'reason' => $this->reason,
            'review_note' => $this->review_note,
            'reviewed_by_name' => $this->reviewed_by_name,
        ];
    }

    private function quantityLabel(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
