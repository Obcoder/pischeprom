<?php

namespace Tests\Feature\AiSales;

use App\Http\Resources\AiSales\ProspectingCandidateResource;

class ProspectingAuthorizationAndApiTest extends Stage08TestCase
{
    public function test_all_writable_routes_require_auth_permission_and_reject_browser_execution_controls(): void
    {
        $this->postJson('/api/ai-sales/prospecting/jobs', [])->assertUnauthorized();
        $viewer = $this->userWith(['ai_sales.view', 'ai_sales.sales.view', 'ai_sales.prospecting.view']);
        $this->actingAs($viewer)->postJson('/api/ai-sales/prospecting/jobs', [
            'purpose' => 'buyer_discovery', 'safe_objective' => 'Synthetic only',
        ])->assertForbidden();
        $actor = $this->prospectingUser(['sales', 'procurement']);
        $this->actingAs($actor)->postJson('/api/ai-sales/prospecting/jobs', [
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Synthetic only',
            'provider' => 'arbitrary',
            'model' => 'arbitrary',
            'contour' => 'external_sanitized',
            'prompt' => 'arbitrary',
            'tool' => 'arbitrary',
            'url' => 'https://arbitrary.example',
            'auto_create_unit' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'provider', 'model', 'contour', 'prompt', 'tool', 'url', 'auto_create_unit',
        ]);
        $response = $this->actingAs($actor)->postJson('/api/ai-sales/prospecting/jobs', [
            'purpose' => 'supplier_discovery', 'safe_objective' => 'Synthetic supplier fixture.',
        ])->assertCreated();
        $response->assertJsonPath('data.lane', 'procurement')
            ->assertJsonPath('data.default_role_code', 'prospective_supplier')
            ->assertJsonPath('data.auto_create_unit', false)
            ->assertJsonPath('data.execution_available', false);
        $this->postJson('/api/ai-sales/prospecting/export', [])->assertNotFound();
    }

    public function test_lane_idor_is_fail_closed_for_dual_lane_candidate_queues(): void
    {
        $dualActor = $this->prospectingUser(['sales', 'procurement']);
        $salesCandidate = $this->candidate($this->approvedJob($dualActor), $dualActor);
        $procurementCandidate = $this->candidate($this->approvedJob($dualActor, 'supplier_discovery'), $dualActor);
        $salesOnly = $this->prospectingUser(['sales']);
        $payload = $this->actingAs($salesOnly)->getJson('/api/ai-sales/prospecting/candidates?per_page=50')
            ->assertOk()->json('data');
        $this->assertSame([$salesCandidate->public_id], collect($payload)->pluck('id')->all());
        $this->actingAs($salesOnly)->getJson('/api/ai-sales/prospecting/candidates/'.$procurementCandidate->public_id)->assertForbidden();
        $this->actingAs($salesOnly)->postJson('/api/ai-sales/prospecting/candidates/'.$procurementCandidate->public_id.'/evaluate', [])->assertForbidden();
    }

    public function test_new_unit_resolution_requires_explicit_resolve_permission(): void
    {
        $actor = $this->prospectingUser();
        $candidate = $this->candidate($this->approvedJob($actor), $actor);
        $reviewOnly = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.prospecting.view',
            'ai_sales.prospecting.review',
        ]);

        $this->postJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id.'/create-unit', [])->assertUnauthorized();
        $this->actingAs($reviewOnly)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id.'/create-unit', [])
            ->assertForbidden();
        $this->assertNull($candidate->fresh()->resolved_unit_id);
    }

    public function test_sales_only_owner_cannot_change_draft_purpose_into_procurement_lane(): void
    {
        $actor = $this->prospectingUser(['sales']);
        $job = app(\App\Domain\AiSales\Services\ProspectingSearchJobService::class)->createDraft([
            'purpose' => 'buyer_discovery',
            'safe_objective' => 'Sales-only draft.',
        ], $actor);
        $this->actingAs($actor)->patchJson('/api/ai-sales/prospecting/jobs/'.$job->public_id, [
            'purpose' => 'supplier_discovery',
        ])->assertForbidden();
        $this->assertSame('sales', $job->fresh()->lane->value);
        $this->assertSame('buyer_discovery', $job->fresh()->purpose->value);
    }

    public function test_resources_never_expose_encrypted_personal_channel_or_raw_provider_fields(): void
    {
        $actor = $this->prospectingUser();
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'channels' => [['kind' => 'email', 'value' => 'private.person@stage08.example', 'contact_role' => 'person_specific']],
        ])->load(['job:id,public_id', 'sources', 'channels', 'unitMatches']);
        $json = json_encode((new ProspectingCandidateResource($candidate))->resolve(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('private.person@stage08.example', $json);
        foreach (['protected_value', 'raw_body', 'provider_body', 'prompt', 'api_key'] as $blocked) {
            $this->assertStringNotContainsString($blocked, $json);
        }
    }

    public function test_default_off_guard_hides_routes_even_from_authorized_user(): void
    {
        $actor = $this->prospectingUser();
        $unit = $this->unit();
        $context = \App\Models\UnitBusinessContext::query()->findOrFail(
            $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer'])['id'],
        );
        $product = \App\Models\Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Синтетический продукт default-off',
            'is_published' => true,
        ]);
        $good = \App\Models\Good::query()->create([
            'name' => 'Default-off synthetic Good',
            'is_published' => true,
        ]);
        $good->products()->attach($product->id);
        $productMatch = app(\App\Domain\AiSales\Services\UnitProductMatchService::class)->suggest($unit, $context, [
            'product_id' => $product->id,
            'match_type' => 'potential_need',
            'safe_rationale' => 'Synthetic default-off Product relation fixture.',
            'origin' => 'manual',
        ], $actor);
        $match = app(\App\Domain\AiSales\Services\UnitGoodMatchService::class)->suggest($unit, $context, [
            'unit_product_match_id' => $productMatch->id,
            'good_id' => $good->id,
            'match_type' => 'potential_need',
            'fit_confidence' => 80,
            'safe_rationale' => 'Synthetic default-off guard fixture.',
            'origin' => 'manual',
        ], $actor);
        $this->actingAs($actor)->postJson('/api/ai-sales/prospecting/good-matches/'.$match->id.'/review', [
            'status' => 'preferred_offer',
            'prompt' => 'arbitrary browser prompt',
            'entity_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['prompt', 'entity_id']);
        config()->set('ai-sales.prospecting.dossier_enabled', false);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/candidates')->assertNotFound();
        $this->actingAs($actor)->postJson('/api/ai-sales/prospecting/good-matches/'.$match->id.'/review', [
            'status' => 'approved_for_offer',
        ])->assertNotFound();
    }
}
