<?php

namespace App\Http\Requests;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
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
            'date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = Holiday::query()
                        ->whereDate('date', Carbon::parse((string) $value)->toDateString())
                        ->exists();

                    if ($exists) {
                        $fail('That date already has a holiday.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(HolidayType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Enter the holiday date.',
            'date.date' => 'Enter the holiday date.',
            'name.required' => 'Enter the holiday name.',
            'type.required' => 'Choose regular or special.',
        ];
    }
}
