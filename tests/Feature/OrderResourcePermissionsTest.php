<?php

namespace Tests\Feature;

use App\Http\Resources\OrderResource;
use App\Models\Entity;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderResourcePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_exposes_edit_and_delivery_permissions_independently(): void
    {
        $employee = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $order = $this->order();
        $this->actingAs($employee);

        $this->getJson('/api/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.permissions.edit', true)
            ->assertJsonPath('data.permissions.delivery_edit', false);

        $employee->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->getJson('/api/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.permissions.edit', true)
            ->assertJsonPath('data.permissions.delivery_edit', true);

        $order->forceFill(['shipped_at' => now()])->save();
        $this->getJson('/api/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.permissions.edit', false)
            ->assertJsonPath('data.permissions.delivery_edit', true);
    }

    public function test_order_permissions_follow_the_authenticated_sanctum_actor(): void
    {
        $employee = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $employee->givePermissionTo(Permission::findOrCreate('orders.edit', 'crm'));
        Sanctum::actingAs($employee);

        $this->getJson('/api/orders/'.$this->order()->id)->assertOk()
            ->assertJsonPath('data.permissions.edit', true)
            ->assertJsonPath('data.permissions.delivery_edit', true);
    }

    public function test_resource_permissions_default_to_false_and_honor_staff_status_and_verification(): void
    {
        $order = $this->order();
        $request = Request::create('/api/orders/'.$order->id);
        $resolve = function (?User $user) use ($order, $request): array {
            $request->setUserResolver(fn () => $user);

            return (new OrderResource($order))->resolve($request)['permissions'];
        };
        $this->assertSame(['edit' => false, 'delivery_edit' => false], $resolve(null));
        $customer = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $this->assertSame(['edit' => false, 'delivery_edit' => false], $resolve($customer));

        $employee = User::factory()->create(['type' => 'employee', 'status' => 'active', 'email_verified_at' => null]);
        $employee->givePermissionTo(Permission::findOrCreate('orders.edit', 'crm'));
        $this->assertSame(['edit' => true, 'delivery_edit' => false], $resolve($employee));
        $employee->status = 'inactive';
        $this->assertSame(['edit' => false, 'delivery_edit' => false], $resolve($employee));

        $customer->assignRole(Role::findOrCreate('manager', 'crm'));
        $this->assertSame(['edit' => true, 'delivery_edit' => false], $resolve($customer));
        $customer->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->assertSame(['edit' => true, 'delivery_edit' => true], $resolve($customer));

        $order->shipped_sale_id = 1;
        $this->assertSame(['edit' => false, 'delivery_edit' => true], $resolve($customer));
    }

    private function order(): Order
    {
        return Order::query()->create([
            'entity_id' => Entity::query()->create(['name' => 'Покупатель'])->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id'),
            'currency_code' => 'RUB',
        ]);
    }
}
