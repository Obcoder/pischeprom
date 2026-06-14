<?php

namespace App\Services\Yandex;

use App\Models\DirectLaunchSession;
use App\Models\Good;
use App\Models\YandexAccount;
use App\Models\YandexDirectAd;
use App\Models\YandexDirectAdGroup;
use App\Models\YandexDirectCampaign;
use App\Models\YandexDirectKeyword;
use App\Models\YandexSyncLog;
use Illuminate\Support\Arr;
use Throwable;

class DirectCampaignBuilderService
{
    public function __construct(
        private readonly DirectStructurePlannerService $planner,
        private readonly YandexDirectApiClient $client,
        private readonly GoodDirectDraftService $draftService,
    ) {}

    public function build(Good $good, YandexAccount $account, DirectLaunchSession $session, array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $plan = $this->planner->plan($good);

        $session->update([
            'status' => DirectLaunchSession::STATUS_BUILDING,
            'step' => 'plan_built',
            'payload' => [
                ...($session->payload ?: []),
                'dry_run' => $dryRun,
                'plan' => $plan,
            ],
        ]);

        $local = $this->createLocalStructure($good, $account, $session, $plan);

        if ($dryRun) {
            $this->logStep($session, $account, 'direct.auto_launch.dry_run', 'dry_run', ['plan' => $plan], ['local' => $local]);

            return [
                'dry_run' => true,
                'plan' => $plan,
                'local' => $local,
                'external_ids' => [],
            ];
        }

        return $this->createCampaignStructure($good, $account, $session, $plan, $local);
    }

