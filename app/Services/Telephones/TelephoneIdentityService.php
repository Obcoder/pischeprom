<?php

namespace App\Services\Telephones;

use App\Models\Entity;
use App\Models\Telephone;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TelephoneIdentityService
{
    /** @var array<string, string> */
    private const FOREIGN_KEYS = [
        'avito_contact_candidates' => 'telephone_id',
        'leads' => 'telephone_id',
        'orders' => 'contact_telephone_id',
        'phone_calls' => 'telephone_id',
    ];

    public function normalize(mixed $number): ?string
    {
        return PhoneNumber::russian($number);
    }

    /** @return array<int, string> */
    public function variants(mixed $number): array
    {
        return PhoneNumber::russianStorageVariants($number);
    }

    /**
     * Read an existing telephone without changing legacy data.
     *
     * @param  array<int, string>  $with
     */
    public function find(mixed $number, array $with = []): ?Telephone
    {
        $canonical = $this->normalize($number);

        if ($canonical === null) {
            return null;
        }

        return Telephone::query()
            ->with($with)
            ->whereIn('number', $this->variants($canonical))
            ->orderByRaw('CASE WHEN number = ? THEN 0 ELSE 1 END', [$canonical])
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  iterable<mixed>  $numbers
     * @param  array<int, string>  $with
     * @return Collection<int, Telephone>
     */
    public function findMany(iterable $numbers, array $with = []): Collection
    {
        $variants = collect($numbers)
            ->flatMap(fn (mixed $number) => $this->variants($number))
            ->unique()
            ->values();

        if ($variants->isEmpty()) {
            return new Collection;
        }

        return Telephone::query()
            ->with($with)
            ->whereIn('number', $variants)
            ->orderBy('id')
            ->get();
    }

    /**
     * Find or create the canonical telephone and fold legacy format duplicates into it.
     */
    public function resolve(mixed $number): ?Telephone
    {
        $canonical = $this->normalize($number);

        if ($canonical === null) {
            return null;
        }

        return DB::transaction(function () use ($canonical): Telephone {
            $telephone = Telephone::query()->firstOrCreate(['number' => $canonical]);
            $duplicates = Telephone::query()
                ->whereIn('number', $this->variants($canonical))
                ->whereKeyNot($telephone->getKey())
                ->lockForUpdate()
                ->get();

            foreach ($duplicates as $duplicate) {
                $this->merge($duplicate, $telephone);
            }

            $this->reconcileEntities($telephone, $canonical);

            return $telephone->fresh();
        });
    }

    private function merge(Telephone $duplicate, Telephone $telephone): void
    {
        $telephone->entities()->syncWithoutDetaching(
            $duplicate->entities()->pluck('entities.id')->all()
        );
        $telephone->units()->syncWithoutDetaching(
            $duplicate->units()->pluck('units.id')->all()
        );

        foreach (self::FOREIGN_KEYS as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->where($column, $duplicate->getKey())
                ->update([$column => $telephone->getKey()]);
        }

        $duplicate->delete();
    }

    private function reconcileEntities(Telephone $telephone, string $canonical): void
    {
        $entities = $telephone->entities()->orderBy('entities.id')->get();
        $placeholders = $entities
            ->filter(fn (Entity $entity) => $this->isPlaceholder($entity, $canonical))
            ->values();
        $namedEntities = $entities
            ->reject(fn (Entity $entity) => $this->isPlaceholder($entity, $canonical))
            ->values();

        if ($placeholders->isEmpty() || $namedEntities->count() !== 1) {
            return;
        }

        $entity = $namedEntities->first();

        foreach ($placeholders as $placeholder) {
            foreach (['phone_calls', 'leads'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)
                    ->where('entity_id', $placeholder->id)
                    ->where('telephone_id', $telephone->id)
                    ->update(['entity_id' => $entity->id]);
            }

            if (Schema::hasTable('avito_chats')) {
                DB::table('avito_chats')
                    ->where('entity_id', $placeholder->id)
                    ->update(['entity_id' => $entity->id]);
            }

            $placeholder->telephones()->detach($telephone->id);
            $this->deleteIfUnreferenced($placeholder);
        }
    }

    private function isPlaceholder(Entity $entity, string $canonical): bool
    {
        return preg_match(
            '/^'.preg_quote('Клиент '.$canonical, '/').'(?: #\d+)?$/u',
            trim((string) $entity->name),
        ) === 1;
    }

    private function deleteIfUnreferenced(Entity $entity): void
    {
        $hasMeaningfulAttributes = collect($entity->getAttributes())
            ->except(['id', 'name', 'created_at', 'updated_at'])
            ->contains(fn (mixed $value) => filled($value));

        if ($hasMeaningfulAttributes) {
            return;
        }

        foreach (Schema::getTableListing(null, false) as $table) {
            if ($table === 'entities') {
                continue;
            }

            $entityColumns = collect(Schema::getColumnListing($table))
                ->filter(fn (string $column) => $column === 'entity_id'
                    || str_ends_with($column, '_entity_id'));

            foreach ($entityColumns as $column) {
                if (DB::table($table)->where($column, $entity->id)->exists()) {
                    return;
                }
            }
        }

        $entity->delete();
    }
}
