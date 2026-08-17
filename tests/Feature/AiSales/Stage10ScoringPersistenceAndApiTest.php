<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\UnitGoodMatchType;
use App\Domain\AiSales\Scoring\ProspectingScoreStalenessService;
use App\Domain\AiSales\Services\UnitGoodMatchService;
use App\Models\Good;
use App\Models\UnitProductRelevanceFactor;
use App\Models\UnitProductRelevanceSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use LogicException;

class Stage10ScoringPersistenceAndApiTest extends Stage10TestCase
{
    public function test_authenticated_authorized_api_persists_idempotent_three_level_snapshots_and_safe_history(): void
    {
        $actor = $this->scoringUser();
        [$unit, $context, $product, $productMatch] = $this->productFixture($actor);
        $good = Good::query()->create(['name' => 'Stage 10 exact Good', 'is_published' => true]);
        $good->products()->attach($product->id);
        $goodMatch = app(UnitGoodMatchService::class)->suggest($unit, $context, [
            'unit_product_match_id' => $productMatch->id,
            'good_id' => $good->id,
            'match_type' => UnitGoodMatchType::PotentialNeed,
            'safe_rationale' => 'Exact mapping; commercial attributes intentionally unknown.',
        ], $actor);

        app('auth')->forgetGuards();
        $this->getJson('/api/ai-sales/scoring/definitions')->assertUnauthorized();
        $definitions = $this->actingAs($actor)->getJson('/api/ai-sales/scoring/definitions')->assertOk();
        $definitions->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'product_relevance.v1');
        $withoutScoring = $this->prospectingUser();
        $this->actingAs($withoutScoring)->getJson("/api/ai-sales/scoring/units/{$unit->id}/contexts/{$context->id}")
            ->assertForbidden();
        $this->actingAs($withoutScoring)->postJson("/api/ai-sales/scoring/product-matches/{$productMatch->id}/recalculate")
            ->assertForbidden();

        $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-matches/{$productMatch->id}/recalculate", [
            'weight' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors('weight');

        $first = $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-matches/{$productMatch->id}/recalculate")
            ->assertOk()->assertJsonPath('data.definition.code', 'product_relevance.v1');
        $snapshotId = $first->json('data.id');
        $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-matches/{$productMatch->id}/recalculate")
            ->assertOk()->assertJsonPath('data.id', $snapshotId);
        $this->assertDatabaseCount('unit_product_relevance_snapshots', 1);
        $this->assertDatabaseHas('unit_product_relevance_factors', [
            'unit_product_relevance_snapshot_id' => $snapshotId,
            'factor_code' => 'direct_product_mention',
            'weight' => 25,
        ]);

        $this->actingAs($actor)->postJson("/api/ai-sales/scoring/good-matches/{$goodMatch->id}/recalculate")
            ->assertOk()->assertJsonPath('data.definition.code', 'good_fit.v1')
            ->assertJsonPath('data.computed_score', 20);
        $this->actingAs($actor)->postJson("/api/ai-sales/scoring/contexts/{$context->id}/priority/recalculate")
            ->assertOk()->assertJsonPath('data.definition.code', 'prospect_priority.v1');

        $response = $this->actingAs($actor)->getJson("/api/ai-sales/scoring/units/{$unit->id}/contexts/{$context->id}")
            ->assertOk()->assertJsonCount(1, 'data.product_relevance')
            ->assertJsonCount(1, 'data.good_fit')->assertJsonCount(1, 'data.prospect_priority')
            ->assertJsonMissing(['channel_value_snapshot'])
            ->assertJsonMissing(['protected_value'])
            ->assertJsonMissing(['raw_body']);
        $this->assertStringNotContainsString('weights_editable', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_override_and_review_create_new_snapshots_and_immutable_rows_reject_mutation(): void
    {
        $actor = $this->scoringUser();
        [, , , $match] = $this->productFixture($actor);
        $base = $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-matches/{$match->id}/recalculate")->assertOk();
        $baseId = $base->json('data.id');
        $computed = $base->json('data.computed_score');
        $reviewOnly = $this->scoringUser(['sales'], false);
        $this->actingAs($reviewOnly)->postJson("/api/ai-sales/scoring/product-relevance-snapshots/{$baseId}/override", [
            'effective_score' => 80,
            'reason_code' => 'human_evidence_correction',
            'safe_note' => 'Permission boundary fixture.',
        ])->assertForbidden();

        $override = $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-relevance-snapshots/{$baseId}/override", [
            'effective_score' => 80,
            'reason_code' => 'human_evidence_correction',
            'safe_note' => 'Synthetic reviewer correction with no contact permission.',
            'expires_at' => now()->addDay()->toISOString(),
        ])->assertOk()
            ->assertJsonPath('data.computed_score', $computed)
            ->assertJsonPath('data.effective_score', 80)
            ->assertJsonPath('data.origin', 'manual_override')
            ->assertJsonPath('data.manual_override.base_snapshot_id', $baseId);
        $overrideId = $override->json('data.id');
        $this->assertNotSame($baseId, $overrideId);
        $this->assertDatabaseHas('unit_product_relevance_snapshots', ['id' => $baseId, 'superseded_by_snapshot_id' => $overrideId]);

