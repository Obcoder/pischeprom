<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\MaxPriceListNotifier;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Domain\AiPriceLists\Services\PriceListFileValidator;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Models\PriceListImport;

class ValidatePriceListFile extends AbstractPriceListJob
{
    public function handle(PriceListFileValidator $validator, PriceListStateMachine $states, PriceListAuditLogger $audit, MaxPriceListNotifier $notifier): void
    {
        $import = PriceListImport::query()->findOrFail($this->importId);

        if (! in_array($import->status, [PriceListStatus::Queued, PriceListStatus::Failed, PriceListStatus::Validating], true)) {
            return;
        }

        $import = $states->transition($import, PriceListStatus::Validating, PriceListStage::Validate, 8);
        $started = hrtime(true);
        $result = $validator->validate($import->disk, $import->path, $import->original_name);
        $duplicate = $result->sha256
            ? PriceListImport::query()->where('sha256', $result->sha256)->whereKeyNot($import->id)->oldest('id')->first()
            : null;

        $import->forceFill([
            'safe_name' => $validator->safeDisplayName($import->original_name),
            'extension' => $result->extension,
            'mime_type' => $result->mimeType,
            'size_bytes' => $result->sizeBytes,
            'sha256' => $result->sha256,
            'requires_ocr' => $result->requiresOcr,
            'duplicate_of_id' => $duplicate?->id,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
        ])->save();

        $audit->record($import, 'file_validated', [
            'valid' => $result->valid,
            'mime_type' => $result->mimeType,
            'size_bytes' => $result->sizeBytes,
            'duplicate_of_uuid' => $duplicate?->uuid,
        ], durationMs: (int) round((hrtime(true) - $started) / 1_000_000), stage: PriceListStage::Validate->value);

        if (! $result->valid) {
            $target = $result->quarantined ? PriceListStatus::Quarantined : ($result->unsupported ? PriceListStatus::UnsupportedFormat : PriceListStatus::Failed);
            $states->transition($import->refresh(), $target, PriceListStage::Validate, 100);
            $import->forceFill([
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'error_retryable' => false,
            ])->save();
            $notifier->failed($import->refresh(), $result->errorMessage ?: 'Файл не удалось обработать.');

            return;
        }

        $this->dispatchNext(new ClassifyPriceListDocument($import->id));
    }
}
