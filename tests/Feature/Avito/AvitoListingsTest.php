<?php

namespace Tests\Feature\Avito;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvitoListingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'listing-client-id',
            'avito.client_secret' => 'listing-client-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru', 'pro.autoteka.ru'],
            'avito.mutations_enabled' => false,
        ]);
    }

    public function test_avito_page_registers_the_compact_listings_workspace(): void
    {
        $page = (string) file_get_contents(resource_path('js/Pages/Ameise/Avito.vue'));
        $workspace = (string) file_get_contents(resource_path('js/Components/Avito/AvitoListings.vue'));

        $this->assertStringContainsString('<v-tab value="listings"', $page);
        $this->assertStringContainsString('<AvitoListings', $page);
        $this->assertStringContainsString('class="listings-table"', $workspace);
        $this->assertStringContainsString('class="inspector"', $workspace);
        $this->assertStringContainsString('value="promotion"', $workspace);
    }

    public function test_listings_are_loaded_with_compact_filters_and_agency_account_header(): void
    {
        $this->fakeToken([
            'https://api.avito.ru/core/v1/items*' => Http::response([
                'resources' => [[
                    'id' => 7001,
                    'title' => 'Промышленный миксер',
                    'price' => 159000,
                    'status' => 'active',
                ]],
                'meta' => ['page' => 2, 'per_page' => 50],
            ]),
        ]);

        $this->getJson('/api/avito/listings?'.http_build_query([
            'account_id' => 321,
            'statuses' => ['active', 'old'],
            'category' => 42,
            'updated_from' => now()->subDays(7)->format('Y-m-d'),
            'page' => 2,
            'per_page' => 50,
        ]))->assertOk()
            ->assertJsonPath('items.0.id', 7001)
            ->assertJsonPath('items.0.title', 'Промышленный миксер')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('remote.status', 200);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.avito.ru/core/v1/items?')
            && str_contains($request->url(), 'status=active%2Cold')
            && str_contains($request->url(), 'category=42')
            && str_contains($request->url(), 'page=2')
            && $request->hasHeader('X-AgencyClientId', '321'));
    }

    public function test_full_statistics_and_item_trend_remain_read_only_when_mutations_are_disabled(): void
    {
        $this->fakeToken([
            'https://api.avito.ru/stats/v2/accounts/321/items' => Http::response([
                'result' => [
                    'groupings' => [[
                        'id' => 7001,
                        'type' => 'totals',
                        'metrics' => [['slug' => 'views', 'value' => 120]],
                    ]],
                    'dataTotalCount' => 1,
                ],
            ]),
            'https://api.avito.ru/stats/v1/accounts/321/items' => Http::response([
                'result' => [[
                    'itemId' => 7001,
                    'stats' => [['date' => '2026-08-01', 'uniqViews' => 11]],
                ]],
            ]),
        ]);
        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $this->postJson('/api/avito/listings/statistics', [
            'account_id' => 321,
            'date_from' => $from,
            'date_to' => $to,
            'grouping' => 'totals',
            'metrics' => ['views', 'contacts', 'favorites'],
        ])->assertOk()
            ->assertJsonPath('statistics.result.groupings.0.metrics.0.value', 120);

        $this->postJson('/api/avito/listings/statistics/items', [
            'account_id' => 321,
            'item_ids' => [7001],
            'date_from' => $from,
            'date_to' => $to,
            'grouping' => 'day',
            'fields' => ['uniqViews', 'uniqContacts', 'uniqFavorites'],
        ])->assertOk()
            ->assertJsonPath('statistics.result.0.stats.0.uniqViews', 11);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.avito.ru/stats/v1/accounts/321/items'
            && $request->data()['itemIds'] === [7001]);
    }

    public function test_promotion_insights_are_partial_and_handle_camel_case_openapi_placeholders(): void
    {
        $this->fakeToken([
            'https://api.avito.ru/promotion/v1/items/services/get' => Http::response([
                'items' => [['itemId' => 7001, 'services' => [['slug' => 'xl']]]],
            ]),
            'https://api.avito.ru/core/v1/accounts/321/vas/prices' => Http::response([
                'items' => [['itemId' => 7001, 'vas' => [['slug' => 'x5_7', 'price' => 1200]]]],
            ]),
            'https://api.avito.ru/cpxpromo/1/getPromotionsByItemIds' => Http::response([
                'error' => ['message' => 'CPX scope is unavailable'],
            ], 403),
            'https://api.avito.ru/promotion/v1/items/services/bbip/suggests/get' => Http::response([
                'items' => [],
            ]),
        ]);

        $this->postJson('/api/avito/listings/promotions', [
            'account_id' => 321,
            'item_ids' => [7001],
        ])->assertOk()
            ->assertJsonPath('active_services.ok', true)
            ->assertJsonPath('available_services.ok', true)
            ->assertJsonPath('available_services.data.items.0.vas.0.slug', 'x5_7')
            ->assertJsonPath('cpx.ok', false)
            ->assertJsonPath('cpx.message', 'CPX scope is unavailable')
            ->assertJsonPath('bbip_suggestions.ok', true);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.avito.ru/core/v1/accounts/321/vas/prices');
    }

    public function test_listing_actions_require_confirmation_and_use_the_mutation_gate(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'listing-access-token',
                'expires_in' => 86400,
            ]),
            'https://api.avito.ru/core/v1/items/7001/update_price' => Http::response(['ok' => true]),
            'https://api.avito.ru/core/v2/items/7001/vas/' => Http::response(['ok' => true]),
        ]);

        $this->postJson('/api/avito/listings/7001/action', [
            'account_id' => 321,
            'action' => 'update_price',
            'price' => 170000,
        ])->assertUnprocessable();
        Http::assertNothingSent();

        $this->postJson('/api/avito/listings/7001/action', [
            'account_id' => 321,
            'action' => 'update_price',
            'price' => 170000,
            'confirmed' => true,
        ])->assertForbidden()->assertJsonPath('category', 'mutations_disabled');

        config(['avito.mutations_enabled' => true]);
        $this->postJson('/api/avito/listings/7001/action', [
            'account_id' => 321,
            'action' => 'apply_services',
            'slugs' => ['x10_1', 'xl'],
            'confirmed' => true,
        ])->assertOk()->assertJsonPath('action', 'apply_services');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.avito.ru/core/v2/items/7001/vas/'
            && $request->data()['slugs'] === ['x10_1', 'xl']);
    }

    private function fakeToken(array $responses = []): void
    {
        Http::fake(array_merge([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'listing-access-token',
                'expires_in' => 86400,
            ]),
        ], $responses));
    }
}
