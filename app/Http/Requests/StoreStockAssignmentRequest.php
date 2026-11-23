<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrderItem;
use App\Support\TestingMode;
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
            'branch_id' => ['required', 'integer', TestingMode::existsBranch()],
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
                    ->withSum(['assignments as assigned_quantity' => fn ($query) => $query->withoutGlobalScope(TestingMode::SCOPE)], 'quantity')
                    ->whereIn('id', collect($rows)->pluck('purchase_order_item_id'))
                    ->get()
                    ->keyBy('id');

                foreach ($rows as $index => $row) {
                    $item = $items->get((int) ($row['purchase_order_item_id'] ?? 0));

                    if ($item === null) {
                        $validator->errors()->add('items.'.$index.'.purchase_order_item_id', 'Choose a delivered product.');

                        continue;
                    }

                    $remaining = max($item->received_quantity - (int) $item->assigned_quantity, 0);

                    if ((int) $row['quantity'] > $remaining) {
                        $validator->errors()->add(
                            'items.'.$index.'.quantity',
                            'Only '.$remaining.' units remain to assign.',
                        );
                    }
                }
            },
        ];
    }
}
