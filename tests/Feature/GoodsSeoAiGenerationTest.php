<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoodsSeoAiGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set([
            'goods-seo-ai.enabled' => true,
            'goods-seo-ai.timeweb.api_key' => 'seo-test-timeweb-key',
            'goods-seo-ai.timeweb.model' => 'seo-test-model',
            'goods-seo-ai.timeweb.token_parameter' => 'max_tokens',
            'goods-seo-ai.timeweb.timeout_seconds' => 45,
        ]);
    }

    public function test_guests_cannot_generate_text(): void
    {
        $good = $this->good();

        $this->postJson($this->endpoint($good), ['field' => 'h1'])
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_only_active_verified_staff_can_generate_text(array $attributes, bool $admin): void
    {
        $actor = User::factory()->create([
            'type' => 'employee',
            'status' => 'active',
            ...$attributes,
        ]);
        if ($admin) {
            $actor->assignRole(Role::findOrCreate('admin', 'crm'));
        }

        $this->actingAs($actor)->postJson($this->endpoint($this->good()), ['field' => 'h1'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public static function unauthorizedUsers(): array
    {
        return [
            'customer' => [['type' => 'customer'], false],
            'blocked employee' => [['status' => 'blocked'], false],
            'blocked administrator' => [['status' => 'blocked'], true],
            'unverified employee' => [['email_verified_at' => null], false],
        ];
    }

    public function test_an_active_administrator_with_a_legacy_customer_type_can_generate(): void
    {
        $actor = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $actor->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->fakeCompletion('Заголовок товара');

        $this->actingAs($actor)->postJson($this->endpoint($this->good()), ['field' => 'h1'])
            ->assertOk()
            ->assertExactJson(['field' => 'h1', 'value' => 'Заголовок товара']);

        Http::assertSentCount(1);
    }

    public function test_seo_load_exposes_availability_without_credentials_or_external_calls(): void
    {
        $good = $this->good();
        $this->actingAs($this->employee());

        $enabled = $this->getJson("/api/goods/{$good->id}/seo")
            ->assertOk()
            ->assertJsonPath('h1', $good->name)
            ->assertJsonPath('ai_generation.available', true);

        config()->set('goods-seo-ai.enabled', false);

        $disabled = $this->getJson("/api/goods/{$good->id}/seo")
            ->assertOk()
            ->assertJsonPath('ai_generation.available', false)
            ->assertJsonStructure(['ai_generation' => ['available', 'message']]);

        foreach ([$enabled, $disabled] as $response) {
            $this->assertStringNotContainsString('seo-test-timeweb-key', $response->getContent());
            $this->assertStringNotContainsString('seo-test-model', $response->getContent());
        }
        Http::assertNothingSent();
    }

    #[DataProvider('invalidRequests')]
    public function test_invalid_requests_do_not_reach_timeweb(array $payload, string $errorKey): void
    {
        $this->actingAs($this->employee())
            ->postJson($this->endpoint($this->good()), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);

        Http::assertNothingSent();
    }

    public static function invalidRequests(): array
    {
        return [
            'missing field' => [[], 'field'],
            'unsupported field' => [['field' => 'canonical_url'], 'field'],
            'invalid context' => [['field' => 'h1', 'context' => 'text'], 'context'],
            'unsupported context fields' => [[
                'field' => 'h1', 'context' => ['supplier_email' => 'internal@example.test'],
            ], 'context'],
            'nested keyword' => [[
                'field' => 'h1', 'context' => ['keywords' => [['secret' => 'value']]],
            ], 'context.keywords.0'],
            'oversized keyword' => [[
                'field' => 'h1', 'context' => ['focus_keyword' => str_repeat('а', 256)],
            ], 'context.focus_keyword'],
        ];
    }

    #[DataProvider('generatedFields')]
    public function test_generation_returns_a_draft_without_creating_seo_or_changing_the_good(
        string $field,
        string $value,
    ): void {
        $good = $this->good();
        $before = $good->fresh()->getAttributes();
        $this->fakeCompletion($value);

        $this->actingAs($this->employee())->postJson($this->endpoint($good), ['field' => $field])
            ->assertOk()
            ->assertExactJson(['field' => $field, 'value' => $value]);

        $this->assertSame($before, $good->fresh()->getAttributes());
        $this->assertDatabaseCount('good_seos', 0);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.timeweb.ai/v1/chat/completions'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer seo-test-timeweb-key')
            && $request['model'] === 'seo-test-model'
            && $request['max_tokens'] === match ($field) {
                'seo_text' => 4096,
                'short_seo_text' => 1024,
                default => 512,
            }
            && ! array_key_exists('max_completion_tokens', $request->data())
            && count($request['messages']) === 2);
    }

    public function test_a_model_can_use_the_alternative_completion_token_parameter(): void
    {
        config()->set('goods-seo-ai.timeweb.token_parameter', 'max_completion_tokens');
        $this->fakeCompletion('Заголовок товара');

        $this->actingAs($this->employee())
            ->postJson($this->endpoint($this->good()), ['field' => 'h1'])
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request['max_completion_tokens'] === 512
            && ! array_key_exists('max_tokens', $request->data())
            && $request['model'] === 'seo-test-model');
        Http::assertSentCount(1);
    }

    public static function generatedFields(): array
    {
        return [
            'h1' => ['h1', 'Яблочный пектин для пищевой промышленности'],
            'meta title' => ['meta_title', 'Яблочный пектин — ингредиент для кондитерских изделий'],
            'meta description' => ['meta_description', 'Яблочный пектин для изготовления джемов и кондитерских изделий. Уточните условия заказа.'],
            'short SEO text' => ['short_seo_text', 'Яблочный пектин — ингредиент для производства джемов.'],
            'long SEO text' => ['seo_text', "Применение пектина\n\nЯблочный пектин применяют в производстве джемов."],
        ];
    }

    public function test_prompt_uses_current_public_context_and_excludes_internal_records(): void
    {
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $category = Category::query()->create(['name' => 'Пищевые загустители']);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Пектин яблочный',
            'category_id' => $category->id,
            'is_published' => true,
        ]);
        $manufacturer = Unit::query()->create(['name' => 'INTERNAL_MANUFACTURER_MARKER']);
        $product->manufacturers()->attach($manufacturer);
        $good = $this->good(['country_id' => $country->id, 'denominator' => 987654.321]);
        $good->products()->attach($product);
        $seo = GoodSeo::query()->create([
            'good_id' => $good->id,
            'h1' => 'Сохранённый заголовок',
            'focus_keyword' => 'Сохранённая ключевая фраза',
            'seo_text' => '<p>Сохранённый SEO-текст</p>',
            'structured_data' => ['private_note' => 'INTERNAL_STRUCTURED_DATA_MARKER'],
            'utm_template' => 'INTERNAL_CAMPAIGN_MARKER',
        ]);
        $before = $seo->fresh()->getAttributes();
        $this->fakeCompletion('Новый заголовок пектина');

        $this->actingAs($this->employee())->postJson($this->endpoint($good), [
            'field' => 'h1',
            'context' => [
                'focus_keyword' => 'Текущая ключевая фраза',
                'keywords' => ['пектин для джемов'],
                'delivery_note' => 'Самовывоз со склада',
            ],
        ])->assertOk();

        $this->assertSame($before, $seo->fresh()->getAttributes());
        Http::assertSent(function (Request $request): bool {
            $prompt = json_encode($request->data(), JSON_UNESCAPED_UNICODE);
            foreach ([
                'Яблочный пектин',
                'Ингредиент для джемов',
                'Россия',
                'Пищевые загустители',
                'Текущая ключевая фраза',
                'пектин для джемов',
                'Самовывоз со склада',
            ] as $publicValue) {
                $this->assertStringContainsString($publicValue, $prompt);
            }
            foreach ([
                'INTERNAL_MANUFACTURER_MARKER',
                'INTERNAL_STRUCTURED_DATA_MARKER',
                'INTERNAL_CAMPAIGN_MARKER',
                '987654.321',
                'Сохранённая ключевая фраза',
            ] as $excludedValue) {
                $this->assertStringNotContainsString($excludedValue, $prompt);
            }

            return true;
        });
        Http::assertSentCount(1);
    }

    #[DataProvider('unavailableConfiguration')]
    public function test_missing_or_disabled_configuration_returns_a_safe_error_without_http(
        string $configKey,
        mixed $value,
    ): void {
        config()->set($configKey, $value);

        $response = $this->actingAs($this->employee())
            ->postJson($this->endpoint($this->good()), ['field' => 'h1'])
            ->assertStatus(503);

        $this->assertStringNotContainsString('seo-test-timeweb-key', $response->getContent());
        Http::assertNothingSent();
    }

    public static function unavailableConfiguration(): array
    {
        return [
            'disabled' => ['goods-seo-ai.enabled', false],
            'missing API key' => ['goods-seo-ai.timeweb.api_key', ''],
            'missing model' => ['goods-seo-ai.timeweb.model', ''],
            'unsupported token parameter' => ['goods-seo-ai.timeweb.token_parameter', 'invalid_parameter'],
        ];
    }

    #[DataProvider('providerFailures')]
    public function test_provider_failures_return_safe_errors_without_persisting_or_retrying(
        int $providerStatus,
        int $expectedStatus,
    ): void {
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::response([
            'error' => ['message' => 'Provider secret: seo-test-timeweb-key'],
        ], $providerStatus)]);

        $response = $this->actingAs($this->employee())
            ->postJson($this->endpoint($this->good()), ['field' => 'h1'])
            ->assertStatus($expectedStatus)
            ->assertJsonStructure(['message']);

        $this->assertStringNotContainsString('seo-test-timeweb-key', $response->getContent());
        if ($providerStatus === 402) {
            $response->assertJsonPath('code', 'seo_ai_insufficient_balance');
            $this->assertStringContainsString('баланс', $response->json('message'));
        }
        $this->assertDatabaseCount('good_seos', 0);
        Http::assertSentCount(1);
    }

    public static function providerFailures(): array
    {
        return [
            'bad credentials' => [401, 502],
            'insufficient balance' => [402, 402],
            'provider rate limited' => [429, 429],
            'provider unavailable' => [503, 502],
        ];
    }

    public function test_a_connection_failure_returns_a_safe_timeout(): void
    {
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::failedConnection()]);

        $this->actingAs($this->employee())
            ->postJson($this->endpoint($this->good()), ['field' => 'h1'])
            ->assertStatus(504)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('good_seos', 0);
    }

    #[DataProvider('invalidCompletions')]
    public function test_invalid_or_truncated_completions_are_not_returned_as_drafts(array $completion): void
    {
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::response($completion)]);

        $this->actingAs($this->employee())
            ->postJson($this->endpoint($this->good()), ['field' => 'h1'])
            ->assertStatus(502)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('good_seos', 0);
        Http::assertSentCount(1);
    }

    public static function invalidCompletions(): array
    {
        return [
            'missing choices' => [[]],
            'blank text' => [['choices' => [['message' => ['content' => '  '], 'finish_reason' => 'stop']]]],
            'invalid content type' => [['choices' => [['message' => ['content' => ['unexpected']], 'finish_reason' => 'stop']]]],
            'truncated text' => [['choices' => [['message' => ['content' => 'Оборванный заголовок'], 'finish_reason' => 'length']]]],
        ];
    }

    public function test_generated_html_is_converted_to_plain_text_without_active_content(): void
    {
        $this->fakeCompletion('<h2 onclick="alert(1)">Применение</h2><p>Пектин <strong style="color:red">для джемов</strong>.</p><script>alert("secret")</script><img src="x" onerror="alert(1)">');

        $response = $this->actingAs($this->employee())
            ->postJson($this->endpoint($this->good()), ['field' => 'seo_text'])
            ->assertOk();

        $value = $response->json('value');
        $this->assertStringContainsString('Применение', $value);
        $this->assertStringContainsString('Пектин для джемов.', $value);
        foreach (['<', '>', 'script', 'onclick', 'onerror', 'style=', 'alert(', 'secret'] as $unsafeContent) {
            $this->assertStringNotContainsString($unsafeContent, $value);
        }
        $this->assertDatabaseCount('good_seos', 0);
    }

    public function test_generation_is_rate_limited_per_staff_member(): void
    {
        $this->fakeCompletion('Заголовок товара');
        $good = $this->good();
        $this->actingAs($this->employee());

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson($this->endpoint($good), ['field' => 'h1'])->assertOk();
        }

        $this->postJson($this->endpoint($good), ['field' => 'h1'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        Http::assertSentCount(10);
        $this->assertDatabaseCount('good_seos', 0);
    }

    private function employee(): User
    {
        return User::factory()->create(['type' => 'employee', 'status' => 'active']);
    }

    private function good(array $attributes = []): Good
    {
        return Good::query()->create([
            'name' => 'Яблочный пектин',
            'description' => 'Ингредиент для джемов',
            'is_published' => true,
            ...$attributes,
        ]);
    }

    private function endpoint(Good $good): string
    {
        return "/api/goods/{$good->id}/seo/generate-ai";
    }

    private function fakeCompletion(string $value): void
    {
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => $value],
                'finish_reason' => 'stop',
            ]],
        ])]);
    }
}
