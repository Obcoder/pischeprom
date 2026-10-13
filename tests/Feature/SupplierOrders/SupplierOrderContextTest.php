<?php

namespace Tests\Feature\SupplierOrders;

use App\Models\Currency;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOrderContextTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Good $good;

    private Warehouse $warehouse;

    private Entity $buyer;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Queue::fake();
        Mail::fake();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->unit = Unit::create(['name' => 'Поставщик']);
        $this->good = Good::create(['name' => 'Мак']);
        $this->buyer = Entity::forceCreate(['name' => 'Покупатель']);
        $this->currency = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $this->warehouse = Warehouse::where('code', 'goods')->firstOrFail();
    }

    private function url(array $query = []): string
    {
        return '/api/units/'.$this->unit->id.'/supplier-order-context'.($query ? '?'.http_build_query($query) : '');
    }

    private function customerOrder(float $quantity, array $attributes = [], ?Good $good = null, ?array $measurement = null): Order
    {
        $good ??= $this->good;
        $measurement ??= $good->measurement();
        $order = Order::create(['entity_id' => $this->buyer->id, 'currency_code' => 'RUB', ...$attributes]);
        $order->items()->create(['good_id' => $good->id, 'good_name' => $good->name, 'quantity' => $quantity,
            'measure_id' => $measurement['measure_id'], 'denominator' => $measurement['kilograms_per_unit'],
            'price_gross' => 87654.321, 'line_total' => $quantity * 87654.321, 'currency_code' => 'RUB',
            'snapshot' => ['measurement' => $measurement, 'private_price_id' => 91234, 'secret_note' => 'Не для поставщика']]);

        return $order;
    }

    private function supplierOrder(string $status, float $quantity, ?int $warehouseId = null, ?array $measurement = null): SupplierOrder
    {
        $measurement ??= $this->good->measurement();
        $order = SupplierOrder::create(['unit_id' => Unit::create(['name' => 'Другой поставщик'])->id,
            'warehouse_id' => $warehouseId, 'to' => [], 'cc' => [], 'create_key' => (string) Str::uuid(), 'create_payload_hash' => str_repeat('a', 64)]);
        $order->items()->create(['position' => 0, 'good_id' => $this->good->id, 'name' => $this->good->name,
            ...$measurement, 'quantity' => $quantity, 'source_order_item_ids' => []]);
        $order->update(['status' => $status]);

        return $order;
    }

    private function stock(float $quantity, ?int $measureId = null, ?Warehouse $warehouse = null): void
    {
        GoodStockMovement::create(['good_id' => $this->good->id, 'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
            'measure_id' => $measureId ?? $this->good->measure_id, 'quantity_delta' => $quantity, 'unit_price' => 1,
            'type' => 'adjustment', 'moved_at' => today()]);
    }

    public function test_demand_uses_all_buyers_signed_stock_and_other_suppliers_without_double_counting_prepared_orders(): void
    {
        $this->customerOrder(4);
        $prepared = $this->customerOrder(3);
        $prepared->forceFill(['prepared_at' => now(), 'fulfillment_warehouse_id' => $this->warehouse->id])->save();
        $this->customerOrder(100, ['order_status_id' => OrderStatus::where('code', 'deferred')->value('id')]);
        $this->customerOrder(100, ['order_status_id' => OrderStatus::where('code', 'closed')->value('id')]);
        $shipped = $this->customerOrder(100);
        $shipped->forceFill(['shipped_at' => now()])->save();
        $this->stock(-2);
        $this->supplierOrder('sent', 3, $this->warehouse->id);
        $this->supplierOrder('confirmed', 1);
        $this->supplierOrder('send_unknown', 8, $this->warehouse->id);
        $this->supplierOrder('draft', 50);
        $this->supplierOrder('closed', 200);
        $other = Warehouse::create(['name' => 'Другой склад', 'code' => 'another', 'is_active' => true]);
        $this->supplierOrder('sent', 999, $other->id);
        $otherOrder = $this->customerOrder(999);
        $otherOrder->forceFill(['fulfillment_warehouse_id' => $other->id])->save();

        $response = $this->getJson($this->url())->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.demand_quantity', 7)->assertJsonPath('data.0.deferred_quantity', 100)
            ->assertJsonPath('data.0.stock_quantity', -2)->assertJsonPath('data.0.shortage_quantity', 9)
            ->assertJsonPath('data.0.ordered_quantity', 4)->assertJsonPath('data.0.to_order_quantity', 5)
            ->assertJsonPath('data.0.uncertain_quantity', 8)->assertJsonPath('data.0.offered_by_supplier', false);
        $this->assertCount(3, $response->json('data.0.customer_orders'));
        $this->assertCount(4, $response->json('data.0.supplier_orders'));
        $this->assertTrue(collect($response->json('data.0.customer_orders'))->firstWhere('id', $prepared->id)['prepared']);
        $this->assertStringNotContainsString('87654', $response->getContent());
        $this->assertStringNotContainsString('secret_note', $response->getContent());
        $this->assertStringNotContainsString('private_price_id', $response->getContent());
        Mail::assertNothingSent();
    }

    public function test_filters_include_custom_open_statuses_deferred_cutoff_and_all_warehouses_explicitly(): void
    {
        $custom = OrderStatus::create(['code' => 'agreed', 'name' => 'Согласован', 'is_closed' => false]);
        $this->customerOrder(2, ['order_status_id' => $custom->id, 'delivery_date' => '2026-10-15']);
        $this->customerOrder(3, ['delivery_date' => null]);
        $this->customerOrder(40, ['delivery_date' => '2026-12-01']);
        $this->customerOrder(5, ['order_status_id' => OrderStatus::where('code', 'deferred')->value('id')]);
        $other = Warehouse::create(['name' => 'Второй', 'code' => 'second', 'is_active' => true]);
        $this->stock(6, warehouse: $other);
        $this->getJson($this->url(['include_deferred' => 1, 'delivery_to' => '2026-10-15', 'warehouse_id' => 0]))
            ->assertOk()->assertJsonPath('warehouse_id', 0)->assertJsonPath('data.0.demand_quantity', 10)
            ->assertJsonPath('data.0.stock_quantity', 6)->assertJsonPath('data.0.to_order_quantity', 4);
        $this->getJson($this->url(['delivery_to' => '2026-10-15']))->assertOk()
            ->assertJsonPath('data.0.demand_quantity', 5)->assertJsonPath('data.0.stock_quantity', 0);
    }

    public function test_unit_mismatches_are_explicit_and_never_become_zero_or_guessed_kilograms(): void
    {
        $box = Measure::where('name', 'коробка')->firstOrFail();
        $measurement = ['measure_id' => $box->id, 'unit_label' => 'коробка', 'kilograms_per_unit' => 10];
        $this->customerOrder(2, measurement: $measurement);
        $this->stock(4);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.demand_quantity', null)
            ->assertJsonPath('data.0.stock_quantity', 4)->assertJsonPath('data.0.shortage_quantity', null)
            ->assertJsonPath('data.0.to_order_quantity', null)->assertJsonPath('data.0.customer_orders.0.quantity', 2)
            ->assertJsonPath('data.0.customer_orders.0.unit_label', 'коробка')->assertJsonPath('data.0.unit_issues.0.source', 'customer_order');

        Order::query()->delete();
        $this->customerOrder(5);
        $this->supplierOrder('sent', 1, measurement: $measurement);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.shortage_quantity', 1)
            ->assertJsonPath('data.0.sent_quantity', null)->assertJsonPath('data.0.to_order_quantity', null);
        $this->stock(7, $box->id);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.stock_quantity', null)->assertJsonPath('data.0.shortage_quantity', null);
    }

    public function test_late_and_undated_incoming_stay_visible_but_cannot_cover_demand_before_a_delivery_cutoff(): void
    {
        $this->customerOrder(10, ['delivery_date' => '2026-10-15']);
        $this->stock(1);
        $timely = $this->supplierOrder('confirmed', 3);
        $timely->update(['delivery_date' => '2026-10-15']);
        $late = $this->supplierOrder('sent', 8);
        $late->update(['delivery_date' => '2026-10-16']);
        $undated = $this->supplierOrder('confirmed', 5);
        $response = $this->getJson($this->url(['delivery_to' => '2026-10-15', 'include_deferred' => 'false']))
            ->assertOk()->assertJsonPath('data.0.shortage_quantity', 9)->assertJsonPath('data.0.to_order_quantity', 6)
            ->assertJsonPath('data.0.ordered_quantity', 16)->assertJsonPath('data.0.incoming_before_cutoff_quantity', 3)
            ->assertJsonPath('data.0.late_incoming_quantity', 8)->assertJsonPath('data.0.undated_incoming_quantity', 5)
            ->assertJsonCount(2, 'data.0.incoming_warnings');
        $details = collect($response->json('data.0.supplier_orders'))->keyBy('id');
        $this->assertTrue($details[$timely->id]['deducted_from_recommendation']);
        $this->assertFalse($details[$late->id]['deducted_from_recommendation']);
        $this->assertSame('after_cutoff', $details[$late->id]['timing']);
        $this->assertSame('unknown', $details[$undated->id]['timing']);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.to_order_quantity', 0);
    }

    public function test_assortment_provenance_purchase_prices_and_recipient_scope_do_not_infer_supplier_skus_or_sale_prices(): void
    {
        Quotation::create(['unit_id' => $this->unit->id, 'good_id' => $this->good->id, 'price' => 120,
            'denominator' => 10, 'measure_id' => $this->good->measure_id, 'currency_id' => $this->currency->id]);
        $linked = Entity::forceCreate(['name' => 'Юрлицо поставщика']);
        $this->unit->entities()->attach($linked->id);
        $historical = Good::create(['name' => 'Ранее закупленный']);
        $purchase = Purchase::create(['entity_id' => $linked->id, 'date' => today(), 'amount' => 1]);
        $purchase->goods()->attach($historical->id, ['quantity' => 1, 'price' => 1]);
        $candidate = Good::create(['name' => 'Кандидат производителя']);
        $product = Product::create(['rus' => 'Мак пищевой']);
        $candidate->products()->attach($product->id);
        $this->unit->manufactures()->attach($product->id);
        Good::create(['name' => 'Посторонний']);
        $this->unit->emails()->attach(Email::create(['address' => 'direct@example.test', 'is_active' => true])->id);
        $linked->emails()->attach(Email::create(['address' => 'entity@example.test', 'is_active' => true])->id);
        $this->unit->emails()->attach(Email::create(['address' => 'inactive@example.test', 'is_active' => false])->id);
        $this->buyer->emails()->attach(Email::create(['address' => 'buyer@example.test', 'is_active' => true])->id);

        $response = $this->getJson($this->url(['tab' => 'assortment']))->assertOk()->assertJsonPath('meta.total', 3);
        $rows = collect($response->json('data'))->keyBy('good_id');
        $this->assertEquals(12, $rows[$this->good->id]['unit_price']);
        $this->assertSame('RUB', $rows[$this->good->id]['currency_code']);
        $this->assertTrue($rows[$historical->id]['offered_by_supplier']);
        $this->assertFalse($rows[$candidate->id]['offered_by_supplier']);
        $this->assertSame('manufacture', $rows[$candidate->id]['assortment_sources'][0]['kind']);
        $this->assertNull($rows[$historical->id]['unit_price']);
        $this->assertSame(['direct@example.test', 'entity@example.test'], collect($response->json('recipients'))->pluck('address')->all());
    }

    public function test_catalog_is_paginated_and_searchable_while_request_bounds_and_staff_boundary_are_enforced(): void
    {
        foreach (range(1, 8) as $index) {
            Good::create(['name' => 'Поиск '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
        }
        $this->getJson($this->url(['tab' => 'catalog', 'search' => 'Поиск', 'page' => 2, 'per_page' => 3]))
            ->assertOk()->assertJsonPath('meta.total', 8)->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('data.0.name', 'Поиск 04')->assertJsonCount(3, 'data')->assertJsonStructure(['can_send', 'currencies', 'generated_at']);
        foreach ([['per_page' => 51], ['warehouse_id' => 987654321], ['tab' => 'bad'], ['delivery_to' => 'tomorrow']] as $bad) {
            $this->getJson($this->url($bad))->assertUnprocessable();
        }
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))
            ->getJson($this->url())->assertForbidden();
        Mail::assertNothingSent();
    }
}
