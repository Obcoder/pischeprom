<?php

namespace App\Services\Yandex;

use App\Models\YandexAccount;
use App\Models\YandexDirectDailyStat;
use App\Models\YandexSyncLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class YandexDirectReportService
{
    public function __construct(private readonly YandexDirectApiClient $client) {}

    public function fetchDailyStats(YandexAccount $account, Carbon|string $from, Carbon|string $to): array
    {
        $from = Carbon::parse($from)->toDateString();
        $to = Carbon::parse($to)->toDateString();

        if (!config('yandex.direct.enable_real_send')) {
            YandexSyncLog::query()->create([
                'yandex_account_id' => $account->id,
                'entity_type' => 'direct_report',
                'action' => 'direct.stats.fetch',
                'status' => 'dry_run',
                'request_payload' => compact('from', 'to'),
                'response_payload' => ['message' => 'YANDEX_DIRECT_ENABLE_REAL_SEND=false. Реальная загрузка отчётов отключена.'],
            ]);

            return [];
        }

        // Reports API can return generated reports asynchronously. The exact report shape
        // depends on the account and goals setup, so MVP keeps the client boundary here.
        return $this->client->request($account, 'reports', 'get', [
            'SelectionCriteria' => [
                'DateFrom' => $from,
                'DateTo' => $to,
            ],
            'FieldNames' => ['Date', 'CampaignId', 'AdGroupId', 'AdId', 'CriteriaId', 'Impressions', 'Clicks', 'Cost', 'Ctr', 'AvgCpc', 'Conversions'],
            'ReportName' => 'pischeprom-goods-daily-' . now()->format('YmdHis'),
            'ReportType' => 'CUSTOM_REPORT',
            'DateRangeType' => 'CUSTOM_DATE',
            'Format' => 'TSV',
            'IncludeVAT' => 'YES',
            'IncludeDiscount' => 'NO',
        ]);
    }

    public function fetchSearchQueryStats(YandexAccount $account, Carbon|string $from, Carbon|string $to): array
    {
        YandexSyncLog::query()->create([
            'yandex_account_id' => $account->id,
            'entity_type' => 'direct_report',
            'action' => 'direct.search-query-stats.fetch',
            'status' => 'prepared',
            'request_payload' => [
                'from' => Carbon::parse($from)->toDateString(),
                'to' => Carbon::parse($to)->toDateString(),
            ],
            'response_payload' => ['message' => 'MVP-заготовка. Требует реального Direct Reports API и настроенных целей.'],
        ]);

        return [];
    }

    public function storeStats(YandexAccount $account, array $rows): int
    {
        $stored = 0;

        DB::transaction(function () use ($account, $rows, &$stored) {
            foreach ($rows as $row) {
                YandexDirectDailyStat::query()->updateOrCreate([
                    'date' => Carbon::parse($row['date'])->toDateString(),
                    'yandex_account_id' => $account->id,
                    'campaign_id' => $row['campaign_id'] ?? null,
                    'ad_group_id' => $row['ad_group_id'] ?? null,
                    'ad_id' => $row['ad_id'] ?? null,
                    'keyword_id' => $row['keyword_id'] ?? null,
                ], [
                    'good_id' => $row['good_id'] ?? null,
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'cost' => (float) ($row['cost'] ?? 0),
                    'avg_cpc' => $row['avg_cpc'] ?? null,
                    'ctr' => $row['ctr'] ?? null,
                    'conversions' => (int) ($row['conversions'] ?? 0),
                    'cost_per_conversion' => $row['cost_per_conversion'] ?? null,
                    'raw' => $row['raw'] ?? $row,
                ]);

                $stored++;
            }
        });

        return $stored;
    }

    public function syncYesterday(?YandexAccount $account = null): array
    {
        $date = now()->subDay()->toDateString();

        return $this->syncPeriod($date, $date, $account);
    }

    public function syncPeriod(Carbon|string $from, Carbon|string $to, ?YandexAccount $account = null): array
    {
        $accounts = $account
            ? collect([$account])
            : YandexAccount::query()->where('is_active', true)->get();

        $result = ['accounts' => $accounts->count(), 'stored' => 0];

        foreach ($accounts as $item) {
            $rows = $this->fetchDailyStats($item, $from, $to);
            $result['stored'] += $this->storeStats($item, is_array($rows) ? $rows : []);
        }

        return $result;
    }
}
