<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginPortalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_portals_render_separate_forms_without_a_redirect_loop(): void
    {
        $this->get('/login')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
        $this->get('/Ameise/login')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/AmeiseLogin'));
        $this->followingRedirects()->get('/Ameise/avito')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/AmeiseLogin'));
    }

    public function test_customer_pages_still_request_public_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/dashboard/profile')->assertRedirect(route('login'));
    }

    public function test_employee_login_always_opens_ameise_home_and_keeps_remember_me(): void
    {
        $employee = $this->user('employee');
        $this->get('/Ameise/avito')->assertRedirect(route('Ameise.login'));

        $this->post('/Ameise/login', [
            'email' => $employee->email,
            'password' => 'password',
            'remember' => true,
        ])->assertRedirect(route('Ameise'))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('url.intended')
            ->assertSessionMissing('auth.login_portal')
            ->assertCookie(Auth::guard('web')->getRecallerName());

        $this->assertAuthenticatedAs($employee);
    }

    public function test_legacy_customer_admin_and_manager_can_use_admin_login(): void
    {
        foreach (['admin', 'manager'] as $role) {
            $legacy = $this->user('customer');
            $legacy->assignRole(Role::findOrCreate($role, 'crm'));

            $this->post('/Ameise/login', $this->credentials($legacy))
                ->assertRedirect(route('Ameise'));
            $this->assertAuthenticatedAs($legacy);
            $this->post('/logout');
        }
    }

    public function test_customer_and_blocked_staff_cannot_authenticate_through_admin_portal(): void
    {
        $customer = $this->user('customer');
        $customer->givePermissionTo(Permission::findOrCreate('mail.send', 'crm'));
        $blockedEmployee = $this->user('employee', ['status' => 'blocked']);
        $blockedAdmin = $this->user('customer', ['status' => 'blocked']);
        $blockedAdmin->assignRole(Role::findOrCreate('admin', 'crm'));

        foreach ([$customer, $blockedEmployee, $blockedAdmin] as $user) {
            $this->from('/Ameise/login')->post('/Ameise/login', $this->credentials($user))
                ->assertRedirect('/Ameise/login')
                ->assertSessionHasErrors('email')
                ->assertSessionMissing('login.id');
            $this->assertGuest();
        }
    }

    public function test_invalid_password_cannot_authenticate_through_admin_portal(): void
    {
        $employee = $this->user('employee');

        $this->from('/Ameise/login')->post('/Ameise/login', [
            'email' => $employee->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/Ameise/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_login_retains_fortify_throttling(): void
    {
        $employee = $this->user('employee');
        $credentials = ['email' => $employee->email, 'password' => 'wrong-password'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/Ameise/login', $credentials)
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->postJson('/Ameise/login', $credentials)->assertTooManyRequests();
        $this->postJson('/login', $credentials)->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_public_login_discards_stale_admin_portal_and_internal_destination(): void
    {
        $customer = $this->user('customer');

        $this->withSession([
            'auth.login_portal' => 'ameise',
            'url.intended' => route('Ameise.avito'),
        ])->post('/login', $this->credentials($customer))
            ->assertRedirect('/dashboard')
            ->assertSessionMissing('url.intended')
            ->assertSessionMissing('auth.login_portal');

        $this->assertAuthenticatedAs($customer);
    }

    public function test_public_login_preserves_a_customer_account_destination(): void
    {
        $customer = $this->user('customer');

        $this->get('/dashboard/profile')->assertRedirect(route('login'));
        $this->post('/login', $this->credentials($customer))
            ->assertRedirect('/dashboard/profile');
    }

    public function test_public_login_never_follows_a_stale_admin_login_destination(): void
    {
        $customer = $this->user('customer');

        $this->withSession(['url.intended' => route('Ameise.login')])
            ->post('/login', $this->credentials($customer))
            ->assertRedirect('/dashboard');
    }

    public function test_admin_password_login_still_requires_two_factor_and_then_opens_ameise_home(): void
    {
        $employee = $this->twoFactorUser('employee');
        $this->get('/Ameise/avito')->assertRedirect(route('Ameise.login'));

        $this->post('/Ameise/login', [...$this->credentials($employee), 'remember' => true])
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $employee->id)
            ->assertSessionHas('auth.login_portal', 'ameise');
        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertOk();

        $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['recovery_code' => 'wrong'])
            ->assertSessionHasErrors('recovery_code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['recovery_code' => 'test-recovery-code'])
            ->assertRedirect(route('Ameise'))
            ->assertSessionMissing('auth.login_portal')
            ->assertSessionMissing('url.intended')
            ->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($employee);
        $this->assertNotContains('test-recovery-code', $employee->fresh()->recoveryCodes());
    }

    public function test_staff_blocked_during_two_factor_challenge_cannot_finish_admin_login(): void
    {
        $employee = $this->twoFactorUser('employee');
        $this->post('/Ameise/login', $this->credentials($employee))
            ->assertRedirect(route('two-factor.login'));

        $employee->update(['status' => 'blocked']);

        $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['recovery_code' => 'test-recovery-code'])
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('login.id');
        $this->assertGuest();
        $this->assertContains('test-recovery-code', $employee->fresh()->recoveryCodes());
    }

    public function test_public_two_factor_login_replaces_a_previous_admin_challenge(): void
    {
        $employee = $this->twoFactorUser('employee');
        $customer = $this->twoFactorUser('customer');

        $this->post('/Ameise/login', $this->credentials($employee))
            ->assertRedirect(route('two-factor.login'));
        $this->post('/login', $this->credentials($customer))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $customer->id)
            ->assertSessionHas('auth.login_portal', 'customer');

        $this->post('/two-factor-challenge', ['recovery_code' => 'test-recovery-code'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($customer);
    }

    public function test_existing_sessions_redirect_from_admin_login_to_their_own_home(): void
    {
        $this->actingAs($this->user('employee'))->get('/Ameise/login')
            ->assertRedirect(route('Ameise'));
        $this->actingAs($this->user('customer'))->get('/Ameise/login')
            ->assertRedirect(route('dashboard'));
    }

    private function user(string $type, array $attributes = []): User
    {
        return User::factory()->create(['type' => $type, 'status' => 'active', ...$attributes]);
    }

    private function twoFactorUser(string $type): User
    {
        return $this->user($type, [
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['test-recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }
}
