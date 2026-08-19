<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Route;

class Stage14AuthorizationAndApiTest extends Stage14TestCase
{
    public function test_routes_require_authentication_verification_policy_and_campaign_scope(): void
    {
        $owner = $this->campaignUser();
        $campaign = $this->campaign($owner);

        $this->getJson('/api/ai-sales/campaigns')->assertUnauthorized();

        $unverified = $this->campaignUser();
        $unverified->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($unverified)->getJson('/api/ai-sales/campaigns')->assertForbidden();

        $withoutPermission = $this->userWith(['ai_sales.view', 'ai_sales.sales.view']);
        $this->actingAs($withoutPermission)->getJson('/api/ai-sales/campaigns')->assertForbidden();

        $other = $this->campaignUser();
        $this->actingAs($other)->getJson("/api/ai-sales/campaigns/{$campaign->public_id}")->assertForbidden();

        $admin = $this->campaignUser(true);
        $this->actingAs($admin)->getJson("/api/ai-sales/campaigns/{$campaign->public_id}")
            ->assertOk()->assertJsonPath('data.id', $campaign->public_id);
    }

    public function test_browser_cannot_supply_server_owned_workflow_or_enable_automation_without_admin(): void
    {
        $actor = $this->campaignUser();
        $payload = $this->campaignPayload();
        $payload['purpose'] = 'supplier_discovery';
        $payload['lane'] = 'procurement';
        $payload['workflow'] = ['arbitrary'];
        $payload['provider'] = 'external';
        $payload['prompt'] = 'arbitrary';
        $payload['url'] = 'https://example.test';
        $payload['dispatch'] = true;

        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose', 'lane', 'workflow', 'provider', 'prompt', 'url', 'dispatch']);

        $automation = $this->campaignPayload(null, [
            'automation_mode' => 'autonomous_reviewed',
            'auto_unit_approved' => true,
        ]);
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $automation)->assertForbidden();

        $admin = $this->campaignUser(true);
        $this->actingAs($admin)->postJson('/api/ai-sales/campaigns', $automation)
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'buyer_discovery')
            ->assertJsonPath('data.lane', 'sales')
            ->assertJsonPath('data.role_code', 'prospective_customer')
            ->assertJsonPath('data.live_run_available', false)
            ->assertJsonPath('data.email_dispatch_available', false);
    }

    public function test_campaign_route_registry_has_required_middleware_and_conservative_throttle(): void
    {
        $campaignRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/ai-sales/campaigns'));

        $this->assertCount(12, $campaignRoutes);
        foreach ($campaignRoutes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('verified', $middleware);
            $this->assertContains('throttle:ai-sales', $middleware);
            $this->assertContains('throttle:ai-sales-campaigns', $middleware);
        }

        $actor = $this->campaignUser();
        for ($request = 1; $request <= 20; $request++) {
            $this->actingAs($actor)->getJson('/api/ai-sales/campaigns')->assertOk();
        }
        $this->actingAs($actor)->getJson('/api/ai-sales/campaigns')->assertTooManyRequests();
    }

    public function test_feature_is_not_discoverable_when_default_off(): void
    {
        config()->set([
            'ai-sales.autonomous_campaigns_enabled' => false,
            'ai-sales.campaigns.enabled' => false,
        ]);
        $actor = $this->campaignUser();

        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $this->campaignPayload())
            ->assertNotFound();
    }
}
