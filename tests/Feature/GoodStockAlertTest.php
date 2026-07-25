<?php

namespace Tests\Feature;

use App\Jobs\EvaluateGoodStockAvailabilityJob;
use App\Jobs\SendGoodStockAlertNotificationJob;
use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\GoodStockAlert;
use App\Models\GoodStockAvailability;
use App\Models\GoodStockMovement;
use App\Models\MaxChat;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Goods\GoodStockAlertMessenger;
use App\Services\Goods\GoodStockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class GoodStockAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('cache.default', 'array');
        config()->set('queue.default', 'sync');
        DB::purge();
        DB::setDefaultConnection('sqlite');

        $this->createTestSchema();

        config()->set([
            'app.url' => 'https://shop.test',
            'services.max.api_url' => 'https://platform-api2.max.ru',
            'services.max.access_token' => 'test-token',
            'services.max.bot_url' => null,
            'services.max.bot_username' => 'stock-test-bot',
            'services.max.webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_customer_can_start_max_alert_for_an_out_of_stock_good(): void
    {
        $good = $this->outOfStockGood();

        $response = $this->postJson(route('public.good-stock-alerts.store', $good));

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Откройте MAX и подтвердите запуск бота.')
            ->assertJsonStructure(['deep_link', 'expires_at']);

        $this->assertStringStartsWith(
            'https://max.ru/stock-test-bot?start=stock_',
            $response->json('deep_link')
        );
        $this->assertDatabaseHas('good_stock_alerts', [
            'good_id' => $good->id,
            'status' => GoodStockAlert::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('good_stock_availabilities', [
            'good_id' => $good->id,
            'is_in_stock' => false,
        ]);
    }

    public function test_customer_can_start_max_alert_for_an_on_request_good(): void
    {
        $good = $this->outOfStockGood();
        $good->seo->update([
            'availability_status' => 'on_request',
        ]);
        $good->load('seo');

        $this->assertTrue(
            app(GoodStockService::class)->availabilityPayload($good)['can_subscribe']
        );

        $this->postJson(route('public.good-stock-alerts.store', $good))
            ->assertCreated()
            ->assertJsonStructure(['deep_link', 'expires_at']);

        $this->assertDatabaseHas('good_stock_alerts', [
            'good_id' => $good->id,
            'status' => GoodStockAlert::STATUS_PENDING,
        ]);
    }

    public function test_public_goods_payload_exposes_the_stock_alert_state(): void
    {
        $good = $this->outOfStockGood();
        $good->seo->update([
            'availability_status' => 'on_request',
        ]);

        $goods = Good::query()
            ->select([
                'goods.id',
                'goods.name',
            ])
            ->with([
                'seo',
                'stockAvailability',
            ])
            ->withExists('stockMovements')
            ->get();

        app(GoodStockService::class)->appendAvailability($goods);

        $payload = $goods->firstOrFail()->toArray();

        $this->assertSame('on_request', $payload['availability']['status']);
        $this->assertTrue($payload['availability']['can_subscribe']);
        $this->assertArrayNotHasKey('stock_movements_exists', $payload);
    }

    public function test_bot_started_webhook_activates_alert_and_sends_confirmation(): void
    {
        Http::fake([
            'https://platform-api2.max.ru/messages*' => Http::response([
                'message' => [
                    'body' => ['mid' => 'confirmation-message'],
                ],
            ]),
        ]);

        $good = $this->outOfStockGood();
        $subscription = $this->postJson(route('public.good-stock-alerts.store', $good))
            ->assertCreated();
        parse_str(
            (string) parse_url($subscription->json('deep_link'), PHP_URL_QUERY),
            $query
        );

        $this->withHeader('X-Max-Bot-Api-Secret', 'test-webhook-secret')
            ->postJson(route('api.max.webhook'), [
                'update_type' => 'bot_started',
                'chat_id' => 9001,
                'user' => [
                    'user_id' => 7001,
                    'name' => 'Покупатель',
                ],
                'payload' => $query['start'],
            ])
            ->assertOk()
            ->assertJsonPath('processed', 1);

        $alert = GoodStockAlert::query()->firstOrFail();

        $this->assertSame(GoodStockAlert::STATUS_ACTIVE, $alert->status);
        $this->assertNotNull($alert->activated_at);
        $this->assertNotNull($alert->confirmation_sent_at);
        $this->assertSame('9001', $alert->maxChat?->chat_id);
        $this->assertDatabaseHas('max_messages', [
            'max_chat_id' => $alert->max_chat_id,
            'max_message_id' => 'confirmation-message',
            'direction' => 'outgoing',
            'status' => 'sent',
        ]);

        Http::assertSentCount(1);
    }

    public function test_customer_can_cancel_active_alert_from_max_button(): void
    {
        Http::fake([
            'https://platform-api2.max.ru/*' => Http::response([
                'message' => [
                    'body' => ['mid' => 'provider-message'],
                ],
            ]),
        ]);

        $good = $this->outOfStockGood();
        $subscription = $this->postJson(route('public.good-stock-alerts.store', $good))
            ->assertCreated();
        parse_str(
            (string) parse_url($subscription->json('deep_link'), PHP_URL_QUERY),
            $query
        );
        $token = substr($query['start'], strlen('stock_'));

        $this->withHeader('X-Max-Bot-Api-Secret', 'test-webhook-secret')
            ->postJson(route('api.max.webhook'), [
                'update_type' => 'bot_started',
                'chat_id' => 9001,
                'user' => ['user_id' => 7001],
                'payload' => $query['start'],
            ])
            ->assertOk();

        $this->withHeader('X-Max-Bot-Api-Secret', 'test-webhook-secret')
            ->postJson(route('api.max.webhook'), [
                'update_type' => 'message_callback',
                'chat_id' => 9001,
                'callback' => [
                    'callback_id' => 'callback-1',
                    'payload' => 'stock_cancel_'.$token,
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('good_stock_alerts', [
            'good_id' => $good->id,
            'status' => GoodStockAlert::STATUS_CANCELLED,
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/answers'));
    }

    public function test_existing_max_webhook_remains_compatible_without_a_secret(): void
    {
        config()->set('services.max.webhook_secret');

        $this->postJson(route('api.max.webhook'), [
            'updates' => [],
        ])
            ->assertOk()
            ->assertJsonPath('processed', 0);
    }

    public function test_subscription_is_unavailable_when_max_api_is_not_configured(): void
    {
        config()->set('services.max.access_token');
        $good = $this->outOfStockGood();

        $this->postJson(route('public.good-stock-alerts.store', $good))
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Интеграция MAX не настроена. Укажите MAX_ACCESS_TOKEN.'
            );

        $this->assertDatabaseCount('good_stock_alerts', 0);
    }

    public function test_deploy_command_ensures_required_max_webhook_events(): void
    {
        Http::fake([
            'https://platform-api2.max.ru/subscriptions' => Http::response([
                'success' => true,
            ]),
        ]);

        $this->artisan('max:webhook:ensure', [
            '--url' => 'https://shop.test/api/max/webhook',
        ])
            ->expectsOutput('Required events: bot_started, message_callback')
            ->assertSuccessful();

        $this->assertDatabaseHas('max_subscriptions', [
            'url' => 'https://shop.test/api/max/webhook',
            'is_active' => true,
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api2.max.ru/subscriptions'
                && in_array('bot_started', $request->data()['update_types'], true)
                && in_array('message_callback', $request->data()['update_types'], true);
        });
    }

    public function test_deploy_command_fails_when_max_rejects_the_webhook(): void
    {
        Http::fake([
            'https://platform-api2.max.ru/subscriptions' => Http::response([
                'success' => false,
                'message' => 'Webhook is unavailable.',
            ]),
        ]);

        $this->artisan('max:webhook:ensure', [
            '--url' => 'https://shop.test/api/max/webhook',
        ])
            ->expectsOutput(
                'MAX rejected the webhook subscription: Webhook is unavailable.'
            )
            ->assertFailed();

        $this->assertDatabaseHas('max_subscriptions', [
            'url' => 'https://shop.test/api/max/webhook',
            'is_active' => false,
        ]);
    }

    public function test_stock_transition_sends_only_one_max_notification(): void
    {
        Http::fake([
            'https://platform-api2.max.ru/messages*' => Http::response([
                'message' => [
                    'body' => ['mid' => 'available-message'],
                ],
            ]),
        ]);

        $good = $this->outOfStockGood();
        $chat = MaxChat::query()->create([
            'chat_id' => '9001',
            'user_id' => '7001',
            'source_type' => 'webhook',
            'is_active' => true,
        ]);
        GoodStockAvailability::query()->create([
            'good_id' => $good->id,
            'is_in_stock' => false,
            'checked_at' => now(),
        ]);
        $alert = GoodStockAlert::query()->create([
            'good_id' => $good->id,
            'max_chat_id' => $chat->id,
            'start_token_hash' => hash('sha256', 'test-token'),
            'status' => GoodStockAlert::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
        $warehouse = Warehouse::query()->firstOrFail();

        GoodStockMovement::withoutEvents(fn () => GoodStockMovement::query()->create([
            'warehouse_id' => $warehouse->id,
            'good_id' => $good->id,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => 10,
            'unit_price' => 125,
            'moved_at' => today(),
        ]));

        $this->assertTrue(app(GoodStockService::class)->isInStock($good));
        $this->assertFalse(
            GoodStockAvailability::query()->where('good_id', $good->id)->value('is_in_stock')
        );

        Queue::fake();

        $job = new EvaluateGoodStockAvailabilityJob($good->id);
        $job->handle(app(GoodStockService::class));
        $job->handle(app(GoodStockService::class));
        Queue::assertPushed(SendGoodStockAlertNotificationJob::class, 1);

        $notification = new SendGoodStockAlertNotificationJob($alert->id);
        $notification->handle(
            app(GoodStockAlertMessenger::class),
            app(GoodStockService::class),
        );
        $notification->handle(
            app(GoodStockAlertMessenger::class),
            app(GoodStockService::class),
        );

        $alert->refresh();

        $this->assertSame(GoodStockAlert::STATUS_NOTIFIED, $alert->status);
        $this->assertSame(1, $alert->attempts);
        $this->assertSame('available-message', $alert->provider_message_id);
        $this->assertDatabaseHas('good_stock_availabilities', [
            'good_id' => $good->id,
            'is_in_stock' => true,
        ]);
        $this->assertDatabaseHas('good_seos', [
            'good_id' => $good->id,
            'availability_status' => 'in_stock',
        ]);

        Http::assertSentCount(1);
    }

    public function test_goods_stock_api_uses_a_separate_ledger_from_commodities(): void
    {
        $user = User::factory()->create();
        $good = $this->outOfStockGood();
        $warehouse = Warehouse::query()->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('good-stock-movements.store'), [
                'warehouse_id' => $warehouse->id,
                'good_id' => $good->id,
                'measure_id' => null,
                'type' => GoodStockMovement::TYPE_RECEIPT,
                'quantity' => 5,
                'unit_price' => 100,
                'moved_at' => today()->toDateString(),
                'note' => 'Отдельный приход goods',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('good_stock_movements', 1);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('good_seos', [
            'good_id' => $good->id,
            'availability_status' => 'in_stock',
        ]);
    }

    public function test_internal_goods_stock_routes_accept_an_authenticated_first_party_session(): void
    {
        config()->set('sanctum.stateful', ['shop.test']);

        $this->assertContains(
            EnsureFrontendRequestsAreStateful::class,
            app('router')->getMiddlewareGroups()['api']
        );

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        app('auth')->forgetGuards();

        $this->withHeader('Referer', 'https://shop.test/Ameise/warehouses')
            ->getJson(route('good-warehouse-stock.index'))
            ->assertOk();

        $this->withHeader('Referer', 'https://shop.test/Ameise/warehouses')
            ->getJson(route('good-stock-movements.index'))
            ->assertOk();

        $this->withHeader('Referer', 'https://shop.test/Ameise/warehouses')
            ->getJson(route('good-stock-alerts.index'))
            ->assertOk();
    }

    public function test_internal_goods_stock_and_max_routes_require_authentication(): void
    {
        $this->getJson(route('good-warehouse-stock.index'))->assertUnauthorized();
        $this->getJson(route('good-stock-alerts.index'))->assertUnauthorized();
        $this->getJson(route('api.max.chats.index'))->assertUnauthorized();
    }

    private function outOfStockGood(): Good
    {
        $good = Good::query()->create([
            'name' => 'Тестовый товар',
            'is_published' => true,
        ]);

        GoodSeo::query()->create([
            'good_id' => $good->id,
            'availability_status' => 'out_of_stock',
        ]);

        return $good->load('seo');
    }

    private function createTestSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->rememberToken();
            $table->string('profile_photo_path')->nullable();
            $table->unsignedBigInteger('current_team_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('max_chat_id')->nullable();
            $table->timestamps();
        });

        Schema::create('goods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('ava_image')->nullable();
            $table->string('ava_thumb')->nullable();
            $table->text('description')->nullable();
            $table->double('denominator')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedBigInteger('vat_rate_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->timestamps();
        });

        Schema::create('good_seos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('good_id')->unique();
            $table->string('availability_status')->default('on_request');
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(500);
            $table->timestamps();
        });

        Warehouse::query()->create([
            'name' => 'Основной склад',
            'code' => 'main',
            'is_active' => true,
            'sort_order' => 100,
        ]);

        Schema::create('measures', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('max_chats', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->nullable();
            $table->string('phone_normalized')->nullable()->unique();
            $table->string('chat_id')->nullable()->unique();
            $table->string('user_id')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('title')->nullable();
            $table->string('source_type')->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('max_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('max_chat_id')->nullable();
            $table->string('max_message_id')->nullable();
            $table->string('direction')->default('outgoing');
            $table->string('status')->default('draft');
            $table->string('phone_normalized')->nullable();
            $table->string('chat_id')->nullable();
            $table->string('user_id')->nullable();
            $table->text('text')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('max_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('update_id')->nullable();
            $table->string('update_type')->nullable();
            $table->string('phone_normalized')->nullable();
            $table->string('chat_id')->nullable();
            $table->string('user_id')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('max_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('url')->unique();
            $table->string('secret')->nullable();
            $table->json('update_types')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('provider_response')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('good_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('good_id');
            $table->unsignedBigInteger('measure_id')->nullable();
            $table->string('type');
            $table->double('quantity_delta');
            $table->double('unit_price')->default(0);
            $table->date('moved_at');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('good_stock_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('good_id')->unique();
            $table->boolean('is_in_stock')->default(false);
            $table->timestamp('became_available_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('good_stock_alerts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('good_id');
            $table->unsignedBigInteger('max_chat_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->char('start_token_hash', 64)->unique();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }
}
