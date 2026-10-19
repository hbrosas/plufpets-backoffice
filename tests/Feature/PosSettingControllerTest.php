<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Printer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_pos_settings(): void
    {
        $this->get('/backoffice/pos/settings')->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_printers(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        Printer::factory()->bluetooth()->for($branch)->create([
            'name' => 'Front receipt',
            'is_default' => true,
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/pos/settings');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Settings/Index')
            ->where('printers.0.name', 'Front receipt')
            ->where('printers.0.branch_name', 'Greenheights')
            ->where('printers.0.role_label', 'Receipts')
            ->where('printers.0.connection_label', 'Bluetooth')
            ->where('printers.0.is_default', true)
            ->where('branches.0.name', 'Greenheights')
            ->has('printerRoles')
            ->has('printerConnections'));
    }
}
