<?php

namespace Tests\Feature\Mail;

use App\Models\Unit;
use App\Models\User;
use App\Services\Mail\AuthorizedMailDispatchService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LegacyMailRouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        Storage::fake('yandex');
        config()->set([
            'mail.default' => 'array',
            'mail.from.address' => 'server@example.test',
            'mail.from.name' => 'Pischeprom CRM',
            'services.yandex_mail.mailboxes' => [[
                'address' => 'server@example.test', 'name' => 'Pischeprom CRM',
                'from_name' => 'Pischeprom CRM', 'is_default' => true, 'is_active' => true,
            ]],
        ]);
    }

    public function test_route_registry_has_no_diagnostic_get_or_anonymous_legacy_alias(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $this->assertFalse($routes->contains(fn ($route) => $route->uri() === 'send-email'));
        $this->assertFalse($routes->contains(fn ($route) => $route->uri() === 'api/mail'));
        $this->assertSame(404, $this->get('/send-email')->getStatusCode());
        $this->assertSame(404, $this->postJson('/api/mail')->getStatusCode());

        foreach (['api/mail-messages/send', 'api/units/{unit}/mail/send'] as $uri) {
            $route = $routes->first(fn ($candidate) => $candidate->uri() === $uri && in_array('POST', $candidate->methods(), true));
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('verified', $middleware);
            $this->assertContains('can:mail.send', $middleware);
            $this->assertContains('throttle:mail-send', $middleware);
        }

        $this->assertFalse($routes->contains(fn ($route) => in_array('GET', $route->methods(), true)
            && str_contains(strtolower($route->getActionName()), 'sendmail')));
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_existing_commercial_mail_surface_is_not_anonymous(): void
    {
        foreach ([
            '/Ameise/commercial-offers/campaigns/1/send-test',
            '/Ameise/commercial-offers/campaigns/1/start',
            '/Ameise/commercial-offers/templates/1/send-test',
        ] as $uri) {
            $this->postJson($uri)->assertUnauthorized();
        }

        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_guest_unverified_and_user_without_permission_are_blocked(): void
    {
        $payload = $this->payload();
        $unit = Unit::query()->create(['name' => 'Mail security Unit', 'is_customer' => false, 'is_supplier' => false]);

        $this->postJson('/api/mail-messages/send', $payload)->assertUnauthorized();
        $this->postJson("/api/units/{$unit->id}/mail/send", $payload)->assertUnauthorized();

        $unverified = $this->mailUser(['mail.send'], verified: false);
        $this->actingAs($unverified)->postJson('/api/mail-messages/send', $payload)->assertForbidden();

        $withoutPermission = $this->mailUser([], verified: true);
        $this->actingAs($withoutPermission)->postJson('/api/mail-messages/send', $payload)->assertForbidden();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
    }

    public function test_authorized_manual_contract_is_preserved_with_server_owned_sender_and_idempotency(): void
    {
        $actor = $this->mailUser(['mail.send'], verified: true);
        $payload = $this->payload();

        $this->actingAs($actor)->postJson('/api/mail-messages/send', $payload)
            ->assertOk()->assertJsonPath('duplicate', false)->assertJsonPath('message', 'Письмо отправлено.');
        $this->postJson('/api/mail-messages/send', $payload)
            ->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 1);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseHas('mail_messages', ['from_address' => 'server@example.test', 'subject' => 'Manual test']);
        $attempt = \App\Models\AuthorizedMailDispatchAttempt::query()->firstOrFail();
        $this->assertSame(64, strlen($attempt->idempotency_key_hash));
        $this->assertStringNotContainsString('recipient@example.test', json_encode($attempt->getAttributes(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('body content', json_encode($attempt->getAttributes(), JSON_THROW_ON_ERROR));
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_unit_policy_blocks_idor_and_service_rechecks_revoked_permission(): void
    {
        $unit = Unit::query()->create(['name' => 'Scoped Unit', 'is_customer' => false, 'is_supplier' => false]);
        $mailOnly = $this->mailUser(['mail.send'], verified: true);
        $this->actingAs($mailOnly)->postJson("/api/units/{$unit->id}/mail/send", $this->payload())->assertForbidden();

        $authorized = $this->mailUser(['mail.send', 'ai_sales.view'], verified: true);
        $this->actingAs($authorized)->postJson("/api/units/{$unit->id}/mail/send", $this->payload())
            ->assertOk()->assertJsonPath('stored_locally', true);
        $authorized->revokePermissionTo('mail.send');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->expectException(AuthorizationException::class);
        app(AuthorizedMailDispatchService::class)->authorize($authorized->fresh(), $unit);
    }

    public function test_strict_request_rejects_arbitrary_headers_paths_and_header_injection(): void
    {
        $actor = $this->mailUser(['mail.send'], verified: true);
        $payload = $this->payload();
        $payload['headers'] = ['X-Anything' => 'value'];
        $payload['from'] = 'attacker@example.test';
        $payload['subject'] = "safe\r\nBcc: victim@example.test";
        $payload['storage_files'] = ['../../secrets/.env'];

        $this->actingAs($actor)->postJson('/api/mail-messages/send', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['headers', 'from', 'subject', 'storage_files']);
        Mail::assertNothingSent();
    }

    public function test_code_owned_rate_limit_is_applied(): void
    {
        $actor = $this->mailUser(['mail.send'], verified: true);
        $this->actingAs($actor);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/mail-messages/send', $this->payload())->assertOk();
        }
        $this->postJson('/api/mail-messages/send', $this->payload())->assertTooManyRequests();
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 5);
    }

    private function payload(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'to' => ['recipient@example.test'],
            'subject' => 'Manual test',
            'body' => 'body content',
        ];
    }

    private function mailUser(array $permissions, bool $verified): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => $verified ? now() : null,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'crm']);
        }
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
