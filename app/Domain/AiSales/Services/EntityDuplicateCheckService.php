<?php

namespace App\Domain\AiSales\Services;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Builder;

class EntityDuplicateCheckService
{
    public function candidateIds(array $attributes, ?int $excludeEntityId = null): array
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $inn = trim((string) ($attributes['INN'] ?? ''));
        $ogrn = trim((string) ($attributes['OGRN'] ?? ''));

        if ($name === '' && $inn === '' && $ogrn === '') {
            return [];
        }

        return Entity::query()
            ->without(['buildings', 'classification', 'country'])
            ->select(['id', 'name', 'INN', 'OGRN'])
            ->when($excludeEntityId, fn (Builder $query) => $query->whereKeyNot($excludeEntityId))
            ->where(function (Builder $query) use ($name, $inn, $ogrn): void {
                if ($inn !== '') {
                    $query->orWhere('INN', $inn);
                }
                if ($ogrn !== '') {
                    $query->orWhere('OGRN', $ogrn);
                }
                if ($name !== '') {
                    $query->orWhere('name', $name);
                }
            })
            ->limit(20)
            ->pluck('id')
            ->map(fn (mixed $id) => (int) $id)
            ->all();
    }
}
