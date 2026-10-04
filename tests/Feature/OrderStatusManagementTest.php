<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Good;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_edit_list_and_delete_custom_statuses(): void
    {
        $this->signIn();

        $created = $this->postJson('/api/order-statuses', [
            'code' => 'awaiting_payment',
            'name' => 'Ожидает оплаты',
            'color' => '#8b5cf6',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 40)
            ->assertJsonPath('data.is_closed', false)
            ->assertJsonPath('data.orders_count', 0)
            ->assertJsonPath('data.is_system', false);

        $id = $created->json('data.id');
        $this->putJson('/api/order-statuses/'.$id, [
            'code' => 'payment-pending',
            'name' => 'На оплате',
            'color' => '#f80',
            'sort_order' => 5,
            'is_closed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'На оплате')
            ->assertJsonPath('data.is_closed', true);

        $this->getJson('/api/order-statuses')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.code', 'payment-pending')
            ->assertJsonPath('data.0.color', '#f80')
            ->assertJsonPath('data.1.is_system', true);

        $this->getJson('/api/orders/options')
            ->assertOk()
            ->assertJsonPath('statuses.0.id', $id)
            ->assertJsonPath('statuses.0.sort_order', 5);

        $this->deleteJson('/api/order-statuses/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('order_statuses', ['id' => $id]);
    }

    public function test_system_status_presentation_can_change_but_codes_and_semantics_are_preserved(): void
    {
        $this->signIn();

        foreach (OrderStatus::query()->ordered()->get() as $status) {
            $url = '/api/order-statuses/'.$status->id;
            $this->putJson($url, [
                'name' => 'Новое название '.$status->code,
                'color' => '#abcdef',
                'sort_order' => 150,
                'code' => $status->code,
                'is_closed' => $status->is_closed,
            ])
                ->assertOk()
                ->assertJsonPath('data.name', 'Новое название '.$status->code)
                ->assertJsonPath('data.color', '#abcdef')
                ->assertJsonPath('data.sort_order', 150)
                ->assertJsonPath('data.is_system', true);

            $this->putJson($url, ['code' => 'renamed'])->assertUnprocessable()->assertJsonValidationErrors('code');
            $this->putJson($url, ['is_closed' => ! $status->is_closed])->assertUnprocessable()->assertJsonValidationErrors('is_closed');
            $this->deleteJson($url)->assertUnprocessable()->assertJsonValidationErrors('status');

            $this->assertDatabaseHas('order_statuses', [
                'id' => $status->id,
                'code' => $status->code,
                'is_closed' => $status->is_closed,
            ]);
        }
    }

    public function test_status_in_use_cannot_be_deleted_or_change_closure_semantics(): void
    {
        $this->signIn();
        $status = OrderStatus::query()->create([
            'code' => 'processing',
            'name' => 'В обработке',
            'is_closed' => false,
        ]);
        $order = Order::query()->create([
            'entity_id' => Entity::query()->create(['name' => 'Покупатель'])->id,
            'order_status_id' => $status->id,
            'currency_code' => 'RUB',
            'total_amount' => 100,
        ]);

        $this->getJson('/api/order-statuses')->assertJsonFragment([
            'id' => $status->id,
            'code' => 'processing',
            'orders_count' => 1,
            'is_system' => false,
        ]);

        $this->deleteJson('/api/order-statuses/'.$status->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->putJson('/api/order-statuses/'.$status->id, ['is_closed' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_closed');
        $this->putJson('/api/order-statuses/'.$status->id, ['name' => 'Готовится', 'color' => null])
            ->assertOk()
            ->assertJsonPath('data.name', 'Готовится')
            ->assertJsonPath('data.orders_count', 1);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status_id' => $status->id, 'closed_at' => null]);
        $this->assertFalse($status->fresh()->is_closed);

        $order->update(['order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id')]);
        $this->deleteJson('/api/order-statuses/'.$status->id)->assertNoContent();
        $this->assertModelExists($order);
    }

    public function test_custom_closed_status_can_be_assigned_to_an_order(): void
    {
        $this->signIn();
        $statusId = $this->postJson('/api/order-statuses', [
            'code' => 'cancelled',
            'name' => 'Отменён',
            'is_closed' => true,
        ])->assertCreated()->json('data.id');

        $entity = Entity::query()->create(['name' => 'Покупатель']);
        $good = Good::query()->create(['name' => 'Товар']);
        $created = $this->postJson('/api/orders', [
            'entity_id' => $entity->id,
            'order_status_id' => $statusId,
            'currency_code' => 'RUB',
            'items' => [['good_id' => $good->id, 'quantity' => 1, 'unit_price' => 100]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status.code', 'cancelled')
            ->assertJsonPath('data.status.is_closed', true);

        $this->assertNotNull(Order::query()->findOrFail($created->json('data.id'))->closed_at);
    }

    public function test_dashboard_reflects_status_names_order_and_custom_active_orders(): void
    {
        $this->signIn();
        $customStatusId = $this->postJson('/api/order-statuses', [
            'code' => 'awaiting_payment',
            'name' => 'Ожидает оплаты',
            'color' => '#8b5cf6',
            'sort_order' => 5,
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/order-statuses', [
            'code' => 'cancelled',
            'name' => 'Отменён',
            'is_closed' => true,
            'sort_order' => 1,
        ])->assertCreated();
        $openStatusId = OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id');
        $this->putJson('/api/order-statuses/'.$openStatusId, [
            'name' => 'Принят в работу',
            'color' => '#123456',
            'sort_order' => 100,
        ])->assertOk();
        $order = Order::query()->create([
            'entity_id' => Entity::query()->create(['name' => 'Покупатель'])->id,
            'order_status_id' => $customStatusId,
            'currency_code' => 'RUB',
            'total_amount' => 100,
        ]);

        $this->get('/Ameise/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Ameise/Verwalter')
            ->has('orderStatuses', 5)
            ->where('orderStatuses.0.code', 'cancelled')
            ->where('orderStatuses.0.is_closed', true)
            ->where('orderStatuses.1.code', 'awaiting_payment')
            ->where('orderStatuses.1.name', 'Ожидает оплаты')
            ->where('orderStatuses.2.code', OrderStatus::DEFERRED)
            ->where('orderStatuses.3.code', OrderStatus::CLOSED)
            ->where('orderStatuses.4.name', 'Принят в работу')
            ->where('orderStatuses.4.color', '#123456')
            ->where('ordersByStatus.awaiting_payment.0.id', $order->id)
            ->where('ordersByStatus.awaiting_payment.0.status.name', 'Ожидает оплаты')
            ->missing('ordersByStatus.cancelled')
            ->missing('ordersByStatus.closed'));
    }

    public function test_status_input_validation_prevents_invalid_values_and_duplicate_codes(): void
    {
        $this->signIn();
        $valid = ['code' => 'new_status', 'name' => 'Новый статус'];

        foreach ([
            ['code' => 'open'],
            ['code' => 'UPPERCASE'],
            ['code' => 'invalid code'],
            ['code' => str_repeat('a', 33)],
            ['name' => ''],
            ['name' => str_repeat('я', 65)],
            ['color' => 'url(example.test)'],
            ['sort_order' => -1],
            ['sort_order' => 65536],
            ['is_closed' => 'yes'],
        ] as $invalid) {
            $this->postJson('/api/order-statuses', array_replace($valid, $invalid))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(array_keys($invalid));
        }

        $this->assertDatabaseCount('order_statuses', 3);
    }

    public function test_status_management_requires_an_active_staff_account(): void
    {
        $id = OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id');
        $requests = [
            ['GET', '/api/order-statuses', []],
            ['POST', '/api/order-statuses', ['code' => 'new_status', 'name' => 'Новый статус']],
            ['PUT', '/api/order-statuses/'.$id, ['name' => 'Другое название']],
            ['DELETE', '/api/order-statuses/'.$id, []],
        ];

        foreach ($requests as [$method, $url, $data]) {
            $this->json($method, $url, $data)->assertUnauthorized();
        }

        foreach ([
            ['type' => 'customer', 'status' => 'active'],
            ['type' => 'employee', 'status' => 'inactive'],
        ] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            foreach ($requests as [$method, $url, $data]) {
                $this->json($method, $url, $data)->assertForbidden();
            }
        }

        $this->assertDatabaseCount('order_statuses', 3);
        $this->assertDatabaseHas('order_statuses', ['id' => $id, 'name' => 'Открытые']);
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }
}
