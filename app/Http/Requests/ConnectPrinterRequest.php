<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConnectPrinterRequest extends FormRequest
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
        return [
            'usb_vendor_id' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usb_product_id' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usb_serial_number' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'bluetooth_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'usb_vendor_id' => $this->filled('usb_vendor_id') ? $this->input('usb_vendor_id') : null,
            'usb_product_id' => $this->filled('usb_product_id') ? $this->input('usb_product_id') : null,
            'usb_serial_number' => $this->filled('usb_serial_number') ? $this->string('usb_serial_number')->toString() : null,
            'device_name' => $this->filled('device_name') ? $this->string('device_name')->toString() : null,
            'bluetooth_id' => $this->filled('bluetooth_id') ? $this->string('bluetooth_id')->toString() : null,
        ]);
    }
}
