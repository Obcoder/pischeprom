<?php

namespace App\Services\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessageTemplate;
use App\Models\Building;
use App\Models\Good;
use App\Models\Order;
use App\Models\Telephone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AvitoMessageTemplateService
{
    public const MESSAGE_LIMIT = 1000;

    public function __construct(
        private readonly AvitoMessengerService $messenger,
        private readonly AvitoCrmOutboundService $outbound,
    ) {}

    /** @return array<int, array{key: string, label: string, group: string}> */
    public function variables(): array
    {
        return [
            ['key' => 'client_name', 'label' => 'Имя / название клиента', 'group' => 'Клиент'],
            ['key' => 'client_full_name', 'label' => 'Полное имя клиента', 'group' => 'Клиент'],
            ['key' => 'peer_name', 'label' => 'Имя собеседника Avito', 'group' => 'Клиент'],
            ['key' => 'client_phone', 'label' => 'Телефон клиента', 'group' => 'Клиент'],
            ['key' => 'client_address', 'label' => 'Адрес клиента', 'group' => 'Клиент'],
            ['key' => 'order_number', 'label' => 'Номер заказа', 'group' => 'Заказ'],
            ['key' => 'order_status', 'label' => 'Статус заказа', 'group' => 'Заказ'],
            ['key' => 'order_total', 'label' => 'Сумма заказа', 'group' => 'Заказ'],
            ['key' => 'order_currency', 'label' => 'Валюта заказа', 'group' => 'Заказ'],
            ['key' => 'order_items', 'label' => 'Состав заказа', 'group' => 'Заказ'],
            ['key' => 'delivery_address', 'label' => 'Адрес доставки', 'group' => 'Заказ'],
            ['key' => 'preferred_delivery_time', 'label' => 'Желаемое время доставки', 'group' => 'Заказ'],
            ['key' => 'good_name', 'label' => 'Название товара', 'group' => 'Товар'],
            ['key' => 'good_description', 'label' => 'Описание товара', 'group' => 'Товар'],
            ['key' => 'good_price', 'label' => 'Цена товара', 'group' => 'Товар'],
            ['key' => 'good_currency', 'label' => 'Валюта цены', 'group' => 'Товар'],
            ['key' => 'good_stock', 'label' => 'Наличие товара', 'group' => 'Товар'],
            ['key' => 'good_url', 'label' => 'Ссылка на товар', 'group' => 'Товар'],
            ['key' => 'context_title', 'label' => 'Название объявления Avito', 'group' => 'Чат'],
            ['key' => 'context_url', 'label' => 'Ссылка объявления Avito', 'group' => 'Чат'],
            ['key' => 'today', 'label' => 'Текущая дата', 'group' => 'Системные'],
        ];
    }

    public function templatePayload(AvitoMessageTemplate $template): array
    {
        return [
            'id' => $template->id,
            'system_key' => $template->system_key,
            'name' => $template->name,
            'category' => $template->category,
            'category_label' => AvitoMessageTemplate::CATEGORIES[$template->category] ?? $template->category,
            'body' => $template->body,
            'placeholders' => $this->placeholders($template->body),
            'is_active' => (bool) $template->is_active,
            'is_favorite' => (bool) $template->is_favorite,
            'sort_order' => (int) $template->sort_order,
            'usage_count' => (int) $template->usage_count,
            'last_used_at' => $template->last_used_at,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }

    /**
     * @return array{
     *     text: string,
     *     length: int,
     *     within_limit: bool,
     *     unresolved: array<int, string>,
     *     values: array<string, string|null>,
     *     context: array<string, int|null>
     * }
     */
    public function render(AvitoMessageTemplate $template, AvitoChat $chat, array $context = []): array
    {
        $chat->loadMissing([
            'entity.telephones:id,number',
            'entity.buildings.city:id,name',
        ]);
        $entity = $chat->entity;
        $order = $this->resolveOrder($chat, $context['order_id'] ?? null);
        $telephone = $this->resolveTelephone($entity?->telephones, $context['telephone_id'] ?? null);
        $building = $this->resolveBuilding($entity?->buildings, $context['building_id'] ?? null);
        $good = $this->resolveGood($context['good_id'] ?? null, $order);
        $goodPayload = $good ? $this->outbound->goodPayload($good) : null;
        $price = collect($goodPayload['prices'] ?? [])->first();
        $orderBuilding = $order?->buildings->first();
        $clientAddress = $this->buildingLabel($building);
        $deliveryAddress = $this->buildingLabel($orderBuilding ?: $building);

        $values = [
            'client_name' => $entity?->name ?: $chat->peer_name ?: $chat->title,
            'client_full_name' => $entity?->full_name ?: $entity?->name,
            'peer_name' => $chat->peer_name ?: $entity?->name,
            'client_phone' => $telephone?->number,
            'client_address' => $clientAddress,
            'order_number' => $order?->number,
            'order_status' => $order?->status?->name,
            'order_total' => $order?->total_amount !== null ? $this->number((float) $order->total_amount) : null,
            'order_currency' => $order?->currency_code,
            'order_items' => $order ? $this->orderItems($order) : null,
            'delivery_address' => $deliveryAddress,
            'preferred_delivery_time' => $order?->preferred_delivery_time,
            'good_name' => $goodPayload['name'] ?? null,
            'good_description' => $goodPayload['description'] ?? null,
            'good_price' => isset($price['amount']) ? $this->number((float) $price['amount']) : null,
            'good_currency' => $price['currency_code'] ?? null,
            'good_stock' => $this->availabilityLabel($goodPayload['availability']['status'] ?? null),
            'good_url' => $goodPayload['public_url'] ?? null,
            'context_title' => $chat->title,
            'context_url' => $chat->context_url,
            'today' => now()->format('d.m.Y'),
        ];
        $text = preg_replace_callback(
            '/{{\s*([a-z0-9_]+)\s*}}/iu',
            function (array $match) use ($values): string {
                $value = $values[$match[1]] ?? null;

                return $value !== null && $value !== '' ? (string) $value : $match[0];
            },
            $template->body,
        ) ?? $template->body;
        $unresolved = $this->placeholders($text);

        return [
            'text' => trim($text),
            'length' => mb_strlen(trim($text)),
            'within_limit' => mb_strlen(trim($text)) <= self::MESSAGE_LIMIT,
            'unresolved' => $unresolved,
            'values' => $values,
            'context' => [
                'order_id' => $order?->id,
                'good_id' => $good?->id,
                'telephone_id' => $telephone?->id,
                'building_id' => $building?->id,
            ],
        ];
    }

    /** @return array{message: AvitoMessage, preview: array} */
    public function send(AvitoMessageTemplate $template, AvitoChat $chat, array $context = []): array
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'template' => 'Неактивный шаблон нельзя отправить.',
            ]);
        }

        $preview = $this->render($template, $chat, $context);
        if ($preview['unresolved'] !== []) {
            throw ValidationException::withMessages([
                'template' => 'Заполните контекст для переменных: '.collect($preview['unresolved'])
                    ->map(fn (string $variable) => '{{'.$variable.'}}')
                    ->implode(', '),
            ]);
        }
        if ($preview['text'] === '' || ! $preview['within_limit']) {
            throw ValidationException::withMessages([
                'template' => 'Сообщение должно содержать от 1 до '.self::MESSAGE_LIMIT.' символов.',
            ]);
        }

        $message = $this->messenger->sendText($chat, $preview['text']);
        $this->recordUsage($template, $chat, $message, $preview['text'], [
            ...$preview['context'],
            'mode' => 'direct',
        ], 'direct');

        return compact('message', 'preview');
    }

    public function recordUsage(
        AvitoMessageTemplate $template,
        AvitoChat $chat,
        AvitoMessage $message,
        string $renderedBody,
        array $context = [],
        string $mode = 'composer',
    ): void {
        $sentAt = now();

        try {
            DB::transaction(function () use ($template, $chat, $message, $renderedBody, $context, $mode, $sentAt): void {
                $template->usages()->create([
                    'avito_chat_id' => $chat->id,
                    'avito_message_id' => $message->id,
                    'mode' => $mode,
                    'rendered_body' => $renderedBody,
                    'context' => [
                        'template_name' => $template->name,
                        ...$context,
                    ],
                    'sent_at' => $sentAt,
                ]);
                AvitoMessageTemplate::query()
                    ->whereKey($template->id)
                    ->increment('usage_count', 1, [
                        'last_used_at' => $sentAt,
                        'updated_at' => $sentAt,
                    ]);
            });
        } catch (Throwable $exception) {
            // The message is already accepted by Avito; do not invite a duplicate retry because analytics failed.
            report($exception);
        }
    }

    /** @return array<int, string> */
    public function placeholders(string $body): array
    {
        preg_match_all('/{{\s*([a-z0-9_]+)\s*}}/iu', $body, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($key) => mb_strtolower((string) $key))
            ->unique()
            ->values()
            ->all();
    }

    private function resolveOrder(AvitoChat $chat, mixed $orderId): ?Order
    {
        $query = Order::query()
            ->with(['status:id,name', 'items', 'buildings.city:id,name', 'contactTelephone:id,number'])
            ->whereHas('avitoChats', fn (Builder $builder) => $builder->where('avito_chats.id', $chat->id));
        $order = filled($orderId)
            ? (clone $query)->whereKey((int) $orderId)->first()
            : $query->latest('submitted_at')->latest('id')->first();

        if (filled($orderId) && ! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Заказ не связан с выбранным чатом Avito.',
            ]);
        }

        return $order;
    }

    private function resolveTelephone(mixed $telephones, mixed $telephoneId): ?Telephone
    {
        $telephone = filled($telephoneId)
            ? $telephones?->firstWhere('id', (int) $telephoneId)
            : $telephones?->first();

        if (filled($telephoneId) && ! $telephone) {
            throw ValidationException::withMessages([
                'telephone_id' => 'Телефон не принадлежит клиенту этого чата.',
            ]);
        }

        return $telephone;
    }

    private function resolveBuilding(mixed $buildings, mixed $buildingId): ?Building
    {
        $building = filled($buildingId)
            ? $buildings?->firstWhere('id', (int) $buildingId)
            : $buildings?->first();

        if (filled($buildingId) && ! $building) {
            throw ValidationException::withMessages([
                'building_id' => 'Адрес не принадлежит клиенту этого чата.',
            ]);
        }

        return $building;
    }

    private function resolveGood(mixed $goodId, ?Order $order): ?Good
    {
        $resolvedId = filled($goodId)
            ? (int) $goodId
            : $order?->items->first(fn ($item) => $item->good_id)?->good_id;

        return $resolvedId ? Good::query()->find($resolvedId) : null;
    }

    private function buildingLabel(?Building $building): ?string
    {
        if (! $building) {
            return null;
        }

        return collect([$building->postcode, $building->city?->name, $building->address])
            ->filter()
            ->implode(', ');
    }

    private function orderItems(Order $order): ?string
    {
        if ($order->items->isEmpty()) {
            return null;
        }

        return $order->items->map(function ($item): string {
            $line = '• '.$item->good_name.' — '.$this->number((float) $item->quantity);
            if ($item->price_gross !== null) {
                $line .= ' × '.$this->number((float) $item->price_gross).' '.$item->currency_code;
            }

            return $line;
        })->implode("\n");
    }

    private function availabilityLabel(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return match ($status) {
            'in_stock' => 'в наличии',
            'out_of_stock' => 'нет в наличии',
            default => 'под заказ',
        };
    }

    private function number(float $value): string
    {
        $precision = abs($value - round($value)) < 0.000001 ? 0 : 2;

        return number_format($value, $precision, ',', ' ');
    }
}
