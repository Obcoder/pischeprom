<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AutoReply\AvitoAutoReplyService;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvitoAutoReplyHandoffTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_REPLY = 'Мы организуем доставку. Напишите город или населённый пункт, чтобы можно было обсудить условия.';

    private string $aiReply = self::DELIVERY_REPLY;

    private array $aiIntents = ['delivery_method'];

    private ?Closure $duringAi = null;

    private ?Closure $duringToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfSecond());
        Queue::fake();
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
        AvitoAutoReplySetting::current()->update(['mode' => 'active']);
        Http::preventStrayRequests();
        Http::fake([
            'https://ai.api.cloud.yandex.net/v1/chat/completions' => function (): mixed {
                if ($this->duringAi) {
                    ($this->duringAi)();
                }

                return Http::response($this->aiBody());
            },
            'https://api.avito.ru/token' => function (): mixed {
                if ($this->duringToken) {
                    ($this->duringToken)();
                }

                return Http::response(['access_token' => 'test-token', 'expires_in' => 86400]);
            },
            'https://api.avito.ru/messenger/*' => fn (Request $request) => Http::response([
                'id' => Str::uuid()->toString(), 'author_id' => 777, 'direction' => 'out',
                'type' => 'text', 'created' => now()->timestamp,
                'content' => ['text' => $request['message']['text']],
            ]),
        ]);
    }

    public function test_delivery_question_is_answered_but_city_an_hour_later_is_left_to_a_human(): void
    {
        $question = $this->message('Авито доставка есть у вас');
        $service = app(AvitoAutoReplyService::class);

        $first = $service->evaluateWebhookMessage($question->id);

        $this->assertSame('sent', $first->outcome);
        $this->assertSame(self::DELIVERY_REPLY, $first->sentMessage->text);
        Http::assertSentCount(3);

        $this->travel(1)->hours();
        $city = $this->message('Ижевск', $question->chat);
        $this->aiReply = 'Здравствуйте! Напишите, какой товар вас интересует.';
        $this->aiIntents = ['greeting'];

        $second = $service->evaluateWebhookMessage($city->id);

        $this->assertHandoff($second);
        $this->assertSame($second->id, $service->evaluateWebhookMessage($city->id)->id);
        $this->assertNull($second->sent_avito_message_id);
        $this->assertSame(1, $question->chat->messages()->where('direction', 'out')->count());
        Http::assertSentCount(3);
    }

    public function test_an_erroneous_later_ai_greeting_does_not_reset_the_requested_details(): void
    {
        $question = $this->message('Доставка есть?', at: now()->subHours(3));
        $this->sentAi($question, self::DELIVERY_REPLY, now()->subHours(3)->addSecond());
        $city = $this->message('Ижевск', $question->chat, now()->subHours(2));
        $this->sentAi($city, 'Здравствуйте! Чем помочь?', now()->subHours(2)->addSecond(), ['greeting']);
        $next = $this->message('Понятно', $question->chat);

        $this->assertHandoff(app(AvitoAutoReplyService::class)->evaluateWebhookMessage($next->id));

        Http::assertNothingSent();
    }

    public function test_an_ordinary_human_reply_releases_handoff_for_a_new_general_question(): void
    {
        $question = $this->message('Доставка есть?', at: now()->subHours(3));
        $this->sentAi($question, self::DELIVERY_REPLY, now()->subHours(3)->addSecond());
        $city = $this->message('Ижевск', $question->chat, now()->subHours(2));
        $this->pending($city);
        $this->message('Проверю варианты и напишу здесь.', $question->chat, now()->subHour(), 'out');
        $next = $this->message('Авито доставка есть у вас', $question->chat);

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($next->id);

        $this->assertSame('sent', $decision->outcome);
        Http::assertSentCount(3);
    }

    public function test_a_human_request_for_details_keeps_the_next_reply_with_the_human(): void
    {
        $question = $this->message('Как заказать?', at: now()->subHour());
        $this->message('Напишите город или населённый пункт.', $question->chat, now()->subMinutes(30), 'out');
        $city = $this->message('Ижевск', $question->chat);

        $this->assertHandoff(app(AvitoAutoReplyService::class)->evaluateWebhookMessage($city->id));

        Http::assertNothingSent();
    }

    #[DataProvider('specificMessages')]
    public function test_concrete_customer_details_are_not_answered_even_if_ai_would_approve_a_greeting(string $text): void
    {
        $this->aiReply = 'Здравствуйте! Чем помочь?';
        $this->aiIntents = ['greeting'];
        $incoming = $this->message($text);

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($incoming->id);

        $this->assertSame('human_required', $decision->outcome);
        $this->assertSame('customer_details_provided', $decision->reason_code);
        $this->assertNull($decision->sent_avito_message_id);
        Http::assertNothingSent();
    }

    public static function specificMessages(): array
    {
        return [
            'city' => ['Ижевск'],
            'delivery question with city' => ["Авито доставка есть у вас?\nИжевск"],
            'quantity' => ['Нужно 10 мешков'],
            'order question with quantity' => ['Как заказать 10 мешков?'],
            'address' => ['Адрес: улица Ленина, дом 5'],
            'phone' => ['Мой телефон +7 999 123-45-67'],
            'email' => ['Моя почта buyer@example.com'],
        ];
    }

    public function test_city_in_the_same_incoming_bundle_prevents_a_generic_delivery_answer(): void
    {
        $question = $this->message('Авито доставка есть у вас?', at: now()->subSeconds(10));
        $city = $this->message('Ижевск', $question->chat);

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($city->id);

        $this->assertSame('human_required', $decision->outcome);
        $this->assertSame('customer_details_provided', $decision->reason_code);
        $this->assertSame([$question->id, $city->id], array_column($decision->input_bundle, 'message_id'));
        Http::assertNothingSent();
    }

    #[DataProvider('liveModes')]
    public function test_a_pending_live_handoff_survives_the_bundle_window(string $mode): void
    {
        $city = $this->message('Ижевск', at: now()->subHours(2));
        $this->pending($city, $mode);
        $next = $this->message('Как заказать?', $city->chat);

        $this->assertHandoff(app(AvitoAutoReplyService::class)->evaluateWebhookMessage($next->id));

        Http::assertNothingSent();
    }

    public static function liveModes(): array
    {
        return [['active'], ['pilot']];
    }

    #[DataProvider('shadowOutcomes')]
    public function test_shadow_analysis_cannot_create_a_live_handoff(string $outcome, string $reason): void
    {
        $old = $this->message('Как заказать?', at: now()->subHours(2));
        AvitoAutoReplyDecision::create([
            'avito_chat_id' => $old->avito_chat_id, 'avito_message_id' => $old->id,
            'mode' => 'shadow', 'outcome' => $outcome, 'reason_code' => $reason,
            'response_text' => self::DELIVERY_REPLY,
            'matched_rule_keys' => ['delivery_method'], 'evaluated_at' => now(),
        ]);
        $question = $this->message('Авито доставка есть у вас', $old->chat);

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($question->id);

        $this->assertSame('sent', $decision->outcome);
        Http::assertSentCount(3);
    }

    public static function shadowOutcomes(): array
    {
        return [
            ['would_send', 'historical_shadow'],
            ['human_required', 'customer_details_provided'],
        ];
    }

    #[DataProvider('uncertainOutcomes')]
    public function test_an_uncertain_send_of_a_clarification_does_not_allow_another_ai_reply(string $outcome, ?string $reason): void
    {
        $question = $this->message('Авито доставка есть у вас', at: now()->subHour());
        AvitoAutoReplyDecision::create([
            'avito_chat_id' => $question->avito_chat_id, 'avito_message_id' => $question->id,
            'mode' => 'active', 'outcome' => $outcome, 'reason_code' => $reason,
            'response_text' => self::DELIVERY_REPLY, 'matched_rule_keys' => ['delivery_method'],
        ]);
        $next = $this->message('Понятно', $question->chat);

        $this->assertHandoff(app(AvitoAutoReplyService::class)->evaluateWebhookMessage($next->id));

        Http::assertNothingSent();
    }

    public static function uncertainOutcomes(): array
    {
        return [['sending', null], ['error', 'send_error']];
    }

    public function test_importing_an_older_human_message_later_does_not_release_handoff(): void
    {
        $question = $this->message('Доставка есть?', at: now()->subHours(2));
        $sent = $this->sentAi($question, self::DELIVERY_REPLY, now()->subHours(2)->addSecond());
        $olderHuman = $this->message('Добрый день.', $question->chat, now()->subDay(), 'out');
        $this->assertGreaterThan($sent->id, $olderHuman->id);
        $next = $this->message('Понятно', $question->chat);

        $this->assertHandoff(app(AvitoAutoReplyService::class)->evaluateWebhookMessage($next->id));

        Http::assertNothingSent();
    }

    public function test_later_remote_human_reply_releases_handoff_even_when_imported_before_the_ai_message(): void
    {
        $question = $this->message('Доставка есть?', at: now()->subHours(3));
        $human = $this->message('Проверю варианты и напишу здесь.', $question->chat, now()->subHour(), 'out');
        $sent = $this->sentAi($question, self::DELIVERY_REPLY, now()->subHours(2));
        $this->assertGreaterThan($human->id, $sent->id);
        $next = $this->message('Авито доставка есть у вас', $question->chat);

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($next->id);

        $this->assertSame('sent', $decision->outcome);
        Http::assertSentCount(3);
    }

    public function test_same_second_outgoing_messages_use_id_to_determine_whether_the_operator_took_over(): void
    {
        $question = $this->message('Доставка есть?', at: now()->subHours(3));
        $sent = $this->sentAi($question, self::DELIVERY_REPLY, now()->subHours(2));
        $human = $this->message('Проверю варианты и напишу здесь.', $question->chat, $sent->remote_created_at, 'out');
        $this->assertGreaterThan($sent->id, $human->id);
        $next = $this->message('Авито доставка есть у вас', $question->chat);

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($next->id);

        $this->assertSame('sent', $decision->outcome);
        Http::assertSentCount(3);
    }

    public function test_preview_with_chat_uses_the_same_pending_handoff_without_calling_ai(): void
    {
        $question = $this->message('Доставка есть?', at: now()->subHour());
        $this->sentAi($question, self::DELIVERY_REPLY, now()->subHour()->addSecond());

        $result = app(AvitoAutoReplyService::class)->preview('Как заказать?', $question->chat);

        $this->assertSame('human_required', $result['outcome']);
        $this->assertSame('customer_details_handoff', $result['reason_code']);
        Http::assertNothingSent();
    }

    public function test_preview_without_chat_rejects_concrete_details_without_calling_ai(): void
    {
        $result = app(AvitoAutoReplyService::class)->preview('Ижевск');

        $this->assertSame('human_required', $result['outcome']);
        $this->assertSame('customer_details_provided', $result['reason_code']);
        Http::assertNothingSent();
    }

    public function test_handoff_discovered_during_generation_prevents_the_actual_send(): void
    {
        $question = $this->message('Авито доставка есть у вас');
        $this->duringAi = function () use ($question): void {
            $earlier = $this->message('Как заказать?', $question->chat, now()->subHours(3));
            $this->sentAi($earlier, self::DELIVERY_REPLY, now()->subHours(2));
        };

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($question->id);

        $this->assertSame('customer_details_handoff', $decision->reason_code);
        $this->assertContains($decision->outcome, ['human_required', 'skipped']);
        $this->assertNull($decision->sent_avito_message_id);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.avito.ru'));
    }

    public function test_handoff_discovered_during_token_refresh_prevents_the_messages_request(): void
    {
        $question = $this->message('Авито доставка есть у вас');
        $this->duringToken = function () use ($question): void {
            $this->message('Напишите город или населённый пункт.', $question->chat, now()->subHour(), 'out');
        };

        $decision = app(AvitoAutoReplyService::class)->evaluateWebhookMessage($question->id);

        $this->assertHandoff($decision);
        $this->assertNull($decision->sent_avito_message_id);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/messages'));
    }

    private function assertHandoff(AvitoAutoReplyDecision $decision): void
    {
        $this->assertSame('human_required', $decision->outcome);
        $this->assertSame('customer_details_handoff', $decision->reason_code);
    }

    private function message(string $text, ?AvitoChat $chat = null, ?CarbonInterface $at = null, string $direction = 'in'): AvitoMessage
    {
        if (! $chat) {
            $account = AvitoMessengerAccount::create([
                'source_key' => 'client_credentials', 'external_user_id' => '777', 'sync_enabled' => true,
            ]);
            $chat = AvitoChat::create([
                'avito_messenger_account_id' => $account->id,
                'external_chat_id' => Str::uuid()->toString(), 'chat_type' => 'u2i',
            ]);
        }

        return AvitoMessage::create([
            'avito_chat_id' => $chat->id, 'external_message_id' => Str::uuid()->toString(),
            'direction' => $direction, 'type' => 'text', 'remote_type' => 'text',
            'text' => $text, 'remote_created_at' => $at ?: now(),
        ]);
    }

    private function sentAi(AvitoMessage $source, string $text, CarbonInterface $at, array $keys = ['delivery_method']): AvitoMessage
    {
        $outgoing = $this->message($text, $source->chat, $at, 'out');
        AvitoAutoReplyDecision::create([
            'avito_message_id' => $source->id, 'avito_chat_id' => $source->avito_chat_id,
            'avito_auto_reply_rule_id' => AvitoAutoReplyRule::where('key', $keys[0])->value('id'),
            'sent_avito_message_id' => $outgoing->id, 'mode' => 'active', 'outcome' => 'sent',
            'reason_code' => 'approved_intent', 'response_text' => $text,
            'matched_rule_keys' => $keys, 'sent_at' => $at,
        ]);

        return $outgoing;
    }

    private function pending(AvitoMessage $source, string $mode = 'active'): void
    {
        AvitoAutoReplyDecision::create([
            'avito_message_id' => $source->id, 'avito_chat_id' => $source->avito_chat_id,
            'mode' => $mode, 'outcome' => 'human_required', 'reason_code' => 'customer_details_provided',
            'evaluated_at' => now(),
        ]);
    }

    private function aiBody(): array
    {
        return ['id' => 'test', 'choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode([
            'intent' => $this->aiIntents[0], 'confidence' => 0.95, 'runner_up_confidence' => 0.01,
            'unsafe' => false, 'mixed' => count($this->aiIntents) > 1, 'reason_code' => 'approved_intent',
            'response_text' => $this->aiReply, 'matched_intents' => $this->aiIntents,
        ], JSON_UNESCAPED_UNICODE)]]]];
    }
}
