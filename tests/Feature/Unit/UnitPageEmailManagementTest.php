<?php

namespace Tests\Feature\Unit;

use App\Models\Email;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UnitPageEmailManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('yandex');
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        Mail::assertNothingSent();

        parent::tearDown();
    }

    public function test_create_email_normalizes_and_atomically_attaches_it_to_route_bound_unit(): void
    {
        $actor = $this->actor(['ai_sales.view', 'ai_sales.contexts.manage']);
        $unit = $this->unit('Contact owner');
        $otherUnit = $this->unit('Other Unit');

        $this->actingAs($actor)
            ->postJson("/api/units/{$unit->id}/emails", [
                'address' => '  BUYING@EXAMPLE.TEST ',
                'name' => 'Отдел закупок',
            ])
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('attached', true)
            ->assertJsonPath('data.address', 'buying@example.test')
            ->assertJsonMissingPath('data.units');

        $email = Email::query()->where('address', 'buying@example.test')->firstOrFail();

        $this->assertSame('unit_manual', $email->source);
        $this->assertDatabaseHas('email_unit', [
            'unit_id' => $unit->id,
            'email_id' => $email->id,
        ]);
        $this->assertDatabaseMissing('email_unit', [
            'unit_id' => $otherUnit->id,
            'email_id' => $email->id,
        ]);
        $this->assertDatabaseCount('entities', 0);
    }

    public function test_existing_email_is_reused_and_repeated_attach_is_idempotent(): void
    {
        $actor = $this->actor(['ai_sales.view', 'ai_sales.contexts.manage']);
        $unit = $this->unit();
        $email = Email::query()->create([
            'address' => 'office@example.test',
            'name' => 'Офис',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->postJson("/api/units/{$unit->id}/emails", ['email_id' => $email->id])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('attached', true);

        $this->postJson("/api/units/{$unit->id}/emails", ['email_id' => $email->id])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('attached', false);

        $this->postJson("/api/units/{$unit->id}/emails", [
            'address' => 'office@example.test',
            'name' => 'Не перезаписывать',
        ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('attached', false);

        $this->assertSame(1, Email::query()->where('address', 'office@example.test')->count());
        $this->assertSame(1, $unit->emails()->whereKey($email->id)->count());
        $this->assertSame('Офис', $email->fresh()->name);
    }

    public function test_soft_deleted_email_is_restored_instead_of_duplicated(): void
    {
        $actor = $this->actor(['ai_sales.view', 'ai_sales.contexts.manage']);
        $unit = $this->unit();
        $email = Email::query()->create([
            'address' => 'restored@example.test',
            'source' => 'manual',
            'is_active' => false,
        ]);
        $email->delete();

        $this->actingAs($actor)
            ->postJson("/api/units/{$unit->id}/emails", [
                'address' => 'restored@example.test',
                'name' => 'Восстановленный контакт',
            ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('attached', true);

        $this->assertDatabaseCount('emails', 1);
        $this->assertNull($email->fresh()->deleted_at);
        $this->assertTrue($email->fresh()->is_active);
        $this->assertSame('Восстановленный контакт', $email->fresh()->name);
    }

    public function test_route_requires_verified_authorized_contact_manager(): void
    {
        $unit = $this->unit();
        $payload = ['address' => 'secure@example.test'];

        $this->postJson("/api/units/{$unit->id}/emails", $payload)->assertUnauthorized();

        $unverified = $this->actor(
            ['ai_sales.view', 'ai_sales.contexts.manage'],
            verified: false,
        );
        $this->actingAs($unverified)
            ->postJson("/api/units/{$unit->id}/emails", $payload)
            ->assertForbidden();

        $viewer = $this->actor(['ai_sales.view']);
        $this->actingAs($viewer)
            ->postJson("/api/units/{$unit->id}/emails", $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('emails', 0);
        $this->assertDatabaseCount('email_unit', 0);
    }

    public function test_request_rejects_invalid_or_ambiguous_email_selection(): void
    {
        $actor = $this->actor(['ai_sales.view', 'ai_sales.contexts.manage']);
        $unit = $this->unit();
        $email = Email::query()->create([
            'address' => 'existing@example.test',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->postJson("/api/units/{$unit->id}/emails", ['address' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address');

        $this->postJson("/api/units/{$unit->id}/emails", [
            'email_id' => $email->id,
            'address' => 'other@example.test',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email_id', 'address']);

        $this->assertDatabaseCount('email_unit', 0);
        $this->assertDatabaseCount('entities', 0);
    }

    public function test_unit_page_exposes_compact_tabs_and_authorized_email_capabilities_without_mutation(): void
    {
        $actor = $this->actor([
            'ai_sales.view',
            'ai_sales.contexts.manage',
            'mail.send',
        ]);
        $unit = $this->unit();
        $email = Email::query()->create([
            'address' => 'page@example.test',
            'source' => 'manual',
            'is_active' => true,
        ]);
        $unit->emails()->attach($email->id);

        $emailCount = Email::query()->count();
        $pivotCount = $unit->emails()->count();

        $this->actingAs($actor)
            ->get("/Ameise/unit/{$unit->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Unit')
                ->where('unit.id', $unit->id)
                ->where('permissions.unit.manage_emails', true)
                ->where('permissions.unit.send_mail', true));

        $this->assertSame($emailCount, Email::query()->count());
        $this->assertSame($pivotCount, $unit->emails()->count());
        $this->assertDatabaseCount('entities', 0);
    }

    public function test_route_registry_and_frontend_keep_email_management_explicit(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->uri() === 'api/units/{unit}/emails'
                && in_array('POST', $candidate->methods(), true));

        $this->assertNotNull($route);
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('verified', $route->gatherMiddleware());
        $this->assertContains('can:manageContacts,unit', $route->gatherMiddleware());

        $page = (string) file_get_contents(resource_path('js/Pages/Ameise/Unit.vue'));
        $overview = (string) file_get_contents(resource_path('js/Components/Unit/UnitOverviewCard.vue'));
        $emailCard = (string) file_get_contents(resource_path('js/Components/Unit/Mail/UnitEmailContactsCard.vue'));

        foreach (['Обзор', 'Торговля', 'Коммуникации', 'AI Sales'] as $tab) {
            $this->assertStringContainsString($tab, $page);
        }

        foreach (['Написать письмо', 'Привязать из базы', 'Создать и привязать'] as $action) {
            $this->assertStringContainsString($action, $emailCard);
        }

        $this->assertStringNotContainsString('mdi-dots-vertical', $overview);
        $this->assertStringNotContainsString("route('emailgood.store')", $overview);
        $this->assertStringNotContainsString("route('web.email.store')", $overview);
    }

    private function unit(string $name = 'Unit with contacts'): Unit
    {
        return Unit::query()->create([
            'name' => $name,
            'is_customer' => true,
            'is_supplier' => false,
        ]);
    }

    private function actor(array $permissions, bool $verified = true): User
    {
        $actor = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => $verified ? now() : null,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'crm',
            ]);
        }

        if ($permissions !== []) {
            $actor->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $actor;
    }
}
