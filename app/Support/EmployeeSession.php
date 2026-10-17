<?php

namespace App\Support;

class EmployeeSession
{
    public const KEY = 'employee';

    /**
     * The signed-in employee stored in the session.
     *
     * @return array{employee_number: string, name: string}|null
     */
    public static function current(): ?array
    {
        $employee = session(self::KEY);

        if (! is_array($employee)) {
            return null;
        }

        return [
            'employee_number' => (string) ($employee['employee_number'] ?? ''),
            'name' => (string) ($employee['name'] ?? ''),
        ];
    }

    public static function check(): bool
    {
        return self::current() !== null;
    }
}
