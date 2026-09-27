<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireStaffAuthentication;
use App\Models\User;
use App\Services\Auth\StaffRouteAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffAuthenticationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        Queue::fake();
    }

    public function test_every_internal_route_in_the_registry_has_the_shared_boundary_before_bindings(): void
    {
        $boundary = app(StaffRouteAccess::class);
        $protected = 0;
        $ameise = 0;

        foreach (Route::getRoutes() as $route) {
            if (array_intersect(['web', 'api'], $route->middleware()) === []) {
                continue; // Stateless provider ingress and the health endpoint.
            }

            foreach ($route->methods() as $method) {
                if (str_starts_with($route->uri(), 'Ameise')) {
                    $ameise++;
                    $this->assertTrue($boundary->requiresStaff($route, $method), $route->uri());
                }

                if (! $boundary->requiresStaff($route, $method)) {
                    continue;
                }

                $protected++;
                $middleware = app('router')->gatherRouteMiddleware($route);
                $this->assertContains(RequireStaffAuthentication::class, $middleware, $route->uri());
                $staffPosition = array_search(RequireStaffAuthentication::class, $middleware, true);
                $bindingPosition = array_search(SubstituteBindings::class, $middleware, true);
                if ($bindingPosition !== false) {
                    $this->assertLessThan($bindingPosition, $staffPosition, $route->uri());
                }
            }
        }

        $this->assertGreaterThan(500, $protected);
        $this->assertGreaterThan(100, $ameise);
    }

    public function test_exceptions_match_exact_registered_methods_and_uris_and_never_an_internal_prefix(): void
    {
        $boundary = app(StaffRouteAccess::class);
        foreach (StaffRouteAccess::EXCEPTIONS as $uri => $methods) {
            $this->assertStringNotContainsString('*', $uri);
            $this->assertFalse(str_starts_with($uri, 'Ameise'));
            foreach ($methods as $method) {
                $route = collect(Route::getRoutes()->getRoutes())->first(
                    fn (RoutingRoute $route) => $route->uri() === $uri && in_array($method, $route->methods(), true),
                );
                $this->assertNotNull($route, $method.' '.$uri.' must retain a reviewed route');
                $this->assertFalse($boundary->requiresStaff($route, $method));
            }
            $wrongMethod = current(array_diff(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $methods));
            $this->assertTrue($boundary->requiresStaff(new RoutingRoute($wrongMethod, $uri, fn () => null), $wrongMethod));
            $this->assertTrue($boundary->requiresStaff(new RoutingRoute('GET', $uri.'/admin', fn () => null), 'GET'));
        }

        foreach (['api/mailboxes', 'api/mail-messages', 'api/avito/webhooks', 'api/marketing/yandex/oauth/redirect',
            'api/avito/oauth/redirect', 'web/entities', 'web/entities/lookup', 'web/entities/building-postcode',
            'purchases', 'gis/2gis', 'city/store', 'genera/{genus}/toggle-agriculturable'] as $uri) {
            $this->assertTrue($boundary->requiresStaff(new RoutingRoute('GET', $uri, fn () => null), 'GET'), $uri);
        }
    }

    public function test_guests_use_normal_login_for_pages_and_receive_401_for_api_even_without_accept_header(): void
    {
        foreach (['/Ameise', '/Ameise/', '/Ameise/Mail', '/Ameise/Avito', '/purchases', '/gis/2gis', '/web/entities'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
            $this->getJson($uri)->assertUnauthorized();
        }

        foreach (['/api/mailboxes', '/api/mail-messages', '/api/mail-messages/999999',
            '/api/mail-messages/999999/attachments/0/download', '/api/avito/webhooks',
            '/api/marketing/yandex/oauth/redirect', '/api/avito/oauth/redirect'] as $uri) {
            $this->get($uri)->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        }

        $this->postJson('/api/mailboxes', [])->assertUnauthorized();
        $this->patchJson('/api/mailboxes/999999', [])->assertUnauthorized();
        $this->deleteJson('/api/mailboxes/999999')->assertUnauthorized();
        $this->postJson('/api/emails/sync-yandex')->assertUnauthorized();
        $this->postJson('/api/mail-messages/send')->assertUnauthorized();
        $this->postJson('/api/good/store')->assertUnauthorized();
        $this->postJson('/city/store')->assertUnauthorized();
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_external_customers_are_forbidden_even_with_an_individual_crm_permission(): void
    {
        $customer = $this->user('customer');
        $customer->givePermissionTo(Permission::findOrCreate('mail.send', 'crm'));
        $this->actingAs($customer);

        foreach (['/Ameise', '/Ameise/Mail', '/api/mailboxes', '/api/mail-messages', '/web/entities', '/purchases'] as $uri) {
            $this->getJson($uri)->assertForbidden();
        }
        $this->postJson('/api/emails/sync-yandex')->assertForbidden();
        $this->postJson('/api/mail-messages/send')->assertForbidden();
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_active_staff_and_legacy_customer_admin_or_manager_can_enter_but_blocked_staff_cannot(): void
    {
        $this->actingAs($this->user('customer'))->getJson('/api/mailboxes')->assertForbidden();
        $employee = $this->user('employee');
        $this->actingAs($employee)->get('/Ameise/Mail')->assertOk();
        $this->getJson('/api/mailboxes')->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        foreach (['admin', 'manager'] as $role) {
            $legacy = $this->user('customer');
            $legacy->assignRole(Role::findOrCreate($role, 'crm'));
            $this->actingAs($legacy)->get('/Ameise/Mail')->assertOk();
            $this->getJson('/api/mailboxes')->assertOk();
        }

        $blocked = $this->user('employee', ['status' => 'blocked']);
        $blocked->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($blocked)->getJson('/Ameise/Mail')->assertForbidden();
        $this->getJson('/api/mailboxes')->assertForbidden();
    }

    public function test_login_preserves_the_requested_admin_page_and_existing_operation_checks_remain(): void
    {
        $employee = $this->user('employee');
        $this->get('/Ameise/Mail')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $employee->email, 'password' => 'password'])
            ->assertRedirect('/Ameise/Mail');
        $this->get('/Ameise/Mail')->assertOk();
        $this->postJson('/api/mail-messages/send')->assertForbidden(); // No mail.send permission.

        $unverified = $this->user('employee', ['email_verified_at' => null]);
        $unverified->givePermissionTo(Permission::findOrCreate('mail.send', 'crm'));
        $this->actingAs($unverified)->postJson('/api/mail-messages/send')->assertForbidden();
        $this->postJson('/Ameise/bank/sync')->assertForbidden();
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_public_storefront_registration_customer_account_and_mobile_auth_contracts_are_preserved(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/privacy-policy')->assertOk();
        $this->getJson('/goods/published')->assertOk();
        $this->getJson('/location/cities')->assertOk();
        $this->getJson('/web/entities/lookup-by-inn?inn=invalid')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/auth/me')->assertUnauthorized()
            ->assertJsonPath('message', 'Войдите в мобильное приложение заново.');
        $this->postJson('/api/mobile/v1/auth/login', [])->assertUnprocessable();

        $customer = $this->user('customer');
        $this->actingAs($customer)->get('/dashboard')->assertOk();
        $this->get('/dashboard/profile')->assertOk();
        $this->getJson('/api/user')->assertOk()->assertJsonPath('id', $customer->id);
        $this->getJson('/Ameise/Mail')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_new_routes_are_private_without_opt_in_and_public_uri_cannot_open_other_methods(): void
    {
        Route::middleware('web')->get('/_boundary/future-page', fn () => response('private'));
        Route::middleware('api')->get('/api/mobile/v1/future-private-route', fn () => response('private'));
        Route::middleware('api')->post('/api/avito/oauth/callback', fn () => response('private'));

        $this->get('/_boundary/future-page')->assertRedirect(route('login'));
        $this->get('/api/mobile/v1/future-private-route')->assertUnauthorized();
        $this->postJson('/api/avito/oauth/callback')->assertUnauthorized();
    }

    private function user(string $type, array $attributes = []): User
    {
        return User::factory()->create(['type' => $type, 'status' => 'active', ...$attributes]);
    }
}
