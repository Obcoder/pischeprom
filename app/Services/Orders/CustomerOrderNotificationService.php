<?php

namespace App\Services\Orders;

use App\Mail\CustomerOrderCreatedMail;
use App\Models\Order;
use App\Services\MaxMessengerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerOrderNotificationService
{
    public function __construct(private readonly MaxMessengerService $maxMessenger) {}

    public function notify(Order $order): bool
    {
        $order->loadMissing([
            'items',
            'entity.emails',
            'entity.telephones',
            'createdBy',
            'contactTelephone',
            'buildings',
        ]);

        $delivered = false;
        $managerEmails = config('services.orders.manager_emails', []);

        if (! empty($managerEmails)) {
            try {
                Mail::to($managerEmails)->send(new CustomerOrderCreatedMail($order));
                $delivered = true;
            } catch (\Throwable $exception) {
                Log::warning('Order email notification failed.', [
                    'order_id' => $order->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $managerMessage = $this->managerMessage($order);

        foreach (config('services.max.manager_chat_ids', []) as $chatId) {
            $delivered = $this->maxMessenger->sendToChat($chatId, $managerMessage) || $delivered;
        }

        if (filled($order->createdBy?->max_chat_id)) {
            $delivered = $this->maxMessenger->sendToChat($order->createdBy->max_chat_id, $this->customerMessage($order)) || $delivered;
        }

        if ($delivered && ! $order->notified_at) {
            $order->forceFill([
                'notified_at' => now(),
            ])->save();
        }

        return $delivered;
    }

    private function managerMessage(Order $order): string
    {
        $lines = [
            "Новый заказ {$order->number}",
            'Клиент: '.$this->customerName($order),
            'Email: '.$this->customerEmail($order),
            'Телефон: '.$this->customerPhone($order),
            'Адрес: '.$this->deliveryAddress($order),
            'Время: '.($order->preferred_delivery_time ?: 'не указано'),
            'Сумма: '.$this->money($order->total_amount, $order->currency_code),
            'Вес: '.$this->weight($order->total_weight),
            "Позиций: {$order->items->count()}",
        ];

        return implode("\n", $lines);
    }

    private function customerMessage(Order $order): string
    {
        return implode("\n", [
            "Заказ {$order->number} создан.",
            'Адрес: '.$this->deliveryAddress($order),
            'Время: '.($order->preferred_delivery_time ?: 'не указано'),
            'Сумма: '.$this->money($order->total_amount, $order->currency_code),
            'Вес: '.$this->weight($order->total_weight),
            'Менеджер ПИЩЕПРОМ-СЕРВЕР скоро начнёт работу с заказом.',
        ]);
    }

    public function customerName(Order $order): string
    {
        return $order->entity?->name
            ?: $order->createdBy?->name
            ?: 'Клиент';
    }

    public function customerEmail(Order $order): string
    {
        return $order->createdBy?->email
            ?: $order->entity?->emails?->first()?->address
            ?: 'не указан';
    }

    public function customerPhone(Order $order): string
    {
        return $order->contactTelephone?->number
            ?: $order->entity?->telephones?->first()?->number
            ?: $order->createdBy?->phone
            ?: 'не указан';
    }

    public function deliveryAddress(Order $order): string
    {
        return $order->buildings
            ->first(fn ($building) => $building->pivot?->role === 'delivery')
            ?->address
            ?: $order->buildings->first()?->address
            ?: 'не указан';
    }

    private function money(float|int|null $amount, ?string $currencyCode): string
    {
        if (! is_numeric($amount) || (float) $amount <= 0) {
            return 'по запросу';
        }

        $currency = $currencyCode === 'RUB' || blank($currencyCode)
            ? '₽'
            : $currencyCode;

        return number_format((float) $amount, 2, ',', ' ').' '.$currency;
    }

    private function weight(float|int|null $weight): string
    {
        if (! is_numeric($weight) || (float) $weight <= 0) {
            return 'уточняется';
        }

        return number_format((float) $weight, 3, ',', ' ').' кг';
    }
}
