<?php

namespace Tests\Feature\Mobile;

use App\Models\Building;
use App\Models\City;
use App\Models\Country;
use App\Models\Entity;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Region;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileDeliveryMapTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->employee = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->employee->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
    }

    public function test_map_configuration_and_customer_addresses_require_a_current_employee_mobile_token(): void
    {
        config(['gis.providers.yandex.api_key' => 'test-public-map-key']);
        foreach (['config', 'orders'] as $resource) {
            $this->getJson('/api/mobile/v1/delivery-map/'.$resource)->assertUnauthorized();
        }
        $customer = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $this->withToken($customer->createToken('mobile:customer', ['mobile:orders'], now()->addHour())->plainTextToken);
        foreach (['config', 'orders'] as $resource) {
            $this->getJson('/api/mobile/v1/delivery-map/'.$resource)->assertForbidden();
        }

        $this->authenticate();
        $this->getJson('/api/mobile/v1/delivery-map/config')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.api_key', 'test-public-map-key');
        $this->employee->update(['status' => 'blocked']);
        $this->getJson('/api/mobile/v1/delivery-map/config')->assertForbidden();
        $this->getJson('/api/mobile/v1/delivery-map/orders')->assertForbidden();
    }

    public function test_missing_key_or_untrusted_script_disables_embedding_and_enterprise_yandex_is_supported(): void
    {
        $this->authenticate();
        config(['gis.providers.yandex.api_key' => null]);
        $this->getJson('/api/mobile/v1/delivery-map/config')->assertOk()
            ->assertJsonPath('data.configured', false)->assertJsonPath('data.api_key', null);

        config(['gis.providers.yandex.api_key' => 'test-public-map-key']);
        foreach (['https://untrusted.example/2.1/', 'https://api-maps.yandex.ru@untrusted.example/2.1/', 'http://api-maps.yandex.ru/2.1/', 'https://api-maps.yandex.ru/2.1/?apikey=other'] as $script) {
            config(['gis.providers.yandex.map_script_url' => $script]);
            $this->getJson('/api/mobile/v1/delivery-map/config')->assertOk()
                ->assertJsonPath('data.configured', false)->assertJsonPath('data.api_key', null)
                ->assertJsonPath('data.script_url', null);
        }
        config(['gis.providers.yandex.map_script_url' => 'https://enterprise.api-maps.yandex.ru/2.1/']);
        $this->getJson('/api/mobile/v1/delivery-map/config')->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.script_url', 'https://enterprise.api-maps.yandex.ru/2.1/');
    }

    public function test_map_contains_only_order_delivery_addresses_and_does_not_read_stock_or_items(): void
    {
        $this->authenticate();
        $buyer = Entity::query()->create(['name' => '<b>Покупатель</b>']);
        $order = Order::query()->create(['entity_id' => $buyer->id, 'internal_comment' => 'private accounting note']);
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $region = Region::query()->create(['name' => 'Москва', 'country_id' => $country->id]);
        $city = City::query()->create(['name' => 'Москва', 'region_id' => $region->id]);
        $delivery = Building::query()->create(['city_id' => $city->id, 'address' => 'Тверская улица, 1']);
        $other = Building::query()->create(['address' => 'Чужой юридический адрес']);
        $buyer->buildings()->attach($other);
        $order->buildings()->attach($delivery, ['role' => 'delivery', 'position' => 0]);
        $order->buildings()->attach($other, ['role' => 'logistics', 'position' => 1]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->getJson('/api/mobile/v1/delivery-map/orders')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.entity.name', '<b>Покупатель</b>')
            ->assertJsonCount(1, 'data.0.delivery_addresses')
            ->assertJsonPath('data.0.delivery_addresses.0.id', $delivery->id)
            ->assertJsonPath('data.0.delivery_addresses.0.full_address', 'Москва, Тверская улица, 1')
            ->assertJsonMissingPath('data.0.items')->assertJsonMissingPath('data.0.internal_comment');
        $sql = implode("\n", $queries);
        $this->assertStringNotContainsString('good_stock_movements', $sql);
        $this->assertStringNotContainsString('order_items', $sql);
        $this->assertStringNotContainsString('measures', $sql);
    }

    public function test_map_keeps_orders_without_addresses_and_paginates_beyond_the_first_page(): void
    {
        $this->authenticate();
        for ($i = 0; $i < 23; $i++) {
            Order::query()->create(['number' => 'DELIVERY-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }
        $first = $this->getJson('/api/mobile/v1/delivery-map/orders?per_page=20')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 23)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.delivery_addresses', [])->json('data');
        $last = $this->getJson('/api/mobile/v1/delivery-map/orders?per_page=20&page=2')->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('meta.current_page', 2)->json('data');
        $this->assertCount(23, array_unique(array_column([...$first, ...$last], 'id')));
        $this->getJson('/api/mobile/v1/delivery-map/orders?per_page=101')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/delivery-map/orders?filter=unknown')->assertUnprocessable();
    }

    public function test_map_and_list_share_customer_number_date_and_workflow_filters(): void
    {
        $this->authenticate();
        $buyer = Entity::query()->create(['name' => 'Хлебокомбинат']);
        $awaiting = Order::query()->create(['number' => 'DELIVERY-NEW', 'entity_id' => $buyer->id]);
        $ready = Order::query()->create(['number' => 'DELIVERY-READY', 'submitted_at' => now()->subDays(2)]);
        $ready->forceFill(['prepared_at' => now()])->save();
        $closed = Order::query()->create(['number' => 'DELIVERY-CLOSED', 'closed_at' => now(), 'order_status_id' => OrderStatus::query()->where('code', OrderStatus::CLOSED)->sole()->id]);
        $sale = Sale::query()->create(['date' => now()->toDateString(), 'total' => 0, 'entity_id' => $buyer->id]);
        $closed->forceFill(['shipped_sale_id' => $sale->id])->save();

        foreach (['filter=all', 'filter=today', 'filter=awaiting', 'filter=ready', 'filter=shipped', 'search=Хлебокомбинат', 'search=DELIVERY-NEW'] as $query) {
            $map = $this->getJson('/api/mobile/v1/delivery-map/orders?'.$query)->assertOk()->json('data');
            $list = $this->getJson('/api/mobile/v1/orders?'.$query)->assertOk()->json('data');
            $this->assertSame(array_column($list, 'id'), array_column($map, 'id'), $query);
        }
        $this->getJson('/api/mobile/v1/delivery-map/orders?search=Хлебокомбинат')->assertJsonPath('data.0.id', $awaiting->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/mobile/v1/delivery-map/orders?filter=shipped')->assertJsonPath('data.0.id', $closed->id)->assertJsonCount(1, 'data');
    }

    private function authenticate(): void
    {
        $this->withToken($this->employee->createToken('mobile:map', ['mobile:orders'], now()->addHour())->plainTextToken);
    }
}
