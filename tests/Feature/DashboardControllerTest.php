<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/backoffice/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_the_dashboard(): void
    {
        $response = $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ])->get('/backoffice/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('employee.employee_number', '10001')
            ->where('employee.name', 'Sample Employee'));
    }
}
