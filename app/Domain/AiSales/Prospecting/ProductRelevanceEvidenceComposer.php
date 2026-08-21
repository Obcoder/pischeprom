<?php

namespace App\Domain\AiSales\Prospecting;

use App\Models\ProspectingSearchResult;
use Illuminate\Support\Collection;

final class ProductRelevanceEvidenceComposer
{
    /**
     * @param  Collection<int, ProspectingSearchResult>  $results
     * @return array{summary: string, factors: list<string>, confidence: int}
     */
    public function compose(Collection $results, ResultBusinessRoleDecision $decision): array
    {
        $factors = collect($decision->reasonCodes);
        $research = $results->pluck('research')->filter(fn ($record) => $record?->status === 'completed' && $record?->schema_valid);
        if ($research->flatMap(fn ($record) => (array) $record->activity_mentions)->filter()->isNotEmpty()) {
            $factors->push('researched_business_activity');
        }
        if ($research->flatMap(fn ($record) => (array) $record->product_mentions)->filter()->isNotEmpty()) {
            $factors->push('public_product_or_catalog_mention');
        }
        if ($results->count() > 1) {
            $factors->push('multiple_same_domain_sources');
        }
        $safeFactors = $factors->unique()->take(20)->values()->all();

        return [
            'summary' => 'Детерминированные публичные признаки указывают на возможное использование Product; факт закупки не утверждается.',
            'factors' => $safeFactors,
            'confidence' => min(90, $decision->confidence + ($research->isNotEmpty() ? 5 : 0)),
        ];
    }
}
