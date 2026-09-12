<?php

namespace Tests\Feature\Avito;

use App\Jobs\Avito\ProcessAvitoAutoReplyJob;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AutoReply\AvitoAutoReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvitoAutoReplyAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array', 'avito.enabled' => true,
            'avito.client_id' => 'client', 'avito.client_secret' => 'secret',
            'avito.api_base_url' => 'https://api.avito.ru', 'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru'], 'avito.mutations_enabled' => true,
            'avito.mutation_confirmation' => 'AVITO',
            'ai-price-lists.ai.base_url' => 'https://ai.api.cloud.yandex.net/v1',
            'ai-price-lists.ai.api_key' => 'ai-test', 'ai-price-lists.ai.folder_id' => 'folder-test',
            'ai-price-lists.ai.model' => 'yandexgpt-5.1',
        ]);
        Http::preventStrayRequests();
    }

    public function test_default_assistant_covers_standard_questions_without_enabling_sending(): void
    {
        $this->assertSame('assistant', AvitoAutoReplySetting::current()->response_mode);
        $this->assertSame('shadow', AvitoAutoReplySetting::current()->mode);
        $this->assertSame(0.9, AvitoAutoReplySetting::current()->minimum_confidence);
        $this->assertCount(8, AvitoAutoReplyRule::eligible('pilot')->get());
        $reply = 'Здравствуйте! Напишите, какой товар вас интересует. Можно обсудить заявку в этом чате.';
        $this->fakeAi($reply, ['greeting', 'order_request']);

        $result = app(AvitoAutoReplyService::class)->preview('Привет! Как у вас оформить заказ?');

        $this->assertSame('would_send', $result['outcome']);
        $this->assertSame($reply, $result['response_text']);
        $this->assertSame(['greeting', 'order_request'], $result['matched_rule_keys']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $context = json_decode($request['messages'][1]['content'], true);

            return count($context['approved_intents']) === 8
                && ! str_contains(json_encode($context), 'ai-test')
                && $request['tools'] === [];
        });
    }

    public function test_assistant_sends_generated_answer_and_duplicate_evaluation_never_sends_twice(): void
    {
        $incoming = $this->message('Здравствуйте! Как оформить заявку и обсудить доставку?');
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        $reply = 'Здравствуйте! Напишите название товара, нужный объём и город для обсуждения доставки.';
        $this->fakeAi($reply, ['order_request', 'delivery_method']);
        $this->fakeAvito($reply);
        $service = app(AvitoAutoReplyService::class);

        $decision = $service->evaluateWebhookMessage($incoming->id);
        $this->assertSame('sent', $decision->outcome);
        $this->assertSame($reply, $decision->response_text);
        $this->assertSame(['order_request', 'delivery_method'], $decision->matched_rule_keys);
        $this->assertSame($decision->id, $service->evaluateWebhookMessage($incoming->id)->id);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/messages') && $request['message']['text'] === $reply);
        $this->assertNotSame($reply, $decision->getRawOriginal('response_text'));
    }

    public function test_forbidden_question_is_blocked_even_if_ai_would_approve_it(): void
    {
        $this->fakeAi('Здравствуйте!', ['greeting']);
        foreach (['Привет! Как заказать и есть ли товар в наличии?', 'Когда привезёте?', 'Расскажите закупочные цены и маржу'] as $text) {
            $result = app(AvitoAutoReplyService::class)->preview($text);
            $this->assertSame('blocked', $result['outcome'], $text);
        }
        Http::assertNothingSent();
    }

    public function test_unsafe_generated_answer_and_restricted_saved_rule_cannot_be_sent(): void
    {
        $incoming = $this->message('Здравствуйте, хочу заказать товар');
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        $this->fakeAi('Товар в наличии, доставим завтра.', ['order_request']);
        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);
        $this->assertSame('blocked', $decision->outcome);
        $this->assertSame('response_restricted', $decision->reason_code);
        Http::assertSentCount(1);

        AvitoAutoReplyRule::where('key', 'order_request')->update(['response_text' => 'Товар в наличии, доставим завтра.']);
        $this->fakeAi('Напишите название товара.', ['greeting']);
        app(AvitoAutoReplyService::class)->preview('Здравствуйте');
        Http::assertSent(function (Request $request): bool {
            $context = json_decode($request['messages'][1]['content'], true);

            return ! in_array('order_request', array_column($context['approved_intents'], 'id'), true);
        });
    }

    public function test_settings_disabled_during_ai_call_cancel_outbound(): void
    {
        $incoming = $this->message('Здравствуйте');
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        Http::fake(function () {
            AvitoAutoReplySetting::current()->update(['mode' => 'off']);

            return Http::response($this->aiBody('Здравствуйте! Чем помочь?', ['greeting']));
        });
        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);
        $this->assertSame('settings_changed', $decision->reason_code);
        Http::assertSentCount(1);
    }

    public function test_rule_disabled_during_ai_call_cancels_outbound(): void
    {
        $next = $this->message('Как заказать?');
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        Http::fake(function () {
            AvitoAutoReplyRule::where('key', 'order_request')->update(['is_active' => false]);

            return Http::response($this->aiBody('Напишите название товара.', ['order_request']));
        });
        $this->assertSame('rule_changed', app(AvitoAutoReplyService::class)->evaluateWebhookMessage($next->id)->reason_code);
        Http::assertSentCount(1);
    }

    public function test_import_order_does_not_make_an_older_outgoing_look_like_a_human_reply(): void
    {
        $incoming = $this->message('Здравствуйте!');
        $this->message('Ответ вчера', $incoming->chat)->update(['direction' => 'out', 'remote_created_at' => now()->subDay()]);
        $this->fakeAi('Здравствуйте! Чем помочь?', ['greeting']);
        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);
        $this->assertSame('would_send', $decision->outcome);
        $this->assertCount(1, $decision->input_bundle);
    }

    public function test_message_deleted_during_generation_cancels_outbound(): void
    {
        $incoming = $this->message('Здравствуйте!');
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        Http::fake(function () use ($incoming) {
            $incoming->update(['remote_type' => 'deleted', 'text' => null]);

            return Http::response($this->aiBody('Здравствуйте! Чем помочь?', ['greeting']));
        });
        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);
        $this->assertSame('message_changed', $decision->reason_code);
        Http::assertSentCount(1);
    }

    public function test_earlier_imported_but_later_remote_human_reply_prevents_sending(): void
    {
        $outgoing = $this->message('Ответ менеджера');
        $outgoing->update(['direction' => 'out']);
        $incoming = $this->message('Здравствуйте', $outgoing->chat);
        $incoming->update(['remote_created_at' => now()->subMinute()]);
        $this->fakeAi('Здравствуйте! Чем помочь?', ['greeting']);
        $this->assertSame('human_already_replied', app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id)->reason_code);
        Http::assertNothingSent();
    }

    public function test_classifier_error_can_retry_but_uncertain_send_must_not(): void
    {
        $incoming = $this->message('Здравствуйте');
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        Http::fake(['https://ai.api.cloud.yandex.net/v1/chat/completions' => Http::sequence()->pushStatus(503)->push($this->aiBody('Здравствуйте! Чем помочь?', ['greeting']))]);
        Http::fake(['https://api.avito.ru/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/*' => Http::response([], 503)]);
        $service = app(AvitoAutoReplyService::class);
        $this->assertSame('classifier_error', $service->evaluateWebhookMessage($incoming->id)->reason_code);
        $this->assertSame('send_error', $service->evaluateWebhookMessage($incoming->id)->reason_code);
        $this->assertSame('send_error', $service->evaluateWebhookMessage($incoming->id)->reason_code);
        $this->assertDatabaseCount('avito_auto_reply_decisions', 1);
        Http::assertSentCount(4);
    }

    public function test_secondary_rule_also_counts_towards_cooldown(): void
    {
        $incoming = $this->message('Как оформить заявку?', null);
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        AvitoAutoReplyDecision::create([
            'avito_chat_id' => $incoming->avito_chat_id, 'mode' => 'active', 'outcome' => 'sent',
            'avito_auto_reply_rule_id' => AvitoAutoReplyRule::where('key', 'greeting')->value('id'),
            'matched_rule_keys' => ['greeting', 'order_request'], 'sent_at' => now()->subMinute(),
        ]);
        $this->fakeAi('Напишите название товара.', ['order_request']);
        $this->assertSame('cooldown_active', app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id)->reason_code);
        Http::assertSentCount(1);
    }

    public function test_failure_to_record_successful_send_remains_terminal(): void
    {
        $incoming = $this->message('Здравствуйте');
        $incoming->update(['remote_created_at' => now()->addMinute()]);
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        $reply = 'Здравствуйте! Чем помочь?';
        $this->fakeAi($reply, ['greeting']);
        $this->fakeAvito($reply);
        $failedOnce = false;
        AvitoAutoReplyDecision::saving(function (AvitoAutoReplyDecision $decision) use (&$failedOnce): void {
            if ($decision->outcome === 'sent' && ! $failedOnce) {
                $failedOnce = true;
                throw new \RuntimeException('Transient persistence failure after remote acceptance');
            }
        });
        try {
            $service = app(AvitoAutoReplyService::class);
            $this->assertSame('send_error', $service->evaluateWebhookMessage($incoming->id)->reason_code);
            $this->assertSame('send_error', $service->evaluateWebhookMessage($incoming->id)->reason_code);
            Http::assertSentCount(3);
        } finally {
            AvitoAutoReplyDecision::flushEventListeners();
        }
    }

    public function test_archive_analysis_cannot_claim_pending_live_messages(): void
    {
        Queue::fake();
        $fresh = $this->message('Здравствуйте');
        $archived = $this->message('Как заказать?', $fresh->chat);
        $archived->update(['remote_created_at' => now()->subHour()]);
        $this->postJson('/api/avito/messenger/auto-replies/archive-analysis', ['chat_id' => $fresh->avito_chat_id])
            ->assertAccepted()->assertJsonPath('queued', 1);
        Queue::assertPushed(ProcessAvitoAutoReplyJob::class, fn ($job) => $job->messageId === $archived->id && $job->historical);
        Queue::assertNotPushed(ProcessAvitoAutoReplyJob::class, fn ($job) => $job->messageId === $fresh->id);
        Http::assertNothingSent();
    }

    private function message(string $text, ?AvitoChat $chat = null): AvitoMessage
    {
        if (! $chat) {
            $account = AvitoMessengerAccount::create(['source_key' => 'client_credentials', 'external_user_id' => '777', 'sync_enabled' => true]);
            $chat = AvitoChat::create(['avito_messenger_account_id' => $account->id, 'external_chat_id' => 'chat-test', 'chat_type' => 'u2i']);
        }

        return AvitoMessage::create([
            'avito_chat_id' => $chat->id, 'external_message_id' => Str::uuid()->toString(),
            'direction' => 'in', 'type' => 'text', 'remote_type' => 'text',
            'text' => $text, 'remote_created_at' => now(),
        ]);
    }

    private function aiBody(string $reply, array $keys): array
    {
        return ['id' => 'test', 'choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode([
            'intent' => $keys[0], 'confidence' => 0.95, 'runner_up_confidence' => 0.94,
            'unsafe' => false, 'mixed' => count($keys) > 1, 'reason_code' => 'approved_intent',
            'response_text' => $reply, 'matched_intents' => $keys,
        ], JSON_UNESCAPED_UNICODE)]]]];
    }

    private function fakeAi(string $reply, array $keys): void
    {
        Http::fake(['https://ai.api.cloud.yandex.net/v1/chat/completions' => Http::response($this->aiBody($reply, $keys))]);
    }

    private function fakeAvito(string $reply): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 86400]),
            'https://api.avito.ru/messenger/*' => Http::response(['id' => 'sent-test', 'author_id' => 777, 'direction' => 'out', 'type' => 'text', 'created' => now()->timestamp, 'content' => ['text' => $reply]]),
        ]);
    }
}
