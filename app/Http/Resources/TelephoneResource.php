<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelephoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'entities' => $this->whenLoaded('entities', function () {
                return $this->entities->map(fn ($entity) => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                ])->values();
            }, []),

            'units' => $this->whenLoaded('units', function () {
                return $this->units->map(fn ($unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                ])->values();
            }, []),
        ];
    }
}
