<?php

namespace App\Services\Units;

use App\Models\Email;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use App\Services\Buildings\BuildingApartmentSelection;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class UnitCreator
{
    public function __construct(private readonly BuildingApartmentSelection $apartments) {}

    public function create(array $data): Unit
    {
        return DB::transaction(function () use ($data): Unit {
            $buildingPivots = $this->apartments->forBuildings(
                $data['buildings'] ?? [], $data['building_apartments'] ?? [],
            );
            $unit = Unit::query()->create([
                'name' => trim($data['name']),
                'is_customer' => $data['is_customer'] ?? false,
                'is_supplier' => $data['is_supplier'] ?? false,
            ]);

            $unit->buildings()->sync($buildingPivots);

            foreach (['cities', 'fields', 'labels', 'entities', 'classifications'] as $relation) {
                $unit->{$relation}()->sync(array_values($data[$relation] ?? []));
            }

            $industries = [];
            foreach ($data['industries'] ?? [] as $industryId) {
                $industries[$industryId] = ['is_primary' => (int) $industryId === (int) ($data['primary_industry_id'] ?? 0)];
            }
            $unit->industries()->sync($industries);

            foreach (['emails', 'telephones', 'uris'] as $type) {
                $ids = array_values($data[$type] ?? []);

                foreach (array_unique($data['new_'.$type] ?? []) as $value) {
                    $ids[] = $this->contactId($type, $value);
                }

                $unit->{$type}()->sync(array_values(array_unique($ids)));
            }

            // Object-storage folders are virtual and created on the first upload.
            // Creating a Unit must not depend on an external placeholder write.
            return $unit->load([
                'buildings.city', 'cities', 'fields', 'labels', 'industries',
                'entities:id,name', 'classifications', 'emails', 'telephones', 'uris',
            ]);
        });
    }

    private function contactId(string $type, string $value): int
    {
        if ($type === 'emails') {
            $email = Email::withTrashed()->firstOrCreate(
                ['address' => $value],
                ['source' => 'unit_manual', 'is_active' => true],
            );

            if ($email->trashed()) {
                $email->restore();
                $email->update(['is_active' => true]);
            }

            return $email->id;
        }

        if ($type === 'telephones') {
            // Reuse legacy phone formats without rewriting their existing owners or history.
            $telephone = Telephone::query()
                ->whereIn('number', array_unique([$value, ...PhoneNumber::russianStorageVariants($value)]))
                ->orderByRaw('CASE WHEN number = ? THEN 0 ELSE 1 END', [$value])
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            return ($telephone ?? Telephone::query()->firstOrCreate(['number' => $value]))->id;
        }

        return Uri::query()->firstOrCreate(['address' => $value])->id;
    }
}
