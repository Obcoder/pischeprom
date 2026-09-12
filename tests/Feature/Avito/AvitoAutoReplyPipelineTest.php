<?php

namespace Tests\Feature\Avito;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Jobs\Avito\ProcessAvitoAutoReplyJob;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Models\AvitoWebhookEvent;
use App\Services\Avito\AutoReply\AvitoAutoReplyService;
use App\Services\Avito\AvitoAutoReplyDispatcher;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AvitoAutoReplyPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        Queue::fake();
        Http::preventStrayRequests();
        config([
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'pipeline-client',
            'avito.client_secret' => 'pipeline-client-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.mutations_enabled' => true,
            'avito.webhook_secret' => 'pipeline-secret +/=?',
            'avito.messenger.chat_page_size' => 100,
            'avito.messenger.message_page_size' => 100,
            'avito.messenger.incremental_chat_limit' => 100,
            'avito.messenger.full_chat_limit' => 100,
            'avito.messenger.message_limit_per_chat' => 100,
        ]);
    }

    public function test_registered_webhook_url_authenticates_without_custom_headers_and_is_redacted(): void
    {
        $url = route('api.avito.webhook', ['secret' => config('avito.webhook_secret')]);
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'pipeline-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/*' => Http::sequence()
                ->push(['ok' => true])
                ->push(['subscriptions' => [['url' => $url]]]),
        ]);
        app(AvitoMessengerService::class)->subscribe();
        $subscription = Http::recorded(fn ($request) => str_contains($request->url(), '/webhook'))->first();
        $this->assertNotNull($subscription);
        $url = $subscription[0]['url'];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(config('avito.webhook_secret'), $query['secret']);

        $this->postJson($url, $this->webhookPayload())->assertAccepted();
        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, 1);

        $subscriptions = app(AvitoMessengerService::class)->subscriptions();
        $this->assertStringContainsString('secret=[redacted]', $subscriptions[0]['url']);
        $this->assertStringNotContainsString(rawurlencode(config('avito.webhook_secret')), $subscriptions[0]['url']);
    }

    public function test_webhook_preserves_remote_epoch_in_moscow_application_timezone(): void
    {
        $originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'Europe/Moscow']);
        date_default_timezone_set('Europe/Moscow');

        try {
            $payload = $this->webhookPayload();
            $this->postJson('/api/avito/webhook', $payload, ['X-Secret' => config('avito.webhook_secret')])
                ->assertAccepted();

            $message = AvitoMessage::query()->sole();
            $this->assertSame($payload['payload']['value']['created'], $message->remote_created_at->timestamp);
            $this->assertTrue($message->remote_created_at->greaterThan(now()->subMinutes(15)));

            $publishedAt = now()->subSeconds(30)->startOfSecond();
            $payload['id'] = 'published-at-event';
            $payload['payload']['value']['id'] = 'published-at-message';
            $payload['payload']['value']['published_at'] = $publishedAt->copy()->utc()->toIso8601String();
            unset($payload['payload']['value']['created']);
            $this->postJson('/api/avito/webhook', $payload, ['X-Secret' => config('avito.webhook_secret')])
                ->assertAccepted();
            $this->assertSame(
                $publishedAt->timestamp,
                AvitoMessage::query()->where('external_message_id', 'published-at-message')->sole()->remote_created_at->timestamp,
            );
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_registration_without_secret_fails_before_calling_avito(): void
    {
        config(['avito.webhook_secret' => null]);

        try {
            app(AvitoMessengerService::class)->subscribe();
            $this->fail('An unauthenticated webhook must not be registered.');
        } catch (AvitoException $exception) {
            $this->assertStringContainsString('AVITO_WEBHOOK_SECRET', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_incremental_poll_recovers_fresh_incoming_including_chat_last_message_and_deduplicates_webhook(): void
    {
        $this->account();
        $this->fakeSync([$this->remoteMessage('fresh-in', now()->subMinute()->timestamp)]);

        app(AvitoMessengerService::class)->sync();
        app(AvitoMessengerService::class)->sync();

        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, 1);
        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, fn ($job) => $job->messageId === AvitoMessage::query()->value('id')
            && $job->delay->isFuture() && ! $job->historical);

        $this->postJson('/api/avito/webhook', $this->webhookPayload(), ['X-Secret' => config('avito.webhook_secret')])
            ->assertAccepted();
        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, 1);
    }

    public function test_full_sync_and_stale_poll_history_never_schedule_auto_replies(): void
    {
        $this->account();
        $this->fakeSync([$this->remoteMessage('fresh-in', now()->subMinute()->timestamp)]);
        app(AvitoMessengerService::class)->sync(full: true);
        Queue::assertNotPushed(ProcessAvitoAutoReplyJob::class);

        $this->travel(20)->minutes();
        $this->fakeSync([$this->remoteMessage('old-in', now()->subHour()->timestamp)]);
        app(AvitoMessengerService::class)->sync();
        Queue::assertNotPushed(ProcessAvitoAutoReplyJob::class);
    }

    public function test_poll_archives_operator_answer_before_dispatching_customer_message(): void
    {
        $this->account();
        $this->fakeSync([
            $this->remoteMessage('fresh-in', now()->subMinute()->timestamp),
            $this->remoteMessage('operator-answer', now()->subSeconds(20)->timestamp, 'out'),
        ]);
        $this->mock(AvitoAutoReplyDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchLatestFreshIncoming')->once()->andReturnUsing(function (AvitoChat $chat): void {
                $this->assertSame(2, $chat->messages()->count());
                $this->assertTrue($chat->messages()->where('external_message_id', 'operator-answer')->where('direction', 'out')->exists());
            });
        });

        app(AvitoMessengerService::class)->sync();
    }

    public function test_failed_queue_push_returns_retryable_webhook_status_and_releases_unique_lock(): void
    {
        $dispatcher = Bus::getFacadeRoot();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));

        $this->postJson('/api/avito/webhook', $this->webhookPayload(), ['X-Secret' => config('avito.webhook_secret')])
            ->assertStatus(503)->assertJsonPath('ok', false);
        $this->assertSame('error', AvitoWebhookEvent::query()->sole()->status);

        Bus::swap($dispatcher);
        $this->postJson('/api/avito/webhook', $this->webhookPayload(), ['X-Secret' => config('avito.webhook_secret')])
            ->assertOk()->assertJsonPath('duplicate', true);

        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, 1);
        $this->assertSame('processed', AvitoWebhookEvent::query()->sole()->status);
    }

    public function test_worker_retries_transient_classification_error_but_never_uncertain_send(): void
    {
        $service = Mockery::mock(AvitoAutoReplyService::class);
        $service->shouldReceive('evaluateWebhookMessage')->with(12, false)->once()
            ->andReturn(new AvitoAutoReplyDecision(['outcome' => 'error', 'reason_code' => 'classifier_error']));
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(30);
        $job = new ProcessAvitoAutoReplyJob(12);
        $job->setJob($queueJob);
        $job->handle($service);

        $service->shouldReceive('evaluateWebhookMessage')->with(13, false)->once()
            ->andReturn(new AvitoAutoReplyDecision(['outcome' => 'error', 'reason_code' => 'send_error']));
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldNotReceive('release');
        $job = new ProcessAvitoAutoReplyJob(13);
        $job->setJob($queueJob);
        $job->handle($service);
    }

    private function account(): AvitoMessengerAccount
    {
        return AvitoMessengerAccount::query()->create([
            'source_key' => 'client_credentials',
            'external_user_id' => '777',
            'name' => 'Pipeline test',
            'sync_enabled' => true,
            'last_synced_at' => now()->subMinutes(5),
        ]);
    }

    private function remoteMessage(string $id, int $created, string $direction = 'in'): array
    {
        return [
            'id' => $id,
            'author_id' => $direction === 'in' ? 999 : 777,
            'direction' => $direction,
            'type' => 'text',
            'created' => $created,
            'content' => ['text' => $direction === 'in' ? 'Здравствуйте!' : 'Здравствуйте, я менеджер.'],
        ];
    }

    private function webhookPayload(): array
    {
        return [
            'id' => 'pipeline-event',
            'payload' => [
                'type' => 'message',
                'value' => $this->remoteMessage('fresh-in', now()->subMinute()->timestamp) + [
                    'chat_id' => 'pipeline-chat',
                    'chat_type' => 'u2i',
                    'user_id' => 777,
                ],
            ],
        ];
    }

    private function fakeSync(array $messages): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'pipeline-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v2/accounts/777/chats*' => Http::response([
                'chats' => [[
                    'id' => 'pipeline-chat',
                    'chat_type' => 'u2i',
                    'last_message' => $messages[0],
                ]],
            ]),
            'https://api.avito.ru/messenger/v3/accounts/777/chats/pipeline-chat/messages/*' => Http::response([
                'messages' => $messages, 'meta' => ['has_more' => false],
            ]),
        ]);
    }
}
