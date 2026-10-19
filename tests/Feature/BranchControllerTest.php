<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BranchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_branches(): void
    {
        $response = $this->get('/backoffice/shop/branches');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_branches(): void
    {
        Branch::factory()->create([
            'name' => 'Makati Branch',
            'code' => 'MKT',
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/branches');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Branches/Index')
            ->where('branches.data.0.name', 'Makati Branch')
            ->where('branches.data.0.code', 'MKT'));
    }

    public function test_empty_create_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/branches/create')->post('/backoffice/shop/branches', []);

        $response->assertRedirect('/backoffice/shop/branches/create');
        $response->assertSessionHasErrors([
            'name' => 'Enter a branch name.',
        ]);
        $this->assertDatabaseCount('branches', 0);
    }

    public function test_valid_branch_is_created(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/shop/branches', [
            'name' => 'Makati Branch',
            'registered_business_name' => 'Pluf Pets Trading',
            'tin' => '123-456-789-00000',
            'code' => 'MKT',
            'address' => 'Ayala Avenue',
            'phone' => '09171234567',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.branches.index'));
        $response->assertSessionHas('status', 'Branch created.');
        $this->assertDatabaseHas('branches', [
            'name' => 'Makati Branch',
            'registered_business_name' => 'Pluf Pets Trading',
            'tin' => '123-456-789-00000',
            'code' => 'MKT',
            'address' => 'Ayala Avenue',
            'is_active' => true,
        ]);
    }

    public function test_branch_can_be_updated(): void
    {
        $branch = Branch::factory()->create([
            'name' => 'Makati Branch',
            'code' => 'MKT',
        ]);

        $response = $this->actingAsEmployee()->put("/backoffice/shop/branches/{$branch->id}", [
            'name' => 'BGC Branch',
            'code' => 'BGC',
            'address' => 'High Street',
            'phone' => '09170000000',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('shop.branches.index'));
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'BGC Branch',
            'code' => 'BGC',
            'is_active' => false,
        ]);
    }

    public function test_branch_inventory_routes_are_unavailable(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAsEmployee()->get("/backoffice/shop/branches/{$branch->id}/inventory")->assertNotFound();
        $this->actingAsEmployee()->put("/backoffice/shop/branches/{$branch->id}/inventory", [])->assertNotFound();
    }

    public function test_branch_can_be_deleted(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);

        $response = $this->actingAsEmployee()->delete("/backoffice/shop/branches/{$branch->id}");

        $response->assertRedirect(route('shop.branches.index'));
        $this->assertDatabaseMissing('branches', [
            'id' => $branch->id,
        ]);
    }
}
