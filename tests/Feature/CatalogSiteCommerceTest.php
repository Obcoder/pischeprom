<?php

namespace Tests\Feature;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\CatalogSiteDomain;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Field;
use App\Models\Good;
use App\Models\GoodOfTheDay;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodSeo;
use App\Models\GoodStockAlert;
use App\Models\HomeBanner;
use App\Models\MaxChat;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\User;
use App\Services\Goods\GoodStockAlertMessenger;
use App\Services\MaxMessengerService;
use App\Services\Orders\CustomerOrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogSiteCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
    }

    public function test_foreign_goods_cannot_receive_inquiries_stock_subscriptions_or_orders(): void
    {
        $food = $this->site('food.test', 'Пищевые товары');
        $fabric = $this->site('fabric.test', 'Ткани');
        $payload = [
            'request_token' => (string) Str::uuid(), 'kind' => 'order', 'quantity' => 1,
            'customer_name' => 'Покупатель', 'customer_email' => 'buyer@example.com',
            'preferred_contact' => 'email', 'consent' => true,
        ];

        $this->postJson('http://food.test/g/'.$fabric['good']->id.'/inquiries', $payload)->assertNotFound();
        $this->postJson('http://food.test/g/'.$fabric['good']->id.'/stock-alerts')->assertNotFound();
        $this->postJson('http://unknown.test/g/'.$food['good']->id.'/inquiries', $payload)->assertNotFound();
        $this->assertDatabaseCount('good_inquiries', 0);
        $this->assertDatabaseCount('good_stock_alerts', 0);

        $this->mock(CustomerOrderNotificationService::class)->shouldNotReceive('notify');
        $user = User::factory()->create(['type' => 'customer']);
        $this->actingAs($user)->postJson('http://food.test/orders', [
            'items' => [['good_id' => $food['good']->id], ['good_id' => $fabric['good']->id]],
            'delivery_address' => 'Складская, 1', 'preferred_delivery_time' => 'После 12',
            'customer_phone' => '+79991234567',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        Queue::assertNothingPushed();
    }

    public function test_inquiry_for_current_site_still_creates_customer_order(): void
    {
        $food = $this->site('food.test', 'Пищевые товары');
        $this->site('fabric.test', 'Ткани');
        $this->postJson('http://food.test/g/'.$food['good']->id.'/inquiries', [
            'request_token' => (string) Str::uuid(), 'kind' => 'order', 'quantity' => 2,
            'customer_name' => 'Покупатель', 'customer_email' => 'buyer@example.com',
            'preferred_contact' => 'email', 'consent' => true,
        ])->assertCreated();
        $this->assertDatabaseHas('order_items', ['good_id' => $food['good']->id, 'quantity' => 2]);
    }

    public function test_home_published_feed_and_legacy_pages_only_expose_current_site(): void
    {
        $food = $this->site('food.test', 'Пищевые товары');
        $fabric = $this->site('fabric.test', 'Ткани');
        GoodOfTheDay::create(['date' => today()->toDateString(), 'good_id' => $fabric['good']->id]);

        $this->get('http://food.test/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('categories', 1)->where('categories.0.id', $food['category']->id)
            ->where('goodsCount', 1)->where('productsCount', 1)
            ->where('goodOfTheDay.good.id', $food['good']->id)
            ->has('featuredGoods', 1)->where('featuredGoods.0.id', $food['good']->id)
            ->has('homeGoodsModule.table_of_contents', 1)
            ->where('homeGoodsModule.table_of_contents.0.id', $food['category']->id));
        $this->assertDatabaseHas('good_of_the_days', ['good_id' => $fabric['good']->id]);

        $this->getJson('http://food.test/goods/published')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $food['good']->id);
        $this->get('http://food.test/p/'.$fabric['product']->id)->assertNotFound();
        $this->get('http://food.test/'.rawurlencode('категория').'/'.$fabric['category']->slug)->assertNotFound();
        $this->get('http://food.test/p/'.$food['product']->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('goods', 1)->where('goods.0.id', $food['good']->id));
        $this->get('http://food.test/sitemap.xml')->assertOk()
            ->assertSee('/g/'.$food['good']->slug, false)->assertDontSee($fabric['good']->slug, false);
    }

    public function test_field_collection_uses_server_pagination_and_scoped_counts(): void
    {
        $food = $this->site('food.test', 'Пищевые товары');
        $fabric = $this->site('fabric.test', 'Ткани');
        $field = Field::create(['title' => 'Подборка', 'is_published' => true]);
        $field->goods()->attach([$food['good']->id, $fabric['good']->id]);
        $foreign = Field::create(['title' => 'Чужая подборка', 'is_published' => true]);
        $foreign->goods()->attach($fabric['good']->id);
        $url = 'http://food.test/'.rawurlencode('подборки').'/';
        $this->get($url.$field->slug.'?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Goods')->where('field.goods_count', 1)
            ->has('goods.data', 1)->where('goods.data.0.id', $food['good']->id)
            ->where('goods.total', 1)->where('goods.per_page', 10));
        $this->get($url.$foreign->slug)->assertNotFound();
    }

    public function test_yandex_feed_and_linked_banners_do_not_advertise_another_site(): void
    {
        $food = $this->site('food.test', 'Пищевые товары');
        $fabric = $this->site('fabric.test', 'Ткани');
        $currency = Currency::where('code', 'RUB')->first() ?: Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $currency->id,
            'is_public' => true, 'is_active' => true]);
        foreach ([$food, $fabric] as $site) {
            $site['good']->seo->update(['include_in_yandex_feed' => true]);
            GoodPriceTypeValue::create(['good_id' => $site['good']->id, 'price_type_id' => $type->id,
                'currency_id' => $currency->id, 'price_gross' => 100, 'is_published' => true]);
        }
        $this->get('http://food.test/yandex-feed.xml')->assertOk()
            ->assertSee('<url>http://food.test</url>', false)
            ->assertSee('/g/'.$food['good']->slug, false)->assertDontSee($fabric['good']->slug, false);

        HomeBanner::query()->delete();
        HomeBanner::create(['title' => 'Ткани', 'slot_number' => 1, 'good_id' => $fabric['good']->id,
            'is_published' => true]);
        $banner = HomeBanner::create(['title' => 'Продукты', 'slot_number' => 2, 'good_id' => $food['good']->id,
            'is_published' => true]);
        $this->get('http://food.test/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('homeBannerFeed.desktop.0', null)->where('homeBannerFeed.desktop.1.id', $banner->id));
    }

    public function test_stock_notification_keeps_the_subscription_site_and_resolves_the_current_slug(): void
    {
        $this->site('food.test', 'Пищевые товары');
        $fabric = $this->site('fabric.test', 'Ткани');
        config()->set(['services.max.access_token' => 'test-token', 'services.max.bot_username' => 'test_bot']);
        $this->postJson('https://fabric.test/g/'.$fabric['good']->id.'/stock-alerts')->assertCreated();
        $alert = GoodStockAlert::query()->sole();
        $this->assertSame('https://fabric.test', $alert->site_url);
        $fabric['good']->seo->update(['slug_override' => 'current-fabric']);
        $chat = MaxChat::create(['chat_id' => '9001', 'user_id' => '7001', 'is_active' => true]);
        $alert->update(['max_chat_id' => $chat->id]);

        URL::forceRootUrl('https://food.test');
        $max = $this->mock(MaxMessengerService::class);
        $max->shouldReceive('sendMessage')->once()->withArgs(fn (array $target, string $text, array $payload): bool => $payload['attachments'][0]['payload']['buttons'][0][0]['url'] === 'https://fabric.test/g/current-fabric')
            ->andReturn(['ok' => true, 'data' => [], 'error' => null]);
        $max->shouldReceive('sendMessage')->once()->withArgs(fn (array $target, string $text, array $payload): bool => $payload['attachments'][0]['payload']['buttons'][0][0]['url'] === 'https://food.test/g/current-fabric')
            ->andReturn(['ok' => true, 'data' => [], 'error' => null]);
        try {
            app(GoodStockAlertMessenger::class)->sendAvailable($alert);
            // Subscriptions created before site origins were stored retain the
            // established canonical URL from the worker's application origin.
            $alert->site_url = null;
            app(GoodStockAlertMessenger::class)->sendAvailable($alert);
        } finally {
            URL::forceRootUrl(null);
        }
        Http::assertNothingSent();
    }

    private function site(string $host, string $name): array
    {
        $domainLevel = CatalogLevel::firstOrCreate(['name' => 'Домен магазина'], ['entity_type' => 'custom', 'is_domain' => true]);
        $categoryLevel = CatalogLevel::firstOrCreate(['name' => 'Категория магазина'], ['entity_type' => 'category']);
        $productLevel = CatalogLevel::firstOrCreate(['name' => 'Класс магазина'], ['entity_type' => 'product']);
        $goodLevel = CatalogLevel::firstOrCreate(['name' => 'Товар магазина'], ['entity_type' => 'good']);
        $domain = CatalogNode::create(['level_id' => $domainLevel->id, 'name' => $name, 'is_published' => true]);
        CatalogSiteDomain::create(['catalog_node_id' => $domain->id, 'hostname' => $host]);
        $category = Category::create(['name' => $name.' категория', 'is_published' => true]);
        $categoryNode = CatalogNode::create(['level_id' => $categoryLevel->id, 'parent_id' => $domain->id,
            'entity_type' => 'category', 'entity_id' => $category->id, 'name' => $category->name, 'is_published' => true]);
        $product = Product::create(['rus' => $name.' класс', 'category_id' => $category->id, 'is_published' => true]);
        $productNode = CatalogNode::create(['level_id' => $productLevel->id, 'parent_id' => $categoryNode->id,
            'entity_type' => 'product', 'entity_id' => $product->id, 'name' => $product->rus, 'is_published' => true]);
        $good = Good::create(['name' => $name.' товар', 'is_published' => true]);
        CatalogNode::create(['level_id' => $goodLevel->id, 'parent_id' => $productNode->id,
            'entity_type' => 'good', 'entity_id' => $good->id, 'name' => $good->name, 'is_published' => true]);
        $good->products()->attach($product->id);
        $category->goods()->attach($good->id);
        GoodSeo::create(['good_id' => $good->id, 'is_active' => true, 'include_in_sitemap' => true]);

        return compact('domain', 'category', 'product', 'good');
    }
}
