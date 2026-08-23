<?php

namespace App\Domain\AiSales\Prospecting;

use App\Models\Industry;
use App\Models\Product;
use App\Models\Segment;
use Illuminate\Support\Collection;

final class BuyerSegmentCatalog
{
    public function __construct(
        private readonly BuyerArchetypeRegistry $archetypes,
        private readonly ProductBuyerArchetypePlanner $planner,
    ) {}

    /**
     * @return Collection<int, array{id: string, name: string, source: string, type: string, recommended: bool}>
     */
    public function options(?Product $product = null, ?string $search = null): Collection
    {
        $recommended = $product
            ? collect($this->planner->plan($product))->pluck('code')
            : collect();
        $search = mb_strtolower(trim((string) $search));
        $escapedSearch = addcslashes($search, '%_\\');
        $options = collect($this->archetypes->all())->map(fn (BuyerArchetype $archetype): array => [
            'id' => 'archetype:'.$archetype->code,
            'name' => $archetype->label,
            'source' => 'code_owned',
            'type' => 'buyer_archetype',
            'recommended' => $recommended->contains($archetype->code),
        ]);
        Industry::query()->select(['id', 'title'])
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$escapedSearch}%"))
            ->orderBy('title')->limit(1000)->get()
            ->reject(fn (Industry $industry): bool => $this->supplierOnly((string) $industry->title))
            ->each(fn (Industry $industry) => $options->push([
                'id' => 'industry:'.$industry->id,
                'name' => $industry->title,
                'source' => 'industries',
                'type' => 'industry',
                'recommended' => false,
            ]));
        Segment::query()->select(['id', 'name'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$escapedSearch}%"))
            ->orderBy('name')->limit(1000)->get()
            ->reject(fn (Segment $segment): bool => $this->supplierOnly((string) $segment->name))
            ->each(fn (Segment $segment) => $options->push([
                'id' => 'segment:'.$segment->id,
                'name' => $segment->name,
                'source' => 'segments',
                'type' => 'segment',
                'recommended' => false,
            ]));

        return $options->when($search !== '', fn (Collection $items): Collection => $items->filter(
            fn (array $item): bool => str_contains(mb_strtolower($item['name']), $search),
        ))->unique('id')->sortBy(fn (array $item): string => sprintf('%d|%s', $item['recommended'] ? 0 : 1, $item['name']))->values();
    }

    /**
     * Hydrates only explicitly selected safe catalogue tokens, regardless of
     * the current autocomplete page.
     *
     * @param  list<string>  $ids
     * @return Collection<int, array{id: string, name: string, source: string, type: string, recommended: bool}>
     */
    public function selectedOptions(array $ids, ?Product $product = null): Collection
    {
        $ids = collect($ids)->filter(fn ($id): bool => is_string($id) && $id !== '')->unique()->take(25)->values();
        $recommended = $product ? collect($this->planner->plan($product))->pluck('code') : collect();
        $industryIds = $ids->filter(fn (string $id): bool => preg_match('/^industry:\d+$/', $id) === 1)
            ->map(fn (string $id): int => (int) substr($id, 9))->values();
        $segmentIds = $ids->filter(fn (string $id): bool => preg_match('/^segment:\d+$/', $id) === 1)
            ->map(fn (string $id): int => (int) substr($id, 8))->values();
        $industries = Industry::query()->whereIn('id', $industryIds)->pluck('title', 'id');
        $segments = Segment::query()->whereIn('id', $segmentIds)->pluck('name', 'id');

        return $ids->map(function (string $id) use ($recommended, $industries, $segments): ?array {
            if (str_starts_with($id, 'archetype:')) {
                $code = substr($id, 10);
                $archetype = $this->archetypes->find($code);

                return $archetype ? [
                    'id' => $id, 'name' => $archetype->label, 'source' => 'code_owned',
                    'type' => 'buyer_archetype', 'recommended' => $recommended->contains($code),
                ] : null;
            }
            if (preg_match('/^industry:(\d+)$/', $id, $match) === 1 && $industries->has((int) $match[1])
                && ! $this->supplierOnly((string) $industries[(int) $match[1]])) {
                return [
                    'id' => $id, 'name' => $industries[(int) $match[1]], 'source' => 'industries',
                    'type' => 'industry', 'recommended' => false,
                ];
            }
            if (preg_match('/^segment:(\d+)$/', $id, $match) === 1 && $segments->has((int) $match[1])
                && ! $this->supplierOnly((string) $segments[(int) $match[1]])) {
                return [
                    'id' => $id, 'name' => $segments[(int) $match[1]], 'source' => 'segments',
                    'type' => 'segment', 'recommended' => false,
                ];
            }

            return null;
        })->filter()->values();
    }

    /** @param list<string> $ids */
    public function assertAllowed(array $ids): void
    {
        $ids = collect($ids)->filter(fn ($id): bool => is_string($id) && $id !== '')->unique()->values();
        if ($ids->count() === 0) {
            return;
        }
        $allowed = $this->selectedOptions($ids->all())->pluck('id');
        if ($ids->diff($allowed)->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'criteria.segments' => 'Buyer segments must come from the protected campaign catalogue.',
            ]);
        }
    }

    /** @param list<string> $ids
     * @return list<string>
     */
    public function labels(array $ids): array
    {
        $byId = $this->selectedOptions($ids)->keyBy('id');

        return collect($ids)->map(fn ($id) => $byId->get((string) $id)['name'] ?? null)
            ->filter()->unique()->values()->all();
    }

    private function supplierOnly(string $label): bool
    {
        $label = mb_strtolower($label);

        return collect(['поставщик', 'продавец сырья', 'supplier-only', 'закупочная поставка'])
            ->contains(fn (string $needle): bool => str_contains($label, $needle));
    }
}
