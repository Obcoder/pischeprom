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
            'assigned_user_id' => $this->assigned_user_id,
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
                'units' => $this->entity->relationLoaded('units')
                    ? $this->entity->units->map(fn ($unit) => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                    ])->values()
                    : [],
                'buildings' => $this->entity->relationLoaded('buildings')
                    ? $this->entity->buildings->map(fn ($building) => [
                        'id' => $building->id,
                        'address' => $building->address,
                        'postcode' => $building->postcode,
                        'city' => $building->city ? [
                            'id' => $building->city->id,
                            'name' => $building->city->name,
                            'region' => $building->city->region ? [
                                'id' => $building->city->region->id,
                                'name' => $building->city->region->name,
                            ] : null,
                        ] : null,
                    ])->values()
                    : [],
            ] : null),

            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
            ] : null),

            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
                'email' => $this->assignedUser->email,
            ] : null),

            'first_phone_call_id' => $this->first_phone_call_id,
            'last_phone_call_id' => $this->last_phone_call_id,
            'first_phone_call' => new PhoneCallResource($this->whenLoaded('firstPhoneCall')),
            'last_phone_call' => new PhoneCallResource($this->whenLoaded('lastPhoneCall')),
            'phone_calls' => PhoneCallResource::collection($this->whenLoaded('phoneCalls')),
            'phone_calls_count' => $this->whenCounted('phoneCalls'),
        ];
    }
}
