<?php

namespace Tests\Feature\Mail;

use App\Jobs\ProcessUnisenderWebhookJob;
use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\MailingContact;
use App\Models\MailingContactSet;
use App\Models\MailingEvent;
use App\Models\MailingMessage;
use App\Models\MailingWebhookCall;
use App\Services\CommercialOffers\LegacyMailProviderPayloadService;
use App\Services\CommercialOffers\MailingCampaignService;
use App\Services\CommercialOffers\MailProviderException;
use App\Services\CommercialOffers\MailProviderSafeErrorCode;
use App\Services\CommercialOffers\RecipientSetService;
use App\Services\CommercialOffers\UnisenderGoClient;
use App\Services\CommercialOffers\UnisenderRequestProfile;
use App\Services\CommercialOffers\UnisenderWebhookIngress;
use App\Services\CommercialOffers\UnisenderWebhookService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class UnisenderWebhookSecurityTest extends TestCase
{
    private const API_KEY = 'security-test-api-key';

    private const RAW_CANARY = 'raw-provider-secret-canary';

    private const RECIPIENT = 'recipient-canary@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'testing',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'queue.default' => 'sync',
            'inertia.ssr.enabled' => false,
            'app.url' => 'https://pischeprom.test',
            'services.unisender_go.enabled' => true,
            'services.unisender_go.api_base' => 'https://go1.unisender.test/en/transactional/api/v1',
            'services.unisender_go.api_key' => self::API_KEY,
            'services.unisender_go.from_email' => 'sales@pischeprom.test',
            'services.unisender_go.from_name' => 'Pischeprom',
            'services.unisender_go.reply_to' => 'sales@pischeprom.test',
            'services.unisender_go.webhook_queue_connection' => 'database',
            'services.unisender_go.webhook_queue' => 'mailing-webhooks',
            'services.mailings.require_consent_for_mass' => true,
            'services.mailings.dry_run' => false,
        ]);

        DB::purge('sqlite');
        DB::connection()->getPdo();
        (include base_path('database/migrations/2026_06_21_130000_create_commercial_offer_mailings_tables.php'))->up();
        DB::statement('create table sendings (id integer primary key autoincrement)');
        DB::statement('create table mail_messages (id integer primary key autoincrement)');
        (include base_path('database/migrations/2026_08_17_123000_harden_unisender_provider_persistence.php'))->up();

        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        RateLimiter::clear($this->rateLimitKey('127.0.0.1'));
    }

    public function test_wrong_or_missing_content_type_is_rejected_before_persistence(): void
    {
        $body = $this->signedBody([$this->event('event-content', 'delivered')]);

        $this->rawPost($body, ['CONTENT_TYPE' => 'text/plain'])->assertStatus(415)->assertJsonPath('code', 'invalid_content_type');
        $this->call('POST', '/webhooks/unisender-go', [], [], [], [], $body)
            ->assertStatus(415)
            ->assertJsonPath('code', 'invalid_content_type');

        $this->assertNoWebhookMutation();
    }

    public function test_oversized_encoded_body_is_rejected_before_body_parsing(): void
    {
        $body = str_repeat('x', UnisenderWebhookIngress::MAX_ENCODED_BODY_BYTES + 1);

        $this->rawPost($body)->assertStatus(413)->assertJsonPath('code', 'payload_too_large');

        $this->assertNoWebhookMutation();
    }

    public function test_gzip_is_rejected_for_the_configured_json_post_webhook(): void
    {
        $body = gzencode($this->signedBody([$this->event('event-gzip', 'delivered')]));

        $this->rawPost((string) $body, [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_ENCODING' => 'gzip',
        ])->assertStatus(415)->assertJsonPath('code', 'invalid_content_type');

        $this->assertNoWebhookMutation();
    }

    public function test_malformed_json_is_rejected_without_rows_or_jobs(): void
    {
        $this->rawPost('{"auth":')->assertStatus(400)->assertJsonPath('code', 'malformed_payload');

        $this->assertNoWebhookMutation();
    }

    public function test_missing_or_invalid_signature_is_rejected_before_any_database_write(): void
    {
        Log::spy();
        $payload = [
            'events_by_user' => [['events' => [$this->event('event-signature', 'delivered')]]],
            'recipient' => self::RECIPIENT,
            'body_marker' => self::RAW_CANARY,
        ];

        $missing = json_encode($payload, JSON_THROW_ON_ERROR);
        $invalid = json_encode($payload + ['auth' => str_repeat('0', 32)], JSON_THROW_ON_ERROR);

        $this->rawPost($missing)->assertStatus(403)->assertJsonPath('code', 'invalid_signature')->assertDontSee(self::RECIPIENT);
        $this->rawPost($invalid)->assertStatus(403)->assertJsonPath('code', 'invalid_signature')->assertDontSee(self::RAW_CANARY);

        $this->assertNoWebhookMutation();
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_valid_signature_persists_only_allowlisted_metadata_and_queues_ids(): void
    {
        $recipient = $this->recipient();
        $event = $this->event('event-valid', 'delivered', $recipient);
        $body = $this->signedBody([$event], self::RAW_CANARY);

        $this->rawPost($body, [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'unsafe-user-agent-canary',
            'REMOTE_ADDR' => '203.0.113.88',
            'HTTP_X_UNSAFE_HEADER' => 'unsafe-header-canary',
        ])->assertOk()->assertJsonPath('accepted_events', 1);

        $call = MailingWebhookCall::query()->sole();
        $storedEvent = MailingEvent::query()->sole();
        $this->assertTrue($call->auth_valid);
        $this->assertSame(hash('sha256', $body), $call->request_hash);
        $this->assertNull($call->raw_payload);
        $this->assertNull($call->parsed_payload);
        $this->assertNull($call->error_message);
        $this->assertSame('delivered', $storedEvent->normalized_status);
        $this->assertSame($recipient->id, $storedEvent->campaign_recipient_id);

        foreach (['payload', 'email', 'url', 'destination_response', 'user_agent', 'ip', 'country', 'city', 'sender_ip', 'metadata'] as $column) {
            $this->assertNull($storedEvent->getRawOriginal($column), $column.' must not be persisted');
        }

        Queue::assertPushed(ProcessUnisenderWebhookJob::class, function (ProcessUnisenderWebhookJob $job) use ($storedEvent): bool {
            $serialized = serialize($job);

            return $job->eventIds === [$storedEvent->id]
                && $job->tries === 1
                && ! str_contains($serialized, self::RECIPIENT)
                && ! str_contains($serialized, self::RAW_CANARY)
                && ! str_contains($serialized, 'unsafe-user-agent-canary')
                && ! str_contains($serialized, '203.0.113.88');
        });

        $persisted = json_encode([
            DB::table('mailing_webhook_calls')->first(),
            DB::table('mailing_events')->first(),
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::RECIPIENT, $persisted);
        $this->assertStringNotContainsString(self::RAW_CANARY, $persisted);
        $this->assertStringNotContainsString('unsafe-user-agent-canary', $persisted);
        $this->assertStringNotContainsString('203.0.113.88', $persisted);
        $this->assertStringNotContainsString('unsafe-header-canary', $persisted);
    }

    public function test_credential_shaped_provider_identifiers_are_not_persisted(): void
    {
        $event = $this->event(self::API_KEY, 'delivered');
        $event['event_data']['message_id'] = 'access_token_canary_123456789';

        $this->rawPost($this->signedBody([$event]))->assertOk();

        $stored = MailingEvent::query()->sole();
        $this->assertNull($stored->provider_event_id);
        $this->assertNull($stored->provider_message_id);
        $serialized = json_encode(DB::table('mailing_events')->first(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::API_KEY, $serialized);
        $this->assertStringNotContainsString('access_token_canary_123456789', $serialized);
    }

    public function test_batch_boundary_is_accepted_and_over_cap_is_rejected(): void
    {
        $events = [];
        for ($i = 1; $i <= UnisenderWebhookIngress::MAX_EVENTS_PER_REQUEST; $i++) {
            $events[] = $this->event('event-boundary-'.$i, 'delivered');
        }

        $this->rawPost($this->signedBody($events))->assertOk()->assertJsonPath('accepted_events', 100);
        $this->assertDatabaseCount('mailing_events', 100);
        Queue::assertPushed(ProcessUnisenderWebhookJob::class, 1);

        DB::table('mailing_events')->delete();
        DB::table('mailing_webhook_calls')->delete();
        Queue::fake();
        $events[] = $this->event('event-over-cap', 'delivered');

        $this->rawPost($this->signedBody($events))->assertStatus(413)->assertJsonPath('code', 'payload_too_large');
        $this->assertNoWebhookMutation();
    }

    public function test_duplicate_request_is_safe_and_does_not_queue_or_store_twice(): void
    {
        $body = $this->signedBody([$this->event('event-request-dedupe', 'delivered')]);

        $this->rawPost($body)->assertOk()->assertJsonPath('duplicate', false);
        $this->rawPost($body)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('mailing_webhook_calls', 1);
        $this->assertDatabaseCount('mailing_events', 1);
        Queue::assertPushed(ProcessUnisenderWebhookJob::class, 1);
    }

    public function test_duplicate_event_across_different_requests_is_deduplicated(): void
    {
        $event = $this->event('event-cross-request', 'delivered');

        $this->rawPost($this->signedBody([$event], 'request-a'))->assertOk();
        $this->rawPost($this->signedBody([$event], 'request-b'))->assertOk();

        $this->assertDatabaseCount('mailing_webhook_calls', 2);
        $this->assertDatabaseCount('mailing_events', 1);
        Queue::assertPushed(ProcessUnisenderWebhookJob::class, 1);
    }

    public function test_worker_is_idempotent_and_terminal_precedence_blocks_out_of_order_open(): void
    {
        Http::fake(['*suppression/set.json*' => Http::response(['ok' => true])]);
        $recipient = $this->recipient();

        $this->rawPost($this->signedBody([$this->event('event-hard-bounce', 'hard_bounced', $recipient)], 'hard'))->assertOk();
        $hardId = (int) MailingEvent::query()->latest('id')->value('id');
        $service = app(UnisenderWebhookService::class);
        $this->assertSame(1, $service->processStoredEventIds([$hardId]));
        $this->assertSame(0, $service->processStoredEventIds([$hardId]));

        $this->rawPost($this->signedBody([$this->event('event-late-open', 'opened', $recipient)], 'open'))->assertOk();
        $openId = (int) MailingEvent::query()->latest('id')->value('id');
        $service->processStoredEventIds([$openId]);

        $recipient->refresh();
        $this->assertSame('hard_bounced', $recipient->status);
        $this->assertSame(0, $recipient->open_count);
        $this->assertDatabaseCount('mailing_suppression_list', 1);
        Http::assertNothingSent();
    }

    public function test_unknown_event_is_durable_but_has_no_domain_side_effect(): void
    {
        $recipient = $this->recipient();
        $unknown = $this->event('event-unknown', 'future_provider_state', $recipient);
        $unknown['event_name'] = 'future_provider_event';

        $this->rawPost($this->signedBody([$unknown]))->assertOk();
        $event = MailingEvent::query()->sole();
        app(UnisenderWebhookService::class)->processStoredEventIds([$event->id]);

        $this->assertDatabaseHas('mailing_events', [
            'id' => $event->id,
            'normalized_event_type' => 'unknown',
            'normalized_status' => 'unknown',
            'safe_error_code' => 'unknown_event',
        ]);
        $this->assertSame('accepted', $recipient->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_provider_exception_uses_safe_taxonomy_and_never_exposes_raw_body(): void
    {
        Log::spy();
        Http::fake([
            '*email/send.json*' => Http::response([
                'message' => self::RAW_CANARY,
                'recipient' => self::RECIPIENT,
                'token' => 'provider-token-canary',
            ], 503, ['X-Request-ID' => 'safe-request-123']),
        ]);

        try {
            app(UnisenderGoClient::class)->sendEmail($this->outboundMessage());
            $this->fail('Expected a safe provider exception.');
        } catch (MailProviderException $exception) {
            $this->assertSame(MailProviderSafeErrorCode::Provider5xx, $exception->safeCode);
            $this->assertSame('5xx', $exception->httpStatusCategory);
            $this->assertSame('safe-request-123', $exception->safeRequestId);
            $this->assertStringNotContainsString(self::RAW_CANARY, $exception->getMessage());
            $this->assertStringNotContainsString(self::RECIPIENT, $exception->getMessage());
            $this->assertStringNotContainsString('provider-token-canary', $exception->getMessage());
        }

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return ! str_contains($serialized, self::RAW_CANARY)
                && ! str_contains($serialized, self::RECIPIENT)
                && ! str_contains($serialized, 'provider-token-canary');
        })->once();
    }

    public function test_unsafe_provider_job_id_is_ambiguous_and_never_exposed(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response([
                'job_id' => 'unsafe job '.self::RAW_CANARY,
                'recipient' => self::RECIPIENT,
            ]),
        ]);

        try {
            app(UnisenderGoClient::class)->sendEmail($this->outboundMessage());
            $this->fail('Unsafe provider ID must not be accepted.');
        } catch (MailProviderException $exception) {
            $this->assertSame(MailProviderSafeErrorCode::AmbiguousAcceptance, $exception->safeCode);
            $this->assertTrue($exception->ambiguousAcceptance);
            $this->assertStringNotContainsString(self::RAW_CANARY, $exception->getMessage());
            $this->assertStringNotContainsString(self::RECIPIENT, $exception->getMessage());
        }
    }

    public function test_credential_shaped_response_header_is_not_retained(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response(
                ['job_id' => 'job-safe-header-1'],
                200,
                ['X-Request-ID' => self::API_KEY],
            ),
        ]);

        $result = app(UnisenderGoClient::class)->sendEmail($this->outboundMessage());

        $this->assertNull($result->safeRequestId);
        $this->assertArrayNotHasKey('safe_request_id', $result->response);
    }

    public function test_oversized_provider_response_is_rejected_without_raw_text(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response(str_repeat(self::RAW_CANARY, 70_000)),
        ]);

        try {
            app(UnisenderGoClient::class)->sendEmail($this->outboundMessage());
            $this->fail('Oversized provider response must be rejected.');
        } catch (MailProviderException $exception) {
            $this->assertSame(MailProviderSafeErrorCode::MalformedResponse, $exception->safeCode);
            $this->assertStringNotContainsString(self::RAW_CANARY, $exception->getMessage());
        }
    }

    public function test_outbound_campaign_persists_hashes_and_safe_errors_only(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response([
                'job_id' => 'job-safe-1',
                'failed_emails' => [],
                'raw_provider_field' => self::RAW_CANARY,
                'recipient_copy' => self::RECIPIENT,
            ], 200, ['X-Request-ID' => 'request-safe-1']),
        ]);
        $contact = MailingContact::query()->create([
            'email' => self::RECIPIENT,
            'normalized_email' => self::RECIPIENT,
            'consent_status' => 'confirmed',
        ]);
        $set = MailingContactSet::query()->create(['name' => 'Safe set', 'type' => 'manual']);
        app(RecipientSetService::class)->addContacts($set, [$contact->id]);
        $campaign = $this->campaign(['contact_set_id' => $set->id]);

        app(MailingCampaignService::class)->startSending($campaign);

        $message = MailingMessage::query()->sole();
        $this->assertNotNull($message->request_hash);
        $this->assertNotNull($message->response_hash);
        $this->assertSame('2xx', $message->http_status_category);
        $this->assertSame('request-safe-1', $message->safe_request_id);
        $this->assertNull($message->request_payload);
        $this->assertNull($message->response_payload);
        $this->assertNull($message->failed_emails);
        $this->assertNull($message->error_message);

        $stored = json_encode(DB::table('mailing_messages')->first(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::RAW_CANARY, $stored);
        $this->assertStringNotContainsString('raw_provider_field', $stored);
    }

    public function test_outbound_failure_persists_no_exception_message(): void
    {
        Http::fake([
            '*email/send.json*' => Http::response([
                'message' => self::RAW_CANARY,
                'recipient' => self::RECIPIENT,
            ], 500),
        ]);
        $contact = MailingContact::query()->create([
            'email' => self::RECIPIENT,
            'normalized_email' => self::RECIPIENT,
            'consent_status' => 'confirmed',
        ]);
        $set = MailingContactSet::query()->create(['name' => 'Failure set', 'type' => 'manual']);
        app(RecipientSetService::class)->addContacts($set, [$contact->id]);
        $campaign = $this->campaign(['contact_set_id' => $set->id]);

        try {
            app(MailingCampaignService::class)->startSending($campaign);
            $this->fail('Expected provider failure.');
        } catch (MailProviderException) {
            // Expected safe exception.
        }

        $message = MailingMessage::query()->sole();
        $this->assertSame('provider_5xx', $message->safe_error_code);
        $this->assertNull($message->error_message);
        $this->assertNull($message->response_payload);
        $this->assertSame('provider_5xx', MailingCampaignRecipient::query()->sole()->safe_error_code);
        $this->assertNull(MailingCampaignRecipient::query()->sole()->failure_reason);
        $this->assertStringNotContainsString(self::RAW_CANARY, json_encode([
            DB::table('mailing_messages')->first(),
            DB::table('mailing_campaign_recipients')->first(),
        ], JSON_THROW_ON_ERROR));
    }

    public function test_eloquent_models_reject_new_deprecated_raw_provider_writes(): void
    {
        $models = [
            (new MailingWebhookCall)->forceFill([
                'provider' => 'unisender_go',
                'auth_valid' => true,
                'raw_payload' => self::RAW_CANARY,
                'created_at' => now(),
            ]),
            (new MailingEvent)->forceFill([
                'provider' => 'unisender_go',
                'event_fingerprint' => hash('sha256', 'guarded-event'),
                'event_name' => 'email_status',
                'payload' => ['raw' => self::RAW_CANARY],
                'created_at' => now(),
            ]),
            (new MailingMessage)->forceFill([
                'email' => self::RECIPIENT,
                'subject' => 'Guarded',
                'response_payload' => ['raw' => self::RAW_CANARY],
            ]),
            (new MailingCampaignRecipient)->forceFill([
                'campaign_id' => 1,
                'email' => self::RECIPIENT,
                'failure_reason' => self::RAW_CANARY,
            ]),
        ];

        foreach ($models as $model) {
            try {
                $model->save();
                $this->fail('Deprecated provider payload write must be rejected.');
            } catch (\LogicException $exception) {
                $this->assertSame('Deprecated provider payload columns are read-only.', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('mailing_webhook_calls', 0);
        $this->assertDatabaseCount('mailing_events', 0);
        $this->assertDatabaseCount('mailing_messages', 0);
    }

    public function test_outreach_profile_uses_one_attempt_and_ambiguous_acceptance_never_resends(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException(self::RAW_CANARY);
        });

        try {
            app(UnisenderGoClient::class)->sendEmail(
                $this->outboundMessage(),
                UnisenderRequestProfile::OutreachZeroRetry,
            );
            $this->fail('Expected ambiguous acceptance.');
        } catch (MailProviderException $exception) {
            $this->assertSame(MailProviderSafeErrorCode::AmbiguousAcceptance, $exception->safeCode);
            $this->assertTrue($exception->ambiguousAcceptance);
            $this->assertStringNotContainsString(self::RAW_CANARY, $exception->getMessage());
        }

        $this->assertSame(1, $attempts);
        $this->assertSame(0, UnisenderRequestProfile::OutreachZeroRetry->transportRetries());
        $this->assertSame(1, UnisenderRequestProfile::OutreachZeroRetry->queueTries());
        $this->assertSame(2, UnisenderRequestProfile::LegacyManual->transportRetries());
        $this->assertSame(1, UnisenderRequestProfile::LegacyManual->queueTries());
    }

    public function test_audit_and_purge_are_dry_run_first_chunked_and_idempotent(): void
    {
        $this->seedLegacyRawRows();

        $this->artisan('mailings:provider-payloads:audit', ['--chunk' => 50])
            ->assertSuccessful()
            ->doesntExpectOutputToContain(self::RAW_CANARY)
            ->doesntExpectOutputToContain(self::RECIPIENT);
        $this->artisan('mailings:provider-payloads:purge', ['--chunk' => 50])
            ->assertSuccessful()
            ->expectsOutputToContain('Mode: dry-run')
            ->doesntExpectOutputToContain(self::RAW_CANARY)
            ->doesntExpectOutputToContain(self::RECIPIENT);
        $this->assertSame(self::RAW_CANARY, DB::table('mailing_webhook_calls')->value('raw_payload'));

        $result = app(LegacyMailProviderPayloadService::class)->purge(true, 50);
        $this->assertTrue($result['applied']);
        $this->assertGreaterThanOrEqual(4, $result['before']['total_rows']);
        $this->assertSame(0, $result['after']['total_rows']);
        $this->assertNull(DB::table('mailing_webhook_calls')->value('raw_payload'));
        $this->assertNull(DB::table('mailing_events')->value('payload'));
        $this->assertNull(DB::table('mailing_messages')->value('response_payload'));
        $this->assertNull(DB::table('mailing_campaign_recipients')->value('failure_reason'));
        $this->assertNull(DB::table('mailing_campaign_recipients')->value('last_clicked_url'));
        $this->assertSame(
            MailProviderSafeErrorCode::ProcessingFailedSafe->value,
            DB::table('mailing_campaign_recipients')->value('safe_error_code'),
        );
        $this->assertNotNull(DB::table('mailing_webhook_calls')->value('request_hash'));
        $this->assertNotNull(DB::table('mailing_messages')->value('response_hash'));

        $again = app(LegacyMailProviderPayloadService::class)->purge(true, 50);
        $this->assertSame(0, array_sum($again['updated_rows']));
    }

    public function test_production_purge_apply_is_blocked(): void
    {
        $this->seedLegacyRawRows();
        $this->app->instance('env', 'production');

        try {
            app(LegacyMailProviderPayloadService::class)->purge(true, 50);
            $this->fail('Production purge must be blocked.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Provider payload purge apply is blocked outside local/testing/staging.', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }

        $this->assertSame(self::RAW_CANARY, DB::table('mailing_webhook_calls')->value('raw_payload'));
    }

    public function test_dedicated_rate_limit_rejects_safely_before_persistence(): void
    {
        $key = $this->rateLimitKey('127.0.0.1');
        for ($attempt = 0; $attempt < 120; $attempt++) {
            RateLimiter::hit($key, 60);
        }

        $this->rawPost('{"auth":"invalid"}')
            ->assertStatus(429)
            ->assertJsonPath('code', MailProviderSafeErrorCode::RateLimited->value);

        $this->assertNoWebhookMutation();
    }

    public function test_route_registry_has_dedicated_throttle_and_pre_persistence_verifier(): void
    {
        $route = Route::getRoutes()->getByName('webhooks.unisender-go.handle');
        $middleware = $route?->gatherMiddleware() ?? [];

        $this->assertNotNull($route);
        $this->assertContains('throttle:unisender-webhook', $middleware);
        $this->assertContains(\App\Http\Middleware\VerifyUnisenderWebhookRequest::class, $middleware);
        $this->assertNotContains('auth:sanctum', $middleware);
        $this->assertNotContains(\Illuminate\Session\Middleware\StartSession::class, $middleware);
        $this->assertNotContains(\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class, $middleware);
        $this->assertNotContains(\App\Http\Middleware\HandleInertiaRequests::class, $middleware);
    }

    private function assertNoWebhookMutation(): void
    {
        $this->assertDatabaseCount('mailing_webhook_calls', 0);
        $this->assertDatabaseCount('mailing_events', 0);
        Queue::assertNothingPushed();
    }

    private function signedBody(array $events, string $requestMarker = 'request-default'): string
    {
        $payload = [
            'events_by_user' => [[
                'events' => $events,
            ]],
            'request_marker' => $requestMarker,
            'auth' => 'signature-placeholder',
        ];
        $template = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $bodyForHash = str_replace('"signature-placeholder"', '"'.self::API_KEY.'"', $template);

        return str_replace('signature-placeholder', md5($bodyForHash), $template);
    }

    private function rawPost(string $body, array $server = []): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/webhooks/unisender-go', [], [], [], $server + [
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function event(
        string $eventId,
        string $status,
        ?MailingCampaignRecipient $recipient = null,
    ): array {
        return [
            'event_id' => $eventId,
            'event_name' => 'transactional_email_status',
            'event_data' => [
                'job_id' => $recipient?->unisender_job_id ?: 'job-secure-1',
                'message_id' => 'message-secure-1',
                'email' => $recipient?->email ?: self::RECIPIENT,
                'status' => $status,
                'event_time' => '2026-08-17T10:00:00+00:00',
                'url' => 'https://example.test/path?token='.self::RAW_CANARY,
                'delivery_info' => ['destination_response' => self::RAW_CANARY],
                'client_info' => ['ip' => '203.0.113.77', 'user_agent' => 'provider-agent-canary'],
                'metadata' => array_filter([
                    'campaign_id' => $recipient?->campaign_id,
                    'campaign_recipient_id' => $recipient?->id,
                    'contact_id' => $recipient?->contact_id,
                ]),
            ],
        ];
    }

    private function recipient(): MailingCampaignRecipient
    {
        $campaign = $this->campaign();
        $contact = MailingContact::query()->create([
            'email' => self::RECIPIENT,
            'normalized_email' => self::RECIPIENT,
            'consent_status' => 'confirmed',
        ]);

        return MailingCampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'email' => self::RECIPIENT,
            'status' => 'accepted',
            'sent_at' => now(),
            'unisender_job_id' => 'job-secure-1',
        ]);
    }

    private function campaign(array $overrides = []): MailingCampaign
    {
        return MailingCampaign::query()->create($overrides + [
            'name' => 'Security campaign',
            'type' => 'mass_offer',
            'status' => 'ready',
            'subject' => 'Safe subject',
            'html_markup' => '<p>Safe body</p><a href="{{unsubscribe_url}}">unsubscribe</a>',
            'plaintext' => 'Safe body {{unsubscribe_url}}',
            'from_email' => 'sales@pischeprom.test',
            'from_name' => 'Pischeprom',
            'reply_to' => 'sales@pischeprom.test',
            'compliance_status' => 'approved',
        ]);
    }

    private function outboundMessage(): array
    {
        return [
            'recipients' => [['email' => self::RECIPIENT]],
            'body' => ['html' => '<p>Hello</p>', 'plaintext' => 'Hello'],
            'subject' => 'Safe subject',
            'from_email' => 'sales@pischeprom.test',
            'from_name' => 'Pischeprom',
            'reply_to' => 'sales@pischeprom.test',
            'idempotence_key' => 'security-idempotence-key',
        ];
    }

    private function seedLegacyRawRows(): void
    {
        $campaign = $this->campaign();
        $recipient = MailingCampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'email' => self::RECIPIENT,
            'status' => 'failed',
        ]);
        DB::table('mailing_campaign_recipients')->where('id', $recipient->id)->update([
            'last_clicked_url' => 'https://example.test/'.self::RAW_CANARY,
            'failure_reason' => self::RAW_CANARY,
            'delivery_info' => json_encode(['raw' => self::RAW_CANARY]),
        ]);
        DB::table('mailing_webhook_calls')->insert([
            'provider' => 'unisender_go',
            'auth_valid' => true,
            'raw_payload' => self::RAW_CANARY,
            'parsed_payload' => json_encode(['recipient' => self::RECIPIENT]),
            'events_count' => 1,
            'error_message' => self::RAW_CANARY,
            'created_at' => now(),
        ]);
        DB::table('mailing_events')->insert([
            'provider' => 'unisender_go',
            'event_fingerprint' => hash('sha256', 'legacy-event'),
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'email' => self::RECIPIENT,
            'event_name' => 'transactional_email_status',
            'status' => 'hard_bounced',
            'url' => 'https://example.test/'.self::RAW_CANARY,
            'destination_response' => self::RAW_CANARY,
            'user_agent' => self::RAW_CANARY,
            'ip' => '203.0.113.99',
            'metadata' => json_encode(['recipient' => self::RECIPIENT]),
            'payload' => json_encode(['message' => self::RAW_CANARY]),
            'created_at' => now(),
        ]);
        DB::table('mailing_messages')->insert([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'email' => self::RECIPIENT,
            'subject' => 'Legacy subject',
            'status' => 'failed',
            'request_payload' => json_encode(['recipient' => self::RECIPIENT]),
            'response_payload' => json_encode(['message' => self::RAW_CANARY]),
            'failed_emails' => json_encode([self::RECIPIENT]),
            'error_message' => self::RAW_CANARY,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rateLimitKey(string $ip): string
    {
        $limitKey = 'unisender-webhook:'.hash_hmac(
            'sha256',
            $ip,
            (string) (config('app.key') ?: 'unisender-webhook-rate-limit'),
        );

        return md5('unisender-webhook'.$limitKey);
    }
}
