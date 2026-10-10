<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\FetchYandexProductSearchJob;
use App\Models\Product;
use App\Models\ProductSearchRequest;
use App\Services\Yandex\YandexSearchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductSearchController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProductSearch($request);
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'max_results' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = trim($validated['query'] ?? '');
        if ($query === '') {
            $query = mb_substr(trim((string) $product->rus), 0, 248).' купить';
        }
        $maxResults = $validated['max_results'] ?? 100;

        // Serialize concurrent clicks for one product before checking active work.
        $searchRequest = DB::transaction(function () use ($product, $query) {
            Product::query()->without(['category', 'manufacturers'])
                ->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $activeRequest = ProductSearchRequest::query()
                ->where('product_id', $product->id)
                ->where('engine', 'yandex')
                ->whereIn('status', ['queued', 'processing'])
                ->latest('id')
                ->first();

            return $activeRequest ?? ProductSearchRequest::create([
                'product_id' => $product->id,
                'engine' => 'yandex',
                'query' => $query,
                'status' => 'queued',
            ]);
        });

        if (! $searchRequest->wasRecentlyCreated) {
            return response()->json([
                'ok' => true,
                'request_id' => $searchRequest->id,
                'status' => $searchRequest->status,
                'query' => $searchRequest->query,
                'message' => 'Для этого товара уже выполняется сбор выдачи.',
            ]);
        }

        try {
            FetchYandexProductSearchJob::dispatch($searchRequest->id, $maxResults)->afterCommit();
        } catch (Throwable $exception) {
            $code = $exception instanceof YandexSearchException
                ? $exception->safeCode
                : 'yandex_search_queue_unavailable';
            $searchRequest->update([
                'status' => 'failed',
                'error_message' => $code,
                'finished_at' => now(),
            ]);

            return response()->json([
                'ok' => false,
                'request_id' => $searchRequest->id,
                'status' => 'failed',
                'error_code' => $code,
                'message' => YandexSearchException::userMessage($code),
            ], 503);
        }

        $searchRequest->refresh();

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
            'request' => $this->requestData($searchRequest),
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
            'request' => $this->requestData($searchRequest),
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

    private function requestData(ProductSearchRequest $searchRequest): array
    {
        $code = $searchRequest->error_message;
        if ($code !== null && preg_match('/^yandex_[a-z0-9_]{1,100}$/', $code) !== 1) {
            $code = 'yandex_product_search_failed_safely';
        }

        return [
            'id' => $searchRequest->id,
            'status' => $searchRequest->status,
            'query' => $searchRequest->query,
            'results_count' => $searchRequest->results_count,
            'error_code' => $code,
            'error_message' => YandexSearchException::userMessage($code),
            'created_at' => $searchRequest->created_at?->toDateTimeString(),
            'started_at' => $searchRequest->started_at?->toDateTimeString(),
            'finished_at' => $searchRequest->finished_at?->toDateTimeString(),
            'searched_at' => $searchRequest->searched_at?->toDateTimeString(),
        ];
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
