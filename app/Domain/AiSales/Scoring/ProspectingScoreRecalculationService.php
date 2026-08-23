<?php

namespace App\Domain\AiSales\Scoring;

use App\Models\UnitBusinessContext;
use App\Models\UnitGoodMatch;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ProspectingScoreRecalculationService
{
    public function __construct(
        private readonly ProspectingScoringFeatureGuard $features,
        private readonly ProductRelevanceInputAssembler $productInputs,
        private readonly ProductRelevanceScoringService $productScoring,
        private readonly GoodFitInputAssembler $goodInputs,
        private readonly GoodFitScoringService $goodScoring,
        private readonly ProspectPriorityInputAssembler $priorityInputs,
        private readonly ProspectPriorityScoringService $priorityScoring,
        private readonly ProspectingScoreSnapshotWriter $writer,
    ) {}

    public function product(User $actor, UnitProductMatch $match, bool $persist = true): ScoreResult|Model
    {
        $this->features->scoring();
        $result = $this->productScoring->calculate($this->productInputs->assemble($actor, $match));

        return $persist ? $this->writer->write($result, $actor->id) : $result;
    }

    public function good(User $actor, UnitGoodMatch $match, bool $persist = true): ScoreResult|Model
    {
        $this->features->scoring();
        $result = $this->goodScoring->calculate($this->goodInputs->assemble($actor, $match));

        return $persist ? $this->writer->write($result, $actor->id) : $result;
    }

    public function priority(User $actor, UnitBusinessContext $context, bool $persist = true): ScoreResult|Model
    {
        $this->features->scoring();
        $result = $this->priorityScoring->calculate($this->priorityInputs->assemble($actor, $context));

        return $persist ? $this->writer->write($result, $actor->id) : $result;
    }
}