    public function createCampaignStructure(Good $good, YandexAccount $account, DirectLaunchSession $session, array $plan, array $local): array
    {
        $externalIds = [
            'campaign_ids' => [],
            'ad_group_ids' => [],
            'ad_ids' => [],
            'keyword_ids' => [],
        ];

        $campaign = YandexDirectCampaign::query()->findOrFail($local['campaign_id']);

        $session->update([
            'status' => DirectLaunchSession::STATUS_SENDING,
            'step' => 'create_campaign',
        ]);

        $campaignPayload = $this->campaignPayload($plan);
        $campaignResponse = $this->client->request($account, 'campaigns', 'add', $campaignPayload);
        try {
            $campaignExternalId = $this->extractAddId($campaignResponse, 'Campaigns.add', 1);
        } catch (DirectApiAddException $e) {
            $externalIds['campaign_ids'] = array_values(array_merge($externalIds['campaign_ids'], $e->externalIds));
            $this->syncSessionExternalIds($session, $externalIds);
            throw $e;
        }
        $externalIds['campaign_ids'][] = $campaignExternalId;
        $campaign->update([
            'external_campaign_id' => (string) $campaignExternalId,
            'status' => YandexDirectAd::STATUS_SYNCING,
            'last_synced_at' => now(),
            'settings' => [
                ...($campaign->settings ?: []),
                'direct_payload' => $campaignPayload,
            ],
        ]);
        $this->syncSessionExternalIds($session, $externalIds);

        $groups = YandexDirectAdGroup::query()
            ->where('yandex_direct_campaign_id', $campaign->id)
            ->with(['ads', 'keywords'])
            ->get();

        foreach ($groups as $group) {
            $session->update(['step' => 'create_ad_group']);
            $groupPayload = $this->adGroupPayload($group, $campaignExternalId);
            $groupResponse = $this->client->request($account, 'adgroups', 'add', $groupPayload);
            try {
                $adGroupExternalId = $this->extractAddId($groupResponse, 'AdGroups.add', 1);
            } catch (DirectApiAddException $e) {
                $externalIds['ad_group_ids'] = array_values(array_merge($externalIds['ad_group_ids'], $e->externalIds));
                $this->syncSessionExternalIds($session, $externalIds);
                throw $e;
            }
            $externalIds['ad_group_ids'][] = $adGroupExternalId;
            $group->update([
                'external_ad_group_id' => (string) $adGroupExternalId,
                'status' => YandexDirectAd::STATUS_SYNCING,
                'last_synced_at' => now(),
                'settings' => [
                    ...($group->settings ?: []),
                    'direct_payload' => $groupPayload,
                ],
            ]);
            $this->syncSessionExternalIds($session, $externalIds);

            $session->update(['step' => 'create_ads']);
            $adsPayload = $this->adsPayload($group->fresh('ads'), $adGroupExternalId);
            $adsResponse = $this->client->request($account, 'ads', 'add', $adsPayload);
            try {
                $adExternalIds = $this->extractAddIds($adsResponse, 'Ads.add', count($adsPayload['Ads']));
            } catch (DirectApiAddException $e) {
                $externalIds['ad_ids'] = array_values(array_merge($externalIds['ad_ids'], $e->externalIds));
                $this->syncSessionExternalIds($session, $externalIds);
                throw $e;
            }
            $externalIds['ad_ids'] = array_values(array_merge($externalIds['ad_ids'], $adExternalIds));

            foreach ($group->ads()->orderBy('id')->get()->values() as $index => $ad) {
                $ad->update([
                    'external_ad_id' => isset($adExternalIds[$index]) ? (string) $adExternalIds[$index] : null,
                    'status' => YandexDirectAd::STATUS_MODERATION,
                    'last_synced_at' => now(),
                    'error_message' => null,
                ]);
            }
            $this->syncSessionExternalIds($session, $externalIds);

            $keywords = $group->keywords()->where('is_negative', false)->orderBy('id')->get();
            if ($keywords->isNotEmpty()) {
                $session->update(['step' => 'create_keywords']);
                $keywordsPayload = $this->keywordsPayload($keywords->all(), $adGroupExternalId);
                $keywordsResponse = $this->client->request($account, 'keywords', 'add', $keywordsPayload);
                try {
                    $keywordExternalIds = $this->extractAddIds($keywordsResponse, 'Keywords.add', count($keywordsPayload['Keywords']));
                } catch (DirectApiAddException $e) {
                    $externalIds['keyword_ids'] = array_values(array_merge($externalIds['keyword_ids'], $e->externalIds));
                    $this->syncSessionExternalIds($session, $externalIds);
                    throw $e;
                }
                $externalIds['keyword_ids'] = array_values(array_merge($externalIds['keyword_ids'], $keywordExternalIds));

                foreach ($keywords->values() as $index => $keyword) {
                    $keyword->update([
                        'external_keyword_id' => isset($keywordExternalIds[$index]) ? (string) $keywordExternalIds[$index] : null,
                        'status' => YandexDirectAd::STATUS_SENT,
                    ]);
                }
                $this->syncSessionExternalIds($session, $externalIds);
            }
        }

        $campaign->update([
            'status' => YandexDirectAd::STATUS_SENT,
            'last_synced_at' => now(),
            'error_message' => null,
        ]);

        $groups = $groups->map(fn (YandexDirectAdGroup $group) => $group->fresh());
        foreach ($groups as $group) {
            $group->update([
                'status' => YandexDirectAd::STATUS_SENT,
                'last_synced_at' => now(),
                'error_message' => null,
            ]);
        }

        return [
            'dry_run' => false,
            'plan' => $plan,
            'local' => $local,
            'external_ids' => $externalIds,
        ];
    }

    public function rollbackOnError(YandexAccount $account, DirectLaunchSession $session, array $externalIds): array
    {
        $rollback = [];

        foreach ([
            'keywords' => $externalIds['keyword_ids'] ?? [],
            'ads' => $externalIds['ad_ids'] ?? [],
            'adgroups' => $externalIds['ad_group_ids'] ?? [],
            'campaigns' => $externalIds['campaign_ids'] ?? [],
        ] as $service => $ids) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) {
                continue;
            }

