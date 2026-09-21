<?php

namespace Tests\Feature\Avito;

use App\Events\AvitoDataChanged;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AvitoMessengerArchive;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsIsolatedAvitoDatabase;
use Tests\TestCase;

class AvitoUnreadStateTest extends TestCase
{
    use BuildsIsolatedAvitoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAvitoTestDatabase();
        config([
            'avito.enabled' => true,
            'avito.client_id' => 'unread-client',
            'avito.client_secret' => 'unread-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.mutations_enabled' => true,
            'avito.mutation_confirmation' => 'AVITO',
        ]);
        Http::preventStrayRequests();
        Queue::fake();
    }

    public function test_webhooks_update_both_badges_immediately_and_do_not_count_system_deleted_or_outgoing_messages(): void
    {
        $archive = app(AvitoMessengerArchive::class);
        $message = $archive->ingestWebhook(['payload' => ['value' => $this->payload('incoming') + [
            'chat_id' => 'webhook-chat', 'user_id' => 777,
        ]]]);
        $chat = $message->chat;
        $this->assertUnread($chat, 1);

        $archive->storeMessage($chat, $this->payload('incoming'));
        $archive->storeMessage($chat, $this->payload('outgoing', ['direction' => 'out', 'author_id' => 777]));
        $archive->storeMessage($chat, $this->payload('system', ['type' => 'system', 'created' => 1785916900, 'is_read' => true]));
        $archive->storeMessage($chat, $this->payload('deleted', ['type' => 'deleted']));
        $this->assertUnread($chat, 1);

        $archive->storeMessage($chat, $this->payload('incoming', ['type' => 'deleted']));
        $this->assertUnread($chat, 0);
    }

    public function test_read_messages_stay_read_when_replayed_webhooks_or_stale_sync_omit_receipts(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $message = $archive->storeMessage($chat, $this->payload('read-locally'));
        $archive->markChatRead($chat);
        $archive->storeMessage($chat, $this->payload('read-locally'));
        $archive->storeChat($chat->account, [
            'id' => $chat->external_chat_id,
            'last_message' => $this->payload('read-locally', ['is_read' => false]),
        ]);
        $this->assertTrue($message->fresh()->is_read);
        $this->assertUnread($chat, 0);

        $archive->storeMessage($chat, $this->payload('zero-read-timestamp', ['read' => 0]));
        $this->assertUnread($chat, 1);
    }

    public function test_incoming_read_receipts_cover_older_history_but_outgoing_receipts_do_not(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $older = $archive->storeMessage($chat, $this->payload('older', ['created' => 1785916000]));
        $archive->storeMessage($chat, $this->payload('sent', ['direction' => 'out', 'author_id' => 777, 'is_read' => true]));
        $this->assertUnread($chat, 1);

        $archive->storeChat($chat->account, ['id' => $chat->external_chat_id, 'last_message' => $this->payload('read-on-avito', ['read' => 1785916900])]);
        $this->assertTrue($older->fresh()->is_read);
        $this->assertUnread($chat, 0);

        $history = $archive->storeMessage($chat, $this->payload('history', ['created' => 1785915900]));
        $this->assertTrue($history->is_read);
        $archive->storeMessage($chat, $this->payload('same-second-arrival'));
        $this->assertUnread($chat, 1);
    }

    public function test_message_events_observe_committed_message_and_chat_unread_count_together(): void
    {
        $chat = $this->chat();
        config(['realtime.enabled' => true, 'realtime.queue_connection' => 'database']);
        $observed = [];
        Event::listen(AvitoDataChanged::class, function () use ($chat, &$observed): void {
            $current = $chat->fresh();
            $observed[] = [$current->messages()->count(), $current->is_unread, $current->unread_count];
        });

        app(AvitoMessengerArchive::class)->storeMessage($chat, $this->payload('atomic-message'));

        $this->assertNotEmpty($observed);
        foreach ($observed as $state) {
            $this->assertSame([1, true, 1], $state);
        }
    }

    public function test_an_older_chat_snapshot_cannot_replace_newer_webhook_preview_or_badge(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $archive->storeMessage($chat, $this->payload('latest', ['created' => 1785916900]));
        $archive->storeChat($chat->account, [
            'id' => $chat->external_chat_id,
            'last_message' => $this->payload('older-read', ['is_read' => true]),
        ]);

        $this->assertSame('latest', $chat->fresh()->last_message_id);
        $this->assertUnread($chat, 1);
    }

    public function test_read_response_preserves_message_arriving_during_remote_read_request(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $shown = $archive->storeMessage($chat, $this->payload('shown'));
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'unread-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-unread/read' => function () use ($archive, $chat) {
                // Avito timestamps have second precision; a same-second arrival
                // must not disappear behind the message the operator has read.
                $archive->storeMessage($chat, $this->payload('arrived-during-read'));

                return Http::response(['ok' => true]);
            },
        ]);

        $this->postJson("/api/avito/messenger/chats/{$chat->id}/read")
            ->assertOk()->assertJsonPath('read_through_id', $shown->id)
            ->assertJsonPath('chat.unread_count', 1)->assertJsonPath('chat.is_unread', true);
        $this->assertTrue($shown->fresh()->is_read);
        $this->assertFalse($chat->messages()->where('external_message_id', 'arrived-during-read')->sole()->is_read);
    }

    public function test_browser_read_boundary_excludes_messages_archived_since_render_and_rejects_other_chats(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $shown = $archive->storeMessage($chat, $this->payload('shown'));
        $unseen = $archive->storeMessage($chat, $this->payload('unseen', ['created' => 1785916801]));
        $otherChat = AvitoChat::create(['avito_messenger_account_id' => $chat->account->id, 'external_chat_id' => 'other']);
        $other = $archive->storeMessage($otherChat, $this->payload('other'));
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/read", ['through_message_id' => $other->id])->assertUnprocessable();
        Http::assertNothingSent();
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'unread-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-unread/read' => Http::response(['ok' => true]),
        ]);

        $this->postJson("/api/avito/messenger/chats/{$chat->id}/read", ['through_message_id' => $shown->id])
            ->assertOk()->assertJsonPath('read_through_id', $shown->id)->assertJsonPath('chat.unread_count', 1);
        $this->assertTrue($shown->fresh()->is_read);
        $this->assertFalse($unseen->fresh()->is_read);
    }

    public function test_repair_uses_read_evidence_excludes_system_messages_and_preserves_unknown_incoming(): void
    {
        $chat = $this->chat();
        foreach ([
            ['external_message_id' => 'old', 'remote_created_at' => now()->subHour()],
            ['external_message_id' => 'receipt', 'remote_created_at' => now()->subMinute(), 'remote_read_at' => now()],
            ['external_message_id' => 'unknown', 'remote_created_at' => now()],
            ['external_message_id' => 'system', 'remote_type' => 'system'],
            ['external_message_id' => 'deleted', 'remote_type' => 'deleted'],
            ['external_message_id' => 'out', 'direction' => 'out'],
        ] as $attributes) {
            AvitoMessage::create($attributes + ['avito_chat_id' => $chat->id, 'type' => 'text', 'remote_type' => 'text', 'direction' => 'in', 'is_read' => false]);
        }
        $chat->update(['is_unread' => true, 'unread_count' => 105]);
        $migration = require database_path('migrations/2026_09_21_100000_repair_avito_unread_counts.php');
        $migration->up();
        $migration->up();
        $this->assertUnread($chat, 1);
        $this->assertTrue($chat->messages()->where('external_message_id', 'old')->sole()->is_read);
        $this->assertTrue($chat->messages()->where('external_message_id', 'receipt')->sole()->is_read);
        $this->assertFalse($chat->messages()->where('external_message_id', 'unknown')->sole()->is_read);
        Http::assertNothingSent();
    }

    public function test_repair_recovers_read_evidence_retained_only_in_chat_payload(): void
    {
        $chat = $this->chat();
        $chat->update(['is_unread' => true, 'unread_count' => 1, 'payload' => [
            'last_message' => $this->payload('read-in-chat', ['is_read' => true]),
        ]]);
        AvitoMessage::create([
            'avito_chat_id' => $chat->id, 'external_message_id' => 'read-in-chat',
            'direction' => 'in', 'is_read' => false, 'type' => 'text', 'remote_type' => 'text',
        ]);

        (require database_path('migrations/2026_09_21_100000_repair_avito_unread_counts.php'))->up();

        $this->assertUnread($chat, 0);
        $this->assertTrue($chat->messages()->sole()->is_read);
    }

    public function test_failed_remote_read_does_not_acknowledge_local_messages(): void
    {
        $chat = $this->chat();
        $message = app(AvitoMessengerArchive::class)->storeMessage($chat, $this->payload('still-unread'));
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'unread-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-unread/read' => Http::response(['message' => 'Avito unavailable'], 400),
        ]);

        $this->postJson("/api/avito/messenger/chats/{$chat->id}/read")->assertStatus(502);

        $this->assertFalse($message->fresh()->is_read);
        $this->assertUnread($chat, 1);
    }

    public function test_unread_filter_and_overview_distinguish_unread_chats_and_messages(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $archive->storeMessage($chat, $this->payload('first'));
        $archive->storeMessage($chat, $this->payload('second'));
        AvitoChat::create(['avito_messenger_account_id' => $chat->account->id, 'external_chat_id' => 'read-chat']);

        $this->getJson('/api/avito/messenger/overview')->assertOk()
            ->assertJsonPath('counts.unread_chats', 1)->assertJsonPath('counts.unread_messages', 2);
        $this->getJson('/api/avito/messenger/chats?unread_only=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $chat->id)->assertJsonPath('data.0.unread_count', 2);
        $archive->markChatRead($chat);
        $this->getJson('/api/avito/messenger/chats?unread_only=1')->assertOk()->assertJsonCount(0, 'data');
    }

    private function chat(): AvitoChat
    {
        $account = AvitoMessengerAccount::create(['source_key' => 'client_credentials', 'external_user_id' => '777']);

        return AvitoChat::create(['avito_messenger_account_id' => $account->id, 'external_chat_id' => 'chat-unread']);
    }

    private function payload(string $id, array $attributes = []): array
    {
        return $attributes + ['id' => $id, 'author_id' => 999, 'direction' => 'in', 'type' => 'text', 'created' => 1785916800, 'content' => ['text' => 'Message']];
    }

    private function assertUnread(AvitoChat $chat, int $count): void
    {
        $this->assertSame($count, $chat->fresh()->unread_count);
        $this->assertSame($count > 0, $chat->fresh()->is_unread);
    }
}
