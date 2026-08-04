<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Matching\PriceListCandidateReranker;
use App\Domain\AiPriceLists\Matching\PriceListItemMatcher;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Models\PriceListImport;
use Illuminate\Queue\Middleware\RateLimited;

class MatchPriceListItems extends AbstractPriceListJob
{
    public function middleware(): array
    {
        return [...parent::middleware(), (new RateLimited('price-list-ai'))->releaseAfter(30)];
    }

    public function handle(PriceListItemMatcher $matcher, PriceListCandidateReranker $reranker, PriceListStateMachine $states, PriceListAuditLogger $audit): void
    {
        $import = PriceListImport::query()->findOrFail($this->importId);

        if (! in_array($import->status, [PriceListStatus::Matching, PriceListStatus::Failed], true)) {
            return;
        }

        $import = $states->transition($import, PriceListStatus::Matching, PriceListStage::Match, 80);
        $stats = $matcher->matchImport($import);
        $stats['ai_reranking'] = $reranker->rerank($import);
        $audit->record($import, 'items_matched', $stats, stage: PriceListStage::Match->value);
        $this->dispatchNext(new FinalizePriceListForReview($import->id));
    }
}
