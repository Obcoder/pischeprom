<?php

namespace Tests\Feature;

use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodSeo;
use App\Models\GoodStockAvailability;
use App\Models\PriceType;
use App\Models\Product;
use App\Services\Goods\PublicGoodOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicClassPageTest extends TestCase
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
        Http::preventStrayRequests();
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
    }

    public function test_guide_selects_only_configured_published_sources_with_stable_unique_goods_and_no_catalog_mutation(): void
    {
        [$guide, $source] = $this->products();
        $other = Product::forceCreate(['id' => 202, 'rus' => 'Другой источник', 'is_published' => true]);
        config()->set('product-pages.pages.124.source_product_ids', [201, 202, 201]);
        $first = $this->good(75, 'А Скумбрия');
        $second = $this->good(104, 'Б Скумбрия');
        $draft = $this->good(95, 'Закрытая скумбрия', ['is_published' => false]);
        $unrelated = $this->good(172, 'Скумбрия с похожим названием');
        $source->goods()->attach([$first->id, $second->id, $draft->id]);
        $other->goods()->attach($first->id);
        $before = DB::table('good_product')->orderBy('good_id')->orderBy('product_id')->get()->toJson();
        $nodeCount = CatalogNode::count();

        $response = $this->page($guide->id)->assertOk()
            ->assertJsonPath('component', 'Products/Show')
            ->assertJsonPath('props.classPage.guide', 'mackerel')
            ->assertJsonCount(0, 'props.goods')
            ->assertJsonCount(2, 'props.classPage.goods')
            ->assertJsonPath('props.classPage.goods.0.id', $first->id)
            ->assertJsonPath('props.classPage.goods.1.id', $second->id)
            ->assertJsonMissingPath('props.classPage.inlineGoods.95')
            ->assertJsonMissingPath('props.classPage.inlineGoods.172');

        $this->assertStringNotContainsString($draft->name, $response->getContent());
        $this->assertStringNotContainsString($unrelated->name, $response->getContent());
        $this->assertSame($before, DB::table('good_product')->orderBy('good_id')->orderBy('product_id')->get()->toJson());
        $this->assertSame($nodeCount, CatalogNode::count());
        Http::assertNothingSent();
    }

    public function test_hidden_source_and_removed_goods_leave_an_empty_catalog_without_disabling_the_article(): void
    {
        [$guide, $source] = $this->products();
        $good = $this->good(75, 'Скумбрия');
        $source->goods()->attach($good);
        $this->page($guide->id)->assertOk()->assertJsonCount(1, 'props.classPage.goods');

        $source->update(['is_published' => false]);
        $this->page($guide->id)->assertOk()
            ->assertJsonPath('props.classPage.guide', 'mackerel')
            ->assertJsonCount(0, 'props.classPage.goods')
            ->assertJsonPath('props.classPage.seo.jsonLd.0.mainEntity.numberOfItems', 0)
            ->assertJsonMissingPath('props.classPage.inlineGoods.75');

        $source->update(['is_published' => true]);
        $good->update(['is_published' => false]);
        $this->page($guide->id)->assertOk()->assertJsonCount(0, 'props.classPage.goods')->assertJsonMissingPath('props.classPage.inlineGoods.75');
        $good->delete();
        $this->page($guide->id)->assertOk()->assertJsonCount(0, 'props.classPage.goods');
    }

    public function test_cards_and_article_links_follow_the_current_canonical_slug(): void
    {
        [$guide, $source] = $this->products();
        $good = $this->good(75, 'Скумбрия');
        $source->goods()->attach($good);
        $seo = GoodSeo::create(['good_id' => $good->id, 'is_active' => true, 'slug_override' => 'current-canonical']);
        $url = route('public.goods.show', ['good' => 'current-canonical']);
        $this->page($guide->id)->assertOk()
            ->assertJsonPath('props.classPage.goods.0.url', $url)
            ->assertJsonPath('props.classPage.inlineGoods.75.url', $url)
            ->assertJsonPath('props.classPage.seo.jsonLd.0.mainEntity.itemListElement.0.url', $url);

        $seo->update(['slug_override' => null]);
        $good->update(['slug' => 'renamed-mackerel']);
        $url = route('public.goods.show', ['good' => 'renamed-mackerel']);
        $this->page($guide->id)->assertOk()
            ->assertJsonPath('props.classPage.goods.0.url', $url)
            ->assertJsonPath('props.classPage.inlineGoods.75.url', $url);
    }

    public function test_minimal_dto_uses_published_images_confirmed_attributes_and_the_same_offer_and_stock_as_the_good_landing(): void
    {
        [$guide, $source] = $this->products();
        $country = Country::forceCreate(['name' => 'Китай', 'сodeISO' => 'CN']);
        $good = $this->good(75, 'Скумбрия', [
            'denominator' => 12.5, 'country_id' => $country->id,
            'incoming_code' => 'private-incoming-code', 'ava_thumb' => '/avatar.jpg',
        ]);
        $source->goods()->attach($good);
        $this->price($good, 200);
        $this->price($good, 89999, ['code' => 'partner', 'name' => 'Партнёрская']);
        $this->price($good, 88888, ['code' => 'private-purchase', 'is_public' => false]);
        $this->price($good, 87777, ['code' => 'expired'], ['valid_to' => today()->subDay()]);
        GoodStockAvailability::create(['good_id' => $good->id, 'is_in_stock' => true]);
        GoodMedia::create(['good_id' => $good->id, 'type' => 'image', 'path' => 'private-image.jpg', 'url' => '/private-image.jpg', 'is_ava' => true, 'is_published' => false]);
        GoodMedia::create(['good_id' => $good->id, 'type' => 'image', 'path' => 'public-image.jpg', 'url' => '/public-image.jpg', 'thumb_url' => '/public-thumb.jpg', 'alt' => 'Фотография партии', 'is_published' => true]);

        $response = $this->page($guide->id)->assertOk();
        $card = $response->json('props.classPage.goods.0');
        $landing = $this->get(route('public.goods.show', $good->slug), ['X-Inertia' => 'true'])->assertOk();
        $this->assertSame(array_keys($card), ['id', 'name', 'url', 'image', 'image_alt', 'attributes', 'offer', 'availability']);
        $this->assertSame('/public-thumb.jpg', $card['image']);
        $this->assertSame('Фотография партии', $card['image_alt']);
        $this->assertSame([
            ['label' => 'Страна происхождения', 'value' => 'Китай'],
            ['label' => 'Фасовка', 'value' => '12,5 кг / упаковка'],
        ], $card['attributes']);
        $publicPurchase = $landing->json('props.publicPurchase');
        unset($publicPurchase['max_url']);
        $this->assertSame($publicPurchase, $card['offer']);
        $this->assertSame($landing->json('props.availability'), $card['availability']);
        foreach (['89999', '88888', '87777', 'internal-price-comment', 'private-incoming-code', 'private-image.jpg', 'calculation_id'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
    }

    public function test_missing_offer_and_unconfirmed_country_are_not_invented(): void
    {
        [$guide, $source] = $this->products();
        $good = $this->good(75, 'Скумбрия Перу 500+');
        $source->goods()->attach($good);
        $this->price($good, 500, ['is_public' => false]);

        $this->page($guide->id)->assertOk()
            ->assertJsonPath('props.classPage.goods.0.image', null)
            ->assertJsonPath('props.classPage.goods.0.offer.price', null)
            ->assertJsonPath('props.classPage.goods.0.offer.package_weight', null)
            ->assertJsonPath('props.classPage.goods.0.availability.status', 'on_request')
            ->assertJsonCount(0, 'props.classPage.goods.0.attributes');
    }

    public function test_other_classes_keep_their_original_goods_and_hidden_classes_remain_unavailable(): void
    {
        [$guide, $source] = $this->products();
        $good = $this->good(75, 'Скумбрия');
        $source->goods()->attach($good);
        $this->page($source->id)->assertOk()
            ->assertJsonPath('component', 'Products/Show')
            ->assertJsonPath('props.classPage', null)
            ->assertJsonPath('props.goods.0.id', $good->id)
            ->assertJsonPath('props.product.rus', 'Скумбрия замороженная')
            ->assertJsonPath('props.seo.canonical', route('shop.products.show', $source));
        $guide->update(['is_published' => false]);
        $this->page($guide->id)->assertNotFound();
    }

    public function test_guide_metadata_matches_visible_items_and_actual_category_structure(): void
    {
        [$guide, $source] = $this->products();
        $good = $this->good(75, 'Скумбрия');
        $source->goods()->attach($good);
        $response = $this->page($guide->id)->assertOk()
            ->assertJsonPath('props.seo.h1', 'Скумбрия')
            ->assertJsonPath('props.seo.canonical', route('shop.products.show', $guide))
            ->assertJsonPath('props.seo.robots', 'index,follow')
            ->assertJsonPath('props.seo.jsonLd.0.@type', 'CollectionPage')
            ->assertJsonPath('props.seo.jsonLd.0.mainEntity.@type', 'ItemList')
            ->assertJsonPath('props.seo.jsonLd.0.mainEntity.numberOfItems', 1)
            ->assertJsonPath('props.seo.jsonLd.1.itemListElement.1.name', 'Рыба')
            ->assertJsonPath('props.classPage.breadcrumbs.1.url', route('category.show', $guide->category->slug));
        $this->assertStringNotContainsString('Offer', json_encode($response->json('props.seo.jsonLd')));

        $guide->category->update(['is_published' => false]);
        $this->page($guide->id)->assertOk()->assertJsonCount(2, 'props.classPage.breadcrumbs');
    }

    public function test_public_offers_are_batched_and_match_single_good_resolution(): void
    {
        $goods = collect(range(1, 8))->map(fn ($id) => $this->good($id, 'Товар '.$id, ['denominator' => 10]));
        foreach ($goods as $good) {
            $this->price($good, 100 + $good->id, ['code' => 'retail-'.$good->id]);
        }
        $service = app(PublicGoodOffer::class);
        $expected = $goods->mapWithKeys(fn (Good $good) => [$good->id => $service->for($good)]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $actual = $service->forMany($goods);
            $this->assertSame($expected->all(), $actual->all());
            $this->assertCount(4, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    private function products(): array
    {
        $category = Category::create(['name' => 'Рыба', 'is_published' => true]);

        return [
            Product::forceCreate(['id' => 124, 'rus' => 'Скумбрия', 'category_id' => $category->id, 'is_published' => true]),
            Product::forceCreate(['id' => 201, 'rus' => 'Скумбрия замороженная', 'category_id' => $category->id, 'is_published' => true]),
        ];
    }

    private function good(int $id, string $name, array $attributes = []): Good
    {
        return Good::forceCreate(['id' => $id, 'name' => $name, 'is_published' => true, ...$attributes]);
    }

    private function price(Good $good, float $value, array $typeAttributes = [], array $attributes = []): void
    {
        $currency = Currency::where('code', 'RUB')->first() ?: Currency::forceCreate(['name' => 'Рубль', 'code' => 'RUB']);
        $type = PriceType::create([
            'name' => 'Розничная', 'code' => 'retail', 'currency_id' => $currency->id,
            'is_public' => true, 'is_active' => true, ...$typeAttributes,
        ]);
        GoodPriceTypeValue::create([
            'good_id' => $good->id, 'price_type_id' => $type->id, 'currency_id' => $currency->id,
            'price_gross' => $value, 'is_published' => true, 'manual_comment' => 'internal-price-comment', ...$attributes,
        ]);
    }

    private function page(int $id)
    {
        return $this->get(route('shop.products.show', ['product' => $id]), ['X-Inertia' => 'true']);
    }
}
