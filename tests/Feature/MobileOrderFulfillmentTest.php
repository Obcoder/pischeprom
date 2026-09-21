<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\City;
use App\Models\Country;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Region;
use App\Models\Telephone;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Orders\OrderWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileOrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private Entity $buyer;

    private Good $good;

    private Measure $measure;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->employee = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->employee->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->withToken($this->employee->createToken('mobile:test', ['mobile:orders'], now()->addHours(8))->plainTextToken);
        $this->buyer = Entity::query()->create(['name' => 'Покупатель для мобильных заказов']);
        $this->good = Good::query()->create(['name' => 'Сахар']);
        $this->measure = Measure::query()->create(['name' => 'кг']);
        $this->warehouse = Warehouse::query()->where('code', Warehouse::GOODS_CODE)->sole();
        $this->receipt($this->good, 10, 20);
    }

    public function test_orders_use_existing_customer_goods_and_stock_with_an_explicit_measure(): void
    {
        $order = $this->order();
        $data = $this->getJson($this->url($order))->assertOk()
            ->assertJsonPath('data.entity.id', $this->buyer->id)
            ->assertJsonPath('data.warehouse.id', $this->warehouse->id)
            ->assertJsonPath('data.workflow_status', 'awaiting')
            ->assertJsonPath('data.responsible', null)
            ->assertJsonPath('data.can_prepare', true)
            ->assertJsonPath('data.can_ship', false)
            ->assertJsonPath('data.items.0.measure_id', null)
            ->assertJsonPath('data.warnings.0.code', 'missing_measure')
            ->json('data');
        $option = collect($data['items'][0]['measure_options'])->firstWhere('id', $this->measure->id);
        $this->assertEquals(10, $option['available_quantity']);

        $prepared = $this->prepare($order);
        $this->assertSame('ready', $prepared['workflow_status']);
        $this->assertTrue($prepared['can_ship']);
        $this->assertSame($this->employee->id, $prepared['responsible']['id']);
        $this->assertSame($this->measure->id, $prepared['items'][0]['measure_id']);
        $this->assertNotSame($data['version'], $prepared['version']);
        $this->assertDatabaseCount('sales', 0);
        $this->assertEquals(10, $this->balance());
    }

    public function test_list_and_details_show_the_order_delivery_addresses_and_contact_number(): void
    {
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $region = Region::query()->create(['name' => 'Самарская область', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'Самара', 'region_id' => $region->id]);
        $delivery = Building::query()->create(['address' => 'Ленина, 10', 'city_id' => $city->id]);
        $legacy = Building::query()->create(['address' => 'Советская, 2']);
        $logistics = Building::query()->create(['address' => 'Складская, 15']);
        $empty = Building::query()->create(['address' => '   ', 'city_id' => $city->id]);
        $telephone = Telephone::query()->create(['number' => '8 (917) 123-45-67']);
        $order = $this->order();
        $order->update(['contact_telephone_id' => $telephone->id]);
        $order->buildings()->attach([
            $delivery->id => ['role' => 'delivery', 'position' => 2],
            $legacy->id => ['role' => '', 'position' => 1],
            $logistics->id => ['role' => 'logistics', 'position' => 0],
            $empty->id => ['role' => 'delivery', 'position' => 3],
        ]);
        $fullAddress = 'Самарская область, Самара, Ленина, 10';

        foreach ([[$this->url($order), 'data'], ['/api/mobile/v1/orders', 'data.0']] as [$url, $path]) {
            $this->getJson($url)->assertOk()
                ->assertJsonCount(2, $path.'.delivery_addresses')
                ->assertJsonPath($path.'.delivery_addresses.0.id', $legacy->id)
                ->assertJsonPath($path.'.delivery_addresses.0.city', null)
                ->assertJsonPath($path.'.delivery_addresses.1.id', $delivery->id)
                ->assertJsonPath($path.'.delivery_addresses.1.city', 'Самара')
                ->assertJsonPath($path.'.delivery_addresses.1.full_address', $fullAddress)
                ->assertJsonPath($path.'.delivery_addresses.1.yandex_maps_url', 'https://yandex.ru/maps/?text='.rawurlencode($fullAddress))
                ->assertJsonPath($path.'.contact_telephone.id', $telephone->id)
                ->assertJsonPath($path.'.contact_telephone.number', '8 (917) 123-45-67')
                ->assertJsonPath($path.'.contact_telephone.dial_number', '+79171234567');
        }
    }

    public function test_missing_delivery_contacts_are_not_replaced_with_arbitrary_customer_data(): void
    {
        $order = $this->order();
        $telephone = Telephone::query()->create(['number' => '+79171234567']);
        $building = Building::query()->create(['address' => 'Другой адрес покупателя, 20']);
        $this->buyer->telephones()->attach($telephone->id);
        $this->buyer->buildings()->attach($building->id);
        $this->getJson($this->url($order))->assertOk()
            ->assertJsonPath('data.contact_telephone', null)
            ->assertJsonPath('data.delivery_addresses', []);

        $order->update(['contact_telephone_id' => $telephone->id]);
        foreach (['*21*79171234567#', 'tel:+79171234567', 'не звонить'] as $unsafe) {
            $telephone->update(['number' => $unsafe]);
            $this->getJson($this->url($order))->assertOk()
                ->assertJsonPath('data.contact_telephone.number', $unsafe)
                ->assertJsonPath('data.contact_telephone.dial_number', null);
        }
        $telephone->update(['number' => '+49 (30) 12345678']);
        $this->getJson($this->url($order))->assertOk()
            ->assertJsonPath('data.contact_telephone.dial_number', '+493012345678');
    }

    public function test_delivery_address_and_contact_edits_invalidate_preparation_even_without_an_order_edit(): void
    {
        $order = $this->order();
        $building = Building::query()->create(['address' => 'Ленина, 10']);
        $telephone = Telephone::query()->create(['number' => '+79171234567']);
        $order->buildings()->attach($building->id, ['role' => 'delivery', 'position' => 0]);
        $order->update(['contact_telephone_id' => $telephone->id]);
        foreach ([[$building, ['address' => 'Ленина, 20']], [$telephone, ['number' => '+79171234568']]] as [$model, $attributes]) {
            $prepared = $this->prepare($order);
            $model->update($attributes);
            $this->postJson($this->url($order).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])
                ->assertConflict();
            $current = $this->getJson($this->url($order))->assertOk()
                ->assertJsonPath('data.workflow_status', 'awaiting')->assertJsonPath('data.can_ship', false)->json('data');
            $this->assertNotSame($prepared['version'], $current['version']);
            $this->assertContains('order_changed', array_column($current['warnings'], 'code'));
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_stock_requests', 0);
        $this->assertEquals(10, $this->balance());
    }

    public function test_temporary_mobile_setting_allows_shortage_and_zero_stock_with_idempotent_existing_ledger_posting(): void
    {
        $this->assertTrue(config('mobile.allow_negative_stock'));
        $otherGood = Good::query()->create(['name' => 'Какао без остатка']);
        $order = $this->order(13);
        $order->items()->create(['good_id' => $otherGood->id, 'good_name' => $otherGood->name, 'quantity' => 2, 'price_gross' => 50, 'line_total' => 100, 'currency_code' => 'RUB']);
        $order->update(['total_amount' => 1400]);
        $prepared = $this->prepare($order);
        $this->assertTrue($prepared['allow_negative_stock']);
        $this->assertTrue($prepared['can_ship']);
        $this->assertSame(2, collect($prepared['warnings'])->where('code', 'insufficient_stock')->count());
        $payload = ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()];
        $saleId = $this->postJson($this->url($order).'/ship', $payload)->assertOk()
            ->assertJsonPath('data.sale.total', 1400)->json('data.sale.id');
        $this->assertEquals(-3, $this->balance());
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $saleId, 'good_id' => $this->good->id, 'quantity_delta' => -13, 'unit_price' => 20]);
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $saleId, 'good_id' => $otherGood->id, 'quantity_delta' => -2, 'unit_price' => 0]);

        // A policy change must not turn a successful retry into another posting.
        config()->set('mobile.allow_negative_stock', false);
        $this->postJson($this->url($order).'/ship', $payload)->assertOk()->assertJsonPath('data.sale.id', $saleId);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('good_sale', 2);
        $this->assertDatabaseCount('sale_stock_requests', 1);
        $this->assertSame(2, GoodStockMovement::query()->where('sale_id', $saleId)->count());
        $this->assertEquals(-3, $this->balance());
    }

    public function test_shipping_creates_one_sale_with_costed_stock_and_audited_order_link(): void
    {
        $order = $this->order();
        $prepared = $this->prepare($order);
        $payload = ['request_id' => (string) Str::uuid(), 'version' => $prepared['version']];
        $saleId = $this->postJson($this->url($order).'/ship', $payload)->assertOk()
            ->assertJsonPath('data.workflow_status', 'shipped')
            ->assertJsonPath('data.can_ship', false)
            ->assertJsonPath('data.can_prepare', false)
            ->assertJsonPath('data.sale.total', 300)
            ->assertJsonPath('data.shipped_by.id', $this->employee->id)
            ->json('data.sale.id');

        $this->assertDatabaseHas('sales', ['id' => $saleId, 'entity_id' => $this->buyer->id, 'total' => 300, 'outstanding_amount' => 300]);
        $this->assertDatabaseHas('good_sale', ['sale_id' => $saleId, 'good_id' => $this->good->id, 'measure_id' => $this->measure->id, 'quantity' => 3, 'price' => 100, 'total' => 300]);
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $saleId, 'good_id' => $this->good->id, 'warehouse_id' => $this->warehouse->id, 'measure_id' => $this->measure->id, 'quantity_delta' => -3, 'unit_price' => 20, 'source_type' => GoodStockMovement::SOURCE_GOOD_SALE]);
        $this->assertSame(OrderStatus::CLOSED, $order->fresh()->status->code);
        $this->assertNotNull($order->fresh()->shipped_at);
        $this->assertEquals(7, $this->balance());

        $this->postJson($this->url($order).'/ship', $payload)->assertOk()->assertJsonPath('data.sale.id', $saleId);
        $payload['request_id'] = strtoupper($payload['request_id']);
        $this->postJson($this->url($order).'/ship', $payload)->assertOk()->assertJsonPath('data.sale.id', $saleId);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('good_sale', 1);
        $this->assertDatabaseCount('sale_stock_requests', 1);
        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $saleId)->count());
        $this->assertEquals(7, $this->balance());
    }

    public function test_another_request_or_changed_version_cannot_ship_an_order_twice(): void
    {
        $order = $this->order();
        $prepared = $this->prepare($order);
        $payload = ['request_id' => (string) Str::uuid(), 'version' => $prepared['version']];
        $shipped = $this->postJson($this->url($order).'/ship', $payload)->assertOk()->json('data');
        $this->postJson($this->url($order).'/ship', [...$payload, 'request_id' => (string) Str::uuid()])->assertConflict();
        $this->postJson($this->url($order).'/ship', [...$payload, 'version' => $shipped['version']])->assertConflict();
        $other = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $other->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->withToken($other->createToken('mobile:other', ['mobile:orders'], now()->addHour())->plainTextToken)
            ->postJson($this->url($order).'/ship', $payload)->assertConflict();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_stock_requests', 1);
        $this->assertEquals(7, $this->balance());
    }

    public function test_ship_request_requires_preparation_and_uuid(): void
    {
        $order = $this->order();
        $version = $this->getJson($this->url($order))->json('data.version');
        $this->postJson($this->url($order).'/ship', ['version' => $version, 'request_id' => 'fixed-key'])
            ->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->postJson($this->url($order).'/ship', ['version' => $version, 'request_id' => (string) Str::uuid()])->assertConflict();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_stock_requests', 0);
    }

    public function test_partial_wrong_or_foreign_quantities_and_missing_measures_cannot_be_prepared(): void
    {
        $order = $this->order();
        $data = $this->getJson($this->url($order))->json('data');
        $line = ['id' => $data['items'][0]['id'], 'measure_id' => $this->measure->id, 'quantity' => 3];
        foreach ([
            [],
            [[...$line, 'quantity' => 2]],
            [[...$line, 'quantity' => 3.0001]],
            [[...$line, 'id' => 999999]],
            [[...$line, 'measure_id' => null]],
            [$line, $line],
        ] as $items) {
            $this->patchJson($this->url($order).'/prepare', ['version' => $data['version'], 'items' => $items])->assertUnprocessable();
        }
        $this->assertNull($order->fresh()->prepared_at);
        $this->assertNull($order->items()->sole()->measure_id);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_stale_version_detects_changes_even_with_the_same_order_timestamp(): void
    {
        $order = $this->order();
        $prepared = $this->prepare($order);
        // Simulate another writer changing persisted line data without touching orders.updated_at.
        DB::table('order_items')->where('order_id', $order->id)->update(['price_gross' => 101, 'line_total' => 303]);
        DB::table('orders')->where('id', $order->id)->update(['total_amount' => 303]);
        $this->postJson($this->url($order).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])->assertConflict();
        $current = $this->getJson($this->url($order))->assertOk()->assertJsonPath('data.workflow_status', 'awaiting')->json('data');
        $this->assertContains('order_changed', array_column($current['warnings'], 'code'));
        $this->assertNotSame($prepared['version'], $current['version']);
        $this->postJson($this->url($order).'/ship', ['version' => $current['version'], 'request_id' => (string) Str::uuid()])->assertConflict();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_normal_order_edit_invalidates_assembly_and_shipped_orders_cannot_be_edited_or_deleted(): void
    {
        $order = $this->order();
        $prepared = $this->prepare($order);
        app(OrderWriter::class)->save($order, $this->orderData(4));
        $data = $this->getJson($this->url($order))->assertOk()->assertJsonPath('data.workflow_status', 'awaiting')->json('data');
        $this->assertContains('order_changed', array_column($data['warnings'], 'code'));
        $this->assertNull($order->fresh()->prepared_at);
        $this->postJson($this->url($order).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])->assertConflict();

        $prepared = $this->prepare($order);
        $this->postJson($this->url($order).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])->assertOk();
        $this->withHeader('Authorization', '')->putJson('/api/orders/'.$order->id, $this->orderData(1))->assertConflict();
        $this->deleteJson('/api/orders/'.$order->id)->assertConflict();
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertEquals(4, $order->items()->sole()->quantity);
        $this->assertEquals(6, $this->balance());
    }

    public function test_shortage_rolls_back_all_lines_sale_request_and_order_and_allows_retry_after_receipt(): void
    {
        config()->set('mobile.allow_negative_stock', false);
        $otherGood = Good::query()->create(['name' => 'Какао']);
        $order = $this->order();
        $order->items()->create(['good_id' => $otherGood->id, 'good_name' => $otherGood->name, 'quantity' => 2, 'price_gross' => 50, 'line_total' => 100, 'currency_code' => 'RUB']);
        $order->update(['total_amount' => 400]);
        $prepared = $this->prepare($order);
        $this->assertFalse($prepared['allow_negative_stock']);
        $this->assertFalse($prepared['can_ship']);
        $this->assertContains('insufficient_stock', array_column($prepared['warnings'], 'code'));
        $payload = ['version' => $prepared['version'], 'request_id' => (string) Str::uuid(), 'allow_negative_stock' => true];
        $this->postJson($this->url($order).'/ship', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_sale', 0);
        $this->assertDatabaseCount('sale_stock_requests', 0);
        $this->assertDatabaseCount('good_stock_movements', 1);
        $this->assertNull($order->fresh()->shipped_sale_id);
        $this->assertEquals(10, $this->balance());

        $this->receipt($otherGood, 2, 15);
        $this->postJson($this->url($order).'/ship', $payload)->assertOk()->assertJsonPath('data.sale.total', 400);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('good_sale', 2);
        $this->assertEquals(7, $this->balance());
    }

    public function test_existing_sale_endpoints_cannot_append_goods_to_a_shipped_order_sale(): void
    {
        $order = $this->order();
        $prepared = $this->prepare($order);
        $saleId = $this->postJson($this->url($order).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])
            ->assertOk()->json('data.sale.id');
        $this->withHeader('Authorization', '')->actingAs($this->employee);
        foreach (["/api/sales/{$saleId}/goods", '/api/goodsales', '/web/goodsale/store'] as $endpoint) {
            $this->postJson($endpoint, ['sale_id' => $saleId, 'good_id' => $this->good->id, 'measure_id' => $this->measure->id, 'quantity' => 1, 'price' => 100])
                ->assertConflict();
        }
        $this->assertDatabaseCount('good_sale', 1);
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total' => 300]);
        $this->assertEquals(7, $this->balance());
    }

    public function test_stock_consumed_by_another_order_is_rechecked_when_shipping(): void
    {
        config()->set('mobile.allow_negative_stock', false);
        $first = $this->order(6);
        $second = $this->order(6);
        $firstData = $this->prepare($first);
        $secondData = $this->prepare($second);
        $this->assertTrue($firstData['can_ship']);
        $this->assertTrue($secondData['can_ship']);
        $this->postJson($this->url($first).'/ship', ['version' => $firstData['version'], 'request_id' => (string) Str::uuid()])->assertOk();
        $this->postJson($this->url($second).'/ship', ['version' => $secondData['version'], 'request_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertNull($second->fresh()->shipped_sale_id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertEquals(4, $this->balance());
    }

    public function test_stock_is_not_summed_across_different_measures_or_warehouses(): void
    {
        config()->set('mobile.allow_negative_stock', false);
        $otherMeasure = Measure::query()->create(['name' => 'мешок']);
        $otherWarehouse = Warehouse::query()->create(['name' => 'Другой', 'code' => 'other-mobile', 'is_active' => true]);
        GoodStockMovement::query()->create(['warehouse_id' => $otherWarehouse->id, 'good_id' => $this->good->id, 'measure_id' => $this->measure->id, 'type' => GoodStockMovement::TYPE_RECEIPT, 'quantity_delta' => 100, 'unit_price' => 10, 'moved_at' => now()->toDateString()]);
        GoodStockMovement::query()->create(['warehouse_id' => $this->warehouse->id, 'good_id' => $this->good->id, 'measure_id' => $otherMeasure->id, 'type' => GoodStockMovement::TYPE_RECEIPT, 'quantity_delta' => 100, 'unit_price' => 10, 'moved_at' => now()->toDateString()]);
        $order = $this->order(11);
        $prepared = $this->prepare($order);
        $this->assertEquals(10, $prepared['items'][0]['available_quantity']);
        $this->assertFalse($prepared['can_ship']);
        $this->postJson($this->url($order).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_missing_prices_unsupported_currency_and_closed_or_deferred_orders_are_blocked(): void
    {
        foreach ([OrderStatus::CLOSED, OrderStatus::DEFERRED] as $status) {
            $order = $this->order();
            $order->update(['order_status_id' => OrderStatus::query()->where('code', $status)->sole()->id]);
            $this->assertPreparationBlocked($order, 'order_not_open');
        }
        $order = $this->order();
        $order->update(['currency_code' => 'EUR']);
        $this->assertPreparationBlocked($order, 'unsupported_currency');
        $order = $this->order();
        $order->items()->update(['price_gross' => null]);
        $this->assertPreparationBlocked($order, 'invalid_price');
        $order = $this->order();
        $order->update(['total_amount' => 1]);
        $this->assertPreparationBlocked($order, 'invalid_total');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_missing_goods_customer_or_inactive_warehouse_are_explained_and_blocked(): void
    {
        $order = $this->order();
        $order->items()->update(['good_id' => null]);
        $this->assertPreparationBlocked($order, 'missing_good');
        $order = $this->order();
        $order->update(['entity_id' => null]);
        $this->assertPreparationBlocked($order, 'missing_customer');
        $order = $this->order();
        $this->warehouse->update(['is_active' => false]);
        $this->assertPreparationBlocked($order, 'missing_warehouse');
    }

    public function test_list_supports_search_pagination_and_fulfillment_filters(): void
    {
        $awaiting = $this->order();
        $ready = $this->order();
        $this->prepare($ready);
        $shipped = $this->order();
        $prepared = $this->prepare($shipped);
        $this->postJson($this->url($shipped).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])->assertOk();
        $awaiting->update(['submitted_at' => now()->subDay()]);

        $closed = $this->order();
        $closed->update(['order_status_id' => OrderStatus::query()->where('code', OrderStatus::CLOSED)->sole()->id, 'submitted_at' => now()->subDay()]);
        $deferred = $this->order();
        $deferred->update(['order_status_id' => OrderStatus::query()->where('code', OrderStatus::DEFERRED)->sole()->id, 'submitted_at' => now()->subDay()]);

        foreach (['awaiting' => $awaiting, 'ready' => $ready, 'shipped' => $shipped] as $filter => $order) {
            $this->getJson('/api/mobile/v1/orders?filter='.$filter)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $order->id);
        }
        $this->getJson('/api/mobile/v1/orders?filter=today')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/mobile/v1/orders?search='.urlencode($ready->number))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ready->id);
        $this->getJson('/api/mobile/v1/orders?search='.urlencode($this->buyer->name).'&per_page=2&page=2')->assertOk()
            ->assertJsonPath('meta.total', 5)->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.last_page', 3)->assertJsonCount(2, 'data');
    }

    public function test_fractional_kopecks_cannot_silently_change_the_order_total_when_creating_a_sale(): void
    {
        $secondGood = Good::query()->create(['name' => 'Товар с дробной ценой']);
        $order = app(OrderWriter::class)->save(null, [
            ...$this->orderData(1),
            'items' => [
                ['good_id' => $this->good->id, 'quantity' => 1, 'unit_price' => 0.005],
                ['good_id' => $secondGood->id, 'quantity' => 1, 'unit_price' => 0.005],
            ],
        ]);
        $this->assertEquals(0.01, $order->total_amount);
        // Existing Sale convention would charge 0.01 + 0.01 = 0.02.
        $this->assertPreparationBlocked($order, 'rounding_mismatch');

        $subFourDecimalOrder = app(OrderWriter::class)->save(null, [
            ...$this->orderData(1),
            'items' => [['good_id' => $this->good->id, 'quantity' => 0.031, 'unit_price' => 0.16]],
        ]);
        $this->assertEquals(0.005, $subFourDecimalOrder->total_amount);
        // 0.00496 rounds directly to 0.00 for a Sale; the four-decimal Order would show 0.01.
        $this->assertPreparationBlocked($subFourDecimalOrder, 'rounding_mismatch');
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_stock_requests', 0);
        $this->assertEquals(10, $this->balance());
    }

    public function test_fractional_kopecks_are_allowed_when_existing_sale_rounding_preserves_the_payable_total(): void
    {
        $order = app(OrderWriter::class)->save(null, [
            ...$this->orderData(1),
            'items' => [['good_id' => $this->good->id, 'quantity' => 1, 'unit_price' => 0.005]],
        ]);
        $prepared = $this->prepare($order);
        $this->postJson($this->url($order).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('data.sale.total', 0.01)->assertJsonPath('data.total_amount', 0.005);
        $this->assertEquals(0.005, $order->fresh()->total_amount);
        $this->assertDatabaseHas('sales', ['total' => 0.01, 'outstanding_amount' => 0.01]);
        $this->assertDatabaseHas('good_sale', ['price' => 0.005, 'quantity' => 1]);
        $this->assertEquals(9, $this->balance());
    }

    public function test_fulfillment_migration_can_roll_back_without_losing_existing_orders_or_stock(): void
    {
        $order = $this->order();
        $migration = require database_path('migrations/2026_09_21_120100_add_order_fulfillment.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('orders', 'shipped_sale_id'));
        $this->assertFalse(Schema::hasColumn('order_items', 'measure_id'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total_amount' => 300]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 3]);
        $this->assertEquals(10, $this->balance());
        $migration->up();
        $this->assertTrue(Schema::hasColumn('orders', 'shipped_sale_id'));
        $this->assertTrue(Schema::hasColumn('order_items', 'measure_id'));
        $this->assertNull($order->fresh()->shipped_sale_id);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 3]);
        $this->assertEquals(10, $this->balance());
    }

    public function test_fulfillment_migration_refuses_rollback_after_posting_a_shipment(): void
    {
        $order = $this->order();
        $prepared = $this->prepare($order);
        $saleId = $this->postJson($this->url($order).'/ship', ['version' => $prepared['version'], 'request_id' => (string) Str::uuid()])
            ->assertOk()->json('data.sale.id');
        $migration = require database_path('migrations/2026_09_21_120100_add_order_fulfillment.php');
        try {
            $migration->down();
            $this->fail('Rollback must preserve posted shipment provenance.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Откат запрещён: существуют отгруженные заказы.', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('orders', 'shipped_sale_id'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'shipped_sale_id' => $saleId]);
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $saleId, 'quantity_delta' => -3]);
        $this->assertEquals(7, $this->balance());
    }

    private function assertPreparationBlocked(Order $order, string $warning): void
    {
        $data = $this->getJson($this->url($order))->assertOk()->assertJsonPath('data.can_prepare', false)->json('data');
        $this->assertContains($warning, array_column($data['warnings'], 'code'));
        $this->patchJson($this->url($order).'/prepare', $this->preparePayload($data))->assertUnprocessable();
        $this->assertNull($order->fresh()->prepared_at);
    }

    private function order(float $quantity = 3): Order
    {
        return app(OrderWriter::class)->save(null, $this->orderData($quantity));
    }

    private function orderData(float $quantity): array
    {
        return [
            'entity_id' => $this->buyer->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->sole()->id,
            'created_by_user_id' => $this->employee->id,
            'currency_code' => 'RUB',
            'items' => [['good_id' => $this->good->id, 'quantity' => $quantity, 'unit_price' => 100]],
        ];
    }

    private function prepare(Order $order): array
    {
        $data = $this->getJson($this->url($order))->assertOk()->json('data');

        return $this->patchJson($this->url($order).'/prepare', $this->preparePayload($data))->assertOk()->json('data');
    }

    private function preparePayload(array $data): array
    {
        return [
            'version' => $data['version'],
            'items' => array_map(fn (array $item) => ['id' => $item['id'], 'measure_id' => $this->measure->id, 'quantity' => $item['quantity']], $data['items']),
        ];
    }

    private function receipt(Good $good, float $quantity, float $cost): void
    {
        GoodStockMovement::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'good_id' => $good->id,
            'measure_id' => $this->measure->id,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => $quantity,
            'unit_price' => $cost,
            'moved_at' => now()->toDateString(),
        ]);
    }

    private function url(Order $order): string
    {
        return '/api/mobile/v1/orders/'.$order->id;
    }

    private function balance(): float
    {
        return (float) DB::table('good_stock_movements')->where('warehouse_id', $this->warehouse->id)->where('good_id', $this->good->id)->where('measure_id', $this->measure->id)->sum('quantity_delta');
    }
}
