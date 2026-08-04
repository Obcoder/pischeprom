<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Exceptions\InvalidPriceListTransition;
use App\Models\PriceListImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PriceListStateMachine
{
    /** @var array<string, list<PriceListStatus>> */
    private const TRANSITIONS = [
        'received' => [PriceListStatus::Queued, PriceListStatus::AwaitingClassification, PriceListStatus::UnsupportedFormat, PriceListStatus::Quarantined, PriceListStatus::Failed, PriceListStatus::Cancelled],
        'queued' => [PriceListStatus::Validating, PriceListStatus::Failed, PriceListStatus::Cancelled],
        'validating' => [PriceListStatus::AwaitingClassification, PriceListStatus::Extracting, PriceListStatus::Ocr, PriceListStatus::UnsupportedFormat, PriceListStatus::Quarantined, PriceListStatus::Failed, PriceListStatus::Cancelled],
        'awaiting_classification' => [PriceListStatus::Queued, PriceListStatus::Extracting, PriceListStatus::NotAPriceList, PriceListStatus::Cancelled],
        'extracting' => [PriceListStatus::Ocr, PriceListStatus::Normalizing, PriceListStatus::Failed, PriceListStatus::Cancelled],
        'ocr' => [PriceListStatus::Normalizing, PriceListStatus::Failed, PriceListStatus::Cancelled],
        'normalizing' => [PriceListStatus::Matching, PriceListStatus::Failed, PriceListStatus::Cancelled],
        'matching' => [PriceListStatus::SupplierUnresolved, PriceListStatus::ReviewRequired, PriceListStatus::Failed, PriceListStatus::Cancelled],
        'supplier_unresolved' => [PriceListStatus::ReviewRequired, PriceListStatus::Queued, PriceListStatus::Cancelled],
        'review_required' => [PriceListStatus::ReadyToApply, PriceListStatus::Queued, PriceListStatus::Cancelled],
        'ready_to_apply' => [PriceListStatus::Applying, PriceListStatus::ReviewRequired, PriceListStatus::Cancelled],
        'applying' => [PriceListStatus::Applied, PriceListStatus::PartiallyApplied, PriceListStatus::Failed],
        'partially_applied' => [PriceListStatus::Applying, PriceListStatus::ReviewRequired],
        'unsupported_format' => [PriceListStatus::Queued, PriceListStatus::Cancelled],
        'quarantined' => [PriceListStatus::Queued, PriceListStatus::Cancelled],
        'failed' => [PriceListStatus::Queued, PriceListStatus::Validating, PriceListStatus::Extracting, PriceListStatus::Ocr, PriceListStatus::Normalizing, PriceListStatus::Matching, PriceListStatus::Applying, PriceListStatus::Cancelled],
        'not_a_price_list' => [PriceListStatus::Queued, PriceListStatus::Cancelled],
        'cancelled' => [PriceListStatus::Queued],
        'applied' => [],
    ];

    public function __construct(
        private readonly PriceListAuditLogger $audit,
        private readonly PriceListStatusNotificationService $notifications,
    ) {}

    public function canTransition(PriceListStatus $from, PriceListStatus $to): bool
    {
        return $from === $to || in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(
        PriceListImport $import,
        PriceListStatus $to,
        ?PriceListStage $stage = null,
        int $progress = 0,
        array $metadata = [],
        User|int|null $user = null,
        ?string $correlationId = null,
    ): PriceListImport {
        return DB::transaction(function () use ($import, $to, $stage, $progress, $metadata, $user, $correlationId): PriceListImport {
            /** @var PriceListImport $locked */
            $locked = PriceListImport::query()->lockForUpdate()->findOrFail($import->id);
            $from = $locked->status;

            if (! $this->canTransition($from, $to)) {
                throw new InvalidPriceListTransition("Transition {$from->value} -> {$to->value} is not allowed.");
            }

            if ($from === $to) {
                $locked->forceFill([
                    'stage_heartbeat_at' => now(),
                    'progress' => max((int) $locked->progress, min(100, max(0, $progress))),
                ])->save();

                return $locked->refresh();
            }

            $attributes = [
                'status' => $to,
                'current_stage' => $stage?->value,
                'progress' => min(100, max(0, $progress)),
                'stage_started_at' => now(),
                'stage_heartbeat_at' => now(),
            ];

            if (! in_array($to, [PriceListStatus::Failed, PriceListStatus::UnsupportedFormat, PriceListStatus::Quarantined], true)) {
                $attributes['error_code'] = null;
                $attributes['error_message'] = null;
                $attributes['error_retryable'] = false;
            }

            if (in_array($to, [PriceListStatus::ReviewRequired, PriceListStatus::SupplierUnresolved], true)) {
                $attributes['processing_completed_at'] = now();
            }

            if ($to === PriceListStatus::Cancelled) {
                $attributes['cancelled_at'] = now();
            }

            if ($to === PriceListStatus::Applied) {
                $attributes['applied_at'] = now();
                $attributes['progress'] = 100;
            }

            $locked->forceFill($attributes)->save();
            $this->audit->record(
                import: $locked,
                eventType: 'status_changed',
                metadata: $metadata,
                user: $user,
                correlationId: $correlationId,
                statusFrom: $from->value,
                statusTo: $to->value,
                stage: $stage?->value,
            );
            if ($to !== PriceListStatus::Failed) {
                $this->notifications->statusChanged($locked, $to);
            }

            return $locked->refresh();
        }, 3);
    }

    public function fail(
        PriceListImport $import,
        string $code,
        string $safeMessage,
        bool $retryable,
        array $metadata = [],
    ): PriceListImport {
        $stage = PriceListStage::tryFrom((string) $import->current_stage);
        $failed = $this->transition($import, PriceListStatus::Failed, $stage, (int) $import->progress, $metadata);
        $failed->forceFill([
            'error_code' => $code,
            'error_message' => mb_substr($safeMessage, 0, 2000),
            'error_retryable' => $retryable,
        ])->save();
        $this->notifications->statusChanged($failed, PriceListStatus::Failed);

        return $failed->refresh();
    }
}
