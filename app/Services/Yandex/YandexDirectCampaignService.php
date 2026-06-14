<?php

namespace App\Services\Yandex;

use App\Models\Good;
use App\Models\YandexAccount;
use App\Models\YandexDirectAd;
use App\Models\YandexSyncLog;
use RuntimeException;

class YandexDirectCampaignService
{
    public function __construct(
        private readonly GoodDirectDraftService $draftService,
        private readonly YandexDirectApiClient $client,
    ) {}

    public function createCampaignFromDraft(YandexDirectAd $ad): array
    {
        return $this->sendAd($ad);
    }

    public function createAdGroupFromGood(Good $good, ?YandexAccount $account = null): YandexDirectAd
    {
        return $this->draftService->generateForGood($good, $account);
    }

    public function createAdsFromDrafts(iterable $ads): array
    {
        $results = [];

        foreach ($ads as $ad) {
            $results[] = $this->sendAd($ad);
        }

        return $results;
    }

    public function createKeywords(YandexDirectAd $ad): array
    {
        $ad->loadMissing('adGroup.keywords');

        return $ad->adGroup->keywords->values()->toArray();
    }

    public function sendAd(YandexDirectAd $ad): array
    {
        $ad->loadMissing('adGroup.campaign.account', 'adGroup.keywords');
        $errors = $this->draftService->validateAndPersist($ad);

        if ($errors) {
            $ad->update([
                'status' => YandexDirectAd::STATUS_ERROR,
                'error_message' => 'Объявление не прошло локальную валидацию.',
            ]);

            throw new RuntimeException('Объявление не прошло локальную валидацию.');
        }

        $account = $ad->adGroup?->campaign?->account;

        if (!$account || blank($account->access_token)) {
            $ad->update([
                'status' => YandexDirectAd::STATUS_ERROR,
                'error_message' => 'Не подключён OAuth-аккаунт Яндекса.',
            ]);

            throw new RuntimeException('Не подключён OAuth-аккаунт Яндекса.');
        }

        $payload = $this->buildDirectPayload($ad);

        if (!config('yandex.direct.enable_real_send')) {
            $ad->update([
                'status' => YandexDirectAd::STATUS_READY,
                'error_message' => null,
            ]);

            YandexSyncLog::query()->create([
                'yandex_account_id' => $account->id,
                'entity_type' => YandexDirectAd::class,
                'entity_id' => $ad->id,
                'action' => 'direct.ads.send',
                'status' => 'dry_run',
                'request_payload' => $payload,
                'response_payload' => ['message' => 'YANDEX_DIRECT_ENABLE_REAL_SEND=false. Реальная отправка отключена.'],
            ]);

            return [
                'sent' => false,
                'dry_run' => true,
                'message' => 'Реальная отправка отключена. Черновик проверен и готов к ручной отправке после настройки аккаунта.',
                'payload' => $payload,
            ];
        }

        $ad->update(['status' => YandexDirectAd::STATUS_SYNCING]);

        $response = $this->client->request($account, 'ads', 'add', ['Ads' => [$payload['ad']]]);

        $externalAdId = data_get($response, 'result.AddResults.0.Id');

        $ad->update([
            'external_ad_id' => $externalAdId,
            'status' => YandexDirectAd::STATUS_SENT,
            'last_synced_at' => now(),
            'error_message' => null,
        ]);

        return [
            'sent' => true,
            'dry_run' => false,
            'response' => $response,
            'ad' => $ad->fresh(),
        ];
    }

    public function suspendAd(YandexDirectAd $ad): YandexDirectAd
    {
        $ad->update(['status' => YandexDirectAd::STATUS_SUSPENDED]);

        return $ad->fresh();
    }

    public function resumeAd(YandexDirectAd $ad): YandexDirectAd
    {
        $ad->update(['status' => YandexDirectAd::STATUS_READY]);

        return $ad->fresh();
    }

    public function syncStatuses(?YandexAccount $account = null): array
    {
        $query = YandexDirectAd::query()
            ->with('adGroup.campaign.account')
            ->whereNotNull('external_ad_id');

        if ($account) {
            $query->whereHas('adGroup.campaign', fn ($q) => $q->where('yandex_account_id', $account->id));
        }

        $count = 0;

        foreach ($query->cursor() as $ad) {
            $ad->update(['last_synced_at' => now()]);
            $count++;
        }

        return ['synced' => $count, 'message' => 'MVP обновляет локальную дату синхронизации. Реальный Direct status sync требует настроенного OAuth-доступа.'];
    }

    private function buildDirectPayload(YandexDirectAd $ad): array
    {
        $keywords = $ad->adGroup->keywords->where('is_negative', false)->pluck('phrase')->values()->all();
        $minusKeywords = $ad->adGroup->keywords->where('is_negative', true)->pluck('phrase')->values()->all();

        return [
            'campaign' => [
                'name' => $ad->adGroup->campaign->name,
                'external_campaign_id' => $ad->adGroup->campaign->external_campaign_id,
            ],
            'ad_group' => [
                'name' => $ad->adGroup->name,
                'external_ad_group_id' => $ad->adGroup->external_ad_group_id,
                'region_ids' => $ad->adGroup->region_ids,
                'minus_keywords' => $minusKeywords,
            ],
            'keywords' => $keywords,
            'ad' => [
                'AdGroupId' => $ad->adGroup->external_ad_group_id,
                'TextAd' => [
                    'Title' => $ad->title_1,
                    'Title2' => $ad->title_2,
                    'Text' => $ad->text,
                    'Href' => $ad->href,
                    'Mobile' => 'NO',
                ],
            ],
        ];
    }
}
