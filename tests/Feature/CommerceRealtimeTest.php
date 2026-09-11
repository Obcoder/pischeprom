<?php

namespace Tests\Feature;

use App\Events\CommerceDataChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\GoodStockMovement;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommerceRealtimeTest extends TestCase
{
    private mixed $originalQueue;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'realtime.enabled' => true,
            'realtime.queue_connection' => 'database',
            'realtime.queue' => 'realtime',
            'realtime.client.host' => 'shop.test',
            'realtime.client.port' => 443,
            'realtime.client.scheme' => 'https',
            'realtime.client.path' => '/realtime',
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'public-test-key',
            'broadcasting.connections.reverb.secret' => 'private-test-secret-never-expose',
            'broadcasting.connections.reverb.app_id' => 'internal-test-app-id',
        ]);
        DB::purge();
        DB::setDefaultConnection('sqlite');
        $this->createTestSchema();

        Broadcast::purge('reverb');
        require base_path('routes/channels.php');

        $this->originalQueue = Queue::getFacadeRoot();
        Queue::fake();
    }

    public function test_guest_customer_employee_without_rights_and_unknown_channel_are_rejected(): void
    {
        $this->authenticateChannel()->assertUnauthorized();

        $customer = $this->user(['type' => 'customer']);
        $customer->givePermissionTo(Permission::findOrCreate('warehouse.view', 'crm'));
        $this->actingAs($customer);
        $this->authenticateChannel()->assertForbidden();

        $this->actingAs($this->user());
        $this->authenticateChannel()->assertForbidden();

        $admin = $this->user();
        $admin->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($admin);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-other-business-data',
        ])->assertForbidden();
    }

    public function test_active_verified_admin_and_either_warehouse_permission_can_subscribe(): void
    {
        $admin = $this->user(['type' => 'customer']);
        $admin->assignRole(Role::findOrCreate('admin', 'crm'));

        $mover = $this->user();
        $mover->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));

        // warehouse.view is deliberately absent while checking warehouse.move.
        foreach ([$admin, $mover] as $user) {
            $this->actingAs($user);
            $this->authenticateChannel()->assertOk()->assertExactJson([
                'auth' => 'public-test-key:'.hash_hmac(
                    'sha256',
                    '123.456:private-commerce.updates',
                    'private-test-secret-never-expose',
                ),
            ]);
        }

        $viewer = $this->user();
        $viewer->givePermissionTo(Permission::findOrCreate('warehouse.view', 'crm'));
        $this->actingAs($viewer);
        $this->authenticateChannel()->assertOk();
    }

    public function test_blocked_and_unverified_administrators_and_disabled_feature_cannot_subscribe(): void
    {
        foreach ([['status' => 'blocked'], ['email_verified_at' => null]] as $attributes) {
            $user = $this->user($attributes);
            $user->assignRole(Role::findOrCreate('admin', 'crm'));
            $this->actingAs($user);
            $this->authenticateChannel()->assertForbidden();
        }

        $user = $this->user();
        $user->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($user);
        config()->set('realtime.enabled', false);
        $this->authenticateChannel()->assertForbidden();
    }

    public function test_private_subscription_authorization_is_rate_limited_per_user(): void
    {
        $user = $this->user();
        $user->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->authenticateChannel()->assertOk();
        }

        $this->authenticateChannel()->assertStatus(429);

        $otherUser = $this->user();
        $otherUser->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($otherUser);
        $this->authenticateChannel()->assertOk();
    }

    public function test_inertia_only_shares_public_connection_settings_with_authorized_users(): void
    {
        $this->assertSame(['enabled' => false], $this->sharedRealtime(null));
        $this->assertSame(['enabled' => false], $this->sharedRealtime($this->user()));

        $user = $this->user();
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->assertSame([
            'enabled' => true,
            'key' => 'public-test-key',
            'host' => 'shop.test',
            'port' => 443,
            'scheme' => 'https',
            'path' => '/realtime',
            'channel' => 'commerce.updates',
        ], $this->sharedRealtime($user));

        config()->set('realtime.enabled', false);
        $this->assertSame(['enabled' => false], $this->sharedRealtime($user));
    }

    public function test_events_are_emitted_only_after_outer_commit_and_contain_no_business_data(): void
    {
        Event::fake([CommerceDataChanged::class]);
        DB::beginTransaction();
        $sale = $this->sale();
        DB::beginTransaction();
        GoodStockMovement::query()->create([
            'good_id' => 991,
            'warehouse_id' => 321,
            'quantity_delta' => -2,
            'unit_price' => 550,
            'note' => 'Sensitive warehouse note',
        ]);
        DB::commit();
        Event::assertNotDispatched(CommerceDataChanged::class);

        DB::commit();

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => 12345]);
        Event::assertDispatchedTimes(CommerceDataChanged::class, 2);
        Event::assertDispatched(CommerceDataChanged::class, function (CommerceDataChanged $event): bool {
            $this->assertSame(['topics', 'event_id'], array_keys($event->broadcastWith()));
            $this->assertTrue(Str::isUuid($event->eventId));
            $this->assertSame('private-commerce.updates', $event->broadcastOn()[0]->name);
            $this->assertSame('commerce.changed', $event->broadcastAs());

            return $event->topics === ['goods_stock'];
        });
        Event::assertDispatched(CommerceDataChanged::class, fn ($event) => $event->topics === ['sales']);
    }

    public function test_rollback_discards_notifications_including_committed_savepoints(): void
    {
        Event::fake([CommerceDataChanged::class]);
        DB::beginTransaction();
        DB::beginTransaction();
        $this->sale();
        DB::commit();
        DB::rollBack();

        Event::assertNotDispatched(CommerceDataChanged::class);
        $this->assertDatabaseCount('sales', 0);

        DB::transaction(function (): void {
            DB::beginTransaction();
            $this->sale();
            DB::rollBack();
            Warehouse::query()->create(['name' => 'Surviving warehouse']);
        });

        Event::assertDispatchedTimes(CommerceDataChanged::class, 1);
        Event::assertDispatched(CommerceDataChanged::class, fn ($event) => $event->topics === ['warehouses']);
    }

    public function test_successive_updates_have_distinct_events_but_unchanged_saves_emit_nothing(): void
    {
        Event::fake([CommerceDataChanged::class]);
        $sale = $this->sale();
        $sale->save();
        Event::assertDispatchedTimes(CommerceDataChanged::class, 1);

        $sale->update(['total' => 9]);
        $sale->save();
        $sale->update(['total' => 8]);
        $sale->save();
        $sale->delete();

        Event::assertDispatchedTimes(CommerceDataChanged::class, 4);
        $ids = Event::dispatched(CommerceDataChanged::class)->map(fn ($arguments) => $arguments[0]->eventId);
        $this->assertSame(4, $ids->unique()->count());
    }

    public function test_purchases_warehouses_and_commodity_movements_publish_their_own_topics(): void
    {
        Event::fake([CommerceDataChanged::class]);
        Purchase::query()->create(['amount' => 20]);
        Warehouse::query()->create(['name' => 'Warehouse']);
        $movement = StockMovement::query()->create(['quantity_delta' => 4]);
        $movement->delete();

        Event::assertDispatchedTimes(CommerceDataChanged::class, 4);
        foreach (['purchases', 'warehouses', 'commodity_stock'] as $topic) {
            Event::assertDispatched(CommerceDataChanged::class, fn ($event) => $event->topics === [$topic]);
        }
    }

    public function test_document_stock_movement_signals_line_changes_even_when_document_total_is_unchanged(): void
    {
        $this->freezeTime();
        $sale = $this->sale();
        Event::fake([CommerceDataChanged::class]);

        DB::transaction(function () use ($sale): void {
            $sale->update(['total' => $sale->total]);
            GoodStockMovement::query()->create([
                'good_id' => 991,
                'sale_id' => $sale->id,
                'source_type' => GoodStockMovement::SOURCE_GOOD_SALE,
                'quantity_delta' => -1,
                'unit_price' => 0,
            ]);
        });
        GoodStockMovement::query()->create([
            'good_id' => 991,
            'purchase_id' => 321,
            'source_type' => GoodStockMovement::SOURCE_GOOD_PURCHASE,
            'quantity_delta' => 1,
            'unit_price' => 0,
        ]);

        Event::assertDispatchedTimes(CommerceDataChanged::class, 2);
        Event::assertDispatched(CommerceDataChanged::class, fn ($event) => $event->topics === ['goods_stock', 'sales']);
        Event::assertDispatched(CommerceDataChanged::class, fn ($event) => $event->topics === ['goods_stock', 'purchases']);
    }

    public function test_database_write_failure_emits_no_event(): void
    {
        Event::fake([CommerceDataChanged::class]);

        try {
            DB::transaction(fn () => Sale::query()->create(['total' => null]));
            $this->fail('A null sale total must fail.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('sales', 0);
            Event::assertNotDispatched(CommerceDataChanged::class);
        }
    }

    public function test_committed_change_creates_a_database_broadcast_job_without_network_io(): void
    {
        Queue::swap($this->originalQueue);
        DB::beginTransaction();
        $this->sale();
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();

        $job = DB::table('jobs')->sole();
        $this->assertSame('realtime', $job->queue);
        $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(5, $payload['maxTries']);
        $this->assertSame('1,5,15,30,60', $payload['backoff']);
        $this->assertStringNotContainsString('Sensitive', $job->payload);
        $this->assertStringNotContainsString('private-test-secret', $job->payload);

        $broadcastJob = unserialize($payload['data']['command']);
        $this->assertInstanceOf(BroadcastEvent::class, $broadcastJob);
        $this->assertSame(['sales'], $broadcastJob->event->topics);
        $this->assertSame('database', $broadcastJob->event->connection);
        $this->assertSame(['reverb'], $broadcastJob->event->broadcastConnections());
    }

    public function test_unavailable_queue_cannot_make_an_already_committed_sale_fail_or_leak_details(): void
    {
        Queue::shouldReceive('connection')->with('database')->andReturnSelf();
        Queue::shouldReceive('pushOn')->with('realtime', Mockery::type(BroadcastEvent::class))
            ->andThrow(new RuntimeException('Sensitive queue credential'));
        Log::shouldReceive('warning')->once()->with('commerce_realtime_unavailable');

        $sale = DB::transaction(fn () => $this->sale());

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => 12345]);
    }

    public function test_sync_queue_configuration_never_runs_broadcast_in_the_posting_request(): void
    {
        config()->set('realtime.queue_connection', 'sync');
        Event::fake([CommerceDataChanged::class]);
        Log::shouldReceive('warning')->once()->with('commerce_realtime_unavailable');

        $sale = DB::transaction(fn () => $this->sale());

        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        Event::assertNotDispatched(CommerceDataChanged::class);
    }

    public function test_disabled_feature_does_not_enqueue_notifications(): void
    {
        config()->set('realtime.enabled', false);
        $this->sale();
        Queue::assertNothingPushed();
    }

    public function test_business_data_cannot_be_passed_as_an_event_topic(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CommerceDataChanged(['sales', 'customer-name']);
    }

    private function authenticateChannel(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-commerce.updates',
        ]);
    }

    private function user(array $attributes = []): User
    {
        return User::query()->forceCreate(array_merge([
            'name' => 'Realtime user',
            'email' => Str::uuid().'@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'type' => 'employee',
            'status' => 'active',
        ], $attributes));
    }

    private function sharedRealtime(?User $user): array
    {
        $request = Request::create('/ameise/warehouses');
        $request->setUserResolver(fn () => $user);
        $share = app(HandleInertiaRequests::class)->share($request);

        return $share['realtime']();
    }

    private function sale(): Sale
    {
        return Sale::query()->create(['total' => 12345, 'payment_reference' => 'Sensitive buyer reference']);
    }

    private function createTestSchema(): void
    {
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', function (Blueprint $table): void {
            $table->string('type');
            $table->string('status');
        });
        (require database_path('migrations/2026_01_03_195751_create_permission_tables.php'))->up();

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total', 15, 2);
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->decimal('outstanding_amount', 15, 2)->nullable();
            $table->decimal('overpaid_amount', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        foreach (['good_stock_movements', 'stock_movements'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('good_id')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->unsignedBigInteger('sale_id')->nullable();
                $table->unsignedBigInteger('purchase_id')->nullable();
                $table->string('source_type')->nullable();
                $table->double('quantity_delta');
                $table->double('unit_price')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
}
