<?php

namespace Tests\Feature;

use App\Enums\PrinterConnection;
use App\Enums\PrinterRole;
use App\Models\Branch;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Printer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrinterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_printer(): void
    {
        $response = $this->post('/backoffice/pos/printers', [
            'name' => 'Counter 80mm',
            'role' => 'receipt',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('printers', 0);
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/pos/settings')->post('/backoffice/pos/printers', []);

        $response->assertRedirect('/backoffice/pos/settings');
        $response->assertSessionHasErrors([
            'branch_id' => 'Choose a branch for this printer.',
            'name' => 'Enter a printer name.',
        ]);
        $this->assertDatabaseCount('printers', 0);
    }

    public function test_bluetooth_printer_is_created_for_a_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);

        $response = $this->actingAsEmployee()->from('/backoffice/pos/settings')->post('/backoffice/pos/printers', [
            'branch_id' => $branch->id,
            'name' => 'Counter 80mm',
            'role' => PrinterRole::Receipt->value,
            'connection' => PrinterConnection::Bluetooth->value,
            'paper_width_mm' => 80,
            'is_default' => true,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('pos.settings.index'));
        $response->assertSessionHas('status', 'Printer created.');
        $this->assertDatabaseHas('printers', [
            'branch_id' => $branch->id,
            'name' => 'Counter 80mm',
            'role' => 'receipt',
            'connection_type' => 'bluetooth',
            'paper_width_mm' => 80,
            'is_default' => true,
        ]);
    }

    public function test_new_default_printer_clears_the_previous_default_for_the_same_branch_and_role(): void
    {
        $branch = Branch::factory()->create();
        $current = Printer::factory()->for($branch)->create([
            'name' => 'Old receipt',
            'role' => PrinterRole::Receipt,
            'is_default' => true,
        ]);
        $otherBranch = Printer::factory()->create([
            'name' => 'Other branch receipt',
            'role' => PrinterRole::Receipt,
            'is_default' => true,
        ]);

        $this->actingAsEmployee()->from('/backoffice/pos/settings')->post('/backoffice/pos/printers', [
            'branch_id' => $branch->id,
            'name' => 'New receipt',
            'role' => PrinterRole::Receipt->value,
            'connection' => PrinterConnection::Browser->value,
            'paper_width_mm' => 80,
            'baud_rate' => 9600,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertFalse($current->fresh()->is_default);
        $this->assertTrue($otherBranch->fresh()->is_default);
        $this->assertTrue(Printer::query()->where('name', 'New receipt')->first()?->is_default);
    }

    public function test_connect_saves_bluetooth_identity(): void
    {
        $printer = Printer::factory()->bluetooth()->create(['name' => 'Epson']);

        $response = $this->actingAsEmployee()->from('/backoffice/pos/settings')->put("/backoffice/pos/printers/{$printer->id}", [
            'branch_id' => $printer->branch_id,
            'name' => 'Epson',
            'role' => PrinterRole::Receipt->value,
            'connection' => PrinterConnection::Bluetooth->value,
            'paper_width_mm' => 80,
            'is_default' => false,
            'is_active' => true,
            'bluetooth_id' => 'bt-device-1',
            'device_name' => 'RPP02N',
        ]);

        $response->assertRedirect(route('pos.settings.index'));
        $this->assertDatabaseHas('printers', [
            'id' => $printer->id,
            'bluetooth_id' => 'bt-device-1',
            'device_name' => 'RPP02N',
        ]);
    }

    public function test_connect_saves_usb_identity(): void
    {
        $printer = Printer::factory()->serial()->create(['name' => 'Epson']);

        $response = $this->actingAsEmployee()->from('/backoffice/pos/settings')->put("/backoffice/pos/printers/{$printer->id}", [
            'branch_id' => $printer->branch_id,
            'name' => 'Epson',
            'role' => PrinterRole::Receipt->value,
            'connection' => PrinterConnection::Serial->value,
            'paper_width_mm' => 80,
            'baud_rate' => 9600,
            'is_default' => false,
            'is_active' => true,
            'usb_vendor_id' => 1208,
            'usb_product_id' => 514,
            'device_name' => 'USB 04B8:0202',
        ]);

        $response->assertRedirect(route('pos.settings.index'));
        $this->assertDatabaseHas('printers', [
            'id' => $printer->id,
            'usb_vendor_id' => 1208,
            'usb_product_id' => 514,
            'device_name' => 'USB 04B8:0202',
        ]);
    }

    public function test_test_page_uses_the_printer_paper_size(): void
    {
        $printer = Printer::factory()->label()->create([
            'name' => 'Barcode 40x30',
            'paper_width_mm' => 40,
            'paper_height_mm' => 30,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/pos/printers/{$printer->id}/test");

        $response->assertOk();
        $response->assertSee('Barcode 40x30', false);
        $response->assertSee('40mm 30mm', false);
    }

    public function test_label_print_uses_the_default_label_printer_size(): void
    {
        Printer::factory()->label()->create([
            'is_default' => true,
            'paper_width_mm' => 50,
            'paper_height_mm' => 25,
        ]);
        $item = CatalogItem::factory()->create();
        $variant = CatalogItemVariant::factory()->for($item)->create([
            'price' => 10,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/inventory/products/{$variant->id}/labels");

        $response->assertOk();
        $response->assertSee('50mm 25mm', false);
    }
}
