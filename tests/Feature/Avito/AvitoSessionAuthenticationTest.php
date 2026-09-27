<?php

namespace Tests\Feature\Avito;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AvitoSessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalStatefulDomains;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStatefulDomains = Env::getRepository()->get('SANCTUM_STATEFUL_DOMAINS');
        config([
            'app.url' => 'https://canonical.example.test',
            'session.driver' => 'database',
            'session.connection' => null,
            'avito.enabled' => false,
            'realtime.enabled' => false,
        ]);
        $this->configureStatefulDomains('spa.example.test');
        $this->withCredentials();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $repository = Env::getRepository();
        $repository->clear('SANCTUM_STATEFUL_DOMAINS');
        if ($this->originalStatefulDomains !== null) {
            $repository->set('SANCTUM_STATEFUL_DOMAINS', $this->originalStatefulDomains);
        }

        parent::tearDown();
    }

    public function test_staff_cookie_authenticates_same_host_api_even_outside_the_configured_domains(): void
    {
        $origin = 'https://ameise.example.test:8443';
        $this->loginWithCookie($this->employee(), $origin);

        foreach (['status', 'capabilities', 'messenger/auto-replies/control'] as $endpoint) {
            $this->resetRequestAuthentication();
            $this->getJson($origin.'/api/avito/'.$endpoint, ['Referer' => $origin.'/Ameise/avito'])
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }

        Http::assertNothingSent();
    }

    public function test_staff_session_survives_avito_page_and_api_requests_in_browser_order(): void
    {
        $origin = 'https://ameise.example.test';
        $employee = $this->employee();
        $this->loginWithCookie($employee, $origin);

        for ($visit = 0; $visit < 2; $visit++) {
            $this->resetRequestAuthentication();
            $this->keepSessionCookie($this->get($origin.'/Ameise/avito')->assertOk());

            foreach (['status', 'capabilities', 'messenger/auto-replies/control'] as $endpoint) {
                $this->resetRequestAuthentication();
                $this->keepSessionCookie(
                    $this->getJson($origin.'/api/avito/'.$endpoint, ['Referer' => $origin.'/Ameise/avito'])
                        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                );
            }

            $this->resetRequestAuthentication();
            $this->keepSessionCookie(
                $this->getJson($origin.'/api/user', ['Referer' => $origin.'/Ameise/avito'])
                    ->assertOk()->assertJsonPath('id', $employee->id)
            );
        }

        Http::assertNothingSent();
    }

    public function test_changing_a_staff_password_still_invalidates_the_browser_session(): void
    {
        $origin = 'https://ameise.example.test';
        $employee = $this->employee();
        $this->loginWithCookie($employee, $origin);
        $this->resetRequestAuthentication();
        $this->keepSessionCookie($this->get($origin.'/Ameise/avito')->assertOk());

        $this->resetRequestAuthentication();
        $this->keepSessionCookie(
            $this->getJson($origin.'/api/user', ['Referer' => $origin.'/Ameise/avito'])
                ->assertOk()->assertJsonPath('id', $employee->id)
        );

        $employee->forceFill(['password' => 'changed-password'])->save();

        $this->resetRequestAuthentication();
        $this->keepSessionCookie(
            $this->getJson($origin.'/api/avito/status', ['Referer' => $origin.'/Ameise/avito'])
                ->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.')
        );

        $this->resetRequestAuthentication();
        $this->get($origin.'/Ameise/avito')->assertRedirect(route('Ameise.login'));
        Http::assertNothingSent();
    }

    public function test_legacy_raw_password_hash_session_is_accepted_and_upgraded_by_the_api(): void
    {
        $origin = 'https://ameise.example.test';
        $employee = $this->employee();
        $this->loginWithCookie($employee, $origin);
        // Existing sessions may contain Sanctum's pre-HMAC password hash format.
        app('session.store')->put('password_hash_web', $employee->getAuthPassword());
        app('session.store')->save();

        $this->resetRequestAuthentication();
        $this->keepSessionCookie(
            $this->getJson($origin.'/api/user', ['Referer' => $origin.'/Ameise/avito'])
                ->assertOk()->assertJsonPath('id', $employee->id)
        );
        $this->assertSame(
            Auth::guard('web')->hashPasswordForCookie($employee->getAuthPassword()),
            app('session.store')->get('password_hash_web')
        );

        $this->resetRequestAuthentication();
        $this->get($origin.'/Ameise/avito')->assertOk();
        Http::assertNothingSent();
    }

    public function test_punycode_browser_host_works_with_a_cyrillic_application_url(): void
    {
        config(['app.url' => 'https://пример.рф']);
        $this->configureStatefulDomains(null);
        $origin = 'https://xn--e1afmkfd.xn--p1ai';
        $this->loginWithCookie($this->employee(), $origin);

        $this->resetRequestAuthentication();
        $this->getJson($origin.'/api/avito/status', ['Referer' => $origin.'/Ameise/avito'])->assertOk();
        Http::assertNothingSent();
    }

    public function test_origin_header_and_explicit_frontend_allowlist_still_authenticate_sessions(): void
    {
        $origin = 'https://ameise.example.test';
        $this->loginWithCookie($this->employee(), $origin);

        foreach ([$origin, 'https://spa.example.test'] as $frontend) {
            $this->resetRequestAuthentication();
            $this->getJson($origin.'/api/avito/status', ['Origin' => $frontend])->assertOk();
        }

        Http::assertNothingSent();
    }

    public function test_untrusted_origins_wrong_ports_and_missing_origin_headers_do_not_enable_cookie_authentication(): void
    {
        $origin = 'https://ameise.example.test';
        $this->loginWithCookie($this->employee(), $origin);

        foreach ([
            ['Origin' => 'https://untrusted.example.test'],
            ['Referer' => 'https://ameise.example.test.attacker.test/Ameise/avito'],
            ['Origin' => $origin.':8443'],
            [],
        ] as $headers) {
            $this->resetRequestAuthentication();
            $this->getJson($origin.'/api/avito/status', $headers)
                ->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        }

        Http::assertNothingSent();
    }

    public function test_guests_customers_and_blocked_staff_cannot_access_the_api(): void
    {
        $origin = 'https://ameise.example.test';
        $headers = ['Referer' => $origin.'/Ameise/avito'];
        $this->getJson($origin.'/api/avito/status', $headers)->assertUnauthorized();

        $customer = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $this->loginWithCookie($customer, $origin, '/login');
        $this->resetRequestAuthentication();
        $this->getJson($origin.'/api/avito/status', $headers)->assertForbidden();

        // A session issued before an employee was blocked must stop working too.
        $this->unencryptedCookies = [];
        $employee = $this->employee();
        $this->loginWithCookie($employee, $origin);
        $employee->update(['status' => 'blocked']);
        $this->resetRequestAuthentication();
        $this->getJson($origin.'/api/avito/status', $headers)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_same_host_cookie_mutations_still_require_a_valid_csrf_token(): void
    {
        $origin = 'https://ameise.example.test';
        $csrfToken = $this->loginWithCookie($this->employee(), $origin);
        // Laravel normally skips CSRF validation in PHPUnit; exercise the real middleware.
        $this->app->instance('env', 'local');
        $headers = ['Origin' => $origin];

        $this->resetRequestAuthentication();
        $this->postJson($origin.'/api/avito/messenger/auto-replies/emergency-stop', [], $headers)
            ->assertStatus(419);

        $this->resetRequestAuthentication();
        $this->postJson($origin.'/api/avito/messenger/auto-replies/emergency-stop', [], [
            ...$headers,
            'X-CSRF-TOKEN' => $csrfToken,
        ])->assertOk()->assertJsonPath('settings.is_emergency_stopped', true);

        Http::assertNothingSent();
    }

    private function employee(): User
    {
        return User::factory()->create(['type' => 'employee', 'status' => 'active']);
    }

    private function configureStatefulDomains(?string $domains): void
    {
        $repository = Env::getRepository();
        $repository->clear('SANCTUM_STATEFUL_DOMAINS');
        if ($domains !== null) {
            $repository->set('SANCTUM_STATEFUL_DOMAINS', $domains);
        }

        config(['sanctum' => require config_path('sanctum.php')]);
    }

    private function loginWithCookie(User $user, string $origin, string $path = '/Ameise/login'): string
    {
        $this->resetRequestAuthentication();
        $response = $this->post($origin.$path, ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->keepSessionCookie($response);

        return app('session.store')->token();
    }

    private function keepSessionCookie(TestResponse $response): void
    {
        $cookie = $response->getCookie(config('session.cookie'), false);
        $this->assertNotNull($cookie);
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
    }

    private function resetRequestAuthentication(): void
    {
        // Force every request to recover its user from the encrypted browser cookie.
        // actingAs() or the previous request's guard would hide missing session middleware.
        Auth::shouldUse('web');
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
    }
}
