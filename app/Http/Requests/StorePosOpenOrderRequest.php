<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Support\PosOpenOrderRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosOpenOrderRequest extends FormRequest
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
        $branch = $this->route('branch');
        $branchId = $branch instanceof Branch ? $branch->id : 0;

        return [
            ...PosOpenOrderRules::for(),
            'hotel_stay_id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_stays', 'id')->where(
                    fn ($query) => $query->where('branch_id', $branchId)->whereNull('checked_out_at'),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...PosOpenOrderRules::messages(),
            'hotel_stay_id.exists' => 'Choose a guest who is still checked in.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }
}
