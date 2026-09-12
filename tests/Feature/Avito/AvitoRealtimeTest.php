<?php

namespace Tests\Feature\Avito;

use App\Events\AvitoDataChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessageAttachment;
use App\Models\AvitoMessengerAccount;
use App\Models\AvitoMessengerSyncRun;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsIsolatedAvitoDatabase;
use Tests\TestCase;

class AvitoRealtimeTest extends TestCase
{
    use BuildsIsolatedAvitoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAvitoTestDatabase();
        config([
            'realtime.enabled' => true,
            'realtime.queue_connection' => 'database',
            'realtime.queue' => 'realtime',
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'avito-public-key',
            'broadcasting.connections.reverb.secret' => 'never-publish-secret',
            'broadcasting.connections.reverb.app_id' => 'internal-app-id',
            'avito.webhook_secret' => 'webhook-secret',
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
        Queue::fake();
        Http::preventStrayRequests();
    }

    public function test_private_channel_requires_active_verified_employee_or_admin_without_warehouse_permissions(): void
    {
        $this->authenticateChannel()->assertUnauthorized();
        $employee = $this->user();
        $admin = $this->user(['type' => 'customer']);
        $admin->assignRole(Role::findOrCreate('admin', 'crm'));
        foreach ([$employee, $admin] as $user) {
            $this->actingAs($user);
            $this->authenticateChannel()->assertOk()->assertExactJson([
                'auth' => 'avito-public-key:'.hash_hmac('sha256', '123.456:private-avito.updates', 'never-publish-secret'),
            ]);
        }
        foreach ([['type' => 'customer'], ['status' => 'blocked'], ['email_verified_at' => null]] as $attributes) {
            $this->actingAs($this->user($attributes));
            $this->authenticateChannel()->assertForbidden();
        }
        $this->actingAs($employee);
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-avito.messages'])->assertForbidden();
        config(['realtime.enabled' => false]);
        $this->authenticateChannel()->assertForbidden();
    }

    public function test_inertia_shares_only_public_avito_config_and_keeps_commerce_permissions_independent(): void
    {
        $request = Request::create('/Ameise/avito');
        $request->setUserResolver(fn () => $this->user());
        $share = app(HandleInertiaRequests::class)->share($request);
        $config = $share['avitoRealtime']();
        $this->assertSame(['enabled', 'key', 'host', 'port', 'scheme', 'path', 'channel'], array_keys($config));
        $this->assertTrue($config['enabled']);
        $this->assertSame('avito.updates', $config['channel']);
        $this->assertStringNotContainsString('never-publish-secret', json_encode($config));
        $this->assertSame(['enabled' => false], $share['realtime']());
        $request->setUserResolver(fn () => null);
        $this->assertSame(['enabled' => false], $share['avitoRealtime']());
    }

    public function test_events_wait_for_outer_commit_and_payload_contains_only_topics_and_unique_id(): void
    {
        Event::fake([AvitoDataChanged::class]);
        DB::beginTransaction();
        DB::beginTransaction();
        $message = $this->message();
        DB::commit();
        Event::assertNotDispatched(AvitoDataChanged::class);
        DB::commit();

        Event::assertDispatchedTimes(AvitoDataChanged::class, 3);
        Event::assertDispatched(AvitoDataChanged::class, function (AvitoDataChanged $event): bool {
            $this->assertSame(['topics', 'event_id'], array_keys($event->broadcastWith()));
            $this->assertTrue(Str::isUuid($event->eventId));
            $this->assertSame('private-avito.updates', $event->broadcastOn()[0]->name);
            $this->assertSame('avito.changed', $event->broadcastAs());
            $this->assertSame('database', $event->connection);
            $this->assertSame('realtime', $event->queue);
            $this->assertSame(['reverb'], $event->broadcastConnections());

            return $event->topics === ['avito_messages'];
        });
        $this->assertDatabaseHas('avito_messages', ['id' => $message->id, 'text' => 'Private conversation']);
        $this->assertSame(3, Event::dispatched(AvitoDataChanged::class)->map(fn ($args) => $args[0]->eventId)->unique()->count());
    }

    public function test_rollback_and_unchanged_read_saves_do_not_publish_notifications(): void
    {
        Event::fake([AvitoDataChanged::class]);
        DB::beginTransaction();
        DB::beginTransaction();
        $this->message();
        DB::commit();
        DB::rollBack();
        Event::assertNotDispatched(AvitoDataChanged::class);

        $message = $this->message();
        Event::fake([AvitoDataChanged::class]);
        $message->save();
        $message->fresh()->save();
        $message->update(['last_synced_at' => now()->addMinute(), 'payload' => ['secret' => 'new encrypted wrapper']]);
        AvitoAutoReplySetting::current()->save();
        Event::assertNotDispatched(AvitoDataChanged::class);

        $message->update(['is_read' => true]);
        $message->save();
        $message->delete();
        Event::assertDispatchedTimes(AvitoDataChanged::class, 2);
    }

    public function test_message_related_models_and_auto_reply_models_publish_their_topics(): void
    {
        $message = $this->message();
        Event::fake([AvitoDataChanged::class]);
        AvitoMessageAttachment::create(['avito_message_id' => $message->id, 'kind' => 'image']);
        AvitoMessengerSyncRun::create(['status' => 'running']);
        $message->chat->account->update(['sync_status' => 'running']);
        $message->chat->update(['peer_name' => 'New display name']);
        AvitoAutoReplySetting::current()->update(['mode' => 'off']);
        $rule = AvitoAutoReplyRule::where('key', 'greeting')->firstOrFail();
        $rule->update(['description' => 'Updated guidance']);
        $rule->examples()->create(['kind' => 'positive', 'text' => 'Hello']);
        AvitoAutoReplyDecision::create(['avito_message_id' => $message->id, 'avito_chat_id' => $message->avito_chat_id]);
        $rule->delete();

        Event::assertDispatchedTimes(AvitoDataChanged::class, 9);
        $events = Event::dispatched(AvitoDataChanged::class)->map(fn ($args) => $args[0]);
        $this->assertCount(4, $events->filter(fn ($event) => $event->topics === ['avito_messages']));
        $this->assertCount(5, $events->filter(fn ($event) => $event->topics === ['avito_auto_replies']));
    }

    public function test_webhook_updates_archive_and_broadcasts_even_when_realtime_queue_is_unavailable(): void
    {
        Bus::fake();
        Queue::shouldReceive('connection')->with('database')->andReturnSelf();
        Queue::shouldReceive('pushOn')->with('realtime', Mockery::type(BroadcastEvent::class))
            ->andThrow(new RuntimeException('Private queue credential'));
        Log::shouldReceive('warning')->with('avito_realtime_unavailable')->atLeast()->once();

        $this->postJson('/api/avito/webhook', [
            'id' => 'realtime-webhook',
            'payload' => ['type' => 'message', 'value' => [
                'id' => 'incoming-realtime', 'chat_id' => 'chat-webhook', 'chat_type' => 'u2i',
                'user_id' => 777, 'author_id' => 888, 'type' => 'text', 'direction' => 'in',
                'created' => now()->timestamp, 'content' => ['text' => 'Private incoming greeting'],
            ]],
        ], ['X-Secret' => 'webhook-secret'])->assertAccepted();

        $this->assertDatabaseHas('avito_messages', ['external_message_id' => 'incoming-realtime', 'text' => 'Private incoming greeting']);
        $this->assertDatabaseHas('avito_webhook_events', ['external_event_id' => 'realtime-webhook', 'status' => 'processed']);
        Http::assertNothingSent();
    }

    public function test_sync_queue_is_never_used_to_broadcast_inside_message_request(): void
    {
        config(['realtime.queue_connection' => 'sync']);
        Event::fake([AvitoDataChanged::class]);
        Log::shouldReceive('warning')->times(3)->with('avito_realtime_unavailable');
        $this->message();
        Event::assertNotDispatched(AvitoDataChanged::class);
        Queue::assertNothingPushed();
    }

    public function test_unapproved_topic_cannot_leak_into_event_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AvitoDataChanged(['avito_messages', 'customer-phone']);
    }

    private function authenticateChannel(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-avito.updates']);
    }

    private function user(array $attributes = []): User
    {
        return User::forceCreate($attributes + [
            'name' => 'Avito operator', 'email' => Str::uuid().'@example.test',
            'email_verified_at' => now(), 'password' => 'password', 'type' => 'employee', 'status' => 'active',
        ]);
    }

    private function message(): AvitoMessage
    {
        $account = AvitoMessengerAccount::create(['source_key' => 'client_credentials', 'external_user_id' => '777']);
        $chat = AvitoChat::create(['avito_messenger_account_id' => $account->id, 'external_chat_id' => 'chat-realtime']);

        return AvitoMessage::create([
            'avito_chat_id' => $chat->id, 'external_message_id' => 'message-realtime',
            'text' => 'Private conversation', 'type' => 'text', 'remote_type' => 'text', 'direction' => 'in',
        ]);
    }
}
