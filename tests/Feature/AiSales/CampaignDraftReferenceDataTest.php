<?php

namespace Tests\Feature\AiSales;

use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CampaignDraftReferenceDataTest extends Stage14TestCase
{
    public function test_product_options_search_the_full_published_catalogue_with_pagination_and_selected_hydration(): void
    {
        $actor = $this->campaignUser();
        $category = Category::query()->create(['name' => 'Овощи', 'is_published' => true]);
        foreach (range(1, 60) as $index) {
            Product::query()->without(['category', 'manufacturers'])->create([
                'rus' => sprintf('А Product %03d', $index),
                'eng' => sprintf('Catalogue item %03d', $index),
                'category_id' => $category->id,
                'is_published' => true,
            ]);
        }
        $target = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Я Брокколи тестовая', 'eng' => 'Broccoli quality target',
            'category_id' => $category->id, 'is_published' => true,
        ]);

        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/products?per_page=25&page=1')
            ->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.current_page', 1);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/products?search='.urlencode('Брок'))
            ->assertOk()->assertJsonPath('data.0.id', $target->id);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/products?search='.urlencode('quality target'))
            ->assertOk()->assertJsonPath('data.0.id', $target->id);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/products?search=missing&ids[]='.$target->id)
            ->assertOk()->assertJsonPath('selected.0.id', $target->id);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/products?per_page=51')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_geography_is_cascading_and_tampered_hierarchy_is_rejected_server_side(): void
    {
        $actor = $this->campaignUser();
        $russia = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $other = Country::query()->create(['name' => 'Другая страна', 'сodeISO' => 'ZZ']);
        $region = Region::query()->without('country')->create(['name' => 'Санкт-Петербург', 'country_id' => $russia->id]);
        $otherRegion = Region::query()->without('country')->create(['name' => 'Чужой регион', 'country_id' => $other->id]);
        $city = City::query()->without('region')->create(['name' => 'Санкт-Петербург', 'region_id' => $region->id]);

        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/countries?search='.urlencode('Россия'))
            ->assertOk()->assertJsonPath('data.0.id', $russia->id);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/regions?country_id='.$russia->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $region->id);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/cities?country_id='.$russia->id.'&region_id='.$region->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $city->id);

        $payload = $this->campaignPayload(null, [
            'criteria' => [
                'country_id' => $russia->id,
                'region_id' => $otherRegion->id,
                'segments' => ['archetype:food_manufacturer'],
                'max_domains' => 30,
                'max_page_fetch_attempts' => 30,
                'max_results_per_query' => 10,
            ],
        ]);
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $payload)
            ->assertUnprocessable();

        $validPayload = $this->campaignPayload(null, [
            'criteria' => [
                'country_id' => $russia->id,
                'region_id' => $region->id,
                'city_id' => City::query()->without('region')->create([
                    'name' => 'Чужой город', 'region_id' => $otherRegion->id,
                ])->id,
                'segments' => ['archetype:food_manufacturer'],
                'max_domains' => 30,
                'max_page_fetch_attempts' => 30,
                'max_results_per_query' => 10,
            ],
        ]);
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $validPayload)
            ->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_segments_reuse_code_owned_archetypes_and_existing_catalogues_without_arbitrary_values(): void
    {
        $actor = $this->campaignUser();
        $category = Category::query()->create(['name' => 'Овощи', 'is_published' => true]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Брокколи', 'eng' => 'Broccoli', 'category_id' => $category->id, 'is_published' => true,
        ]);
        $segmentId = DB::table('segments')->insertGetId(['name' => 'Корпоративное питание', 'created_at' => now(), 'updated_at' => now()]);
        $supplierSegmentId = DB::table('segments')->insertGetId(['name' => 'Поставщик сырья', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/segments?product_id='.$product->id.'&per_page=50')
            ->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertContains('archetype:vegetable_processor', collect($response->json('data'))->where('recommended', true)->pluck('id')->all());
        $this->assertTrue(collect($response->json('data'))->contains(fn (array $item): bool => $item['source'] === 'segments'));
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/segments?search=missing&ids[]=segment:'.$segmentId)
            ->assertOk()->assertJsonPath('selected.0.name', 'Корпоративное питание');

        $validPayload = $this->campaignPayload($product);
        $validPayload['criteria']['segments'] = ['segment:'.$segmentId];
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $validPayload)->assertCreated();

        $payload = $this->campaignPayload($product);
        $payload['criteria']['segments'] = ['browser:invented-segment'];
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('criteria.segments.0');
        $payload['criteria']['segments'] = ['segment:'.$supplierSegmentId];
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('criteria.segments.0');
        Http::assertNothingSent();
    }

    public function test_product_scope_rejects_duplicate_or_unpublished_options_and_hydrates_names(): void
    {
        $actor = $this->campaignUser();
        $primary = $this->campaignProduct('Primary Product');
        $additional = $this->campaignProduct('Additional Product');
        $unpublished = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Hidden Product', 'eng' => 'Hidden Product', 'is_published' => false,
        ]);

        $payload = $this->campaignPayload($primary, ['additional_product_ids' => [$primary->id]]);
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $payload)->assertUnprocessable();
        $payload = $this->campaignPayload($primary, ['additional_product_ids' => [$additional->id, $additional->id]]);
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('additional_product_ids.1');
        $payload = $this->campaignPayload($primary, ['additional_product_ids' => [$unpublished->id]]);
        $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $payload)->assertUnprocessable();

        $payload = $this->campaignPayload($primary, ['additional_product_ids' => [$additional->id]]);
        $created = $this->actingAs($actor)->postJson('/api/ai-sales/campaigns', $payload)
            ->assertCreated()->json('data');
        $this->assertSame($primary->rus, collect($created['products'])->firstWhere('role', 'primary')['name']);
        $this->assertSame($additional->rus, collect($created['products'])->firstWhere('role', 'additional')['name']);
        Http::assertNothingSent();
    }

    public function test_reference_apis_are_permissioned_read_only_and_form_never_requests_raw_ids(): void
    {
        $actor = $this->campaignUser();
        $before = [
            Product::query()->without(['category', 'manufacturers'])->count(),
            Country::query()->count(), Region::query()->without('country')->count(), City::query()->without('region')->count(),
        ];
        app('auth')->forgetGuards();
        $this->getJson('/api/ai-sales/prospecting/catalog/products')->assertUnauthorized();
        $withoutPermission = $this->userWith(['ai_sales.view']);
        $this->actingAs($withoutPermission)->getJson('/api/ai-sales/prospecting/catalog/products')->assertForbidden();
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/catalog/products')->assertOk();
        $this->assertSame($before, [
            Product::query()->without(['category', 'manufacturers'])->count(),
            Country::query()->count(), Region::query()->without('country')->count(), City::query()->without('region')->count(),
        ]);

        $component = file_get_contents(resource_path('js/Components/AiSales/CampaignDraftForm.vue'));
        $dashboard = file_get_contents(resource_path('js/Components/AiSales/ClientAcquisitionCampaignDashboard.vue'));
        $this->assertStringContainsString('Основной продукт', $component);
        $this->assertStringContainsString('Дополнительные продукты', $component);
        $this->assertStringContainsString('Страна', $component);
        $this->assertStringContainsString('Сегменты покупателей', $component);
        $this->assertStringContainsString('form.criteria.region_id = null', $component);
        $this->assertStringContainsString('form.criteria.city_id = null', $component);
        $this->assertStringContainsString('Потенциальные покупатели', $dashboard);
        $this->assertStringContainsString('Отклонённые поставщики', $dashboard);
        $this->assertStringContainsString('Маркетплейсы/справочники', $dashboard);
        $this->assertStringContainsString('domain.source_url', $dashboard);
        $this->assertStringContainsString('target="_blank"', $dashboard);
        $this->assertStringContainsString('rel="noopener noreferrer"', $dashboard);
        $this->assertStringContainsString('mdi-open-in-new', $dashboard);
        $this->assertStringNotContainsString('Country ID', $component);
        $this->assertStringNotContainsString('Region ID', $component);
        $this->assertStringNotContainsString('City ID', $component);
        $this->assertStringContainsString('<CampaignDraftForm', $dashboard);
        Http::assertNothingSent();
    }
}
