<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingSearchJob;
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
        ])->validate();
        $search = addcslashes(trim((string) ($validated['search'] ?? '')), '%_\\');
        $products = Product::query()->without(['category', 'manufacturers'])
            ->leftJoin('categories', function ($join): void {
                $join->on('categories.id', '=', 'products.category_id')
                    ->where('categories.is_published', true);
            })
            ->where('products.is_published', true)
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                $nested->where('products.rus', 'like', "%{$search}%")
                    ->orWhere('products.eng', 'like', "%{$search}%");
            }))
            ->select(['products.id', 'products.rus', 'products.eng', 'categories.name as category_name'])
            ->orderBy('products.rus')->orderBy('products.id')
            ->limit($validated['limit'] ?? 50)->get()
            ->map(fn ($product) => [
                'id' => (int) $product->id,
                'name' => $product->rus,
                'english_name' => $product->eng,
                'category' => $product->category_name,
            ]);

        return response()->json(['data' => $products]);
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
}
