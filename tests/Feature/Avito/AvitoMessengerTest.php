<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvitoMessengerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'messenger-client',
            'avito.client_secret' => 'messenger-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru', 'pro.autoteka.ru'],
            'avito.mutations_enabled' => true,
            'avito.mutation_confirmation' => 'AVITO',
            'avito.webhook_secret' => 'messenger-webhook-secret',
            'avito.messenger.archive_disk' => 'avito',
            'avito.messenger.chat_page_size' => 100,
            'avito.messenger.message_page_size' => 100,
            'avito.messenger.incremental_chat_limit' => 100,
            'avito.messenger.full_chat_limit' => 1100,
            'avito.messenger.message_limit_per_chat' => 1100,
            'avito.messenger.max_attachment_bytes' => 25 * 1024 * 1024,
            'ai-price-lists.ai.api_key' => null,
            'ai-price-lists.ai.folder_id' => null,
        ]);
    }

    public function test_messages_tab_and_public_archive_endpoints_use_the_ameise_access_model(): void
    {
        $this->artisan('avito:preflight --schema')->assertExitCode(0);
        $this->get('/Ameise/avito')->assertOk();
        $this->getJson('/api/avito/messenger/overview')
            ->assertOk()
            ->assertJsonPath('counts.chats', 0)
            ->assertJsonCount(13, 'tools');

        $page = (string) file_get_contents(resource_path('js/Pages/Ameise/Avito.vue'));
        $component = (string) file_get_contents(resource_path('js/Components/Avito/AvitoMessages.vue'));
        $crmComponent = (string) file_get_contents(resource_path('js/Components/Avito/AvitoCrmPanel.vue'));
        $templateComponent = (string) file_get_contents(resource_path('js/Components/Avito/AvitoMessageTemplates.vue'));
        $autoReplyComponent = (string) file_get_contents(resource_path('js/Components/Avito/AvitoAutoReplies.vue'));
        $this->assertStringContainsString('value="messages"', $page);
        $this->assertStringContainsString('<AvitoMessages', $page);
        $this->assertStringContainsString('value="templates"', $page);
        $this->assertStringContainsString('<AvitoMessageTemplates', $page);
        $this->assertStringContainsString('value="auto-replies"', $page);
        $this->assertStringContainsString('<AvitoAutoReplies', $page);
        $this->assertStringContainsString('/api/avito/messenger/chats', $component);
        $this->assertStringContainsString('<AvitoCrmPanel', $component);
        $this->assertStringContainsString('Клиент · заказ · товары', $crmComponent);
        $this->assertStringContainsString('/crm/telephones', $crmComponent);
        $this->assertStringContainsString('/crm/buildings', $crmComponent);
        $this->assertStringContainsString('/crm/orders', $crmComponent);
        $this->assertStringContainsString('/crm/goods/', $crmComponent);
        $this->assertStringContainsString('class="order-line__media"', $crmComponent);
        $this->assertStringContainsString('mdi-image-off-outline', $crmComponent);
        $this->assertStringContainsString('grid-template-columns: 38px minmax(0, 1fr) 24px', $crmComponent);
        $this->assertStringContainsString('flex: 0 0 38px', $crmComponent);
        $this->assertStringContainsString('.crm-content { min-height: 0;', $crmComponent);
        $this->assertStringContainsString('value="templates"', $crmComponent);
        $this->assertStringContainsString('<AvitoMessageTemplates', $crmComponent);
        $this->assertStringContainsString('value="auto-replies"', $crmComponent);
        $this->assertStringContainsString('<AvitoAutoReplies', $crmComponent);
        $this->assertStringContainsString('/api/avito/messenger/templates', $templateComponent);
        $this->assertStringContainsString('/message-templates/', $templateComponent);
        $this->assertStringContainsString('openMessageTemplates', $component);
        $this->assertStringContainsString('openAutoReplies', $component);
        $this->assertStringContainsString('/api/avito/messenger/auto-replies', $autoReplyComponent);
        $this->assertStringContainsString('всей сохранённой переписке', $component);
        $this->assertStringContainsString('width: 100%; max-width: none', $page);
        $this->assertStringNotContainsString('localStorage', $component);
    }

    public function test_messenger_webhook_is_immediately_archived_and_encrypted(): void
    {
        $payload = [
            'id' => 'webhook-event-1',
            'version' => 'v3.0',
            'timestamp' => 1785916800,
            'payload' => [
                'type' => 'message',
                'value' => [
                    'id' => 'message-in-1',
                    'chat_id' => 'chat-archive-1',
                    'chat_type' => 'u2i',
                    'user_id' => 777,
                    'author_id' => 999,
                    'item_id' => 12345,
                    'created' => 1785916800,
                    'type' => 'text',
                    'content' => ['text' => 'Исторический текст переписки'],
                    'read' => null,
                ],
            ],
        ];

        $this->postJson('/api/avito/webhook', $payload, ['X-Secret' => 'messenger-webhook-secret'])
            ->assertStatus(202)
            ->assertJsonPath('duplicate', false);

        $this->assertDatabaseHas('avito_messenger_accounts', ['external_user_id' => '777']);
        $this->assertDatabaseHas('avito_chats', ['external_chat_id' => 'chat-archive-1', 'context_id' => '12345']);
        $this->assertDatabaseHas('avito_messages', [
            'external_message_id' => 'message-in-1',
            'text' => 'Исторический текст переписки',
            'direction' => 'in',
        ]);
        $this->assertDatabaseHas('avito_webhook_events', ['status' => 'processed']);
        $this->assertStringNotContainsString(
            'Исторический текст переписки',
            (string) DB::table('avito_messages')->value('payload')
        );
    }

    public function test_synchronization_archives_chat_and_complete_available_history(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'messenger-token', 'expires_in' => 86400]),
            'https://api.avito.ru/core/v1/accounts/self' => Http::response(['id' => 777, 'name' => 'Магазин']),
            'https://api.avito.ru/messenger/v2/accounts/777/chats*' => Http::response([
                'chats' => [[
                    'id' => 'chat-sync-1',
                    'chat_type' => 'u2i',
                    'created' => 1785910000,
                    'updated' => 1785916800,
                    'context' => ['type' => 'item', 'value' => ['id' => 123, 'title' => 'Товар', 'url' => 'https://www.avito.ru/item/123']],
                    'users' => [
                        ['id' => 777, 'name' => 'Магазин'],
                        ['id' => 999, 'name' => 'Покупатель'],
                    ],
                    'last_message' => [
                        'id' => 'sync-message-2',
                        'author_id' => 999,
                        'direction' => 'in',
                        'type' => 'text',
                        'created' => 1785916800,
                        'content' => ['text' => 'Второе сообщение'],
                    ],
                ]],
            ]),
            'https://api.avito.ru/messenger/v3/accounts/777/chats/chat-sync-1/messages/*' => Http::response([
                'messages' => [[
                    'id' => 'sync-message-2', 'author_id' => 999, 'direction' => 'in',
                    'type' => 'text', 'created' => 1785916800, 'is_read' => false,
                    'content' => ['text' => 'Второе сообщение'],
                ],
                    [
                        'id' => 'sync-message-1', 'author_id' => 777, 'direction' => 'out',
                        'type' => 'text', 'created' => 1785910000, 'is_read' => true,
                        'content' => ['text' => 'Первое сообщение'],
                    ]],
                'meta' => ['has_more' => false],
            ]),
        ]);

        $run = app(AvitoMessengerService::class)->sync();

        $this->assertSame('success', $run->status);
        $this->assertTrue($run->full_sync);
        $this->assertSame(1, $run->chats_created);
        $this->assertSame(2, $run->messages_seen);
        $this->assertSame(1, AvitoChat::query()->count());
        $this->assertSame(2, AvitoMessage::query()->count());
        $this->assertDatabaseHas('avito_chats', [
            'external_chat_id' => 'chat-sync-1',
            'peer_name' => 'Покупатель',
            'is_unread' => true,
        ]);
        $chat = AvitoChat::query()->where('external_chat_id', 'chat-sync-1')->firstOrFail();
        $this->getJson('/api/avito/messenger/chats?search='.rawurlencode('Первое сообщение'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $chat->id);
        $this->getJson('/api/avito/messenger/chats?search='.rawurlencode('Такого текста нет'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_text_read_delete_blacklist_and_subscriptions_cover_all_remote_management_actions(): void
    {
        [$account, $chat] = $this->archiveFixture();
        $incoming = AvitoMessage::query()->create([
            'avito_chat_id' => $chat->id,
            'external_message_id' => 'incoming-1',
            'author_id' => '999',
            'direction' => 'in',
            'type' => 'text',
            'remote_type' => 'text',
            'text' => 'Непрочитанное',
            'is_read' => false,
            'content' => ['text' => 'Непрочитанное'],
        ]);
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'messenger-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crud/messages' => Http::response([
                'id' => 'outgoing-1', 'author_id' => 777, 'direction' => 'out', 'type' => 'text',
                'created' => 1785916800, 'content' => ['text' => 'Ответ клиенту'],
            ]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crud/messages/outgoing-1' => Http::response([]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crud/read' => Http::response(['ok' => true]),
            'https://api.avito.ru/messenger/v2/accounts/777/blacklist' => Http::response([], 200),
            'https://api.avito.ru/messenger/v1/subscriptions' => Http::response(
                '{"subscriptions":[{"url":"https://example.test/webhook","version":"3"}]}',
                200,
                ['Content-Type' => 'text/plain']
            ),
            'https://api.avito.ru/messenger/v3/webhook' => Http::response('{"ok":true}', 200, ['Content-Type' => 'text/plain']),
            'https://api.avito.ru/messenger/v1/webhook/unsubscribe' => Http::response('{"ok":true}', 200, ['Content-Type' => 'text/plain']),
        ]);

        $sent = $this->postJson("/api/avito/messenger/chats/{$chat->id}/messages", ['text' => 'Ответ клиенту'])
            ->assertCreated()
            ->assertJsonPath('item.text', 'Ответ клиенту');
        $messageId = $sent->json('item.id');

        $this->postJson("/api/avito/messenger/chats/{$chat->id}/read")
            ->assertOk();
        $this->postJson("/api/avito/messenger/chats/{$chat->id}/blacklist", ['reason_id' => 1])
            ->assertOk();
        $this->getJson('/api/avito/messenger/subscriptions')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.version', '3');
        $this->postJson('/api/avito/messenger/subscriptions')->assertOk();
        $this->deleteJson('/api/avito/messenger/subscriptions')->assertOk();
        $this->deleteJson("/api/avito/messenger/messages/{$messageId}")
            ->assertOk()
            ->assertJsonPath('item.remote_type', 'deleted')
            ->assertJsonPath('item.text', 'Ответ клиенту');

        $this->assertTrue($incoming->fresh()->is_read);
        $this->assertDatabaseHas('avito_messages', [
            'external_message_id' => 'outgoing-1',
            'text' => 'Ответ клиенту',
            'remote_type' => 'deleted',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.avito.ru/messenger/v2/accounts/777/blacklist'
            && $request->data()['users'][0]['context']['reason_id'] === 1);
    }

    public function test_image_is_uploaded_sent_and_copied_to_private_archive(): void
    {
        Storage::fake('avito');
        [, $chat] = $this->archiveFixture();
        $cdnUrl = 'https://img.k.avito.ru/chat/1280x960/test-image.jpg';
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'messenger-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v1/accounts/777/uploadImages' => Http::response([
                'image-upload-id' => ['1280x960' => $cdnUrl],
            ]),
            'https://api.avito.ru/messenger/v1/accounts/777/chats/chat-crud/messages/image' => Http::response([
                'id' => 'image-message-1', 'author_id' => 777, 'direction' => 'out', 'type' => 'image',
                'created' => 1785916800, 'content' => ['image' => ['sizes' => ['1280x960' => $cdnUrl]]],
            ]),
            $cdnUrl => Http::response('jpeg-binary', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $response = $this->post("/api/avito/messenger/chats/{$chat->id}/messages/image", [
            'image' => UploadedFile::fake()->image('photo.jpg', 120, 80),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('item.type', 'image')
            ->assertJsonPath('item.attachments.0.archived', true);

        $path = DB::table('avito_message_attachments')->value('storage_path');
        Storage::disk('avito')->assertExists($path);
        $this->get($response->json('item.attachments.0.url'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    private function archiveFixture(): array
    {
        $account = AvitoMessengerAccount::query()->create([
            'source_key' => 'client_credentials',
            'external_user_id' => '777',
            'name' => 'Магазин',
            'sync_enabled' => true,
        ]);
        $chat = AvitoChat::query()->create([
            'avito_messenger_account_id' => $account->id,
            'external_chat_id' => 'chat-crud',
            'chat_type' => 'u2i',
            'context_type' => 'item',
            'context_id' => '123',
            'title' => 'Товар',
            'peer_user_id' => '999',
            'peer_name' => 'Покупатель',
            'is_unread' => true,
        ]);

        return [$account, $chat];
    }
}
