<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_public_home_page(): void
    {
        $branch = Branch::factory()->create([
            'name' => 'Greenheights',
            'is_active' => true,
        ]);

        Branch::factory()->inactive()->create([
            'name' => 'Hidden Branch',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Site/Home')
            ->has('branches', 1)
            ->where('branches.0.name', $branch->name));
    }

    public function test_authenticated_employee_can_still_open_the_public_home_page(): void
    {
        $this->actingAsEmployee()->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Site/Home'),
        );
    }

    public function test_guest_is_not_redirected_from_the_home_page_to_login(): void
    {
        $this->get('/')->assertOk();
        $this->get('/backoffice')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Auth/Login'),
        );
    }
}