        $review = $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-relevance-snapshots/{$overrideId}/review", [
            'status' => 'reviewed',
        ])->assertOk()->assertJsonPath('data.origin', 'review_correction');
        $this->assertNotSame($overrideId, $review->json('data.id'));
        $this->assertDatabaseHas('unit_dossier_audit_events', ['event_type' => 'unit.score.overridden', 'subject_id' => $overrideId]);
        $this->assertDatabaseHas('unit_dossier_audit_events', ['event_type' => 'unit.score.reviewed', 'subject_id' => $review->json('data.id')]);

        $snapshot = UnitProductRelevanceSnapshot::query()->findOrFail($review->json('data.id'));
        try {
            $snapshot->update(['computed_score' => 1]);
            $this->fail('Append-only score snapshot accepted a score mutation.');
        } catch (LogicException) {
            $this->assertSame($computed, $snapshot->fresh()->computed_score);
        }
        $factor = UnitProductRelevanceFactor::query()->where('unit_product_relevance_snapshot_id', $snapshot->id)->firstOrFail();
        $this->expectException(LogicException::class);
        $factor->update(['weight' => 99]);
    }

    public function test_override_cannot_submit_formula_or_secret_material(): void
    {
        $actor = $this->scoringUser();
        [, , , $match] = $this->productFixture($actor);
        $id = $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-matches/{$match->id}/recalculate")->json('data.id');
        $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-relevance-snapshots/{$id}/override", [
            'effective_score' => 50,
            'reason_code' => 'review_disagreement',
            'safe_note' => 'Safe review note.',
            'formula' => 'score=100',
        ])->assertUnprocessable()->assertJsonValidationErrors('formula');
        $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-relevance-snapshots/{$id}/override", [
            'effective_score' => 50,
            'reason_code' => 'review_disagreement',
            'safe_note' => 'Authorization: Bearer forbidden-secret-material',
        ])->assertUnprocessable();
    }

    public function test_expired_override_is_marked_stale_and_explicit_recalculation_creates_a_new_current_snapshot(): void
    {
        $actor = $this->scoringUser();
        [, , , $match] = $this->productFixture($actor);
        $baseId = $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-matches/{$match->id}/recalculate")->json('data.id');
        $expiresAt = now()->addDay();
        $overrideId = $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-relevance-snapshots/{$baseId}/override", [
            'effective_score' => 60,
            'reason_code' => 'temporary_priority_review',
            'safe_note' => 'Temporary synthetic review override.',
            'expires_at' => $expiresAt->toISOString(),
        ])->json('data.id');
        Carbon::setTestNow($expiresAt->copy()->addSecond());
        try {
            $this->assertSame(1, app(ProspectingScoreStalenessService::class)->markExpiredOverrides());
            $this->assertDatabaseHas('unit_product_relevance_snapshots', [
                'id' => $overrideId, 'stale_reason_code' => 'override_expired',
            ]);
            $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-relevance-snapshots/{$overrideId}/review", [
                'status' => 'reviewed',
            ])->assertUnprocessable();
            $newId = $this->actingAs($actor)->postJson("/api/ai-sales/scoring/product-matches/{$match->id}/recalculate")
                ->assertOk()->json('data.id');
            $this->assertNotSame($baseId, $newId);
            $this->assertNotSame($overrideId, $newId);
            $this->assertDatabaseHas('unit_product_relevance_snapshots', [
                'id' => $overrideId, 'superseded_by_snapshot_id' => $newId,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_recalculation_cli_is_dry_run_by_default_and_apply_is_explicit(): void
    {
        $actor = $this->scoringUser();
        [, $context] = $this->productFixture($actor);
        Http::preventStrayRequests();

        $arguments = [
            '--user-id' => $actor->id,
            '--context-id' => $context->id,
            '--chunk' => 1,
        ];
        $this->artisan('ai-sales:recalculate-prospecting-scores', $arguments)
            ->expectsOutputToContain('Dry-run; HTTP=0')
            ->assertSuccessful();
        $this->assertDatabaseCount('unit_product_relevance_snapshots', 0);
        $this->assertDatabaseCount('unit_prospect_priority_snapshots', 0);

        $this->artisan('ai-sales:recalculate-prospecting-scores', [
            ...$arguments,
            '--apply' => true,
            '--yes' => true,
        ])->expectsOutputToContain('Applied; HTTP=0')->assertSuccessful();
        $this->assertDatabaseCount('unit_product_relevance_snapshots', 1);
        $this->assertDatabaseCount('unit_prospect_priority_snapshots', 1);
        Http::assertNothingSent();
    }
}
