<?php

namespace App\Services\Yandex;

use App\Models\DirectLaunchSession;
use App\Models\Good;
use App\Models\YandexAccount;
use App\Models\YandexDirectCampaign;
use Throwable;

class DirectSafetyGateService
{
    public function __construct(
        private readonly DirectIntelligentKeywordBuilderService $keywordBuilder,
        private readonly GoodDirectDraftService $draftService,
        private readonly DirectRegionResolverService $regionResolver,
    ) {}

    public function validate(Good $good, YandexAccount $account, array $options = []): array
    {
        $good->loadMissing('seo');
        $seo = $good->seo;
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $budgetApproved = (bool) ($options['budget_approved'] ?? false);
        $dailyBudget = (float) ($options['daily_budget'] ?? config('yandex.direct.auto_launch.daily_budget', 300));
        $maxDailyBudget = (float) config('yandex.direct.auto_launch.max_daily_budget', 500);
        $errors = [];
        $warnings = [];

        if (!$account->is_active || blank($account->access_token)) {
            $errors[] = 'Не подключён активный OAuth-аккаунт Яндекса.';
        }

        if (!$seo) {
            $errors[] = 'У товара нет SEO-карточки.';
        } else {
            if (blank($seo->h1) && blank($good->name)) {
                $errors[] = 'Не заполнен SEO H1 или название товара.';
            }

            if (blank($seo->yandex_direct_title_1)) {
                $warnings[] = 'Заголовок Директ 1 пустой, будет использовано название товара.';
            }

            if (blank($seo->yandex_direct_text) && blank($seo->short_seo_text)) {
                $errors[] = 'Не заполнен текст Директ или короткий SEO-текст.';
            }

            if (!$this->keywordBuilder->hasKeywords($seo)) {
                $errors[] = 'Нет ключевых фраз в search_queries или keywords.';
            }

            if (!$this->keywordBuilder->hasSemanticCore($seo)) {
                $errors[] = 'Нет semantic_core. Автозапуск без семантического ядра заблокирован.';
            }
        }

        if (blank($seo?->og_image ?: $good->ava_image ?: $good->ava_thumb)) {
            $errors[] = 'Нет изображения товара для рекламного предпросмотра.';
        }

        try {
            $href = $this->draftService->buildHrefWithUtm($good, $seo?->utm_template);
            if (!filter_var($href, FILTER_VALIDATE_URL)) {
                $errors[] = 'Публичный URL товара некорректен.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Не удалось собрать публичный URL товара: ' . $e->getMessage();
        }

        if ($dailyBudget <= 0) {
            $errors[] = 'Дневной бюджет должен быть больше 0.';
        }

        if ($dailyBudget > $maxDailyBudget) {
            $errors[] = "Дневной бюджет {$dailyBudget} превышает guard-лимит {$maxDailyBudget}.";
        }

        if (!$dryRun && !$budgetApproved) {
            $errors[] = 'Для реального запуска нужно явное подтверждение budget_approved=true.';
        }

        if (!$dryRun && !config('yandex.direct.enable_real_send')) {
            $errors[] = 'Реальная отправка отключена: YANDEX_DIRECT_ENABLE_REAL_SEND=false.';
        }

        $regionIds = $this->regionResolver->regionIds();
        if (!filled($regionIds)) {
            $message = 'Не заданы регионы показа: включите регионы в Geography / Regions или заполните YANDEX_DIRECT_DEFAULT_REGION_IDS / YANDEX_DIRECT_AUTO_REGION_IDS.';
            $dryRun ? $warnings[] = $message : $errors[] = $message;
        }

        $hasSuccessfulLaunch = DirectLaunchSession::query()
            ->where('good_id', $good->id)
            ->where('yandex_account_id', $account->id)
            ->where('status', DirectLaunchSession::STATUS_SUCCESS)
            ->exists();

        $rolledBackSessionIds = DirectLaunchSession::query()
            ->where('good_id', $good->id)
            ->where('yandex_account_id', $account->id)
            ->whereIn('status', [DirectLaunchSession::STATUS_FAILED, DirectLaunchSession::STATUS_PARTIAL])
            ->whereNotNull('rollback_payload')
            ->pluck('id')
            ->all();

        $hasExternalCampaign = YandexDirectCampaign::query()
            ->where('good_id', $good->id)
            ->where('yandex_account_id', $account->id)
            ->whereNotNull('external_campaign_id')
            ->whereNotIn('status', ['error', 'suspended'])
            ->when($rolledBackSessionIds, function ($query) use ($rolledBackSessionIds) {
                $query->where(function ($query) use ($rolledBackSessionIds) {
                    $query->whereNull('settings->launch_session_id')
                        ->orWhereNotIn('settings->launch_session_id', $rolledBackSessionIds);
                });
            })
            ->exists();

        if ($hasSuccessfulLaunch || $hasExternalCampaign) {
            $message = 'Для товара уже есть успешный запуск или внешняя кампания Direct. Повторный автозапуск может создать дубль.';
            $dryRun ? $warnings[] = $message : $errors[] = $message;
        }

        if (!filter_var((string) config('app.url'), FILTER_VALIDATE_URL)) {
            $warnings[] = 'APP_URL выглядит некорректно. Проверь публичный домен перед реальным запуском.';
        }

        return [
            'ok' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
