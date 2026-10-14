<?php

namespace App\Services\Buildings;

use App\Models\Apartment;
use App\Models\Entity;

class ApartmentEntityLinks
{
    /** Preserve explicit room selections made by the older delivery-address tools. */
    public function remember(Entity $entity, array $pivots): void
    {
        foreach ($pivots as $buildingId => $pivot) {
            if (empty($pivot['apartment_id'])) {
                continue;
            }
            $apartment = Apartment::query()->whereKey($pivot['apartment_id'])->where('building_id', $buildingId)->lockForUpdate()->firstOrFail();
            $apartment->entities()->syncWithoutDetaching([$entity->id]);
        }
    }
}
