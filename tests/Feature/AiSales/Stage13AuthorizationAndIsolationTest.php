<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Str;

class Stage13AuthorizationAndIsolationTest extends Stage13TestCase
{
    public function test_dispatch_routes_require_auth_verification_permissions_and_unit_binding(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, , , , , , $draft] = $fixture;
        $url = "/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draft->id}/dispatches";
        $payload = ['idempotency_key' => (string) Str::uuid()];

        auth()->logout();
        $this->postJson($url, $payload)->assertUnauthorized();

        $unverified = $this->outreachUser();
        $unverified->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($unverified)->postJson($url, $payload)->assertForbidden();

        $withoutPermission = $this->userWith(['ai_sales.view', 'ai_sales.sales.view', 'ai_sales.outreach.view']);
        $this->actingAs($withoutPermission)->postJson($url, $payload)->assertForbidden();

        $otherUnit = $this->unit(['name' => 'Stage 13 Other Unit']);
        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$otherUnit->id}/outreach/drafts/{$draft->id}/dispatches",
            $payload,
        )->assertNotFound();
    }

    public function test_queue_permission_is_revalidated_server_side_and_arbitrary_payload_is_rejected(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [, $unit] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        $viewer = $this->userWith([
            'ai_sales.view', 'ai_sales.sales.view', 'ai_sales.outreach.view',
            'ai_sales.outreach.dispatch.view',
        ]);
        $url = "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue";

        $this->actingAs($viewer)->postJson($url)->assertForbidden();
        $this->actingAs($fixture[0])->postJson($url, [
            'recipient' => 'attacker@example.test',
            'body' => 'override',
            'provider' => 'arbitrary',
            'url' => 'https://attacker.example',
        ])->assertUnprocessable();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_dual_lane_unit_does_not_expose_sales_dispatch_to_procurement_only_actor(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [, $unit] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $procurement = $this->userWith([
            'ai_sales.view', 'ai_sales.procurement.view', 'ai_sales.outreach.view',
            'ai_sales.outreach.dispatch.view',
        ]);

        $this->actingAs($procurement)->getJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}",
        )->assertForbidden();
    }

    public function test_dispatch_throttle_is_applied(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, , , , , , $draft] = $fixture;
        $url = "/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draft->id}/dispatches";

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->actingAs($actor)->postJson($url, ['idempotency_key' => 'invalid'])->assertUnprocessable();
        }
        $this->postJson($url, ['idempotency_key' => 'invalid'])->assertTooManyRequests();
    }
}
