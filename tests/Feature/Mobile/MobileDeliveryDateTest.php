<?php

namespace Tests\Feature\Mobile;

use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Measure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\Orders\OrderWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileDeliveryDateTest extends TestCase
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
        $this->employee = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->employee->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->buyer = Entity::query()->create(['name' => 'Покупатель с доставкой']);
        $this->good = Good::query()->create(['name' => 'Товар для доставки']);
        $this->measure = Measure::query()->create(['name' => 'шт']);
    }

    public function test_admin_can_create_read_clear_and_preserve_calendar_dates_without_timezone_conversion(): void
    {
        $this->browser();
        config(['app.timezone' => 'Pacific/Kiritimati']);
        $created = $this->postJson('/api/orders', [...$this->orderData(), 'delivery_date' => '2028-02-29'])
            ->assertCreated()->assertJsonPath('data.delivery_date', '2028-02-29')->json('data');
        $order = Order::query()->findOrFail($created['id']);
        $this->assertSame('2028-02-29', $order->delivery_date->toDateString());
        $this->getJson('/api/orders/'.$order->id)->assertOk()->assertJsonPath('data.delivery_date', '2028-02-29');

        // Existing writers that do not know about delivery planning preserve its date.
        $updated = $this->putJson('/api/orders/'.$order->id, $this->orderData())->assertOk()
            ->assertJsonPath('data.delivery_date', '2028-02-29')->json('data');
        $this->patchJson('/api/orders/'.$order->id.'/delivery-date', [
            'version' => $updated['delivery_version'], 'delivery_date' => null,
        ])->assertOk()->assertJsonPath('data.delivery_date', null);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_date' => null]);
    }

    public function test_date_edits_preserve_assembly_reject_stale_shipping_and_leave_posted_ledger_unchanged(): void
    {
        $order = $this->order();
        $this->mobile();
        $initial = $this->getJson($this->mobileUrl($order))->assertOk()->json('data');
        $prepared = $this->patchJson($this->mobileUrl($order).'/prepare', [
            'version' => $initial['version'],
            'items' => [['id' => $initial['items'][0]['id'], 'quantity' => 2, 'measure_id' => $this->measure->id]],
        ])->assertOk()->assertJsonPath('data.workflow_status', 'ready')->json('data');
        $fingerprint = $order->fresh()->prepared_fingerprint;
        $itemId = $order->items()->sole()->id;
        $planned = $this->patchJson($this->mobileUrl($order).'/delivery-date', [
            'version' => $prepared['version'], 'delivery_date' => '2026-09-24',
        ])->assertOk()->assertJsonPath('data.delivery_date', '2026-09-24')
            ->assertJsonPath('data.workflow_status', 'ready')->assertJsonPath('data.can_ship', true)->json('data');
        $this->assertNotSame($prepared['version'], $planned['version']);
        $this->assertSame($fingerprint, $order->fresh()->prepared_fingerprint);
        $this->assertSame($itemId, $order->items()->sole()->id);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
        $this->postJson($this->mobileUrl($order).'/ship', [
            'version' => $prepared['version'], 'request_id' => (string) Str::uuid(),
        ])->assertConflict();
        $shipped = $this->postJson($this->mobileUrl($order).'/ship', [
            'version' => $planned['version'], 'request_id' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('data.workflow_status', 'shipped')->json('data');
        $ledger = DB::table('good_stock_movements')->get()->map(fn ($row) => (array) $row)->all();
        $sale = DB::table('sales')->where('id', $shipped['sale']['id'])->first();
        $shipmentDate = $order->fresh()->shipped_at;

        $rescheduled = $this->patchJson($this->mobileUrl($order).'/delivery-date', [
            'version' => $shipped['version'], 'delivery_date' => '2026-09-25',
        ])->assertOk()->assertJsonPath('data.delivery_date', '2026-09-25')
            ->assertJsonPath('data.sale.id', $shipped['sale']['id'])->json('data');
        $this->browser();
        $this->patchJson('/api/orders/'.$order->id.'/delivery-date', [
            'version' => $rescheduled['version'], 'delivery_date' => '2026-09-26',
        ])->assertOk()->assertJsonPath('data.delivery_date', '2026-09-26')
            ->assertJsonPath('data.shipped_sale_id', $shipped['sale']['id']);
        $this->assertEquals($sale, DB::table('sales')->where('id', $shipped['sale']['id'])->first());
        $this->assertSame($ledger, DB::table('good_stock_movements')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertEquals($shipmentDate, $order->fresh()->shipped_at);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_stock_requests', 1);
        $this->assertEquals(-2, GoodStockMovement::query()->sum('quantity_delta'));
    }

    public function test_admin_and_mobile_date_edits_share_conflict_detection(): void
    {
        $order = $this->order();
        $this->browser();
        $admin = $this->getJson('/api/orders/'.$order->id)->assertOk()->json('data');
        $this->mobile();
        $mobile = $this->getJson($this->mobileUrl($order))->assertOk()->json('data');
        $this->assertSame($admin['delivery_version'], $mobile['version']);
        $planned = $this->patchJson($this->mobileUrl($order).'/delivery-date', [
            'version' => $mobile['version'], 'delivery_date' => '2026-09-24',
        ])->assertOk()->json('data');
        $this->browser();
        $this->patchJson('/api/orders/'.$order->id.'/delivery-date', [
            'version' => $admin['delivery_version'], 'delivery_date' => '2026-09-25',
        ])->assertConflict();
        $this->putJson('/api/orders/'.$order->id, [
            ...$this->orderData(), 'delivery_date' => '2026-09-25', 'delivery_version' => $admin['delivery_version'],
        ])->assertConflict();
        $this->putJson('/api/orders/'.$order->id, [
            ...$this->orderData(), 'delivery_date' => '2026-09-25',
        ])->assertUnprocessable()->assertJsonValidationErrors('delivery_version');
        $updated = $this->putJson('/api/orders/'.$order->id, [
            ...$this->orderData(), 'delivery_date' => '2026-09-25', 'delivery_version' => $planned['version'],
        ])->assertOk()->assertJsonPath('data.delivery_date', '2026-09-25')->json('data');
        $this->mobile();
        $this->patchJson($this->mobileUrl($order).'/delivery-date', [
            'version' => $planned['version'], 'delivery_date' => null,
        ])->assertConflict();
        $this->getJson($this->mobileUrl($order))->assertJsonPath('data.version', $updated['delivery_version'])
            ->assertJsonPath('data.delivery_date', '2026-09-25');
    }

    public function test_admin_mobile_and_map_filters_select_delivery_days_independent_of_creation_date(): void
    {
        $first = $this->order('2026-09-24');
        $first->update(['submitted_at' => '2025-01-01 08:00:00']);
        $second = $this->order('2026-09-25');
        $unscheduled = $this->order();
        $cases = [
            ['delivery_date=2026-09-24', [$first->id]],
            ['delivery_date=2026-09-25', [$second->id]],
            ['delivery_date=2026-09-26', []],
            ['delivery_unscheduled=1', [$unscheduled->id]],
            ['delivery_unscheduled=true', [$unscheduled->id]],
        ];
        $this->browser();
        foreach ($cases as [$query, $expected]) {
            $response = $this->getJson('/api/orders?'.$query)->assertOk()->assertJsonPath('meta.total', count($expected));
            $this->assertSame($expected, array_column($response->json('data'), 'id'));
        }
        $this->mobile();
        foreach (['/api/mobile/v1/orders', '/api/mobile/v1/delivery-map/orders'] as $endpoint) {
            foreach ($cases as [$query, $expected]) {
                $response = $this->getJson($endpoint.'?'.$query)->assertOk()->assertJsonPath('meta.total', count($expected));
                $this->assertSame($expected, array_column($response->json('data'), 'id'));
                if ($expected === [$first->id]) {
                    $response->assertJsonPath('data.0.delivery_date', '2026-09-24');
                }
            }
            $this->getJson($endpoint.'?delivery_date=2026-09-24&filter=awaiting')->assertOk()->assertJsonCount(1, 'data');
            $this->getJson($endpoint.'?delivery_date=2026-09-24&filter=shipped')->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_dates_are_strict_calendar_values_and_missing_date_is_not_a_clear_request(): void
    {
        $order = $this->order('2026-09-24');
        $this->mobile();
        $version = $this->getJson($this->mobileUrl($order))->json('data.version');
        foreach (['2026-02-29', '2026-04-31', '2026-9-24', '24.09.2026', '2026-09-24T00:00:00Z', '0000-01-01', 'tomorrow'] as $date) {
            $this->patchJson($this->mobileUrl($order).'/delivery-date', ['version' => $version, 'delivery_date' => $date])
                ->assertUnprocessable()->assertJsonValidationErrors('delivery_date');
        }
        $this->patchJson($this->mobileUrl($order).'/delivery-date', ['version' => $version])
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_date');
        $this->patchJson($this->mobileUrl($order).'/delivery-date', ['delivery_date' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_date' => '2026-09-24']);
        $this->patchJson($this->mobileUrl($order).'/delivery-date', ['version' => $version, 'delivery_date' => null])
            ->assertOk()->assertJsonPath('data.delivery_date', null);
    }

    public function test_filters_reject_impossible_dates_and_conflicting_day_selections(): void
    {
        foreach (['/api/orders', '/api/mobile/v1/orders', '/api/mobile/v1/delivery-map/orders'] as $endpoint) {
            str_starts_with($endpoint, '/api/mobile/') ? $this->mobile() : $this->browser();
            foreach (['delivery_date=2026-02-30', 'delivery_date=21.09.2026', 'delivery_unscheduled=wrong', 'delivery_date=2026-09-24&delivery_unscheduled=1'] as $query) {
                $this->getJson($endpoint.'?'.$query)->assertUnprocessable();
            }
        }
    }

    public function test_date_writes_require_authorization_including_existing_order_routes(): void
    {
        $order = $this->order();
        $version = $this->getJson('/api/orders/'.$order->id)->json('data.delivery_version');
        $patch = ['version' => $version, 'delivery_date' => '2026-09-24'];
        $this->patchJson('/api/orders/'.$order->id.'/delivery-date', $patch)->assertUnauthorized();
        $this->patchJson($this->mobileUrl($order).'/delivery-date', $patch)->assertUnauthorized();
        $this->postJson('/api/orders', [...$this->orderData(), 'delivery_date' => '2026-09-24'])->assertUnauthorized();
        $this->putJson('/api/orders/'.$order->id, [...$this->orderData(), 'delivery_date' => null, 'delivery_version' => $version])->assertUnauthorized();

        foreach ([['customer', 'active', true], ['employee', 'blocked', true], ['employee', 'active', false]] as [$type, $status, $verified]) {
            $user = User::factory()->create(['type' => $type, 'status' => $status, 'email_verified_at' => $verified ? now() : null]);
            $user->givePermissionTo(Permission::findOrCreate('orders.edit', 'crm'));
            $user->givePermissionTo(Permission::findOrCreate('orders.create', 'crm'));
            $this->browser($user);
            $this->patchJson('/api/orders/'.$order->id.'/delivery-date', $patch)->assertForbidden();
            $this->putJson('/api/orders/'.$order->id, [...$this->orderData(), 'delivery_date' => null, 'delivery_version' => $version])->assertForbidden();
            $this->postJson('/api/orders', [...$this->orderData(), 'delivery_date' => '2026-09-24'])->assertForbidden();
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_date' => null]);
    }

    public function test_crm_order_manager_and_legacy_administrator_can_plan_without_warehouse_permissions(): void
    {
        $order = $this->order();
        $manager = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $manager->givePermissionTo(Permission::findOrCreate('orders.edit', 'crm'));
        $this->browser($manager);
        $version = $this->getJson('/api/orders/'.$order->id)->json('data.delivery_version');
        $planned = $this->patchJson('/api/orders/'.$order->id.'/delivery-date', [
            'version' => $version, 'delivery_date' => '2026-09-24',
        ])->assertOk()->json('data');
        $admin = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $admin->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->browser($admin);
        $this->patchJson('/api/orders/'.$order->id.'/delivery-date', [
            'version' => $planned['delivery_version'], 'delivery_date' => '2026-09-25',
        ])->assertOk()->assertJsonPath('data.delivery_date', '2026-09-25');
    }

    public function test_date_migration_preserves_existing_orders_and_their_items(): void
    {
        $order = $this->order();
        $itemId = $order->items()->sole()->id;
        $migration = require database_path('migrations/2026_09_21_130000_add_delivery_date_to_orders.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_date' => null]);
        $this->assertDatabaseHas('order_items', ['id' => $itemId, 'order_id' => $order->id, 'quantity' => 2]);
    }

    private function browser(?User $user = null): void
    {
        $this->withHeader('Authorization', '');
        Auth::forgetGuards();
        $this->actingAs($user ?? $this->employee, 'web');
    }

    private function mobile(): void
    {
        Auth::forgetGuards();
        $this->withToken($this->employee->createToken('mobile:delivery-date', ['mobile:orders'], now()->addHour())->plainTextToken);
    }

    private function order(?string $date = null): Order
    {
        return app(OrderWriter::class)->save(null, [...$this->orderData(), 'delivery_date' => $date]);
    }

    private function orderData(): array
    {
        return [
            'entity_id' => $this->buyer->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->sole()->id,
            'currency_code' => 'RUB',
            'preferred_delivery_time' => 'После 12:00',
            'items' => [['good_id' => $this->good->id, 'quantity' => 2, 'unit_price' => 100]],
        ];
    }

    private function mobileUrl(Order $order): string
    {
        return '/api/mobile/v1/orders/'.$order->id;
    }
}
