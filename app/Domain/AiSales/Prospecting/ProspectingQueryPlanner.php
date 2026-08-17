<?php

namespace App\Domain\AiSales\Prospecting;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\ProductScopeRole;
use App\Domain\AiSales\Services\GoodProductMappingResolver;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Models\ProspectingSearchJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectingQueryPlanner
{
    public function __construct(
        private readonly ProspectingQueryTemplateRegistry $templates,
        private readonly GoodProductMappingResolver $productMappings,
        private readonly AiToolDlpGuard $dlp,
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
        foreach ($job->goods()->pluck('goods.id')->map(fn ($id): int => (int) $id)->unique() as $goodId) {
            $mappedProductId = $this->productMappings->exactProductId($goodId);
            if ($mappedProductId === null || ! in_array($mappedProductId, $productIds, true)) {
                throw ValidationException::withMessages([
                    'originating_goods' => 'Every originating Good must map to exactly one selected Product before planning.',
                ]);
            }
        }

        $geography = $this->geography($job);
        $criteria = $this->criteriaTerms($job->criteria_snapshot ?? []);
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
                $parts = ['"'.$name.'"', ...$template['terms']];
                if ($product->category_published && filled($product->category_name)) {
                    $parts[] = $this->cleanTerm((string) $product->category_name);
                }
                array_push($parts, ...$criteria);
                if ($geography !== null) {
                    $parts[] = $geography;
                }
                foreach ($excludedTerms as $excludedTerm) {
                    $parts[] = '-"'.$excludedTerm.'"';
                }
                $queryText = mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))) ?? ''), 0, 512);
                $this->dlp->assertPayloadSafe(
                    ['public_search_query' => $queryText],
                    AiProcessingContour::ExternalSanitized,
                    $job->lane,
                );
                $templateHash = $this->templates->templateHash($template);
                $queryHash = AiCanonicalJson::hash([
                    'query' => mb_strtolower($queryText),
                    'language' => $job->locale,
                    'geography' => $geography,
                    'product_id' => (int) $product->id,
                    'template_hash' => $templateHash,
                ]);
                $items[] = new ProspectingQueryPlanItem(
                    count($items) + 1,
                    (int) $product->id,
                    $template['code'],
                    $template['version'],
                    $templateHash,
                    $queryText,
                    $queryHash,
                    $job->locale,
                    $geography,
                    $template['intent'],
                );
            }
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
        return collect(['segments', 'industries', 'categories'])
            ->flatMap(fn (string $key) => (array) ($criteria[$key] ?? []))
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
