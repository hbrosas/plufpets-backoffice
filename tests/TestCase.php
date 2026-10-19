<?php

namespace Tests;

use App\Models\Employee;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsEmployee(?Employee $employee = null): static
    {
        if ($employee instanceof Employee) {
            return $this->withSession([
                'employee' => [
                    'id' => $employee->id,
                ],
            ]);
        }

        return $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ]);
    }
}
