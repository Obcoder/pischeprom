<?php

namespace Tests\Feature;

use App\Models\MailingCampaign;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommercialOffersAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'sales_mailings.view',
        'sales_mailings.edit',
        'sales_mailings.send_test',
        'sales_mailings.send_mass',
        'sales_mailings.compliance_override',
        'sales_mailings.manage_templates',
        'sales_mailings.manage_suppression',
    ];

    private const INITIAL_ENDPOINTS = [
        'campaigns', 'contacts', 'sets', 'templates', 'events',
        'suppression', 'source-emails', 'price-types',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Http::fake();
        Http::preventStrayRequests();
        Queue::fake();

        Route::middleware('web')->get('/_test/mailing-navigation', fn () => Inertia::render('Admin/CommercialOffers'));
    }

    public function test_legacy_crm_admin_can_open_page_without_registered_mailing_permissions(): void
    {
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = Role::findOrCreate('admin', 'crm');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($admin);

        $this->assertSame(0, $user->getAllPermissions()->whereIn('name', self::PERMISSIONS)->count());
        $this->actingAs($user)->get('/Ameise/commercial-offers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/CommercialOffers')
                ->where('auth.permissions.sales_mailings.view', true)
                ->where('permissions.view', true)
                ->where('permissions.edit', true)
                ->where('permissions.send_test', true)
                ->where('permissions.send_mass', true)
                ->where('permissions.compliance_override', true)
                ->where('permissions.manage_templates', true)
                ->where('permissions.manage_suppression', true));

        foreach (self::INITIAL_ENDPOINTS as $endpoint) {
            $this->getJson('/Ameise/commercial-offers/'.$endpoint)->assertOk();
        }

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_explicit_view_permission_loads_page_and_all_initial_data_without_write_permissions(): void
    {
        $user = $this->userWithPermissions(['sales_mailings.view']);

        $this->actingAs($user)->get('/Ameise/commercial-offers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/CommercialOffers')
                ->where('auth.permissions.sales_mailings.view', true)
                ->where('permissions.view', true)
                ->where('permissions.edit', false)
                ->where('permissions.send_test', false)
                ->where('permissions.send_mass', false)
                ->where('permissions.compliance_override', false)
                ->where('permissions.manage_templates', false)
                ->where('permissions.manage_suppression', false));

        foreach (self::INITIAL_ENDPOINTS as $endpoint) {
            $this->getJson('/Ameise/commercial-offers/'.$endpoint)->assertOk();
        }

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_users_without_view_permission_cannot_open_page_or_read_initial_data(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user)->get('/Ameise/commercial-offers')->assertForbidden();

        foreach (self::INITIAL_ENDPOINTS as $endpoint) {
            $this->getJson('/Ameise/commercial-offers/'.$endpoint)->assertForbidden();
        }

        $this->get('/_test/mailing-navigation')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions.sales_mailings.view', false));
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_page_and_initial_data_do_not_depend_on_provider_being_enabled_or_configured(): void
    {
        $this->actingAs($this->userWithPermissions(['sales_mailings.view']));

        foreach ([false, true] as $enabled) {
            config([
                'services.unisender_go.enabled' => $enabled,
                'services.unisender_go.api_key' => '',
            ]);

            $this->get('/Ameise/commercial-offers')->assertOk();
            foreach (self::INITIAL_ENDPOINTS as $endpoint) {
                $this->getJson('/Ameise/commercial-offers/'.$endpoint)->assertOk();
            }
        }

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_blocked_admin_with_explicit_permissions_cannot_read_or_send(): void
    {
        $user = $this->userWithPermissions(self::PERMISSIONS, ['status' => 'blocked']);
        $user->assignRole(Role::findOrCreate('admin', 'crm'));

        $this->actingAs($user)->get('/Ameise/commercial-offers')->assertForbidden();
        foreach (self::INITIAL_ENDPOINTS as $endpoint) {
            $this->getJson('/Ameise/commercial-offers/'.$endpoint)->assertForbidden();
        }
        $this->postJson('/Ameise/commercial-offers/campaigns', ['name' => 'Blocked draft'])->assertForbidden();
        $this->postJson('/Ameise/commercial-offers/campaigns/1/send-test', ['email' => 'test@example.test'])->assertForbidden();
        $this->postJson('/Ameise/commercial-offers/campaigns/1/start')->assertForbidden();

        $this->get('/_test/mailing-navigation')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions.sales_mailings.view', false));
        $this->assertDatabaseCount('mailing_campaigns', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_unverified_admin_must_verify_email_before_accessing_page_or_services(): void
    {
        $user = $this->userWithPermissions(self::PERMISSIONS, ['email_verified_at' => null]);
        $user->assignRole(Role::findOrCreate('admin', 'crm'));

        $this->actingAs($user)->get('/Ameise/commercial-offers')->assertRedirect(route('verification.notice'));
        foreach (self::INITIAL_ENDPOINTS as $endpoint) {
            $this->getJson('/Ameise/commercial-offers/'.$endpoint)->assertForbidden();
        }
        $this->postJson('/Ameise/commercial-offers/campaigns/1/send-test', ['email' => 'test@example.test'])->assertForbidden();
        $this->postJson('/Ameise/commercial-offers/campaigns/1/start')->assertForbidden();

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_guests_must_authenticate_before_accessing_page_or_services(): void
    {
        $this->get('/Ameise/commercial-offers')->assertRedirect(route('login'));
        foreach (self::INITIAL_ENDPOINTS as $endpoint) {
            $this->getJson('/Ameise/commercial-offers/'.$endpoint)->assertUnauthorized();
        }
        $this->postJson('/Ameise/commercial-offers/campaigns/1/send-test', ['email' => 'test@example.test'])->assertUnauthorized();
        $this->postJson('/Ameise/commercial-offers/campaigns/1/start')->assertUnauthorized();

        $this->get('/_test/mailing-navigation')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions.sales_mailings.view', false));
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_view_only_user_cannot_modify_data_send_mail_or_change_provider_settings(): void
    {
        $campaign = MailingCampaign::query()->create([
            'name' => 'Existing offer',
            'subject' => 'Offer subject',
            'from_email' => 'sales@example.test',
            'from_name' => 'Sales',
            'status' => 'draft',
        ]);
        $this->actingAs($this->userWithPermissions(['sales_mailings.view']));

        $requests = [
            ['POST', '/campaigns', ['name' => 'Unauthorized offer']],
            ['PUT', '/campaigns/'.$campaign->id, ['name' => 'Unauthorized change']],
            ['POST', '/campaigns/'.$campaign->id.'/send-test', ['email' => 'test@example.test']],
            ['POST', '/campaigns/'.$campaign->id.'/approve', []],
            ['POST', '/campaigns/'.$campaign->id.'/start', []],
            ['POST', '/campaigns/'.$campaign->id.'/recipients', ['emails' => ['test@example.test']]],
            ['POST', '/contacts', ['email' => 'new@example.test']],
            ['POST', '/sets', ['name' => 'Unauthorized set']],
            ['POST', '/templates', ['name' => 'Unauthorized template']],
            ['POST', '/suppression', ['email' => 'blocked@example.test', 'cause' => 'manual']],
            ['POST', '/settings/set-webhook', []],
        ];

        foreach ($requests as [$method, $path, $payload]) {
            $this->json($method, '/Ameise/commercial-offers'.$path, $payload)->assertForbidden();
        }

        $this->assertDatabaseCount('mailing_campaigns', 1);
        $this->assertSame('Existing offer', $campaign->fresh()->name);
        $this->assertSame('draft', $campaign->fresh()->status);
        foreach (['mailing_campaign_recipients', 'mailing_contacts', 'mailing_contact_sets', 'mailing_templates', 'mailing_suppression_list'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_seeder_registers_all_mailing_permissions_for_admin_without_granting_manager_access(): void
    {
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(self::PERMISSIONS, Permission::query()
            ->where('guard_name', 'crm')->where('name', 'like', 'sales_mailings.%')->pluck('name')->all());
        $admin = Role::findByName('admin', 'crm');
        $manager = Role::findByName('manager', 'crm');
        foreach (self::PERMISSIONS as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission, 'crm'), $permission);
            $this->assertFalse($manager->hasPermissionTo($permission, 'crm'), $permission);
        }
    }

    public function test_permission_migration_is_additive_idempotent_and_preserves_existing_grants(): void
    {
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $unrelated = Permission::findOrCreate('existing.custom_permission', 'crm');
        $view = Permission::findOrCreate('sales_mailings.view', 'crm');
        $admin = Role::findOrCreate('admin', 'crm');
        $admin->givePermissionTo($unrelated);
        $manager = Role::findOrCreate('manager', 'crm');
        $manager->givePermissionTo([$unrelated, $view]);
        $otherGuardAdmin = Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($unrelated);

        $migration = require database_path('migrations/2026_09_11_130000_register_sales_mailing_permissions.php');
        $migration->up();

        $this->assertEqualsCanonicalizing([...self::PERMISSIONS, $unrelated->name], $admin->fresh()->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing([$unrelated->name, $view->name], $manager->fresh()->permissions->pluck('name')->all());
        $this->assertSame([$unrelated->id], $user->fresh()->permissions->pluck('id')->all());
        $this->assertCount(0, $otherGuardAdmin->fresh()->permissions);
        $this->assertSame($view->id, Permission::findByName('sales_mailings.view', 'crm')->id);

        $tables = ['permissions', 'roles', 'role_has_permissions', 'model_has_permissions', 'model_has_roles'];
        $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()->toArray()])->all();
        $migration->up();
        $migration->down();
        foreach ($before as $table => $rows) {
            $this->assertEqualsCanonicalizing($rows, DB::table($table)->get()->toArray(), $table);
        }
    }

    private function userWithPermissions(array $permissions, array $attributes = []): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'crm');
        }

        $user = User::factory()->create(['status' => 'active', ...$attributes]);
        $user->givePermissionTo($permissions);

        return $user;
    }
}
