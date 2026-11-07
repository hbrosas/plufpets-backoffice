<?php

namespace App\Actions;

use App\Enums\CatalogItemType;
use App\Enums\ShopOrderStatus;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use App\Models\PosOpenOrder;
use App\Models\Voucher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PlaceShopOrder
{
    public function __construct(private ShopOrderStock $shopOrderStock) {}

    /**
     * @param  array{
     *     name: string,
     *     phone: string,
     *     delivery_method_id: int,
     *     payment_method_id: int,
     *     voucher_code?: string|null,
     *     address_line_1?: string|null,
     *     address_line_2?: string|null,
     *     city?: string|null,
     *     province?: string|null,
     *     latitude?: float|string|null,
     *     longitude?: float|string|null
     * }  $customer
     * @param  list<array{catalog_item_variant_id: int, quantity: int}>  $items
     */
    public function handle(Branch $branch, array $customer, array $items): PosOpenOrder
    {
        return DB::transaction(function () use ($branch, $customer, $items): PosOpenOrder {
            $priced = $this->price($branch, $customer, $items, true);
            $order = $this->createOrder($branch, $customer, $priced);
            $this->shopOrderStock->hold($order);

            return $order;
        });
    }

    /**
     * @param  array{
     *     name?: string,
     *     phone?: string,
     *     delivery_method_id: int,
     *     payment_method_id: int,
     *     voucher_code?: string|null,
     *     address_line_1?: string|null,
     *     address_line_2?: string|null,
     *     city?: string|null,
     *     province?: string|null,
     *     latitude?: float|string|null,
     *     longitude?: float|string|null
     * }  $customer
     * @param  list<array{catalog_item_variant_id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    public function quote(Branch $branch, array $customer, array $items): array
    {
        return $this->price($branch, $customer, $items, false);
    }

    public function activeVoucher(string $code): Voucher
    {
        $voucher = $this->voucher($code);

        if (! $voucher instanceof Voucher) {
            throw ValidationException::withMessages([
                'voucher_code' => 'That voucher is not valid.',
            ]);
        }

        return $voucher;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @param  list<array{catalog_item_variant_id: int, quantity: int}>  $items
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     subtotal: string,
     *     discount: string,
     *     total: string,
     *     checkout: array<string, mixed>
     * }
     */
    private function price(Branch $branch, array $customer, array $items, bool $lock): array
    {
        $delivery = DeliveryMethod::query()
            ->with('branchInstructions')
            ->whereKey($customer['delivery_method_id'])
            ->where('is_active', true)
            ->first();

        if (! $delivery instanceof DeliveryMethod) {
            throw ValidationException::withMessages([
                'delivery_method_id' => 'Choose a delivery method.',
            ]);
        }

        $payment = PaymentMethod::query()
            ->with('branchInstructions')
            ->whereKey($customer['payment_method_id'])
            ->where('is_active', true)
            ->first();

        if (! $payment instanceof PaymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Choose a payment method.',
            ]);
        }

        $address = $this->address($customer, $delivery);
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $query = CatalogItemVariant::query()
                ->with([
                    'catalogItem:id,name,type,has_branch_pricing,allows_preorder,is_active',
                    'branchPrices' => fn ($prices) => $prices->where('branch_id', $branch->id),
                ])
                ->whereKey($item['catalog_item_variant_id']);

            if ($lock) {
                $query->lockForUpdate();
            }

            $variant = $query->first();

            if ($variant === null || ! $variant->is_active || ! $variant->catalogItem?->is_active) {
                throw ValidationException::withMessages([
                    'items' => 'That item is not available.',
                ]);
            }

            $quantity = (int) $item['quantity'];
            $isProduct = $variant->catalogItem->type === CatalogItemType::Product;
            $preorder = false;

            if ($isProduct) {
                $inventoryQuery = BranchInventory::query()
                    ->where('branch_id', $branch->id)
                    ->where('catalog_item_variant_id', $variant->id);

                if ($lock) {
                    $inventoryQuery->lockForUpdate();
                }

                $inventory = $inventoryQuery->first();
                $onHand = $inventory instanceof BranchInventory ? $inventory->available() : 0;
                $preorder = $onHand < 1 && $variant->catalogItem->allows_preorder;

                if ($onHand < 1 && ! $variant->catalogItem->allows_preorder) {
                    throw ValidationException::withMessages([
                        'items' => $this->productName($variant).' is out of stock.',
                    ]);
                }

                if (! $preorder && $quantity > $onHand) {
                    throw ValidationException::withMessages([
                        'items' => 'Only '.$onHand.' left for '.$this->productName($variant).'.',
                    ]);
                }
            }

            $price = $this->unitPrice($variant);
            $subtotal += $price * $quantity;
            $lines[] = [
                'catalog_item_variant_id' => $variant->id,
                'product_name' => $variant->catalogItem->name,
                'variant_name' => $variant->name,
                'price' => number_format($price, 2, '.', ''),
                'quantity' => $quantity,
                'notes' => $preorder ? 'Preorder' : null,
                'is_product' => $isProduct,
                'modifiers' => [],
                'assigned_employee_ids' => [],
                'assisted_employee_ids' => [],
            ];
        }

        $deliveryFee = (float) ($delivery->fee ?? 0);
        $paymentFee = (float) ($payment->fee ?? 0);
        $voucher = $this->voucher(isset($customer['voucher_code']) ? (string) $customer['voucher_code'] : null);
        $discount = $voucher instanceof Voucher ? $voucher->discountFor($subtotal) : 0.0;
        $total = max(0, round($subtotal + $deliveryFee + $paymentFee - $discount, 2));

        return [
            'lines' => $lines,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount' => number_format($discount, 2, '.', ''),
            'delivery_fee' => number_format($deliveryFee, 2, '.', ''),
            'payment_fee' => number_format($paymentFee, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'asks_for_address' => $delivery->asks_for_address,
            'delivery_instructions' => $delivery->instructionFor($branch->id),
            'payment_instructions' => $payment->instructionFor($branch->id),
            'checkout' => [
                'delivery' => [
                    'id' => $delivery->id,
                    'name' => $delivery->name,
                    'fee' => number_format($deliveryFee, 2, '.', ''),
                    'instructions' => $delivery->instructionFor($branch->id),
                    'instruction_images' => $delivery->instructionImageUrls($branch->id),
                    'asks_for_address' => $delivery->asks_for_address,
                ],
                'payment' => [
                    'id' => $payment->id,
                    'name' => $payment->name,
                    'fee' => number_format($paymentFee, 2, '.', ''),
                    'instructions' => $payment->instructionFor($branch->id),
                    'instruction_images' => $payment->instructionImageUrls($branch->id),
                ],
                'address' => $address,
                'voucher' => $voucher instanceof Voucher ? [
                    'code' => $voucher->code,
                    'discount' => number_format($discount, 2, '.', ''),
                ] : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>|null
     */
    private function address(array $customer, DeliveryMethod $delivery): ?array
    {
        if (! $delivery->asks_for_address) {
            return null;
        }

        $line1 = trim((string) ($customer['address_line_1'] ?? ''));
        $city = trim((string) ($customer['city'] ?? ''));
        $province = trim((string) ($customer['province'] ?? ''));

        if ($line1 === '' || $city === '' || $province === '') {
            throw ValidationException::withMessages([
                'address_line_1' => 'Enter the delivery address.',
            ]);
        }

        return [
            'line_1' => $line1,
            'line_2' => $this->blank($customer['address_line_2'] ?? null),
            'city' => $city,
            'province' => $province,
            'latitude' => $this->blank($customer['latitude'] ?? null),
            'longitude' => $this->blank($customer['longitude'] ?? null),
        ];
    }

    private function voucher(?string $code): ?Voucher
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        $voucher = Voucher::query()->where('code', $code)->where('is_active', true)->first();

        if (! $voucher instanceof Voucher) {
            throw ValidationException::withMessages([
                'voucher_code' => 'That voucher is not valid.',
            ]);
        }

        return $voucher;
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function unitPrice(CatalogItemVariant $variant): float
    {
        if ($variant->catalogItem?->has_branch_pricing) {
            $branchPrice = $variant->branchPrices->first();

            if ($branchPrice !== null) {
                return (float) $branchPrice->price;
            }
        }

        return (float) $variant->price;
    }

    private function productName(CatalogItemVariant $variant): string
    {
        $name = $variant->catalogItem?->name ?? 'That item';
        $variantName = trim((string) $variant->name);

        if ($variantName !== '' && strcasecmp($variantName, 'Default') !== 0) {
            return $name.' · '.$variantName;
        }

        return $name;
    }

    /**
     * @param  array{name: string, phone: string}  $customer
     * @param  array{lines: mixed, subtotal: mixed, discount: mixed, total: mixed, checkout: mixed}  $priced
     */
    private function createOrder(Branch $branch, array $customer, array $priced): PosOpenOrder
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($branch, $customer, $priced): PosOpenOrder {
                    return PosOpenOrder::query()->create([
                        'branch_id' => $branch->id,
                        'number' => $this->orderCode(),
                        'name' => $customer['name'].' · '.$customer['phone'],
                        'phone' => $customer['phone'],
                        'status' => ShopOrderStatus::Placed->value,
                        'items' => $priced['lines'],
                        'subtotal' => $priced['subtotal'],
                        'discount' => $priced['discount'],
                        'total' => $priced['total'],
                        'checkout' => $priced['checkout'],
                        'saved_by_employee_number' => '',
                        'saved_by_name' => $customer['name'],
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 4) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Could not create an order number.');
    }

    private function orderCode(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $number = $this->mixedCode();

            $taken = PosOpenOrder::query()->where('number', $number)->exists();

            if (! $taken) {
                return $number;
            }
        }

        throw new RuntimeException('Could not create an order number.');
    }

    private function mixedCode(): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $alphabet = $letters.$digits;
        $characters = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        for ($index = 0; $index < 4; $index++) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        shuffle($characters);

        return implode('', $characters);
    }
}
