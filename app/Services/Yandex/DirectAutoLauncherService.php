<?php

namespace App\Services\Yandex;

use App\Jobs\SyncYandexDirectStatusesJob;
use App\Models\DirectLaunchSession;
use App\Models\Good;
use App\Models\YandexAccount;
use App\Models\YandexDirectAd;
use App\Models\YandexDirectAdGroup;
use App\Models\YandexDirectCampaign;
use App\Models\YandexDirectKeyword;
use App\Models\YandexSyncLog;
use Throwable;

class DirectAutoLauncherService
{
    public function __construct(
        private readonly DirectSafetyGateService $safetyGate,
        private readonly DirectCampaignBuilderService $builder,
    ) {}

    public function launch(Good $good, ?YandexAccount $account = null, array $options = []): array
    {
        $account ??= $this->activeAccount();
        $dryRun = array_key_exists('dry_run', $options)
            ? (bool) $options['dry_run']
            : (bool) config('yandex.direct.auto_launch.dry_run', true);

        $session = DirectLaunchSession::query()->create([
            'good_id' => $good->id,
            'yandex_account_id' => $account?->id,
            'status' => DirectLaunchSession::STATUS_PENDING,
            'step' => 'created',
            'payload' => [
                'options' => [
                    ...$options,
                    'dry_run' => $dryRun,
                    'budget_approved' => (bool) ($options['budget_approved'] ?? false),
                ],
            ],
        ]);

        if (!$account) {
            return $this->fail($session, 'Не подключён аккаунт Яндекса.', null, DirectLaunchSession::STATUS_FAILED);
        }

        try {
            $validation = $this->validateBeforeLaunch($good, $account, [
                ...$options,
                'dry_run' => $dryRun,
            ]);

            $session->update([
                'status' => DirectLaunchSession::STATUS_VALIDATING,
                'step' => 'safety_gate',
                'payload' => [
                    ...($session->payload ?: []),
                    'safety_gate' => $validation,
                ],
            ]);

            $this->log($session, $account, 'direct.auto_launch.safety_gate', $validation['ok'] ? 'success' : 'error', null, $validation, $validation['ok'] ? null : implode('; ', $validation['errors']));

            if (!$validation['ok']) {
                return $this->fail($session, 'Автозапуск остановлен safety gate.', [
                    'safety_gate' => $validation,
                ], DirectLaunchSession::STATUS_FAILED);
            }

            $result = $this->executePipeline($good, $account, $session, [
                ...$options,
                'dry_run' => $dryRun,
            ]);

            $status = $dryRun ? DirectLaunchSession::STATUS_DRY_RUN : DirectLaunchSession::STATUS_SUCCESS;
            $session->update([
                'status' => $status,
                'step' => $dryRun ? 'dry_run_completed' : 'launched',
                'error_message' => null,
                'payload' => [
                    ...($session->payload ?: []),
                    'result' => $result,
                ],
                'external_ids' => $result['external_ids'] ?? [],
                'finished_at' => now(),
            ]);

            $this->log($session, $account, 'direct.auto_launch.completed', $status, null, $result);

            if (!$dryRun) {
                SyncYandexDirectStatusesJob::dispatch($account->id);
            }

            return [
                'session' => $session->fresh(['good', 'account']),
                'status' => $status,
                'dry_run' => $dryRun,
                'message' => $dryRun
                    ? 'FULL AUTO LAUNCH dry-run выполнен. Структура создана локально, в Яндекс ничего не отправлено.'
                    : 'FULL AUTO LAUNCH выполнен. Структура отправлена в Яндекс.Директ.',
                'warnings' => $validation['warnings'] ?? [],
                'result' => $result,
            ];
        } catch (Throwable $e) {
            $externalIds = $session->fresh()?->external_ids ?: [];
            $rollback = [];

            if (!$dryRun && $externalIds) {
                $rollback = $this->rollbackOnError($account, $session, $externalIds);
            }

            $this->markLocalStructureFailed($session, $e->getMessage());

            $status = $externalIds ? DirectLaunchSession::STATUS_PARTIAL : DirectLaunchSession::STATUS_FAILED;

            return $this->fail($session, $e->getMessage(), [
                'external_ids' => $externalIds,
                'rollback' => $rollback,
            ], $status);
        }
    }

