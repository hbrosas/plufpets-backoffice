<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrderItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockAssignmentRequest extends FormRequest
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
            'purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'selling_price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_order_item_id.required' => 'Choose a delivered product.',
            'branch_id.required' => 'Choose a branch.',
            'quantity.required' => 'Enter a quantity.',
            'selling_price.required' => 'Enter a selling price.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['purchase_order_item_id', 'quantity'])) {
                    return;
                }

                $item = PurchaseOrderItem::query()
                    ->with('assignments')
                    ->find($this->integer('purchase_order_item_id'));

                if ($item === null) {
                    return;
                }

                if ($this->integer('quantity') > $item->remainingToAssign()) {
                    $validator->errors()->add(
                        'quantity',
                        'Only '.$item->remainingToAssign().' units remain to assign.',
                    );
                }
            },
        ];
    }
}
