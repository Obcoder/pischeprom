<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseMutationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_sales_or_change_inventory(): void
    {
        foreach ($this->mutationEndpoints() as $endpoint) {
            $this->postJson($endpoint, [])->assertUnauthorized();
        }
    }

    public function test_customers_cannot_change_inventory_even_with_a_warehouse_permission(): void
    {
        $user = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($user);

        foreach ($this->mutationEndpoints() as $endpoint) {
            $this->postJson($endpoint, [])->assertForbidden();
        }
    }

    public function test_employees_need_a_warehouse_permission(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));

        foreach ($this->mutationEndpoints() as $endpoint) {
            $this->postJson($endpoint, [])->assertForbidden();
        }
    }

    public function test_blocked_administrators_cannot_change_inventory(): void
    {
        $user = User::factory()->create(['type' => 'employee', 'status' => 'blocked']);
        $user->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($user);

        foreach ($this->mutationEndpoints() as $endpoint) {
            $this->postJson($endpoint, [])->assertForbidden();
        }
    }

    public function test_unverified_employees_cannot_change_inventory(): void
    {
        $user = User::factory()->unverified()->create(['type' => 'employee', 'status' => 'active']);
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($user);

        foreach ($this->mutationEndpoints() as $endpoint) {
            $this->postJson($endpoint, [])->assertForbidden();
        }
    }

    public function test_administrators_are_allowed_even_with_a_legacy_customer_type(): void
    {
        $user = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $user->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($user);

        foreach ($this->mutationEndpoints() as $endpoint) {
            $this->postJson($endpoint, [])->assertUnprocessable();
        }
    }

    public function test_authorized_employee_cannot_rename_delete_or_duplicate_the_goods_warehouse(): void
    {
        $user = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($user);
        $warehouse = Warehouse::query()->where('code', Warehouse::GOODS_CODE)->firstOrFail();

        $this->patchJson('/api/warehouses/'.$warehouse->id, [
            'name' => $warehouse->name,
            'code' => 'renamed-goods',
        ])->assertUnprocessable();
        $this->deleteJson('/api/warehouses/'.$warehouse->id)->assertUnprocessable();
        $this->postJson('/api/warehouses', [
            'name' => 'Подмена склада goods',
            'code' => Warehouse::GOODS_CODE,
        ])->assertUnprocessable();

        $this->assertSame(Warehouse::GOODS_CODE, $warehouse->fresh()->code);
    }

    public function test_unsupported_sale_changes_do_not_return_a_successful_response(): void
    {
        $user = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($user);

        foreach (['/api/sales/1', '/api/goodsales/1'] as $endpoint) {
            $this->patchJson($endpoint, [])->assertStatus(405);
            $this->deleteJson($endpoint)->assertStatus(405);
        }
    }

    private function mutationEndpoints(): array
    {
        return [
            '/api/sales',
            '/api/goodsales',
            '/api/purchases',
            '/api/warehouses',
            '/api/good-stock-movements',
            '/api/stock-movements',
            '/web/sale/store',
            '/web/goodsale/store',
        ];
    }
}
