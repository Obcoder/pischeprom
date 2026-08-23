<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchType;
use App\Domain\AiSales\Services\UnitProductMatchService;
use App\Domain\AiSales\Services\UnitSourceService;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitProductMatch;
use App\Models\User;

abstract class Stage10TestCase extends Stage08TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai-sales.prospecting.scoring_enabled' => true,
            'ai-sales.prospecting.auto_scoring_enabled' => false,
            'ai-sales.prospecting.score_overrides_enabled' => true,
            'ai-sales.prospecting.ai_evidence_enabled' => true,
            'ai-sales.prospecting.live_scoring_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
        ]);
    }

    protected function scoringUser(array $lanes = ['sales'], bool $override = true): User
    {
        $permissions = [
            'ai_sales.scoring.view', 'ai_sales.scoring.definitions.view',
            'ai_sales.scoring.recalculate', 'ai_sales.scoring.review',
        ];
        if ($override) {
            $permissions[] = 'ai_sales.scoring.override';
        }

        return $this->prospectingUser($lanes, $permissions);
    }

    /** @return array{Unit, UnitBusinessContext, Product, UnitProductMatch} */
    protected function productFixture(User $actor, string $lane = 'sales', ?Unit $unit = null): array
    {
        $unit ??= $this->unit(['name' => 'Stage 10 synthetic Unit '.uniqid()]);
        $context = UnitBusinessContext::query()->findOrFail($this->createContext($actor, $unit, [
            'lane' => $lane,
            'role_code' => $lane === 'sales' ? 'prospective_customer' : 'prospective_supplier',
        ])['id']);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Stage 10 Product '.uniqid(), 'eng' => 'Stage 10 Product', 'is_published' => true,
        ]);
        $source = app(UnitSourceService::class)->create($unit, [
            'unit_business_context_id' => $context->id,
            'source_type' => 'corporate_website',
            'source_label' => 'Repository synthetic corporate source',
            'source_reference' => 'fixture:stage10:corporate:'.uniqid(),
            'data_classification' => 'public',
            'visibility_scope' => $lane === 'sales' ? 'sales_lane' : 'procurement_lane',
            'observed_at' => now(), 'last_checked_at' => now(),
        ], $actor);
        $match = app(UnitProductMatchService::class)->suggest($unit, $context, [
            'product_id' => $product->id,
            'unit_source_id' => $source->id,
            'match_type' => $lane === 'sales' ? UnitProductMatchType::PotentialNeed : UnitProductMatchType::PotentialOffer,
            'evidence_confidence' => 90,
            'safe_rationale' => 'Repository-owned direct Product evidence.',
            'evidence_reference' => $source->source_reference,
            'evidence_hash' => hash('sha256', $source->source_reference),
        ], $actor);
        $match = app(UnitProductMatchService::class)->review($match, UnitProductMatchStatus::Reviewed, $actor);

        return [$unit, $context, $product, $match];
    }
}
