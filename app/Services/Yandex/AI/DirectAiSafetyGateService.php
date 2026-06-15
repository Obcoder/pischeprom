<?php

namespace App\Services\Yandex\AI;

use App\Models\YandexDirectAiDecision;
use App\Models\YandexDirectDailyStat;
use App\Services\Yandex\DirectSafetyGateService;

class DirectAiSafetyGateService
{
    public function __construct(private readonly DirectSafetyGateService $launchSafetyGate) {}

    public function inspect(YandexDirectAiDecision $decision, ?string $mode = null): array
    {
        $decision->loadMissing(['good.seo', 'account']);
        $mode ??= (string) config('yandex.direct.ai_autopilot.mode', 'monitor');
        $errors = [];
        $warnings = [];
        $type = $decision->type;

        if (!in_array($mode, ['monitor', 'assist', 'autopilot', 'full_auto'], true)) {
            $errors[] = "Неизвестный режим AI Autopilot: {$mode}.";
        }

        if ($mode === 'full_auto' && !config('yandex.direct.ai_autopilot.full_auto_enabled', false)) {
            $errors[] = 'FULL AUTO для AI Autopilot запрещён в MVP.';
        }

        if (!$decision->account || !$decision->account->is_active) {
            $errors[] = 'Нет активного Yandex Account для решения.';
        }

        if (!$decision->good) {
            $errors[] = 'Решение не связано с товаром.';
        }

        $duplicateCooldown = $this->cooldownViolation($decision);
        if ($duplicateCooldown) {
            $errors[] = $duplicateCooldown;
        }

        $spendGuard = $this->spendExplosionGuard($decision);
        if ($spendGuard) {
            $errors[] = $spendGuard;
        }

        if ($type === YandexDirectAiDecision::TYPE_LAUNCH && $decision->good && $decision->account) {
            $validation = $this->launchSafetyGate->validate($decision->good, $decision->account, [
                'dry_run' => true,
                'budget_approved' => false,
                'daily_budget' => (float) data_get($decision->expected_impact, 'daily_budget', config('yandex.direct.auto_launch.daily_budget', 300)),
            ]);

            $warnings = array_values(array_merge($warnings, $validation['warnings'] ?? []));
            if (!($validation['ok'] ?? false)) {
                $errors = array_values(array_merge($errors, $validation['errors'] ?? []));
            }
        }

        if (in_array($type, [
            YandexDirectAiDecision::TYPE_PAUSE,
            YandexDirectAiDecision::TYPE_SCALE,
            YandexDirectAiDecision::TYPE_OPTIMIZE,
            YandexDirectAiDecision::TYPE_KEYWORD_EXPANSION,
            YandexDirectAiDecision::TYPE_NEGATIVE_KEYWORDS,
        ], true)) {
            $dataErrors = $this->minimumDataErrors($decision);
            $errors = array_values(array_merge($errors, $dataErrors));
        }

        if ($type === YandexDirectAiDecision::TYPE_SCALE) {
            $warnings[] = 'Автоувеличение бюджета запрещено в MVP: approve фиксирует рекомендацию, но бюджет не меняется автоматически.';
        }

        if ($type === YandexDirectAiDecision::TYPE_PAUSE && !config('yandex.direct.ai_autopilot.allow_auto_pause', false)) {
            $warnings[] = 'Автопауза выключена: решение будет только рекомендацией.';
        }

        $executable = count($errors) === 0 && $this->isExecutableByMode($decision, $mode);

        return [
            'ok' => count($errors) === 0,
            'executable' => $executable,
            'mode' => $mode,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function isExecutableByMode(YandexDirectAiDecision $decision, string $mode): bool
    {
        if ($mode !== 'autopilot') {
            return false;
        }

        // MVP safe action: build/validate launch dry-run only. No budget mutation and no campaign deletion.
        return $decision->type === YandexDirectAiDecision::TYPE_LAUNCH
            && $decision->risk_level === YandexDirectAiDecision::RISK_LOW
            && $decision->confidence_score >= (int) config('yandex.direct.ai_autopilot.auto_execute_min_confidence', 85);
    }

    private function minimumDataErrors(YandexDirectAiDecision $decision): array
    {
        $stats = $decision->signals['stats'] ?? [];
        $clicks = (int) ($stats['clicks'] ?? 0);
        $daysWithData = (int) ($stats['days_with_data'] ?? 0);
        $minClicks = (int) config('yandex.direct.ai_autopilot.min_clicks_threshold', 30);
        $minDays = (int) config('yandex.direct.ai_autopilot.min_learning_days', 3);
        $errors = [];

        if ($clicks < $minClicks) {
            $errors[] = "Недостаточно кликов для performance-действия: {$clicks}/{$minClicks}.";
        }

        if ($daysWithData < $minDays) {
            $errors[] = "Недостаточно дней обучения: {$daysWithData}/{$minDays}.";
        }

        return $errors;
    }

    private function cooldownViolation(YandexDirectAiDecision $decision): ?string
    {
        if (!$decision->good_id) {
            return null;
        }

        $hours = (int) config('yandex.direct.ai_autopilot.cooldown_hours', 24);
        $exists = YandexDirectAiDecision::query()
            ->where('good_id', $decision->good_id)
            ->where('type', $decision->type)
            ->where('id', '!=', $decision->id)
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereIn('status', [
                YandexDirectAiDecision::STATUS_PENDING,
                YandexDirectAiDecision::STATUS_APPROVED,
                YandexDirectAiDecision::STATUS_EXECUTED,
            ])
            ->exists();

        return $exists ? "Cooldown активен: похожее решение уже было создано за последние {$hours} ч." : null;
    }

    private function spendExplosionGuard(YandexDirectAiDecision $decision): ?string
    {
        $limit = (float) config('yandex.direct.ai_autopilot.max_daily_spend', 1500);
        $spentToday = (float) YandexDirectDailyStat::query()
            ->where('yandex_account_id', $decision->yandex_account_id)
            ->whereDate('date', now()->toDateString())
            ->sum('cost');

        return $spentToday > $limit
            ? "Spend guard: расход за сегодня {$spentToday} выше лимита {$limit}."
            : null;
    }
}
