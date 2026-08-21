<?php

namespace App\Domain\AiSales\Prospecting;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\ProductScopeRole;
use App\Domain\AiSales\Services\GoodProductMappingResolver;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Models\Product;
use App\Models\ProspectingSearchJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectingQueryPlanner
{
    public function __construct(
        private readonly ProspectingQueryTemplateRegistry $templates,
        private readonly GoodProductMappingResolver $productMappings,
        private readonly AiToolDlpGuard $dlp,
        private readonly ProductBuyerArchetypePlanner $archetypes,
        private readonly BuyerSegmentCatalog $segments,
    ) {}

    public function plan(ProspectingSearchJob $job): ProspectingQueryPlan
    {
        $products = DB::table('prospecting_search_job_products as scope')
            ->join('products', 'products.id', '=', 'scope.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('scope.prospecting_search_job_id', $job->id)
            ->whereIn('scope.role', [ProductScopeRole::Primary->value, ProductScopeRole::Additional->value])
            ->where('products.is_published', true)
            ->orderByRaw("CASE WHEN scope.role = 'primary' THEN 0 ELSE 1 END")
            ->orderBy('products.id')
            ->distinct()
            ->get([
                'products.id', 'products.rus', 'products.eng', 'scope.role',
                'categories.name as category_name', 'categories.is_published as category_published',
            ])
            ->unique('id')
            ->values();

        if ($products->isEmpty() || ! $products->contains(fn ($product) => $product->role === ProductScopeRole::Primary->value)) {
            throw ValidationException::withMessages(['products' => 'A published primary Product is required for query planning.']);
        }

        $excluded = DB::table('prospecting_search_job_products as scope')
            ->join('products', 'products.id', '=', 'scope.product_id')
            ->where('scope.prospecting_search_job_id', $job->id)
            ->where('scope.role', ProductScopeRole::Exclude->value)
            ->where('products.is_published', true)
            ->orderBy('products.id')
            ->distinct()
            ->get(['products.id', 'products.rus', 'products.eng'])
            ->unique('id')
            ->values();

        $productIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $primaryProductId = (int) $products->firstWhere('role', ProductScopeRole::Primary->value)->id;
        $explicitStage11Selection = $job->launch_source_type === 'good'
            && $job->wizard_version === (string) config('ai-sales.find_buyers.wizard_version', 'stage11-v1')
            && $job->product_mapping_state?->value === 'mapped';
        foreach ($job->goods()->pluck('goods.id')->map(fn ($id): int => (int) $id)->unique() as $goodId) {
            $mappedProductId = $this->productMappings->exactProductId($goodId);
            $explicitlyMapped = $explicitStage11Selection
                && $this->productMappings->stateForExplicitProduct($goodId, $primaryProductId)->value === 'mapped';
            if ((! $explicitlyMapped && $mappedProductId === null)
                || ($mappedProductId !== null && ! in_array($mappedProductId, $productIds, true))) {
                throw ValidationException::withMessages([
                    'originating_goods' => 'Every originating Good must map to exactly one selected Product before planning.',
                ]);
            }
        }

        $geography = $this->geography($job);
        $criteria = $this->criteriaTerms($job->criteria_snapshot ?? []);
        $excludedCriteria = collect((array) (($job->criteria_snapshot ?? [])['excluded_categories'] ?? []))
            ->map(fn ($value): string => $this->cleanTerm((string) $value))
            ->filter()->unique()->take(5)->values()->all();
        $excludedTerms = $excluded->map(fn ($product): string => $this->cleanTerm((string) ($product->rus ?: $product->eng)))
            ->filter()->take(5)->values()->all();
        $scopePayload = [
            'purpose' => $job->purpose->value,
            'locale' => $job->locale,
            'geography' => $geography,
            'criteria' => $criteria,
            'products' => $products->map(fn ($product): array => [
                'id' => (int) $product->id,
                'role' => $product->role,
                'rus' => $this->cleanTerm((string) $product->rus),
                'eng' => $this->cleanTerm((string) $product->eng),
                'category' => $product->category_published ? $this->cleanTerm((string) $product->category_name) : null,
            ])->all(),
            'excluded_product_ids' => $excluded->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ];
        $productScopeHash = AiCanonicalJson::hash($scopePayload);
        $items = [];
        $maxQueries = min(
            (int) $job->max_queries,
            (int) config('ai-sales.prospecting.limits.max_queries', 20),
        );

        if ($job->purpose->value === 'buyer_discovery') {
            $items = $this->buyerMatrix(
                $job, $products, $maxQueries, $geography, $criteria,
                $excludedTerms, $excludedCriteria,
            );
        } else {
            $items = $this->legacyMatrix(
                $job, $products, $maxQueries, $geography, $criteria,
                $excludedTerms, $excludedCriteria,
            );
        }

        if ($items === []) {
            throw ValidationException::withMessages(['products' => 'No bounded Product-first queries could be planned.']);
        }

        $registryHash = $this->templates->registryHash();
        $planHash = AiCanonicalJson::hash([
            'job_id' => $job->id,
            'job_schema_hash' => $job->schema_hash,
            'product_scope_hash' => $productScopeHash,
            'registry_hash' => $registryHash,
            'items' => array_map(fn (ProspectingQueryPlanItem $item): array => $item->hashPayload(), $items),
        ]);

        return new ProspectingQueryPlan($job->id, $productScopeHash, $registryHash, $planHash, $items);
    }

    /** @return list<ProspectingQueryPlanItem> */
    private function buyerMatrix(
        ProspectingSearchJob $job,
        $products,
        int $maxQueries,
        ?string $geography,
        array $criteria,
        array $excludedTerms,
        array $excludedCriteria,
    ): array {
        $matrixGroups = [];
        $selectedSegments = (array) (($job->criteria_snapshot ?? [])['segments'] ?? []);
        $templates = collect($this->templates->forPurpose($job->purpose))->keyBy('intent');
        $discoveryIntents = ['company_discovery', 'manufacturer_discovery', 'production_activity', 'company_discovery', 'institutional_buyer', 'manufacturer_discovery'];
        foreach ($products as $productRow) {
            $product = Product::query()->without(['category', 'manufacturers'])->findOrFail($productRow->id);
            $matrixGroups[] = [$productRow, $this->archetypes->plan($product, $selectedSegments, 6)];
        }

        $matrix = collect();
        foreach ($matrixGroups as [$productRow, $archetypeGroup]) {
            $evidenceCovered = collect();
            foreach (collect($archetypeGroup)->chunk(2) as $chunkIndex => $chunk) {
                foreach ($chunk->values() as $localIndex => $archetype) {
                    $index = ($chunkIndex * 2) + $localIndex;
                    $matrix->push([$productRow, $archetype, $templates[$discoveryIntents[$index % count($discoveryIntents)]]]);
                }
                $evidenceArchetype = $chunk->values()[$chunkIndex % $chunk->count()];
                $evidenceIntent = $chunkIndex % 2 === 0 ? 'product_usage_evidence' : 'procurement_evidence';
                $matrix->push([$productRow, $evidenceArchetype, $templates[$evidenceIntent]]);
                $evidenceCovered->push($evidenceArchetype->code);
            }
            foreach ($archetypeGroup as $index => $archetype) {
                if ($evidenceCovered->contains($archetype->code)) {
                    continue;
                }
                $intent = $index % 2 === 0 ? 'product_usage_evidence' : 'procurement_evidence';
                $matrix->push([$productRow, $archetype, $templates[$intent]]);
            }
            foreach ($archetypeGroup as $archetype) {
                $matrix->push([$productRow, $archetype, $templates['institutional_buyer']]);
            }
        }

        $items = [];
        foreach ($matrix as [$product, $archetype, $template]) {
            if (count($items) >= $maxQueries) {
                break;
            }
            $name = $this->cleanTerm((string) ($product->rus ?: $product->eng));
            $phraseIndex = (count($items) + (int) $product->id) % count($archetype->discoveryPhrases);
            $parts = [$archetype->discoveryPhrases[$phraseIndex], ...$template['terms']];
            if (in_array($template['intent'], ['product_usage_evidence', 'procurement_evidence'], true) && $name !== '') {
                $parts[] = '"'.$name.'"';
            }
            if ($geography !== null) {
                $parts[] = $geography;
            }
            $parts = [...$parts, ...$criteria];
            foreach ($excludedTerms as $term) {
                $parts[] = '-"'.$term.'"';
            }
            foreach ($excludedCriteria as $term) {
                $parts[] = '-"'.$term.'"';
            }
            $ownedTemplate = [...$template, 'archetype' => $archetype->hashPayload()];
            $items[] = $this->item($job, $product, $ownedTemplate, $parts, $geography, $archetype->code.':'.$template['intent'], count($items));
        }

        return $items;
    }

    /** @return list<ProspectingQueryPlanItem> */
    private function legacyMatrix(
        ProspectingSearchJob $job,
        $products,
        int $maxQueries,
        ?string $geography,
        array $criteria,
        array $excludedTerms,
        array $excludedCriteria,
    ): array {
        $items = [];
        foreach ($products as $product) {
            foreach ($this->templates->forPurpose($job->purpose) as $template) {
                if (count($items) >= $maxQueries) {
                    break 2;
                }
                $preferred = $template['name_source'] === 'eng' ? $product->eng : $product->rus;
                $name = $this->cleanTerm((string) ($preferred ?: $product->rus ?: $product->eng));
                if ($name === '') {
                    continue;
                }
                $parts = ['"'.$name.'"', ...$template['terms'], ...$criteria];
                if ($geography !== null) {
                    $parts[] = $geography;
                }
                foreach ($excludedTerms as $term) {
                    $parts[] = '-"'.$term.'"';
                }
                foreach ($excludedCriteria as $term) {
                    $parts[] = '-"'.$term.'"';
                }
                $items[] = $this->item($job, $product, $template, $parts, $geography, $template['intent'], count($items));
            }
        }

        return $items;
    }

    private function item(
        ProspectingSearchJob $job,
        object $product,
        array $template,
        array $parts,
        ?string $geography,
        string $industryIntent,
        int $sequence,
    ): ProspectingQueryPlanItem {
        $queryText = mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))) ?? ''), 0, 512);
        $this->dlp->assertPayloadSafe(['public_search_query' => $queryText], AiProcessingContour::ExternalSanitized, $job->lane);
        $templateHash = $this->templates->templateHash($template);
        $queryHash = AiCanonicalJson::hash([
            'query' => mb_strtolower($queryText),
            'language' => $job->locale,
            'geography' => $geography,
            'product_id' => (int) $product->id,
            'template_hash' => $templateHash,
        ]);

        return new ProspectingQueryPlanItem(
            $sequence + 1,
            (int) $product->id,
            $template['code'],
            $template['version'],
            $templateHash,
            $queryText,
            $queryHash,
            $job->locale,
            $geography,
            $industryIntent,
        );
    }

    private function geography(ProspectingSearchJob $job): ?string
    {
        $value = null;
        if ($job->city_id) {
            $value = DB::table('cities')->where('id', $job->city_id)->value('name');
        } elseif ($job->region_id) {
            $value = DB::table('regions')->where('id', $job->region_id)->value('name');
        } elseif ($job->country_id) {
            $value = DB::table('countries')->where('id', $job->country_id)->value('name');
        }

        $clean = $this->cleanTerm((string) $value);

        return $clean !== '' ? mb_substr($clean, 0, 120) : null;
    }

    /** @return list<string> */
    private function criteriaTerms(array $criteria): array
    {
        $segmentValues = (array) ($criteria['segments'] ?? []);
        $resolvedSegments = $this->segments->labels(array_values(array_filter($segmentValues, 'is_string')));

        return collect($resolvedSegments)
            ->merge(collect(['segments', 'industries', 'categories'])
                ->flatMap(fn (string $key) => (array) ($criteria[$key] ?? []))
                ->reject(fn ($value): bool => is_string($value) && preg_match('/^(archetype|industry|segment):/', $value) === 1))
            ->map(fn ($value): string => $this->cleanTerm((string) $value))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function cleanTerm(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $value = preg_replace('/[^\pL\pN .,+&()\/-]/u', '', $value) ?? '';

        return mb_substr(trim($value), 0, 120);
    }
}
