<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'entity_id' => $this->entity_id,
            'order_status_id' => $this->order_status_id,
            'created_by_user_id' => $this->created_by_user_id,
            'contact_telephone_id' => $this->contact_telephone_id,
            'preferred_delivery_time' => $this->preferred_delivery_time,
            'internal_comment' => $this->internal_comment,
            'total_amount' => $this->total_amount,
            'total_weight' => $this->total_weight,
            'currency_code' => $this->currency_code,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'notified_at' => $this->notified_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'items_count' => $this->whenCounted('items'),

            'status' => $this->whenLoaded('status', fn () => $this->status ? [
                'id' => $this->status->id,
                'code' => $this->status->code,
                'name' => $this->status->name,
                'color' => $this->status->color,
                'is_closed' => $this->status->is_closed,
            ] : null),

            'entity' => $this->whenLoaded('entity', fn () => $this->entity ? [
                'id' => $this->entity->id,
                'name' => $this->entity->name,
                'INN' => $this->entity->INN,
                'units' => $this->entity->relationLoaded('units')
                    ? $this->entity->units->map(fn ($unit) => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                    ])->values()
                    : [],
            ] : null),

            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),

            'contact_telephone' => $this->whenLoaded('contactTelephone', fn () => $this->contactTelephone ? [
                'id' => $this->contactTelephone->id,
                'number' => $this->contactTelephone->number,
            ] : null),

            'buildings' => $this->whenLoaded('buildings', fn () => $this->buildings->map(fn ($building) => [
                'id' => $building->id,
                'address' => $building->address,
                'postcode' => $building->postcode,
                'role' => $building->pivot?->role,
                'position' => $building->pivot?->position,
                'building_type' => $building->buildingType?->name,
                'city' => $building->city ? [
                    'id' => $building->city->id,
                    'name' => $building->city->name,
                    'region' => $building->city->region?->name,
                ] : null,
            ])->values()),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'good_id' => $item->good_id,
                'good_name' => $item->good_name,
                'good_slug' => $item->good_slug,
                'image_url' => $item->image_url,
                'quantity' => $item->quantity,
                'denominator' => $item->denominator,
                'line_weight' => $item->line_weight,
                'unit_price' => $item->price_gross,
                'price_gross' => $item->price_gross,
                'currency_code' => $item->currency_code,
                'total_amount' => $item->line_total,
                'line_total' => $item->line_total,
                'country_name' => $item->country_name,
                'good' => $item->good ? [
                    'id' => $item->good->id,
                    'name' => $item->good->name,
                    'slug' => $item->good->slug,
                ] : null,
            ])->values()),
        ];
    }
}
