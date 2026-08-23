<?php

namespace App\Domain\AiSales\Prospecting;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProductBuyerArchetypePlanner
{
    public const VERSION = 'product-buyer-archetype-planner-v1';

    public function __construct(private readonly BuyerArchetypeRegistry $registry) {}

    /**
     * Product Consumers are observed Units which already consume the Product.
     * Their reviewed industries/classifications may strengthen a code-owned
     * archetype, but never create a new canonical archetype automatically.
     *
     * @param  list<string>  $selectedSegmentCodes
     * @return list<BuyerArchetype>
     */
    public function plan(Product $product, array $selectedSegmentCodes = [], int $limit = 6): array
    {
        $product->loadMissing('category:id,name');
        $signals = $this->signals($product);
        $isFoodContext = $signals->contains(fn (string $signal): bool => collect([
            'пищ', 'food', 'овощ', 'vegetable', 'фрукт', 'fruit', 'мяс', 'meat', 'молоч', 'dairy', 'напит', 'beverage',
        ])->contains(fn (string $needle): bool => str_contains($signal, $needle)));
        $isPlantFoodContext = $signals->contains(fn (string $signal): bool => collect([
            'овощ', 'vegetable', 'фрукт', 'fruit', 'зелень', 'раститель',
        ])->contains(fn (string $needle): bool => str_contains($signal, $needle)));
        $selected = collect($selectedSegmentCodes)
            ->map(fn (string $value): string => str_starts_with($value, 'archetype:') ? substr($value, 10) : $value)
            ->filter()->unique()->values();

        $scored = collect($this->registry->all())->map(function (BuyerArchetype $archetype) use ($signals, $selected, $isFoodContext, $isPlantFoodContext): array {
            $matches = collect($archetype->signals)->filter(
                fn (string $needle): bool => $signals->contains(fn (string $signal): bool => str_contains($signal, mb_strtolower($needle))),
            )->count();
            $explicit = $selected->contains($archetype->code) || $selected->contains($archetype->segmentCode);

            $family = $isFoodContext && in_array($archetype->code, [
                'food_manufacturer', 'ready_meal_manufacturer', 'food_service_operator', 'institutional_catering',
            ], true) ? 3 : 0;
            $plant = $isPlantFoodContext && in_array($archetype->code, [
                'vegetable_processor', 'frozen_food_manufacturer', 'ready_meal_manufacturer', 'catering_factory',
            ], true) ? 5 : 0;

            return ['archetype' => $archetype, 'score' => ($explicit ? 100 : 0) + ($matches * 10) + $family + $plant];
        })->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')->values();

        if ($scored->isEmpty()) {
            $fallbackCodes = ['food_manufacturer', 'food_service_operator', 'institutional_catering', 'horeca_distributor'];
            $scored = collect($fallbackCodes)->map(fn (string $code): array => [
                'archetype' => $this->registry->find($code),
                'score' => 1,
            ])->filter(fn (array $item): bool => $item['archetype'] instanceof BuyerArchetype);
        }

        return $scored->pluck('archetype')->unique('code')->take(max(1, min(10, $limit)))->values()->all();
    }

    /** @return Collection<int, string> */
    private function signals(Product $product): Collection
    {
        $consumerIndustries = DB::table('consumptions')
            ->join('industry_unit', 'industry_unit.unit_id', '=', 'consumptions.unit_id')
            ->join('industries', 'industries.id', '=', 'industry_unit.industry_id')
            ->where('consumptions.product_id', $product->id)
            ->orderByDesc('industry_unit.is_primary')->orderBy('industries.id')
            ->limit(100)->pluck('industries.title');
        $consumerClassifications = DB::table('consumptions')
            ->join('entity_classification_unit', 'entity_classification_unit.unit_id', '=', 'consumptions.unit_id')
            ->join('entity_classifications', 'entity_classifications.id', '=', 'entity_classification_unit.entity_classification_id')
            ->where('consumptions.product_id', $product->id)
            ->orderBy('entity_classifications.id')->limit(100)->pluck('entity_classifications.name');

        return collect([
            $product->rus,
            $product->eng,
            $product->category?->name,
        ])->merge($consumerIndustries)->merge($consumerClassifications)
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => mb_strtolower(trim($value)))
            ->values();
    }
}
