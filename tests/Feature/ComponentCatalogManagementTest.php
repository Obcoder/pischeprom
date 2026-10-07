<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AuthenticatesStaff;
use Tests\TestCase;

class ComponentCatalogManagementTest extends TestCase
{
    use AuthenticatesStaff;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Http::preventStrayRequests();

        Schema::create('components', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('rus');
            $table->string('eng')->nullable();
            $table->timestamps();
        });
        Schema::create('component_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function test_staff_can_create_read_update_and_delete_components_without_deleting_products(): void
    {
        $this->actingAsStaff();
        $created = $this->postJson('/api/components', ['name' => 'Сахар', 'id' => 99999])
            ->assertCreated()->assertJsonPath('name', 'Сахар')->assertJsonPath('products_count', 0);
        $id = $created->json('id');
        $this->assertNotSame(99999, $id);

        $productId = DB::table('products')->insertGetId(['rus' => 'Варенье']);
        Component::findOrFail($id)->products()->attach($productId);

        $this->getJson('/api/components')->assertOk()
            ->assertJsonPath('0.id', $id)->assertJsonPath('0.products_count', 1);
        $this->getJson("/api/components/{$id}")->assertOk()
            ->assertJsonPath('products.0.rus', 'Варенье')
            ->assertJsonMissingPath('products.0.manufacturers');
        $this->patchJson("/api/components/{$id}", ['name' => 'Сахар-песок'])
            ->assertOk()->assertJsonPath('name', 'Сахар-песок')->assertJsonPath('products_count', 1);
        $this->assertDatabaseHas('component_product', ['component_id' => $id, 'product_id' => $productId]);

        $this->deleteJson("/api/components/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('components', ['id' => $id]);
        $this->assertDatabaseCount('component_product', 0);
        $this->assertDatabaseHas('products', ['id' => $productId, 'rus' => 'Варенье']);
        $this->getJson("/api/components/{$id}")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_invalid_names_are_rejected_and_existing_values_are_preserved(): void
    {
        $this->actingAsStaff();
        $component = Component::create(['name' => 'Соль']);

        foreach (['', '  ', null, ['invalid'], str_repeat('а', 256)] as $name) {
            $this->postJson('/api/components', ['name' => $name])
                ->assertUnprocessable()->assertJsonValidationErrors('name');
            $this->patchJson("/api/components/{$component->id}", ['name' => $name])
                ->assertUnprocessable()->assertJsonValidationErrors('name');
        }

        $this->assertDatabaseCount('components', 1);
        $this->assertSame('Соль', $component->fresh()->name);
    }

    public function test_guests_and_inactive_employees_cannot_read_or_mutate_components(): void
    {
        $component = Component::create(['name' => 'Соль']);
        $requests = [
            ['GET', '/api/components', []],
            ['GET', "/api/components/{$component->id}", []],
            ['POST', '/api/components', ['name' => 'Сахар']],
            ['PATCH', "/api/components/{$component->id}", ['name' => 'Сахар']],
            ['DELETE', "/api/components/{$component->id}", []],
        ];

        foreach ($requests as [$method, $url, $payload]) {
            $this->json($method, $url, $payload)->assertUnauthorized();
        }

        $this->actingAs((new User)->forceFill(['id' => 2, 'type' => 'employee', 'status' => 'blocked']));
        foreach ($requests as [$method, $url, $payload]) {
            $this->json($method, $url, $payload)->assertForbidden();
        }

        $this->assertDatabaseCount('components', 1);
        $this->assertSame('Соль', $component->fresh()->name);
    }
}
