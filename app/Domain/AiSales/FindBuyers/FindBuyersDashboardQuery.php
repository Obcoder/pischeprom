<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Models\ProspectingSearchJob;
use App\Models\User;

final class FindBuyersDashboardQuery
{
    public function __construct(
        private readonly FindBuyersFeatureGuard $features,
        private readonly FindBuyersAuthorizationService $authorization,
        private readonly FindBuyersProgressQuery $progress,
    ) {}

    /** @return array<string, mixed> */
    public function get(User $actor, int $limit = 25): array
    {
        $this->features->ui();
        $this->authorization->authorizeView($actor);
        $jobs = ProspectingSearchJob::query()
            ->where('purpose', ProspectingPurpose::BuyerDiscovery->value)
            ->where('lane', BusinessLane::Sales->value)
            ->latest('id')->limit(max(1, min($limit, 50)))->get();
        $items = $jobs->map(fn (ProspectingSearchJob $job): array => $this->progress->get($job, $actor)->toArray());
        $sectionPredicates = [
            'my_jobs' => fn (array $item): bool => $item['job']['owner']['id'] === (int) $actor->id,
            'review_required' => fn (array $item): bool => $item['stage'] === 'review_required',
            'in_progress' => fn (array $item): bool => in_array($item['stage'], ['search_pending', 'searching', 'public_research_pending', 'researching', 'scoring_pending'], true),
            'candidates' => fn (array $item): bool => $item['counts']['candidates']['total'] > 0,
            'high_priority' => fn (array $item): bool => collect($item['scoring']['prospect_priority'] ?? [])->contains(fn (array $score): bool => in_array($score['band'], ['high', 'very_high'], true)),
            'blocked' => fn (array $item): bool => $item['stage'] === 'blocked' || $item['counts']['scores']['blocked'] > 0,
            'completed' => fn (array $item): bool => in_array($item['stage'], ['scored', 'completed', 'cancelled'], true),
        ];
        $sections = collect($sectionPredicates)->map(fn ($predicate, string $code): array => [
            'code' => $code,
            'count' => $items->filter($predicate)->count(),
        ])->values()->all();

        return [
            'sections' => $sections,
            'jobs' => $items->all(),
            'runtime' => $this->features->runtimeState(),
            'live_execution_action_available' => false,
        ];
    }
}
