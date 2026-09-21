<?php

namespace Tests\Feature\Avito;

use App\Events\AvitoDataChanged;
use App\Models\AvitoChat;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AvitoMessengerArchive;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsIsolatedAvitoDatabase;
use Tests\TestCase;

class AvitoWaitingListTest extends TestCase
{
    use BuildsIsolatedAvitoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAvitoTestDatabase();
        config([
            'avito.enabled' => true,
            'avito.client_id' => 'waiting-client',
            'avito.client_secret' => 'waiting-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.mutations_enabled' => true,
            'avito.mutation_confirmation' => 'AVITO',
        ]);
        Http::preventStrayRequests();
        Queue::fake();
    }

    public function test_waiting_list_has_shared_crud_using_the_existing_ameise_access_model(): void
    {
        $chat = $this->chat();
        $url = "/api/avito/messenger/chats/{$chat->id}/waiting-list";
        $this->assertGuest();
        $this->getJson('/api/avito/messenger/waiting-list')->assertOk()->assertJsonCount(0, 'data');
        $added = $this->putJson($url, ['note' => 'Уточнить срок доставки'])->assertOk()
            ->assertJsonPath('chat.id', $chat->id)
            ->assertJsonPath('chat.waiting_note', 'Уточнить срок доставки')
            ->assertJsonPath('chat.account.name', 'Основной аккаунт')
            ->assertJsonPath('chat.is_unread', false);
        $since = $added->json('chat.waiting_since');
        $this->assertNotNull($since);
        $this->getJson('/api/avito/messenger/waiting-list')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.waiting_since', $since);
        $this->patchJson($url, ['note' => 'Ответить после расчёта'])->assertOk()
            ->assertJsonPath('chat.waiting_note', 'Ответить после расчёта')
            ->assertJsonPath('chat.waiting_since', $since);
        $this->patchJson($url, ['note' => null])->assertOk()
            ->assertJsonPath('chat.waiting_note', null)->assertJsonPath('chat.waiting_since', $since);
        $this->deleteJson($url)->assertOk()->assertJsonPath('chat.waiting_since', null)->assertJsonPath('chat.waiting_note', null);
        $this->deleteJson($url)->assertOk();
        $this->getJson('/api/avito/messenger/waiting-list')->assertOk()->assertJsonPath('total', 0);
        $this->assertDatabaseCount('avito_chats', 1);
        Http::assertNothingSent();
    }

    public function test_repeated_add_preserves_queue_position_and_omitted_note(): void
    {
        $chat = $this->chat();
        $url = "/api/avito/messenger/chats/{$chat->id}/waiting-list";
        $since = $this->putJson($url, ['note' => 'Не потерять ответ'])->assertOk()->json('chat.waiting_since');
        $this->travel(2)->hours();
        $this->putJson($url)->assertOk()
            ->assertJsonPath('chat.waiting_since', $since)->assertJsonPath('chat.waiting_note', 'Не потерять ответ');
        $this->putJson($url, ['note' => 'Уже уточняем'])->assertOk()
            ->assertJsonPath('chat.waiting_since', $since)->assertJsonPath('chat.waiting_note', 'Уже уточняем');
        $this->deleteJson($url)->assertOk();
        $readded = $this->putJson($url)->assertOk()->assertJsonPath('chat.waiting_note', null);
        $this->assertNotSame($since, $readded->json('chat.waiting_since'));
    }

    public function test_waiting_list_is_paginated_oldest_first_and_searches_notes_without_returning_other_chats(): void
    {
        $first = $this->chat(['waiting_since' => now()->subDay(), 'waiting_note' => 'Confirm shipping']);
        $second = $this->chat(['waiting_since' => now()->subDay(), 'peer_name' => 'Buyer two']);
        $third = $this->chat(['waiting_since' => now(), 'waiting_note' => 'Newer item']);
        $this->chat(['waiting_note' => 'Confirm shipping']);

        $this->getJson('/api/avito/messenger/waiting-list?per_page=2')->assertOk()
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('total', 3)->assertJsonPath('current_page', 1)->assertJsonPath('last_page', 2)->assertJsonPath('per_page', 2);
        $this->getJson('/api/avito/messenger/waiting-list?per_page=2&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $third->id);
        $this->getJson('/api/avito/messenger/waiting-list?search=shipping')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/avito/messenger/waiting-list?search=Buyer')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $second->id);
    }

    public function test_mutations_and_list_validate_input_and_missing_membership(): void
    {
        $chat = $this->chat();
        $url = "/api/avito/messenger/chats/{$chat->id}/waiting-list";
        $this->patchJson($url, ['note' => 'Нет в списке'])->assertNotFound();
        $this->putJson($url, ['note' => str_repeat('я', 2001)])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->putJson($url, ['note' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->assertNull($chat->fresh()->waiting_since);
        $this->putJson($url, ['note' => str_repeat('я', 2000)])->assertOk();
        $this->patchJson($url)->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->patchJson($url, ['note' => str_repeat('я', 2001)])->assertUnprocessable()->assertJsonValidationErrors('note');
        foreach (['per_page=0', 'per_page=101', 'page=0', 'page=no', 'search='.str_repeat('a', 201)] as $query) {
            $this->getJson('/api/avito/messenger/waiting-list?'.$query)->assertUnprocessable();
        }
        foreach (['putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}('/api/avito/messenger/chats/999999/waiting-list', ['note' => null])->assertNotFound();
        }
    }

    public function test_waiting_metadata_is_exposed_by_chat_list_and_detail_and_can_filter_without_unread_state(): void
    {
        $chat = $this->chat(['waiting_since' => now(), 'waiting_note' => 'Проверить наличие']);
        $this->chat(['is_unread' => true, 'unread_count' => 1]);

        $this->getJson('/api/avito/messenger/chats?waiting_only=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $chat->id)
            ->assertJsonPath('data.0.waiting_note', 'Проверить наличие')->assertJsonPath('data.0.is_unread', false);
        $this->getJson("/api/avito/messenger/chats/{$chat->id}")->assertOk()
            ->assertJsonPath('chat.waiting_note', 'Проверить наличие');
        $this->getJson("/api/avito/messenger/updates?chats=1&waiting_only=1&selected=1&selected_chat_id={$chat->id}")->assertOk()
            ->assertJsonCount(1, 'chats.data')->assertJsonPath('chats.data.0.id', $chat->id)
            ->assertJsonPath('selected.chat.waiting_note', 'Проверить наличие');
        $this->getJson('/api/avito/messenger/chats?waiting_only=1&unread_only=1')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_reading_syncing_incoming_and_sending_messages_keep_the_waiting_entry(): void
    {
        $chat = $this->chat(['waiting_since' => now()->subHour(), 'waiting_note' => 'Продолжить завтра']);
        $since = $chat->waiting_since->toISOString();
        $archive = app(AvitoMessengerArchive::class);
        $incoming = $archive->storeMessage($chat, $this->payload('incoming'));
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'waiting-token', 'expires_in' => 86400]),
            "https://api.avito.ru/messenger/v1/accounts/777/chats/{$chat->external_chat_id}/read" => Http::response(['ok' => true]),
            "https://api.avito.ru/messenger/v1/accounts/777/chats/{$chat->external_chat_id}/messages" => Http::response($this->payload('sent', ['direction' => 'out', 'author_id' => 777])),
        ]);
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/read", ['through_message_id' => $incoming->id])->assertOk()
            ->assertJsonPath('chat.is_unread', false)->assertJsonPath('chat.waiting_note', 'Продолжить завтра')
            ->assertJsonPath('chat.waiting_since', $since);
        $archive->storeChat($chat->account, ['id' => $chat->external_chat_id, 'last_message' => $this->payload('incoming', ['is_read' => true])]);
        $archive->storeMessage($chat, $this->payload('next-incoming', ['created' => 1785916900]));
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/messages", ['text' => 'Уточняем информацию'])->assertCreated();

        $this->getJson('/api/avito/messenger/waiting-list')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.waiting_since', $since)
            ->assertJsonPath('data.0.waiting_note', 'Продолжить завтра')->assertJsonPath('data.0.is_unread', true);
    }

    public function test_waiting_changes_publish_existing_chat_events_without_exposing_notes_or_republishing_noops(): void
    {
        $chat = $this->chat();
        $url = "/api/avito/messenger/chats/{$chat->id}/waiting-list";
        config(['realtime.enabled' => true, 'realtime.queue_connection' => 'database']);
        Event::fake([AvitoDataChanged::class]);
        $this->putJson($url, ['note' => 'Private waiting note'])->assertOk();
        $this->putJson($url)->assertOk();
        $this->patchJson($url, ['note' => 'Updated private note'])->assertOk();
        $this->deleteJson($url)->assertOk();
        $this->deleteJson($url)->assertOk();

        Event::assertDispatchedTimes(AvitoDataChanged::class, 3);
        Event::assertDispatched(AvitoDataChanged::class, function (AvitoDataChanged $event) use ($chat): bool {
            $this->assertStringNotContainsString('note', json_encode($event->broadcastWith()));

            return $event->topics === ['avito_messages'] && $event->changes === [
                'chat_ids' => [$chat->id], 'overview' => true, 'chats' => true,
            ];
        });
    }

    private function chat(array $attributes = []): AvitoChat
    {
        $account = AvitoMessengerAccount::firstOrCreate(
            ['source_key' => 'client_credentials', 'external_user_id' => '777'],
            ['name' => 'Основной аккаунт'],
        );

        return AvitoChat::create($attributes + [
            'avito_messenger_account_id' => $account->id,
            'external_chat_id' => 'waiting-chat-'.(AvitoChat::count() + 1),
        ]);
    }

    private function payload(string $id, array $attributes = []): array
    {
        return $attributes + [
            'id' => $id, 'author_id' => 999, 'direction' => 'in', 'type' => 'text',
            'created' => 1785916800, 'content' => ['text' => 'Сообщение'],
        ];
    }
}
