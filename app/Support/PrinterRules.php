<?php

namespace App\Support;

use App\Enums\PrinterConnection;
use App\Enums\PrinterRole;
use App\Models\Printer;
use Illuminate\Validation\Rule;

class PrinterRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?Printer $printer = null, ?int $branchId = null): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('printers', 'name')
                    ->where('branch_id', $branchId)
                    ->ignore($printer?->id),
            ],
            'role' => ['required', Rule::enum(PrinterRole::class)],
            'connection' => ['required', Rule::enum(PrinterConnection::class)],
            'paper_width_mm' => ['required', 'integer', 'min:20', 'max:120'],
            'paper_height_mm' => ['nullable', 'integer', 'min:15', 'max:300'],
            'baud_rate' => ['nullable', 'integer', Rule::in([9600, 19200, 38400, 57600, 115200])],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'usb_vendor_id' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usb_product_id' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usb_serial_number' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'bluetooth_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'branch_id.required' => 'Choose a branch for this printer.',
            'branch_id.exists' => 'Choose a branch for this printer.',
            'name.required' => 'Enter a printer name.',
            'name.unique' => 'That printer name already exists at this branch.',
            'role.required' => 'Choose receipts or labels.',
            'connection.required' => 'Choose how this printer connects.',
            'paper_width_mm.required' => 'Enter the paper width in millimeters.',
        ];
    }
}
