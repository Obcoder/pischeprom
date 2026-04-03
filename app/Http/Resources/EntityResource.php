<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'INN' => $this->INN,
            'OGRN' => $this->OGRN,
            'entity_classification_id' => $this->entity_classification_id,
            'country_id' => $this->country_id,

            'classification' => $this->whenLoaded('classification', fn () => [
                'id' => $this->classification?->id,
                'name' => $this->classification?->name,
            ]),

            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country?->id,
                'name' => $this->country?->name,
            ]),

            'buildings' => $this->whenLoaded('buildings', fn () => $this->buildings->map(fn ($item) => [
                'id' => $item->id,
                'address' => $item->address,
            ])),

            'cities' => $this->whenLoaded('cities', fn () => $this->cities->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])),

            'emails' => $this->whenLoaded('emails', fn () => $this->emails->map(fn ($item) => [
                'id' => $item->id,
                'address' => $item->address,
            ])->values()),

            'telephones' => $this->whenLoaded('telephones', fn () => $this->telephones->map(fn ($item) => [
                'id' => $item->id,
                'number' => $item->number ?? $item->telephone ?? $item->phone,
            ])->values()),

            'units' => $this->whenLoaded('units', fn () => $this->units->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])),

            'chats' => $this->whenLoaded('chats', fn () => $this->chats->map(fn ($item) => [
                'id' => $item->id,
                'numbers' => $item->numbers,
            ])),

            'sales_count' => $this->whenCounted('sales', fn () => $this->sales_count),
            'last_purchase_date' => $this->sales_max_date,
        ];
    }
}
