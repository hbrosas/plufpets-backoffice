<?php

namespace App\Support;

use App\Enums\EmployeeHomeScreen;
use App\Models\Role;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(bool $pinRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'start_date' => ['required', 'date', 'after_or_equal:date_of_birth'],
            'pin' => [$pinRequired ? 'required' : 'nullable', 'string', 'digits_between:4,8'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['required', 'boolean'],
            'home_screen' => ['required', Rule::enum(EmployeeHomeScreen::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a name.',
            'date_of_birth.required' => 'Enter a date of birth.',
            'date_of_birth.date' => 'Enter a valid date of birth.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'date_of_birth.after' => 'Enter a valid date of birth.',
            'start_date.required' => 'Enter a start date.',
            'start_date.date' => 'Enter a valid start date.',
            'start_date.after_or_equal' => 'Start date must be on or after the date of birth.',
            'pin.required' => 'Enter a PIN code.',
            'pin.digits_between' => 'The PIN code must be 4 to 8 digits.',
            'role_id.required' => 'Choose a role.',
            'role_id.exists' => 'Choose a role.',
            'home_screen.required' => 'Choose where sign-in opens.',
            'home_screen.enum' => 'Choose POS or the dashboard.',
        ];
    }

    public static function guardHomeScreen(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $homeScreen = EmployeeHomeScreen::tryFrom((string) ($validator->getData()['home_screen'] ?? ''));

        if (! $homeScreen instanceof EmployeeHomeScreen) {
            return;
        }

        $permission = $homeScreen === EmployeeHomeScreen::Pos ? 'pos.access' : 'dashboard.view';

        $granted = Role::query()
            ->whereKey($validator->getData()['role_id'] ?? null)
            ->whereHas('permissions', function ($query) use ($permission): void {
                $query->where('key', $permission);
            })
            ->exists();

        if ($granted) {
            return;
        }

        $validator->errors()->add(
            'home_screen',
            $homeScreen === EmployeeHomeScreen::Pos
                ? 'POS first needs a role that can use the register.'
                : 'Dashboard first needs a role that can view the dashboard.',
        );
    }
}
