<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Prospecting\BuyerSegmentCatalog;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiSales\BuyerSegmentOptionResource;
use App\Http\Resources\AiSales\GeographyOptionResource;
use App\Http\Resources\AiSales\ProductOptionResource;
use App\Models\City;
use App\Models\Country;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingSearchJob;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProspectingCatalogController extends Controller
{
    public function products(Request $request, ProspectingFeatureGuard $features): JsonResponse
    {
        $features->jobs();
        Gate::authorize('viewAny', ProspectingSearchJob::class);
        $validated = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'ids' => ['nullable', 'array', 'max:25'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ])->validate();
        $search = addcslashes(trim((string) ($validated['search'] ?? '')), '%_\\');
        $query = Product::query()->without(['category', 'manufacturers'])
            ->leftJoin('categories', function ($join): void {
                $join->on('categories.id', '=', 'products.category_id')
                    ->where('categories.is_published', true);
            })
            ->where('products.is_published', true)
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                foreach (Product::TRANSLATION_COLUMNS as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $nested->{$method}('products.'.$column, 'like', "%{$search}%");
                }
            }))
            ->select(['products.id', 'products.rus', 'products.eng', 'categories.name as category_name'])
            ->orderBy('products.rus')->orderBy('products.id');
        $perPage = (int) ($validated['per_page'] ?? $validated['limit'] ?? 25);
        $products = $query->paginate($perPage, ['*'], 'page', (int) ($validated['page'] ?? 1));
        $selected = empty($validated['ids']) ? collect() : Product::query()->without(['category', 'manufacturers'])
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_published', true)->whereIn('products.id', $validated['ids'])
            ->select(['products.id', 'products.rus', 'products.eng', 'categories.name as category_name'])
            ->orderBy('products.rus')->get();

        return response()->json([
            'data' => ProductOptionResource::collection($products->getCollection())->resolve($request),
            'selected' => ProductOptionResource::collection($selected)->resolve($request),
            'meta' => $this->meta($products),
        ]);
    }

    public function countries(Request $request, ProspectingFeatureGuard $features): JsonResponse
    {
        $this->authorizeCatalogue($features);
        $validated = $this->optionInput($request, ['id' => ['nullable', 'integer', 'exists:countries,id']]);
        $query = Country::query()->select(['id', 'name'])->when(isset($validated['id']), fn ($query) => $query->whereKey($validated['id']))
            ->when($validated['search'] !== '', fn ($query) => $query
                ->where('name', 'like', '%'.addcslashes($validated['search'], '%_\\').'%'))
            ->orderBy('name')->orderBy('id');

        return $this->optionResponse($request, $query, $validated);
    }

    public function regions(Request $request, ProspectingFeatureGuard $features): JsonResponse
    {
        $this->authorizeCatalogue($features);
        $validated = $this->optionInput($request, [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'id' => ['nullable', 'integer', 'exists:regions,id'],
        ]);
        $query = Region::query()->without('country')->select(['id', 'name'])->where('country_id', $validated['country_id'])
            ->when(isset($validated['id']), fn ($query) => $query->whereKey($validated['id']))
            ->when($validated['search'] !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($validated['search'], '%_\\').'%'))
            ->orderBy('name')->orderBy('id');

        return $this->optionResponse($request, $query, $validated);
    }

    public function cities(Request $request, ProspectingFeatureGuard $features): JsonResponse
    {
        $this->authorizeCatalogue($features);
        $validated = $this->optionInput($request, [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'id' => ['nullable', 'integer', 'exists:cities,id'],
        ]);
        abort_unless(Region::query()->whereKey($validated['region_id'])->where('country_id', $validated['country_id'])->exists(), 422);
        $query = City::query()->without('region')->select(['id', 'name'])->where('region_id', $validated['region_id'])
            ->when(isset($validated['id']), fn ($query) => $query->whereKey($validated['id']))
            ->when($validated['search'] !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($validated['search'], '%_\\').'%'))
            ->orderBy('name')->orderBy('id');

        return $this->optionResponse($request, $query, $validated);
    }

    public function segments(Request $request, ProspectingFeatureGuard $features, BuyerSegmentCatalog $catalog): JsonResponse
    {
        $this->authorizeCatalogue($features);
        $validated = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'recommended_only' => ['nullable', 'boolean'],
            'ids' => ['nullable', 'array', 'max:25'],
            'ids.*' => ['string', 'distinct', 'max:80', 'regex:/^(archetype:[a-z0-9_]+|industry:\d+|segment:\d+)$/'],
        ])->validate();
        $product = isset($validated['product_id']) ? Product::query()->without(['category', 'manufacturers'])
            ->where('is_published', true)->findOrFail($validated['product_id']) : null;
        $items = $catalog->options($product, $validated['search'] ?? null)
            ->when((bool) ($validated['recommended_only'] ?? false), fn ($values) => $values->where('recommended', true))
            ->values();
        $perPage = (int) ($validated['per_page'] ?? 25);
        $page = (int) ($validated['page'] ?? 1);
        $slice = $items->forPage($page, $perPage)->values();
        $selected = $catalog->selectedOptions((array) ($validated['ids'] ?? []), $product);

        return response()->json([
            'data' => BuyerSegmentOptionResource::collection($slice)->resolve($request),
            'selected' => BuyerSegmentOptionResource::collection($selected)->resolve($request),
            'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($items->count() / $perPage)), 'per_page' => $perPage, 'total' => $items->count()],
        ]);
    }

    public function goods(Request $request, int $productId, ProspectingFeatureGuard $features): JsonResponse
    {
        $features->jobs();
        Gate::authorize('viewAny', ProspectingSearchJob::class);
        $validated = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();
        $search = addcslashes(trim((string) ($validated['search'] ?? '')), '%_\\');
        $goods = Good::query()
            ->join('good_product', 'good_product.good_id', '=', 'goods.id')
            ->join('products', 'products.id', '=', 'good_product.product_id')
            ->where('products.id', $productId)
            ->where('products.is_published', true)
            ->where('goods.is_published', true)
            ->when($search !== '', fn ($query) => $query->where('goods.name', 'like', "%{$search}%"))
            ->select(['goods.id', 'goods.name'])->distinct()
            ->orderBy('goods.name')->orderBy('goods.id')
            ->limit($validated['limit'] ?? 50)->get()
            ->map(fn ($good) => ['id' => (int) $good->id, 'name' => $good->name]);

        return response()->json(['data' => $goods]);
    }

    private function authorizeCatalogue(ProspectingFeatureGuard $features): void
    {
        $features->jobs();
        Gate::authorize('viewAny', ProspectingSearchJob::class);
    }

    private function optionInput(Request $request, array $extra): array
    {
        $validated = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ...$extra,
        ])->validate();
        $validated['search'] = trim((string) ($validated['search'] ?? ''));
        $validated['page'] = (int) ($validated['page'] ?? 1);
        $validated['per_page'] = (int) ($validated['per_page'] ?? 25);

        return $validated;
    }

    private function optionResponse(Request $request, $query, array $validated): JsonResponse
    {
        $paginator = $query->paginate($validated['per_page'], ['*'], 'page', $validated['page']);

        return response()->json([
            'data' => GeographyOptionResource::collection($paginator->getCollection())->resolve($request),
            'meta' => $this->meta($paginator),
        ]);
    }

    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
