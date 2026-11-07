<?php

namespace App\Http\Controllers;

use App\Actions\PlaceShopOrder;
use App\Actions\SendShopOrderText;
use App\Enums\ShopOrderStatus;
use App\Http\Requests\ApplyShopVoucherRequest;
use App\Http\Requests\QuoteShopOrderRequest;
use App\Http\Requests\StoreShopOrderRequest;
use App\Models\Branch;
use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use App\Models\PosOpenOrder;
use App\Support\Peso;
use App\Support\ShopCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request, ShopCatalog $catalog): Response
    {
        return Inertia::render('Site/Home', $catalog->page($request));
    }

    public function checkout(Request $request): Response
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);
        $branch = $branches->firstWhere('id', $request->integer('branch')) ?? $branches->first();

        return Inertia::render('Site/Checkout', [
            'branch' => $branch instanceof Branch ? ['id' => $branch->id, 'name' => $branch->name] : null,
            'deliveryMethods' => DeliveryMethod::query()
                ->where('is_active', true)
                ->with('branchInstructions')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (DeliveryMethod $method): array => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'fee' => $method->fee === null ? '0.00' : $method->fee,
                    'fee_label' => Peso::format($method->fee ?? 0),
                    'instructions' => $method->instructionFor($branch?->id),
                    'instruction_images' => $method->instructionImageUrls($branch?->id),
                    'asks_for_address' => $method->asks_for_address,
                ]),
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->with('branchInstructions')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (PaymentMethod $method): array => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'fee' => $method->fee === null ? '0.00' : $method->fee,
                    'fee_label' => Peso::format($method->fee ?? 0),
                    'instructions' => $method->instructionFor($branch?->id),
                    'instruction_images' => $method->instructionImageUrls($branch?->id),
                ]),
        ]);
    }

    public function quote(QuoteShopOrderRequest $request, PlaceShopOrder $placeShopOrder): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $priced = $placeShopOrder->quote($branch, $request->safe()->only([
            'delivery_method_id',
            'payment_method_id',
            'voucher_code',
        ]), $request->validated('items'));

        return response()->json([
            'subtotal' => $priced['subtotal'],
            'discount' => $priced['discount'],
            'subtotal_label' => Peso::format($priced['subtotal']),
            'delivery_fee_label' => Peso::format($priced['delivery_fee']),
            'payment_fee_label' => Peso::format($priced['payment_fee']),
            'discount_label' => Peso::format($priced['discount']),
            'total_label' => Peso::format($priced['total']),
            'asks_for_address' => $priced['asks_for_address'],
            'delivery_instructions' => $priced['delivery_instructions'],
            'payment_instructions' => $priced['payment_instructions'],
        ]);
    }

    public function applyVoucher(ApplyShopVoucherRequest $request, PlaceShopOrder $placeShopOrder): JsonResponse
    {
        $voucher = $placeShopOrder->activeVoucher($request->string('voucher_code')->toString());

        return response()->json([
            'code' => $voucher->code,
        ]);
    }

    public function store(StoreShopOrderRequest $request, PlaceShopOrder $placeShopOrder, SendShopOrderText $sendShopOrderText): RedirectResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $order = $placeShopOrder->handle($branch, $request->customer(), $request->validated('items'));
        $smsError = $sendShopOrderText->handle($order->load('branch'));
        $request->session()->put('shop_order_id', $order->id);

        return redirect()
            ->route('shop.track', ['number' => $order->number])
            ->with('shop_order_placed', true)
            ->with('shop_text_notice', is_string($smsError)
                ? 'Your order is saved. The text could not be sent.'
                : 'We\'ll text '.$order->phone.' when the status changes.');
    }

    public function track(Request $request): Response
    {
        $lookup = strtoupper(preg_replace('/\s+/', '', $request->string('number')->toString()) ?? '');
        $missing = false;

        if ($request->exists('number')) {
            $order = $lookup === '' ? null : $this->shopOrderByNumber($lookup);
            $missing = $lookup !== '' && ! $order instanceof PosOpenOrder;
        } else {
            $order = PosOpenOrder::query()
                ->with('branch:id,name')
                ->find($request->session()->get('shop_order_id'));

            if (! $order instanceof PosOpenOrder || ! is_string($order->status) || $order->status === '') {
                $request->session()->forget('shop_order_id');
                $order = null;
            }

            $lookup = $order instanceof PosOpenOrder ? $order->number : '';
        }

        return Inertia::render('Site/Track', [
            'number' => $lookup,
            'missing' => $missing,
            'order' => $order instanceof PosOpenOrder ? $this->trackingPayload($order) : null,
            'statuses' => ShopOrderStatus::options(),
            'placed' => $request->session()->get('shop_order_placed') === true,
            'textNotice' => $request->session()->get('shop_text_notice'),
        ]);
    }

    private function shopOrderByNumber(string $number): ?PosOpenOrder
    {
        $order = PosOpenOrder::query()
            ->with('branch:id,name')
            ->where('number', $number)
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->orderByDesc('id')
            ->first();

        return $order instanceof PosOpenOrder ? $order : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function trackingPayload(PosOpenOrder $order): array
    {
        $checkout = is_array($order->checkout) ? $order->checkout : [];
        $delivery = is_array($checkout['delivery'] ?? null) ? $checkout['delivery'] : [];
        $payment = is_array($checkout['payment'] ?? null) ? $checkout['payment'] : [];
        $address = is_array($checkout['address'] ?? null) ? $checkout['address'] : null;
        $status = ShopOrderStatus::tryFrom((string) $order->status);

        return [
            'number' => $order->number,
            'status' => $status?->value ?? (string) $order->status,
            'status_label' => $status?->label() ?? (string) $order->status,
            'customer' => $order->saved_by_name,
            'phone' => $order->phone,
            'branch' => $order->branch?->name,
            'branch_id' => $order->branch_id,
            'items' => collect($order->items)->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $name = (string) ($item['product_name'] ?? 'Item');
                $variant = trim((string) ($item['variant_name'] ?? ''));
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($variant !== '' && strcasecmp($variant, 'Default') !== 0) {
                    $name .= ' · '.$variant;
                }

                return [
                    'name' => $name,
                    'quantity' => $quantity,
                    'total_label' => Peso::format(((float) ($item['price'] ?? 0)) * $quantity),
                ];
            })->filter()->values()->all(),
            'delivery' => [
                'name' => $delivery['name'] ?? null,
                'fee_label' => Peso::format($delivery['fee'] ?? 0),
                'instructions' => $this->present($delivery['instructions'] ?? null),
                'instruction_images' => $this->imageUrls($delivery['instruction_images'] ?? null),
            ],
            'payment' => [
                'name' => $payment['name'] ?? null,
                'fee_label' => Peso::format($payment['fee'] ?? 0),
                'instructions' => $this->present($payment['instructions'] ?? null),
                'instruction_images' => $this->imageUrls($payment['instruction_images'] ?? null),
            ],
            'address' => $address === null ? null : collect([
                $address['line_1'] ?? null,
                $address['line_2'] ?? null,
                $address['city'] ?? null,
                $address['province'] ?? null,
            ])->filter(fn (mixed $part): bool => is_string($part) && $part !== '')->implode(', '),
            'voucher' => $checkout['voucher']['code'] ?? null,
            'subtotal_label' => Peso::format($order->subtotal),
            'discount_label' => Peso::format($order->discount),
            'total_label' => Peso::format($order->total ?? $order->subtotal),
        ];
    }

    private function present(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @return list<string>
     */
    private function imageUrls(mixed $images): array
    {
        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter($images, fn (mixed $url): bool => is_string($url) && $url !== ''));
    }
}
