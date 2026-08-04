<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\DTO\ExtractedRow;
use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Domain\AiPriceLists\Services\PriceListParserManager;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Domain\AiPriceLists\Services\StoredFileMaterializer;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;
use RuntimeException;

class ExtractPriceListContent extends AbstractPriceListJob
{
    public function handle(
        StoredFileMaterializer $files,
        PriceListParserManager $parsers,
        PriceListStateMachine $states,
        PriceListAuditLogger $audit,
    ): void {
        $import = PriceListImport::query()->findOrFail($this->importId);

        if (! in_array($import->status, [PriceListStatus::Extracting, PriceListStatus::Failed], true)) {
            return;
        }

        $import = $states->transition($import, PriceListStatus::Extracting, PriceListStage::Extract, 22);
        $started = hrtime(true);
        $result = $files->using($import->disk, $import->path, fn (string $path) => $parsers->parse($path, (string) $import->extension));

        $import->items()->whereNull('reviewed_at')->delete();

        foreach ($result->rows as $row) {
            $this->storeRawRow($import, $row);
        }

        $import->forceFill([
            'parser_type' => $result->parser,
            'extractor_version' => 'deterministic-v1',
            'requires_ocr' => $result->requiresOcr,
            'document_metadata' => array_merge($import->document_metadata ?: [], $result->metadata, ['parser_warnings' => $result->warnings]),
        ])->save();
        $audit->record($import, 'content_extracted', [
            'parser' => $result->parser,
            'rows' => count($result->rows),
            'requires_ocr' => $result->requiresOcr,
        ], durationMs: (int) round((hrtime(true) - $started) / 1_000_000), stage: PriceListStage::Extract->value);

        if ($result->requiresOcr) {
            $states->transition($import, PriceListStatus::Ocr, PriceListStage::Ocr, 30);
            $this->dispatchNext(new RecognizePriceListWithOcr($import->id));

            return;
        }

        if ($result->rows === []) {
            throw new RuntimeException('Документ не содержит извлекаемых строк.');
        }

        $states->transition($import, PriceListStatus::Normalizing, PriceListStage::Normalize, 45);
        $this->dispatchNext(new NormalizePriceListRows($import->id));
    }

    private function storeRawRow(PriceListImport $import, ExtractedRow $row): void
    {
        PriceListImportItem::query()->updateOrCreate([
            'price_list_import_id' => $import->id,
            'row_fingerprint' => $row->fingerprint(),
        ], [
            'position' => $row->position,
            'source_sheet' => $row->sheet,
            'source_page' => $row->page,
            'source_table' => $row->table,
            'source_row' => $row->row,
            'source_range' => $row->range,
            'raw_cells' => $row->cells,
            'raw_text' => mb_substr($row->text, 0, 10000),
            'field_evidence' => $row->evidence,
            'decision_status' => ItemDecisionStatus::Unreviewed,
            'match_class' => MatchClass::None,
        ]);
    }
}
