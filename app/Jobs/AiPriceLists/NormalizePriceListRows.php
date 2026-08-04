<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Domain\AiPriceLists\Normalization\PriceListRowNormalizer;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Domain\AiPriceLists\Services\StructuredPriceListExtractor;
use App\Models\PriceListImport;
use Illuminate\Queue\Middleware\RateLimited;
use RuntimeException;

class NormalizePriceListRows extends AbstractPriceListJob
{
    public function middleware(): array
    {
        return [...parent::middleware(), (new RateLimited('price-list-ai'))->releaseAfter(30)];
    }

    public function handle(
        PriceListRowNormalizer $normalizer,
        StructuredPriceListExtractor $structured,
        PriceListStateMachine $states,
        PriceListAuditLogger $audit,
    ): void {
        $import = PriceListImport::query()->findOrFail($this->importId);

        if (! in_array($import->status, [PriceListStatus::Normalizing, PriceListStatus::Failed], true)) {
            return;
        }

        $import = $states->transition($import, PriceListStatus::Normalizing, PriceListStage::Normalize, 60);
        $result = $normalizer->normalize($import);

        if ($result['product_rows'] === 0) {
            if (! $structured->configured()) {
                $states->fail($import, 'ai_required_not_configured', 'Структура документа неоднозначна, а Yandex AI Studio не настроен.', false);

                return;
            }

            try {
                $result['product_rows'] = $structured->extract($import);
            } catch (ExternalAiException $exception) {
                $states->fail($import, $exception->errorCode, $exception->getMessage(), $exception->retryable, ['external_request_id' => $exception->externalRequestId]);

                if ($exception->retryable) {
                    throw $exception;
                }

                return;
            }
        }

        if ($result['product_rows'] === 0) {
            throw new RuntimeException('В документе не найдено товарных строк.');
        }

        $audit->record($import, 'rows_normalized', $result, stage: PriceListStage::Normalize->value);
        $states->transition($import, PriceListStatus::Matching, PriceListStage::Match, 75);
        $this->dispatchNext(new MatchPriceListItems($import->id));
    }
}
