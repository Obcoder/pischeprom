<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodInquiry;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\User;
use App\Services\Goods\PublicGoodOffer;
use App\Services\Orders\CustomerOrderNotificationService;
use App\Services\Orders\OrderFulfillmentService;
use App\Services\Orders\OrderWriter;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GoodMeasurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_ten_kilograms_and_ten_boxes_have_explicit_different_weights_with_the_same_packaging(): void
    {
        $kg = $this->good('кг');
        $box = $this->good('коробка', 10);
        $order = app(OrderWriter::class)->save(null, $this->data([
            ['good_id' => $kg->id, 'quantity' => 10, 'unit_price' => 100],
            ['good_id' => $box->id, 'quantity' => 10, 'unit_price' => 1000],
        ]));

        $this->assertSame(10.0, $order->items[0]->line_weight);
        $this->assertSame(100.0, $order->items[1]->line_weight);
        $this->assertSame(1000.0, $order->items[0]->line_total);
        $this->assertSame(10000.0, $order->items[1]->line_total);
        $this->assertSame('кг', $order->items[0]->measurement()['unit_label']);
        $this->assertSame('коробка', $order->items[1]->measurement()['unit_label']);
        $this->assertSame(110.0, $order->total_weight);
    }

    public function test_editing_an_order_preserves_its_unit_and_factor_after_the_product_changes(): void
    {
        $good = $this->good('кг');
        $data = $this->data([['good_id' => $good->id, 'quantity' => 10, 'unit_price' => 100]]);
        $order = app(OrderWriter::class)->save(null, $data);
        $oldItem = $order->items->sole();
        $good->update(['measure_id' => Measure::query()->create(['name' => 'коробка'])->id, 'unit_weight_kg' => 10]);
        $data['items'][0] += ['id' => $oldItem->id, 'measure_id' => $oldItem->measure_id];
        $data['items'][0]['quantity'] = 12.5;

        $updated = app(OrderWriter::class)->save($order, $data);

        $this->assertSame('кг', $updated->items->sole()->measurement()['unit_label']);
        $this->assertSame(12.5, $updated->total_weight);
        $this->assertSame(1250.0, $updated->total_amount);
    }

    public function test_fractional_grams_keep_subgram_weight_precision(): void
    {
        $good = $this->good('г');
        $order = app(OrderWriter::class)->save(null, $this->data([[
            'good_id' => $good->id, 'quantity' => 0.125, 'unit_price' => 100,
        ]]));
        $this->assertSame(0.000125, $order->items->sole()->line_weight);
        $this->assertSame(0.000125, $order->total_weight);
        $this->assertSame(12.5, $order->total_amount);
    }

    public function test_order_item_ids_cannot_copy_units_from_another_order(): void
    {
        $good = $this->good('кг');
        $data = $this->data([['good_id' => $good->id, 'quantity' => 10, 'unit_price' => 100]]);
        $order = app(OrderWriter::class)->save(null, $data);
        $data['items'][0]['id'] = $order->items->sole()->id;

        $this->expectException(ValidationException::class);
        app(OrderWriter::class)->save(null, $data);
    }

    public function test_explicit_new_line_can_replace_the_same_good_using_its_new_unit(): void
    {
        $good = $this->good('кг');
        $data = $this->data([['good_id' => $good->id, 'quantity' => 10, 'unit_price' => 100]]);
        $order = app(OrderWriter::class)->save(null, $data);
        $box = Measure::query()->create(['name' => 'коробка']);
        $good->update(['measure_id' => $box->id, 'unit_weight_kg' => 10]);
        $data['items'][0] += ['id' => null, 'measure_id' => $box->id];
        $updated = app(OrderWriter::class)->save($order, $data);
        $this->assertSame($box->id, $updated->items->sole()->measure_id);
        $this->assertSame(100.0, $updated->total_weight);
    }

    public function test_new_orders_reject_a_stale_unit_selected_before_the_product_changed(): void
    {
        $good = $this->good('кг');
        $oldMeasure = $good->measure_id;
        $good->update(['measure_id' => Measure::query()->create(['name' => 'коробка'])->id, 'unit_weight_kg' => 10]);

        $this->expectException(ValidationException::class);
        app(OrderWriter::class)->save(null, $this->data([[
            'good_id' => $good->id, 'measure_id' => $oldMeasure, 'quantity' => 10, 'unit_price' => 100,
        ]]));
    }

    public function test_new_orders_reject_a_stale_weight_even_when_the_unit_id_is_unchanged(): void
    {
        $good = $this->good('коробка', 10);
        $snapshot = $good->measurement();
        $good->update(['unit_weight_kg' => 12]);

        $this->expectException(ValidationException::class);
        app(OrderWriter::class)->save(null, $this->data([[
            'good_id' => $good->id, 'measurement' => $snapshot, 'quantity' => 10, 'unit_price' => 100,
        ]]));
    }

    public function test_customer_checkout_uses_kilograms_even_when_the_packaging_is_ten_kilograms(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldReceive('notify')->once();
        $user = User::factory()->create(['type' => 'customer']);
        $good = $this->good('кг');
        $this->price($good, 240);
        $this->actingAs($user)->postJson('/orders', [
            'items' => [['good_id' => $good->id, 'quantity' => 10, 'measurement' => $good->measurement()]],
            'delivery_address' => 'Лесная, 10', 'preferred_delivery_time' => 'После 12',
            'customer_phone' => '+79991234567',
        ])->assertCreated()->assertJsonPath('order.total_weight', 10)->assertJsonPath('order.total_amount', 2400);
        $this->assertSame('кг', Order::query()->sole()->items->sole()->measurement()['unit_label']);
    }

    public function test_fulfillment_cannot_turn_kilograms_into_boxes(): void
    {
        $good = $this->good('кг');
        $order = app(OrderWriter::class)->save(null, $this->data([['good_id' => $good->id, 'quantity' => 10, 'unit_price' => 100]]));
        $fulfillment = app(OrderFulfillmentService::class);
        $order->load($fulfillment->relations());
        $box = Measure::query()->create(['name' => 'коробка']);
        $actor = User::factory()->create();
        try {
            $fulfillment->prepare($order, [
                'version' => $fulfillment->version($order),
                'items' => [['id' => $order->items->sole()->id, 'quantity' => 10, 'measure_id' => $box->id]],
            ], $actor);
            $this->fail('An assigned unit must be immutable during fulfillment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        $this->assertSame($good->measure_id, $order->items()->sole()->measure_id);
        $this->assertNull($order->fresh()->prepared_at);
    }

    public function test_fractional_inquiry_uses_unit_price_and_weight_without_packaging_multiplier(): void
    {
        $good = $this->good('кг');
        $this->price($good, 240);
        $offer = app(PublicGoodOffer::class)->for($good);
        $this->assertSame('кг', $offer['price_unit_label']);
        $this->assertSame(2400.0, $offer['package_price']);
        $this->assertSame(240.0, $offer['unit_price']);
        $this->assertSame(10.0, $offer['package_weight']);

        $this->postJson('/g/'.$good->id.'/inquiries', [
            'request_token' => (string) Str::uuid(), 'kind' => 'order', 'quantity' => 0.125,
            'measure_id' => $good->measure_id, 'customer_name' => 'Покупатель',
            'customer_email' => 'buyer@example.test', 'consent' => true,
        ])->assertCreated();

        $inquiry = GoodInquiry::query()->sole();
        $order = Order::query()->sole();
        $this->assertSame(0.125, $inquiry->quantity);
        $this->assertSame('кг', $inquiry->unit_label);
        $this->assertSame(0.125, $order->total_weight);
        $this->assertSame(30.0, $order->total_amount);
        $this->assertSame(240.0, $order->items->sole()->price_gross);
    }

    public function test_explicit_legacy_unit_confirmation_recalculates_kilograms_once(): void
    {
        $good = $this->good('кг');
        $order = app(OrderWriter::class)->save(null, $this->data([['good_id' => $good->id, 'quantity' => 10, 'unit_price' => 100]]));
        $order->items()->sole()->update(['measure_id' => null, 'snapshot' => null, 'denominator' => 10, 'line_weight' => 100]);
        $order->update(['total_weight' => 100]);
        $fulfillment = app(OrderFulfillmentService::class);
        $order = $order->fresh($fulfillment->relations());
        $prepared = $fulfillment->prepare($order, [
            'version' => $fulfillment->version($order),
            'items' => [['id' => $order->items->sole()->id, 'quantity' => 10, 'measure_id' => $good->measure_id]],
        ], User::factory()->create());

        $this->assertSame(10.0, $prepared->total_weight);
        $this->assertSame(10.0, $prepared->items->sole()->line_weight);
        $this->assertEquals(1, $prepared->items->sole()->measurement()['kilograms_per_unit']);
        $this->assertSame('кг', $prepared->items->sole()->measurement()['unit_label']);
        $this->assertSame(1000.0, $prepared->total_amount);
    }

    public function test_unconfigured_legacy_good_cannot_create_a_new_order(): void
    {
        $good = $this->good('кг');
        $good->update(['measure_id' => null]);
        $this->expectException(ValidationException::class);
        app(OrderWriter::class)->save(null, $this->data([['good_id' => $good->id, 'quantity' => 10, 'unit_price' => 100]]));
    }

    public function test_mixed_orders_do_not_report_partial_weight_as_total_when_a_unit_has_no_known_mass(): void
    {
        $kg = $this->good('кг');
        $piece = $this->good('шт');
        $order = app(OrderWriter::class)->save(null, $this->data([
            ['good_id' => $kg->id, 'quantity' => 10, 'unit_price' => 100],
            ['good_id' => $piece->id, 'quantity' => 10, 'unit_price' => 100],
        ]));
        $this->assertNull($order->total_weight);
        $this->assertSame(10.0, $order->items[0]->line_weight);
        $this->assertNull($order->items[1]->line_weight);
    }

    public function test_historical_purchase_edits_keep_their_explicit_unit_after_the_product_is_reconfigured(): void
    {
        $good = $this->good('кг');
        $data = ['date' => '2026-10-10', 'entity_id' => Entity::query()->create(['name' => 'Поставщик'])->id,
            'items' => [['good_id' => $good->id, 'measure_id' => $good->measure_id, 'quantity' => 10, 'price' => 100]]];
        $purchase = app(PurchaseService::class)->store($data);
        $good->update(['measure_id' => Measure::query()->create(['name' => 'коробка'])->id, 'unit_weight_kg' => 10]);
        $data['items'][0]['quantity'] = 12;
        $updated = app(PurchaseService::class)->update($purchase, [...$data, 'date' => '2026-10-11']);
        $this->assertEquals($data['items'][0]['measure_id'], $updated->goods->sole()->pivot->measure_id);
        $this->assertEquals(12, $updated->goods->sole()->pivot->quantity);
    }

    public function test_purchase_defaults_to_the_product_unit_and_rejects_a_different_unit(): void
    {
        $good = $this->good('кг');
        $data = ['date' => '2026-10-10', 'entity_id' => Entity::query()->create(['name' => 'Поставщик'])->id,
            'items' => [['good_id' => $good->id, 'quantity' => 10, 'price' => 100]]];
        $purchase = app(PurchaseService::class)->store($data);
        $this->assertEquals($good->measure_id, $purchase->goods->sole()->pivot->measure_id);
        $data['items'][0]['measure_id'] = Measure::query()->create(['name' => 'коробка'])->id;
        $this->expectException(ValidationException::class);
        app(PurchaseService::class)->store($data);
    }

    private function good(string $label, ?float $weight = null): Good
    {
        return Good::query()->create([
            'name' => 'Товар '.Str::random(6), 'is_published' => true, 'denominator' => 10,
            'measure_id' => Measure::query()->firstOrCreate(['name' => $label])->id, 'unit_weight_kg' => $weight,
        ]);
    }

    private function data(array $items): array
    {
        return ['entity_id' => Entity::query()->create(['name' => 'Покупатель'])->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->sole()->id,
            'currency_code' => 'RUB', 'items' => $items];
    }

    private function price(Good $good, float $price): void
    {
        $currency = Currency::query()->where('code', 'RUB')->first()
            ?: Currency::query()->forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $type = PriceType::query()->create(['code' => 'retail', 'name' => 'Розничная', 'currency_id' => $currency->id,
            'is_active' => true, 'is_public' => true]);
        GoodPriceTypeValue::query()->create(['good_id' => $good->id, 'price_type_id' => $type->id,
            'currency_id' => $currency->id, 'price_gross' => $price, 'is_published' => true]);
    }
}
