<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Enums\HotelPetGender;
use App\Enums\PosCashMovementType;
use App\Models\Branch;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Employee;
use App\Models\HotelPodType;
use App\Models\HotelStay;
use App\Models\HotelStayPayment;
use App\Models\HotelStayPet;
use App\Models\PaymentMethod;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Role;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HotelStayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_check_a_hotel_guest_in(): void
    {
        $branch = Branch::factory()->create();

        $this->post(route('pos.hotel-stays.store', $branch), $this->checkInPayload())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('hotel_stays', 0);
    }

    public function test_employee_without_register_access_cannot_check_a_guest_in(): void
    {
        $branch = Branch::factory()->create();
        $employee = Employee::factory()->create([
            'role_id' => Role::factory()->withPermissions(['dashboard.view'])->create()->id,
        ]);

        $this->actingAsEmployee($employee)
            ->post(route('pos.hotel-stays.store', $branch), $this->checkInPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('hotel_stays', 0);
    }

    public function test_check_in_requires_the_guest_and_pet_details(): void
    {
        $branch = $this->openRegister();

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.hotel-stays.store', $branch), [])
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'client_name' => 'Enter the client name.',
                'contact_number' => 'Enter the contact number.',
                'address' => 'Enter the address.',
                'checkout_on' => 'Enter the check-out date.',
                'hotel_pod_type_id' => 'Choose a pod type.',
                'pets' => 'Add at least one pet.',
            ]);

        $this->assertDatabaseCount('hotel_stays', 0);
    }

    public function test_check_in_rejects_a_past_checkout_date(): void
    {
        $branch = $this->openRegister();

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.hotel-stays.store', $branch), $this->checkInPayload([
                'checkout_on' => now(ReportPeriod::ZONE)->subDay()->toDateString(),
            ]))
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'checkout_on' => 'Check-out date must be today or later.',
            ]);
    }

    public function test_check_in_rejects_an_unknown_vaccine(): void
    {
        $branch = $this->openRegister();
        $payload = $this->checkInPayload();
        $payload['pets'][0]['vaccines'] = ['parvo'];

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.hotel-stays.store', $branch), $payload)
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'pets.0.vaccines.0' => 'Choose a vaccine from the list.',
            ]);
    }

    public function test_an_inactive_pod_type_cannot_be_chosen_for_check_in(): void
    {
        $branch = $this->openRegister();
        $podType = HotelPodType::factory()->inactive()->create(['name' => 'Closed crate']);

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.hotel-stays.store', $branch), $this->checkInPayload([
                'hotel_pod_type_id' => $podType->id,
            ]))
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'hotel_pod_type_id' => 'Choose a pod type.',
            ]);

        $this->assertDatabaseCount('hotel_stays', 0);

        $this->actingAsEmployee()
            ->get(route('pos.index', $branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hotelOptions.pod_types', fn ($podTypes) => $podTypes->contains(fn ($type) => $type['name'] === 'Small dog')
                    && $podTypes->doesntContain(fn ($type) => $type['name'] === 'Closed crate')));
    }

    public function test_check_in_is_rejected_without_an_open_shift(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.hotel-stays.store', $branch), $this->checkInPayload())
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'status' => 'Open a shift before checking a guest in.',
            ]);
    }

    public function test_check_in_records_the_client_each_pet_and_lists_who_is_still_here(): void
    {
        $branch = $this->openRegister();
        $checkoutOn = now(ReportPeriod::ZONE)->addDays(2)->toDateString();
        $payload = $this->checkInPayload(['checkout_on' => $checkoutOn]);
        $payload['pets'][] = [
            'name' => 'Mochi',
            'breed' => 'Puspin',
            'age' => '1 year',
            'gender' => 'male',
            'has_vaccine_booklet' => false,
            'vaccines' => ['four_in_one'],
            'feeding_instructions' => null,
            'medication_instructions' => null,
            'special_instructions' => 'Needs a quiet pod',
        ];
        HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Gone Guest',
        ]);

        $this->actingAsEmployee()
            ->post(route('pos.hotel-stays.store', $branch), $payload)
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHas('status', 'Checked in Mina Cruz.');

        $this->assertDatabaseHas('hotel_stays', [
            'branch_id' => $branch->id,
            'client_name' => 'Mina Cruz',
            'contact_number' => '09171234567',
            'hotel_pod_type_id' => HotelPodType::query()->where('name', 'Small dog')->value('id'),
            'checked_in_by_name' => 'Sample Employee',
        ]);
        $stay = HotelStay::query()->where('client_name', 'Mina Cruz')->first();
        $this->assertNotNull($stay);
        $this->assertNull($stay->checked_out_at);
        $this->assertSame($checkoutOn, $stay->checkout_on->toDateString());
        $biscuit = HotelStayPet::query()->where('name', 'Biscuit')->first();
        $this->assertNotNull($biscuit);
        $this->assertSame(HotelPetGender::Female, $biscuit->gender);
        $this->assertTrue($biscuit->has_vaccine_booklet);
        $this->assertSame(['anti_rabies', 'kennel_cough'], $biscuit->vaccines);
        $this->assertSame('One cup twice a day', $biscuit->feeding_instructions);
        $this->assertDatabaseHas('hotel_stay_pets', [
            'name' => 'Mochi',
            'has_vaccine_booklet' => false,
            'special_instructions' => 'Needs a quiet pod',
        ]);

        $this->actingAsEmployee()
            ->get(route('pos.index', $branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('hotelStays', 1)
                ->where('hotelStays.0.client_name', 'Mina Cruz')
                ->where('hotelStays.0.pet_names', 'Biscuit, Mochi')
                ->where('hotelStays.0.pod_type_label', 'Small dog')
                ->where('hotelStays.0.pets.0.vaccines', ['Anti-Rabies', 'Kennel Cough'])
                ->has('hotelOptions.vaccines', 5));
    }

    public function test_cash_downpayment_is_recorded_on_the_stay_and_in_the_drawer(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'starting_cash' => 1000,
        ]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
            'client_name' => 'Mina Cruz',
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $this->actingAsEmployee()
            ->post(route('pos.hotel-stays.payments.store', [$branch, $stay]), [
                'payment_method_id' => $payment->id,
                'amount' => '500',
                'amount_tendered' => '600',
            ])
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHas('status', 'Downpayment recorded.')
            ->assertSessionHas('open_drawer', true);

        $this->assertDatabaseHas('hotel_stay_payments', [
            'hotel_stay_id' => $stay->id,
            'payment_method_id' => $payment->id,
            'is_cash' => true,
            'amount' => '500.00',
            'amount_tendered' => '600.00',
            'change_due' => '100.00',
        ]);
        $this->assertDatabaseHas('pos_cash_movements', [
            'pos_shift_id' => $shift->id,
            'type' => PosCashMovementType::CashIn->value,
            'amount' => '500.00',
            'reason' => 'Hotel downpayment for Mina Cruz',
        ]);
        $this->assertSame('1500.00', $shift->fresh()->expectedDrawerCash());

        $this->actingAsEmployee()
            ->get(route('pos.index', $branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('todayTransactions', fn ($rows) => $rows->contains(
                    fn ($row) => $row['title'] === 'Hotel payment'
                        && $row['amount_label'] === '₱500.00'
                        && $row['detail'] === 'Mina Cruz',
                ) && $rows->doesntContain(fn ($row) => $row['title'] === 'Cash in')));
    }

    public function test_cash_downpayment_requires_the_amount_tendered(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.hotel-stays.payments.store', [$branch, $stay]), [
                'payment_method_id' => $payment->id,
                'amount' => '500',
            ])
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'amount_tendered' => 'Enter the amount tendered.',
            ]);

        $this->assertDatabaseCount('hotel_stay_payments', 0);
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_card_downpayment_requires_a_reference_and_does_not_change_the_drawer(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'starting_cash' => 1000,
        ]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
        ]);
        $payment = PaymentMethod::factory()->create(['name' => 'Card']);

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.hotel-stays.payments.store', [$branch, $stay]), [
                'payment_method_id' => $payment->id,
                'amount' => '500',
            ])
            ->assertSessionHasErrors([
                'reference_number' => 'Enter the reference number.',
            ]);

        $this->actingAsEmployee()
            ->post(route('pos.hotel-stays.payments.store', [$branch, $stay]), [
                'payment_method_id' => $payment->id,
                'amount' => '500',
                'reference_number' => 'REF-100',
            ])
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHas('open_drawer', false);

        $this->assertDatabaseHas('hotel_stay_payments', [
            'hotel_stay_id' => $stay->id,
            'reference_number' => 'REF-100',
            'is_cash' => false,
            'amount' => '500.00',
        ]);
        $this->assertDatabaseCount('pos_cash_movements', 0);
        $this->assertSame('1000.00', $shift->fresh()->expectedDrawerCash());
    }

    public function test_payment_for_a_guest_at_another_branch_is_not_found(): void
    {
        $branch = $this->openRegister();
        $other = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $other->id]);
        $stay = HotelStay::factory()->create(['branch_id' => $other->id]);
        $payment = PaymentMethod::factory()->cash()->create();

        $this->actingAsEmployee()
            ->post(route('pos.hotel-stays.payments.store', [$branch, $stay]), [
                'payment_method_id' => $payment->id,
                'amount' => '100',
                'amount_tendered' => '100',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('hotel_stay_payments', 0);
    }

    public function test_checked_out_guest_cannot_take_another_payment(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);
        $stay = HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.hotel-stays.payments.store', [$branch, $stay]), [
                'payment_method_id' => $payment->id,
                'amount' => '100',
                'amount_tendered' => '100',
            ])
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'status' => 'This guest is already checked out.',
            ]);
    }

    public function test_checkout_removes_the_guest_from_the_checked_in_list(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
            'client_name' => 'Mina Cruz',
        ]);
        PosOpenOrder::factory()->create([
            'branch_id' => $branch->id,
            'hotel_stay_id' => $stay->id,
            'name' => 'Mina Cruz',
        ]);

        $this->actingAsEmployee()
            ->post(route('pos.hotel-stays.checkout', [$branch, $stay]))
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHas('status', 'Checked out Mina Cruz.');

        $this->assertNotNull($stay->fresh()->checked_out_at);
        $this->assertSame('Sample Employee', $stay->fresh()->checked_out_by_name);
        $this->assertDatabaseMissing('pos_open_orders', ['hotel_stay_id' => $stay->id]);

        $this->actingAsEmployee()
            ->get(route('pos.index', $branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('hotelStays', 0));
    }

    public function test_checkout_for_a_guest_at_another_branch_is_not_found(): void
    {
        $branch = $this->openRegister();
        $stay = HotelStay::factory()->create();

        $this->actingAsEmployee()
            ->post(route('pos.hotel-stays.checkout', [$branch, $stay]))
            ->assertNotFound();

        $this->assertNull($stay->fresh()->checked_out_at);
    }

    public function test_sale_for_a_checked_in_guest_is_linked_to_the_stay(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
        ]);
        $cart = PosOpenOrder::factory()->create([
            'branch_id' => $branch->id,
            'hotel_stay_id' => $stay->id,
            'name' => 'Mina Cruz',
        ]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Service, 'name' => 'Treat']))
            ->create(['name' => 'Default', 'price' => 80]);
        $payment = PaymentMethod::factory()->cash()->create();

        $this->actingAsEmployee()
            ->post(route('pos.sales.store', $branch), [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '80',
                    'amount_tendered' => '80',
                ]],
                'hotel_stay_id' => $stay->id,
            ])
            ->assertRedirect(route('pos.index', $branch));

        $this->assertDatabaseHas('pos_sales', [
            'branch_id' => $branch->id,
            'hotel_stay_id' => $stay->id,
            'total' => '80.00',
        ]);
        $this->assertDatabaseMissing('pos_open_orders', ['id' => $cart->id]);
    }

    public function test_a_sale_can_apply_the_hotel_payment_to_the_amount_due(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'starting_cash' => 1000,
        ]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
            'client_name' => 'Mina Cruz',
        ]);
        $payment = PaymentMethod::factory()->cash()->create();
        HotelStayPayment::factory()->create([
            'hotel_stay_id' => $stay->id,
            'pos_shift_id' => $shift->id,
            'payment_method_id' => $payment->id,
            'amount' => '30.00',
            'amount_tendered' => '30.00',
        ]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Service, 'name' => 'Treat']))
            ->create(['name' => 'Default', 'price' => 80]);

        $this->actingAsEmployee()
            ->post(route('pos.sales.store', $branch), [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '50',
                    'amount_tendered' => '50',
                ]],
                'hotel_stay_id' => $stay->id,
                'apply_hotel_payment' => true,
            ])
            ->assertRedirect(route('pos.index', $branch));

        $sale = PosSale::query()->where('hotel_stay_id', $stay->id)->first();
        $this->assertNotNull($sale);
        $this->assertSame('80.00', $sale->total);
        $this->assertSame('30.00', $sale->hotel_credit);
        $this->assertSame('Cash + Hotel payment', $sale->payment_method_name);
        $this->assertSame('50.00', $sale->payments()->first()?->amount);
        $this->assertSame('1050.00', $shift->fresh()->expectedDrawerCash());
    }

    public function test_a_hotel_payment_that_covers_the_sale_leaves_nothing_due(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create(['branch_id' => $branch->id, 'starting_cash' => 1000]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
        ]);
        HotelStayPayment::factory()->create([
            'hotel_stay_id' => $stay->id,
            'pos_shift_id' => $shift->id,
            'amount' => '80.00',
        ]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Service, 'name' => 'Treat']))
            ->create(['name' => 'Default', 'price' => 80]);

        $this->actingAsEmployee()
            ->post(route('pos.sales.store', $branch), [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [],
                'hotel_stay_id' => $stay->id,
                'apply_hotel_payment' => true,
            ])
            ->assertRedirect(route('pos.index', $branch));

        $sale = PosSale::query()->where('hotel_stay_id', $stay->id)->first();
        $this->assertNotNull($sale);
        $this->assertSame('80.00', $sale->hotel_credit);
        $this->assertSame('Hotel payment', $sale->payment_method_name);
        $this->assertFalse($sale->is_cash);
        $this->assertSame('1000.00', $shift->fresh()->expectedDrawerCash());

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.sales.store', $branch), [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [],
                'hotel_stay_id' => $stay->id,
                'apply_hotel_payment' => true,
            ])
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'apply_hotel_payment' => 'This guest has no hotel payment left to apply.',
            ]);
    }

    public function test_other_payments_must_match_the_amount_due_after_a_hotel_payment(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();
        HotelStayPayment::factory()->create([
            'hotel_stay_id' => $stay->id,
            'pos_shift_id' => $stay->pos_shift_id,
            'payment_method_id' => $payment->id,
            'amount' => '30.00',
        ]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Service, 'name' => 'Treat']))
            ->create(['name' => 'Default', 'price' => 80]);

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.sales.store', $branch), [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '80',
                    'amount_tendered' => '80',
                ]],
                'hotel_stay_id' => $stay->id,
                'apply_hotel_payment' => true,
            ])
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'payments' => 'Payments must equal the amount due.',
            ]);

        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_sale_is_rejected_when_the_guest_is_already_checked_out(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $stay = HotelStay::factory()->checkedOut()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Service, 'name' => 'Treat']))
            ->create(['name' => 'Default', 'price' => 80]);
        $payment = PaymentMethod::factory()->cash()->create();

        $this->actingAsEmployee()
            ->from(route('pos.index', $branch))
            ->post(route('pos.sales.store', $branch), [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '80',
                    'amount_tendered' => '80',
                ]],
                'hotel_stay_id' => $stay->id,
            ])
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHasErrors([
                'hotel_stay_id' => 'Choose a guest who is still checked in.',
            ]);

        $this->assertDatabaseCount('pos_sales', 0);
    }

    private function openRegister(): Branch
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);

        return $branch;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function checkInPayload(array $overrides = []): array
    {
        return $overrides + [
            'client_name' => 'Mina Cruz',
            'contact_number' => '09171234567',
            'address' => '12 Mabini Street, Quezon City',
            'checkout_on' => now(ReportPeriod::ZONE)->addDay()->toDateString(),
            'hotel_pod_type_id' => HotelPodType::query()->where('name', 'Small dog')->value('id'),
            'pets' => [[
                'name' => 'Biscuit',
                'breed' => 'Aspin',
                'age' => '3 years',
                'gender' => 'female',
                'has_vaccine_booklet' => true,
                'vaccines' => ['anti_rabies', 'kennel_cough'],
                'feeding_instructions' => 'One cup twice a day',
                'medication_instructions' => 'Apoquel with dinner',
                'special_instructions' => 'Scared of thunder',
            ]],
        ];
    }
}
