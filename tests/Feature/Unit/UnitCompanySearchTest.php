<?php

namespace Tests\Feature\Unit;

use App\Models\Entity;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnitCompanySearchTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('services.dadata.token', 'synthetic-company-search-token');
    }

    public function test_search_sends_exact_filters_and_returns_entity_form_data_without_creating_records(): void
    {
        $suggestion = $this->suggestion();
        Http::fake([self::ENDPOINT => Http::response(['suggestions' => [$suggestion]])]);

        $this->actingAs($this->employee())
            ->postJson(route('web.units.company-search'), [
                'query' => '  Пищепром  ',
                'okved' => ['10.89.9', '46.38'],
                'status' => ['ACTIVE', 'REORGANIZING'],
                'type' => 'LEGAL',
                'region_code' => '77',
                'count' => 5,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity.name', 'ООО "Пищепром"')
            ->assertJsonPath('data.0.entity.INN', '7707083893')
            ->assertJsonPath('data.0.entity.KPP', '770701001')
            ->assertJsonPath('data.0.entity.OGRN', '1027700132195')
            ->assertJsonPath('data.0.entity.legal_address', 'Россия, г Москва, ул Тестовая, д 1')
            ->assertJsonPath('data.0.entity.okved', '10.89.9')
            ->assertJsonPath('data.0.entity.status', 'ACTIVE')
            ->assertJsonPath('data.0.raw', $suggestion)
            ->assertJsonPath('data.0.existing_entities', [])
            ->assertJsonPath('meta', [
                'source' => 'DaData',
                'limit' => 5,
                'returned' => 1,
                'exhaustive' => false,
                'okved_match' => 'main_exact',
            ]);

        Http::assertSent(fn (Request $request) => $request->url() === self::ENDPOINT
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Token synthetic-company-search-token')
            && $request->data() == [
                'query' => 'Пищепром',
                'okved' => ['10.89.9', '46.38'],
                'count' => 5,
                'status' => ['ACTIVE', 'REORGANIZING'],
                'type' => 'LEGAL',
                'locations' => [['kladr_id' => '77']],
            ]);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('entities', 0);
        $this->assertDatabaseCount('units', 0);
    }

    public function test_numeric_query_keeps_okved_filter_and_defaults_to_active_companies(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['suggestions' => []])]);

        $this->actingAs($this->employee())
            ->postJson('/web/units/company-search', ['query' => '7707083893', 'okved' => ['10']])
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.limit', 20)
            ->assertJsonPath('meta.returned', 0)
            ->assertJsonPath('meta.exhaustive', false);

        Http::assertSent(fn (Request $request) => $request->url() === self::ENDPOINT
            && $request->data() == [
                'query' => '7707083893', 'okved' => ['10'], 'count' => 20, 'status' => ['ACTIVE'],
            ]);
        Http::assertSentCount(1);
    }

    public function test_existing_lookup_by_inn_keeps_its_endpoint_and_entity_mapping(): void
    {
        $endpoint = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party';
        $suggestion = $this->suggestion();
        Http::fake([$endpoint => Http::response(['suggestions' => [$suggestion]])]);

        $this->actingAs($this->employee())
            ->getJson('/web/entities/lookup-by-inn?inn=7707083893')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity.name', 'ООО "Пищепром"')
            ->assertJsonPath('data.0.entity.INN', '7707083893')
            ->assertJsonPath('data.0.entity.entity_classification_name', 'ООО')
            ->assertJsonPath('data.0.raw', $suggestion);

        Http::assertSent(fn (Request $request) => $request->url() === $endpoint
            && $request->data() == ['query' => '7707083893', 'count' => 5]);
        Http::assertSentCount(1);
    }

    public function test_all_statuses_and_individual_businesses_can_be_requested(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['suggestions' => []])]);

        $this->actingAs($this->employee())
            ->postJson('/web/units/company-search', [
                ...$this->payload(), 'status' => [], 'type' => 'INDIVIDUAL', 'region_code' => '01', 'count' => 1,
            ])
            ->assertOk();

        Http::assertSent(fn (Request $request) => ! array_key_exists('status', $request->data())
            && $request['type'] === 'INDIVIDUAL'
            && $request['count'] === 1
            && $request['locations'] === [['kladr_id' => '01']]);
    }

    public function test_existing_entities_and_units_are_reported_by_inn_including_other_branches(): void
    {
        $entity = Entity::query()->create(['name' => 'Карточка организации', 'INN' => '7707083893', 'KPP' => '770701001']);
        $branch = Entity::query()->create(['name' => 'Филиал организации', 'INN' => '7707083893', 'KPP' => '780101001']);
        Entity::query()->create(['name' => 'ООО "Пищепром"', 'INN' => '7800000000']);
        $unit = Unit::query()->create(['name' => 'Существующий контрагент']);
        $entity->units()->attach($unit->id);
        $before = $entity->fresh()->getAttributes();
        Http::fake([self::ENDPOINT => Http::response(['suggestions' => [$this->suggestion()]])]);

        $response = $this->actingAs($this->employee())
            ->postJson('/web/units/company-search', $this->payload())
            ->assertOk()
            ->assertJsonCount(2, 'data.0.existing_entities');

        $matches = collect($response->json('data.0.existing_entities'))->keyBy('id');
        $this->assertSame([
            'id' => $entity->id,
            'name' => 'Карточка организации',
            'units' => [['id' => $unit->id, 'name' => 'Существующий контрагент']],
        ], $matches->get($entity->id));
        $this->assertSame(['id' => $branch->id, 'name' => 'Филиал организации', 'units' => []], $matches->get($branch->id));
        $this->assertSame($before, $entity->fresh()->getAttributes());
        $this->assertDatabaseCount('entities', 3);
        $this->assertDatabaseCount('units', 1);
        $this->assertDatabaseCount('entity_unit', 1);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_search_input_never_reaches_dadata(array $changes, string $field): void
    {
        $this->actingAs($this->employee())
            ->postJson('/web/units/company-search', array_replace($this->payload(), $changes))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        Http::assertNothingSent();
    }

    public static function invalidInputs(): array
    {
        return [
            'missing query' => [['query' => null], 'query'],
            'blank query' => [['query' => '   '], 'query'],
            'short trimmed query' => [['query' => ' я '], 'query'],
            'long query' => [['query' => str_repeat('я', 301)], 'query'],
            'missing codes' => [['okved' => null], 'okved'],
            'empty codes' => [['okved' => []], 'okved'],
            'string codes' => [['okved' => '10.89.9'], 'okved'],
            'numeric code' => [['okved' => [10]], 'okved.0'],
            'short code' => [['okved' => ['1']], 'okved.0'],
            'wildcard code' => [['okved' => ['10.*']], 'okved.0'],
            'long code segment' => [['okved' => ['10.123']], 'okved.0'],
            'subclass before group' => [['okved' => ['10.5.1']], 'okved.0'],
            'extra code segment' => [['okved' => ['10.12.34.56']], 'okved.0'],
            'duplicate codes' => [['okved' => ['10.89.9', '10.89.9']], 'okved.0'],
            'too many codes' => [['okved' => array_map('strval', range(10, 20))], 'okved'],
            'zero count' => [['count' => 0], 'count'],
            'too high count' => [['count' => 21], 'count'],
            'invalid status' => [['status' => ['UNKNOWN']], 'status.0'],
            'string status' => [['status' => 'ACTIVE'], 'status'],
            'invalid type' => [['type' => 'OTHER'], 'type'],
            'invalid region' => [['region_code' => '00'], 'region_code'],
            'long region' => [['region_code' => '770'], 'region_code'],
        ];
    }

    public function test_guests_cannot_search(): void
    {
        $this->postJson('/web/units/company-search', $this->payload())->assertUnauthorized();

        Http::assertNothingSent();
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_customers_and_inactive_employees_cannot_search(string $type, string $status): void
    {
        $this->actingAs(User::factory()->create(['type' => $type, 'status' => $status]))
            ->postJson('/web/units/company-search', $this->payload())
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public static function unauthorizedUsers(): array
    {
        return [['customer', 'active'], ['employee', 'blocked']];
    }

    public function test_missing_token_returns_a_russian_configuration_message_without_an_http_call(): void
    {
        config()->set('services.dadata.token', null);

        $response = $this->actingAs($this->employee())
            ->postJson('/web/units/company-search', $this->payload())
            ->assertStatus(503);

        $this->assertMatchesRegularExpression('/[А-Яа-я]/u', $response->json('message'));
        Http::assertNothingSent();
    }

    #[DataProvider('upstreamFailures')]
    public function test_upstream_failures_return_safe_errors(int $upstreamStatus, int $expectedStatus): void
    {
        Http::fake([self::ENDPOINT => Http::response(['message' => 'PRIVATE_UPSTREAM_BODY'], $upstreamStatus)]);

        $response = $this->actingAs($this->employee())
            ->postJson('/web/units/company-search', $this->payload())
            ->assertStatus($expectedStatus);

        $this->assertIsString($response->json('message'));
        $this->assertStringNotContainsString('PRIVATE_UPSTREAM_BODY', $response->getContent());
        $this->assertStringNotContainsString('synthetic-company-search-token', $response->getContent());
        Http::assertSentCount(1);
    }

    public static function upstreamFailures(): array
    {
        return [[401, 503], [403, 503], [429, 429], [500, 503], [503, 503]];
    }

    #[DataProvider('malformedResponses')]
    public function test_malformed_successful_responses_are_rejected(mixed $body): void
    {
        Http::fake([self::ENDPOINT => Http::response($body)]);

        $this->actingAs($this->employee())
            ->postJson('/web/units/company-search', $this->payload())
            ->assertStatus(502);
    }

    public static function malformedResponses(): array
    {
        return [
            'invalid json' => ['not json'],
            'missing suggestions' => [[]],
            'invalid suggestions type' => [['suggestions' => 'invalid']],
            'invalid suggestion' => [['suggestions' => [null]]],
            'invalid nested field' => [['suggestions' => [['data' => ['inn' => []]]]]],
        ];
    }

    public function test_connection_failures_do_not_expose_transport_details(): void
    {
        Http::fake([self::ENDPOINT => Http::failedConnection('PRIVATE_CONNECTION_DETAILS')]);

        $response = $this->actingAs($this->employee())
            ->postJson('/web/units/company-search', $this->payload())
            ->assertStatus(503);

        $this->assertStringNotContainsString('PRIVATE_CONNECTION_DETAILS', $response->getContent());
    }

    public function test_requests_are_throttled_before_the_next_paid_provider_call(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['suggestions' => []])]);
        $this->actingAs($this->employee());

        $successfulRequests = 0;
        for ($attempt = 0; $attempt < 11; $attempt++) {
            $response = $this->postJson('/web/units/company-search', $this->payload());
            if ($response->status() === 429) {
                $response->assertHeader('Retry-After');
                break;
            }
            $response->assertOk();
            $successfulRequests++;
        }

        $this->assertSame(10, $successfulRequests);
        $response->assertStatus(429);
        Http::assertSentCount($successfulRequests);
    }

    private function employee(): User
    {
        return User::factory()->create(['type' => 'employee', 'status' => 'active']);
    }

    private function payload(): array
    {
        return ['query' => 'Пищепром', 'okved' => ['10.89.9']];
    }

    private function suggestion(): array
    {
        return [
            'value' => 'ООО "Пищепром"',
            'data' => [
                'inn' => '7707083893',
                'kpp' => '770701001',
                'ogrn' => '1027700132195',
                'name' => ['short_with_opf' => 'ООО "Пищепром"', 'full_with_opf' => 'Общество с ограниченной ответственностью "Пищепром"'],
                'type' => 'LEGAL',
                'opf' => ['short' => 'ООО'],
                'address' => ['unrestricted_value' => 'Россия, г Москва, ул Тестовая, д 1', 'data' => ['country' => 'Россия']],
                'okved' => '10.89.9',
                'state' => ['status' => 'ACTIVE'],
            ],
        ];
    }
}
