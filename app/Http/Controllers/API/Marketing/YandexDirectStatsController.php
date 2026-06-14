<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\SyncYandexStatsRequest;
use App\Jobs\SyncYandexDirectStatsJob;
use App\Models\YandexAccount;
use App\Models\YandexDirectDailyStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YandexDirectStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);
        $from = $request->input('from');
        $to = $request->input('to');

        $query = YandexDirectDailyStat::query()
            ->with(['good', 'campaign', 'adGroup', 'ad', 'keyword'])
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->when($request->input('good_id'), fn ($q, $id) => $q->where('good_id', $id))
            ->when($request->input('campaign_id'), fn ($q, $id) => $q->where('campaign_id', $id))
            ->when($request->boolean('only_clicks'), fn ($q) => $q->where('clicks', '>', 0))
            ->when($request->boolean('only_cost'), fn ($q) => $q->where('cost', '>', 0))
            ->when($request->boolean('only_conversions'), fn ($q) => $q->where('conversions', '>', 0));

        $summaryQuery = clone $query;
        $summary = [
            'impressions' => (int) $summaryQuery->sum('impressions'),
            'clicks' => (int) (clone $query)->sum('clicks'),
            'cost' => (float) (clone $query)->sum('cost'),
            'conversions' => (int) (clone $query)->sum('conversions'),
        ];
        $summary['ctr'] = $summary['impressions'] > 0 ? round($summary['clicks'] / $summary['impressions'] * 100, 2) : null;
        $summary['avg_cpc'] = $summary['clicks'] > 0 ? round($summary['cost'] / $summary['clicks'], 2) : null;
        $summary['cpl'] = $summary['conversions'] > 0 ? round($summary['cost'] / $summary['conversions'], 2) : null;

        $paginator = $query->orderByDesc('date')->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'summary' => $summary,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function sync(SyncYandexStatsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $from = $validated['from'] ?? now()->subDays(7)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $accountId = $validated['yandex_account_id'] ?? null;

        $account = $accountId ? YandexAccount::query()->findOrFail($accountId) : null;

        SyncYandexDirectStatsJob::dispatch($from, $to, $account?->id);

        return response()->json([
            'queued' => true,
            'from' => $from,
            'to' => $to,
            'account_id' => $account?->id,
        ]);
    }
}
