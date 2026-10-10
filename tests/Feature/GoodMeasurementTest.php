<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodPriceCalculation;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Catalog\CatalogService;
use App\Services\Goods\GoodMeasurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodMeasurementTest extends TestCase
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
        Storage::fake('yandex');
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_good_and_catalog_card_share_the_explicit_unit_and_weight(): void
    {
        $box = Measure::firstWhere('name', 'коробка');
        $id = $this->postJson(route('goods.store'), [
            'name' => 'Филе в коробках', 'measure_id' => $box->id, 'unit_weight_kg' => 10, 'denominator' => 10,
        ])->assertCreated()->assertJsonPath('measurement.unit_label', 'коробка')
            ->assertJsonPath('measurement.kilograms_per_unit', 10)->json('id');

        $node = collect(app(CatalogService::class)->snapshot()['nodes'])->firstWhere('entity_id', $id);
        $this->getJson('/api/catalog/nodes/'.$node['id'].'/overview')
            ->assertOk()->assertJsonPath('data.measure_id', $box->id)->assertJsonPath('data.unit_weight_kg', 10);

        $kg = Measure::firstWhere('name', 'кг');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['good' => ['measure_id' => $kg->id, 'unit_weight_kg' => null]])->assertOk();
        $this->getJson(route('good.fetch', ['id' => $id]))->assertOk()
            ->assertJsonPath('measurement.unit_label', 'кг')
            ->assertJsonPath('measurement.kilograms_per_unit', 1)
            ->assertJsonPath('denominator', 10);
        $this->getJson(route('goods.index', ['view' => 'filters']))->assertOk()->assertJsonFragment(['id' => $box->id, 'name' => 'коробка']);
    }

    public function test_invalid_unit_or_weight_does_not_partially_change_the_catalog_record(): void
    {
        $good = Good::create(['name' => 'Исходный']);
        $node = collect(app(CatalogService::class)->snapshot()['nodes'])->firstWhere('entity_id', $good->id);
        foreach ([['measure_id' => null], ['measure_id' => 999999], ['unit_weight_kg' => -1], ['unit_weight_kg' => 0]] as $invalid) {
            $field = array_key_first($invalid);
            $this->patchJson('/api/catalog/nodes/'.$node['id'], ['name' => 'Не сохранять', 'good' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('good.'.$field);
            $this->patchJson(route('goods.update', $good), $invalid)
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame('Исходный', $good->fresh()->name);
    }

    public function test_mass_units_have_fixed_factors_and_packaging_never_changes_them(): void
    {
        foreach (['кг' => 1.0, 'г' => 0.001, 'т' => 1000.0] as $label => $weight) {
            $good = Good::create([
                'name' => $label, 'measure_id' => Measure::firstWhere('name', $label)->id,
                'denominator' => 10, 'unit_weight_kg' => 7,
            ]);
            $this->assertSame($weight, $good->measurement()['kilograms_per_unit']);
        }
        $piece = Good::create(['name' => 'Штучный', 'measure_id' => Measure::firstWhere('name', 'шт.')->id, 'denominator' => 10]);
        $this->assertNull($piece->measurement()['kilograms_per_unit']);
        $legacy = Good::create(['name' => 'Не настроен', 'measure_id' => null, 'denominator' => 10]);
        $this->assertNull($legacy->measurement()['measure_id']);
    }

    public function test_changing_the_price_basis_converts_prices_atomically(): void
    {
        $good = Good::create(['name' => 'Рыба', 'denominator' => 10]);
        $type = PriceType::create(['name' => 'Розница', 'code' => 'retail']);
        $price = GoodPriceTypeValue::create(['good_id' => $good->id, 'price_type_id' => $type->id, 'price_net' => 100, 'price_gross' => 120]);
        $box = Measure::firstWhere('name', 'коробка');

        $this->patchJson(route('goods.update', $good), ['measure_id' => $box->id, 'unit_weight_kg' => 10])->assertOk();
        $this->assertEquals(1200, $price->fresh()->price_gross);
        $this->assertEquals(1000, $price->fresh()->price_net);
        $this->patchJson('/api/goods/'.$good->id.'/price-type-values/'.$price->id, [
            'price_net' => 100, 'price_gross' => 120,
            'measurement' => ['measure_id' => Measure::firstWhere('name', 'кг')->id, 'unit_label' => 'кг', 'kilograms_per_unit' => 1],
        ])->assertUnprocessable()->assertJsonValidationErrors('measurement');
        $this->assertEquals(1200, $price->fresh()->price_gross);

        $this->patchJson(route('goods.update', $good), ['measure_id' => Measure::firstWhere('name', 'л')->id, 'unit_weight_kg' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_weight_kg');
        $this->assertSame($box->id, $good->fresh()->measure_id);
        $this->assertEquals(1200, $price->fresh()->price_gross);

        app(GoodMeasurement::class)->update($good, ['measure_id' => Measure::firstWhere('name', 'кг')->id, 'unit_weight_kg' => null]);
        $this->assertEquals(120, $price->fresh()->price_gross);
    }

    public function test_price_calculation_is_converted_from_kg_once_and_explicit_prices_are_per_unit(): void
    {
        $good = Good::create(['name' => 'Коробка рыбы', 'measure_id' => Measure::firstWhere('name', 'коробка')->id, 'unit_weight_kg' => 10]);
        $type = PriceType::create(['name' => 'Розница', 'code' => 'retail']);
        $calculation = GoodPriceCalculation::create(['good_id' => $good->id, 'sale_net_per_kg' => 100, 'sale_gross_per_kg' => 120]);
        $payload = ['price_type_id' => $type->id, 'calculation_id' => $calculation->id];
        $this->postJson('/api/goods/'.$good->id.'/price-type-values', $payload)
            ->assertCreated()->assertJsonPath('price_net', 1000)->assertJsonPath('price_gross', 1200);
        $this->postJson('/api/goods/'.$good->id.'/price-type-values', [...$payload, 'price_net' => 1100, 'price_gross' => 1320])
            ->assertCreated()->assertJsonPath('price_net', 1100)->assertJsonPath('price_gross', 1320);

        $good->update(['unit_weight_kg' => null]);
        $this->postJson('/api/goods/'.$good->id.'/price-type-values', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('calculation_id');
    }

    public function test_first_legacy_configuration_preserves_prices_unless_the_operator_selects_kg(): void
    {
        $type = PriceType::create(['name' => 'Розница', 'code' => 'retail']);
        foreach (['selected_unit' => 120, 'kg' => 1200] as $basis => $expected) {
            $good = Good::create(['name' => 'Старый товар '.$basis, 'measure_id' => null]);
            $price = GoodPriceTypeValue::create(['good_id' => $good->id, 'price_type_id' => $type->id, 'price_gross' => 120]);
            $node = collect(app(CatalogService::class)->snapshot()['nodes'])->firstWhere('entity_id', $good->id);
            $this->patchJson('/api/catalog/nodes/'.$node['id'], ['good' => [
                'measure_id' => Measure::firstWhere('name', 'коробка')->id, 'unit_weight_kg' => 10, 'existing_price_basis' => $basis,
            ]])->assertOk();
            $this->assertEquals($expected, $price->fresh()->price_gross);
        }
    }

    public function test_a_unit_change_cannot_strand_an_existing_stock_balance(): void
    {
        $box = Measure::firstWhere('name', 'коробка');
        $kg = Measure::firstWhere('name', 'кг');
        $good = Good::create(['name' => 'Коробки на складе', 'measure_id' => $box->id, 'unit_weight_kg' => 10]);
        $warehouse = Warehouse::firstWhere('code', Warehouse::GOODS_CODE);
        $movement = [
            'warehouse_id' => $warehouse->id, 'good_id' => $good->id, 'measure_id' => $box->id,
            'type' => 'receipt', 'quantity_delta' => 5, 'unit_price' => 100, 'moved_at' => today()->toDateString(),
        ];
        DB::table('good_stock_movements')->insert($movement);
        $this->patchJson(route('goods.update', $good), ['measure_id' => $kg->id])
            ->assertUnprocessable()->assertJsonValidationErrors('measure_id');
        $this->assertSame($box->id, $good->fresh()->measure_id);

        DB::table('good_stock_movements')->insert([...$movement, 'type' => 'write_off', 'quantity_delta' => -5]);
        $this->patchJson(route('goods.update', $good), ['measure_id' => $kg->id])->assertOk();
        $this->assertSame($kg->id, $good->fresh()->measure_id);
        $this->assertDatabaseCount('good_stock_movements', 2);
    }

    public function test_migration_preserves_populated_goods_and_order_history(): void
    {
        $good = Good::create(['name' => 'Исторический товар']);
        $entity = Entity::create(['name' => 'Покупатель']);
        $order = Order::create(['entity_id' => $entity->id, 'order_status_id' => OrderStatus::where('code', OrderStatus::OPEN)->value('id'), 'total_amount' => 100, 'total_weight' => 100]);
        $line = $order->items()->create(['good_id' => $good->id, 'good_name' => $good->name, 'quantity' => 10, 'denominator' => 10, 'line_weight' => 100, 'price_gross' => 10, 'line_total' => 100]);
        $migration = require database_path('migrations/2026_10_10_120000_add_good_accounting_measurements.php');
        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('goods', ['id' => $good->id, 'measure_id' => null]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total_weight' => 100, 'total_amount' => 100]);
        $this->assertDatabaseHas('order_items', ['id' => $line->id, 'good_id' => $good->id, 'quantity' => 10, 'denominator' => 10, 'line_total' => 100]);
    }
}
