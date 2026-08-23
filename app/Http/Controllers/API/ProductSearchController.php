<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\FetchYandexProductSearchJob;
use App\Models\Product;
use App\Models\ProductSearchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProductSearch($request);
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'max_results' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = $validated['query'] ?? ($product->name.' купить');
        $maxResults = $validated['max_results'] ?? 100;

        // Проверяем, нет ли уже активного запроса по этому товару
        $activeRequest = ProductSearchRequest::query()
            ->where('product_id', $product->id)
            ->where('engine', 'yandex')
            ->whereIn('status', ['queued', 'processing'])
            ->latest('id')
            ->first();

        if ($activeRequest) {
            return response()->json([
                'ok' => true,
                'request_id' => $activeRequest->id,
                'status' => $activeRequest->status,
                'query' => $activeRequest->query,
                'message' => 'Для этого товара уже выполняется сбор выдачи.',
            ]);
        }

        $searchRequest = ProductSearchRequest::create([
            'product_id' => $product->id,
            'engine' => 'yandex',
            'query' => $query,
            'status' => 'queued',
        ]);

        FetchYandexProductSearchJob::dispatch($searchRequest->id, $maxResults);

        return response()->json([
            'ok' => true,
            'request_id' => $searchRequest->id,
            'status' => $searchRequest->status,
            'query' => $searchRequest->query,
            'message' => 'Задача поставлена в очередь.',
        ], 201);
    }

    public function latest(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProductSearch($request);
        $searchRequest = ProductSearchRequest::query()
            ->where('product_id', $product->id)
            ->where('engine', 'yandex')
            ->latest('id')
            ->with('results')
            ->first();

        if (! $searchRequest) {
            return response()->json([
                'request' => null,
                'results' => [],
            ]);
        }

        return response()->json([
            'request' => [
                'id' => $searchRequest->id,
                'status' => $searchRequest->status,
                'query' => $searchRequest->query,
                'results_count' => $searchRequest->results_count,
                'error_message' => $searchRequest->error_message,
                'started_at' => optional($searchRequest->started_at)?->toDateTimeString(),
                'finished_at' => optional($searchRequest->finished_at)?->toDateTimeString(),
                'searched_at' => optional($searchRequest->searched_at)?->toDateTimeString(),
            ],
            'results' => $searchRequest->results->map(fn ($item) => [
                'id' => $item->id,
                'position' => $item->position,
                'title' => $item->title,
                'url' => $item->url,
                'domain' => $item->domain,
                'snippet' => $item->snippet,
            ])->values(),
        ]);
    }

    public function show(Request $request, Product $product, ProductSearchRequest $searchRequest): JsonResponse
    {
        $this->authorizeProductSearch($request);
        abort_unless($searchRequest->product_id === $product->id, 404);

        $searchRequest->load('results');

        return response()->json([
            'request' => [
                'id' => $searchRequest->id,
                'status' => $searchRequest->status,
                'query' => $searchRequest->query,
                'results_count' => $searchRequest->results_count,
                'error_message' => $searchRequest->error_message,
                'started_at' => optional($searchRequest->started_at)?->toDateTimeString(),
                'finished_at' => optional($searchRequest->finished_at)?->toDateTimeString(),
                'searched_at' => optional($searchRequest->searched_at)?->toDateTimeString(),
            ],
            'results' => $searchRequest->results->map(fn ($item) => [
                'id' => $item->id,
                'position' => $item->position,
                'title' => $item->title,
                'url' => $item->url,
                'domain' => $item->domain,
                'snippet' => $item->snippet,
            ])->values(),
        ]);
    }

    private function authorizeProductSearch(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? null) === 'active', 403);

        try {
            $allowed = $user->hasRole('admin', 'crm')
                || $user->hasPermissionTo('products.view', 'crm');
        } catch (\Throwable) {
            $allowed = false;
        }

        abort_unless($allowed, 403);
    }
}
