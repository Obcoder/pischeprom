<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Entity;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\AuthenticatesStaff;
use Tests\TestCase;

class TelegramIntegrationRetiredTest extends TestCase
{
    use AuthenticatesStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            // A leftover server configuration must not reactivate retired endpoints.
            'services.telegram.bot_token' => '12345:legacy-test-token',
            'services.telegram.webhook_secret' => 'legacy_webhook-secret_42',
        ]);
    }

    public function test_retired_webhook_rejects_legacy_credentials_without_changing_the_archive(): void
    {
        [$chat, $message] = $this->archive();
        $chatBefore = $chat->fresh()->getRawOriginal();
        $messageBefore = $message->fresh()->getRawOriginal();
        $update = [
            'update_id' => 82,
            'message' => [
                'message_id' => 19,
                'date' => 1790470800,
                'chat' => ['id' => 123456, 'type' => 'private'],
                'text' => 'This update must not enter the archive',
            ],
        ];

        foreach ([[], ['X-Telegram-Bot-Api-Secret-Token' => 'legacy_webhook-secret_42']] as $headers) {
            $this->postJson('/api/webhook', $update, $headers)->assertNotFound();
        }
        $this->actingAsStaff()->postJson('/api/webhook', $update, [
            'X-Telegram-Bot-Api-Secret-Token' => 'legacy_webhook-secret_42',
        ])->assertNotFound();

        $this->assertDatabaseCount('chats', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame($chatBefore, $chat->fresh()->getRawOriginal());
        $this->assertSame($messageBefore, $message->fresh()->getRawOriginal());
        Http::assertNothingSent();
    }

    public function test_retired_send_endpoints_and_page_are_unavailable_to_guests_and_staff(): void
    {
        [$chat, $message] = $this->archive();

        foreach ([false, true] as $staff) {
            if ($staff) {
                $this->actingAsStaff();
            }

            foreach (['/api/telegram/send-message', '/api/telegram/send-message/'.$chat->numbers, '/api/telegram/send-message/'.$chat->numbers.'/ignored'] as $uri) {
                $this->postJson($uri, [
                    'chat_id' => $chat->numbers,
                    'text' => 'This message must not be sent or archived',
                ])->assertNotFound();
            }

            $this->getJson('/Ameise/TelegramBot')->assertNotFound();
            $this->getJson('/Ameise/TelegramBot/')->assertNotFound();
        }

        $this->assertDatabaseCount('chats', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame($message->content, $message->fresh()->content);
        Http::assertNothingSent();
    }

    public function test_existing_chat_and_message_archives_remain_private_and_readable_by_staff(): void
    {
        [$chat, $message] = $this->archive();

        foreach (['/api/chats', '/api/messages'] as $uri) {
            $this->getJson($uri)->assertUnauthorized()
                ->assertDontSee($chat->numbers)
                ->assertDontSee($message->content);
        }

        $this->actingAsStaff();
        $this->getJson('/api/chats')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $chat->id)
            ->assertJsonPath('0.numbers', $chat->numbers);
        $this->getJson('/api/messages')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $message->id)
            ->assertJsonPath('0.content', $message->content)
            ->assertJsonPath('0.chat.id', $chat->id);

        $this->assertDatabaseCount('chats', 1);
        $this->assertDatabaseCount('messages', 1);
        Http::assertNothingSent();
    }

    public function test_crm_can_still_link_filter_and_display_an_archived_chat(): void
    {
        [$chat] = $this->archive();
        $this->actingAsStaff();
        $response = $this->postJson('/api/entities', [
            'name' => 'Company with an archived conversation',
            'chats' => [$chat->id],
        ])->assertSuccessful()->assertJsonPath('data.chats.0.id', $chat->id);
        $entityId = $response->json('data.id');
        Entity::query()->create(['name' => 'Company without an archived conversation']);

        $this->getJson('/api/entities/'.$entityId)->assertOk()
            ->assertJsonPath('data.chats.0.numbers', $chat->numbers);
        $this->getJson('/api/entities?chat_ids[]='.$chat->id)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entityId);
        $this->getJson('/api/entities-meta')->assertOk()
            ->assertJsonPath('chats.0.id', $chat->id);
        $this->assertDatabaseHas('chat_entity', ['entity_id' => $entityId, 'chat_id' => $chat->id]);
        $this->assertDatabaseCount('messages', 1);
        Http::assertNothingSent();
    }

    private function archive(): array
    {
        $chat = Chat::query()->create(['numbers' => '987654']);
        $message = Message::query()->create([
            'chat_id' => $chat->id,
            'content' => 'Preserved conversation history',
            'message_id' => 18,
            'update_id' => 81,
        ]);

        return [$chat, $message];
    }
}
