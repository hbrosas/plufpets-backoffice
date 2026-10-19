<?php

namespace App\Http\Requests;

use App\Models\Printer;
use App\Support\PrinterRules;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdatePrinterRequest extends StorePrinterRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $printer = $this->route('printer');

        return PrinterRules::for(
            $printer instanceof Printer ? $printer : null,
            $this->integer('branch_id') ?: null,
        );
    }
}
