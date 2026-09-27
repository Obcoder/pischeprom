<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TelegramWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.telegram.bot_token' => '12345:test-config-token',
            'services.telegram.webhook_secret' => 'test_webhook-secret_42',
        ]);
    }

    public function test_missing_or_invalid_configuration_rejects_before_writing_or_logging_the_update(): void
    {
        Log::spy();
        foreach ([null, '', 'invalid secret', str_repeat('a', 257)] as $secret) {
            config(['services.telegram.webhook_secret' => $secret]);
            $this->postJson('/api/webhook', $this->update(), [
                'X-Telegram-Bot-Api-Secret-Token' => 'test_webhook-secret_42',
            ])->assertStatus(503);
        }
        config(['services.telegram.webhook_secret' => 'test_webhook-secret_42', 'services.telegram.bot_token' => '']);
        $this->postJson('/api/webhook', $this->update(), [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_webhook-secret_42',
        ])->assertStatus(503);
        $this->assertDatabaseCount('chats', 0);
        $this->assertDatabaseCount('messages', 0);
        Log::shouldNotHaveReceived('info');
        Http::assertNothingSent();
    }

    public function test_missing_or_forged_secret_cannot_create_chats_or_messages(): void
    {
        Log::spy();
        foreach ([[], ['X-Telegram-Bot-Api-Secret-Token' => 'forged'],
            ['X-Telegram-Bot-Api-Secret-Token' => 'test_webhook-secret_43']] as $headers) {
            $this->postJson('/api/webhook', $this->update(), $headers)->assertForbidden();
        }
        $this->assertDatabaseCount('chats', 0);
        $this->assertDatabaseCount('messages', 0);
        Log::shouldNotHaveReceived('info');
        Http::assertNothingSent();
    }

    public function test_authenticated_provider_update_preserves_incoming_message_without_staff_session(): void
    {
        Log::spy();
        $this->postJson('/api/webhook', $this->update(), [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_webhook-secret_42',
        ])->assertOk()->assertJsonPath('ok', true);

        $chat = Chat::sole();
        $message = Message::sole();
        $this->assertSame('987654', $chat->numbers);
        $this->assertSame($chat->id, $message->chat_id);
        $this->assertSame('Provider message', $message->content);
        $this->assertSame(18, $message->message_id);
        $this->assertSame(81, $message->update_id);
        Log::shouldNotHaveReceived('info');
        Http::assertNothingSent();

        $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => $route->uri() === 'api/webhook');
        $this->assertContains('throttle:120,1,telegram-webhook:', $route->gatherMiddleware());
    }

    public function test_authenticated_non_message_update_is_acknowledged_without_writes(): void
    {
        $this->postJson('/api/webhook', ['update_id' => 82, 'my_chat_member' => []], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_webhook-secret_42',
        ])->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseCount('chats', 0);
        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }

    public function test_photo_update_keeps_archive_entry_without_logging_token_bearing_url(): void
    {
        Log::spy();
        $update = $this->update();
        unset($update['message']['text']);
        $update['message']['photo'] = [['file_id' => 'photo-file-1']];
        $this->postJson('/api/webhook', $update, [
            'X-Telegram-Bot-Api-Secret-Token' => 'test_webhook-secret_42',
        ])->assertOk();
        $this->assertSame('[Фото] photo-file-1', Message::sole()->content);
        Log::shouldNotHaveReceived('info');
        Http::assertNothingSent();
    }

    public function test_file_lookup_uses_cached_configuration_and_sanitizes_provider_failure(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['result' => ['file_path' => 'photos/test.jpg']])]);
        $service = app(TelegramService::class);
        $this->assertSame('https://api.telegram.org/file/bot12345:test-config-token/photos/test.jpg', $service->getFileUrl('photo-1'));
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.telegram.org/bot12345:test-config-token/getFile'));

        Http::fake(fn () => throw new \RuntimeException('Failed https://api.telegram.org/bot12345:test-config-token/getFile'));
        try {
            $service->getFileUrl('photo-2');
            $this->fail('Expected a sanitized provider failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Telegram file lookup failed.', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    private function update(): array
    {
        return [
            'update_id' => 81,
            'message' => [
                'message_id' => 18, 'date' => 1790470800,
                'chat' => ['id' => 987654, 'type' => 'private'],
                'text' => 'Provider message',
            ],
        ];
    }
}