    public function validateBeforeLaunch(Good $good, YandexAccount $account, array $options = []): array
    {
        return $this->safetyGate->validate($good, $account, $options);
    }

    public function executePipeline(Good $good, YandexAccount $account, DirectLaunchSession $session, array $options = []): array
    {
        return $this->builder->build($good, $account, $session, $options);
    }

    public function rollbackOnError(YandexAccount $account, DirectLaunchSession $session, array $externalIds): array
    {
        $session->update(['step' => 'rollback']);
        $rollback = $this->builder->rollbackOnError($account, $session, $externalIds);
        $this->log($session, $account, 'direct.auto_launch.rollback', empty($rollback) ? 'skipped' : 'completed', ['external_ids' => $externalIds], $rollback);

        return $rollback;
    }

    private function activeAccount(): ?YandexAccount
    {
        return YandexAccount::query()
            ->where('is_active', true)
            ->whereNotNull('access_token')
            ->latest('last_checked_at')
            ->latest()
            ->first();
    }

    private function fail(DirectLaunchSession $session, string $message, ?array $payload = null, string $status = DirectLaunchSession::STATUS_FAILED): array
    {
        $session->update([
            'status' => $status,
            'step' => $session->step ?: 'failed',
            'error_message' => $message,
            'payload' => [
                ...($session->payload ?: []),
                'failure' => $payload,
            ],
            'finished_at' => now(),
        ]);

        if ($session->yandex_account_id) {
            $this->log($session, $session->account, 'direct.auto_launch.failed', $status, null, $payload, $message);
        }

        return [
            'session' => $session->fresh(['good', 'account']),
            'status' => $status,
            'dry_run' => (bool) data_get($session->payload, 'options.dry_run', true),
            'message' => $message,
            'errors' => data_get($payload, 'safety_gate.errors', []),
            'warnings' => data_get($payload, 'safety_gate.warnings', []),
            'rollback' => data_get($payload, 'rollback', []),
        ];
    }

    private function markLocalStructureFailed(DirectLaunchSession $session, string $message): void
    {
        $campaignIds = YandexDirectCampaign::query()
            ->where('settings->launch_session_id', $session->id)
            ->pluck('id');

        $groupIds = YandexDirectAdGroup::query()
            ->whereIn('yandex_direct_campaign_id', $campaignIds)
            ->orWhere('settings->launch_session_id', $session->id)
            ->pluck('id');

        if ($groupIds->isNotEmpty()) {
            YandexDirectKeyword::query()
                ->whereIn('yandex_direct_ad_group_id', $groupIds)
                ->whereNull('external_keyword_id')
                ->update(['status' => YandexDirectAd::STATUS_ERROR]);

            YandexDirectAd::query()
                ->whereIn('yandex_direct_ad_group_id', $groupIds)
                ->update([
                    'status' => YandexDirectAd::STATUS_ERROR,
                    'error_message' => $message,
                ]);

            YandexDirectAdGroup::query()
                ->whereIn('id', $groupIds)
                ->update([
                    'status' => YandexDirectAd::STATUS_ERROR,
                    'error_message' => $message,
                ]);
        }

        if ($campaignIds->isNotEmpty()) {
            YandexDirectCampaign::query()
                ->whereIn('id', $campaignIds)
                ->update([
                    'status' => YandexDirectAd::STATUS_ERROR,
                    'error_message' => $message,
                ]);
        }
    }

    private function log(DirectLaunchSession $session, ?YandexAccount $account, string $action, string $status, ?array $request = null, ?array $response = null, ?string $error = null): void
    {
        YandexSyncLog::query()->create([
            'yandex_account_id' => $account?->id,
            'entity_type' => DirectLaunchSession::class,
            'entity_id' => $session->id,
            'action' => $action,
            'status' => $status,
            'request_payload' => $request,
            'response_payload' => $response,
            'error_message' => $error,
        ]);
    }
}
