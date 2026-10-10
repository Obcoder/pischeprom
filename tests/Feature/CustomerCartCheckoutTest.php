<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\User;
use App\Services\Orders\CustomerOrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerCartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
    }

    public function test_public_product_offer_creates_a_cart_order_with_public_prices_and_delivery(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldReceive('notify')->once();
        $good = Good::query()->create(['name' => 'Арахис', 'denominator' => 25, 'is_published' => true]);
        $this->price($good, 240);
        $this->price($good, 100, ['code' => 'partner', 'name' => 'Партнёрская']);
        $this->price($good, 50, ['code' => 'purchase', 'is_public' => false]);
        $this->price($good, 999, ['code' => 'expired', 'sort_order' => 1], ['valid_to' => today()->subDay()]);
        $this->price($good, 888, ['code' => 'future', 'sort_order' => 1], ['valid_from' => today()->addDay()]);
        $this->price($good, 777, ['code' => 'inactive', 'is_active' => false, 'sort_order' => 1]);
        $this->price($good, 666, ['code' => 'hidden', 'sort_order' => 1], ['is_published' => false]);

        $offer = $this->get(route('public.goods.show', $good->slug), ['X-Inertia' => 'true'])
            ->assertOk()->json('props.publicPurchase');
        $this->assertSame(240, $offer['price']);
        $box = Good::query()->create([
            'name' => 'Сахар в коробках', 'is_published' => true,
            'measure_id' => Measure::query()->create(['name' => 'коробка'])->id, 'unit_weight_kg' => 10,
        ]);
        $this->price($box, 1200, ['code' => 'retail-box']);
        $user = User::factory()->create(['type' => 'customer']);

        $response = $this->actingAs($user)->postJson('/orders', $this->payload([
            [
                'good_id' => $good->id, 'quantity' => 2.125,
                'measure_id' => $offer['measure_id'], 'measurement' => $offer['measurement'],
                'pricing_context' => 'public', 'price_gross' => 1,
            ],
            $this->item($box, 2),
        ]))->assertCreated()
            ->assertJsonPath('order.total_amount', 2910)
            ->assertJsonPath('order.total_weight', 22.125)
            ->assertJsonPath('order.currency_code', 'RUB')
            ->assertJsonPath('order.status', OrderStatus::OPEN)
            ->assertJsonPath('redirect', route('dashboard'));

        $order = Order::query()->findOrFail($response->json('order.id'));
        $this->assertSame($user->id, $order->created_by_user_id);
        $this->assertCount(2, $order->items);
        $this->assertSame(2.125, $order->items[0]->quantity);
        $this->assertSame(240.0, $order->items[0]->price_gross);
        $this->assertSame('кг', $order->items[0]->measurement()['unit_label']);
        $this->assertSame('retail', $order->items[0]->snapshot['price_type']['code']);
        $this->assertSame('Лесная, 10, офис 305', $order->buildings->sole()->address_with_apartment);
        $this->assertSame('+79991234567', $order->contactTelephone->number);
        $this->get('/dashboard', ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.orders.0.id', $order->id)
            ->assertJsonPath('props.orders.0.total_amount', 2910);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_cart_rechecks_current_public_price_when_the_order_is_submitted(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldReceive('notify')->once();
        $good = Good::query()->create(['name' => 'Товар', 'is_published' => true]);
        $price = $this->price($good, 100);
        $item = $this->item($good, 3);
        $item['price_gross'] = 100;
        $price->update(['price_gross' => 120]);

        $this->actingAs(User::factory()->create(['type' => 'customer']))
            ->postJson('/orders', $this->payload([$item]))->assertCreated()
            ->assertJsonPath('order.total_amount', 360);
        $this->assertSame(120.0, Order::query()->sole()->items->sole()->price_gross);
    }

    public function test_public_cart_with_only_private_prices_creates_an_order_by_request(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldReceive('notify')->once();
        $good = Good::query()->create(['name' => 'Без публичной цены', 'is_published' => true]);
        $this->price($good, 900, ['code' => 'purchase', 'is_public' => false]);
        $this->price($good, 800, ['code' => 'partner']);
        $this->actingAs(User::factory()->create(['type' => 'customer']))
            ->postJson('/orders', $this->payload([$this->item($good)]))->assertCreated();

        $line = Order::query()->sole()->items->sole();
        $this->assertNull($line->price_gross);
        $this->assertNull($line->line_total);
        $this->assertNull($line->snapshot['price_type']);
    }

    public function test_existing_catalog_cart_keeps_current_partner_prices_without_using_expired_prices(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldReceive('notify')->once();
        $good = Good::query()->create(['name' => 'Товар', 'is_published' => true]);
        $this->price($good, 240);
        $this->price($good, 190, ['code' => 'partner']);
        $this->price($good, 1, ['code' => 'partner-old', 'sort_order' => 1], ['valid_to' => today()->subDay()]);
        $this->actingAs(User::factory()->create(['type' => 'customer']))
            ->postJson('/orders', $this->payload([['good_id' => $good->id, 'quantity' => 2]]))
            ->assertCreated()->assertJsonPath('order.total_amount', 380);
    }

    public function test_checkout_rejects_unpublished_goods_without_creating_an_order(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldNotReceive('notify');
        $good = Good::query()->create(['name' => 'Снятый с продажи', 'is_published' => true]);
        $item = $this->item($good);
        $good->update(['is_published' => false]);
        $this->actingAs(User::factory()->create(['type' => 'customer']))
            ->postJson('/orders', $this->payload([$item]))->assertUnprocessable()
            ->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('buildings', 0);
    }

    public function test_checkout_rejects_stale_units_and_weight_snapshots_with_an_item_error(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldNotReceive('notify');
        $good = Good::query()->create(['name' => 'Товар', 'is_published' => true]);
        $item = $this->item($good);
        $good->update(['measure_id' => Measure::query()->create(['name' => 'коробка'])->id, 'unit_weight_kg' => 10]);
        $this->actingAs(User::factory()->create(['type' => 'customer']))
            ->postJson('/orders', $this->payload([$item]))->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.measure_id');
        $item = $this->item($good);
        $good->update(['unit_weight_kg' => 12]);
        $this->postJson('/orders', $this->payload([$item]))->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.measurement');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('buildings', 0);
    }

    public function test_checkout_quantity_limits_match_the_cart_and_invalid_quantities_are_rejected(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldReceive('notify')->once();
        $good = Good::query()->create(['name' => 'Товар', 'is_published' => true]);
        $this->actingAs(User::factory()->create(['type' => 'customer']));
        foreach ([0, -1, 0.0001, 10000] as $quantity) {
            $this->postJson('/orders', $this->payload([$this->item($good, $quantity)]))
                ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        }
        $this->assertDatabaseCount('orders', 0);
        $this->postJson('/orders', $this->payload([$this->item($good, 9999)]))->assertCreated();
        $this->assertSame(9999.0, Order::query()->sole()->items->sole()->quantity);
    }

    public function test_checkout_requires_login_and_a_nonempty_cart(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldNotReceive('notify');
        $this->postJson('/orders', $this->payload([]))->assertUnauthorized();
        $this->actingAs(User::factory()->create(['type' => 'customer']))
            ->postJson('/orders', $this->payload([]))->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    private function item(Good $good, float $quantity = 1): array
    {
        return [
            'good_id' => $good->id, 'quantity' => $quantity,
            'measure_id' => $good->measure_id, 'measurement' => $good->measurement(),
            'pricing_context' => 'public',
        ];
    }

    private function payload(array $items): array
    {
        return [
            'items' => $items, 'delivery_address' => 'Лесная, 10',
            'delivery_apartment_number' => '305', 'delivery_apartment_type' => 'office',
            'preferred_delivery_time' => 'Завтра с 10 до 14', 'customer_phone' => '8 (999) 123-45-67',
        ];
    }

    private function price(Good $good, float $amount, array $typeAttributes = [], array $attributes = []): GoodPriceTypeValue
    {
        $currency = Currency::query()->where('code', 'RUB')->first()
            ?: Currency::query()->forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $type = PriceType::query()->create([
            'name' => 'Розница', 'code' => 'retail', 'currency_id' => $currency->id,
            'is_public' => true, 'is_active' => true, 'sort_order' => 10,
            ...$typeAttributes,
        ]);

        return GoodPriceTypeValue::query()->create([
            'good_id' => $good->id, 'price_type_id' => $type->id, 'currency_id' => $currency->id,
            'price_gross' => $amount, 'is_published' => true,
            ...$attributes,
        ]);
    }
}
