<?php

namespace App\Support;

use App\Enums\ShopOrderStatus;
use App\Models\PosOpenOrder;
use App\Models\SmsTemplate;

class ShopOrderMessages
{
    public static function ensure(): void
    {
        foreach (ShopOrderStatus::cases() as $status) {
            SmsTemplate::query()->firstOrCreate(
                ['key' => $status->templateKey()],
                [
                    'name' => $status->label(),
                    'body' => $status->defaultBody(),
                ],
            );
        }
    }

    public static function body(ShopOrderStatus $status): string
    {
        self::ensure();

        $body = SmsTemplate::query()->where('key', $status->templateKey())->value('body');

        return is_string($body) && $body !== '' ? $body : $status->defaultBody();
    }

    public static function render(PosOpenOrder $order, ShopOrderStatus $status): string
    {
        return strtr(self::body($status), [
            '{name}' => $order->saved_by_name,
            '{order}' => $order->number,
            '{total}' => Peso::format((float) ($order->total ?? $order->subtotal)),
            '{branch}' => $order->branch?->name ?? 'the branch',
        ]);
    }

    /**
     * @return list<array{id: int, key: string, name: string, body: string}>
     */
    public static function editorPayload(): array
    {
        self::ensure();

        return SmsTemplate::query()
            ->whereIn('key', array_map(
                fn (ShopOrderStatus $status): string => $status->templateKey(),
                ShopOrderStatus::cases(),
            ))
            ->orderBy('id')
            ->get()
            ->map(fn (SmsTemplate $template): array => [
                'id' => $template->id,
                'key' => (string) $template->key,
                'name' => $template->name,
                'body' => $template->body,
            ])
            ->all();
    }
}
