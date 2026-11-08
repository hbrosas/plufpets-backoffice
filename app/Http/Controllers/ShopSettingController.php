<?php

namespace App\Http\Controllers;

use App\Enums\TaxFeeType;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\DeliveryMethod;
use App\Models\DeliveryMethodInstruction;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodInstruction;
use App\Models\TaxFee;
use App\Models\Voucher;
use App\Support\InstructionImages;
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
                ->with('branchInstructions')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (PaymentMethod $paymentMethod): array => [
                    'id' => $paymentMethod->id,
                    'name' => $paymentMethod->name,
                    'instructions' => $paymentMethod->instructions ?? '',
                    'instructions_for_all_branches' => $paymentMethod->instructions_for_all_branches,
                    'instruction_images' => InstructionImages::previews($paymentMethod->instruction_images),
                    'branch_instructions' => $paymentMethod->branchInstructions
                        ->map(fn (PaymentMethodInstruction $instruction): array => [
                            'branch_id' => $instruction->branch_id,
                            'instructions' => $instruction->instructions ?? '',
                            'images' => InstructionImages::previews($instruction->images),
                        ])
                        ->values(),
                    'fee' => $paymentMethod->fee,
                    'fee_label' => $paymentMethod->fee === null ? 'No fee' : Peso::format($paymentMethod->fee),
                    'is_cash' => $paymentMethod->is_cash,
                    'is_active' => $paymentMethod->is_active,
                ]),
            'deliveryMethods' => DeliveryMethod::query()
                ->with('branchInstructions')
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
                    'instructions_for_all_branches' => $deliveryMethod->instructions_for_all_branches,
                    'instruction_images' => InstructionImages::previews($deliveryMethod->instruction_images),
                    'branch_instructions' => $deliveryMethod->branchInstructions
                        ->map(fn (DeliveryMethodInstruction $instruction): array => [
                            'branch_id' => $instruction->branch_id,
                            'instructions' => $instruction->instructions ?? '',
                            'images' => InstructionImages::previews($instruction->images),
                        ])
                        ->values(),
                    'asks_for_address' => $deliveryMethod->asks_for_address,
                    'uses_delivery_schedule' => $deliveryMethod->uses_delivery_schedule,
                    'is_active' => $deliveryMethod->is_active,
                ]),
            'vouchers' => Voucher::query()
                ->orderBy('code')
                ->orderBy('id')
                ->get()
                ->map(fn (Voucher $voucher): array => [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'type' => $voucher->type->value,
                    'type_label' => $voucher->type->label(),
                    'amount' => $voucher->amount,
                    'amount_label' => $voucher->type === VoucherType::Percent
                        ? number_format((float) $voucher->amount, 2).'%'
                        : Peso::format($voucher->amount),
                    'is_active' => $voucher->is_active,
                ]),
            'voucherTypes' => array_map(
                fn (VoucherType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                VoucherType::cases(),
            ),
            'taxFeeTypes' => array_map(
                fn (TaxFeeType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                TaxFeeType::cases(),
            ),
            'branches' => Branch::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ]),
        ]);
    }
}
