<?php

namespace App\Services\Yandex;

use App\Models\YandexAccount;
use App\Models\YandexDirectAd;
use App\Models\YandexDirectAdGroup;
use App\Models\YandexDirectCampaign;
use App\Models\YandexDirectDailyStat;
use App\Models\YandexDirectKeyword;
use App\Models\YandexSyncLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class YandexDirectReportService
{
    private const REPORT_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly YandexOAuthService $oauthService,
    ) {}

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

        $payload = [
            'SelectionCriteria' => [
                'DateFrom' => $from,
                'DateTo' => $to,
            ],
            'FieldNames' => ['Date', 'CampaignId', 'AdGroupId', 'AdId', 'CriterionId', 'Impressions', 'Clicks', 'Cost', 'Ctr', 'AvgCpc', 'Conversions'],
            'ReportName' => 'pischeprom-goods-daily-' . now()->format('YmdHis'),
            'ReportType' => 'CUSTOM_REPORT',
            'DateRangeType' => 'CUSTOM_DATE',
            'Format' => 'TSV',
            'IncludeVAT' => 'YES',
            'IncludeDiscount' => 'NO',
        ];

        $text = $this->requestReport($account, $payload);

        return $this->parseDailyStatsTsv($account, $text);
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
        $rows = $this->attachLocalIds($account, $rows);
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

    private function requestReport(YandexAccount $account, array $payload): string
    {
        $account = $this->oauthService->ensureFreshToken($account);

        if (blank($account->access_token)) {
            throw new RuntimeException('Не подключён OAuth-токен Яндекса.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $account->access_token,
            'Accept-Language' => 'ru',
            'Content-Type' => 'application/json; charset=utf-8',
            'processingMode' => 'auto',
            'returnMoneyInMicros' => 'false',
            'skipReportHeader' => 'true',
            'skipColumnHeader' => 'false',
            'skipReportSummary' => 'true',
        ];

        if (filled($account->direct_client_login)) {
            $headers['Client-Login'] = $account->direct_client_login;
        }

        $body = ['params' => $payload];

        $log = YandexSyncLog::query()->create([
            'yandex_account_id' => $account->id,
            'entity_type' => 'direct_report',
            'action' => 'direct.stats.fetch',
            'status' => 'request',
            'request_payload' => $body,
        ]);

        $url = config('yandex.direct.reports_url');

        for ($attempt = 1; $attempt <= self::REPORT_MAX_ATTEMPTS; $attempt++) {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('yandex.direct.timeout', 20))
                ->post($url, $body);

            if ($response->status() === 200) {
                $body = (string) $response->body();
                $log->update([
                    'status' => 'success',
                    'response_payload' => [
                        'attempt' => $attempt,
                        'bytes' => strlen($body),
                        'sample' => mb_substr($body, 0, 2000),
                    ],
                ]);

                return $body;
            }

            if (in_array($response->status(), [201, 202], true)) {
                $retryIn = max(1, min(10, (int) ($response->header('retryIn') ?: 3)));
                $log->update([
                    'status' => 'pending',
                    'response_payload' => [
                        'attempt' => $attempt,
                        'http_status' => $response->status(),
                        'retry_in' => $retryIn,
                    ],
                ]);
                sleep($retryIn);
                continue;
            }

            $body = (string) $response->body();
            $message = $this->reportErrorMessage($body) ?: 'Ошибка Direct Reports API: HTTP ' . $response->status();

            $log->update([
                'status' => 'error',
                'response_payload' => [
                    'http_status' => $response->status(),
                    'body' => mb_substr($body, 0, 12000),
                ],
                'error_message' => $message,
            ]);

            throw new RuntimeException($message);
        }

        $message = 'Отчёт Яндекс.Директа ещё формируется. Повторите синхронизацию позже.';
        $log->update([
            'status' => 'pending',
            'error_message' => $message,
        ]);

        return '';
    }

    private function parseDailyStatsTsv(YandexAccount $account, string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/u', $text) ?: [];
        $headers = null;
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $columns = str_getcsv($line, "\t");

            if ($headers === null) {
                $headers = $columns;
                continue;
            }

            $values = array_slice(array_pad($columns, count($headers), null), 0, count($headers));
            $raw = array_combine($headers, $values);

            if (!is_array($raw) || blank($raw['Date'] ?? null)) {
                continue;
            }

            $rows[] = [
                'date' => $raw['Date'],
                'external_campaign_id' => $this->nullableInt($raw['CampaignId'] ?? null),
                'external_ad_group_id' => $this->nullableInt($raw['AdGroupId'] ?? null),
                'external_ad_id' => $this->nullableInt($raw['AdId'] ?? null),
                'external_keyword_id' => $this->nullableInt($raw['CriterionId'] ?? $raw['CriteriaId'] ?? null),
                'impressions' => $this->intMetric($raw['Impressions'] ?? null),
                'clicks' => $this->intMetric($raw['Clicks'] ?? null),
                'cost' => $this->decimalMetric($raw['Cost'] ?? null),
                'avg_cpc' => $this->nullableDecimalMetric($raw['AvgCpc'] ?? null),
                'ctr' => $this->nullableDecimalMetric($raw['Ctr'] ?? null),
                'conversions' => $this->intMetric($raw['Conversions'] ?? null),
                'raw' => $raw,
            ];
        }

        YandexSyncLog::query()->create([
            'yandex_account_id' => $account->id,
            'entity_type' => 'direct_report',
            'action' => 'direct.stats.parse',
            'status' => 'success',
            'response_payload' => ['rows' => count($rows)],
        ]);

        return $rows;
    }

    private function attachLocalIds(YandexAccount $account, array $rows): array
    {
        $campaigns = YandexDirectCampaign::query()
            ->where('yandex_account_id', $account->id)
            ->whereNotNull('external_campaign_id')
            ->get()
            ->keyBy(fn (YandexDirectCampaign $campaign) => (string) $campaign->external_campaign_id);

        $adGroups = YandexDirectAdGroup::query()
            ->whereNotNull('external_ad_group_id')
            ->get()
            ->keyBy(fn (YandexDirectAdGroup $group) => (string) $group->external_ad_group_id);

        $ads = YandexDirectAd::query()
            ->whereNotNull('external_ad_id')
            ->get()
            ->keyBy(fn (YandexDirectAd $ad) => (string) $ad->external_ad_id);

        $keywords = YandexDirectKeyword::query()
            ->whereNotNull('external_keyword_id')
            ->get()
            ->keyBy(fn (YandexDirectKeyword $keyword) => (string) $keyword->external_keyword_id);

        return collect($rows)
            ->map(function (array $row) use ($campaigns, $adGroups, $ads, $keywords) {
                $campaign = $campaigns->get((string) ($row['external_campaign_id'] ?? ''));
                $adGroup = $adGroups->get((string) ($row['external_ad_group_id'] ?? ''));
                $ad = $ads->get((string) ($row['external_ad_id'] ?? ''));
                $keyword = $keywords->get((string) ($row['external_keyword_id'] ?? ''));

                return [
                    ...$row,
                    'good_id' => $ad?->good_id ?: $adGroup?->good_id ?: $campaign?->good_id ?: $keyword?->good_id,
                    'campaign_id' => $campaign?->id,
                    'ad_group_id' => $adGroup?->id,
                    'ad_id' => $ad?->id,
                    'keyword_id' => $keyword?->id,
                ];
            })
            ->all();
    }

    private function reportErrorMessage(string $body): ?string
    {
        $json = json_decode($body, true);

        if (!is_array($json)) {
            return null;
        }

        return data_get($json, 'error.error_detail')
            ?: data_get($json, 'error.error_string')
            ?: data_get($json, 'error.message');
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' || $value === '--' ? null : (int) $value;
    }

    private function intMetric(mixed $value): int
    {
        return (int) str_replace(' ', '', (string) ($value ?: 0));
    }

    private function nullableDecimalMetric(mixed $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '--') {
            return null;
        }

        return $this->decimalMetric($value);
    }

    private function decimalMetric(mixed $value): float
    {
        $value = str_replace([' ', ','], ['', '.'], (string) ($value ?: 0));

        return (float) $value;
    }
}
