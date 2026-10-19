<?php

namespace App\Http\Requests;

use App\Models\BranchInventory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
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
            'from_branch_id' => ['required', 'integer', 'exists:branches,id', 'different:to_branch_id'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'catalog_item_variant_id' => ['required', 'integer', 'exists:catalog_item_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_branch_id.required' => 'Choose the source branch.',
            'to_branch_id.required' => 'Choose the destination branch.',
            'from_branch_id.different' => 'Choose a different destination branch.',
            'catalog_item_variant_id.required' => 'Choose a product.',
            'quantity.required' => 'Enter a quantity.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from_branch_id', 'catalog_item_variant_id', 'quantity'])) {
                    return;
                }

                $available = BranchInventory::query()
                    ->where('branch_id', $this->integer('from_branch_id'))
                    ->where('catalog_item_variant_id', $this->integer('catalog_item_variant_id'))
                    ->value('quantity') ?? 0;

                if ($this->integer('quantity') > $available) {
                    $validator->errors()->add('quantity', 'Only '.$available.' units are available at that branch.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->filled('notes') ? $this->string('notes')->trim()->toString() : null,
        ]);
    }
}
