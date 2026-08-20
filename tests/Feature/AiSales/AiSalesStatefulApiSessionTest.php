<?php

namespace Tests\Feature\AiSales;

use App\Models\ClientAcquisitionCampaign;
use App\Models\Entity;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchExecution;
use App\Models\Unit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

final class AiSalesStatefulApiSessionTest extends Stage14TestCase
{
    public function test_first_party_web_session_can_read_protected_ai_sales_api_projections(): void
    {
        config()->set([
            'ai-sales.enabled' => true,
            'ai-sales.find_buyers.ui_enabled' => true,
            'ai-sales.campaigns.enabled' => true,
        ]);

        $actor = $this->campaignUser();
        $product = $this->campaignProduct('Stateful session Product');
        $campaign = $this->campaign($actor, $product);
        $before = $this->domainCounts();

        $this->assertContains(
            EnsureFrontendRequestsAreStateful::class,
            app('router')->getMiddlewareGroups()['api'] ?? [],
        );

        $this->post('/login', [
            'email' => $actor->email,
            'password' => 'password',
        ])->assertRedirect();

        $headers = [
            'Origin' => rtrim((string) config('app.url'), '/'),
            'Referer' => rtrim((string) config('app.url'), '/').'/Ameise/ai-sales',
        ];

        $this->withHeaders($headers)
            ->getJson('/api/ai-sales/campaigns')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $campaign->public_id);
        $this->withHeaders($headers)
            ->getJson('/api/ai-sales/prospecting/candidates?per_page=50')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withHeaders($headers)
            ->getJson('/api/ai-sales/find-buyers/dashboard?limit=25')
            ->assertOk();

        $this->assertSame($before, $this->domainCounts());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_stateful_bridge_keeps_guest_and_permissionless_users_blocked(): void
    {
        config()->set([
            'ai-sales.enabled' => true,
            'ai-sales.find_buyers.ui_enabled' => true,
            'ai-sales.campaigns.enabled' => true,
        ]);

        $headers = [
            'Origin' => rtrim((string) config('app.url'), '/'),
            'Referer' => rtrim((string) config('app.url'), '/').'/Ameise/ai-sales',
        ];

        $this->withHeaders($headers)
            ->getJson('/api/ai-sales/campaigns')
            ->assertUnauthorized();

        $actor = $this->userWith([]);
        $this->post('/login', [
            'email' => $actor->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->withHeaders($headers)
            ->getJson('/api/ai-sales/campaigns')
            ->assertForbidden();
    }

    /** @return array<string, int> */
    private function domainCounts(): array
    {
        return [
            'campaigns' => ClientAcquisitionCampaign::query()->count(),
            'executions' => ProspectingSearchExecution::query()->count(),
            'candidates' => ProspectingCandidate::query()->count(),
            'units' => Unit::query()->count(),
            'entities' => Entity::query()->count(),
        ];
    }
}
