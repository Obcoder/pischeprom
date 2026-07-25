<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PriceType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_schema_is_normalized_around_relations(): void
    {
        foreach ([
            'entity_id',
            'order_status_id',
            'created_by_user_id',
            'contact_telephone_id',
            'total_amount',
            'currency_code',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column));
        }

        foreach ([
            'status',
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_entity_name',
            'delivery_address',
            'metadata',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('orders', $column));
        }

        $this->assertDatabaseHas('order_statuses', ['code' => OrderStatus::OPEN]);
        $this->assertDatabaseHas('order_statuses', ['code' => OrderStatus::DEFERRED]);
        $this->assertDatabaseHas('order_statuses', ['code' => OrderStatus::CLOSED]);
        $this->assertTrue(Schema::hasTable('building_order'));
    }

    public function test_normalization_migration_can_resume_without_resetting_existing_statuses(): void
    {
        $entity = Entity::query()->create(['name' => 'Entity для повторной миграции']);
        $deferredStatus = OrderStatus::query()
            ->where('code', OrderStatus::DEFERRED)
            ->firstOrFail();
        $order = Order::query()->create([
            'entity_id' => $entity->id,
            'order_status_id' => $deferredStatus->id,
            'total_amount' => 100,
            'currency_code' => 'RUB',
        ]);

        $migration = require database_path(
            'migrations/2026_07_25_130000_normalize_orders_architecture.php'
        );
        $migration->up();

        $this->assertSame(
            OrderStatus::DEFERRED,
            $order->fresh()->status->code
        );
    }

    public function test_order_crud_recalculates_goods_totals_and_syncs_logistics(): void
    {
        $entity = Entity::query()->create(['name' => 'ООО Покупатель']);
        $building = Building::query()->create(['address' => 'Складская, 10']);
        $firstGood = Good::query()->create([
            'name' => 'Лецитин',
            'denominator' => 2.5,
            'is_published' => true,
        ]);
        $secondGood = Good::query()->create([
            'name' => 'Какао',
            'denominator' => 1,
            'is_published' => true,
        ]);
        $openStatus = OrderStatus::query()->where('code', OrderStatus::OPEN)->firstOrFail();
        $deferredStatus = OrderStatus::query()->where('code', OrderStatus::DEFERRED)->firstOrFail();

        $created = $this->postJson('/api/orders', [
            'entity_id' => $entity->id,
            'order_status_id' => $openStatus->id,
            'building_ids' => [$building->id],
            'currency_code' => 'RUB',
            'preferred_delivery_time' => 'Завтра после 12:00',
            'items' => [
                [
                    'good_id' => $firstGood->id,
                    'quantity' => 2,
                    'unit_price' => 150.5,
                ],
                [
                    'good_id' => $secondGood->id,
                    'quantity' => 3,
                    'unit_price' => 20,
                ],
            ],
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('data.status.code', OrderStatus::OPEN)
            ->assertJsonPath('data.entity.id', $entity->id)
            ->assertJsonPath('data.total_amount', 361)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(1, 'data.buildings');

        $orderId = $created->json('data.id');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'good_id' => $firstGood->id,
            'quantity' => 2,
            'price_gross' => 150.5,
            'line_total' => 301,
        ]);
        $this->assertDatabaseHas('building_order', [
            'order_id' => $orderId,
            'building_id' => $building->id,
            'role' => 'delivery',
        ]);
        $this->assertDatabaseHas('building_entities', [
            'entity_id' => $entity->id,
            'building_id' => $building->id,
        ]);

        $this->getJson('/api/orders?status=open&entity_id='.$entity->id.'&sort_by=total_amount&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $orderId);

        $updated = $this->putJson('/api/orders/'.$orderId, [
            'number' => $created->json('data.number'),
            'entity_id' => $entity->id,
            'order_status_id' => $deferredStatus->id,
            'building_ids' => [$building->id],
            'currency_code' => 'RUB',
            'items' => [
                [
                    'good_id' => $firstGood->id,
                    'quantity' => 4,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $updated
            ->assertOk()
            ->assertJsonPath('data.status.code', OrderStatus::DEFERRED)
            ->assertJsonPath('data.total_amount', 400)
            ->assertJsonCount(1, 'data.items');

        $this->assertCount(1, $entity->fresh()->orders);
        $this->assertCount(1, $firstGood->fresh()->orders);
        $this->assertCount(1, $building->fresh()->orders);

        $this->deleteJson('/api/orders/'.$orderId)->assertNoContent();
        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $orderId]);
        $this->assertDatabaseMissing('building_order', ['order_id' => $orderId]);
    }

    public function test_customer_order_uses_user_entity_and_creates_delivery_building(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Покупатель',
            'email' => 'buyer@example.com',
            'phone' => '+79991234567',
        ]);
        $entity = Entity::query()->create(['name' => 'Покупатель Entity']);
        $user->entities()->attach($entity->id, [
            'role' => 'owner',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $currency = Currency::query()->forceCreate([
            'name' => 'Российский рубль',
            'code' => 'RUB',
        ]);
        $priceType = PriceType::query()->create([
            'name' => 'Розница',
            'code' => 'retail',
            'currency_id' => $currency->id,
            'is_active' => true,
            'is_public' => true,
        ]);
        $good = Good::query()->create([
            'name' => 'Заказной товар',
            'denominator' => 5,
            'is_published' => true,
        ]);
        GoodPriceTypeValue::query()->create([
            'good_id' => $good->id,
            'price_type_id' => $priceType->id,
            'currency_id' => $currency->id,
            'price_gross' => 250,
            'is_published' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/orders', [
                'items' => [
                    [
                        'good_id' => $good->id,
                        'quantity' => 2,
                    ],
                ],
                'delivery_address' => 'Невский проспект, 1',
                'preferred_delivery_time' => 'В рабочее время',
                'customer_phone' => '8 (999) 123-45-67',
                'customer_phone_source' => 'profile',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::OPEN)
            ->assertJsonPath('order.total_amount', 500)
            ->assertJsonPath('redirect', route('dashboard'));

        $order = Order::query()->with(['entity', 'buildings', 'items'])->firstOrFail();

        $this->assertSame($entity->id, $order->entity_id);
        $this->assertSame($user->id, $order->created_by_user_id);
        $this->assertSame('Невский проспект, 1', $order->buildings->first()->address);
        $this->assertSame(500.0, $order->total_amount);
        $this->assertDatabaseHas('entity_telephone', [
            'entity_id' => $entity->id,
            'telephone_id' => $order->contact_telephone_id,
        ]);
    }

    public function test_order_pages_dashboard_and_unit_expose_related_orders(): void
    {
        $entity = Entity::query()->create(['name' => 'Связанная Entity']);
        $unit = Unit::query()->create(['name' => 'Unit покупателя']);
        $unit->entities()->attach($entity);
        $good = Good::query()->create(['name' => 'Товар в заказе']);
        $status = OrderStatus::query()->where('code', OrderStatus::OPEN)->firstOrFail();
        $order = Order::query()->create([
            'entity_id' => $entity->id,
            'order_status_id' => $status->id,
            'total_amount' => 120,
            'currency_code' => 'RUB',
        ]);
        $order->items()->create([
            'good_id' => $good->id,
            'good_name' => $good->name,
            'quantity' => 1,
            'price_gross' => 120,
            'currency_code' => 'RUB',
            'line_total' => 120,
        ]);

        $this->get('/Ameise/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Orders/Index'));

        $this->get('/Ameise/orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Orders/Show')
                ->where('orderId', $order->id));

        $this->get('/Ameise/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Verwalter')
                ->where('ordersByStatus.open.0.id', $order->id));

        $loadedUnit = Unit::query()
            ->with('entities.orders.items.good')
            ->findOrFail($unit->id);

        $this->assertSame($order->id, $loadedUnit->entities->first()->orders->first()->id);
        $this->assertSame($good->id, $loadedUnit->entities->first()->orders->first()->items->first()->good->id);
    }

    public function test_order_control_panel_and_api_are_available_without_authentication(): void
    {
        $this->get('/Ameise/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Orders/Index')
                ->where('permissions.view', true)
                ->where('permissions.create', true)
                ->where('auth.user', null)
                ->where('auth.permissions.orders.view', true));
        $this->get('/Ameise/orders/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Orders/Show')
                ->where('orderId', null)
                ->where('permissions.create', true));
        $this->getJson('/api/orders/options')->assertOk();
        $this->getJson('/api/orders')->assertOk();

        $order = Order::query()->create([
            'entity_id' => Entity::query()->create(['name' => 'Public Ameise Entity'])->id,
            'order_status_id' => OrderStatus::query()
                ->where('code', OrderStatus::OPEN)
                ->value('id'),
            'total_amount' => 10,
            'currency_code' => 'RUB',
        ]);

        $this->getJson('/api/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
        $this->deleteJson('/api/orders/'.$order->id)->assertNoContent();
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }
}
