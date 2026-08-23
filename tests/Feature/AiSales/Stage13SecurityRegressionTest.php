<?php

namespace Tests\Feature\AiSales;

use App\Http\Resources\AiSales\OutreachDispatchResource;
use App\Http\Resources\AiSales\OutreachEventResource;
use App\Models\MailingEvent;
use App\Models\MailingMessage;
use Illuminate\Support\Facades\Http;

class Stage13SecurityRegressionTest extends Stage13TestCase
{
    public function test_all_stage13_feature_flags_and_numeric_guards_default_off_in_config_source(): void
    {
        $source = file_get_contents(config_path('ai-sales.php'));

        foreach ([
            'AI_OUTREACH_DISPATCH_PIPELINE_ENABLED', 'AI_OUTREACH_QUEUE_ENABLED',
            'AI_OUTREACH_PROVIDER_SEND_ENABLED', 'AI_OUTREACH_EVENT_INGESTION_ENABLED',
            'AI_OUTREACH_REPLY_CORRELATION_ENABLED', 'AI_OUTREACH_REPLY_TRIAGE_ENABLED',
            'AI_OUTREACH_FOLLOWUP_PLANNING_ENABLED', 'AI_OUTREACH_AUTO_FOLLOWUP_ENABLED',
        ] as $flag) {
            $this->assertMatchesRegularExpression("/env\\('{$flag}', false\\)/", $source);
        }
        $this->assertSame(0, config('ai-sales.outreach.limits.global_daily_sends'));
    }

    public function test_route_registry_applies_auth_verified_and_throttle_to_mutations(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $mutations = $routes->filter(fn ($route) => str_contains($route->uri(), 'ai-sales/units/{unit}/outreach')
            && in_array('POST', $route->methods(), true));

        $this->assertNotEmpty($mutations);
        foreach ($mutations as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, $route->uri());
            $this->assertContains('verified', $middleware, $route->uri());
            $this->assertTrue(collect($middleware)->contains(fn ($item) => str_starts_with($item, 'throttle:')), $route->uri());
        }
    }

    public function test_stage13_code_does_not_invoke_manual_routes_or_deprecated_payloads(): void
    {
        $source = file_get_contents(app_path('Domain/AiSales/Outreach/OutreachDispatchService.php'));
        $this->assertStringNotContainsString('AuthorizedMailDispatchService', $source);
        $this->assertStringNotContainsString('MailMessageController', $source);
        $this->assertStringNotContainsString('MailingCampaignService', $source);
        foreach (['raw_payload', 'parsed_payload', 'request_payload', 'response_payload', 'error_message', 'getMessage()'] as $blocked) {
            $this->assertStringNotContainsString($blocked, $source);
        }
    }

    public function test_resources_expose_only_normalized_safe_metadata(): void
    {
        $fixture = $this->approvedOutreachFixture();
        $dispatch = $this->prepareDispatch($fixture);
        $event = MailingEvent::query()->create([
            'provider' => 'unisender_go', 'event_fingerprint' => hash('sha256', 'safe-resource'),
            'sending_id' => $dispatch->sending_id, 'event_name' => 'email_status',
            'normalized_event_type' => 'email_status', 'status' => 'delivered',
            'normalized_status' => 'delivered', 'verified_at' => now(), 'created_at' => now(),
        ]);

        $encoded = json_encode([
            (new OutreachDispatchResource($dispatch))->resolve(),
            (new OutreachEventResource($event))->resolve(),
        ], JSON_THROW_ON_ERROR);
        foreach (['raw_payload', 'parsed_payload', 'payload', 'recipient', 'headers', 'error_message'] as $blocked) {
            $this->assertStringNotContainsString($blocked, $encoded);
        }
    }

    public function test_deprecated_provider_columns_reject_new_writes(): void
    {
        $message = MailingMessage::query()->create([
            'provider' => 'unisender_go', 'email' => 'synthetic@example.test', 'subject' => 'Synthetic',
            'request_payload' => ['forbidden' => true],
        ]);

        $this->assertNull(\Illuminate\Support\Facades\DB::table('mailing_messages')->where('id', $message->id)->value('request_payload'));
        $this->assertNotContains('request_payload', $message->getFillable());
    }

    public function test_no_outreach_default_path_can_issue_http(): void
    {
        $fixture = $this->approvedOutreachFixture();
        $this->prepareDispatch($fixture);

        Http::assertNothingSent();
    }
}
