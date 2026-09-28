<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Good;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CustomerOrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerOrderApartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_reuses_apartments_and_preserves_customer_default_for_other_orders(): void
    {
        $this->mock(CustomerOrderNotificationService::class)->shouldReceive('notify')->times(3);
        $user = User::factory()->create(['type' => 'customer']);
        $entity = Entity::query()->create(['name' => 'Покупатель']);
        $entity->forceFill(['customer_created_by_user_id' => $user->id])->save();
        $user->entities()->attach($entity->id, ['is_primary' => true, 'role' => 'owner', 'status' => 'active']);
        $good = Good::query()->create(['name' => 'Товар', 'is_published' => true]);
        $payload = [
            'items' => [['good_id' => $good->id, 'quantity' => 1]],
            'delivery_address' => 'Лесная, 10',
            'delivery_apartment_number' => '305А',
            'delivery_apartment_type' => 'office',
            'preferred_delivery_time' => 'После 12',
            'customer_phone' => '+79991234567',
        ];

        $first = $this->actingAs($user)->postJson('/orders', $payload)->assertCreated();
        $firstOrder = Order::query()->findOrFail($first->json('order.id'));
        $building = $firstOrder->buildings->sole();
        $this->assertSame('305А', $building->apartment->number);
        $this->assertSame('office', $building->apartment->type);
        $this->assertSame('Лесная, 10, офис 305А', $building->address_with_apartment);
        $this->assertSame($building->apartment->id, (int) $entity->buildings->sole()->pivot->apartment_id);

        $this->postJson('/orders', $payload)->assertCreated();
        $third = $this->postJson('/orders', [...$payload, 'delivery_apartment_number' => '306'])->assertCreated();
        $this->assertDatabaseCount('buildings', 1);
        $this->assertDatabaseCount('apartments', 2);
        $this->assertSame('306', Order::query()->findOrFail($third->json('order.id'))->buildings->sole()->apartment->number);
        $this->assertSame('305А', $entity->fresh()->buildings->sole()->apartment->number);

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('orders.0.delivery_address', 'Лесная, 10, офис 306')
            ->where('orders.1.delivery_address', 'Лесная, 10, офис 305А'));
        $this->assertSame('Лесная, 10, офис 305А', $this->app->build(CustomerOrderNotificationService::class)->deliveryAddress($firstOrder));
        $this->assertStringContainsString('Лесная, 10, офис 305А', view('emails.customer-order-created', ['order' => $firstOrder])->render());
    }

    public function test_invalid_apartment_details_do_not_create_an_order_or_building(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'customer']))
            ->postJson('/orders', [
                'items' => [['good_id' => Good::query()->create(['name' => 'Товар', 'is_published' => true])->id]],
                'delivery_address' => 'Лесная, 10',
                'delivery_apartment_number' => str_repeat('1', 51),
                'delivery_apartment_type' => 'invalid',
                'preferred_delivery_time' => 'После 12',
                'customer_phone' => '+79991234567',
            ])->assertUnprocessable()->assertJsonValidationErrors(['delivery_apartment_number', 'delivery_apartment_type']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('buildings', 0);
    }
}
