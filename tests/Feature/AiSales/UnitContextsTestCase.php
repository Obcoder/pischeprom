<?php

namespace Tests\Feature\AiSales;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class UnitContextsTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $allowExpectedHttpRequests = false;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('cache.default', 'array');
    }

    protected function tearDown(): void
    {
        if (! $this->allowExpectedHttpRequests) {
            Http::assertNothingSent();
        }

        parent::tearDown();
    }

    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'crm',
            ]);
        }

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function manager(array $extra = []): User
    {
        return $this->userWith(array_values(array_unique([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.procurement.view',
            'ai_sales.unit_roles.manage',
            'ai_sales.contexts.manage',
            'ai_sales.aliases.manage',
            'ai_sales.observation.manage',
            'ai_sales.observation.verify',
            'ai_sales.observation.promote',
            'ai_sales.entity.propose',
            'ai_sales.classifications.view_internal',
            'ai_sales.audit.view',
            ...$extra,
        ])));
    }

    protected function unit(array $attributes = []): Unit
    {
        return Unit::query()->create([
            'name' => 'Тестовое дело '.uniqid(),
            'is_customer' => false,
            'is_supplier' => false,
            ...$attributes,
        ]);
    }

    protected function createContext(User $actor, Unit $unit, array $attributes): array
    {
        return $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/contexts", [
                'stage' => 'new',
                'status' => 'active',
                'source' => 'test',
                ...$attributes,
            ])
            ->assertSuccessful()
            ->json('data');
    }
}
