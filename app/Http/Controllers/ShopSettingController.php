<?php

namespace App\Http\Controllers;

use App\Enums\TaxFeeType;
use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use App\Models\TaxFee;
use App\Support\Peso;
use Inertia\Inertia;
use Inertia\Response;

class ShopSettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Shop/Settings/Index', [
            'taxFees' => TaxFee::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (TaxFee $taxFee): array => [
                    'id' => $taxFee->id,
                    'name' => $taxFee->name,
                    'type' => $taxFee->type->value,
                    'type_label' => $taxFee->type->label(),
                    'rate' => $taxFee->rate,
                    'amount_label' => $taxFee->type === TaxFeeType::Percent
                        ? number_format((float) $taxFee->rate, 2).'%'
                        : Peso::format($taxFee->rate),
                    'is_active' => $taxFee->is_active,
                ]),
            'paymentMethods' => PaymentMethod::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (PaymentMethod $paymentMethod): array => [
                    'id' => $paymentMethod->id,
                    'name' => $paymentMethod->name,
                    'instructions' => $paymentMethod->instructions ?? '',
                    'is_cash' => $paymentMethod->is_cash,
                    'is_active' => $paymentMethod->is_active,
                ]),
            'deliveryMethods' => DeliveryMethod::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (DeliveryMethod $deliveryMethod): array => [
                    'id' => $deliveryMethod->id,
                    'name' => $deliveryMethod->name,
                    'fee' => $deliveryMethod->fee,
                    'fee_label' => $deliveryMethod->fee === null
                        ? 'Set in cart'
                        : Peso::format($deliveryMethod->fee),
                    'notes' => $deliveryMethod->notes ?? '',
                    'is_active' => $deliveryMethod->is_active,
                ]),
            'taxFeeTypes' => array_map(
                fn (TaxFeeType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                TaxFeeType::cases(),
            ),
        ]);
    }
}
