<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Modifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModifierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_modifiers(): void
    {
        $response = $this->get('/backoffice/shop/modifiers');

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_a_modifier(): void
    {
        $response = $this->post('/backoffice/shop/modifiers', [
            'name' => 'Gift wrap',
            'options' => ['Standard', 'Premium'],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('modifiers', 0);
    }

    public function test_authenticated_employee_sees_modifiers(): void
    {
        $modifier = Modifier::factory()->create([
            'name' => 'Gift wrap',
            'options' => ['Standard', 'Premium'],
        ]);
        CatalogItem::factory()->create()->modifiers()->attach($modifier);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/modifiers');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Modifiers/Index')
            ->where('modifiers.0.name', 'Gift wrap')
            ->where('modifiers.0.options.0', 'Standard')
            ->where('modifiers.0.options.1', 'Premium')
            ->where('modifiers.0.item_count', 1));
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/modifiers')->post('/backoffice/shop/modifiers', []);

        $response->assertRedirect('/backoffice/shop/modifiers');
        $response->assertSessionHasErrors([
            'name' => 'Enter a modifier name.',
            'options' => 'Add at least one option.',
        ]);
        $this->assertDatabaseCount('modifiers', 0);
    }

    public function test_modifier_is_created_with_options(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/modifiers')->post('/backoffice/shop/modifiers', [
            'name' => 'Gift wrap',
            'options' => ['Standard', 'Premium', 'Standard'],
            'is_active' => true,
        ]);

        $response->assertRedirect('/backoffice/shop/modifiers');
        $response->assertSessionHas('status', 'Modifier created.');

        $modifier = Modifier::query()->where('name', 'Gift wrap')->first();

        $this->assertNotNull($modifier);
        $this->assertTrue($modifier->is_active);
        $this->assertSame(['Standard', 'Premium'], $modifier->options);
    }

    public function test_duplicate_modifier_name_is_rejected(): void
    {
        Modifier::factory()->create(['name' => 'Gift wrap']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/modifiers')->post('/backoffice/shop/modifiers', [
            'name' => 'Gift wrap',
            'options' => ['Standard'],
            'is_active' => true,
        ]);

        $response->assertRedirect('/backoffice/shop/modifiers');
        $response->assertSessionHasErrors([
            'name' => 'That modifier already exists.',
        ]);
        $this->assertDatabaseCount('modifiers', 1);
    }

    public function test_modifier_can_be_updated(): void
    {
        $modifier = Modifier::factory()->create([
            'name' => 'Gift wrap',
            'options' => ['Standard'],
            'is_active' => true,
        ]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/modifiers')->put("/backoffice/shop/modifiers/{$modifier->id}", [
            'name' => 'Wrap style',
            'options' => ['Paper', 'Box'],
            'is_active' => false,
        ]);

        $response->assertRedirect('/backoffice/shop/modifiers');
        $this->assertDatabaseHas('modifiers', [
            'id' => $modifier->id,
            'name' => 'Wrap style',
            'is_active' => false,
        ]);
        $this->assertSame(['Paper', 'Box'], $modifier->fresh()->options);
    }

    public function test_modifier_can_be_deleted(): void
    {
        $modifier = Modifier::factory()->create(['name' => 'Gift wrap']);
        CatalogItem::factory()->create()->modifiers()->attach($modifier);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/modifiers')->delete("/backoffice/shop/modifiers/{$modifier->id}");

        $response->assertRedirect('/backoffice/shop/modifiers');
        $this->assertDatabaseMissing('modifiers', ['id' => $modifier->id]);
        $this->assertDatabaseCount('catalog_item_modifier', 0);
    }
}
