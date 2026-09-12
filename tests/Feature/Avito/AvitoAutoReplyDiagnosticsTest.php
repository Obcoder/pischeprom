<?php

namespace Tests\Feature\Avito;

use App\Jobs\Avito\ProcessAvitoAutoReplyJob;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AutoReply\AvitoAutoReplyDiagnostics;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AvitoAutoReplyDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'avito.enabled' => true,
            'avito.mutations_enabled' => true,
            'avito.client_id' => 'diagnostic-client',
            'avito.client_secret' => 'diagnostic-secret',
            'avito.webhook_secret' => 'diagnostic-webhook',
            'ai-price-lists.ai.api_key' => 'diagnostic-ai-key',
            'ai-price-lists.ai.folder_id' => 'diagnostic-folder',
            'ai-price-lists.ai.model' => 'yandexgpt-5.1',
            'queue.failed.database' => 'sqlite',
        ]);
        Http::fake();
        Queue::fake();
    }

    public function test_audit_command_is_read_only_and_does_not_recreate_missing_settings(): void
    {
        AvitoAutoReplySetting::query()->delete();
        $this->artisan('avito:auto-reply-audit', ['--json' => true])->expectsOutputToContain('"mode": "shadow"')->assertSuccessful();
        $this->assertDatabaseCount('avito_auto_reply_settings', 0);
        $this->assertDatabaseCount('avito_auto_reply_decisions', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_diagnostics_explain_disabled_configuration_and_pilot_scope(): void
    {
        $chat = $this->chat();
        AvitoAutoReplySetting::current()->update(['mode' => 'pilot']);
        AvitoAutoReplyRule::query()->update(['is_pilot' => false]);
        config(['avito.enabled' => false, 'avito.mutations_enabled' => false, 'ai-price-lists.ai.api_key' => null]);

        $report = app(AvitoAutoReplyDiagnostics::class)->report($chat);
        $codes = array_column($report['blockers'], 'code');
        foreach (['avito_disabled', 'mutations_disabled', 'classifier_not_configured', 'no_eligible_rules'] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame(0, $report['rules']['pilot_in_scope']);
        $this->assertFalse($report['ready_for_sending']);

        AvitoAutoReplyRule::query()->update(['is_pilot' => true, 'context_ids' => json_encode(['another-listing'])]);
        $this->assertSame(0, app(AvitoAutoReplyDiagnostics::class)->report($chat)['rules']['in_scope']);
    }

    public function test_missing_new_columns_are_reported_even_when_all_old_tables_exist(): void
    {
        Schema::table('avito_auto_reply_settings', fn (Blueprint $table) => $table->dropColumn('response_mode'));
        Schema::table('avito_auto_reply_decisions', fn (Blueprint $table) => $table->dropColumn(['response_text', 'matched_rule_keys']));

        $report = app(AvitoAutoReplyDiagnostics::class)->report();

        $this->assertFalse($report['ready_for_sending']);
        $this->assertCount(3, $report['blockers']);
        $this->assertSame(['schema_column_missing'], array_values(array_unique(array_column($report['blockers'], 'code'))));
        foreach (['avito_auto_reply_settings.response_mode', 'avito_auto_reply_decisions.response_text', 'avito_auto_reply_decisions.matched_rule_keys'] as $column) {
            $this->assertStringContainsString($column, implode(' ', array_column($report['blockers'], 'message')));
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_unsafe_saved_answers_are_excluded_from_eligible_count_and_readiness(): void
    {
        $chat = $this->chat();
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        AvitoAutoReplyRule::query()->update(['is_active' => false]);
        AvitoAutoReplyRule::query()->create([
            'key' => 'unsafe-legacy-rule', 'name' => 'Старый ответ о наличии',
            'response_text' => 'На складе есть 50 штук.', 'is_active' => true,
            'is_approved' => true, 'is_pilot' => true,
        ]);

        $report = app(AvitoAutoReplyDiagnostics::class)->report($chat);

        $this->assertSame(1, $report['rules']['eligible_before_safety']);
        $this->assertSame(1, $report['rules']['unsafe_responses']);
        $this->assertSame(0, $report['rules']['eligible']);
        $this->assertFalse($report['ready_for_sending']);
        $this->assertContains('no_eligible_rules', array_column($report['blockers'], 'code'));
        $this->assertContains('unsafe_rule_responses', array_column($report['warnings'], 'code'));
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_queue_metadata_ignores_unrelated_payloads_and_never_exposes_secrets(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.connection' => 'sqlite', 'queue.connections.database.retry_after' => 90]);
        $secret = 'private-token-must-not-appear';
        $payload = json_encode(['displayName' => ProcessAvitoAutoReplyJob::class, 'data' => ['command' => $secret]], JSON_THROW_ON_ERROR);
        foreach ([$payload, json_encode(['displayName' => 'OtherJob', 'data' => ['command' => 'ProcessAvitoAutoReplyJob '.$secret]]), '{invalid ProcessAvitoAutoReplyJob'] as $item) {
            DB::table('jobs')->insert(['queue' => 'default', 'payload' => $item, 'attempts' => 0, 'available_at' => now()->subMinutes(6)->timestamp, 'created_at' => now()->subMinutes(6)->timestamp]);
        }
        DB::table('failed_jobs')->insert(['uuid' => 'audit-failed', 'connection' => 'database', 'queue' => 'default', 'payload' => $payload, 'exception' => $secret, 'failed_at' => now()]);

        $report = app(AvitoAutoReplyDiagnostics::class)->report();
        $this->assertSame(1, $report['queue']['pending']['count']);
        $this->assertSame(1, $report['queue']['pending']['overdue']);
        $this->assertSame(1, $report['queue']['failed']['count']);
        $this->assertFalse($report['queue']['worker_verified']);
        $this->assertContains('queue_retry_after_too_short', array_column($report['warnings'], 'code'));
        $json = json_encode($report, JSON_THROW_ON_ERROR);
        foreach ([$secret, 'diagnostic-secret', 'diagnostic-ai-key', 'diagnostic-webhook', 'command', 'exception'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $json);
        }
        $this->assertDatabaseCount('jobs', 3);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_api_shows_shadow_answer_and_scoped_reason_counts(): void
    {
        $chat = $this->chat();
        $message = AvitoMessage::query()->create(['avito_chat_id' => $chat->id, 'external_message_id' => 'audit-incoming', 'direction' => 'in', 'type' => 'text', 'remote_type' => 'text', 'text' => 'Здравствуйте', 'remote_created_at' => now()]);
        AvitoAutoReplyDecision::query()->create([
            'avito_message_id' => $message->id,
            'avito_chat_id' => $chat->id,
            'mode' => 'shadow',
            'outcome' => 'would_send',
            'reason_code' => 'shadow_mode',
            'response_text' => 'Здравствуйте! Какой товар вас интересует?',
            'matched_rule_keys' => ['greeting'],
        ]);

        $this->getJson('/api/avito/messenger/auto-replies?chat_id='.$chat->id)
            ->assertOk()
            ->assertJsonPath('diagnostics.mode', 'shadow')
            ->assertJsonPath('diagnostics.blockers.0.code', 'shadow_mode')
            ->assertJsonPath('diagnostics.activity.decision_count', 1)
            ->assertJsonPath('diagnostics.reason_counts.0.reason_code', 'shadow_mode')
            ->assertJsonPath('decisions.data.0.response_text', 'Здравствуйте! Какой товар вас интересует?')
            ->assertJsonPath('decisions.data.0.matched_rule_keys.0', 'greeting');
        Http::assertNothingSent();
    }

    public function test_rule_responses_cannot_authorize_restricted_facts_and_response_modes_are_validated(): void
    {
        $payload = ['name' => 'Проверка', 'response_text' => 'На складе есть 50 штук.', 'positive_examples' => ['Есть в наличии?']];
        $this->postJson('/api/avito/messenger/auto-replies/rules', $payload)->assertUnprocessable()->assertJsonValidationErrors('response_text');
        $payload['response_text'] = 'Здравствуйте! Какой товар вас интересует?';
        $created = $this->postJson('/api/avito/messenger/auto-replies/rules', $payload)->assertCreated()->assertJsonPath('rule.confidence_threshold', 0.9);
        $this->patchJson('/api/avito/messenger/auto-replies/rules/'.$created->json('rule.id'), ['response_text' => 'Закупочная цена 100 рублей.'])->assertUnprocessable()->assertJsonValidationErrors('response_text');
        $this->patchJson('/api/avito/messenger/auto-replies/settings', ['response_mode' => 'templates'])->assertOk()->assertJsonPath('settings.response_mode', 'templates');
        $this->patchJson('/api/avito/messenger/auto-replies/settings', ['response_mode' => 'anything'])->assertUnprocessable()->assertJsonValidationErrors('response_mode');
    }

    private function chat(): AvitoChat
    {
        $account = AvitoMessengerAccount::query()->create(['source_key' => 'audit-client', 'external_user_id' => '777', 'name' => 'Магазин', 'sync_enabled' => true]);

        return AvitoChat::query()->create(['avito_messenger_account_id' => $account->id, 'external_chat_id' => 'audit-chat', 'chat_type' => 'u2i', 'context_type' => 'item', 'context_id' => '123']);
    }
}
