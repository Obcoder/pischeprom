<?php

namespace App\Services\Yandex;

use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\YandexAccount;
use App\Models\YandexDirectAd;
use App\Models\YandexDirectAdGroup;
use App\Models\YandexDirectCampaign;
use App\Models\YandexDirectKeyword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GoodDirectDraftService
{
    public function __construct(private readonly YandexDirectKeywordSanitizer $keywordSanitizer) {}

    public function generateForGood(Good $good, ?YandexAccount $account = null): YandexDirectAd
    {
        $account ??= YandexAccount::query()->where('is_active', true)->latest('last_checked_at')->latest()->first();

        if (!$account) {
            throw new RuntimeException('Не подключён аккаунт Яндекса. Сначала подключите OAuth в разделе Маркетинг.');
        }

        $good->loadMissing(['seo', 'products.category']);
        $seo = $good->seo ?: $good->seo()->create([
            'h1' => $good->name,
            'robots' => 'index,follow',
            'is_active' => true,
            'include_in_sitemap' => true,
            'include_in_yandex_feed' => true,
            'availability_status' => 'on_request',
        ]);

        return DB::transaction(function () use ($good, $seo, $account) {
            $campaign = YandexDirectCampaign::query()->firstOrCreate([
                'yandex_account_id' => $account->id,
                'good_id' => $good->id,
            ], [
                'name' => $this->campaignName($good),
                'type' => 'TEXT_CAMPAIGN',
                'status' => YandexDirectAd::STATUS_DRAFT,
                'daily_budget' => null,
                'region_ids' => config('yandex.default_region_ids'),
                'settings' => [],
            ]);

            $adGroup = YandexDirectAdGroup::query()->firstOrCreate([
                'yandex_direct_campaign_id' => $campaign->id,
                'good_id' => $good->id,
            ], [
                'name' => $this->adGroupName($good, $seo),
                'status' => YandexDirectAd::STATUS_DRAFT,
                'region_ids' => config('yandex.default_region_ids'),
                'minus_keywords' => implode("\n", $this->generateMinusKeywords($good)),
                'settings' => [],
            ]);

            $ad = YandexDirectAd::query()
                ->where('yandex_direct_ad_group_id', $adGroup->id)
                ->where('good_id', $good->id)
                ->first() ?: new YandexDirectAd([
                    'yandex_direct_ad_group_id' => $adGroup->id,
                    'good_id' => $good->id,
                ]);

            $ad->fill([
                'good_seo_id' => $seo->id,
                'title_1' => $this->limit($seo->yandex_direct_title_1 ?: $good->name, config('yandex.limits.title_1')),
                'title_2' => $this->limit($seo->yandex_direct_title_2 ?: 'Опт и розница', config('yandex.limits.title_2')),
                'text' => $this->limit($seo->yandex_direct_text ?: $this->fallbackText($good, $seo), config('yandex.limits.text')),
                'href' => $this->buildHrefWithUtm($good, $seo->utm_template),
                'utm_template' => $seo->utm_template ?: config('yandex.default_utm_template'),
                'image_url' => $seo->og_image ?: $good->ava_image ?: $good->ava_thumb,
                'status' => YandexDirectAd::STATUS_DRAFT,
                'validation_errors' => null,
                'error_message' => null,
            ])->save();

            $this->syncKeywords($adGroup, $good, $seo);
            $this->validateAndPersist($ad);

            return $ad->fresh(['good.products.category', 'seo', 'adGroup.campaign.account', 'adGroup.keywords']);
        });
    }

    public function generateKeywordsFromSeo(GoodSeo $seo): array
    {
        return collect()
            ->merge($this->normalizePhraseList($seo->search_queries))
            ->merge($this->normalizePhraseList($seo->keywords))
            ->merge($this->normalizePhraseList($seo->semantic_core))
            ->map(fn (string $phrase) => trim(mb_strtolower($phrase)))
            ->map(fn (string $phrase) => $this->keywordSanitizer->plain($phrase))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function generateMinusKeywords(Good $good): array
    {
        return collect(config('yandex.default_minus_keywords', []))
            ->map(fn ($phrase) => $this->keywordSanitizer->negative((string) $phrase))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function buildHrefWithUtm(Good $good, ?string $utmTemplate): string
    {
        $good->loadMissing('seo');
        $slug = $good->seo?->is_active && filled($good->seo?->slug_override)
            ? trim($good->seo->slug_override)
            : $good->slug;

        $url = route('public.goods.show', ['good' => $slug]);
        $template = trim($utmTemplate ?: config('yandex.default_utm_template'));

        if ($template === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . ltrim($template, '?&');
    }

    public function validateAd(YandexDirectAd $ad): array
    {
        $limits = config('yandex.limits');
        $errors = [];

        foreach ([
            'title_1' => 'Заголовок 1',
            'title_2' => 'Заголовок 2',
            'text' => 'Текст объявления',
        ] as $field => $label) {
            $value = (string) $ad->{$field};
            $limit = (int) ($limits[$field] ?? 0);

            if ($limit > 0 && mb_strlen($value) > $limit) {
                $errors[$field][] = "{$label}: превышен лимит {$limit} символов.";
            }
        }

        if (blank($ad->href) || !filter_var($ad->href, FILTER_VALIDATE_URL)) {
            $errors['href'][] = 'URL объявления должен быть валидным абсолютным URL.';
        }

        if (blank($ad->title_1)) {
            $errors['title_1'][] = 'Заголовок 1 обязателен.';
        }

        if (blank($ad->text)) {
            $errors['text'][] = 'Текст объявления обязателен.';
        }

        return $errors;
    }

    public function validateAndPersist(YandexDirectAd $ad): array
    {
        $errors = $this->validateAd($ad);

        $ad->update([
            'validation_errors' => $errors ?: null,
            'status' => $errors ? YandexDirectAd::STATUS_DRAFT : YandexDirectAd::STATUS_READY,
        ]);

        return $errors;
    }

    private function syncKeywords(YandexDirectAdGroup $adGroup, Good $good, GoodSeo $seo): void
    {
        $adGroup->keywords()->delete();

        foreach ($this->generateKeywordsFromSeo($seo) as $phrase) {
            YandexDirectKeyword::query()->create([
                'yandex_direct_ad_group_id' => $adGroup->id,
                'good_id' => $good->id,
                'phrase' => $phrase,
                'status' => YandexDirectAd::STATUS_DRAFT,
                'is_negative' => false,
            ]);
        }

        foreach ($this->generateMinusKeywords($good) as $phrase) {
            YandexDirectKeyword::query()->create([
                'yandex_direct_ad_group_id' => $adGroup->id,
                'good_id' => $good->id,
                'phrase' => $phrase,
                'status' => YandexDirectAd::STATUS_DRAFT,
                'is_negative' => true,
            ]);
        }
    }

    private function campaignName(Good $good): string
    {
        return $this->limit('Goods: ' . $good->name, 255);
    }

    private function adGroupName(Good $good, GoodSeo $seo): string
    {
        return $this->limit($seo->h1 ?: $good->name, 255);
    }

    private function fallbackText(Good $good, GoodSeo $seo): string
    {
        $source = trim((string) ($seo->short_seo_text ?: $good->description));
        $source = preg_replace('/\s+/u', ' ', strip_tags($source ?: 'Поставки для пищевой промышленности. Опт и розница.'));

        return $source ?: 'Поставки для пищевой промышленности. Опт и розница.';
    }

    private function normalizePhraseList(mixed $value): array
    {
        if (is_string($value)) {
            return preg_split('/[\r\n,;]+/u', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(function ($item) {
                if (is_array($item)) {
                    return $this->normalizePhraseList(implode("\n", $item));
                }

                return $this->normalizePhraseList((string) $item);
            })
            ->all();
    }

    private function limit(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?: '');

        return Str::limit($value, $limit, '');
    }
}
