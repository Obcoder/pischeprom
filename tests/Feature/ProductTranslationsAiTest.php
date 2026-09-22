<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductTranslationsAiTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/products/translate-ai';

    private const PROVIDER = 'https://api.timeweb.ai/v1/chat/completions';

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
            'product-translations-ai.enabled' => true,
            'product-translations-ai.timeweb.api_key' => 'translation-test-secret',
            'product-translations-ai.timeweb.model' => 'translation-test-model',
            'product-translations-ai.timeweb.token_parameter' => 'max_tokens',
            'product-translations-ai.timeweb.timeout_seconds' => 45,
        ]);
    }

    public function test_guests_cannot_generate_translations(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();

        Http::assertNothingSent();
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_only_active_verified_staff_can_generate_translations(array $attributes, bool $admin): void
    {
        $actor = User::factory()->create(['type' => 'employee', 'status' => 'active', ...$attributes]);
        if ($admin) {
            $actor->assignRole(Role::findOrCreate('admin', 'crm'));
        }

        $this->actingAs($actor)->postJson(self::ENDPOINT, $this->payload())->assertForbidden();

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
        $this->fakeTranslations(['eng' => 'Apple pectin']);

        $this->actingAs($actor)->postJson(self::ENDPOINT, $this->payload())
            ->assertOk()
            ->assertExactJson(['translations' => ['eng' => 'Apple pectin']]);

        Http::assertSentCount(1);
    }

    public function test_one_request_translates_all_eighteen_languages_without_creating_a_product(): void
    {
        $translations = $this->translations();
        $expectedLanguages = array_values(array_diff(Product::TRANSLATION_COLUMNS, ['rus']));
        $this->assertEqualsCanonicalizing($expectedLanguages, array_keys($translations));
        $this->fakeTranslations($translations);

        $this->actingAs($this->employee())
            ->postJson(self::ENDPOINT, $this->payload(['languages' => array_keys($translations)]))
            ->assertOk()
            ->assertExactJson(['translations' => $translations]);

        Http::assertSent(function (Request $request) use ($translations): bool {
            $this->assertSame(self::PROVIDER, $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer translation-test-secret'));
            $this->assertSame('translation-test-model', $request['model']);
            $this->assertSame(4096, $request['max_tokens']);
            $this->assertArrayNotHasKey('max_completion_tokens', $request->data());
            $this->assertFalse($request['store']);
            $this->assertFalse($request['stream']);
            $this->assertCount(2, $request['messages']);
            $this->assertSame('system', $request['messages'][0]['role']);
            $this->assertSame('user', $request['messages'][1]['role']);
            $prompt = $request['messages'][1]['content'];
            $this->assertStringContainsString('Пектин яблочный', $prompt);
            foreach (array_keys($translations) as $language) {
                $this->assertStringContainsString('"'.$language.'"', $prompt);
            }
            $context = json_decode($prompt, true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame('Португальский', $context['languages']['po']);
            $this->assertSame('Турецкий', $context['languages']['tu']);
            $this->assertSame('Индонезийский', $context['languages']['idn']);

            return true;
        });
        Http::assertSentCount(1);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_requested_subset_uses_category_context_and_leaves_existing_records_unchanged(): void
    {
        $category = Category::query()->create([
            'name' => 'Пищевые загустители',
            'description' => 'PRIVATE_CATEGORY_DESCRIPTION',
        ]);
        $existing = Product::query()->create([
            'rus' => 'Сохранённый продукт', 'eng' => 'Existing name', 'category_id' => $category->id,
        ]);
        $before = $existing->fresh()->getAttributes();
        $translations = ['eng' => 'Apple pectin', 'de' => 'Apfelpektin'];
        $this->fakeTranslations($translations);

        $this->actingAs($this->employee())->postJson(self::ENDPOINT, $this->payload([
            'category_id' => $category->id,
            'languages' => ['eng', 'de'],
            'supplier_email' => 'PRIVATE_SUPPLIER@example.test',
        ]))->assertOk()->assertExactJson(['translations' => $translations]);

        Http::assertSent(function (Request $request): bool {
            $prompt = json_encode($request->data(), JSON_UNESCAPED_UNICODE);
            $this->assertStringContainsString('Пищевые загустители', $prompt);
            $this->assertStringContainsString('Пектин яблочный', $prompt);
            foreach (['PRIVATE_CATEGORY_DESCRIPTION', 'PRIVATE_SUPPLIER', 'Existing name', 'Сохранённый продукт'] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $prompt);
            }

            return true;
        });
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('products', 1);
        Http::assertSentCount(1);
    }

    public function test_translations_are_saved_only_when_the_normal_product_form_is_submitted(): void
    {
        $translations = $this->translations();
        $this->fakeTranslations($translations);
        $this->actingAs($this->employee());

        $draft = $this->postJson(self::ENDPOINT, $this->payload(['languages' => array_keys($translations)]))
            ->assertOk()->json('translations');
        $this->assertDatabaseCount('products', 0);

        $response = $this->postJson('/api/products', ['rus' => 'Пектин яблочный', ...$draft])
            ->assertSuccessful();

        $saved = Product::query()->findOrFail($response->json('id'));
        $this->assertSame('Пектин яблочный', $saved->rus);
        foreach ($translations as $language => $value) {
            $this->assertSame($value, $saved->getAttribute($language));
        }
        Http::assertSentCount(1);
    }

    public function test_json_fences_and_surrounding_name_whitespace_are_accepted(): void
    {
        $this->fakeCompletion("```json\n{\"eng\":\" Apple pectin \"}\n```");

        $this->actingAs($this->employee())->postJson(self::ENDPOINT, $this->payload(['rus' => ' Пектин яблочный ']))
            ->assertOk()->assertExactJson(['translations' => ['eng' => 'Apple pectin']]);
    }

    public function test_alternative_completion_token_parameter_is_supported(): void
    {
        config()->set('product-translations-ai.timeweb.token_parameter', 'max_completion_tokens');
        $this->fakeTranslations(['eng' => 'Apple pectin']);

        $this->actingAs($this->employee())->postJson(self::ENDPOINT, $this->payload())->assertOk();

        Http::assertSent(fn (Request $request): bool => $request['max_completion_tokens'] === 4096
            && ! array_key_exists('max_tokens', $request->data()));
        Http::assertSentCount(1);
    }

    #[DataProvider('invalidRequests')]
    public function test_invalid_input_does_not_reach_the_provider(array $payload, string $errorKey): void
    {
        $this->actingAs($this->employee())->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($errorKey);

        Http::assertNothingSent();
    }

    public static function invalidRequests(): array
    {
        return [
            'missing Russian name' => [['languages' => ['eng']], 'rus'],
            'blank Russian name' => [['rus' => '  ', 'languages' => ['eng']], 'rus'],
            'non-string name' => [['rus' => ['name'], 'languages' => ['eng']], 'rus'],
            'oversized name' => [['rus' => str_repeat('а', 256), 'languages' => ['eng']], 'rus'],
            'missing languages' => [['rus' => 'Пектин'], 'languages'],
            'empty languages' => [['rus' => 'Пектин', 'languages' => []], 'languages'],
            'non-array languages' => [['rus' => 'Пектин', 'languages' => 'eng'], 'languages'],
            'associative languages' => [['rus' => 'Пектин', 'languages' => ['target' => 'eng']], 'languages'],
            'unsupported language' => [['rus' => 'Пектин', 'languages' => ['unknown']], 'languages.0'],
            'Russian as target' => [['rus' => 'Пектин', 'languages' => ['rus']], 'languages.0'],
            'duplicate languages' => [['rus' => 'Пектин', 'languages' => ['eng', 'eng']], 'languages.0'],
            'nested language' => [['rus' => 'Пектин', 'languages' => [['eng']]], 'languages.0'],
            'too many languages' => [['rus' => 'Пектин', 'languages' => array_fill(0, 19, 'eng')], 'languages'],
            'missing category' => [['rus' => 'Пектин', 'languages' => ['eng'], 'category_id' => 999999], 'category_id'],
            'invalid category' => [['rus' => 'Пектин', 'languages' => ['eng'], 'category_id' => 'invalid'], 'category_id'],
        ];
    }

    #[DataProvider('unavailableConfiguration')]
    public function test_invalid_configuration_returns_a_safe_error_without_http(string $key, mixed $value): void
    {
        config()->set('product-translations-ai.'.$key, $value);

        $response = $this->actingAs($this->employee())->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(503)->assertJsonStructure(['message', 'code']);

        $this->assertStringNotContainsString('translation-test-secret', $response->getContent());
        Http::assertNothingSent();
    }

    public static function unavailableConfiguration(): array
    {
        return [
            'disabled' => ['enabled', false],
            'missing key' => ['timeweb.api_key', ''],
            'invalid key' => ['timeweb.api_key', "invalid\nkey"],
            'missing model' => ['timeweb.model', ''],
            'unsupported token parameter' => ['timeweb.token_parameter', 'invalid'],
            'timeout too large' => ['timeweb.timeout_seconds', 61],
        ];
    }

    #[DataProvider('providerFailures')]
    public function test_provider_errors_are_safe_and_are_not_retried(int $providerStatus, int $expectedStatus): void
    {
        Http::fake([self::PROVIDER => Http::response(['error' => ['message' => 'translation-test-secret']], $providerStatus)]);

        $response = $this->actingAs($this->employee())->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus($expectedStatus)->assertJsonStructure(['message', 'code']);

        $this->assertStringNotContainsString('translation-test-secret', $response->getContent());
        $this->assertDatabaseCount('products', 0);
        Http::assertSentCount(1);
    }

    public static function providerFailures(): array
    {
        return ['credentials' => [401, 502], 'balance' => [402, 402], 'rate limit' => [429, 429], 'unavailable' => [503, 502]];
    }

    public function test_connection_failure_returns_a_safe_timeout(): void
    {
        Http::fake([self::PROVIDER => Http::failedConnection()]);

        $this->actingAs($this->employee())->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(504)->assertJsonStructure(['message', 'code']);

        $this->assertDatabaseCount('products', 0);
    }

    #[DataProvider('invalidTranslations')]
    public function test_incomplete_or_invalid_translation_objects_are_rejected(string $content): void
    {
        $this->fakeCompletion($content);

        $this->actingAs($this->employee())->postJson(self::ENDPOINT, $this->payload(['languages' => ['eng', 'de']]))
            ->assertStatus(502)->assertJsonStructure(['message', 'code']);

        $this->assertDatabaseCount('products', 0);
        Http::assertSentCount(1);
    }

    public static function invalidTranslations(): array
    {
        return [
            'partial result' => ['{"eng":"Apple pectin"}'],
            'extra target' => ['{"eng":"Apple pectin","de":"Apfelpektin","fr":"Pectine"}'],
            'blank value' => ['{"eng":" ","de":"Apfelpektin"}'],
            'non-string value' => ['{"eng":["Apple pectin"],"de":"Apfelpektin"}'],
            'null value' => ['{"eng":null,"de":"Apfelpektin"}'],
            'too long value' => [json_encode(['eng' => str_repeat('a', 256), 'de' => 'Apfelpektin'])],
            'HTML value' => ['{"eng":"<b>Apple pectin</b>","de":"Apfelpektin"}'],
            'control character' => [json_encode(['eng' => "Apple\0pectin", 'de' => 'Apfelpektin'])],
            'wrong wrapper' => ['{"translations":{"eng":"Apple pectin","de":"Apfelpektin"}}'],
            'list result' => ['["Apple pectin","Apfelpektin"]'],
            'empty object' => ['{}'],
            'broken JSON' => ['{"eng":"Apple pectin"'],
            'commentary' => ['Here are translations: {"eng":"Apple pectin","de":"Apfelpektin"}'],
        ];
    }

    #[DataProvider('invalidCompletions')]
    public function test_invalid_provider_envelopes_are_rejected(array $completion): void
    {
        Http::fake([self::PROVIDER => Http::response($completion)]);

        $this->actingAs($this->employee())->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(502)->assertJsonStructure(['message', 'code']);

        Http::assertSentCount(1);
    }

    public static function invalidCompletions(): array
    {
        $content = '{"eng":"Apple pectin"}';

        return [
            'missing choices' => [[]],
            'truncated answer' => [['choices' => [['message' => ['content' => $content], 'finish_reason' => 'length']]]],
            'non-string answer' => [['choices' => [['message' => ['content' => ['eng' => 'Apple pectin']], 'finish_reason' => 'stop']]]],
            'refusal' => [['choices' => [['message' => ['content' => $content, 'refusal' => 'Refused'], 'finish_reason' => 'stop']]]],
            'tool call' => [['choices' => [['message' => ['content' => $content, 'tool_calls' => [['id' => 'call_1']]], 'finish_reason' => 'stop']]]],
        ];
    }

    public function test_generation_is_limited_to_ten_requests_per_minute_per_staff_member(): void
    {
        $this->fakeTranslations(['eng' => 'Apple pectin']);
        $this->actingAs($this->employee());

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(self::ENDPOINT, $this->payload())->assertOk();
        }

        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(429)->assertHeader('Retry-After');

        Http::assertSentCount(10);
        $this->assertDatabaseCount('products', 0);
    }

    private function employee(): User
    {
        return User::factory()->create(['type' => 'employee', 'status' => 'active']);
    }

    private function payload(array $overrides = []): array
    {
        return ['rus' => 'Пектин яблочный', 'languages' => ['eng'], ...$overrides];
    }

    private function translations(): array
    {
        return [
            'eng' => 'Apple pectin', 'zh' => '苹果果胶', 'hi' => 'सेब पेक्टिन',
            'es' => 'Pectina de manzana', 'fr' => 'Pectine de pomme', 'ar' => 'بكتين التفاح',
            'po' => 'Pectina de maçã', 'ur' => 'سیب پیکٹین', 'idn' => 'Pektin apel',
            'de' => 'Apfelpektin', 'ja' => 'リンゴペクチン', 'fa' => 'پکتین سیب',
            'vi' => 'Pectin táo', 'tu' => 'Elma pektini', 'ko' => '사과 펙틴',
            'it' => 'Pectina di mela', 'nl' => 'Appelpectine', 'he' => 'פקטין תפוחים',
        ];
    }

    private function fakeTranslations(array $translations): void
    {
        $this->fakeCompletion(json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function fakeCompletion(string $content): void
    {
        Http::fake([self::PROVIDER => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
        ])]);
    }
}
