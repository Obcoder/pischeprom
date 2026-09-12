<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoAutoReplyExample;
use App\Models\AvitoAutoReplyRule;
use App\Services\Avito\AutoReply\AvitoAutoReplyClassifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AvitoAutoReplyClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'ai-price-lists.ai.base_url' => 'https://ai.api.cloud.yandex.net/v1',
            'ai-price-lists.ai.api_key' => 'fake-yandex-key',
            'ai-price-lists.ai.folder_id' => 'test-folder',
            'ai-price-lists.ai.model' => 'yandexgpt/latest',
        ]);
        Cache::clear();
        Http::preventStrayRequests();
    }

    public function test_flexible_reply_uses_only_explicit_public_facts_and_tracks_all_sources(): void
    {
        Http::fake(['*' => Http::response($this->response($this->validData()))]);
        $result = (new AvitoAutoReplyClassifier)->classify('Добрый день, как заказать, нужна фасовка?', $this->rules(), 'avito-chat', true);

        $this->assertSame(['greeting', 'order'], $result->matchedIntents);
        $this->assertSame('Здравствуйте! Напишите нужный товар и объём заказа.', $result->responseText);
        $this->assertTrue($result->mixed);
        Http::assertSent(function (Request $request) {
            $payload = $request->data();
            $data = json_decode($payload['messages'][1]['content'], true);

            return array_keys($data) === ['message', 'approved_intents']
                && $data['approved_intents'][1]['public_facts'] === 'Напишите нужный товар и объём заказа.'
                && $data['approved_intents'][1]['positive_examples'] === ['Как заказать?']
                && ! str_contains($payload['messages'][1]['content'], 'private-marker')
                && $payload['tools'] === []
                && $payload['store'] === false
                && $payload['max_completion_tokens'] >= 1000;
        });
    }

    public function test_fixed_mode_keeps_response_text_out_of_ai_context(): void
    {
        $data = $this->validData();
        unset($data['response_text'], $data['matched_intents']);
        $data['mixed'] = false;
        Http::fake(['*' => Http::response($this->response($data))]);
        $result = (new AvitoAutoReplyClassifier)->classify('Здравствуйте', $this->rules(), 'avito-chat');
        $this->assertNull($result->responseText);
        $this->assertSame([], $result->matchedIntents);
        Http::assertSent(fn (Request $request) => ! str_contains($request->data()['messages'][1]['content'], 'public_facts'));
    }

    #[DataProvider('invalidData')]
    public function test_invalid_structured_decisions_are_rejected(array $overrides): void
    {
        Http::fake(['*' => Http::response($this->response(array_replace($this->validData(), $overrides)))]);
        $this->expectException(RuntimeException::class);
        (new AvitoAutoReplyClassifier)->classify('Как заказать?', $this->rules(), 'avito-chat', true);
    }

    public static function invalidData(): array
    {
        return array_map(fn ($overrides) => [$overrides], [
            ['confidence' => 1.01], ['confidence' => -0.1], ['confidence' => '0.99'],
            ['runner_up_confidence' => 2], ['runner_up_confidence' => false],
            ['unsafe' => 'false'], ['mixed' => 1], ['intent' => ['greeting']],
            ['reason_code' => 'unknown'], ['unsafe' => true],
            ['response_text' => null], ['response_text' => ''], ['response_text' => str_repeat('а', 2001)],
            ['matched_intents' => []], ['matched_intents' => ['order']],
            ['matched_intents' => ['greeting', 'unknown']], ['matched_intents' => ['greeting', 'greeting']],
            ['matched_intents' => ['greeting', ['order']]], ['matched_intents' => ['greeting', 'human_required']],
            ['unexpected' => 'unapproved data'], ['intent' => 'human_required'],
        ]);
    }

    #[DataProvider('invalidEnvelopes')]
    public function test_truncated_refused_and_tool_responses_cannot_be_sent(array $overrides): void
    {
        $response = array_replace_recursive($this->response($this->validData()), $overrides);
        Http::fake(['*' => Http::response($response)]);
        $this->expectException(RuntimeException::class);
        (new AvitoAutoReplyClassifier)->classify('Как заказать?', $this->rules(), 'avito-chat', true);
    }

    public static function invalidEnvelopes(): array
    {
        return array_map(fn ($overrides) => [$overrides], [
            ['choices' => [['finish_reason' => 'length']]],
            ['choices' => [['finish_reason' => 'content_filter']]],
            ['choices' => [['message' => ['refusal' => 'Cannot answer']]]],
            ['choices' => [['message' => ['tool_calls' => [['type' => 'function']]]]]],
            ['choices' => [['message' => ['content' => '[]']]]],
            ['choices' => [['message' => ['content' => 'null']]]],
            ['choices' => [['message' => ['content' => '{"intent":"greeting"}']]]],
        ]);
    }

    public function test_handoff_requires_empty_text_and_sources(): void
    {
        $data = array_replace($this->validData(), [
            'intent' => 'human_required', 'reason_code' => 'sensitive_request',
            'unsafe' => true, 'response_text' => null, 'matched_intents' => [],
        ]);
        Http::fake(['*' => Http::response($this->response($data))]);
        $result = (new AvitoAutoReplyClassifier)->classify('Когда доставка?', $this->rules(), 'avito-chat', true);
        $this->assertNull($result->responseText);
        $this->assertSame('human_required', $result->intent);
    }

    private function validData(): array
    {
        return [
            'intent' => 'greeting', 'confidence' => 0.98, 'runner_up_confidence' => 0.01,
            'unsafe' => false, 'mixed' => true, 'reason_code' => 'approved_intent',
            'response_text' => 'Здравствуйте! Напишите нужный товар и объём заказа.',
            'matched_intents' => ['greeting', 'order'],
        ];
    }

    private function response(array $data): array
    {
        return [
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($data, JSON_UNESCAPED_UNICODE)]]],
        ];
    }

    private function rules(): Collection
    {
        return collect([
            (new AvitoAutoReplyRule(['key' => 'greeting', 'name' => 'Приветствие', 'response_text' => 'Здравствуйте!']))
                ->setRelation('examples', collect()),
            (new AvitoAutoReplyRule(['key' => 'order', 'name' => 'Заказ', 'response_text' => 'Напишите нужный товар и объём заказа.']))
                ->setRelation('examples', collect([new AvitoAutoReplyExample(['kind' => 'positive', 'text' => 'Как заказать?'])]))
                ->setAttribute('private_note', 'private-marker'),
        ]);
    }
}
