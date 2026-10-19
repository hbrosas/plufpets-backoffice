<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use App\Support\PurchaseOrderRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
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
        return PurchaseOrderRules::receive();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Enter the quantities that arrived.',
            'items.min' => 'Enter the quantities that arrived.',
            'items.*.quantity.required' => 'Enter a received quantity.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delivery_receipt_number' => $this->filled('delivery_receipt_number')
                ? $this->string('delivery_receipt_number')->trim()->toString()
                : null,
            'sales_invoice_number' => $this->filled('sales_invoice_number')
                ? $this->string('sales_invoice_number')->trim()->toString()
                : null,
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var PurchaseOrder $purchaseOrder */
                $purchaseOrder = $this->route('purchase_order');
                $purchaseOrder->load('items');

                $items = $purchaseOrder->items->keyBy('id');

                foreach ($this->input('items', []) as $index => $row) {
                    $item = $items->get((int) ($row['id'] ?? 0));

                    if ($item === null) {
                        $validator->errors()->add('items.'.$index.'.id', 'That product is not on this purchase order.');

                        continue;
                    }

                    if ((int) $row['quantity'] > $item->remainingToReceive()) {
                        $validator->errors()->add(
                            'items.'.$index.'.quantity',
                            'Only '.$item->remainingToReceive().' units remain to receive.',
                        );
                    }
                }
            },
        ];
    }
}
