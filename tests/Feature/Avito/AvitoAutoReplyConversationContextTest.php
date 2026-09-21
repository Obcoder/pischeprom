<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AutoReply\AvitoAutoReplyContextUnavailable;
use App\Services\Avito\AutoReply\AvitoAutoReplyConversationContext;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsIsolatedAvitoDatabase;
use Tests\TestCase;

class AvitoAutoReplyConversationContextTest extends TestCase
{
    use BuildsIsolatedAvitoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAvitoTestDatabase();
        Queue::fake();
        config(['avito.auto_reply.context_max_bytes' => 60000]);
    }

    public function test_entire_local_history_including_ten_year_old_and_long_messages_is_preserved(): void
    {
        $chat = $this->chat();
        $longText = str_repeat('Подробности заказа. ', 120).'Адрес: Санкт-Петербург, Примерная, 12к1.';
        $old = $this->message($chat, ['text' => $longText, 'remote_created_at' => '2016-09-21 10:00:00']);
        $intermediate = [];
        for ($index = 0; $index < 12; $index++) {
            $intermediate[] = $this->message($chat, [
                'text' => 'Архивное сообщение '.$index,
                'direction' => $index % 2 === 0 ? 'out' : 'in',
                'remote_created_at' => '2026-09-21 10:'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).':00',
            ]);
        }
        $anchor = $this->message($chat, ['text' => 'Уточните доставку.', 'remote_created_at' => '2026-09-21 11:00:00']);

        $context = $this->builder()->build($chat, $anchor, collect([$anchor]));

        $this->assertCount(13, $context);
        $this->assertSame($longText, $context[0]['text']);
        $this->assertSame($old->id, $context[0]['message_id']);
        $this->assertStringStartsWith('2016-09-21T10:00:00', $context[0]['occurred_at']);
        $this->assertSame($intermediate[11]->id, $context[12]['message_id']);
        $this->assertSame(['in', 'out', 'in'], array_column(array_slice($context, 0, 3), 'direction'));
    }

    public function test_order_uses_remote_time_then_local_time_and_id_and_excludes_future_bundle_and_other_chats(): void
    {
        $chat = $this->chat();
        $late = $this->message($chat, ['text' => 'Позже', 'remote_created_at' => '2026-09-21 13:00:00']);
        $old = $this->message($chat, ['text' => 'Старое', 'remote_created_at' => '2016-09-21 10:00:00']);
        $fallback = $this->message($chat, ['text' => 'Без времени Авито', 'remote_created_at' => null]);
        $fallback->forceFill(['created_at' => '2026-09-21 10:00:00'])->saveQuietly();
        $sameTimeBefore = $this->message($chat, ['text' => 'До', 'remote_created_at' => '2026-09-21 12:00:00']);
        $bundled = $this->message($chat, ['text' => 'Часть запроса', 'remote_created_at' => '2026-09-21 12:00:00']);
        $anchor = $this->message($chat, ['text' => 'Последняя часть', 'remote_created_at' => '2026-09-21 12:00:00']);
        $sameTimeAfter = $this->message($chat, ['text' => 'После', 'remote_created_at' => '2026-09-21 12:00:00']);
        $this->message($chat, ['direction' => 'system', 'text' => 'Системное', 'remote_created_at' => '2015-01-01 10:00:00']);
        $this->message($this->chat(), ['text' => 'Чужой чат', 'remote_created_at' => '2014-01-01 10:00:00']);

        $context = $this->builder()->build($chat, $anchor, collect([$bundled, $anchor]));

        $this->assertSame([$old->id, $fallback->id, $sameTimeBefore->id], array_column($context, 'message_id'));
        $unbundled = $this->builder()->build($chat, $anchor);
        $this->assertSame([$old->id, $fallback->id, $sameTimeBefore->id, $bundled->id, $anchor->id], array_column($unbundled, 'message_id'));
        $this->assertSame([$old->id, $fallback->id, $sameTimeBefore->id, $bundled->id, $anchor->id, $sameTimeAfter->id, $late->id], array_column($this->builder()->build($chat), 'message_id'));
    }

    public function test_non_text_captions_and_deleted_archived_text_are_kept_without_claiming_media_was_seen(): void
    {
        $chat = $this->chat();
        $this->message($chat, [
            'type' => 'image', 'remote_type' => 'image', 'text' => 'Вот нужный товар, 10 кг.',
            'content' => ['text' => 'private-content-marker', 'images' => ['https://private-media-marker']],
            'payload' => ['secret' => 'private-payload-marker'],
            'quote' => ['content' => ['text' => 'Доставка по ранее указанному адресу.'], 'token' => 'private-quote-token'],
        ]);
        $this->message($chat, [
            'type' => 'text', 'remote_type' => 'deleted', 'text' => 'Архивное согласование заказа.',
            'deleted_from_avito_at' => now(),
        ]);
        $this->message($chat, ['type' => 'voice', 'remote_type' => 'voice', 'text' => null]);

        $context = $this->builder()->build($chat);

        $this->assertCount(3, $context);
        $this->assertStringContainsString('Вот нужный товар, 10 кг.', $context[0]['text']);
        $this->assertStringContainsString('AI не просматривал', $context[0]['text']);
        $this->assertStringContainsString('[Цитата в сообщении]: Доставка по ранее указанному адресу.', $context[0]['text']);
        $this->assertSame('Архивное согласование заказа.', $context[1]['text']);
        $this->assertTrue($context[1]['deleted']);
        $this->assertStringContainsString('AI не прослушивал', $context[2]['text']);
        $this->assertStringNotContainsString('private-', json_encode($context));
        $this->assertSame(['direction', 'text', 'message_id', 'occurred_at', 'type', 'deleted'], array_keys($context[0]));
    }

    public function test_oversized_full_history_is_rejected_instead_of_returning_a_recent_fragment(): void
    {
        config(['avito.auto_reply.context_max_bytes' => 1500]);
        $chat = $this->chat();
        $this->message($chat, ['text' => str_repeat('я', 800), 'remote_created_at' => '2016-09-21 10:00:00']);
        $anchor = $this->message($chat, ['text' => 'Здравствуйте', 'remote_created_at' => '2026-09-21 12:00:00']);

        try {
            $this->builder()->build($chat, $anchor, collect([$anchor]));
            $this->fail('A partial context must never be returned.');
        } catch (AvitoAutoReplyContextUnavailable $exception) {
            $this->assertSame('conversation_context_too_large', $exception->reasonCode);
        }
    }

    public function test_current_request_also_counts_toward_the_history_budget(): void
    {
        config(['avito.auto_reply.context_max_bytes' => 700]);
        $chat = $this->chat();
        $this->message($chat, ['text' => str_repeat('a', 200), 'remote_created_at' => '2016-09-21 10:00:00']);
        $anchor = $this->message($chat, ['text' => str_repeat('b', 350), 'remote_created_at' => '2026-09-21 12:00:00']);

        $this->expectException(AvitoAutoReplyContextUnavailable::class);
        $this->builder()->build($chat, $anchor, collect([$anchor]));
    }

    public function test_fingerprint_detects_old_content_changes_but_ignores_read_receipts_and_syncs(): void
    {
        $chat = $this->chat();
        $message = $this->message($chat, ['text' => 'Адрес доставки указан десять лет назад.', 'remote_created_at' => '2016-09-21 10:00:00']);
        $original = $this->builder()->fingerprint($this->builder()->build($chat));
        $message->update(['is_read' => true, 'remote_read_at' => now(), 'last_synced_at' => now()]);
        $this->assertSame($original, $this->builder()->fingerprint($this->builder()->build($chat)));

        $message->update(['text' => 'Адрес доставки изменился.']);
        $changed = $this->builder()->fingerprint($this->builder()->build($chat));
        $this->assertNotSame($original, $changed);
        $message->update(['remote_type' => 'deleted', 'deleted_from_avito_at' => now()]);
        $this->assertNotSame($changed, $this->builder()->fingerprint($this->builder()->build($chat)));
    }

    public function test_missing_chat_has_empty_history(): void
    {
        $this->assertSame([], $this->builder()->build(null));
    }

    private function builder(): AvitoAutoReplyConversationContext
    {
        return app(AvitoAutoReplyConversationContext::class);
    }

    private function chat(): AvitoChat
    {
        $account = AvitoMessengerAccount::create(['source_key' => Str::uuid()->toString()]);

        return AvitoChat::create([
            'avito_messenger_account_id' => $account->id,
            'external_chat_id' => Str::uuid()->toString(),
        ]);
    }

    private function message(AvitoChat $chat, array $attributes = []): AvitoMessage
    {
        return AvitoMessage::create(array_replace([
            'avito_chat_id' => $chat->id,
            'external_message_id' => Str::uuid()->toString(),
            'direction' => 'in', 'type' => 'text', 'remote_type' => 'text',
            'text' => 'Сообщение клиента', 'remote_created_at' => '2026-09-21 12:00:00',
        ], $attributes));
    }
}
