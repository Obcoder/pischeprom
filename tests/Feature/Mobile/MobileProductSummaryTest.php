<?php

namespace Tests\Feature\Mobile;

use App\Models\Entity;
use App\Models\Good;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\Sale;
use App\Models\User;
use App\Services\Orders\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileProductSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private Entity $buyer;

    private Good $good;

    private Measure $measure;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 11)->startOfDay()->addHours(12));
        $this->employee = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->employee->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->withToken($this->employee->createToken('mobile:products', ['mobile:orders'], now()->addHour())->plainTextToken);
        $this->buyer = Entity::query()->create(['name' => 'Покупатель']);
        $this->measure = Measure::query()->firstOrCreate(['name' => 'кг']);
        $this->good = Good::query()->create(['name' => 'Сахар', 'measure_id' => $this->measure->id]);
    }

    public function test_products_sum_quantities_and_count_distinct_orders_for_each_fulfillment_state(): void
    {
        $first = $this->order(1.125);
        $this->line($first, 2.2);
        $this->order(0.675);
        $ready = $this->order(3);
        $other = Good::query()->create(['name' => 'Какао', 'measure_id' => $this->measure->id]);
        $this->line($ready, 4, $other);
        $this->prepare($ready);

        $response = $this->getJson('/api/mobile/v1/products')->assertOk()
            ->assertJsonPath('meta.delivery_date', '2026-10-11')
            ->assertJsonPath('meta.awaiting_orders_count', 2)
            ->assertJsonPath('meta.ready_orders_count', 1)
            ->assertJsonCount(2, 'data');
        $products = collect($response->json('data'))->keyBy('good_id');
        $this->assertSame(['Какао', 'Сахар'], $products->pluck('name')->all());
        $this->assertEquals(4, $products[$this->good->id]['awaiting_quantity']);
        $this->assertEquals(3, $products[$this->good->id]['ready_quantity']);
        $this->assertSame(2, $products[$this->good->id]['awaiting_orders_count']);
        $this->assertSame(1, $products[$this->good->id]['ready_orders_count']);
        $this->assertEquals(0, $products[$other->id]['awaiting_quantity']);
        $this->assertEquals(4, $products[$other->id]['ready_quantity']);
        $this->assertSame('кг', $products[$this->good->id]['measure_name']);
        $this->assertCount(2, $products->pluck('key')->unique());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_delivery_day_defaults_to_today_and_can_select_yesterday_tomorrow_or_a_calendar_date(): void
    {
        $this->order(1, '2026-10-10');
        $today = $this->order(2);
        $today->update(['submitted_at' => '2025-01-01 08:00:00']);
        $this->order(3, '2026-10-12');
        $this->order(4, '2028-02-29');
        $this->order(999, null);

        foreach ([
            ['', '2026-10-11', 2],
            ['?delivery_date=2026-10-10', '2026-10-10', 1],
            ['?delivery_date=2026-10-12', '2026-10-12', 3],
            ['?delivery_date=2028-02-29', '2028-02-29', 4],
        ] as [$query, $date, $quantity]) {
            $this->getJson('/api/mobile/v1/products'.$query)->assertOk()
                ->assertJsonPath('meta.delivery_date', $date)
                ->assertJsonPath('meta.awaiting_orders_count', 1)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.awaiting_quantity', $quantity);
        }
        $this->getJson('/api/mobile/v1/products?delivery_date=2026-10-13')->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => ['delivery_date' => '2026-10-13', 'awaiting_orders_count' => 0, 'ready_orders_count' => 0],
            ]);
    }

    public function test_summary_includes_all_orders_beyond_list_pagination_and_aggregation_batches(): void
    {
        for ($index = 0; $index < 205; $index++) {
            $this->order(0.1);
        }

        $this->getJson('/api/mobile/v1/products?per_page=1&page=2')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.awaiting_quantity', 20.5)
            ->assertJsonPath('data.0.awaiting_orders_count', 205)
            ->assertJsonPath('meta.awaiting_orders_count', 205);
    }

    public function test_closed_deferred_cancelled_and_shipped_orders_do_not_contribute(): void
    {
        $this->order(2);
        foreach ([OrderStatus::CLOSED, OrderStatus::DEFERRED] as $status) {
            $this->order(100)->update([
                'order_status_id' => OrderStatus::query()->where('code', $status)->sole()->id,
            ]);
        }
        $cancelled = OrderStatus::query()->create(['code' => 'cancelled', 'name' => 'Отменён', 'is_closed' => true]);
        $this->order(100)->update(['order_status_id' => $cancelled->id]);
        $this->order(100)->update(['closed_at' => now()]);
        $sale = Sale::query()->create(['entity_id' => $this->buyer->id, 'date' => '2026-10-11', 'total' => 10000]);
        $this->order(100)->forceFill(['shipped_sale_id' => $sale->id])->save();
        $this->order(100)->forceFill(['shipped_at' => now()])->save();

        $this->getJson('/api/mobile/v1/products')->assertOk()
            ->assertJsonPath('data.0.awaiting_quantity', 2)
            ->assertJsonPath('meta.awaiting_orders_count', 1)
            ->assertJsonPath('meta.ready_orders_count', 0);
    }

    public function test_changed_prepared_order_returns_to_assembly_even_if_prepared_at_is_still_present(): void
    {
        $order = $this->order(3);
        $this->prepare($order);
        $this->getJson('/api/mobile/v1/products')->assertOk()
            ->assertJsonPath('data.0.ready_quantity', 3)
            ->assertJsonPath('meta.ready_orders_count', 1);

        // Another writer changed persisted quantities without clearing preparation.
        DB::table('order_items')->where('order_id', $order->id)->update(['quantity' => 4, 'line_total' => 400]);
        DB::table('orders')->where('id', $order->id)->update(['total_amount' => 400]);
        $this->assertNotNull($order->fresh()->prepared_at);
        $this->getJson('/api/mobile/v1/products')->assertOk()
            ->assertJsonPath('data.0.awaiting_quantity', 4)
            ->assertJsonPath('data.0.ready_quantity', 0)
            ->assertJsonPath('meta.awaiting_orders_count', 1)
            ->assertJsonPath('meta.ready_orders_count', 0);
    }

    public function test_product_and_historical_units_and_pack_weights_are_not_combined(): void
    {
        $pieces = Measure::query()->create(['name' => 'шт']);
        $this->order(2);
        $oldPieces = $this->order(3);
        $oldPieces->items()->sole()->update([
            'measure_id' => $pieces->id,
            'snapshot' => ['measurement' => ['measure_id' => $pieces->id, 'unit_label' => 'шт', 'kilograms_per_unit' => 0.5]],
        ]);
        $newPieces = $this->order(4);
        $newPieces->items()->sole()->update([
            'measure_id' => $pieces->id,
            'snapshot' => ['measurement' => ['measure_id' => $pieces->id, 'unit_label' => 'шт', 'kilograms_per_unit' => 1]],
        ]);
        $other = Good::query()->create(['name' => 'Сахар', 'measure_id' => $this->measure->id]);
        $this->order(5)->items()->sole()->update(['good_id' => $other->id]);
        // Product and dictionary edits cannot rewrite historical order units.
        $this->good->update(['measure_id' => $pieces->id]);
        $this->measure->update(['name' => 'килограмм']);

        $products = collect($this->getJson('/api/mobile/v1/products')->assertOk()->assertJsonCount(4, 'data')->json('data'));
        $sugar = $products->where('good_id', $this->good->id);
        $this->assertCount(3, $sugar);
        $this->assertEquals(2, $sugar->firstWhere('measure_name', 'кг')['awaiting_quantity']);
        $this->assertEquals(3, $sugar->firstWhere('kilograms_per_unit', 0.5)['awaiting_quantity']);
        $this->assertEquals(4, $sugar->where('measure_name', 'шт')->firstWhere('kilograms_per_unit', 1)['awaiting_quantity']);
        $this->assertEquals(5, $products->firstWhere('good_id', $other->id)['awaiting_quantity']);
        $this->assertCount(4, $products->pluck('key')->unique());
    }

    public function test_legacy_units_and_deleted_same_named_products_remain_visible_and_separate(): void
    {
        $legacy = $this->order(2);
        $legacy->items()->sole()->update(['snapshot' => null]);
        foreach ([3, 4] as $quantity) {
            $this->order($quantity)->items()->sole()->update(['good_id' => null]);
        }

        $products = collect($this->getJson('/api/mobile/v1/products')->assertOk()->assertJsonCount(3, 'data')->json('data'));
        $this->assertSame('кг', $products->firstWhere('good_id', $this->good->id)['measure_name']);
        $this->assertCount(2, $products->whereNull('good_id'));
        $this->assertEquals([3, 4], $products->whereNull('good_id')->pluck('awaiting_quantity')->sort()->values()->all());
    }

    public function test_rejects_invalid_dates_and_requires_an_authorized_mobile_token(): void
    {
        foreach (['2026-02-29', '2026-04-31', '2026-1-11', '11.10.2026', '2026-10-11T00:00:00Z', '0000-01-01'] as $date) {
            $this->getJson('/api/mobile/v1/products?delivery_date='.urlencode($date))
                ->assertUnprocessable()->assertJsonValidationErrors('delivery_date');
        }
        $this->withHeader('Authorization', '')->getJson('/api/mobile/v1/products')->assertUnauthorized();
        $this->actingAs($this->employee, 'web')->getJson('/api/mobile/v1/products')->assertUnauthorized();
        $this->withToken($this->employee->createToken('crm', ['*'], now()->addHour())->plainTextToken)
            ->getJson('/api/mobile/v1/products')->assertUnauthorized();
        $unauthorized = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->withToken($unauthorized->createToken('mobile:products', ['mobile:orders'], now()->addHour())->plainTextToken)
            ->getJson('/api/mobile/v1/products')->assertForbidden();
    }

    private function order(float $quantity, ?string $date = '2026-10-11'): Order
    {
        $order = Order::query()->create([
            'entity_id' => $this->buyer->id,
            'currency_code' => 'RUB',
            'delivery_date' => $date,
            'total_amount' => 0,
        ]);
        $this->line($order, $quantity);

        return $order;
    }

    private function line(Order $order, float $quantity, ?Good $good = null): OrderItem
    {
        $good ??= $this->good;
        $item = $order->items()->create([
            'good_id' => $good->id,
            'good_name' => $good->name,
            'measure_id' => $this->measure->id,
            'quantity' => $quantity,
            'price_gross' => 100,
            'line_total' => round($quantity * 100, 4),
            'currency_code' => 'RUB',
            'snapshot' => ['measurement' => ['measure_id' => $this->measure->id, 'unit_label' => 'кг', 'kilograms_per_unit' => 1]],
        ]);
        $order->update(['total_amount' => round($order->items()->sum('line_total'), 4)]);

        return $item;
    }

    private function prepare(Order $order): void
    {
        $fulfillment = app(OrderFulfillmentService::class);
        $order->load($fulfillment->relations());
        $fulfillment->prepare($order, [
            'version' => $fulfillment->version($order),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id, 'quantity' => $item->quantity, 'measure_id' => $item->measure_id,
            ])->all(),
        ], $this->employee);
    }
}
