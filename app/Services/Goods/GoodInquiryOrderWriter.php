<?php

namespace App\Services\Goods;

use App\Models\Building;
use App\Models\Email;
use App\Models\Entity;
use App\Models\GoodInquiry;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Entities\UserEntityResolver;
use App\Services\Orders\OrderWriter;

class GoodInquiryOrderWriter
{
    public function __construct(
        private readonly OrderWriter $orders,
        private readonly UserEntityResolver $entities,
    ) {}

    public function create(GoodInquiry $inquiry): Order
    {
        // An unverified guest must never edit or claim an existing customer's entity.
        $entity = Entity::query()->create([
            'name' => $inquiry->company ?: $inquiry->customer_name,
        ]);
        $email = Email::withTrashed()->firstOrCreate(['address' => $inquiry->customer_email]);
        $entity->emails()->syncWithoutDetaching([$email->id]);
        $telephone = $this->entities->attachPhone($entity, $inquiry->customer_phone);
        $address = trim(implode(', ', array_filter([$inquiry->delivery_city, $inquiry->delivery_address])));
        $building = $address !== '' ? Building::query()->firstOrCreate(['address' => $address, 'city_id' => null]) : null;

        return $this->orders->save(null, [
            'entity_id' => $entity->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id'),
            'created_by_user_id' => $inquiry->user_id,
            'contact_telephone_id' => $telephone?->id,
            'building_ids' => $building ? [$building->id] : [],
            'currency_code' => $inquiry->currency_code,
            'internal_comment' => implode("\n", array_filter([
                'Заказ с публичной страницы. Требует подтверждения цены, наличия и доставки менеджером.',
                'Заявка: '.$inquiry->number,
                'Контакт: '.$inquiry->customer_name,
                'Email (со слов клиента, не проверен): '.$inquiry->customer_email,
                $inquiry->preferred_contact === 'max' ? 'Ответить в MAX: '.$inquiry->max_contact : 'Ответить по email.',
                $inquiry->comment,
            ])),
            'items' => [[
                'good_id' => $inquiry->good_id,
                'quantity' => $inquiry->quantity,
                'unit_price' => $inquiry->listed_price !== null
                    ? round($inquiry->listed_price * ($inquiry->package_weight ?? 1), 4)
                    : null,
            ]],
        ]);
    }
}
