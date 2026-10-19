<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsEmployee(): static
    {
        return $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ]);
    }
}
