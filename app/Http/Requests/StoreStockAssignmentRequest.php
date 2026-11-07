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
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Choose a branch.',
            'items.required' => 'Choose a product to assign.',
            'items.min' => 'Choose a product to assign.',
            'items.*.purchase_order_item_id.required' => 'Choose a delivered product.',
            'items.*.purchase_order_item_id.distinct' => 'Choose each product once.',
            'items.*.quantity.required' => 'Enter a quantity.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $rows = $this->input('items', []);
                $items = PurchaseOrderItem::query()
                    ->with('assignments')
                    ->whereIn('id', collect($rows)->pluck('purchase_order_item_id'))
                    ->get()
                    ->keyBy('id');

                foreach ($rows as $index => $row) {
                    $item = $items->get((int) ($row['purchase_order_item_id'] ?? 0));

                    if ($item === null) {
                        continue;
                    }

                    if ((int) $row['quantity'] > $item->remainingToAssign()) {
                        $validator->errors()->add(
                            'items.'.$index.'.quantity',
                            'Only '.$item->remainingToAssign().' units remain to assign.',
                        );
                    }
                }
            },
        ];
    }
}
