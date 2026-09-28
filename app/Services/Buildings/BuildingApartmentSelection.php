<?php

namespace App\Services\Buildings;

use App\Models\Apartment;
use Illuminate\Validation\ValidationException;

class BuildingApartmentSelection
{
    /**
     * Missing map entries leave existing pivot selections intact; null explicitly clears one.
     */
    public function forBuildings(array $buildingIds, array $selections = []): array
    {
        $pivots = array_fill_keys(array_map('intval', $buildingIds), []);
        $apartments = Apartment::query()
            ->whereIn('id', array_filter(array_values($selections), fn ($id) => $id !== null))
            ->sharedLock()
            ->get()
            ->keyBy('id');

        foreach ($selections as $buildingId => $apartmentId) {
            $key = 'building_apartments.'.$buildingId;
            if (! ctype_digit((string) $buildingId) || ! array_key_exists((int) $buildingId, $pivots)) {
                throw ValidationException::withMessages([$key => 'Выберите здание для помещения.']);
            }

            $apartment = $apartments->get($apartmentId);
            if ($apartmentId !== null && (! $apartment || $apartment->building_id !== (int) $buildingId)) {
                throw ValidationException::withMessages([$key => 'Помещение должно принадлежать выбранному зданию.']);
            }

            $pivots[(int) $buildingId] = ['apartment_id' => $apartmentId !== null ? (int) $apartmentId : null];
        }

        return $pivots;
    }
}
