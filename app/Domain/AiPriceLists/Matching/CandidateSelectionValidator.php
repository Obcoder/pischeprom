<?php

namespace App\Domain\AiPriceLists\Matching;

use App\Models\PriceListImportItem;
use DomainException;

class CandidateSelectionValidator
{
    public function validateAiSelection(PriceListImportItem $item, int $goodId): int
    {
        if (! $item->candidates()->where('good_id', $goodId)->exists()) {
            throw new DomainException('AI returned a Good ID outside the supplied candidate set.');
        }

        return $goodId;
    }
}
