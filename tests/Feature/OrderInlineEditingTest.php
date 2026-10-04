<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\City;
use App\Models\Country;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Region;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderInlineEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inline_status_preserves_order_contents_and_supports_closing_and_reopening(): void
    {
        $this->actingAs($this->employee());
        $order = $this->order();
        $building = Building::query()->create(['address' => 'Ленина, 10']);
        $order->buildings()->attach($building->id, ['role' => 'delivery', 'position' => 0]);
        $items = $order->items()->get()->toArray();
        $addresses = DB::table('building_order')->where('order_id', $order->id)->get()->toArray();
        $closedStatus = OrderStatus::query()->create(['code' => 'cancelled', 'name' => 'Отменён', 'is_closed' => true]);

        $closed = $this->patchJson($this->url($order), [
            'order_status_id' => $closedStatus->id,
            'expected_order_status_id' => $order->order_status_id,
            'internal_comment' => 'Не должно изменить комментарий',
            'delivery_date' => null,
            'items' => [],
            'building_ids' => [],
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'cancelled')
            ->assertJsonPath('data.status.is_closed', true)
            ->assertJsonPath('data.internal_comment', 'Сохранить комментарий')
            ->assertJsonPath('data.delivery_date', '2026-10-10')
            ->assertJsonPath('data.items_count', 1)
            ->json('data');
        $this->assertNotNull($closed['closed_at']);
        $this->assertSame($items, $order->items()->get()->toArray());
        $this->assertEquals($addresses, DB::table('building_order')->where('order_id', $order->id)->get()->toArray());
        $this->assertEquals(200, $order->fresh()->total_amount);

        $this->patchJson($this->url($order), [
            'order_status_id' => $order->order_status_id,
            'expected_order_status_id' => $closedStatus->id,
        ])->assertOk()->assertJsonPath('data.status.code', OrderStatus::OPEN)->assertJsonPath('data.closed_at', null);
    }

    public function test_status_changes_invalidate_preparation_without_replacing_items(): void
    {
        $employee = $this->employee();
        $this->actingAs($employee);
        $order = $this->order();
        $order->forceFill([
            'prepared_at' => now(),
            'prepared_fingerprint' => str_repeat('a', 64),
            'prepared_by_user_id' => $employee->id,
            'fulfillment_warehouse_id' => Warehouse::query()->where('code', Warehouse::GOODS_CODE)->value('id'),
        ])->save();
        $items = $order->items()->get()->toArray();

        $this->patchJson($this->url($order), [
            'order_status_id' => $order->order_status_id,
            'expected_order_status_id' => $order->order_status_id,
        ])->assertOk();
        $this->assertNotNull($order->fresh()->prepared_at);

        $this->patchJson($this->url($order), $this->statusPayload($order))->assertOk();
        $order->refresh();
        $this->assertNull($order->prepared_at);
        $this->assertNull($order->prepared_fingerprint);
        $this->assertNull($order->prepared_by_user_id);
        $this->assertNull($order->fulfillment_warehouse_id);
        $this->assertNotNull($order->preparation_invalidated_at);
        $this->assertSame($items, $order->items()->get()->toArray());
    }

    public function test_stale_status_changes_are_rejected_without_overwriting_the_current_order(): void
    {
        $this->actingAs($this->employee());
        $order = $this->order();
        $payload = $this->statusPayload($order);
        $this->patchJson($this->url($order), $payload)->assertOk();

        $this->patchJson($this->url($order), [
            ...$payload,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::CLOSED)->value('id'),
        ])->assertConflict();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $payload['order_status_id'], 'closed_at' => null]);
    }

    public function test_shipped_orders_cannot_change_status(): void
    {
        $this->actingAs($this->employee());
        $order = $this->order();
        $sale = Sale::query()->create(['entity_id' => $order->entity_id, 'date' => '2026-10-04', 'total' => 200]);

        foreach ([
            ['shipped_at' => now(), 'shipped_sale_id' => null],
            ['shipped_at' => null, 'shipped_sale_id' => $sale->id],
        ] as $shipment) {
            $order->forceFill($shipment)->save();
            $this->patchJson($this->url($order), $this->statusPayload($order))->assertConflict();
            $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $order->order_status_id]);
        }
    }

    public function test_status_endpoint_requires_an_active_staff_account(): void
    {
        $order = $this->order();
        $payload = $this->statusPayload($order);
        $this->patchJson($this->url($order), $payload)->assertUnauthorized();

        foreach ([
            ['type' => 'customer', 'status' => 'active'],
            ['type' => 'employee', 'status' => 'inactive'],
        ] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->patchJson($this->url($order), $payload)->assertForbidden();
        }

        $manager = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $manager->assignRole(Role::findOrCreate('manager', 'crm'));
        $this->actingAs($manager);
        $this->patchJson($this->url($order), $payload)->assertOk();
    }

    public function test_sanctum_staff_can_change_status_and_invalid_input_is_rejected(): void
    {
        Sanctum::actingAs($this->employee());
        $order = $this->order();
        $payload = $this->statusPayload($order);
        foreach ([
            ['order_status_id' => null],
            ['order_status_id' => 999999],
            ['expected_order_status_id' => null],
            ['expected_order_status_id' => 'open'],
        ] as $invalid) {
            $this->patchJson($this->url($order), array_replace($payload, $invalid))
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->patchJson($this->url($order), $payload)->assertOk();
    }

    public function test_dashboard_supplies_full_delivery_address_and_a_usable_delivery_version(): void
    {
        $employee = $this->employee();
        $employee->givePermissionTo(Permission::findOrCreate('orders.edit', 'crm'));
        $this->actingAs($employee);
        $order = $this->order();
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $region = Region::query()->create(['name' => 'Самарская область', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'Самара', 'region_id' => $region->id]);
        $building = Building::query()->create(['address' => 'Ленина, 10', 'city_id' => $city->id]);
        $apartment = $building->apartments()->create(['number' => '12А', 'type' => 'office']);
        $order->buildings()->attach($building->id, ['role' => 'delivery', 'position' => 0, 'apartment_id' => $apartment->id]);
        $version = null;

        $this->get('/Ameise/')->assertOk()->assertInertia(function (Assert $page) use ($order, &$version): void {
            $page->where('ordersByStatus.open.0.id', $order->id)
                ->where('ordersByStatus.open.0.delivery_date', '2026-10-10')
                ->where('ordersByStatus.open.0.buildings.0.address', 'Ленина, 10')
                ->where('ordersByStatus.open.0.buildings.0.city.name', 'Самара')
                ->where('ordersByStatus.open.0.buildings.0.city.region', 'Самарская область')
                ->where('ordersByStatus.open.0.buildings.0.apartment.label', 'офис 12А')
                ->where('ordersByStatus.open.0.permissions.edit', true)
                ->where('ordersByStatus.open.0.permissions.delivery_edit', true)
                ->where('ordersByStatus.open.0.delivery_version', function (string $value) use (&$version): bool {
                    $version = $value;

                    return strlen($value) === 64;
                });
        });

        $this->patchJson('/api/orders/'.$order->id.'/delivery-date', ['delivery_date' => '2026-10-11', 'version' => $version])
            ->assertOk()->assertJsonPath('data.delivery_date', '2026-10-11');
    }

    private function employee(): User
    {
        return User::factory()->create(['type' => 'employee', 'status' => 'active']);
    }

    private function order(): Order
    {
        $good = Good::query()->create(['name' => 'Сахар']);
        $order = Order::query()->create([
            'entity_id' => Entity::query()->create(['name' => 'Покупатель'])->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id'),
            'currency_code' => 'RUB',
            'total_amount' => 200,
            'internal_comment' => 'Сохранить комментарий',
            'delivery_date' => '2026-10-10',
        ]);
        $order->items()->create([
            'good_id' => $good->id, 'good_name' => $good->name, 'quantity' => 2,
            'price_gross' => 100, 'line_total' => 200, 'currency_code' => 'RUB',
        ]);

        return $order;
    }

    private function statusPayload(Order $order): array
    {
        return [
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::DEFERRED)->value('id'),
            'expected_order_status_id' => $order->order_status_id,
        ];
    }

    private function url(Order $order): string
    {
        return '/api/orders/'.$order->id.'/status';
    }
}
