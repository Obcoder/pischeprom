<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\DTO\FindBuyers\FindBuyersLaunchContext;
use App\Domain\AiSales\Enums\ProductScopeRole;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Services\GoodProductMappingResolver;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindBuyersLaunchContextResolver
{
    public const SOURCE_PRODUCT = 'product';

    public const SOURCE_GOOD = 'good';

    public function __construct(
        private readonly FindBuyersFeatureGuard $features,
        private readonly FindBuyersAuthorizationService $authorization,
        private readonly GoodProductMappingResolver $mappings,
        private readonly FindBuyersDisclosurePreview $disclosure,
        private readonly FindBuyersCriteriaRegistry $criteria,
        private readonly FindBuyersGeographyService $geography,
    ) {}

    public function resolve(User $actor, string $sourceType, int $sourceId, ?int $selectedProductId = null): FindBuyersLaunchContext
    {
        $this->features->ui();
        $this->authorization->authorizeLaunch($actor);

        if (! in_array($sourceType, [self::SOURCE_PRODUCT, self::SOURCE_GOOD], true)) {
            throw ValidationException::withMessages(['source_type' => 'Unsupported Find Buyers launch source.']);
        }

        $source = [];
        $originatingGood = null;
        $productOptions = [];
        $primaryProduct = null;
        $eligible = true;
        $reasonCode = null;
        $message = null;

        if ($sourceType === self::SOURCE_PRODUCT) {
            $primaryProduct = $this->publishedProduct($sourceId);
            $source = ['type' => self::SOURCE_PRODUCT, 'id' => $sourceId, 'label' => $primaryProduct['name']];
            if ($selectedProductId !== null && $selectedProductId !== $sourceId) {
                throw ValidationException::withMessages(['selected_product_id' => 'Product launch scope cannot be substituted.']);
            }
            $productOptions = [$primaryProduct];
        } else {
            $good = DB::table('goods')->select(['id', 'name', 'is_published'])->where('id', $sourceId)->first();
            if (! $good || ! (bool) $good->is_published) {
                throw new NotFoundHttpException('Good is unavailable for Find Buyers.');
            }
            $originatingGood = ['id' => (int) $good->id, 'name' => mb_substr((string) $good->name, 0, 255)];
            $source = ['type' => self::SOURCE_GOOD, 'id' => $sourceId, 'label' => $originatingGood['name']];
            $distinctIds = $this->mappings->distinctProductIds($sourceId);
            $published = $this->publishedProducts($distinctIds);
            $productOptions = array_values($published);

            if ($distinctIds === []) {
                $eligible = false;
                $reasonCode = 'missing_product_mapping';
                $message = 'Поиск покупателей нельзя запустить: Good не связан ни с одним Product. Сначала выполните Product mapping.';
            } elseif (count($distinctIds) === 1) {
                $primaryProduct = $published[$distinctIds[0]] ?? null;
                if ($primaryProduct === null) {
                    $eligible = false;
                    $reasonCode = 'product_unavailable';
                    $message = 'Связанный Product не опубликован или недоступен.';
                }
                if ($selectedProductId !== null && $selectedProductId !== $distinctIds[0]) {
                    throw ValidationException::withMessages(['selected_product_id' => 'Selected Product is not related to this Good.']);
                }
            } else {
                if ($selectedProductId === null) {
                    $eligible = false;
                    $reasonCode = 'product_selection_required';
                    $message = 'Good связан с несколькими Product. Выберите Product явно.';
                } elseif (! in_array($selectedProductId, $distinctIds, true) || ! isset($published[$selectedProductId])) {
                    throw ValidationException::withMessages(['selected_product_id' => 'Selected Product is not an available relation of this Good.']);
                } else {
                    $primaryProduct = $published[$selectedProductId];
                }
            }
        }

        $offerOptions = $primaryProduct ? $this->publishedGoodsForProduct((int) $primaryProduct['id']) : [];
        if ($originatingGood !== null && ! collect($offerOptions)->contains('id', $originatingGood['id'])) {
            $offerOptions[] = $originatingGood;
        }
        $recentJobs = $primaryProduct ? $this->recentJobs((int) $primaryProduct['id']) : [];

        return new FindBuyersLaunchContext(
            $source,
            $primaryProduct,
            $productOptions,
            $originatingGood,
            array_values($offerOptions),
            $recentJobs,
            [
                'available_goods' => count($offerOptions),
                'active_jobs' => count($recentJobs),
            ],
            [
                ...$this->criteria->options(),
                'industries' => $this->industries(),
                'categories' => $this->categories(),
                'limits' => config('ai-sales.find_buyers.limits', []),
                'purpose' => ProspectingPurpose::BuyerDiscovery->value,
                'lane' => 'sales',
                'role_code' => 'prospective_customer',
                'match_type' => 'potential_need',
            ],
            $this->geography->options(),
            ['eligible' => $eligible, 'reason_code' => $reasonCode, 'message' => $message],
            $this->disclosure->build(),
            $this->features->runtimeState(),
        );
    }

    /** @return array{id: int, name: string, english_name: ?string, category: ?string} */
    private function publishedProduct(int $productId): array
    {
        $product = DB::table('products')
            ->leftJoin('categories', function ($join): void {
                $join->on('categories.id', '=', 'products.category_id')
                    ->where('categories.is_published', true);
            })
            ->where('products.id', $productId)
            ->where('products.is_published', true)
            ->select(['products.id', 'products.rus', 'products.eng', 'categories.name as category_name'])
            ->first();
        if (! $product) {
            throw new NotFoundHttpException('Product is unavailable for Find Buyers.');
        }

        return $this->productRow($product);
    }

    /** @param list<int> $ids
     * @return array<int, array{id: int, name: string, english_name: ?string, category: ?string}>
     */
    private function publishedProducts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('products')
            ->leftJoin('categories', function ($join): void {
                $join->on('categories.id', '=', 'products.category_id')
                    ->where('categories.is_published', true);
            })
            ->whereIn('products.id', $ids)->where('products.is_published', true)
            ->select(['products.id', 'products.rus', 'products.eng', 'categories.name as category_name'])
            ->orderBy('products.id')->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->id => $this->productRow($row)])->all();
    }

    /** @return array{id: int, name: string, english_name: ?string, category: ?string} */
    private function productRow(object $product): array
    {
        return [
            'id' => (int) $product->id,
            'name' => mb_substr((string) $product->rus, 0, 255),
            'english_name' => filled($product->eng) ? mb_substr((string) $product->eng, 0, 255) : null,
            'category' => filled($product->category_name) ? mb_substr((string) $product->category_name, 0, 255) : null,
        ];
    }

    /** @return list<array{id: int, name: string}> */
    private function publishedGoodsForProduct(int $productId): array
    {
        return DB::table('goods')->join('good_product', 'good_product.good_id', '=', 'goods.id')
            ->where('good_product.product_id', $productId)->where('goods.is_published', true)
            ->select(['goods.id', 'goods.name'])->distinct()->orderBy('goods.name')->orderBy('goods.id')->limit(25)->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => mb_substr((string) $row->name, 0, 255)])->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentJobs(int $productId): array
    {
        return ProspectingSearchJob::query()->where('purpose', ProspectingPurpose::BuyerDiscovery->value)
            ->whereHas('products', fn ($query) => $query->where('products.id', $productId)
                ->where('prospecting_search_job_products.role', ProductScopeRole::Primary->value))
            ->whereNotIn('status', [ProspectingJobStatus::Cancelled->value, ProspectingJobStatus::Archived->value])
            ->select(['id', 'public_id', 'status', 'safe_objective', 'created_at'])->latest('id')->limit(5)->get()
            ->map(fn (ProspectingSearchJob $job): array => [
                'id' => $job->public_id,
                'status' => $job->status->value,
                'safe_objective' => mb_substr((string) $job->safe_objective, 0, 512),
                'created_at' => $job->created_at?->toISOString(),
            ])->all();
    }

    /** @return list<array{id: int, code: string, name: string}> */
    private function industries(): array
    {
        return DB::table('industries')->select(['id', 'code', 'title'])->orderBy('title')->limit(100)->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'code' => mb_substr((string) $row->code, 0, 20),
                'name' => mb_substr((string) $row->title, 0, 255),
            ])->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function categories(): array
    {
        return DB::table('categories')->select(['id', 'name'])->where('is_published', true)
            ->orderBy('name')->limit(100)->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => mb_substr((string) $row->name, 0, 255)])->all();
    }
}
