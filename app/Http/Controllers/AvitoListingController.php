<?php

namespace App\Http\Controllers;

use App\Models\AvitoConnection;
use App\Services\Avito\AvitoListingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvitoListingController extends Controller
{
    public function context(Request $request, AvitoListingService $listings): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
        ]);

        return response()->json($listings->context($this->connection($validated['connection_id'] ?? null)));
    }

    public function index(Request $request, AvitoListingService $listings): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'statuses' => ['sometimes', 'array', 'max:5'],
            'statuses.*' => ['required', 'string', Rule::in(AvitoListingService::STATUSES), 'distinct'],
            'category' => ['nullable', 'integer', 'min:1'],
            'updated_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($listings->listings((int) $validated['account_id'], $validated));
    }

    public function show(Request $request, int $item, AvitoListingService $listings): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($listings->listing((int) $validated['account_id'], $item));
    }

    public function statistics(Request $request, AvitoListingService $listings): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'date_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:today'],
            'grouping' => ['required', Rule::in(['day', 'week', 'month', 'totals'])],
            'metrics' => ['required', 'array', 'min:1', 'max:36'],
            'metrics.*' => ['required', 'string', Rule::in(AvitoListingService::METRICS), 'distinct'],
            'category_ids' => ['sometimes', 'array', 'max:100'],
            'category_ids.*' => ['integer', 'min:1', 'distinct'],
            'employee_ids' => ['sometimes', 'array', 'max:100'],
            'employee_ids.*' => ['integer', 'min:1', 'distinct'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sort_key' => ['nullable', 'string', Rule::in(AvitoListingService::METRICS)],
            'sort_order' => ['required_with:sort_key', Rule::in(['asc', 'desc'])],
        ]);
        $this->assertDateDepth($validated['date_from'], $validated['date_to'], 270);

        return response()->json($listings->statistics((int) $validated['account_id'], $validated));
    }

    public function itemStatistics(Request $request, AvitoListingService $listings): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'item_ids' => ['required', 'array', 'min:1', 'max:200'],
            'item_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'date_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:today'],
            'fields' => ['required', 'array', 'min:1', 'max:3'],
            'fields.*' => ['required', Rule::in(['uniqViews', 'uniqContacts', 'uniqFavorites']), 'distinct'],
            'grouping' => ['required', Rule::in(['day', 'week', 'month'])],
        ]);
        $this->assertDateDepth($validated['date_from'], $validated['date_to'], 270);

        return response()->json($listings->itemStatistics((int) $validated['account_id'], $validated));
    }

    public function spendings(Request $request, AvitoListingService $listings): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'item_ids' => ['sometimes', 'array', 'max:100'],
            'item_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'category_ids' => ['sometimes', 'array', 'max:100'],
            'category_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'date_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:today'],
            'grouping' => ['required', Rule::in(['day', 'week', 'month'])],
            'spending_types' => ['required', 'array', 'min:1', 'max:5'],
            'spending_types.*' => ['required', Rule::in(['all', 'promotion', 'presence', 'commission', 'rest']), 'distinct'],
        ]);
        $this->assertDateDepth($validated['date_from'], $validated['date_to'], 510);

        return response()->json($listings->spendings((int) $validated['account_id'], $validated));
    }

    public function promotions(Request $request, AvitoListingService $listings): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'item_ids' => ['required', 'array', 'min:1', 'max:100'],
            'item_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
        ]);

        return response()->json($listings->promotionInsights(
            (int) $validated['account_id'],
            $validated['item_ids'],
            $this->connection($validated['connection_id'] ?? null),
        ));
    }

    public function action(Request $request, int $item, AvitoListingService $listings): JsonResponse
    {
        $base = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'connection_id' => ['nullable', 'integer', 'exists:avito_connections,id'],
            'action' => ['required', Rule::in([
                'update_price', 'apply_vas', 'apply_package',
                'apply_services', 'stop_cpx', 'create_bbip',
            ])],
            'confirmed' => ['accepted'],
        ]);
        $rules = match ($base['action']) {
            'update_price' => ['price' => ['required', 'integer', 'min:0', 'max:999999999999']],
            'apply_vas' => ['slug' => ['required', Rule::in(['highlight', 'xl'])]],
            'apply_package' => ['package_id' => ['required', Rule::in([
                'x2_1', 'x2_7', 'x5_1', 'x5_7', 'x10_1', 'x10_7',
                'x15_1', 'x15_7', 'x20_1', 'x20_7',
            ])]],
            'apply_services' => [
                'slugs' => ['required', 'array', 'min:1', 'max:20'],
                'slugs.*' => ['required', 'string', 'max:80', 'distinct'],
                'stickers' => ['sometimes', 'array', 'max:20'],
                'stickers.*' => ['required', 'integer', 'min:1', 'distinct'],
            ],
            'create_bbip' => [
                'duration' => ['required', 'integer', 'min:1', 'max:365'],
                'old_price' => ['required', 'integer', 'min:0'],
                'promo_price' => ['required', 'integer', 'min:0'],
            ],
            default => [],
        };
        $actionInput = Validator::make($request->all(), $rules)->validate();

        return response()->json($listings->performAction(
            (int) $base['account_id'],
            $item,
            $base['action'],
            $actionInput,
            $this->connection($base['connection_id'] ?? null),
        ));
    }

    private function connection(?int $connectionId): ?AvitoConnection
    {
        return $connectionId ? AvitoConnection::query()->findOrFail($connectionId) : null;
    }

    private function assertDateDepth(string $from, string $to, int $days): void
    {
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= $days) {
            throw ValidationException::withMessages([
                'date_from' => "Глубина периода ограничена {$days} днями.",
            ]);
        }
    }
}
