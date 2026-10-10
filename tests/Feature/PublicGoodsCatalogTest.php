<?php

namespace Tests\Feature;

use App\Models\CatalogNode;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Field;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodSeo;
use App\Models\GoodStockAvailability;
use App\Models\PriceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicGoodsCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
    }

    public function test_database_pagination_reaches_products_beyond_the_old_96_item_limit(): void
    {
        for ($i = 1; $i <= 105; $i++) {
            $this->good(sprintf('Товар %03d', $i));
        }
        $this->good('Скрытый', ['is_published' => false]);
        $this->page(['per_page' => 10, 'page' => 11, 'view' => 'tree', 'show_filters' => 0])
            ->assertOk()->assertJsonPath('props.goods.total', 105)
            ->assertJsonPath('props.goods.current_page', 11)
            ->assertJsonPath('props.goods.last_page', 11)
            ->assertJsonPath('props.goods.from', 101)
            ->assertJsonPath('props.goods.to', 105)
            ->assertJsonCount(5, 'props.goods.data')
            ->assertJsonPath('props.goods.data.0.name', 'Товар 101')
            ->assertJsonPath('props.filters.view', 'tree')
            ->assertJsonPath('props.filters.show_filters', false);
        $this->page(['per_page' => 100])->assertJsonCount(100, 'props.goods.data');
        $this->page(['per_page' => 17, 'page' => 999])->assertJsonPath('props.goods.current_page', 7)->assertJsonCount(3, 'props.goods.data');
    }

    public function test_search_country_collection_availability_and_section_filters_intersect(): void
    {
        $country = Country::forceCreate(['name' => 'Россия', 'сodeISO' => 'RU']);
        $field = Field::create(['title' => 'Для пекарни', 'is_published' => true]);
        $section = CatalogNode::create(['name' => 'Орехи', 'slug' => 'nuts', 'is_published' => true]);
        $nested = CatalogNode::create(['name' => 'Арахис', 'parent_id' => $section->id, 'is_published' => true]);
        $target = $this->good('Арахис жареный', ['country_id' => $country->id]);
        $target->fields()->attach($field);
        CatalogNode::create(['name' => $target->name, 'parent_id' => $nested->id, 'entity_type' => 'good', 'entity_id' => $target->id]);
        // The warehouse state takes precedence over stale SEO availability.
        GoodSeo::create(['good_id' => $target->id, 'availability_status' => 'out_of_stock']);
        GoodStockAvailability::create(['good_id' => $target->id, 'is_in_stock' => true, 'checked_at' => now()]);
        $this->good('Арахис другой');
        $response = $this->page(['search' => 'АРАХИС', 'country_id' => $country->id, 'field_id' => $field->id,
            'node_id' => $section->id, 'availability' => 'in_stock'])->assertOk()
            ->assertJsonPath('props.goods.total', 1)->assertJsonPath('props.goods.data.0.id', $target->id)
            ->assertJsonPath('props.goods.data.0.catalog_node_id', $nested->id)
            ->assertJsonPath('props.goods.data.0.availability.status', 'in_stock')
            ->assertJsonPath('props.breadcrumbs.0.id', $section->id);
        $tree = collect($response->json('props.catalogTree'))->keyBy('id');
        $this->assertSame(1, $tree[$section->id]['goods_count']);
        $this->assertSame(1, $tree[$nested->id]['goods_count']);
        $this->page(['node_id' => 999999])->assertNotFound();
    }

    public function test_price_filter_and_sort_use_the_same_current_public_price_as_the_card(): void
    {
        $a = $this->good('Арахис');
        $b = $this->good('Миндаль');
        $without = $this->good('Без цены');
        $this->price($a, 250);
        $this->price($a, 1, ['code' => 'partner', 'name' => 'Партнёрская']);
        $this->price($a, 2, [], ['valid_to' => today()->subDay()]);
        $this->price($a, 3, [], ['valid_from' => today()->addDay()]);
        $this->price($a, 4, ['is_active' => false]);
        $this->price($a, 5, [], ['is_published' => false]);
        $this->price($a, 6, ['is_public' => false]);
        $this->price($a, 7, ['code' => 'wholesale', 'name' => 'Оптовая', 'sort_order' => 0]);
        $this->price($b, 100);
        $response = $this->page(['sort' => 'price_asc'])->assertOk()
            ->assertJsonPath('props.goods.data.0.id', $b->id)
            ->assertJsonPath('props.goods.data.1.id', $a->id)
            ->assertJsonPath('props.goods.data.1.public_purchase.price', 250)
            ->assertJsonPath('props.goods.data.2.id', $without->id)
            ->assertJsonPath('props.goods.data.2.public_purchase.price', null);
        $this->assertStringNotContainsString('staff-price-secret', $response->getContent());
        $response->assertJsonMissingPath('props.goods.data.0.price_type_values');
        $this->page(['sort' => 'price_desc'])->assertJsonPath('props.goods.data.0.id', $a->id)->assertJsonPath('props.goods.data.2.id', $without->id);
        $this->page(['price_min' => 200, 'price_max' => 300])->assertJsonPath('props.goods.total', 1)->assertJsonPath('props.goods.data.0.id', $a->id);
        $this->page(['price_max' => 150])->assertOk()->assertJsonPath('props.goods.total', 1)->assertJsonPath('props.goods.data.0.id', $b->id);
    }

    public function test_empty_results_keep_pagination_and_filters_usable(): void
    {
        $this->page(['search' => 'нет таких товаров', 'page' => 5])->assertOk()
            ->assertJsonCount(0, 'props.goods.data')->assertJsonPath('props.goods.total', 0)
            ->assertJsonPath('props.goods.current_page', 1)->assertJsonPath('props.goods.last_page', 1)
            ->assertJsonPath('props.filters.search', 'нет таких товаров');
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_query_options_are_rejected(array $query, string $key): void
    {
        $this->getJson('/g?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public static function invalidFilters(): array
    {
        return [
            [['per_page' => 9], 'per_page'], [['per_page' => 101], 'per_page'],
            [['per_page' => 'all'], 'per_page'], [['page' => -1], 'page'],
            [['sort' => 'internal_margin'], 'sort'], [['view' => 'admin'], 'view'],
            [['search' => ['unexpected']], 'search'], [['price_min' => -1], 'price_min'],
            [['price_min' => 300, 'price_max' => 100], 'price_max'],
        ];
    }

    private function page(array $query = [])
    {
        return $this->get('/g?'.http_build_query($query), ['X-Inertia' => 'true']);
    }

    private function good(string $name, array $attributes = []): Good
    {
        return Good::create(['name' => $name, 'is_published' => true, ...$attributes]);
    }

    private function price(Good $good, float $amount, array $type = [], array $attributes = []): void
    {
        $currency = Currency::where('code', 'RUB')->first() ?: Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $priceType = PriceType::create(['name' => 'Розничная', 'code' => 'retail-'.PriceType::count(), 'is_public' => true,
            'is_active' => true, 'sort_order' => 10, 'currency_id' => $currency->id, ...$type]);
        GoodPriceTypeValue::create(['good_id' => $good->id, 'price_type_id' => $priceType->id,
            'currency_id' => $currency->id, 'price_gross' => $amount, 'is_published' => true,
            'manual_comment' => 'staff-price-secret', ...$attributes]);
    }
}
