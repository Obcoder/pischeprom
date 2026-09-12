<?php

namespace Tests\Feature\Avito;

use App\Events\AvitoDataChanged;
use App\Http\Controllers\AvitoAutoReplyController;
use App\Jobs\Avito\ProcessAvitoAutoReplyJob;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AutoReply\AvitoAutoReplyClassifier;
use App\Services\Avito\AutoReply\AvitoAutoReplyDiagnostics;
use App\Services\Avito\AutoReply\AvitoAutoReplyService;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Concerns\BuildsIsolatedAvitoDatabase;
use Tests\TestCase;

class AvitoAutoReplyEmergencyStopTest extends TestCase
{
    use BuildsIsolatedAvitoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAvitoTestDatabase();
        config([
            'ai-price-lists.ai.base_url' => 'https://ai.api.cloud.yandex.net/v1',
            'ai-price-lists.ai.api_key' => 'test-key',
            'ai-price-lists.ai.folder_id' => 'test-folder',
            'ai-price-lists.ai.model' => 'yandexgpt-5.1',
        ]);
        Cache::clear();
        Http::preventStrayRequests();
    }

    public function test_control_and_emergency_stop_do_not_resolve_ai_and_stop_persists_without_realtime(): void
    {
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        $this->app->bind(AvitoAutoReplyClassifier::class, fn () => throw new RuntimeException('AI container unavailable'));

        $this->getJson('/api/avito/messenger/auto-replies/control')
            ->assertOk()->assertJsonPath('settings.mode', 'active')
            ->assertJsonMissingPath('rules')->assertJsonMissingPath('diagnostics');
        $stopped = $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')
            ->assertOk()->assertJsonPath('settings.mode', 'off')
            ->assertJsonPath('settings.is_emergency_stopped', true)
            ->json('settings.emergency_stopped_at');
        $this->assertNotNull($stopped);
        $this->travel(10)->seconds();
        $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')
            ->assertOk()->assertJsonPath('settings.emergency_stopped_at', $stopped);
        $this->getJson('/api/avito/messenger/auto-replies/control')
            ->assertOk()->assertJsonPath('settings.is_emergency_stopped', true);
        Http::assertNothingSent();
    }

    public function test_stale_settings_cannot_clear_stop_and_explicit_resume_only_enables_shadow(): void
    {
        $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')->assertOk();
        foreach (['shadow', 'pilot', 'active'] as $mode) {
            $this->patchJson('/api/avito/messenger/auto-replies/settings', [
                'mode' => $mode, 'emergency_stopped_at' => null, 'is_emergency_stopped' => false,
            ])->assertUnprocessable()->assertJsonValidationErrors('mode');
        }
        $this->patchJson('/api/avito/messenger/auto-replies/settings', ['daily_limit' => 25])
            ->assertOk()->assertJsonPath('settings.mode', 'off')->assertJsonPath('settings.is_emergency_stopped', true);
        $this->postJson('/api/avito/messenger/auto-replies/resume', ['mode' => 'active'])
            ->assertOk()->assertJsonPath('settings.mode', 'shadow')
            ->assertJsonPath('settings.is_emergency_stopped', false)->assertJsonPath('settings.emergency_stopped_at', null);
        $this->patchJson('/api/avito/messenger/auto-replies/settings', ['mode' => 'active'])
            ->assertOk()->assertJsonPath('settings.mode', 'active');
        // A delayed duplicate resume must not overwrite an operator's newer mode.
        $this->postJson('/api/avito/messenger/auto-replies/resume')
            ->assertOk()->assertJsonPath('settings.mode', 'active');
        Http::assertNothingSent();
    }

    public function test_queued_and_historical_messages_are_skipped_before_ai_while_stopped(): void
    {
        $incoming = $this->message();
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        $queued = new ProcessAvitoAutoReplyJob($incoming->id);
        $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')->assertOk();
        $queued->handle(app(AvitoAutoReplyService::class));
        $this->assertDatabaseHas('avito_auto_reply_decisions', [
            'avito_message_id' => $incoming->id, 'outcome' => 'skipped', 'reason_code' => 'emergency_stopped',
        ]);

        $historical = $this->message($incoming->chat);
        (new ProcessAvitoAutoReplyJob($historical->id, historical: true))->handle(app(AvitoAutoReplyService::class));
        $this->assertDatabaseHas('avito_auto_reply_decisions', [
            'avito_message_id' => $historical->id, 'outcome' => 'skipped', 'reason_code' => 'emergency_stopped',
        ]);
        Http::assertNothingSent();
    }

    public function test_stop_during_model_generation_cancels_reply_and_does_not_wait_for_outbound_lock(): void
    {
        $incoming = $this->message();
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        $outbound = Cache::lock('avito:auto-reply:outbound', 180);
        $this->assertTrue($outbound->get());
        Http::fake(function () {
            $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')
                ->assertOk()->assertJsonPath('settings.mode', 'off');

            return Http::response($this->aiResponse());
        });

        try {
            $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);
            $this->assertSame('skipped', $decision->outcome);
            $this->assertSame('emergency_stopped', $decision->reason_code);
            Http::assertSentCount(1);
            $this->assertSame(0, AvitoMessage::where('direction', 'out')->count());
        } finally {
            $outbound->release();
        }
    }

    public function test_stop_between_last_validation_and_sending_authorization_blocks_send(): void
    {
        $incoming = $this->message();
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        Http::fake(['https://ai.api.cloud.yandex.net/v1/chat/completions' => Http::response($this->aiResponse())]);
        $this->mock(AvitoMessengerService::class)->shouldNotReceive('sendText');
        Event::listen('eloquent.saving: '.AvitoAutoReplyDecision::class, function (AvitoAutoReplyDecision $decision): void {
            if ($decision->outcome === 'sending') {
                app(AvitoAutoReplyController::class)->emergencyStop();
            }
        });

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);
        $this->assertSame('emergency_stopped', $decision->reason_code);
        $this->assertSame('skipped', $decision->outcome);
        Http::assertSentCount(1);
    }

    public function test_stop_does_not_hold_settings_lock_over_an_already_started_avito_request(): void
    {
        $incoming = $this->message();
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        Http::fake(['https://ai.api.cloud.yandex.net/v1/chat/completions' => Http::response($this->aiResponse())]);
        $this->mock(AvitoMessengerService::class)->shouldReceive('sendText')->once()
            ->andReturnUsing(function (AvitoChat $chat, string $text): AvitoMessage {
                $this->assertSame(0, DB::transactionLevel(), 'The settings row must be unlocked before the Avito request.');
                $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')->assertOk();

                // Avito may already have accepted this request; acknowledge it
                // honestly and keep the persistent stop for subsequent jobs.
                return AvitoMessage::create([
                    'avito_chat_id' => $chat->id, 'external_message_id' => 'already-sent',
                    'direction' => 'out', 'type' => 'text', 'remote_type' => 'text', 'text' => $text,
                ]);
            });
        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);
        $this->assertSame('sent', $decision->outcome);
        $this->assertSame('off', AvitoAutoReplySetting::current()->mode);
        $this->assertNotNull(AvitoAutoReplySetting::current()->emergency_stopped_at);
    }

    public function test_stop_during_avito_token_refresh_prevents_the_actual_message_request(): void
    {
        config([
            'avito.enabled' => true, 'avito.mutations_enabled' => true,
            'avito.client_id' => 'stop-client', 'avito.client_secret' => 'stop-secret',
            'avito.api_base_url' => 'https://api.avito.ru', 'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru'], 'avito.mutation_confirmation' => 'AVITO',
        ]);
        $incoming = $this->message();
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        Http::fake([
            'https://ai.api.cloud.yandex.net/v1/chat/completions' => Http::response($this->aiResponse()),
            'https://api.avito.ru/token' => function () {
                $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')->assertOk();

                return Http::response(['access_token' => 'stop-token', 'expires_in' => 86400]);
            },
        ]);

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);
        $this->assertSame('skipped', $decision->outcome);
        $this->assertSame('emergency_stopped', $decision->reason_code);
        Http::assertSentCount(2);
        $this->assertSame(0, AvitoMessage::where('direction', 'out')->count());
    }

    public function test_stop_survives_realtime_queue_failure_and_diagnostics_explain_it(): void
    {
        config(['realtime.enabled' => true, 'realtime.queue_connection' => 'database']);
        Queue::shouldReceive('connection')->with('database')->andReturnSelf();
        Queue::shouldReceive('pushOn')->with('realtime', Mockery::type(BroadcastEvent::class))
            ->andThrow(new RuntimeException('Queue unavailable'));
        Log::shouldReceive('warning')->with('avito_realtime_unavailable')->once();
        $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')
            ->assertOk()->assertJsonPath('settings.is_emergency_stopped', true);
        $report = app(AvitoAutoReplyDiagnostics::class)->report();
        $this->assertFalse($report['ready_for_sending']);
        $this->assertNotNull($report['emergency_stopped_at']);
        $this->assertContains('emergency_stopped', array_column($report['blockers'], 'code'));
    }

    public function test_emergency_stop_has_independent_rate_limit_from_busy_avito_reads(): void
    {
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->getJson('/api/avito/messenger/auto-replies/control')->assertOk();
        }
        $this->getJson('/api/avito/messenger/auto-replies/control')->assertStatus(429);
        $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')
            ->assertOk()->assertJsonPath('settings.is_emergency_stopped', true);
    }

    public function test_stop_and_resume_publish_settings_invalidation_without_sending_text(): void
    {
        config(['realtime.enabled' => true, 'realtime.queue_connection' => 'database']);
        Event::fake([AvitoDataChanged::class]);
        $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')->assertOk();
        $this->postJson('/api/avito/messenger/auto-replies/emergency-stop')->assertOk();
        $this->postJson('/api/avito/messenger/auto-replies/resume')->assertOk();
        Event::assertDispatchedTimes(AvitoDataChanged::class, 2);
        Event::assertDispatched(AvitoDataChanged::class, fn ($event) => $event->topics === ['avito_auto_replies']);
        Http::assertNothingSent();
    }

    private function message(?AvitoChat $chat = null): AvitoMessage
    {
        if (! $chat) {
            $account = AvitoMessengerAccount::create(['source_key' => 'client_credentials', 'external_user_id' => '777']);
            $chat = AvitoChat::create(['avito_messenger_account_id' => $account->id, 'external_chat_id' => 'chat-stop', 'chat_type' => 'u2i']);
        }

        return AvitoMessage::create([
            'avito_chat_id' => $chat->id, 'external_message_id' => (string) Str::uuid(),
            'direction' => 'in', 'type' => 'text', 'remote_type' => 'text',
            'text' => 'Здравствуйте', 'remote_created_at' => now(),
        ]);
    }

    private function aiResponse(): array
    {
        return ['id' => 'ai-stop-test', 'choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode([
            'intent' => 'greeting', 'confidence' => 0.95, 'runner_up_confidence' => 0.01,
            'unsafe' => false, 'mixed' => false, 'reason_code' => 'approved_intent',
            'response_text' => 'Здравствуйте! Чем помочь?', 'matched_intents' => ['greeting'],
        ], JSON_UNESCAPED_UNICODE)]]]];
    }
}
