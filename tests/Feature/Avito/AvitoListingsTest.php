<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoListingGoodLink;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Models\GoodPriceTypeValue;
use App\Models\PriceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        $goodTransfer = (string) file_get_contents(resource_path('js/Components/Avito/AvitoListingGoodLink.vue'));

        $this->assertStringContainsString('<v-tab value="listings"', $page);
        $this->assertStringContainsString('<AvitoListings', $page);
        $this->assertStringContainsString('class="listings-table"', $workspace);
        $this->assertStringContainsString('class="inspector"', $workspace);
        $this->assertStringContainsString('value="promotion"', $workspace);
        $this->assertStringContainsString('value="good"', $workspace);
        $this->assertStringContainsString('<AvitoListingGoodLink', $workspace);
        $this->assertStringContainsString('const perPage = ref(100)', $workspace);
        $this->assertStringContainsString('v-model="agencyMode"', $workspace);
        $this->assertStringContainsString("'/api/avito/listings/statistics/items'", $workspace);
        $this->assertStringContainsString('Источник истины — Good', $goodTransfer);
        $this->assertStringContainsString('Применить цену Good в Avito', $goodTransfer);
    }

    public function test_own_account_listings_are_loaded_without_an_agency_header(): void
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
            && ! $request->hasHeader('X-AgencyClientId'));
    }

    public function test_agency_client_listings_include_the_client_header(): void
    {
        $this->fakeToken([
            'https://api.avito.ru/core/v1/items*' => Http::response([
                'resources' => [['id' => 7001, 'title' => 'Промышленный миксер']],
            ]),
        ]);

        $this->getJson('/api/avito/listings?'.http_build_query([
            'account_id' => 321,
            'agency_mode' => 1,
        ]))->assertOk()->assertJsonPath('items.0.id', 7001);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.avito.ru/core/v1/items?')
            && $request->hasHeader('X-AgencyClientId', '321'));
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.avito.ru/core/v1/items?')
            && str_contains($request->url(), 'per_page=100'));
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
            'agency_mode' => true,
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
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.avito.ru/stats/v2/accounts/321/items'
            && $request->hasHeader('X-AgencyClientId', '321'));
    }

    public function test_remote_forbidden_status_is_not_hidden_as_a_gateway_error(): void
    {
        $this->fakeToken([
            'https://api.avito.ru/core/v1/items*' => Http::response([], 403),
        ]);

        $this->getJson('/api/avito/listings?account_id=321')
            ->assertForbidden()
            ->assertJsonPath('category', 'listing_remote');
    }

    public function test_listing_can_be_linked_to_good_and_selected_fields_are_prepared_from_database(): void
    {
        Storage::fake('yandex');
        [$good, $price, $media] = $this->goodFixture();
        Storage::disk('yandex')->put($media->path, 'image-contents');

        $this->getJson('/api/avito/listings/goods?search=какао')
            ->assertOk()
            ->assertJsonPath('items.0.id', $good->id)
            ->assertJsonPath('items.0.prices.0.id', $price->id)
            ->assertJsonPath('items.0.media.0.id', $media->id);

        $this->putJson('/api/avito/listings/7001/good-link', [
            'account_id' => 321,
            'good_id' => $good->id,
        ])->assertOk()
            ->assertJsonPath('source_of_truth', 'good')
            ->assertJsonPath('link.good.id', $good->id)
            ->assertJsonPath('link.good.name', 'Масло какао')
            ->assertJsonPath('field_capabilities.price.mode', 'api')
            ->assertJsonPath('field_capabilities.description.mode', 'manual');

        $response = $this->postJson('/api/avito/listings/7001/good-transfer/preview', [
            'account_id' => 321,
            'fields' => ['title', 'description', 'price', 'images'],
            'price_value_id' => $price->id,
            'media_ids' => [$media->id],
            'include_facts' => true,
            'avito' => [
                'title' => 'Старое название',
                'price' => 999,
            ],
        ])->assertOk()
            ->assertJsonPath('preview.source_of_truth', 'good')
            ->assertJsonPath('preview.title.good_value', 'Масло какао')
            ->assertJsonPath('preview.title.different', true)
            ->assertJsonPath('preview.price.good_value', 1250)
            ->assertJsonPath('preview.price.avito_value', 1250)
            ->assertJsonPath('preview.price.can_apply', true)
            ->assertJsonPath('preview.images.0.id', $media->id)
            ->assertJsonPath('preview.manual_fields.0', 'title')
            ->assertJsonPath('preview.direct_fields.0', 'price')
            ->assertJsonPath('history.0.status', 'prepared')
            ->assertJsonPath('history.0.selected_fields.2', 'price');

        $this->assertStringContainsString('Натуральное масло', $response->json('preview.description.good_value'));
        $this->assertStringContainsString('Фасовка: 5 кг', $response->json('preview.description.good_value'));
        $this->assertStringContainsString('Страна: Россия', $response->json('preview.description.good_value'));
        $this->assertDatabaseHas('avito_listing_good_links', [
            'avito_account_id' => 321,
            'avito_item_id' => 7001,
            'good_id' => $good->id,
            'last_price_value_id' => $price->id,
        ]);
        $this->assertDatabaseHas('avito_listing_good_transfers', [
            'mode' => 'preview',
            'status' => 'prepared',
        ]);

        $this->get('/api/avito/listings/7001/good-transfer/media/'.$media->id.'?account_id=321')
            ->assertOk()
            ->assertDownload('maslo-kakao-'.$media->id.'.jpg');
    }

    public function test_good_price_is_reloaded_and_applied_to_avito_only_after_confirmation(): void
    {
        [$good, $price] = $this->goodFixture();
        $link = AvitoListingGoodLink::query()->create([
            'avito_account_id' => 321,
            'avito_item_id' => 7001,
            'good_id' => $good->id,
        ]);
        $price->update(['price_gross' => 1499.60]);
        config(['avito.mutations_enabled' => true]);
        $this->fakeToken([
            'https://api.avito.ru/core/v1/items/7001/update_price' => Http::response(['ok' => true]),
        ]);
        $payload = [
            'account_id' => 321,
            'fields' => ['price'],
            'price_value_id' => $price->id,
            'media_ids' => [],
            'include_facts' => false,
            'avito' => ['price' => 999],
        ];

        $this->postJson('/api/avito/listings/7001/good-transfer/apply', $payload)
            ->assertUnprocessable();
        Http::assertNothingSent();

        $this->postJson('/api/avito/listings/7001/good-transfer/apply', $payload + [
            'confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('transfer.status', 'applied')
            ->assertJsonPath('transfer.applied_fields.0', 'price')
            ->assertJsonPath('transfer.price.good_value', 1499.6)
            ->assertJsonPath('transfer.price.avito_value', 1500)
            ->assertJsonPath('history.0.status', 'applied');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.avito.ru/core/v1/items/7001/update_price'
            && $request->data()['price'] === 1500);
        $this->assertNotNull($link->fresh()->last_applied_at);
        $this->assertDatabaseHas('avito_listing_good_transfers', [
            'avito_listing_good_link_id' => $link->id,
            'mode' => 'apply',
            'status' => 'applied',
        ]);
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

    /** @return array{Good, GoodPriceTypeValue, GoodMedia} */
    private function goodFixture(): array
    {
        $currency = Currency::forceCreate([
            'name' => 'Российский рубль',
            'code' => 'RUB',
        ]);
        $priceType = PriceType::query()->create([
            'name' => 'Розница',
            'code' => 'retail',
            'currency_id' => $currency->id,
            'is_active' => true,
            'is_public' => true,
        ]);
        $country = Country::query()->create([
            'name' => 'Россия',
            'сodeISO' => 'RU',
        ]);
        $good = Good::query()->create([
            'name' => 'Масло какао',
            'description' => '<p>Натуральное масло для кондитерского производства.</p>',
            'denominator' => 5,
            'country_id' => $country->id,
            'is_published' => true,
        ]);
        $price = GoodPriceTypeValue::query()->create([
            'good_id' => $good->id,
            'price_type_id' => $priceType->id,
            'currency_id' => $currency->id,
            'price_gross' => 1250,
            'is_published' => true,
        ]);
        $media = GoodMedia::query()->create([
            'good_id' => $good->id,
            'type' => 'image',
            'disk' => 'yandex',
            'path' => "goods/{$good->id}/images/cacao.jpg",
            'url' => 'https://storage.example.test/cacao.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'is_published' => true,
            'is_ava' => true,
        ]);

        return [$good, $price, $media];
    }
}
