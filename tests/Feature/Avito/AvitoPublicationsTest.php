<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoAutoloadFeed;
use App\Models\AvitoConnection;
use App\Models\AvitoListingGoodLink;
use App\Models\AvitoPublication;
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

class AvitoPublicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        Storage::fake('yandex');
        Storage::fake('avito');
        config([
            'app.url' => 'https://ameise.example.test',
            'cache.default' => 'array',
            'avito.enabled' => true,
            'avito.client_id' => 'autoload-client-id',
            'avito.client_secret' => 'autoload-client-secret',
            'avito.api_base_url' => 'https://api.avito.ru',
            'avito.token_url' => 'https://api.avito.ru/token',
            'avito.allowed_hosts' => ['api.avito.ru', 'pro.autoteka.ru'],
            'avito.mutations_enabled' => false,
            'avito.autoload.media_disk' => 'avito',
        ]);
    }

    public function test_page_registers_compact_good_to_avito_publication_workspace(): void
    {
        $page = (string) file_get_contents(resource_path('js/Pages/Ameise/Avito.vue'));
        $workspace = (string) file_get_contents(resource_path('js/Components/Avito/AvitoPublications.vue'));

        $this->assertStringContainsString('<v-tab value="publications"', $page);
        $this->assertStringContainsString('<AvitoPublications', $page);
        $this->assertStringContainsString('/api/avito/publications', $workspace);
        $this->assertStringContainsString('Good остаётся источником истины', $workspace);
        $this->assertStringContainsString('Точно опубликованная версия', $workspace);
        $this->assertStringContainsString('Подтверждаю запуск', $workspace);
        $this->assertStringContainsString('@click="createGoodId = Number(item.id)"', $workspace);
        $this->assertStringContainsString(':disabled="!positiveInteger(createGoodId)"', $workspace);
        $this->assertStringNotContainsString('<v-radio v-model="createGoodId"', $workspace);
    }

    public function test_official_category_tree_and_dynamic_fields_are_normalized_with_bearer_auth(): void
    {
        Http::fake([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'category-access-token',
                'expires_in' => 86400,
            ]),
            'https://api.avito.ru/autoload/v1/user-docs/tree' => Http::response([
                'categories' => [[
                    'slug' => 'biznes',
                    'name' => 'Для бизнеса',
                    'children' => [[
                        'slug' => 'oborudovanie',
                        'name' => 'Оборудование',
                    ]],
                ]],
            ]),
            'https://api.avito.ru/autoload/v1/user-docs/node/oborudovanie/fields' => Http::response([
                'fields' => [[
                    'name' => 'GoodsType',
                    'label' => 'Вид оборудования',
                    'required' => true,
                    'type' => 'string',
                    'values' => [['value' => 'Пищевое', 'title' => 'Пищевое']],
                ]],
            ]),
        ]);

        $this->getJson('/api/avito/publications/categories?account_id=321')
            ->assertOk()
            ->assertJsonPath('items.0.slug', 'biznes')
            ->assertJsonPath('items.0.is_leaf', false)
            ->assertJsonPath('items.1.slug', 'oborudovanie')
            ->assertJsonPath('items.1.is_leaf', true)
            ->assertJsonPath('items.1.path', 'Для бизнеса → Оборудование');
        $this->getJson('/api/avito/publications/categories/oborudovanie/fields?account_id=321')
            ->assertOk()
            ->assertJsonPath('items.0.key', 'GoodsType')
            ->assertJsonPath('items.0.required', true)
            ->assertJsonPath('items.0.options.0.value', 'Пищевое');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/autoload/v1/user-docs/')
            && $request->hasHeader('Authorization', 'Bearer category-access-token'));
    }

    public function test_draft_preview_and_immutable_revision_are_created_from_good(): void
    {
        [$good, $price, $media] = $this->goodFixture();
        Storage::disk('yandex')->put($media->path, 'first-image-contents');

        $created = $this->postJson('/api/avito/publications', [
            'account_id' => 321,
            'good_id' => $good->id,
        ])->assertCreated()
            ->assertJsonPath('publication.good.id', $good->id)
            ->assertJsonPath('publication.draft.selected_fields.0', 'title')
            ->assertJsonPath('publication.draft.price_value_id', $price->id)
            ->assertJsonPath('publication.draft.media_ids.0', $media->id)
            ->assertJsonPath('feed.approved_publications_count', 0);
        $publicationId = $created->json('publication.id');

        $this->putJson("/api/avito/publications/{$publicationId}", [
            'account_id' => 321,
            'category_node_slug' => 'oborudovanie-dlya-biznesa',
            'category_name' => 'Оборудование для бизнеса',
            'selected_fields' => ['title', 'description', 'price', 'images'],
            'price_value_id' => $price->id,
            'media_ids' => [$media->id],
            'include_facts' => true,
            'address' => 'Москва, Складочная улица, 1',
            'contact_phone' => '+7 999 123-45-67',
            'manager_name' => 'Отдел продаж',
            'ad_type' => 'Товар приобретен на продажу',
            'condition' => 'Новое',
            'category_fields' => ['GoodsType' => 'Пищевое оборудование'],
            'category_schema' => [[
                'key' => 'GoodsType',
                'label' => 'Вид товара',
                'required' => true,
                'type' => 'string',
                'options' => [],
            ], [
                'key' => 'Title',
                'label' => 'Название',
                'required' => true,
                'type' => 'string',
                'options' => [],
            ], [
                'key' => 'Price',
                'label' => 'Цена',
                'required' => true,
                'type' => 'integer',
                'options' => [],
            ]],
        ])->assertOk()
            ->assertJsonPath('preview.valid', true)
            ->assertJsonPath('preview.payload.price', 1250)
            ->assertJsonPath('preview.payload.category_fields.GoodsType', 'Пищевое оборудование');

        $this->postJson("/api/avito/publications/{$publicationId}/preview", [
            'account_id' => 321,
        ])->assertOk()
            ->assertJsonPath('preview.valid', true)
            ->assertJsonPath('preview.payload.title', 'Масло какао')
            ->assertJsonPath('preview.payload.category', 'Оборудование для бизнеса')
            ->assertJsonPath('preview.payload.description', fn ($value) => str_contains($value, 'Натуральное масло'));

        $this->postJson("/api/avito/publications/{$publicationId}/approve", [
            'account_id' => 321,
        ])->assertUnprocessable();

        $approved = $this->postJson("/api/avito/publications/{$publicationId}/approve", [
            'account_id' => 321,
            'confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('publication.status', 'ready')
            ->assertJsonPath('publication.draft_dirty', false)
            ->assertJsonPath('publication.current_revision.version', 1)
            ->assertJsonPath('publication.current_revision.payload.description', fn ($value) => str_contains($value, 'Натуральное масло'))
            ->assertJsonPath('publication.current_revision.images.0.source_good_media_id', $media->id)
            ->assertJsonPath('feed.approved_publications_count', 1);

        $this->assertDatabaseHas('avito_publication_revisions', [
            'avito_publication_id' => $publicationId,
            'version' => 1,
            'is_current' => true,
        ]);
        $snapshotPath = AvitoPublication::query()->findOrFail($publicationId)
            ->currentRevision()->firstOrFail()->media()->firstOrFail()->path;
        Storage::disk('avito')->assertExists($snapshotPath);
        $this->assertSame('first-image-contents', Storage::disk('avito')->get($snapshotPath));

        $feedUrl = $approved->json('feed.url');
        $xml = $this->get($this->relativeUrl($feedUrl))->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();
        $this->assertStringContainsString('<Title>Масло какао</Title>', $xml);
        $this->assertStringContainsString('<GoodsType>Пищевое оборудование</GoodsType>', $xml);
        preg_match('/<Image url="([^"]+)"/', $xml, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->get($this->relativeUrl(html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1)))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertStreamedContent('first-image-contents');
    }

    public function test_good_changes_do_not_change_feed_until_a_new_revision_is_approved(): void
    {
        [$good, $price] = $this->goodFixture(withMedia: false);
        $publication = $this->configuredPublication($good, $price);
        $this->postJson("/api/avito/publications/{$publication->id}/approve", [
            'account_id' => 321,
            'confirmed' => true,
        ])->assertOk();
        $feed = AvitoAutoloadFeed::query()->firstOrFail();

        $good->update(['description' => '<p>Полностью новое описание из Good.</p>']);
        $price->update(['price_gross' => 2400]);

        $oldXml = $this->get($this->relativeUrl($this->feedUrl($feed)))->assertOk()->getContent();
        $this->assertStringContainsString('Натуральное масло', $oldXml);
        $this->assertStringContainsString('<Price>1250</Price>', $oldXml);
        $this->assertStringNotContainsString('Полностью новое описание', $oldXml);

        $this->postJson("/api/avito/publications/{$publication->id}/approve", [
            'account_id' => 321,
            'confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('publication.current_revision.version', 2)
            ->assertJsonPath('publication.current_revision.payload.price', 2400)
            ->assertJsonPath('publication.revisions.1.payload.description', fn ($value) => str_contains($value, 'Натуральное масло'));

        $newXml = $this->get($this->relativeUrl($this->feedUrl($feed->fresh())))->assertOk()->getContent();
        $this->assertStringContainsString('Полностью новое описание', $newXml);
        $this->assertStringContainsString('<Price>2400</Price>', $newXml);
        $this->assertStringNotContainsString('Натуральное масло', $newXml);
    }

    public function test_profile_attachment_preserves_other_feeds_and_upload_is_explicit_and_authorized(): void
    {
        [$good, $price] = $this->goodFixture(withMedia: false);
        $publication = $this->configuredPublication($good, $price);
        $this->postJson("/api/avito/publications/{$publication->id}/approve", [
            'account_id' => 321,
            'confirmed' => true,
        ])->assertOk();
        config(['avito.mutations_enabled' => true]);

        Http::fake([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'autoload-access-token',
                'expires_in' => 86400,
            ]),
            'https://api.avito.ru/autoload/v2/profile' => Http::sequence()
                ->push([
                    'feeds_data' => [[
                        'feed_name' => 'legacy-feed',
                        'feed_url' => 'https://legacy.example.test/feed.xml',
                    ]],
                    'schedule' => [],
                    'autoload_enabled' => false,
                    'report_email' => 'old@example.test',
                ])
                ->push(['ok' => true])
                ->push([
                    'feeds_data' => [
                        ['feed_name' => 'legacy-feed', 'feed_url' => 'https://legacy.example.test/feed.xml'],
                        ['feed_name' => 'ameise-goods', 'feed_url' => $this->feedUrl(AvitoAutoloadFeed::query()->firstOrFail())],
                    ],
                    'schedule' => [],
                    'autoload_enabled' => false,
                    'report_email' => 'reports@example.test',
                ]),
            'https://api.avito.ru/autoload/v1/upload' => Http::response(['upload_id' => 99]),
        ]);

        $this->postJson('/api/avito/publications/feed/profile', [
            'account_id' => 321,
            'confirmed' => true,
            'report_email' => 'reports@example.test',
            'autoload_enabled' => false,
            'schedule' => [],
        ])->assertOk()
            ->assertJsonPath('profile.feeds_data.0.feed_name', 'legacy-feed')
            ->assertJsonPath('profile.feeds_data.1.feed_name', 'ameise-goods')
            ->assertJsonPath('feed.profile_status', 'attached');

        $this->postJson('/api/avito/publications/feed/upload', [
            'account_id' => 321,
        ])->assertUnprocessable();

        $this->postJson('/api/avito/publications/feed/upload', [
            'account_id' => 321,
            'confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('submitted_publications', 1)
            ->assertJsonPath('result.upload_id', 99);
        $this->postJson('/api/avito/publications/feed/upload', [
            'account_id' => 321,
            'confirmed' => true,
        ])->assertStatus(429)
            ->assertJsonPath('category', 'autoload_rate_limit');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.avito.ru/autoload/v2/profile'
            && collect($request->data()['feeds_data'])->contains(fn ($feed) => $feed['feed_name'] === 'legacy-feed')
            && collect($request->data()['feeds_data'])->contains(fn ($feed) => $feed['feed_name'] === 'ameise-goods')
            && $request->hasHeader('Authorization', 'Bearer autoload-access-token'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.avito.ru/autoload/v1/upload'
            && $request->hasHeader('Authorization', 'Bearer autoload-access-token'));
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->url() === 'https://api.avito.ru/autoload/v1/upload'));
    }

    public function test_report_sync_maps_avito_id_and_links_listing_to_good(): void
    {
        [$good, $price] = $this->goodFixture(withMedia: false);
        $publication = $this->configuredPublication($good, $price);
        $this->postJson("/api/avito/publications/{$publication->id}/approve", [
            'account_id' => 321,
            'confirmed' => true,
        ])->assertOk();
        $externalId = $publication->fresh()->external_id;

        Http::fake([
            'https://api.avito.ru/token' => Http::response([
                'access_token' => 'report-access-token',
                'expires_in' => 86400,
            ]),
            'https://api.avito.ru/autoload/v4/uploads/current/items*' => Http::response([
                'items' => [],
            ]),
            'https://api.avito.ru/autoload/v4/uploads/last_successful/items*' => Http::response([
                'items' => [[
                    'adId' => $externalId,
                    'status' => 'rejected',
                    'messages' => [
                        ['level' => 'warning', 'message' => 'Проверьте остаток'],
                        ['level' => 'error', 'message' => 'Исправьте обязательное поле'],
                    ],
                ]],
            ]),
            'https://api.avito.ru/autoload/v2/items/avito_ids*' => Http::response([
                'items' => [[
                    'ad_id' => $externalId,
                    'avito_id' => 778899,
                ]],
            ]),
        ]);

        $this->postJson("/api/avito/publications/{$publication->id}/sync", [
            'account_id' => 321,
        ])->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('avito_item_id', 778899)
            ->assertJsonPath('messages.0.level', 'warning')
            ->assertJsonPath('messages.1.level', 'error')
            ->assertJsonPath('publication.current_revision.report_messages.0.level', 'warning')
            ->assertJsonPath('publication.current_revision.report_messages.1.level', 'error')
            ->assertJsonPath('publication.current_revision.payload.description', fn ($value) => str_contains($value, 'Натуральное масло'));

        $this->assertDatabaseHas('avito_listing_good_links', [
            'avito_account_id' => 321,
            'avito_item_id' => 778899,
            'good_id' => $good->id,
        ]);
        $this->assertSame(1, AvitoListingGoodLink::query()->count());
    }

    public function test_publication_list_has_server_pagination_instead_of_a_fixed_first_page(): void
    {
        [$good] = $this->goodFixture(withMedia: false);
        $feed = AvitoAutoloadFeed::query()->create([
            'avito_account_id' => 321,
            'name' => 'ameise-goods',
            'access_token' => str_repeat('a', 64),
            'defaults' => [],
        ]);
        foreach (range(1, 61) as $index) {
            AvitoPublication::query()->create([
                'avito_autoload_feed_id' => $feed->id,
                'good_id' => $good->id,
                'avito_account_id' => 321,
                'external_id' => "ameise-page-{$index}",
                'status' => $index <= 3 ? 'ready' : 'draft',
                'draft_payload' => [],
            ]);
        }

        $this->getJson('/api/avito/publications?account_id=321&page=2&per_page=50')
            ->assertOk()
            ->assertJsonCount(11, 'items')
            ->assertJsonPath('meta.total', 61)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('status_counts.ready', 3)
            ->assertJsonPath('status_counts.draft', 58);
    }

    private function configuredPublication(Good $good, GoodPriceTypeValue $price): AvitoPublication
    {
        $created = $this->postJson('/api/avito/publications', [
            'account_id' => 321,
            'good_id' => $good->id,
        ])->assertCreated();
        $publicationId = $created->json('publication.id');
        $this->putJson("/api/avito/publications/{$publicationId}", [
            'account_id' => 321,
            'category_node_slug' => 'oborudovanie-dlya-biznesa',
            'category_name' => 'Оборудование для бизнеса',
            'selected_fields' => ['title', 'description', 'price'],
            'price_value_id' => $price->id,
            'media_ids' => [],
            'include_facts' => true,
            'address' => 'Москва, Складочная улица, 1',
            'contact_phone' => '+7 999 123-45-67',
            'category_fields' => [],
            'category_schema' => [],
        ])->assertOk()->assertJsonPath('preview.valid', true);

        return AvitoPublication::query()->findOrFail($publicationId);
    }

    private function fakeConnection(): AvitoConnection
    {
        return AvitoConnection::query()->create([
            'name' => 'OAuth account',
            'auth_mode' => 'authorization_code',
            'external_user_id' => '321',
            'access_token' => 'oauth-token',
            'token_expires_at' => now()->addDay(),
            'scopes' => ['autoload:reports'],
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    /** @return array{Good, GoodPriceTypeValue, GoodMedia|null} */
    private function goodFixture(bool $withMedia = true): array
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
        $media = $withMedia ? GoodMedia::query()->create([
            'good_id' => $good->id,
            'type' => 'image',
            'disk' => 'yandex',
            'path' => "goods/{$good->id}/images/cacao.jpg",
            'url' => 'https://storage.example.test/cacao.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'is_published' => true,
            'is_ava' => true,
        ]) : null;

        return [$good, $price, $media];
    }

    private function feedUrl(AvitoAutoloadFeed $feed): string
    {
        return route('avito.autoload.feed', [
            'feed' => $feed->id,
            'token' => $feed->access_token,
        ]);
    }

    private function relativeUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return $path.($query ? '?'.$query : '');
    }
}