            try {
                $response = $this->client->request($account, $service, 'delete', [
                    'SelectionCriteria' => ['Ids' => $ids],
                ]);
                $rollback[$service] = [
                    'status' => 'success',
                    'ids' => $ids,
                    'response' => $response,
                ];
            } catch (Throwable $e) {
                $rollback[$service] = [
                    'status' => 'error',
                    'ids' => $ids,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $session->update(['rollback_payload' => $rollback ?: null]);

        return $rollback;
    }

    private function createLocalStructure(Good $good, YandexAccount $account, DirectLaunchSession $session, array $plan): array
    {
        $campaign = YandexDirectCampaign::query()->create([
            'yandex_account_id' => $account->id,
            'good_id' => $good->id,
            'name' => $plan['campaign']['name'],
            'type' => $plan['campaign']['type'],
            'status' => YandexDirectAd::STATUS_DRAFT,
            'daily_budget' => $plan['campaign']['daily_budget'],
            'region_ids' => $plan['campaign']['region_ids'],
            'settings' => [
                'launch_session_id' => $session->id,
                'strategy' => $plan['campaign']['strategy'],
                'minus_keywords' => $plan['campaign']['minus_keywords'],
            ],
        ]);

        $groupIds = [];
        $adIds = [];
        $keywordIds = [];

        foreach ($plan['ad_groups'] as $groupPlan) {
            $group = YandexDirectAdGroup::query()->create([
                'yandex_direct_campaign_id' => $campaign->id,
                'good_id' => $good->id,
                'name' => $groupPlan['name'],
                'status' => YandexDirectAd::STATUS_DRAFT,
                'region_ids' => $groupPlan['region_ids'],
                'minus_keywords' => implode("\n", $groupPlan['minus_keywords']),
                'settings' => [
                    'launch_session_id' => $session->id,
                    'segment' => $groupPlan['segment'],
                ],
            ]);
            $groupIds[] = $group->id;

            foreach ($groupPlan['ads'] as $adPlan) {
                $ad = YandexDirectAd::query()->create([
                    'yandex_direct_ad_group_id' => $group->id,
                    'good_id' => $good->id,
                    'good_seo_id' => $good->seo?->id,
                    'title_1' => $adPlan['title_1'],
                    'title_2' => $adPlan['title_2'],
                    'text' => $adPlan['text'],
                    'href' => $adPlan['href'],
                    'utm_template' => $adPlan['utm_template'],
                    'image_url' => $adPlan['image_url'],
                    'status' => YandexDirectAd::STATUS_DRAFT,
                    'validation_errors' => null,
                    'error_message' => null,
                ]);
                $this->draftService->validateAndPersist($ad);
                $adIds[] = $ad->id;
            }

            foreach ($groupPlan['keywords'] as $keywordPlan) {
                $keyword = YandexDirectKeyword::query()->create([
                    'yandex_direct_ad_group_id' => $group->id,
                    'good_id' => $good->id,
                    'phrase' => $keywordPlan['phrase'],
                    'status' => YandexDirectAd::STATUS_DRAFT,
                    'is_negative' => false,
                ]);
                $keywordIds[] = $keyword->id;
            }

            foreach ($groupPlan['minus_keywords'] as $phrase) {
                $keyword = YandexDirectKeyword::query()->create([
                    'yandex_direct_ad_group_id' => $group->id,
                    'good_id' => $good->id,
                    'phrase' => $phrase,
                    'status' => YandexDirectAd::STATUS_DRAFT,
                    'is_negative' => true,
                ]);
                $keywordIds[] = $keyword->id;
            }
        }

        return [
            'campaign_id' => $campaign->id,
            'ad_group_ids' => $groupIds,
            'ad_ids' => $adIds,
            'keyword_ids' => $keywordIds,
        ];
    }

    private function campaignPayload(array $plan): array
    {
        $weeklySpendLimit = $this->moneyToMicros((float) $plan['campaign']['daily_budget'] * 7);
        $textCampaign = [
            'BiddingStrategy' => [
                'Search' => [
                    'BiddingStrategyType' => 'WB_MAXIMUM_CLICKS',
                    'WbMaximumClicks' => [
                        'WeeklySpendLimit' => $weeklySpendLimit,
                    ],
                ],
                'Network' => [
                    'BiddingStrategyType' => 'SERVING_OFF',
                ],
            ],
            'Settings' => [
                ['Option' => 'ADD_METRICA_TAG', 'Value' => 'YES'],
            ],
        ];

        $counterId = config('yandex.metrica.counter_id');
        if (filled($counterId)) {
            $textCampaign['CounterIds'] = ['Items' => [(int) $counterId]];
        }

        $campaign = [
            'Name' => $plan['campaign']['name'],
            'StartDate' => now()->toDateString(),
            'TimeZone' => config('app.timezone', 'Europe/Moscow'),
            'TextCampaign' => $textCampaign,
        ];

        if (filled($plan['campaign']['minus_keywords'])) {
            $campaign['NegativeKeywords'] = ['Items' => array_values($plan['campaign']['minus_keywords'])];
        }

        return ['Campaigns' => [$campaign]];
    }

    private function adGroupPayload(YandexDirectAdGroup $group, int|string $externalCampaignId): array
    {
        $item = [
            'Name' => $group->name,
            'CampaignId' => (int) $externalCampaignId,
            'RegionIds' => array_values(array_map('intval', $group->region_ids ?: [])),
        ];

        $minusKeywords = preg_split('/[\r\n]+/u', (string) $group->minus_keywords) ?: [];
        $minusKeywords = array_values(array_filter(array_map('trim', $minusKeywords)));
        if ($minusKeywords) {
            $item['NegativeKeywords'] = ['Items' => $minusKeywords];
        }

        return ['AdGroups' => [$item]];
    }

    private function adsPayload(YandexDirectAdGroup $group, int|string $externalAdGroupId): array
    {
        return [
            'Ads' => $group->ads
                ->map(fn (YandexDirectAd $ad) => [
                    'AdGroupId' => (int) $externalAdGroupId,
                    'TextAd' => [
                        'Title' => $ad->title_1,
                        'Title2' => $ad->title_2,
                        'Text' => $ad->text,
                        'Href' => $ad->href,
                        'Mobile' => 'NO',
                    ],
                ])
                ->values()
                ->all(),
        ];
    }

    private function keywordsPayload(array $keywords, int|string $externalAdGroupId): array
    {
        return [
            'Keywords' => collect($keywords)
                ->map(fn (YandexDirectKeyword $keyword) => [
                    'AdGroupId' => (int) $externalAdGroupId,
                    'Keyword' => $keyword->phrase,
                ])
                ->values()
                ->all(),
        ];
    }

    private function extractAddId(array $response, string $context, int $expectedCount = 1): int
    {
        $ids = $this->extractAddIds($response, $context, $expectedCount);

        return (int) $ids[0];
    }

    private function extractAddIds(array $response, string $context, ?int $expectedCount = null): array
    {
        $result = Arr::get($response, 'result', []);
        $addResults = collect($result)->first(fn ($value, $key) => is_string($key) && str_ends_with($key, 'AddResults'));

        if (!is_array($addResults)) {
            throw new DirectApiAddException("{$context}: Direct API не вернул AddResults.");
        }

        $ids = [];
        $errors = [];

        foreach ($addResults as $index => $item) {
            foreach (($item['Errors'] ?? []) as $error) {
                $errors[] = trim(($error['Message'] ?? 'Ошибка') . ' ' . ($error['Details'] ?? ''));
            }

            if (isset($item['Id'])) {
                $ids[] = (int) $item['Id'];
            }
        }

        if ($errors || !$ids || ($expectedCount !== null && count($ids) !== $expectedCount)) {
            $message = $errors ?: ["Direct API вернул external ID не для всех объектов: " . count($ids) . '/' . $expectedCount . '.'];

            throw new DirectApiAddException("{$context}: " . implode('; ', $message), $ids);
        }

        return $ids;
    }

    private function syncSessionExternalIds(DirectLaunchSession $session, array $externalIds): void
    {
        $session->update(['external_ids' => $externalIds]);
    }

    private function logStep(DirectLaunchSession $session, YandexAccount $account, string $action, string $status, ?array $request = null, ?array $response = null, ?string $error = null): void
    {
        YandexSyncLog::query()->create([
            'yandex_account_id' => $account->id,
            'entity_type' => DirectLaunchSession::class,
            'entity_id' => $session->id,
            'action' => $action,
            'status' => $status,
            'request_payload' => $request,
            'response_payload' => $response,
            'error_message' => $error,
        ]);
    }

    private function moneyToMicros(float $amount): int
    {
        return (int) round($amount * 1_000_000);
    }
}
