<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ProspectingAuthorizationService
{
    public const VIEW = 'ai_sales.prospecting.view';

    public const MANAGE_JOBS = 'ai_sales.prospecting.jobs.manage';

    public const REVIEW = 'ai_sales.prospecting.review';

    public const RESOLVE = 'ai_sales.prospecting.resolve';

    public const REVIEW_GOOD_MATCHES = 'ai_sales.good_matches.review';

    public const REVIEW_PRODUCT_MATCHES = 'ai_sales.product_matches.review';

    public const VIEW_TIMELINE = 'ai_sales.timeline.view';

    public const PLAN_SEARCH = 'ai_sales.search.plan';

    public const REVIEW_SEARCH = 'ai_sales.search.review';

    public const EXECUTE_SEARCH = 'ai_sales.search.execute';

    public const VIEW_SEARCH_RESULTS = 'ai_sales.search.results.view';

    public const RESEARCH_SEARCH_RESULTS = 'ai_sales.search.research';

    public const VIEW_SEARCH_PROVIDERS = 'ai_sales.search.providers.view';

    public function __construct(private readonly UnitContextAuthorizationService $contexts) {}

    public function can(User $actor, string $permission, BusinessLane $lane): bool
    {
        return $this->contexts->canViewLane($actor, $lane)
            && $this->contexts->hasPermission($actor, self::VIEW)
            && $this->contexts->hasPermission($actor, $permission);
    }

    public function authorize(User $actor, string $permission, BusinessLane $lane): void
    {
        if (! $this->can($actor, $permission, $lane)) {
            throw new AuthorizationException('Prospecting action is not authorized for this lane.');
        }
    }
}
