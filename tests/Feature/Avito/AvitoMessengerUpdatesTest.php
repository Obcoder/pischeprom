<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsIsolatedAvitoDatabase;
use Tests\TestCase;

class AvitoMessengerUpdatesTest extends TestCase
{
    use BuildsIsolatedAvitoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAvitoTestDatabase();
        Schema::table('entities', function (Blueprint $table): void {
            foreach (['name', 'full_name', 'INN', 'KPP', 'OGRN', 'legal_address'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::table('telephones', fn (Blueprint $table) => $table->string('number')->nullable());
        Schema::create('entity_telephone', function (Blueprint $table): void {
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('telephone_id');
        });
        Http::preventStrayRequests();
    }

    public function test_updates_reuse_chat_filters_and_pagination_and_reconcile_overview_together(): void
    {
        $account = AvitoMessengerAccount::create(['source_key' => 'test-main', 'external_user_id' => '777']);
        for ($index = 1; $index <= 13; $index++) {
            $this->chat($account, 'matched-'.$index, [
                'title' => 'Matched conversation '.$index,
                'is_unread' => true,
                'unread_count' => 1,
            ]);
        }
        $this->chat($account, 'read', ['title' => 'Matched read conversation']);
        $this->chat($account, 'wrong-type', ['title' => 'Matched other type', 'chat_type' => 'u2u', 'is_unread' => true, 'unread_count' => 1]);
        $otherAccount = AvitoMessengerAccount::create(['source_key' => 'test-other', 'external_user_id' => '999']);
        $this->chat($otherAccount, 'wrong-account', ['title' => 'Matched other account', 'is_unread' => true, 'unread_count' => 1]);

        $response = $this->updates([
            'overview' => 1,
            'chats' => 1,
            'account_id' => $account->id,
            'search' => 'Matched',
            'chat_type' => 'u2i',
            'unread_only' => 1,
            'per_page' => 10,
            'page' => 2,
        ])->assertOk()
            ->assertJsonPath('overview.counts.chats', 16)
            ->assertJsonPath('overview.counts.unread_chats', 15)
            ->assertJsonPath('overview.counts.unread_messages', 15)
            ->assertJsonPath('chats.total', 13)
            ->assertJsonPath('chats.current_page', 2)
            ->assertJsonPath('chats.last_page', 2)
            ->assertJsonPath('chats.per_page', 10)
            ->assertJsonCount(3, 'chats.data')
            ->assertJsonMissingPath('overview.tools')
            ->assertJsonMissingPath('selected');

        foreach ($response->json('chats.data') as $chat) {
            $this->assertSame($account->id, $chat['account_id']);
            $this->assertTrue($chat['is_unread']);
            $this->assertSame('u2i', $chat['chat_type']);
        }
        Http::assertNothingSent();
    }

    public function test_selected_updates_only_return_requested_messages_from_that_chat_with_related_data(): void
    {
        $account = AvitoMessengerAccount::create(['source_key' => 'test-main', 'external_user_id' => '777']);
        $chat = $this->chat($account, 'selected');
        for ($index = 1; $index <= 105; $index++) {
            $this->message($chat, 'history-'.$index);
        }
        $changed = $this->message($chat, 'changed', ['text' => 'Changed text', 'is_read' => true]);
        $attachment = $changed->attachments()->create(['kind' => 'image', 'mime_type' => 'image/png']);
        $candidate = $changed->contactCandidates()->create([
            'type' => 'phone', 'raw_value' => '+7 999 111-22-33',
            'normalized_value' => '79991112233', 'fingerprint' => str_repeat('a', 64),
            'confidence' => 95, 'status' => 'pending',
        ]);
        $changed->contactCandidates()->create([
            'type' => 'phone', 'raw_value' => '+7 999 111-22-34',
            'fingerprint' => str_repeat('b', 64), 'status' => 'rejected',
        ]);
        $otherChat = $this->chat($account, 'other');
        $otherMessage = $this->message($otherChat, 'other-chat-message');
        $deleted = $this->message($chat, 'deleted');
        $deleted->delete();

        $this->updates([
            'selected' => 1,
            'selected_chat_id' => $chat->id,
            'message_ids' => [$changed->id, $otherMessage->id, $deleted->id, $changed->id],
        ])->assertOk()
            ->assertJsonMissingPath('overview')
            ->assertJsonMissingPath('chats')
            ->assertJsonPath('selected.chat.id', $chat->id)
            ->assertJsonPath('selected.chat.messages_count', 106)
            ->assertJsonCount(1, 'selected.messages')
            ->assertJsonPath('selected.messages.0.id', $changed->id)
            ->assertJsonPath('selected.messages.0.text', 'Changed text')
            ->assertJsonPath('selected.messages.0.is_read', true)
            ->assertJsonPath('selected.messages.0.attachments.0.id', $attachment->id)
            ->assertJsonCount(1, 'selected.messages.0.contact_candidates')
            ->assertJsonPath('selected.messages.0.contact_candidates.0.id', $candidate->id)
            ->assertJsonPath('selected.missing_message_ids', [$otherMessage->id, $deleted->id])
            ->assertJsonPath('selected.has_more', false);
        Http::assertNothingSent();
    }

    public function test_incremental_updates_include_new_selected_chat_messages_and_requested_older_changes(): void
    {
        $account = AvitoMessengerAccount::create(['source_key' => 'test-main', 'external_user_id' => '777']);
        $chat = $this->chat($account, 'selected');
        $olderChanged = $this->message($chat, 'older', ['is_read' => true]);
        $cutoff = $this->message($chat, 'already-loaded');
        $otherChat = $this->chat($account, 'other');
        $foreignMessage = $this->message($otherChat, 'foreign');
        $newFirst = $this->message($chat, 'new-first');
        $newSecond = $this->message($chat, 'new-second');

        $response = $this->updates([
            'selected' => 1,
            'selected_chat_id' => $chat->id,
            'after_message_id' => $cutoff->id,
            'message_ids' => [$olderChanged->id, $foreignMessage->id],
        ])->assertOk()
            ->assertJsonCount(3, 'selected.messages')
            ->assertJsonPath('selected.missing_message_ids', [$foreignMessage->id])
            ->assertJsonPath('selected.has_more', false);

        $this->assertSame([$olderChanged->id, $newFirst->id, $newSecond->id], array_column($response->json('selected.messages'), 'id'));
        Http::assertNothingSent();
    }

    public function test_incremental_updates_are_bounded_and_do_not_report_capped_requested_messages_as_missing(): void
    {
        $account = AvitoMessengerAccount::create(['source_key' => 'test-main', 'external_user_id' => '777']);
        $chat = $this->chat($account, 'selected');
        for ($index = 1; $index <= 205; $index++) {
            $last = $this->message($chat, 'message-'.$index);
        }

        $response = $this->updates([
            'selected' => 1,
            'selected_chat_id' => $chat->id,
            'after_message_id' => 0,
            'message_ids' => [$last->id],
        ])->assertOk()
            ->assertJsonCount(200, 'selected.messages')
            ->assertJsonPath('selected.missing_message_ids', [])
            ->assertJsonPath('selected.has_more', true);

        $this->assertNotContains($last->id, array_column($response->json('selected.messages'), 'id'));
        Http::assertNothingSent();
    }

    public function test_selected_chat_metadata_without_message_ids_does_not_fetch_history_or_mark_read(): void
    {
        $account = AvitoMessengerAccount::create(['source_key' => 'test-main', 'external_user_id' => '777']);
        $chat = $this->chat($account, 'selected', ['is_unread' => true, 'unread_count' => 1]);
        $message = $this->message($chat, 'unread', ['is_read' => false]);

        $this->updates(['selected' => 1, 'selected_chat_id' => $chat->id])->assertOk()
            ->assertJsonPath('selected.chat.is_unread', true)
            ->assertJsonPath('selected.chat.unread_count', 1)
            ->assertJsonPath('selected.messages', [])
            ->assertJsonPath('selected.missing_message_ids', []);
        $this->assertFalse($message->fresh()->is_read);
        $this->assertNull($message->fresh()->crm_scanned_at);
        Http::assertNothingSent();
    }

    public function test_updates_validate_selected_chat_message_bounds_and_pagination(): void
    {
        $this->updates(['selected' => 1])->assertUnprocessable()->assertJsonValidationErrors('selected_chat_id');
        $this->updates(['selected' => 1, 'selected_chat_id' => 999])->assertUnprocessable()->assertJsonValidationErrors('selected_chat_id');
        $this->updates(['message_ids' => range(1, 201)])->assertUnprocessable()->assertJsonValidationErrors('message_ids');
        $this->updates(['message_ids' => [0]])->assertUnprocessable()->assertJsonValidationErrors('message_ids.0');
        $this->updates(['message_ids' => ['private-text']])->assertUnprocessable()->assertJsonValidationErrors('message_ids.0');
        $this->updates(['after_message_id' => -1])->assertUnprocessable()->assertJsonValidationErrors('after_message_id');
        $this->updates(['chats' => 1, 'page' => 0, 'per_page' => 101])->assertUnprocessable()->assertJsonValidationErrors(['page', 'per_page']);
        $this->updates(['overview' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('overview');
        Http::assertNothingSent();
    }

    private function updates(array $parameters): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/avito/messenger/updates?'.http_build_query($parameters));
    }

    private function chat(AvitoMessengerAccount $account, string $externalId, array $attributes = []): AvitoChat
    {
        return AvitoChat::create($attributes + [
            'avito_messenger_account_id' => $account->id,
            'external_chat_id' => $externalId,
            'chat_type' => 'u2i',
        ]);
    }

    private function message(AvitoChat $chat, string $externalId, array $attributes = []): AvitoMessage
    {
        return AvitoMessage::create($attributes + [
            'avito_chat_id' => $chat->id,
            'external_message_id' => $externalId,
            'type' => 'text',
            'direction' => 'in',
            'remote_created_at' => now(),
        ]);
    }
}
