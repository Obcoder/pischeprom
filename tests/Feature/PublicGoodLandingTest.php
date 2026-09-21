<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodSeo;
use App\Models\PriceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicGoodLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set([
            'app.asset_url' => 'https://assets.example.test',
            'services.max.bot_url' => 'https://max.ru/public_shop_bot',
            'services.max.bot_username' => null,
            'services.max.access_token' => 'secret-must-stay-server-side',
        ]);
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
    }

    public function test_guest_landing_exposes_the_current_public_offer_and_product_max_link(): void
    {
        $good = $this->good();
        $this->price($good, 240, [], ['manual_comment' => 'internal-price-comment-sentinel']);
        $this->price($good, 81111.11, ['code' => 'partner', 'name' => 'Партнерская', 'is_public' => true]);
        $this->price($good, 82222.22, ['code' => 'purchase', 'name' => 'Закупочная', 'is_public' => false]);
        $this->price($good, 83333.33, ['code' => 'retail-inactive', 'is_active' => false]);
        $this->price($good, 84444.44, ['code' => 'retail-expired'], ['valid_to' => today()->subDay()]);
        $this->price($good, 85555.55, ['code' => 'retail-future'], ['valid_from' => today()->addDay()]);
        $this->price($good, 86666.66, ['code' => 'retail-unpublished'], ['is_published' => false]);

        $related = $this->good('Другой товар');
        $this->price($related, 87777.77, ['code' => 'related-dealer', 'name' => 'Дилерская', 'is_public' => false]);
        GoodSeo::query()->create([
            'good_id' => $good->id,
            'is_active' => true,
            'structured_data' => [
                '@type' => 'Product',
                'offers' => ['@type' => 'Offer', 'price' => '81111.11'],
            ],
        ]);

        $response = $this->get(route('public.goods.show', $good->slug), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('component', 'Goods/Show')
            ->assertJsonPath('props.good.id', $good->id)
            ->assertJsonPath('props.publicPurchase.price', 240)
            ->assertJsonPath('props.publicPurchase.package_price', 1200)
            ->assertJsonPath('props.publicPurchase.package_weight', 5)
            ->assertJsonPath('props.publicPurchase.price_unit', 'kg')
            ->assertJsonPath('props.publicPurchase.currency_code', 'RUB')
            ->assertJsonPath('props.publicPurchase.max_url', 'https://max.ru/public_shop_bot?start=good_'.$good->id)
            ->assertJsonPath('props.seo.price', 240)
            ->assertJsonPath('props.seo.currency', 'RUB')
            ->assertJsonPath('props.seo.jsonLd.0.offers.price', '240.00')
            ->assertJsonMissingPath('props.good.price_type_values')
            ->assertJsonMissingPath('props.relatedGoods.0.price_type_values');

        foreach (['81111.11', '82222.22', '83333.33', '84444.44', '85555.55', '86666.66', '87777.77', 'internal-price-comment-sentinel', 'secret-must-stay-server-side'] as $privateValue) {
            $this->assertFalse(str_contains($response->getContent(), $privateValue), 'Private data leaked into the public payload: '.$privateValue);
        }
        Http::assertNothingSent();
    }

    public function test_landing_does_not_invent_a_price_when_only_private_prices_exist(): void
    {
        $good = $this->good();
        $this->price($good, 250, ['code' => 'purchase', 'name' => 'Закупочная', 'is_public' => false]);

        $this->get(route('public.goods.show', $good->slug), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.publicPurchase.price', null)
            ->assertJsonPath('props.publicPurchase.package_price', null)
            ->assertJsonPath('props.seo.price', null);
    }

    public function test_active_product_alias_redirects_to_the_canonical_landing(): void
    {
        $good = $this->good();
        GoodSeo::query()->create([
            'good_id' => $good->id,
            'slug_override' => 'canonical-product',
            'h1' => 'Заголовок товара',
            'is_active' => true,
        ]);

        $canonical = route('public.goods.show', 'canonical-product');
        $this->get(route('public.goods.show', $good->slug))
            ->assertStatus(301)->assertRedirect($canonical);
        $this->get($canonical, ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.good.id', $good->id)
            ->assertJsonPath('props.seo.h1', 'Заголовок товара')
            ->assertJsonPath('props.seo.canonical', $canonical)
            ->assertJsonPath('props.seo.jsonLd.0.url', $canonical);
    }

    public function test_unpublished_products_are_hidden_by_slug_and_alias(): void
    {
        $good = $this->good();
        $good->update(['is_published' => false]);
        GoodSeo::query()->create([
            'good_id' => $good->id,
            'slug_override' => 'hidden-product',
            'is_active' => true,
        ]);

        $this->get(route('public.goods.show', $good->slug))->assertNotFound();
        $this->get(route('public.goods.show', 'hidden-product'))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_landing_remains_available_without_a_configured_max_bot(): void
    {
        config()->set([
            'services.max.bot_url' => null,
            'services.max.bot_username' => null,
            'services.max.access_token' => null,
        ]);
        $good = $this->good();

        $this->get(route('public.goods.show', $good->slug), ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonPath('props.publicPurchase.max_url', null);
        Http::assertNothingSent();
    }

    private function good(string $name = 'Арахис сырой, 5 кг'): Good
    {
        return Good::query()->create([
            'name' => $name,
            'denominator' => 5,
            'is_published' => true,
        ]);
    }

    private function price(Good $good, float $value, array $typeAttributes = [], array $attributes = []): void
    {
        $currency = Currency::query()->where('code', 'RUB')->first()
            ?: Currency::query()->forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $type = PriceType::query()->create([
            'name' => 'Розничная',
            'code' => 'retail',
            'currency_id' => $currency->id,
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 10,
            ...$typeAttributes,
        ]);
        GoodPriceTypeValue::query()->create([
            'good_id' => $good->id,
            'price_type_id' => $type->id,
            'currency_id' => $currency->id,
            'price_gross' => $value,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
