<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhoneCallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'provider_call_id' => $this->provider_call_id,
            'event_type' => $this->event_type,
            'direction' => $this->direction,
            'status' => $this->status,
            'client_phone' => $this->client_phone,
            'employee_user' => $this->employee_user,
            'employee_extension' => $this->employee_extension,
            'employee_phone' => $this->employee_phone,
            'group_name' => $this->group_name,
            'diversion_phone' => $this->diversion_phone,
            'started_at' => $this->started_at?->toISOString(),
            'answered_at' => $this->answered_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'duration_seconds' => $this->duration_seconds,
            'wait_seconds' => $this->wait_seconds,
            'recording_url' => $this->recording_url,
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

            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id,
                'title' => $this->lead->title,
                'status' => $this->lead->status,
            ] : null),
        ];
    }
}
