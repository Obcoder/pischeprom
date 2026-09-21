<?php

namespace Tests\Feature\Avito;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoChat;
use App\Models\AvitoConnection;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AvitoMessengerArchive;
use App\Services\Avito\AvitoMessengerMediaArchive;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsIsolatedAvitoDatabase;
use Tests\TestCase;

class AvitoArchiveDurabilityTest extends TestCase
{
    use BuildsIsolatedAvitoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAvitoTestDatabase();
        config([
            'avito.enabled' => true,
            'avito.client_id' => 'archive-client',
            'avito.client_secret' => 'archive-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.messenger.archive_disk' => 'avito',
            'avito.messenger.message_page_size' => 2,
            'avito.messenger.message_limit_per_chat' => 1100,
        ]);
        Http::preventStrayRequests();
        Queue::fake();
    }

    public function test_partial_receipts_and_chat_summaries_cannot_erase_archived_message_content_or_sender(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $message = $archive->storeMessage($chat, $this->payload('outgoing', [
            'author_id' => 777,
            'direction' => 'out',
            'quote' => ['id' => 'original-question', 'content' => ['text' => 'Какой город?']],
        ]));

        $archive->storeChat($chat->account, [
            'id' => $chat->external_chat_id,
            'last_message' => ['id' => 'outgoing', 'is_read' => true, 'content' => [], 'quote' => null],
        ]);
        $archive->storeMessage($chat, [
            'id' => 'outgoing', 'type' => null, 'content' => ['text' => null],
            'quote' => ['id' => 'original-question'],
        ]);
        $message->refresh();

        $this->assertSame('Доставка в Казань, телефон +7 900 000-00-00', $message->text);
        $this->assertSame($message->text, $message->content['text']);
        $this->assertSame($message->text, $message->payload['content']['text']);
        $this->assertSame('text', $message->type);
        $this->assertSame('777', $message->author_id);
        $this->assertSame('out', $message->direction);
        $this->assertSame('original-question', $message->quote['id']);
        $this->assertSame('Какой город?', $message->quote['content']['text']);
        $this->assertTrue($message->is_read);
    }

    public function test_partial_chat_payload_preserves_original_listing_and_peer_information(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $chat = $archive->storeChat($chat->account, [
            'id' => $chat->external_chat_id,
            'chat_type' => 'u2i',
            'context' => ['type' => 'item', 'value' => [
                'id' => 123, 'title' => 'Архивный товар', 'url' => 'https://www.avito.ru/item/123', 'price' => 4500,
            ]],
            'users' => [['id' => 999, 'name' => 'Покупатель']],
        ]);
        $archive->storeChat($chat->account, [
            'id' => $chat->external_chat_id,
            'chat_type' => null,
            'context' => ['type' => null, 'value' => ['id' => null, 'url' => null]],
            'users' => [],
        ]);
        $chat->refresh();

        $this->assertSame('u2i', $chat->chat_type);
        $this->assertSame('item', $chat->context_type);
        $this->assertSame('123', $chat->context_id);
        $this->assertSame('Архивный товар', $chat->title);
        $this->assertSame('https://www.avito.ru/item/123', $chat->context_url);
        $this->assertSame('Покупатель', $chat->peer_name);
        $this->assertSame(4500, $chat->payload['context']['value']['price']);
        $this->assertSame('Покупатель', $chat->payload['users'][0]['name']);
    }

    public function test_invalid_partial_field_types_cannot_erase_known_text_direction_or_structured_metadata(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $message = $archive->storeMessage($chat, $this->payload('invalid-receipt', [
            'direction' => 'out', 'author_id' => 777,
            'quote' => ['id' => 'question', 'content' => ['text' => 'Какой город?']],
            'metadata' => ['enabled' => true, 'weight' => 5],
        ]));

        $archive->storeMessage($chat, [
            'id' => 'invalid-receipt', 'direction' => 'unknown', 'author_id' => false, 'type' => false,
            'content' => ['text' => false], 'quote' => ['content' => false],
            'metadata' => ['enabled' => false, 'weight' => 5.5],
        ]);
        $message->refresh();

        $this->assertSame('Доставка в Казань, телефон +7 900 000-00-00', $message->text);
        $this->assertSame($message->text, $message->content['text']);
        $this->assertSame($message->text, $message->payload['content']['text']);
        $this->assertSame('out', $message->direction);
        $this->assertSame('out', $message->payload['direction']);
        $this->assertSame('777', $message->author_id);
        $this->assertSame('text', $message->type);
        $this->assertSame('Какой город?', $message->quote['content']['text']);
        $this->assertFalse($message->payload['metadata']['enabled']);
        $this->assertSame(5.5, $message->payload['metadata']['weight']);
    }

    public function test_non_string_text_and_empty_image_sizes_do_not_destroy_an_unarchived_attachment_reference(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $url = 'https://img.k.avito.ru/preserved.jpg';
        $message = $archive->storeMessage($chat, $this->payload('partial-image', [
            'type' => 'image',
            'content' => ['text' => 'Подписанное изображение', 'image' => ['sizes' => ['100x100' => $url]]],
        ]));

        $archive->storeMessage($chat, [
            'id' => 'partial-image',
            'content' => ['text' => ['invalid'], 'image' => ['sizes' => ['100x100' => null]]],
        ]);
        $archive->storeMessage($chat, ['id' => 'partial-image', 'content' => ['text' => 123]]);
        $message->refresh();

        $this->assertSame('Подписанное изображение', $message->text);
        $this->assertSame('Подписанное изображение', $message->content['text']);
        $this->assertSame($url, $message->content['image']['sizes']['100x100']);
        $this->assertSame($url, $message->attachments->sole()->remote_url);
        $this->assertNull($message->attachments->sole()->archived_at);
    }

    public function test_remote_deletion_and_stale_replay_preserve_original_text_quote_and_direction(): void
    {
        $chat = $this->chat();
        $archive = app(AvitoMessengerArchive::class);
        $payload = $this->payload('deleted-outgoing', [
            'author_id' => 777, 'direction' => 'out', 'quote' => ['id' => 'old-question'],
        ]);
        $message = $archive->storeMessage($chat, $payload);
        $archive->storeMessage($chat, ['id' => 'deleted-outgoing', 'type' => 'deleted', 'content' => []]);
        $deletedAt = $message->fresh()->deleted_from_avito_at;
        $archive->storeMessage($chat, $payload);
        $message->refresh();

        $this->assertSame($payload['content']['text'], $message->text);
        $this->assertSame($payload['content']['text'], $message->payload['content']['text']);
        $this->assertSame('old-question', $message->quote['id']);
        $this->assertSame('text', $message->type);
        $this->assertSame('deleted', $message->remote_type);
        $this->assertTrue($deletedAt->equalTo($message->deleted_from_avito_at));
        $this->assertSame('777', $message->author_id);
        $this->assertSame('out', $message->direction);
        $this->assertSame(0, $chat->fresh()->unread_count);
    }

    public function test_disappearing_remote_chats_and_messages_remain_readable_from_local_database(): void
    {
        $chat = $this->chat();
        $message = app(AvitoMessengerArchive::class)->storeMessage($chat, $this->payload('ten-years-old'));
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v2/accounts/777/chats*' => Http::response(['chats' => []]),
        ]);

        app(AvitoMessengerService::class)->sync(full: true);

        $this->assertSame(1, AvitoChat::query()->count());
        $this->assertSame(1, AvitoMessage::query()->count());
        Http::fake();
        $this->getJson("/api/avito/messenger/chats/{$chat->id}")
            ->assertOk()
            ->assertJsonPath('messages.data.0.text', $message->text);
        Http::assertNothingSent();
    }

    public function test_oauth_disconnect_and_remote_tombstone_preserve_private_attachment_copy(): void
    {
        Storage::fake('avito');
        $chat = $this->chat();
        $connection = AvitoConnection::query()->create([
            'name' => 'Old account', 'auth_mode' => 'oauth', 'external_user_id' => '777', 'is_active' => true,
        ]);
        $chat->account->update(['source_key' => 'oauth:'.$connection->id, 'avito_connection_id' => $connection->id]);
        $url = 'https://img.k.avito.ru/archived.jpg';
        Http::fake([$url => Http::response('archived-image', 200, ['Content-Type' => 'image/jpeg'])]);
        $archive = app(AvitoMessengerArchive::class);
        $message = $archive->storeMessage($chat, $this->payload('image', [
            'type' => 'image', 'content' => ['image' => ['sizes' => ['100x100' => $url]]],
        ]));
        app(AvitoMessengerMediaArchive::class)->archiveMessage($message);

        $archive->storeMessage($chat, ['id' => 'image', 'type' => 'deleted']);
        $connection->delete();

        $attachment = $message->fresh('attachments')->attachments->sole();
        $this->assertNull($chat->account->fresh()->avito_connection_id);
        $this->assertNotNull($attachment->archived_at);
        $this->assertSame('archived-image', Storage::disk('avito')->get($attachment->storage_path));
        $this->assertSame($url, $message->fresh()->content['image']['sizes']['100x100']);
        Http::fake();
        $this->get(route('api.avito.messenger.attachments.show', $attachment))->assertOk();
        Http::assertNothingSent();
    }

    public function test_empty_remote_message_page_does_not_remove_archived_history(): void
    {
        $chat = $this->chat();
        $message = app(AvitoMessengerArchive::class)->storeMessage($chat, $this->payload('old-message'));
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v2/accounts/777/chats/durable-chat' => Http::response(['id' => $chat->external_chat_id]),
            'https://api.avito.ru/messenger/v3/accounts/777/chats/*/messages/*' => Http::response(['messages' => [], 'meta' => ['has_more' => false]]),
        ]);

        app(AvitoMessengerService::class)->refreshChat($chat);

        $this->assertSame(1, $chat->messages()->count());
        $this->assertSame($message->text, $message->fresh()->text);
    }

    public function test_reconnecting_same_oauth_user_reuses_the_single_disconnected_archive(): void
    {
        $chat = $this->chat();
        $message = app(AvitoMessengerArchive::class)->storeMessage($chat, $this->payload('old-context'));
        $oldConnection = $this->connection('777');
        $chat->account->update([
            'source_key' => 'oauth:'.$oldConnection->id,
            'avito_connection_id' => $oldConnection->id,
        ]);
        $oldConnection->delete();
        $newConnection = $this->connection('777');

        $resolved = app(AvitoMessengerArchive::class)->resolveAccount($newConnection);

        $this->assertSame($chat->avito_messenger_account_id, $resolved->id);
        $this->assertSame('oauth:'.$newConnection->id, $resolved->source_key);
        $this->assertSame($newConnection->id, $resolved->avito_connection_id);
        $this->assertSame($message->text, $resolved->chats->sole()->messages->sole()->text);
        $this->assertSame(1, AvitoMessengerAccount::query()->count());
        $this->assertSame($resolved->id, app(AvitoMessengerArchive::class)->accountForWebhook('777')->id);
        Http::assertNothingSent();
    }

    public function test_reconnect_does_not_merge_multiple_ambiguous_disconnected_archives(): void
    {
        $chat = $this->chat();
        $chat->account->update(['source_key' => 'oauth:100']);
        $other = AvitoMessengerAccount::query()->create([
            'source_key' => 'oauth:101', 'external_user_id' => '777', 'sync_enabled' => false,
        ]);
        $connection = $this->connection('777');

        $resolved = app(AvitoMessengerArchive::class)->resolveAccount($connection);

        $this->assertNotSame($chat->avito_messenger_account_id, $resolved->id);
        $this->assertNotSame($other->id, $resolved->id);
        $this->assertSame('oauth:100', $chat->account->fresh()->source_key);
        $this->assertNull($other->fresh()->avito_connection_id);
        $this->assertSame(3, AvitoMessengerAccount::query()->count());
    }

    public function test_reconnect_keeps_active_oauth_and_client_credentials_sources_separate(): void
    {
        $clientChat = $this->chat();
        $activeConnection = $this->connection('777');
        $active = AvitoMessengerAccount::query()->create([
            'source_key' => 'oauth:'.$activeConnection->id,
            'external_user_id' => '777',
            'avito_connection_id' => $activeConnection->id,
        ]);

        $resolved = app(AvitoMessengerArchive::class)->resolveAccount($this->connection('777'));

        $this->assertNotSame($active->id, $resolved->id);
        $this->assertNotSame($clientChat->avito_messenger_account_id, $resolved->id);
        $this->assertSame($activeConnection->id, $active->fresh()->avito_connection_id);
        $this->assertSame('client_credentials', $clientChat->account->fresh()->source_key);
        $this->assertSame(3, AvitoMessengerAccount::query()->count());
    }

    public function test_reconnect_never_reuses_an_archive_for_a_different_remote_user(): void
    {
        $chat = $this->chat();
        $chat->account->update(['source_key' => 'oauth:100']);

        $resolved = app(AvitoMessengerArchive::class)->resolveAccount($this->connection('888'));

        $this->assertNotSame($chat->avito_messenger_account_id, $resolved->id);
        $this->assertSame('888', $resolved->external_user_id);
        $this->assertSame('777', $chat->account->fresh()->external_user_id);
        $this->assertNull($chat->account->fresh()->avito_connection_id);
    }

    public function test_log_retention_does_not_prune_decade_old_chats_or_messages(): void
    {
        $chat = $this->chat();
        $message = app(AvitoMessengerArchive::class)->storeMessage($chat, $this->payload('ten-years-old'));
        $chat->forceFill(['created_at' => now()->subYears(10), 'updated_at' => now()->subYears(10)])->saveQuietly();
        $message->forceFill(['created_at' => now()->subYears(10), 'updated_at' => now()->subYears(10)])->saveQuietly();

        $this->artisan('avito:maintain')->assertExitCode(0);

        $this->assertSame(1, AvitoChat::query()->count());
        $this->assertSame($message->text, $message->fresh()->text);
        Http::assertNothingSent();
    }

    public function test_failed_media_write_is_reported_and_remains_eligible_for_retry(): void
    {
        $chat = $this->chat();
        $url = 'https://img.k.avito.ru/retry.jpg';
        Http::fake([$url => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
        $message = app(AvitoMessengerArchive::class)->storeMessage($chat, $this->payload('image', [
            'type' => 'image', 'content' => ['image' => ['sizes' => ['100x100' => $url]]],
        ]));
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->twice()->andReturn(false, true);
        Storage::shouldReceive('disk')->with('avito')->twice()->andReturn($disk);
        $mediaArchive = app(AvitoMessengerMediaArchive::class);

        $this->assertSame(0, $mediaArchive->archiveMessage($message));
        $attachment = $message->fresh('attachments')->attachments->sole();
        $this->assertNull($attachment->archived_at);
        $this->assertNotNull($attachment->archive_error);
        $this->assertSame(1, $attachment->archive_attempts);

        $this->assertSame(1, $mediaArchive->archiveMessage($message->fresh()));
        $this->assertNotNull($attachment->fresh()->archived_at);
        $this->assertNull($attachment->fresh()->archive_error);
    }

    public function test_chat_created_by_webhook_gets_available_history_before_incremental_sync(): void
    {
        $chat = $this->chat();
        $chat->account->update(['last_synced_at' => now()->subMinute()]);
        app(AvitoMessengerArchive::class)->storeMessage($chat, $this->payload('newest', ['created' => now()->timestamp]));
        $this->fakeHistoryPages($chat);

        app(AvitoMessengerService::class)->sync();

        $this->assertSame(3, $chat->messages()->count());
        $this->assertNotNull($chat->fresh()->history_synced_at);
        app(AvitoMessengerService::class)->sync();
        $offsets = Http::recorded(fn ($request) => str_contains($request->url(), '/messages/'))
            ->map(function ($record): int {
                parse_str((string) parse_url($record[0]->url(), PHP_URL_QUERY), $query);

                return (int) $query['offset'];
            })->values()->all();
        $this->assertSame([0, 2, 0], $offsets);
        $this->assertSame(3, $chat->messages()->count());
    }

    public function test_failed_history_import_keeps_archived_rows_and_is_retried_next_sync(): void
    {
        $chat = $this->chat();
        $chat->account->update(['last_synced_at' => now()->subMinute()]);
        $this->fakeHistoryPages($chat, failSecondPage: true);

        try {
            app(AvitoMessengerService::class)->sync();
            $this->fail('The failed history request must remain visible.');
        } catch (AvitoException) {
            $this->assertNull($chat->fresh()->history_synced_at);
            $this->assertSame(2, $chat->messages()->count());
        }

        app(AvitoMessengerService::class)->sync();
        $this->assertNotNull($chat->fresh()->history_synced_at);
        $this->assertSame(3, $chat->messages()->count());
    }

    public function test_first_manual_refresh_also_imports_available_history_beyond_requested_recent_page(): void
    {
        $chat = $this->chat();
        $this->fakeHistoryPages($chat);

        app(AvitoMessengerService::class)->refreshChat($chat, messageLimit: 1);

        $this->assertSame(3, $chat->messages()->count());
        $this->assertNotNull($chat->fresh()->history_synced_at);
    }

    public function test_short_page_with_more_history_advances_by_actual_count_and_keeps_loading(): void
    {
        $chat = $this->chat();
        $this->fakePaginationResponses($chat, [
            ['messages' => [$this->payload('newest')], 'meta' => ['has_more' => true]],
            ['messages' => [$this->payload('older')], 'meta' => ['has_more' => false]],
        ]);

        app(AvitoMessengerService::class)->refreshChat($chat);

        $this->assertSame(2, $chat->messages()->count());
        $this->assertNotNull($chat->fresh()->history_synced_at);
        $this->assertSame([0, 1], $this->requestedMessageOffsets());
    }

    #[DataProvider('incompleteHistoryPages')]
    public function test_invalid_later_history_page_never_marks_partial_import_complete(mixed $invalidPage): void
    {
        $chat = $this->chat();
        $this->fakePaginationResponses($chat, [
            ['messages' => [$this->payload('newest'), $this->payload('older')], 'meta' => ['has_more' => true]],
            $invalidPage,
        ]);

        try {
            app(AvitoMessengerService::class)->refreshChat($chat);
            $this->fail('An incomplete history response must not complete the archive import.');
        } catch (AvitoException $exception) {
            $this->assertSame('history_incomplete', $exception->category);
            $this->assertNull($chat->fresh()->history_synced_at);
            $this->assertSame(2, $chat->messages()->count());
            $this->assertSame([0, 2], $this->requestedMessageOffsets());
        }
    }

    public static function incompleteHistoryPages(): array
    {
        return [
            'missing messages' => [['unexpected' => 'response']],
            'null messages' => [['messages' => null]],
            'associative messages' => [['messages' => ['named' => ['id' => 'message']]]],
            'non-array response' => [false],
            'non-array message' => [['messages' => ['invalid']]],
            'missing message id' => [['messages' => [['type' => 'text']]]],
            'empty message id' => [['messages' => [['id' => ' ']]]],
            'non-scalar message id' => [['messages' => [['id' => ['invalid']]]]],
            'empty page with more' => [['messages' => [], 'meta' => ['has_more' => true]]],
        ];
    }

    public function test_repeated_history_page_fails_without_looping_or_marking_complete(): void
    {
        $chat = $this->chat();
        $page = ['messages' => [$this->payload('newest'), $this->payload('older')], 'meta' => ['has_more' => true]];
        $this->fakePaginationResponses($chat, [$page, $page]);

        try {
            app(AvitoMessengerService::class)->refreshChat($chat);
            $this->fail('A repeated page cannot establish that the archive import completed.');
        } catch (AvitoException $exception) {
            $this->assertSame('history_incomplete', $exception->category);
            $this->assertNull($chat->fresh()->history_synced_at);
            $this->assertSame(2, $chat->messages()->count());
            $this->assertSame([0, 2], $this->requestedMessageOffsets());
        }
    }

    public function test_bare_full_page_without_metadata_continues_until_valid_empty_page(): void
    {
        $chat = $this->chat();
        $this->fakePaginationResponses($chat, [
            [$this->payload('newest'), $this->payload('older')],
            [],
        ]);

        app(AvitoMessengerService::class)->refreshChat($chat);

        $this->assertSame(2, $chat->messages()->count());
        $this->assertNotNull($chat->fresh()->history_synced_at);
        $this->assertSame([0, 2], $this->requestedMessageOffsets());
    }

    private function fakePaginationResponses(AvitoChat $chat, array $pages): void
    {
        $responses = Http::sequence();
        foreach ($pages as $page) {
            $responses->push(json_encode($page, JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
        }

        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v2/accounts/777/chats/durable-chat' => Http::response(['id' => $chat->external_chat_id]),
            'https://api.avito.ru/messenger/v3/accounts/777/chats/*/messages/*' => $responses,
        ]);
    }

    private function requestedMessageOffsets(): array
    {
        return Http::recorded(fn ($request) => str_contains($request->url(), '/messages/'))
            ->map(function ($record): int {
                parse_str((string) parse_url($record[0]->url(), PHP_URL_QUERY), $query);

                return (int) $query['offset'];
            })->values()->all();
    }

    private function fakeHistoryPages(AvitoChat $chat, bool $failSecondPage = false): void
    {
        $failuresRemaining = $failSecondPage ? 2 : 0;
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/v2/accounts/777/chats/durable-chat' => Http::response(['id' => $chat->external_chat_id]),
            'https://api.avito.ru/messenger/v2/accounts/777/chats*' => Http::response(['chats' => [['id' => $chat->external_chat_id]]]),
            'https://api.avito.ru/messenger/v3/accounts/777/chats/*/messages/*' => function ($request) use (&$failuresRemaining) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                if ((int) $query['offset'] === 2) {
                    if ($failuresRemaining > 0) {
                        $failuresRemaining--;

                        return Http::response(['message' => 'Unavailable'], 404);
                    }

                    return Http::response(['messages' => [$this->payload('oldest')], 'meta' => ['has_more' => false]]);
                }

                return Http::response([
                    'messages' => [$this->payload('newest', ['created' => now()->timestamp]), $this->payload('middle')],
                    'meta' => ['has_more' => true],
                ]);
            },
        ]);
    }

    private function chat(): AvitoChat
    {
        $account = AvitoMessengerAccount::query()->create([
            'source_key' => 'client_credentials', 'external_user_id' => '777', 'name' => 'Archive', 'sync_enabled' => true,
        ]);

        return AvitoChat::query()->create([
            'avito_messenger_account_id' => $account->id, 'external_chat_id' => 'durable-chat', 'peer_user_id' => '999',
        ]);
    }

    private function connection(string $externalUserId): AvitoConnection
    {
        return AvitoConnection::query()->create([
            'name' => 'OAuth account', 'auth_mode' => 'authorization_code',
            'external_user_id' => $externalUserId, 'is_active' => true,
        ]);
    }

    private function payload(string $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id, 'author_id' => 999, 'direction' => 'in', 'type' => 'text',
            'created' => now()->subYears(10)->timestamp,
            'content' => ['text' => 'Доставка в Казань, телефон +7 900 000-00-00'],
        ], $overrides);
    }
}
