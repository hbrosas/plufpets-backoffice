<?php

namespace App\Http\Requests;

use App\Enums\PrinterConnection;
use App\Support\PrinterRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return PrinterRules::for(null, $this->integer('branch_id') ?: null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PrinterRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $connection = $this->string('connection')->toString();
        $isSerial = $connection === PrinterConnection::Serial->value;
        $isBluetooth = $connection === PrinterConnection::Bluetooth->value;
        $keepsDevice = $isSerial || $isBluetooth;

        $this->merge([
            'branch_id' => $this->filled('branch_id') ? $this->integer('branch_id') : null,
            'name' => $this->string('name')->trim()->toString(),
            'role' => $this->string('role')->toString(),
            'connection' => $connection,
            'paper_width_mm' => $this->integer('paper_width_mm'),
            'paper_height_mm' => $this->filled('paper_height_mm') ? $this->integer('paper_height_mm') : null,
            'baud_rate' => $isSerial && $this->filled('baud_rate') ? $this->integer('baud_rate') : 9600,
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active'),
            'usb_vendor_id' => $isSerial && $this->filled('usb_vendor_id') ? $this->integer('usb_vendor_id') : null,
            'usb_product_id' => $isSerial && $this->filled('usb_product_id') ? $this->integer('usb_product_id') : null,
            'usb_serial_number' => $isSerial ? $this->string('usb_serial_number')->trim()->toString() ?: null : null,
            'device_name' => $keepsDevice ? $this->string('device_name')->trim()->toString() ?: null : null,
            'bluetooth_id' => $isBluetooth ? $this->string('bluetooth_id')->trim()->toString() ?: null : null,
        ]);
    }
}
