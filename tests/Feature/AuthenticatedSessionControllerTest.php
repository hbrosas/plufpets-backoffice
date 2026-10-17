<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_guest_sees_the_login_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_missing_credentials_are_rejected(): void
    {
        $response = $this->from('/')->post('/login', []);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'employee_number' => 'Enter your employee number.',
            'pin' => 'Enter your PIN code.',
        ]);
        $response->assertSessionMissing('employee');
    }

    public function test_short_pin_is_rejected(): void
    {
        $response = $this->from('/')->post('/login', [
            'employee_number' => '10001',
            'pin' => '12',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'pin' => 'The PIN code must be 4 to 8 digits.',
        ]);
        $response->assertSessionMissing('employee');
    }

    public function test_incorrect_credentials_are_rejected(): void
    {
        $response = $this->from('/')->post('/login', [
            'employee_number' => '10001',
            'pin' => '0000',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'employee_number' => 'Employee number or PIN code is incorrect.',
        ]);
        $response->assertSessionMissing('employee');
    }

    public function test_correct_credentials_start_a_session(): void
    {
        $response = $this->post('/login', [
            'employee_number' => '10001',
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('employee', [
            'employee_number' => '10001',
            'name' => 'Sample Employee',
        ]);
    }

    public function test_credentials_with_surrounding_spaces_start_a_session(): void
    {
        $response = $this->post('/login', [
            'employee_number' => ' 10001 ',
            'pin' => ' 1234 ',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('employee.employee_number', '10001');
    }

    public function test_authenticated_employee_is_redirected_away_from_login(): void
    {
        $response = $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ])->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_logout_ends_the_session(): void
    {
        $response = $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ])->post('/logout');

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('employee');

        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_repeated_login_attempts_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/')->post('/login', [
                'employee_number' => '10001',
                'pin' => '0000',
            ])->assertRedirect('/');
        }

        $this->from('/')->post('/login', [
            'employee_number' => '10001',
            'pin' => '0000',
        ])->assertTooManyRequests();
    }
}
