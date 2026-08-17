<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Services\ProspectingCandidateService;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchJob;
use App\Models\User;

abstract class Stage08TestCase extends UnitContextsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => true,
            'ai-sales.prospecting.auto_create_unit' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
        ]);
    }

    protected function prospectingUser(array $lanes = ['sales'], array $extra = []): User
    {
        return $this->userWith(array_values(array_unique([
            'ai_sales.view',
            ...array_map(fn ($lane) => "ai_sales.{$lane}.view", $lanes),
            'ai_sales.unit_roles.manage',
            'ai_sales.contexts.manage',
            'ai_sales.aliases.manage',
            'ai_sales.observation.manage',
            'ai_sales.prospecting.view',
            'ai_sales.prospecting.jobs.manage',
            'ai_sales.prospecting.review',
            'ai_sales.prospecting.resolve',
            'ai_sales.good_matches.review',
            'ai_sales.product_matches.review',
            'ai_sales.timeline.view',
            'ai_sales.search.plan',
            'ai_sales.search.review',
            'ai_sales.search.execute',
            'ai_sales.search.results.view',
            'ai_sales.search.research',
            'ai_sales.search.providers.view',
            ...$extra,
        ])));
    }

    protected function approvedJob(
        User $actor,
        string $purpose = 'buyer_discovery',
        ?Good $good = null,
        ?Product $product = null,
    ): ProspectingSearchJob {
        $good ??= Good::query()->create([
            'name' => 'Repository Synthetic Good',
            'is_published' => true,
        ]);
        $product ??= $good->products()->without(['category', 'manufacturers'])->first();
        $product ??= Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Репозиторный синтетический продукт',
            'eng' => 'Repository Synthetic Product',
            'is_published' => true,
        ]);
        $good->products()->syncWithoutDetaching([$product->id]);
        $service = app(ProspectingSearchJobService::class);
        $job = $service->createDraft([
            'purpose' => $purpose,
            'safe_objective' => 'Repository-owned Stage 08 test fixture.',
            'primary_product_id' => $product->id,
            'originating_good_ids' => [$good->id],
            'criteria' => ['segments' => ['synthetic']],
        ], $actor);
        $service->submit($job, $actor);

        return $service->approve($job->fresh(), $actor);
    }

    protected function candidate(ProspectingSearchJob $job, User $actor, array $overrides = []): ProspectingCandidate
    {
        $input = [
            'working_name' => 'Synthetic Candidate '.uniqid(),
            'website' => 'https://candidate-'.uniqid().'.example',
            'public_activity_summary' => 'Fictional public activity.',
            'relevance_summary' => 'Fictional product relevance.',
            'confidence_components' => ['relevance' => 85, 'identity' => 80],
            'sources' => [[
                'type' => 'synthetic_fixture',
                'reference' => 'repository-fixture:stage08-test',
                'title' => 'Synthetic evidence',
                'excerpt' => 'Fictional bounded evidence.',
            ]],
            ...$overrides,
        ];

        return app(ProspectingCandidateService::class)->createFixture($job, $input, $actor, true);
    }
}
