<?php

namespace Tests\Feature\Suppliers;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Order;
use App\Models\SupplierOrder;
use App\Models\SupplierProfile;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierFreeformLinesTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Currency $rub;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->unit = Unit::create(['name' => 'Поставщик произвольных позиций', 'is_supplier' => true]);
        SupplierProfile::create(['unit_id' => $this->unit->id, 'is_watched' => true]);
        $this->rub = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
    }

    public function test_freeform_names_units_and_exact_totals_survive_registers_and_supplier_analytics(): void
    {
        $order = $this->order([
            $this->line('Набор овощей по спецификации', '2.500000', '125.4321', 'набор'),
            $this->line('Набор овощей по спецификации', '3.000000', '10.0000', 'лоток'),
            $this->line('Образец нового продукта', '1.000000', null, 'образец'),
        ]);

        $this->getJson('/api/supplier-orders?'.http_build_query(['search' => 'Набор овощей']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.items_count', 3)->assertJsonPath('data.0.items.0.good_id', null)
            ->assertJsonPath('data.0.items.0.name', 'Набор овощей по спецификации')
            ->assertJsonPath('data.0.items.0.unit_label', 'набор')
            ->assertJsonPath('data.0.items.1.unit_label', 'лоток')
            ->assertJsonPath('data.0.items.2.name', 'Образец нового продукта')
            ->assertJsonPath('data.0.totals_by_currency.RUB', 343.5803)
            ->assertJsonPath('data.0.has_unpriced_items', true);

        $period = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
        $this->getJson('/api/suppliers?'.http_build_query($period))
            ->assertOk()->assertJsonPath('data.0.unit.id', $this->unit->id)
            ->assertJsonPath('data.0.metrics.orders.count', 1)
            ->assertJsonPath('data.0.metrics.orders.totals_by_currency.RUB', '343.5803')
            ->assertJsonPath('data.0.metrics.orders.unpriced_items_count', 1)
            ->assertJsonPath('data.0.metrics.orders.distinct_goods_count', 2);

        $response = $this->getJson('/api/suppliers/'.$this->unit->id.'/analytics?'.http_build_query($period))
            ->assertOk()->assertJsonPath('goods.meta.total', 3)
            ->assertJsonPath('monthly.0.order_totals_by_currency.RUB', '343.5803');
        $rows = collect($response->json('goods.data'))->keyBy('unit_label');
        $this->assertSame('Набор овощей по спецификации', $rows['набор']['name']);
        $this->assertNull($rows['набор']['good_id']);
        $this->assertNull($rows['набор']['measure_id']);
        $this->assertSame('2.500000', $rows['набор']['quantity']);
        $this->assertSame(['RUB' => '313.5803'], $rows['набор']['totals_by_currency']);
        $this->assertSame('3.000000', $rows['лоток']['quantity']);
        $this->assertSame(['RUB' => '30.0000'], $rows['лоток']['totals_by_currency']);
        $this->assertNotSame($rows['набор']['key'], $rows['лоток']['key']);
        $this->assertSame(1, $rows['образец']['unpriced_items_count']);

        $this->assertDatabaseCount('goods', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
        $this->assertDatabaseCount('purchases', 0);
        Mail::assertNothingSent();
    }

    public function test_unregistered_rows_do_not_cover_catalog_demand_even_when_the_names_match(): void
    {
        $good = Good::create(['name' => 'Черника свежая']);
        $warehouse = Warehouse::where('code', Warehouse::GOODS_CODE)->firstOrFail();
        $buyer = Entity::forceCreate(['name' => 'Покупатель']);
        $customerOrder = Order::create(['entity_id' => $buyer->id, 'currency_code' => 'RUB']);
        $measurement = $good->measurement();
        $customerOrder->items()->create([
            'good_id' => $good->id, 'good_name' => $good->name, 'quantity' => 10,
            'measure_id' => $measurement['measure_id'], 'denominator' => $measurement['kilograms_per_unit'],
            'price_gross' => 200, 'line_total' => 2000, 'currency_code' => 'RUB',
            'snapshot' => ['measurement' => $measurement],
        ]);
        GoodStockMovement::create([
            'good_id' => $good->id, 'warehouse_id' => $warehouse->id, 'measure_id' => $good->measure_id,
            'quantity_delta' => 3, 'unit_price' => 100, 'type' => 'adjustment', 'moved_at' => today(),
        ]);
        $this->order([[
            ...$this->line($good->name, '2.000000', '100.0000', $measurement['unit_label']),
            'good_id' => $good->id, ...$measurement,
        ]]);
        $url = '/api/units/'.$this->unit->id.'/supplier-order-context?tab=demand&warehouse_id='.$warehouse->id;
        $before = $this->getJson($url)->assertOk()->json('data');
        $movements = DB::table('good_stock_movements')->orderBy('id')->get()->all();

        $this->order([
            $this->line($good->name, '1000.000000', '75.0000', 'кг'),
            $this->line('Товар, которого ещё нет в каталоге', '40.000000', null, 'ящик'),
        ]);

        $after = $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.demand_quantity', 10)
            ->assertJsonPath('data.0.stock_quantity', 3)
            ->assertJsonPath('data.0.shortage_quantity', 7)
            ->assertJsonPath('data.0.ordered_quantity', 2)
            ->assertJsonPath('data.0.to_order_quantity', 5)->json('data');
        $this->assertSame($before, $after);
        $this->assertEquals($movements, DB::table('good_stock_movements')->orderBy('id')->get()->all());
        $this->assertDatabaseCount('goods', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('purchases', 0);
        Mail::assertNothingSent();
    }

    public function test_legacy_deleted_good_keeps_its_snapshot_in_order_and_email_preview(): void
    {
        $good = Good::create(['name' => 'Архивный товар']);
        $order = $this->order([[
            ...$this->line($good->name, '2.000000', '75.5000', $good->measurement()['unit_label']),
            'good_id' => $good->id, ...$good->measurement(),
        ]]);
        $good->delete();

        $this->getJson('/api/supplier-orders/'.$order->id)->assertOk()
            ->assertJsonPath('items.0.good_id', null)->assertJsonPath('items.0.name', 'Архивный товар')
            ->assertJsonPath('items.0.unit_label', 'кг')->assertJsonPath('items.0.avatar_url', null)
            ->assertJsonPath('totals_by_currency.RUB', 151);
        $preview = $this->getJson('/api/supplier-orders/'.$order->id.'/preview')->assertOk();
        $this->assertStringContainsString('Архивный товар', $preview->json('html'));
        $this->assertStringContainsString('Количество: 2 кг', $preview->json('text'));
        $this->assertStringContainsString('151,00 RUB', $preview->json('text'));
        $this->assertDatabaseCount('goods', 0);
        Mail::assertNothingSent();
    }

    private function line(string $name, string $quantity, ?string $price, string $label): array
    {
        return ['good_id' => null, 'name' => $name, 'measure_id' => null, 'unit_label' => $label,
            'kilograms_per_unit' => null, 'quantity' => $quantity, 'unit_price' => $price,
            'currency_id' => $price === null ? null : $this->rub->id,
            'currency_code' => $price === null ? null : 'RUB', 'source_order_item_ids' => []];
    }

    private function order(array $items): SupplierOrder
    {
        $order = SupplierOrder::create(['unit_id' => $this->unit->id, 'to' => [], 'cc' => [],
            'create_key' => (string) Str::uuid(), 'create_payload_hash' => str_repeat('a', 64)]);
        foreach ($items as $index => $item) {
            $order->items()->create(['position' => $index, ...$item]);
        }
        $order->update(['status' => 'sent', 'sent_at' => '2026-10-15 09:00:00']);

        return $order;
    }
}
