<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'client_phone' => $this->client_phone,
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'telephone' => $this->whenLoaded('telephone', fn () => $this->telephone ? [
                'id' => $this->telephone->id,
                'number' => $this->telephone->number,
            ] : null),

            'entity' => $this->whenLoaded('entity', fn () => $this->entity ? [
                'id' => $this->entity->id,
                'name' => $this->entity->name,
            ] : null),

            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
            ] : null),

            'first_phone_call_id' => $this->first_phone_call_id,
            'last_phone_call_id' => $this->last_phone_call_id,
            'phone_calls_count' => $this->whenCounted('phoneCalls'),
        ];
    }
}
