<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Good;
use App\Models\Product;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoodsVatAiCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5));
        Http::preventStrayRequests();
        config()->set([
            'goods-vat-ai.enabled' => null,
            'goods-vat-ai.timeweb.api_key' => null,
            'goods-vat-ai.timeweb.model' => null,
            'goods-vat-ai.timeweb.token_parameter' => null,
            'goods-vat-ai.timeweb.timeout_seconds' => null,
            'goods-seo-ai.enabled' => true,
            'goods-seo-ai.timeweb.api_key' => 'vat-test-private-key',
            'goods-seo-ai.timeweb.model' => 'vat-test-model',
            'goods-seo-ai.timeweb.token_parameter' => 'max_tokens',
            'goods-seo-ai.timeweb.timeout_seconds' => 45,
        ]);
    }

    public function test_guests_cannot_check_or_read_availability(): void
    {
        $this->getJson('/api/goods/vat-check/availability')->assertUnauthorized();
        $this->postJson('/api/goods/vat-check', ['name' => 'Товар'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_only_active_verified_staff_can_check(array $attributes): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active', ...$attributes]));
        $this->getJson('/api/goods/vat-check/availability')->assertForbidden();
        $this->postJson('/api/goods/vat-check', ['name' => 'Товар'])->assertForbidden();
        Http::assertNothingSent();
    }

    public static function unauthorizedUsers(): array
    {
        return [
            'customer' => [['type' => 'customer']],
            'blocked employee' => [['status' => 'blocked']],
            'unverified employee' => [['email_verified_at' => null]],
        ];
    }

    public function test_administrator_uses_catalog_ai_configuration_without_exposing_credentials(): void
    {
        $actor = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $actor->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($actor);
        $response = $this->getJson('/api/goods/vat-check/availability')->assertOk()
            ->assertJsonPath('available', true)->assertJsonPath('rules_verified_at', '2026-10-05')
            ->assertJsonPath('jurisdiction', 'RU')->assertJsonCount(4, 'sources');
        $this->assertStringNotContainsString('vat-test-private-key', $response->getContent());
        $this->assertStringNotContainsString('vat-test-model', $response->getContent());
        config()->set('goods-vat-ai.enabled', false);
        $this->getJson('/api/goods/vat-check/availability')->assertOk()->assertJsonPath('available', false);
        $this->postJson('/api/goods/vat-check', ['name' => 'Товар'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_draft_check_returns_dictionary_id_without_creating_goods(): void
    {
        $vat = VatRate::query()->create(['title' => 'НДС 22%', 'rate' => 22]);
        $this->fakeAnswer();
        $this->actingAs($this->employee())->postJson('/api/goods/vat-check', ['name' => 'Стол металлический'])
            ->assertOk()->assertJsonPath('status', 'suggestion')->assertJsonPath('rate', 22)
            ->assertJsonPath('vat_rate_id', $vat->id)->assertJsonPath('advisory', true)
            ->assertJsonPath('operation', 'domestic')->assertJsonPath('check_date', '2026-10-05');
        $this->assertDatabaseCount('goods', 0);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.timeweb.ai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer vat-test-private-key')
            && $request['model'] === 'vat-test-model' && $request['max_tokens'] === 2048
            && $request['response_format']['type'] === 'json_object' && $request['store'] === false);
    }

    public function test_saved_good_and_trade_codes_are_not_mutated_and_only_draft_catalog_context_is_sent(): void
    {
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $category = Category::query()->create(['name' => 'Промышленное оборудование']);
        $product = Product::query()->without(['category', 'manufacturers'])->create(['rus' => 'Столы', 'category_id' => $category->id]);
        $vat = VatRate::query()->create(['title' => '22%', 'rate' => 22]);
        $good = Good::query()->create(['name' => 'OLD_NAME_NOT_SENT', 'vat_rate_id' => $vat->id, 'hs_code' => '940320', 'denominator' => 987654.321]);
        $before = $good->fresh()->getAttributes();
        $this->fakeAnswer();
        $this->actingAs($this->employee())->postJson('/api/goods/vat-check', [
            'good_id' => $good->id, 'name' => 'Новый стол', 'description' => '<p>Нержавеющая сталь</p>',
            'country_id' => $country->id, 'product_ids' => [$product->id], 'vat_rate_id' => $vat->id,
            'hs_code' => '9403 20', 'operation' => 'import', 'private_note' => 'INTERNAL_NOTE_NOT_SENT',
        ])->assertOk()->assertJsonPath('operation', 'import');
        $this->assertSame($before, $good->fresh()->getAttributes());
        Http::assertSent(function (Request $request): bool {
            $context = json_decode($request['messages'][1]['content'], true);
            $this->assertSame('Новый стол', $context['name']);
            $this->assertSame('Нержавеющая сталь', $context['description']);
            $this->assertSame('Россия', $context['country_of_origin']);
            $this->assertSame(['Промышленное оборудование'], $context['categories']);
            $this->assertSame('940320', $context['trade_codes']['hs_code']);
            $prompt = json_encode($request->data());
            foreach (['OLD_NAME_NOT_SENT', 'INTERNAL_NOTE_NOT_SENT', '987654.321'] as $secret) {
                $this->assertStringNotContainsString($secret, $prompt);
            }

            return true;
        });
    }

    #[DataProvider('guardedRates')]
    public function test_special_rates_cannot_be_applied_based_only_on_model_output(int $rate, string $operation): void
    {
        VatRate::query()->create(['title' => "{$rate}%", 'rate' => $rate]);
        $this->fakeAnswer(['rate' => $rate]);
        $response = $this->actingAs($this->employee())->postJson('/api/goods/vat-check', [
            'name' => 'Товар', 'operation' => $operation, 'tn_ved_code' => '1602509509', 'okpd2_code' => '10.20.25.115',
        ])->assertOk()->assertJsonPath('status', 'needs_information')->assertJsonPath('rate', $rate)
            ->assertJsonPath('vat_rate_id', null);
        $this->assertNotEmpty($response->json('missing_information'));
    }

    public static function guardedRates(): array
    {
        return ['reduced' => [10, 'domestic'], 'import reduced' => [10, 'import'], 'special5' => [5, 'domestic'],
            'special7' => [7, 'domestic'], 'zero' => [0, 'domestic'], 'export' => [22, 'export']];
    }

    public function test_missing_dictionary_rate_is_reported_instead_of_creating_it(): void
    {
        $this->fakeAnswer();
        $response = $this->actingAs($this->employee())->postJson('/api/goods/vat-check', ['name' => 'Стол'])
            ->assertOk()->assertJsonPath('status', 'needs_information')->assertJsonPath('rate', 22)->assertJsonPath('vat_rate_id', null);
        $this->assertStringContainsString('справочник', implode(' ', $response->json('missing_information')));
        $this->assertDatabaseCount('vat_rates', 0);
    }

    #[DataProvider('invalidRequests')]
    public function test_invalid_input_never_reaches_provider(array $payload, string $error): void
    {
        $this->actingAs($this->employee())->postJson('/api/goods/vat-check', ['name' => 'Товар', ...$payload])
            ->assertUnprocessable()->assertJsonValidationErrors($error);
        Http::assertNothingSent();
    }

    public static function invalidRequests(): array
    {
        return ['missing name' => [['name' => ''], 'name'], 'oversized name' => [['name' => str_repeat('а', 256)], 'name'],
            'unsupported operation' => [['operation' => 'elsewhere'], 'operation'], 'bad code' => [['hs_code' => '123'], 'hs_code'],
            'bad date' => [['check_date' => 'not-a-date'], 'check_date'], 'unknown good' => [['good_id' => 99999], 'good_id']];
    }

    public function test_dates_outside_verified_year_do_not_use_the_model(): void
    {
        $this->actingAs($this->employee())->postJson('/api/goods/vat-check', ['name' => 'Товар', 'check_date' => '2025-12-31'])
            ->assertOk()->assertJsonPath('status', 'needs_information')->assertJsonPath('vat_rate_id', null);
        Http::assertNothingSent();
    }

    #[DataProvider('invalidAnswers')]
    public function test_untrusted_model_outputs_are_rejected(array $answer): void
    {
        $this->fakeAnswer($answer);
        $this->actingAs($this->employee())->postJson('/api/goods/vat-check', ['name' => 'Товар'])
            ->assertStatus(502)->assertJsonPath('code', 'vat_ai_invalid_response');
        Http::assertSentCount(1);
    }

    public static function invalidAnswers(): array
    {
        return ['old rate' => [['rate' => 20]], 'string rate' => [['rate' => '22']],
            'model database id' => [['vat_rate_id' => 999]], 'invented source' => [['source_ids' => ['https://evil.example']]],
            'missing source' => [['source_ids' => []]], 'long rationale' => [['rationale' => str_repeat('x', 2501)]]];
    }

    public function test_provider_failures_do_not_expose_payloads_or_keys(): void
    {
        Http::fake(fn () => throw new \RuntimeException('vat-test-private-key INTERNAL_PROVIDER_ERROR'));
        $response = $this->actingAs($this->employee())->postJson('/api/goods/vat-check', ['name' => 'Товар'])
            ->assertStatus(502)->assertJsonPath('code', 'vat_ai_provider_error');
        $this->assertStringNotContainsString('vat-test-private-key', $response->getContent());
        $this->assertStringNotContainsString('INTERNAL_PROVIDER_ERROR', $response->getContent());
    }

    public function test_provider_timeout_is_bounded_and_not_retried(): void
    {
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::failedConnection()]);
        $this->actingAs($this->employee())->postJson('/api/goods/vat-check', ['name' => 'Товар'])
            ->assertStatus(504)->assertJsonPath('code', 'vat_ai_timeout');
    }

    public function test_oversized_responses_are_rejected(): void
    {
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::response(str_repeat('x', 140000), 200, ['Content-Type' => 'application/json'])]);
        $this->actingAs($this->employee())->postJson('/api/goods/vat-check', ['name' => 'Товар'])
            ->assertStatus(502)->assertJsonPath('code', 'vat_ai_invalid_response');
    }

    public function test_checks_are_limited_to_six_per_minute_per_user(): void
    {
        $this->fakeAnswer();
        $this->actingAs($this->employee());
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/goods/vat-check', ['name' => 'Товар'])->assertOk();
        }
        $this->postJson('/api/goods/vat-check', ['name' => 'Товар'])->assertStatus(429)->assertHeader('Retry-After');
        Http::assertSentCount(6);
    }

    private function employee(): User
    {
        return User::factory()->create(['type' => 'employee', 'status' => 'active']);
    }

    private function fakeAnswer(array $overrides = []): void
    {
        Http::fake(['https://api.timeweb.ai/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => json_encode([
                    'status' => 'suggestion', 'rate' => 22, 'rationale' => 'Для промышленного стола применяется общая ставка.',
                    'missing_information' => [], 'source_ids' => ['fns_rates', 'fns_2026'], ...$overrides,
                ], JSON_UNESCAPED_UNICODE)],
                'finish_reason' => 'stop',
            ]],
        ])]);
    }
}
