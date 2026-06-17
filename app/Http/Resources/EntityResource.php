<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $includeUnitDetails = $request->routeIs('entities.show', 'entities.store', 'entities.update');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'full_name' => $this->full_name,
            'INN' => $this->INN,
            'KPP' => $this->KPP,
            'OGRN' => $this->OGRN,
            'legal_address' => $this->legal_address,
            'entity_classification_id' => $this->entity_classification_id,
            'country_id' => $this->country_id,
            'dadata_raw' => $this->dadata_raw,
            'dadata_loaded_at' => $this->dadata_loaded_at,

            'cities' => $this->whenLoaded('cities', fn () => $this->cities->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])),

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
                'city_id' => $item->city_id,
                'address' => $item->address,
                'postcode' => $item->postcode,
                'city' => [
                    'id' => $item->city?->id,
                    'name' => $item->city?->name,
                ],
            ])),

            'emails' => $this->whenLoaded('emails', fn () => $this->emails->map(fn ($item) => [
                'id' => $item->id,
                'address' => $item->address,
                'name' => $item->name,
                'domain' => $item->domain,
                'comment' => $item->comment,
                'source' => $item->source,
                'is_active' => $item->is_active,
                'verified_at' => $item->verified_at,
                'last_seen_at' => $item->last_seen_at,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ])->values()),

            'telephones' => $this->whenLoaded('telephones', fn () => $this->telephones->map(fn ($item) => [
                'id' => $item->id,
                'number' => $item->number ?? $item->telephone ?? $item->phone,
            ])->values()),

            'units' => $this->whenLoaded('units', fn () => $this->units
                ->map(fn ($item) => $this->unitPayload($item, $includeUnitDetails))
                ->values()),

            'chats' => $this->whenLoaded('chats', fn () => $this->chats->map(fn ($item) => [
                'id' => $item->id,
                'numbers' => $item->numbers,
            ])),

            'sales_count' => $this->whenCounted('sales', fn () => $this->sales_count),
            'last_purchase_date' => $this->sales_max_date,
        ];
    }

    private function unitPayload($unit, bool $includeDetails): array
    {
        $payload = [
            'id' => $unit->id,
            'name' => $unit->name,
            'is_customer' => (bool) $unit->is_customer,
            'is_supplier' => (bool) $unit->is_supplier,
        ];

        if (! $includeDetails) {
            return $payload;
        }

        return [
            ...$payload,
            'created_at' => $unit->created_at?->toISOString(),
            'updated_at' => $unit->updated_at?->toISOString(),
            'entities_count' => $unit->entities_count ?? null,
            'emails_count' => $unit->emails_count ?? null,
            'buildings_count' => $unit->buildings_count ?? null,
            'cities_count' => $unit->cities_count ?? null,
            'labels' => $this->mapLoadedRelation($unit, 'labels', fn ($label) => [
                'id' => $label->id,
                'name' => $label->name,
            ]),
            'fields' => $this->mapLoadedRelation($unit, 'fields', fn ($field) => [
                'id' => $field->id,
                'name' => $field->name ?? $field->title,
                'title' => $field->title,
            ]),
            'telephones' => $this->mapLoadedRelation($unit, 'telephones', fn ($telephone) => [
                'id' => $telephone->id,
                'number' => $telephone->number ?? $telephone->telephone ?? $telephone->phone,
            ]),
            'uris' => $this->mapLoadedRelation($unit, 'uris', fn ($uri) => [
                'id' => $uri->id,
                'address' => $uri->address,
            ]),
            'cities' => $this->mapLoadedRelation($unit, 'cities', fn ($city) => [
                'id' => $city->id,
                'name' => $city->name,
            ]),
            'buildings' => $this->mapLoadedRelation($unit, 'buildings', fn ($building) => [
                'id' => $building->id,
                'address' => $building->address,
                'city' => $building->city ? [
                    'id' => $building->city->id,
                    'name' => $building->city->name,
                ] : null,
            ]),
            'industries' => $this->mapLoadedRelation($unit, 'industries', fn ($industry) => [
                'id' => $industry->id,
                'name' => $industry->name,
                'code' => $industry->code,
            ]),
            'emails' => $this->mapLoadedRelation($unit, 'emails', fn ($email) => [
                'id' => $email->id,
                'address' => $email->address,
                'name' => $email->name,
            ]),
        ];
    }

    private function mapLoadedRelation($model, string $relation, callable $mapper): array
    {
        if (! method_exists($model, 'relationLoaded') || ! $model->relationLoaded($relation)) {
            return [];
        }

        return $model->{$relation}
            ->map($mapper)
            ->values()
            ->all();
    }
}
