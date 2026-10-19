<?php

namespace Tests\Feature;

use App\Enums\TaxFeeType;
use App\Models\TaxFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxFeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_tax_fee(): void
    {
        $response = $this->post('/backoffice/shop/tax-fees', [
            'name' => 'VAT',
            'type' => TaxFeeType::Percent->value,
            'rate' => '12',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('tax_fees', 0);
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/tax-fees', []);

        $response->assertRedirect('/backoffice/shop/settings');
        $response->assertSessionHasErrors([
            'name' => 'Enter a tax or fee name.',
            'type' => 'Choose percent or a fixed amount.',
            'rate' => 'Enter a rate.',
        ]);
        $this->assertDatabaseCount('tax_fees', 0);
    }

    public function test_tax_fee_is_created(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/tax-fees', [
            'name' => 'VAT',
            'type' => TaxFeeType::Percent->value,
            'rate' => '12',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $response->assertSessionHas('status', 'Tax or fee created.');
        $this->assertDatabaseHas('tax_fees', [
            'name' => 'VAT',
            'type' => TaxFeeType::Percent->value,
            'rate' => '12.00',
        ]);
    }

    public function test_percent_rate_over_one_hundred_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/tax-fees', [
            'name' => 'VAT',
            'type' => TaxFeeType::Percent->value,
            'rate' => '101',
            'is_active' => true,
        ]);

        $response->assertRedirect('/backoffice/shop/settings');
        $response->assertSessionHasErrors([
            'rate' => 'Percent rates cannot be more than 100.',
        ]);
        $this->assertDatabaseCount('tax_fees', 0);
    }

    public function test_duplicate_tax_fee_name_is_rejected(): void
    {
        TaxFee::factory()->create(['name' => 'VAT']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/tax-fees', [
            'name' => 'VAT',
            'type' => TaxFeeType::Percent->value,
            'rate' => '12',
            'is_active' => true,
        ]);

        $response->assertRedirect('/backoffice/shop/settings');
        $response->assertSessionHasErrors([
            'name' => 'That tax or fee already exists.',
        ]);
        $this->assertDatabaseCount('tax_fees', 1);
    }

    public function test_tax_fee_can_be_updated(): void
    {
        $taxFee = TaxFee::factory()->create([
            'name' => 'Service fee',
            'type' => TaxFeeType::Percent,
            'rate' => 5,
        ]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->put("/backoffice/shop/tax-fees/{$taxFee->id}", [
            'name' => 'Packing fee',
            'type' => TaxFeeType::Fixed->value,
            'rate' => '25.50',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseHas('tax_fees', [
            'id' => $taxFee->id,
            'name' => 'Packing fee',
            'type' => TaxFeeType::Fixed->value,
            'rate' => '25.50',
            'is_active' => false,
        ]);
    }

    public function test_tax_fee_can_be_deleted(): void
    {
        $taxFee = TaxFee::factory()->create(['name' => 'VAT']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->delete("/backoffice/shop/tax-fees/{$taxFee->id}");

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseMissing('tax_fees', ['id' => $taxFee->id]);
    }
}
