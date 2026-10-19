<?php

namespace App\Models;

use App\Enums\PrinterConnection;
use App\Enums\PrinterRole;
use Database\Factories\PrinterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'name',
    'role',
    'connection_type',
    'paper_width_mm',
    'paper_height_mm',
    'baud_rate',
    'is_default',
    'is_active',
    'usb_vendor_id',
    'usb_product_id',
    'usb_serial_number',
    'device_name',
    'bluetooth_id',
])]
class Printer extends Model
{
    /** @use HasFactory<PrinterFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'connection_type' => 'browser',
        'paper_width_mm' => 80,
        'baud_rate' => 9600,
        'is_default' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (Printer $printer): void {
            if (! $printer->is_default) {
                return;
            }

            static::query()
                ->where('role', $printer->role)
                ->where('branch_id', $printer->branch_id)
                ->when($printer->exists, fn (Builder $query): Builder => $query->whereKeyNot($printer->id))
                ->update(['is_default' => false]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PrinterRole::class,
            'connection_type' => PrinterConnection::class,
            'paper_width_mm' => 'integer',
            'paper_height_mm' => 'integer',
            'baud_rate' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'usb_vendor_id' => 'integer',
            'usb_product_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function defaultFor(PrinterRole $role, ?Branch $branch = null): ?self
    {
        return static::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->when($branch !== null, fn (Builder $query): Builder => $query->where('branch_id', $branch->id))
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{width: int, height: int}
     */
    public static function labelPaper(?Branch $branch = null): array
    {
        $printer = static::defaultFor(PrinterRole::Label, $branch);

        return [
            'width' => max(20, min(100, (int) ($printer?->paper_width_mm ?? 40))),
            'height' => max(15, min(100, (int) ($printer?->paper_height_mm ?? 30))),
        ];
    }

    public function isIdentified(): bool
    {
        return filled($this->bluetooth_id)
            || ($this->usb_vendor_id !== null && $this->usb_product_id !== null);
    }

    public function paperLabel(): string
    {
        if ($this->paper_height_mm === null) {
            return $this->paper_width_mm.'mm';
        }

        return $this->paper_width_mm.'×'.$this->paper_height_mm.'mm';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSettingsPayload(): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'name' => $this->name,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'connection' => $this->connection_type->value,
            'connection_label' => $this->connection_type->label(),
            'paper_width_mm' => $this->paper_width_mm,
            'paper_height_mm' => $this->paper_height_mm,
            'paper_label' => $this->paperLabel(),
            'baud_rate' => $this->baud_rate,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'usb_vendor_id' => $this->usb_vendor_id,
            'usb_product_id' => $this->usb_product_id,
            'usb_serial_number' => $this->usb_serial_number,
            'device_name' => $this->device_name,
            'bluetooth_id' => $this->bluetooth_id,
            'is_identified' => $this->isIdentified(),
            'test_href' => route('pos.printers.test', $this),
        ];
    }
}
