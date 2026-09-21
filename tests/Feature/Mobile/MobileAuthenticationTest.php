<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_login_issues_a_scoped_expiring_token_and_a_minimal_profile(): void
    {
        $user = $this->employee();
        $response = $this->postJson('/api/mobile/v1/auth/login', $this->credentials($user))
            ->assertOk()->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('abilities', ['mobile:orders']);

        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertSame('mobile:Склад Android', $token->name);
        $this->assertSame(['mobile:orders'], $token->abilities);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addMinutes(480)));
        $this->assertSame(['id', 'name', 'email'], array_keys($response->json('user')));
        $this->withToken($response->json('token'))->getJson('/api/mobile/v1/auth/me')
            ->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_legacy_crm_administrators_can_use_the_same_mobile_workflow(): void
    {
        $user = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $user->assignRole(Role::findOrCreate('admin', 'crm'));

        $this->postJson('/api/mobile/v1/auth/login', $this->credentials($user))->assertOk();
    }

    public function test_customers_and_staff_without_warehouse_rights_cannot_sign_in(): void
    {
        foreach (['customer', 'employee'] as $type) {
            $user = User::factory()->create(['type' => $type, 'status' => 'active']);
            if ($type === 'customer') {
                $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
            }

            $this->postJson('/api/mobile/v1/auth/login', $this->credentials($user))->assertForbidden();
            $this->assertCount(0, $user->tokens);
        }
    }

    public function test_blocked_or_unverified_staff_cannot_sign_in(): void
    {
        foreach ([['status' => 'blocked'], ['email_verified_at' => null]] as $attributes) {
            $user = $this->employee($attributes);

            $this->postJson('/api/mobile/v1/auth/login', $this->credentials($user))->assertForbidden();
            $this->assertCount(0, $user->tokens);
        }
    }

    public function test_wrong_credentials_do_not_create_tokens_and_are_rate_limited(): void
    {
        $user = $this->employee();
        $credentials = [...$this->credentials($user), 'password' => 'incorrect'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/mobile/v1/auth/login', $credentials)
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/mobile/v1/auth/login', $credentials)->assertTooManyRequests();
        $this->assertCount(0, $user->tokens);
    }

    public function test_confirmed_two_factor_authentication_requires_a_valid_code(): void
    {
        $user = $this->employeeWithTwoFactor();
        $credentials = $this->credentials($user);

        $this->postJson('/api/mobile/v1/auth/login', $credentials)
            ->assertUnprocessable()->assertJsonPath('code', 'two_factor_required');
        $this->postJson('/api/mobile/v1/auth/login', [...$credentials, 'two_factor_code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');
        $this->assertCount(0, $user->tokens);

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->postJson('/api/mobile/v1/auth/login', [...$credentials, 'two_factor_code' => $code])->assertOk();

        // The Fortify provider rejects a code that has already been consumed.
        $this->postJson('/api/mobile/v1/auth/login', [...$credentials, 'two_factor_code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');
    }

    public function test_a_two_factor_recovery_code_can_only_be_used_once(): void
    {
        $user = $this->employeeWithTwoFactor();
        $credentials = [...$this->credentials($user), 'recovery_code' => 'recovery-one'];

        $this->postJson('/api/mobile/v1/auth/login', $credentials)->assertOk();
        $this->postJson('/api/mobile/v1/auth/login', $credentials)
            ->assertUnprocessable()->assertJsonValidationErrors('recovery_code');
        $this->assertNotContains('recovery-one', $user->fresh()->recoveryCodes());
        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_expired_revoked_generic_and_unbounded_tokens_cannot_access_mobile_api(): void
    {
        $user = $this->employee();
        $tokens = [
            $user->createToken('mobile:expired', ['mobile:orders'], now()->subMinute()),
            $user->createToken('mobile:unbounded', ['mobile:orders']),
            $user->createToken('integration', ['*'], now()->addHour()),
            $user->createToken('mobile:wildcard', ['*'], now()->addHour()),
            $user->createToken('mobile:wrong', ['orders:read'], now()->addHour()),
        ];
        $revoked = $this->token($user);
        $user->tokens()->where('name', 'mobile:Android')->delete();

        foreach ([...array_map(fn ($token) => $token->plainTextToken, $tokens), $revoked] as $token) {
            $this->withToken($token)->getJson('/api/mobile/v1/auth/me')->assertUnauthorized();
        }
    }

    public function test_employee_access_is_rechecked_when_the_account_or_permissions_change(): void
    {
        foreach (['status', 'type', 'verified', 'permission'] as $change) {
            $user = $this->employee();
            $token = $this->token($user);
            $this->withToken($token)->getJson('/api/mobile/v1/auth/me')->assertOk();

            match ($change) {
                'status' => $user->update(['status' => 'blocked']),
                'type' => $user->update(['type' => 'customer']),
                'verified' => $user->forceFill(['email_verified_at' => null])->save(),
                'permission' => $user->revokePermissionTo('warehouse.move'),
            };

            $this->withToken($token)->getJson('/api/mobile/v1/auth/me')->assertForbidden();
        }
    }

    public function test_cookie_authentication_does_not_grant_mobile_access(): void
    {
        $this->actingAs($this->employee())->getJson('/api/mobile/v1/auth/me')->assertUnauthorized();
    }

    public function test_mobile_tokens_cannot_reach_legacy_public_or_protected_endpoints(): void
    {
        $token = $this->token($this->employee());

        foreach ([$token, explode('|', $token, 2)[1]] as $representation) {
            $this->withToken($representation)->getJson('/api/goods')->assertForbidden();
            $this->withToken($representation)->postJson('/api/sales', [])->assertForbidden();
            $this->withToken($representation)->getJson('/dashboard')->assertForbidden();
        }
    }

    public function test_existing_integration_tokens_keep_their_legacy_api_access(): void
    {
        $user = $this->employee();
        $token = $user->createToken('integration', ['*'])->plainTextToken;

        // Empty payload reaches the existing sale validation, after authorization.
        $this->withToken($token)->postJson('/api/sales', [])->assertUnprocessable();
    }

    public function test_mobile_bearer_requests_ignore_an_existing_browser_session(): void
    {
        $employee = $this->employee();
        $other = User::factory()->create(['type' => 'customer', 'status' => 'active']);

        $this->actingAs($other)->withToken($this->token($employee))
            ->getJson('/api/mobile/v1/auth/me')->assertOk()->assertJsonPath('user.id', $employee->id);
    }

    public function test_sign_out_only_revokes_the_current_device_token(): void
    {
        $user = $this->employee();
        $current = $this->token($user);
        $another = $this->token($user);
        $legacy = $user->createToken('integration', ['*']);

        $this->withToken($current)->deleteJson('/api/mobile/v1/auth/token')->assertNoContent();
        $this->withToken($current)->getJson('/api/mobile/v1/auth/me')->assertUnauthorized();
        $this->withToken($another)->getJson('/api/mobile/v1/auth/me')->assertOk();
        $this->assertNotNull(PersonalAccessToken::findToken($legacy->plainTextToken));
    }

    public function test_native_origin_login_does_not_require_browser_csrf(): void
    {
        // Laravel normally bypasses CSRF while testing. Enable the real check here.
        $this->app['env'] = 'local';
        $user = $this->employee();

        $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/mobile/v1/auth/login', $this->credentials($user))
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', 'https://localhost');
    }

    public function test_cors_allows_only_configured_mobile_origins_and_paths(): void
    {
        config()->set('cors.allowed_origins', ['https://localhost', 'http://localhost:5173']);
        $this->withHeaders([
            'Origin' => 'https://localhost',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type, Idempotency-Key',
        ])->options('/api/mobile/v1/orders/1/ship')->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://localhost')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $this->withHeader('Access-Control-Request-Method', 'PATCH')
            ->options('/api/mobile/v1/orders/1/prepare')->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://localhost')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS');

        $this->withHeader('Origin', 'https://untrusted.example')
            ->options('/api/mobile/v1/orders/1/ship')->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->withHeader('Origin', 'https://localhost')
            ->options('/api/sales')->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    private function employee(array $attributes = []): User
    {
        $user = User::factory()->create([...['type' => 'employee', 'status' => 'active'], ...$attributes]);
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));

        return $user;
    }

    private function employeeWithTwoFactor(): User
    {
        return $this->employee([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-one', 'recovery-two'])),
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('mobile:Android', ['mobile:orders'], now()->addHour())->plainTextToken;
    }

    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password', 'device_name' => 'Склад Android'];
    }
}
