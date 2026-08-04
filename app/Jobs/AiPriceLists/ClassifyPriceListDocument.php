<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Enums\DocumentClass;
use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\PriceListAiClassifier;
use App\Domain\AiPriceLists\Services\PriceListDocumentClassifier;
use App\Domain\AiPriceLists\Services\PriceListParserManager;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Domain\AiPriceLists\Services\StoredFileMaterializer;
use App\Models\PriceListImport;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

class ClassifyPriceListDocument extends AbstractPriceListJob
{
    public function middleware(): array
    {
        return [...parent::middleware(), (new RateLimited('price-list-ai'))->releaseAfter(30)];
    }

    public function handle(
        PriceListDocumentClassifier $classifier,
        PriceListAiClassifier $aiClassifier,
        PriceListParserManager $parsers,
        StoredFileMaterializer $files,
        PriceListStateMachine $states,
    ): void {
        $import = PriceListImport::query()->findOrFail($this->importId);

        if (! in_array($import->status, [PriceListStatus::Validating, PriceListStatus::Failed, PriceListStatus::AwaitingClassification], true)) {
            return;
        }

        $class = $import->document_class;

        if ($class === DocumentClass::Uncertain && ! $import->requires_ocr) {
            try {
                $extraction = $files->using(
                    $import->disk,
                    $import->path,
                    fn (string $path) => $parsers->parse($path, (string) $import->extension),
                );
                $class = $classifier->classifyExtraction($extraction);

                if ($class === DocumentClass::Uncertain && $aiClassifier->configured()) {
                    $class = $aiClassifier->classify($import, $extraction);
                }

                $import->forceFill(['document_class' => $class])->save();
            } catch (Throwable) {
                $class = DocumentClass::Uncertain;
            }
        }

        if ($class === DocumentClass::NotPriceList) {
            $states->transition($import, PriceListStatus::NotAPriceList, PriceListStage::Classify, 100);

            return;
        }

        if ($class === DocumentClass::Uncertain) {
            $states->transition($import, PriceListStatus::AwaitingClassification, PriceListStage::Classify, 12);

            return;
        }

        $states->transition($import, PriceListStatus::Extracting, PriceListStage::Extract, 18);
        $this->dispatchNext(new ExtractPriceListContent($import->id));
    }
}
