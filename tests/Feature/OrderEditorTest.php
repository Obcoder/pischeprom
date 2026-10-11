<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Currency;
use App\Models\Email;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Telephone;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Orders\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_options_identify_entities_and_their_buildings_without_removing_other_buildings(): void
    {
        $entity = Entity::query()->create([
            'name' => 'Ромашка', 'full_name' => 'ООО Ромашка', 'INN' => '7701234567',
            'KPP' => '770101001', 'OGRN' => '1234567890123', 'legal_address' => 'Москва, Лесная 1',
            'entity_classification_id' => EntityClassification::query()->create(['name' => 'Производитель'])->id,
        ]);
        $linked = Building::query()->create(['address' => 'Складская 1']);
        $other = Building::query()->create(['address' => 'Другая 2']);
        $entity->buildings()->attach($linked);
        $entity->telephones()->attach(Telephone::query()->create(['number' => '+79991112233']));
        $entity->emails()->attach(Email::query()->create(['address' => 'client@example.test']));

        $result = $this->getJson('/api/orders/options')->assertOk()
            ->assertJsonPath('entities.0.full_name', 'ООО Ромашка')
            ->assertJsonPath('entities.0.INN', '7701234567')
            ->assertJsonPath('entities.0.KPP', '770101001')
            ->assertJsonPath('entities.0.OGRN', '1234567890123')
            ->assertJsonPath('entities.0.legal_address', 'Москва, Лесная 1')
            ->assertJsonPath('entities.0.classification.name', 'Производитель')
            ->assertJsonPath('entities.0.telephones.0.number', '+79991112233')
            ->assertJsonPath('entities.0.emails.0.address', 'client@example.test')
            ->assertJsonPath('entities.0.building_ids', [$linked->id])
            ->assertJsonCount(2, 'buildings');
        $this->assertEqualsCanonicalizing([$linked->id, $other->id], array_column($result->json('buildings'), 'id'));
    }

    public function test_options_include_current_database_prices_in_the_accounting_unit_with_currency_fallback(): void
    {
        $measure = Measure::query()->firstOrCreate(['name' => 'коробка']);
        $good = Good::query()->create(['name' => 'Какао', 'measure_id' => $measure->id, 'unit_weight_kg' => 10]);
        $currency = Currency::query()->forceCreate(['name' => 'Евро', 'code' => 'EUR']);
        $type = PriceType::query()->create(['name' => 'Оптовая', 'code' => 'editor-wholesale', 'currency_id' => $currency->id, 'is_active' => true]);
        $price = GoodPriceTypeValue::query()->create([
            'good_id' => $good->id, 'price_type_id' => $type->id, 'price_gross' => 125.1234,
            'is_published' => false, 'valid_from' => today(), 'valid_to' => today(),
        ]);
        foreach ([['valid_to' => today()->subDay()], ['valid_from' => today()->addDay()]] as $dates) {
            $otherType = PriceType::query()->create(['name' => 'Архив', 'code' => 'archive-'.count($dates).'-'.str()->random(5), 'is_active' => true]);
            GoodPriceTypeValue::query()->create(['good_id' => $good->id, 'price_type_id' => $otherType->id, 'price_gross' => 1, ...$dates]);
        }
        $inactive = PriceType::query()->create(['name' => 'Отключённая', 'code' => 'inactive', 'is_active' => false]);
        GoodPriceTypeValue::query()->create(['good_id' => $good->id, 'price_type_id' => $inactive->id, 'price_gross' => 2]);

        $this->getJson('/api/orders/options')->assertOk()
            ->assertJsonPath('goods.0.measurement.unit_label', 'коробка')
            ->assertJsonPath('goods.0.measurement.kilograms_per_unit', 10)
            ->assertJsonCount(1, 'goods.0.prices')
            ->assertJsonPath('goods.0.prices.0.id', $price->id)
            ->assertJsonPath('goods.0.prices.0.price_gross', 125.1234)
            ->assertJsonPath('goods.0.prices.0.currency_code', 'EUR')
            ->assertJsonPath('goods.0.prices.0.price_type.name', 'Оптовая')
            ->assertJsonPath('goods.0.prices.0.measure_id', $measure->id)
            ->assertJsonPath('goods.0.prices.0.unit_label', 'коробка');
    }

    public function test_amount_edits_derive_the_price_and_survive_unrelated_edits_and_fulfillment_checks(): void
    {
        $good = Good::query()->create(['name' => 'Товар']);
        $data = $this->data([['good_id' => $good->id, 'quantity' => 3, 'unit_price' => 1, 'line_total' => 100]]);
        $created = $this->postJson('/api/orders', $data)->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', 33.3333)
            ->assertJsonPath('data.items.0.line_total', 100)
            ->assertJsonPath('data.items.0.pricing_basis', 'total')
            ->assertJsonPath('data.total_amount', 100)
            ->assertJsonPath('data.currency_code', 'RUB');
        $order = Order::query()->findOrFail($created->json('data.id'));
        $data['internal_comment'] = 'Без изменения суммы';
        $data['items'][0] = ['id' => $created->json('data.items.0.id'), 'good_id' => $good->id, 'quantity' => 3, 'unit_price' => 33.3333];
        $updated = $this->putJson('/api/orders/'.$order->id, $data)->assertOk()
            ->assertJsonPath('data.items.0.line_total', 100)
            ->assertJsonPath('data.total_amount', 100);

        $fulfillment = app(OrderFulfillmentService::class);
        $warehouse = Warehouse::query()->firstOrCreate(['code' => Warehouse::GOODS_CODE], ['name' => 'Склад', 'is_active' => true]);
        $this->assertSame([], $fulfillment->problems($order->fresh($fulfillment->relations()), $warehouse));

        // An explicit new price switches back to multiplication; the stale amount is discarded.
        $data['items'][0]['id'] = $updated->json('data.items.0.id');
        $data['items'][0]['unit_price'] = 25.5;
        $this->putJson('/api/orders/'.$order->id, $data)->assertOk()
            ->assertJsonPath('data.items.0.line_total', 76.5)
            ->assertJsonPath('data.items.0.pricing_basis', 'unit_price');
    }

    public function test_each_line_keeps_its_currency_and_mixed_totals_are_never_added_together(): void
    {
        $goods = collect(['Рубли', 'Доллары', 'Свободная цена'])->map(fn ($name) => Good::query()->create(['name' => $name]));
        $data = $this->data([
            ['good_id' => $goods[0]->id, 'quantity' => 2, 'unit_price' => 125.5],
            ['good_id' => $goods[1]->id, 'quantity' => 3, 'unit_price' => 5, 'currency_code' => 'usd'],
            ['good_id' => $goods[2]->id, 'quantity' => 1, 'unit_price' => 0],
        ]);
        $created = $this->postJson('/api/orders', $data)->assertCreated()
            ->assertJsonPath('data.total_amount', null)
            ->assertJsonPath('data.totals_by_currency.RUB', 251)
            ->assertJsonPath('data.totals_by_currency.USD', 15)
            ->assertJsonPath('data.items.0.currency_code', 'RUB')
            ->assertJsonPath('data.items.1.currency_code', 'USD')
            ->assertJsonPath('data.items.2.unit_price', 0);
        $this->assertDatabaseHas('orders', ['id' => $created->json('data.id'), 'total_amount' => null]);
        $this->getJson('/api/orders')->assertOk()->assertJsonPath('data.0.totals_by_currency.USD', 15);
        $order = Order::query()->findOrFail($created->json('data.id'));
        $service = app(OrderFulfillmentService::class);
        $warnings = array_column($service->problems($order->load($service->relations()), $service->warehouse()), 'code');
        $this->assertContains('unsupported_currency', $warnings);
        $this->assertNotContains('invalid_total', $warnings);
    }

    public function test_legacy_order_currency_remains_the_default_for_items_and_single_currency_totals(): void
    {
        $good = Good::query()->create(['name' => 'Товар']);
        $this->postJson('/api/orders', [...$this->data([['good_id' => $good->id, 'quantity' => 2, 'unit_price' => 10]]), 'currency_code' => 'EUR'])
            ->assertCreated()->assertJsonPath('data.items.0.currency_code', 'EUR')
            ->assertJsonPath('data.total_amount', 20)->assertJsonPath('data.totals_by_currency.EUR', 20);
    }

    public function test_price_or_amount_overflow_is_rejected_without_creating_an_order(): void
    {
        $good = Good::query()->create(['name' => 'Товар']);
        foreach ([['quantity' => 1000, 'unit_price' => 999999999999], ['quantity' => 0.001, 'line_total' => 999999999999]] as $line) {
            $this->postJson('/api/orders', $this->data([['good_id' => $good->id, ...$line]]))
                ->assertUnprocessable()->assertJsonValidationErrors('items.0.unit_price');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_currency_migration_preserves_orders_and_their_items_when_reapplied(): void
    {
        $good = Good::query()->create(['name' => 'Товар']);
        $created = $this->postJson('/api/orders', $this->data([['good_id' => $good->id, 'quantity' => 3, 'unit_price' => 125]]))->assertCreated();
        $migration = require database_path('migrations/2026_10_11_130000_allow_orders_with_multiple_currencies.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('orders', ['id' => $created->json('data.id'), 'total_amount' => 375]);
        $this->assertDatabaseHas('order_items', ['id' => $created->json('data.items.0.id'), 'line_total' => 375]);
    }

    private function data(array $items): array
    {
        return [
            'entity_id' => Entity::query()->firstOrCreate(['name' => 'Покупатель'])->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->sole()->id,
            'items' => $items,
        ];
    }
}
