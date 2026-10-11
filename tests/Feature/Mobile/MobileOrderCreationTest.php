<?php

namespace Tests\Feature\Mobile;

use App\Models\Building;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Telephone;
use App\Models\User;
use App\Services\Mobile\MobileAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private Entity $buyer;

    private Good $good;

    private Building $building;

    private Telephone $telephone;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->employee = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->employee->givePermissionTo([
            Permission::findOrCreate('warehouse.move', 'crm'),
            Permission::findOrCreate('orders.create', 'crm'),
        ]);
        $this->buyer = Entity::query()->create(['name' => 'Покупатель доставки', 'INN' => '7701234567']);
        $this->good = Good::query()->create([
            'name' => 'Какао в коробках',
            'measure_id' => Measure::query()->firstOrCreate(['name' => 'коробка'])->id,
            'unit_weight_kg' => 10,
        ]);
        $this->building = Building::query()->create(['address' => 'Складская 1']);
        $this->telephone = Telephone::query()->create(['number' => '+79991234567']);
        $this->buyer->buildings()->attach($this->building);
        $this->buyer->telephones()->attach($this->telephone);
        $this->mobile();
    }

    public function test_creates_an_order_with_delivery_details_accounting_units_and_author(): void
    {
        $apartment = $this->building->apartments()->create(['number' => '12', 'type' => 'office']);
        $data = [...$this->data(), 'building_apartments' => [$this->building->id => $apartment->id]];
        $created = $this->postJson('/api/mobile/v1/orders', $data)->assertCreated()
            ->assertJsonPath('data.entity.id', $this->buyer->id)
            ->assertJsonPath('data.delivery_date', '2028-02-29')
            ->assertJsonPath('data.contact_telephone.id', $this->telephone->id)
            ->assertJsonPath('data.delivery_addresses.0.id', $this->building->id)
            ->assertJsonPath('data.delivery_addresses.0.apartment_id', $apartment->id)
            ->assertJsonPath('data.items.0.quantity', 2.5)
            ->assertJsonPath('data.items.0.price', 125.5)
            ->assertJsonPath('data.items.0.measurement.unit_label', 'коробка')
            ->assertJsonPath('data.total_amount', 313.75)
            ->assertJsonPath('data.workflow_status', 'awaiting');
        $this->assertDatabaseHas('orders', [
            'id' => $created->json('data.id'), 'created_by_user_id' => $this->employee->id,
            'preferred_delivery_time' => 'С 10 до 12', 'internal_comment' => 'Позвонить заранее',
        ]);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
    }

    public function test_retries_return_the_same_order_and_reject_changed_payloads(): void
    {
        $data = $this->data();
        $id = $this->postJson('/api/mobile/v1/orders', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/mobile/v1/orders', $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/mobile/v1/orders', [...$data, 'internal_comment' => 'Другой заказ'])->assertConflict();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('mobile_order_creation_requests', 1);

        Order::query()->findOrFail($id)->delete();
        $this->postJson('/api/mobile/v1/orders', $data)->assertStatus(410);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_idempotency_is_scoped_to_the_authenticated_user(): void
    {
        $data = $this->data();
        $first = $this->postJson('/api/mobile/v1/orders', $data)->assertCreated()->json('data.id');
        $admin = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $admin->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->mobile($admin);
        $second = $this->postJson('/api/mobile/v1/orders', $data)->assertCreated()->json('data.id');
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_replay_precedes_unique_number_and_changed_catalog_validation(): void
    {
        $data = [...$this->data(), 'number' => 'MOBILE-RETRY-1'];
        $id = $this->postJson('/api/mobile/v1/orders', $data)->assertCreated()->json('data.id');
        $this->good->delete();
        $this->buyer->telephones()->detach($this->telephone);
        $this->postJson('/api/mobile/v1/orders', array_reverse($data, true))->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_creation_permission_is_required_for_options_and_store_and_can_be_revoked(): void
    {
        $this->getJson('/api/mobile/v1/auth/me')->assertOk()->assertJsonPath('capabilities.can_create_orders', true);
        $this->employee->revokePermissionTo('orders.create');
        $this->getJson('/api/mobile/v1/auth/me')->assertOk()->assertJsonPath('capabilities.can_create_orders', false);
        foreach (['/order-options', '/order-options/entities', '/order-options/goods'] as $path) {
            $this->getJson('/api/mobile/v1'.$path)->assertForbidden();
        }
        $this->postJson('/api/mobile/v1/orders', $this->data())->assertForbidden();
        $this->getJson('/api/mobile/v1/orders')->assertOk();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_mobile_creation_requires_an_expiring_mobile_token_and_keeps_legacy_api_scoped(): void
    {
        $this->postJson('/api/orders', $this->data())->assertForbidden();
        $this->getJson('/api/orders/options')->assertForbidden();
        $this->flushHeaders();
        Auth::forgetGuards();
        $this->actingAs($this->employee);
        $this->getJson('/api/mobile/v1/order-options')->assertUnauthorized();
        $this->postJson('/api/mobile/v1/orders', $this->data())->assertUnauthorized();
    }

    public function test_customer_picker_searches_paginates_and_only_returns_delivery_references(): void
    {
        Entity::query()->create(['name' => 'Не подходит']);
        Building::query()->create(['address' => 'Чужой адрес']);
        $apartment = $this->building->apartments()->create(['number' => '7', 'type' => 'apartment']);
        $this->buyer->buildings()->updateExistingPivot($this->building->id, ['apartment_id' => $apartment->id]);
        $this->getJson('/api/mobile/v1/order-options/entities?search=7701234567&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $this->buyer->id)
            ->assertJsonCount(1, 'data.0.buildings')->assertJsonCount(1, 'data.0.telephones')
            ->assertJsonPath('data.0.buildings.0.apartment_id', $apartment->id)
            ->assertJsonPath('data.0.buildings.0.apartments.0.label', 'кв. 7')
            ->assertJsonMissingPath('data.0.bank_account_number')
            ->assertJsonMissingPath('data.0.emails');
        $this->getJson('/api/mobile/v1/order-options/entities?per_page=1&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/mobile/v1/order-options/entities?per_page=10000')->assertUnprocessable();
    }

    public function test_goods_picker_exposes_current_prices_measurement_and_currency_fallback(): void
    {
        Good::query()->create(['name' => 'Другой товар']);
        $currency = Currency::query()->forceCreate(['name' => 'Евро', 'code' => 'EUR']);
        $type = PriceType::query()->create(['name' => 'Оптовая', 'code' => 'mobile-wholesale', 'currency_id' => $currency->id, 'is_active' => true]);
        GoodPriceTypeValue::query()->create([
            'good_id' => $this->good->id, 'price_type_id' => $type->id, 'price_gross' => 123.5,
            'valid_from' => today(), 'valid_to' => today(),
        ]);
        $expired = PriceType::query()->create(['name' => 'Архив', 'code' => 'mobile-expired', 'is_active' => true]);
        GoodPriceTypeValue::query()->create([
            'good_id' => $this->good->id, 'price_type_id' => $expired->id, 'price_gross' => 1,
            'valid_to' => today()->subDay(),
        ]);
        $this->getJson('/api/mobile/v1/order-options/goods?search='.urlencode('Какао'))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $this->good->id)
            ->assertJsonPath('data.0.measurement.unit_label', 'коробка')
            ->assertJsonPath('data.0.measurement.kilograms_per_unit', 10)
            ->assertJsonCount(1, 'data.0.prices')
            ->assertJsonPath('data.0.prices.0.unit_price', 123.5)
            ->assertJsonPath('data.0.prices.0.currency_code', 'EUR');
        $this->getJson('/api/mobile/v1/order-options')->assertOk()
            ->assertJsonPath('default_status_id', OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id'))
            ->assertJsonFragment(['currency_codes' => ['EUR', 'RUB']]);
    }

    public function test_rejects_invalid_quantity_dates_customer_references_and_stale_measurements_atomically(): void
    {
        $data = $this->data();
        $otherBuilding = Building::query()->create(['address' => 'Чужой склад']);
        $otherTelephone = Telephone::query()->create(['number' => '+79997654321']);
        $otherApartment = $otherBuilding->apartments()->create(['number' => '9', 'type' => 'office']);
        $cases = [
            [['request_id' => 'invalid'], 'request_id'],
            [['delivery_date' => '2027-02-29'], 'delivery_date'],
            [['contact_telephone_id' => $otherTelephone->id], 'contact_telephone_id'],
            [['building_ids' => [$otherBuilding->id]], 'building_ids'],
            [['building_apartments' => [$this->building->id => $otherApartment->id]], 'building_apartments.'.$this->building->id],
            [['items' => [[...$data['items'][0], 'quantity' => 0]]], 'items.0.quantity'],
            [['items' => [[...$data['items'][0], 'quantity' => 1.1234]]], 'items.0.quantity'],
            [['items' => [[...$data['items'][0], 'measurement' => [...$this->good->measurement(), 'kilograms_per_unit' => 20]]]], 'items.0.measure_id'],
            [['order_status_id' => OrderStatus::query()->where('code', OrderStatus::CLOSED)->value('id')], 'order_status_id'],
        ];
        foreach ($cases as [$changes, $key]) {
            $this->postJson('/api/mobile/v1/orders', [...$data, ...$changes])->assertUnprocessable()->assertJsonValidationErrors($key);
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('mobile_order_creation_requests', 0);
        }
        $this->postJson('/api/mobile/v1/orders', $data)->assertCreated();
    }

    private function data(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'entity_id' => $this->buyer->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id'),
            'contact_telephone_id' => $this->telephone->id,
            'building_ids' => [$this->building->id],
            'delivery_date' => '2028-02-29',
            'preferred_delivery_time' => 'С 10 до 12',
            'internal_comment' => 'Позвонить заранее',
            'items' => [[
                'good_id' => $this->good->id, 'quantity' => 2.5, 'unit_price' => 125.5,
                'currency_code' => 'RUB', 'measure_id' => $this->good->measure_id, 'measurement' => $this->good->measurement(),
            ]],
        ];
    }

    private function mobile(?User $user = null): void
    {
        Auth::forgetGuards();
        $token = ($user ?? $this->employee)->createToken('mobile:creation-test', [MobileAccess::ABILITY], now()->addHour());
        $this->withToken($token->plainTextToken);
    }
}
