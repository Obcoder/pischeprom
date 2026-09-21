<?php

namespace Tests\Feature\Avito;

use App\Jobs\Avito\ArchiveAvitoMessageMediaJob;
use App\Jobs\Avito\BootstrapAvitoChatHistoryJob;
use App\Jobs\Avito\ProcessAvitoAutoReplyJob;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AutoReply\AvitoAutoReplyService;
use App\Services\Avito\AvitoAutoReplyDispatcher;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\BuildsIsolatedAvitoDatabase;
use Tests\TestCase;

class BootstrapAvitoChatHistoryJobTest extends TestCase
{
    use BuildsIsolatedAvitoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAvitoTestDatabase();
        $this->travelTo(now()->startOfSecond());
        config(['queue.default' => 'database']);
        Cache::clear();
        Queue::fake();
        Http::preventStrayRequests();
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
    }

    public function test_async_reply_jobs_queue_one_history_import_without_network_or_ai_work(): void
    {
        $incoming = $this->message();
        $next = $this->message($incoming->chat);
        $service = $this->mock(AvitoAutoReplyService::class);
        $service->shouldNotReceive('evaluateWebhookMessage');
        $this->mock(AvitoMessengerService::class)->shouldNotReceive('refreshChat');

        (new ProcessAvitoAutoReplyJob($incoming->id))->handle($service);
        (new ProcessAvitoAutoReplyJob($next->id))->handle($service);

        Queue::assertPushed(BootstrapAvitoChatHistoryJob::class, 1);
        Queue::assertPushed(BootstrapAvitoChatHistoryJob::class, fn ($job) => $job->chatId === $incoming->avito_chat_id && $job->timeout === 840);
        Queue::assertNotPushed(ProcessAvitoAutoReplyJob::class);
        $this->assertNull($incoming->chat->fresh()->history_synced_at);
        Http::assertNothingSent();
    }

    public function test_bootstrap_resumes_the_newest_live_message_only_after_import_completion(): void
    {
        $incoming = $this->message();
        $latest = null;
        $messenger = $this->mock(AvitoMessengerService::class);
        $messenger->shouldReceive('refreshChat')->once()->andReturnUsing(function (AvitoChat $chat, int $limit, bool $archiveMedia) use (&$latest): AvitoChat {
            $this->assertFalse($archiveMedia);
            $this->message($chat, ['remote_created_at' => now()->subYears(10)]);
            $latest = $this->message($chat, ['remote_created_at' => now()->addSecond()]);
            $this->message($chat, ['remote_created_at' => now()->addMinutes(10)]);
            $chat->update(['history_synced_at' => now()]);

            return $chat;
        });

        (new BootstrapAvitoChatHistoryJob($incoming->avito_chat_id))->handle($messenger, app(AvitoAutoReplyDispatcher::class));

        $this->assertNotNull($incoming->chat->fresh()->history_synced_at);
        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, 1);
        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, fn ($job) => $job->messageId === $latest->id && ! $job->historical);
        $this->assertDatabaseCount('avito_auto_reply_decisions', 0);
        Http::assertNothingSent();
    }

    public function test_import_failure_remains_retryable_and_never_resumes_ai(): void
    {
        $incoming = $this->message();
        $messenger = $this->mock(AvitoMessengerService::class);
        $messenger->shouldReceive('refreshChat')->once()->andThrow(new RuntimeException('History unavailable'));

        try {
            (new BootstrapAvitoChatHistoryJob($incoming->avito_chat_id))->handle($messenger, app(AvitoAutoReplyDispatcher::class));
            $this->fail('The queue must retry a failed history import.');
        } catch (RuntimeException $exception) {
            $this->assertSame('History unavailable', $exception->getMessage());
        }

        $this->assertNull($incoming->chat->fresh()->history_synced_at);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('avito_auto_reply_decisions', 0);
        Http::assertNothingSent();
    }

    public function test_missing_completion_marker_cannot_be_mistaken_for_a_complete_archive(): void
    {
        $incoming = $this->message();
        $messenger = $this->mock(AvitoMessengerService::class);
        $messenger->shouldReceive('refreshChat')->once()->andReturn($incoming->chat);

        try {
            (new BootstrapAvitoChatHistoryJob($incoming->avito_chat_id))->handle($messenger, app(AvitoAutoReplyDispatcher::class));
            $this->fail('An incomplete import cannot resume replies.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Avito chat history import did not complete.', $exception->getMessage());
        }
        Queue::assertNothingPushed();
    }

    public function test_original_reply_unique_lock_race_retries_dispatch_without_reimporting_history(): void
    {
        $incoming = $this->message();
        $original = new ProcessAvitoAutoReplyJob($incoming->id);
        $uniqueLock = new UniqueLock(Cache::store());
        $this->assertTrue($uniqueLock->acquire($original));
        $messenger = $this->mock(AvitoMessengerService::class);
        $messenger->shouldReceive('refreshChat')->once()->andReturnUsing(function (AvitoChat $chat): AvitoChat {
            $chat->update(['history_synced_at' => now()]);

            return $chat;
        });
        $bootstrap = (new BootstrapAvitoChatHistoryJob($incoming->avito_chat_id))->withFakeQueueInteractions();

        $bootstrap->handle($messenger, app(AvitoAutoReplyDispatcher::class));

        $bootstrap->assertReleased(5);
        Queue::assertNothingPushed();
        $this->assertNotNull($incoming->chat->fresh()->history_synced_at);
        $uniqueLock->release($original);
        $bootstrap->handle($messenger, app(AvitoAutoReplyDispatcher::class));

        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, 1);
    }

    public function test_established_archive_never_needs_bootstrap_or_avito_for_context(): void
    {
        $incoming = $this->message();
        $incoming->chat->update(['history_synced_at' => now()->subYears(10)]);
        $this->mock(AvitoMessengerService::class)->shouldNotReceive('refreshChat');
        $service = $this->mock(AvitoAutoReplyService::class);
        $service->shouldReceive('evaluateWebhookMessage')->once()->with($incoming->id, false)->andReturnNull();

        (new ProcessAvitoAutoReplyJob($incoming->id))->handle($service);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_sync_driver_bootstraps_inline_once_without_recursively_queueing_replies(): void
    {
        config(['queue.default' => 'sync']);
        $incoming = $this->message();
        $messenger = $this->mock(AvitoMessengerService::class);
        $messenger->shouldReceive('refreshChat')->once()->andReturnUsing(function (AvitoChat $chat): AvitoChat {
            $chat->update(['history_synced_at' => now()]);

            return $chat;
        });
        $service = $this->mock(AvitoAutoReplyService::class);
        $service->shouldReceive('evaluateWebhookMessage')->twice()->with($incoming->id, false)->andReturnNull();

        (new ProcessAvitoAutoReplyJob($incoming->id))->handle($service);
        (new ProcessAvitoAutoReplyJob($incoming->id))->handle($service);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_bootstrap_does_not_replay_questions_that_became_stale_during_import(): void
    {
        $incoming = $this->message(null, ['remote_created_at' => now()->subMinutes(16)]);
        $incoming->chat->update(['history_synced_at' => now()]);
        $messenger = $this->mock(AvitoMessengerService::class);
        $messenger->shouldNotReceive('refreshChat');

        (new BootstrapAvitoChatHistoryJob($incoming->avito_chat_id))->handle($messenger, app(AvitoAutoReplyDispatcher::class));

        Queue::assertNothingPushed();
    }

    public function test_history_import_archives_text_and_queues_media_without_waiting_for_downloads(): void
    {
        config([
            'avito.enabled' => true, 'avito.client_id' => 'bootstrap-client', 'avito.client_secret' => 'bootstrap-secret',
            'avito.api_base_url' => 'https://api.avito.ru', 'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru'],
        ]);
        $incoming = $this->message();
        $incoming->chat->account->update(['external_user_id' => '777']);
        $externalChatId = $incoming->chat->external_chat_id;
        $cdnUrl = 'https://img.k.avito.ru/chat/1280x960/archived-image.jpg';
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v2/accounts/777/chats/'.$externalChatId => Http::response(['id' => $externalChatId]),
            'https://api.avito.ru/messenger/v3/accounts/777/chats/'.$externalChatId.'/messages*' => Http::response([
                'messages' => [[
                    'id' => 'old-image', 'author_id' => 999, 'direction' => 'in', 'type' => 'image',
                    'created' => now()->subYears(10)->timestamp,
                    'content' => ['text' => 'Архивная подпись.', 'image' => ['sizes' => ['1280x960' => $cdnUrl]]],
                ]],
                'meta' => ['has_more' => false],
            ]),
        ]);

        app(AvitoMessengerService::class)->refreshChat($incoming->chat, archiveMedia: false);

        $image = AvitoMessage::where('external_message_id', 'old-image')->sole();
        $this->assertSame('Архивная подпись.', $image->text);
        $this->assertNull($image->attachments()->sole()->archived_at);
        $this->assertNotNull($incoming->chat->fresh()->history_synced_at);
        Queue::assertPushed(ArchiveAvitoMessageMediaJob::class, fn ($job) => $job->messageId === $image->id && $job->delay->eq(now()->addMinute()));
        Http::assertSentCount(3);
        Http::assertNotSent(fn ($request) => $request->url() === $cdnUrl);
    }

    private function message(?AvitoChat $chat = null, array $attributes = []): AvitoMessage
    {
        if (! $chat) {
            $account = AvitoMessengerAccount::create(['source_key' => Str::uuid()->toString()]);
            $chat = AvitoChat::create([
                'avito_messenger_account_id' => $account->id, 'external_chat_id' => Str::uuid()->toString(),
            ]);
        }

        return AvitoMessage::create(array_replace([
            'avito_chat_id' => $chat->id, 'external_message_id' => Str::uuid()->toString(),
            'direction' => 'in', 'type' => 'text', 'remote_type' => 'text',
            'text' => 'Как оформить заявку?', 'remote_created_at' => now(),
        ], $attributes));
    }
}
