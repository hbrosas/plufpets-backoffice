<?php

namespace App\Models;

use Database\Factories\SmsMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'number',
    'body',
    'employee_number',
    'employee_name',
    'status',
    'sent_at',
])]
class SmsMessage extends Model
{
    /** @use HasFactory<SmsMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return array{id: int, number: string, body: string, status: string|null, branch: string|null, sender: string, sent_at: string|null}
     */
    public function toHistoryArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'body' => $this->body,
            'status' => $this->status,
            'branch' => $this->branch?->name,
            'sender' => $this->employee_name.' · #'.$this->employee_number,
            'sent_at' => $this->sent_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
        ];
    }
}
