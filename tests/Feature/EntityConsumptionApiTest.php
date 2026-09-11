<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Measure;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EntityConsumptionApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Http::preventStrayRequests();
        Mail::fake();

        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('INN')->nullable();
            $table->text('dadata_raw')->nullable();
            $table->timestamps();
        });
        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('city_entity', function (Blueprint $table): void {
            $table->unsignedBigInteger('city_id');
            $table->unsignedBigInteger('entity_id');
        });
        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('city_id')->nullable();
        });
        Schema::create('building_entities', function (Blueprint $table): void {
            $table->unsignedBigInteger('building_id');
            $table->unsignedBigInteger('entity_id');
        });
        Schema::create('telephones', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
        });
        Schema::create('entity_telephone', function (Blueprint $table): void {
            $table->unsignedBigInteger('telephone_id');
            $table->unsignedBigInteger('entity_id');
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('rus');
            $table->string('eng')->nullable();
            $table->string('zh')->nullable();
            $table->timestamps();
        });
        Schema::create('measures', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('consumptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('product_id');
            $table->double('quantity');
            $table->timestamps();
        });

        $this->migration()->up();
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        Mail::assertNothingSent();
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_crud_supports_unknown_volume_and_updates_the_current_need(): void
    {
        $this->signIn();
        [$entity, $product, $measure] = $this->fixtures();
        $base = "/api/entities/{$entity->id}/consumptions";

        $created = $this->postJson($base, ['product_id' => $product->id])
            ->assertCreated()
            ->assertJsonPath('data.entity_id', $entity->id)
            ->assertJsonPath('data.status', 'potential')
            ->assertJsonPath('data.quantity', null)
            ->assertJsonPath('data.measure', null)
            ->assertJsonPath('data.product.rus', 'Сахар')
            ->json('data');

        $this->getJson($base)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $created['id']);
        $url = $base.'/'.$created['id'];
        $this->getJson($url)->assertOk()->assertJsonPath('data.product.eng', 'Sugar');

        $this->patchJson($url, [
            'quantity' => '12.125', 'measure_id' => $measure->id,
            'status' => 'confirmed', 'comment' => 'Уточнить объём к следующей закупке',
        ])->assertOk()
            ->assertJsonPath('data.quantity', '12.125')
            ->assertJsonPath('data.measure.name', 'кг')
            ->assertJsonPath('data.status', 'confirmed');

        $otherProduct = Product::query()->create(['rus' => 'Соль']);
        $this->putJson($url, [
            'product_id' => $otherProduct->id, 'quantity' => 8,
            'measure_id' => $measure->id, 'status' => 'closed', 'comment' => null,
        ])->assertOk()
            ->assertJsonPath('data.product.id', $otherProduct->id)
            ->assertJsonPath('data.quantity', '8.000')
            ->assertJsonPath('data.comment', null);

        $this->patchJson($url, ['quantity' => null, 'measure_id' => null])
            ->assertOk()->assertJsonPath('data.quantity', null)->assertJsonPath('data.measure', null);
        $this->deleteJson($url)->assertNoContent();
        $this->getJson($url)->assertNotFound();
        $this->getJson($base)->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('entity_consumptions', 0);
        $this->assertDatabaseCount('consumptions', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_create_input_is_rejected(array $overrides, array $errorFields): void
    {
        $this->signIn();
        [$entity, $product] = $this->fixtures();

        $this->postJson("/api/entities/{$entity->id}/consumptions", array_replace([
            'product_id' => $product->id,
        ], $overrides))->assertUnprocessable()->assertJsonValidationErrors($errorFields);

        $this->assertDatabaseCount('entity_consumptions', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing product' => [['product_id' => null], ['product_id']],
            'unknown product' => [['product_id' => 999], ['product_id']],
            'negative quantity' => [['quantity' => -1], ['quantity']],
            'zero quantity' => [['quantity' => 0], ['quantity']],
            'excess precision' => [['quantity' => '1.0001'], ['quantity']],
            'overflow' => [['quantity' => '1000000000000'], ['quantity']],
            'missing measure' => [['quantity' => '1.250'], ['measure_id']],
            'unknown measure' => [['measure_id' => 999], ['measure_id']],
            'invalid status' => [['status' => 'in_progress'], ['status']],
            'empty status' => [['status' => null], ['status']],
            'long comment' => [['comment' => str_repeat('a', 2001)], ['comment']],
            'forged entity' => [['entity_id' => 999], ['entity_id']],
        ];
    }

    public function test_partial_updates_cannot_leave_a_known_quantity_without_its_measure(): void
    {
        $this->signIn();
        [$entity, $product, $measure] = $this->fixtures();
        $need = $entity->consumptions()->create([
            'product_id' => $product->id, 'quantity' => '2.500', 'measure_id' => $measure->id,
        ]);
        $url = "/api/entities/{$entity->id}/consumptions/{$need->id}";

        $this->patchJson($url, ['measure_id' => null])->assertUnprocessable()->assertJsonValidationErrors('measure_id');
        $this->assertSame($measure->id, $need->refresh()->measure_id);
        $this->patchJson($url, ['comment' => 'Объём прежний'])->assertOk()->assertJsonPath('data.quantity', '2.500');
        $this->patchJson($url, ['quantity' => 3])->assertOk()->assertJsonPath('data.quantity', '3.000');
        $this->patchJson($url, ['quantity' => null, 'measure_id' => null])->assertOk();
        $this->patchJson($url, ['quantity' => 3])->assertUnprocessable()->assertJsonValidationErrors('measure_id');
    }

    public function test_product_is_unique_per_entity_and_can_still_be_registered_for_another_entity(): void
    {
        $this->signIn();
        [$entity, $product] = $this->fixtures();
        $otherEntity = Entity::query()->create(['name' => 'Другой клиент']);
        $otherProduct = Product::query()->create(['rus' => 'Соль']);
        $base = "/api/entities/{$entity->id}/consumptions";

        $firstId = $this->postJson($base, ['product_id' => $product->id])->assertCreated()->json('data.id');
        $this->postJson($base, ['product_id' => $product->id])->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->postJson("/api/entities/{$otherEntity->id}/consumptions", ['product_id' => $product->id])->assertCreated();
        $secondId = $this->postJson($base, ['product_id' => $otherProduct->id])->assertCreated()->json('data.id');

        $this->patchJson($base.'/'.$firstId, ['product_id' => $product->id])->assertOk();
        $this->patchJson($base.'/'.$secondId, ['product_id' => $product->id])->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->assertDatabaseCount('entity_consumptions', 3);

        $this->expectException(UniqueConstraintViolationException::class);
        $entity->consumptions()->create(['product_id' => $product->id]);
    }

    public function test_nested_routes_do_not_read_modify_or_delete_another_entity_need(): void
    {
        $this->signIn();
        [$entity, $product] = $this->fixtures();
        $otherEntity = Entity::query()->create(['name' => 'Другой клиент']);
        $need = $entity->consumptions()->create(['product_id' => $product->id]);
        $url = "/api/entities/{$otherEntity->id}/consumptions/{$need->id}";

        $this->getJson($url)->assertNotFound();
        $this->patchJson($url, ['status' => 'closed'])->assertNotFound();
        $this->putJson($url, ['product_id' => $product->id])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->getJson("/api/entities/{$otherEntity->id}/consumptions")->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame('potential', $need->refresh()->status);

        $this->patchJson("/api/entities/{$entity->id}/consumptions/{$need->id}", ['entity_id' => $otherEntity->id])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_id');
        $this->assertSame($entity->id, $need->refresh()->entity_id);
    }

    public function test_missing_parents_and_unknown_need_ids_return_not_found(): void
    {
        $this->signIn();
        [$entity, $product] = $this->fixtures();

        $this->getJson('/api/entities/999/consumptions')->assertNotFound();
        $this->getJson('/api/entities/999/consumptions/meta')->assertNotFound();
        $this->postJson('/api/entities/999/consumptions', ['product_id' => $product->id])->assertNotFound();
        $this->getJson("/api/entities/{$entity->id}/consumptions/999")->assertNotFound();
        $this->getJson('/api/products/999/entity-consumptions')->assertNotFound();
        $this->assertDatabaseCount('entity_consumptions', 0);
    }

    public function test_all_routes_require_a_verified_authenticated_user(): void
    {
        [$entity, $product] = $this->fixtures();
        $need = $entity->consumptions()->create(['product_id' => $product->id]);
        $base = "/api/entities/{$entity->id}/consumptions";
        $routes = [
            ['get', $base], ['get', $base.'/meta'], ['post', $base],
            ['get', $base.'/'.$need->id], ['patch', $base.'/'.$need->id],
            ['put', $base.'/'.$need->id], ['delete', $base.'/'.$need->id],
            ['get', "/api/products/{$product->id}/entity-consumptions"],
        ];

        foreach ($routes as [$method, $url]) {
            $this->json($method, $url)->assertUnauthorized();
        }
        $this->signIn(verified: false);
        foreach ($routes as [$method, $url]) {
            $this->json($method, $url)->assertForbidden();
        }

        $this->assertSame('potential', $need->refresh()->status);
        $this->assertDatabaseCount('entity_consumptions', 1);
    }

    public function test_metadata_and_product_listing_use_small_explicit_relations(): void
    {
        $this->signIn();
        [$entity, $product, $measure] = $this->fixtures();
        $otherProduct = Product::query()->create(['rus' => 'Соль']);
        $entity->update(['dadata_raw' => ['private' => 'Raw entity data']]);
        $otherEntity = Entity::query()->create(['name' => 'Другой клиент']);
        $emptyEntity = Entity::query()->create(['name' => 'Без контактов']);
        $emptyEntity->consumptions()->create(['product_id' => $product->id]);
        $otherEntity->consumptions()->create(['product_id' => $product->id]);
        DB::table('cities')->insert([
            ['id' => 1, 'name' => 'Москва'],
            ['id' => 2, 'name' => 'Казань'],
            ['id' => 3, 'name' => 'Омск'],
        ]);
        DB::table('city_entity')->insert([
            ['entity_id' => $entity->id, 'city_id' => 1],
            ['entity_id' => $otherEntity->id, 'city_id' => 3],
        ]);
        DB::table('buildings')->insert([
            ['id' => 1, 'city_id' => 1],
            ['id' => 2, 'city_id' => 2],
            ['id' => 3, 'city_id' => null],
        ]);
        DB::table('building_entities')->insert([
            ['entity_id' => $entity->id, 'building_id' => 1],
            ['entity_id' => $entity->id, 'building_id' => 2],
            ['entity_id' => $emptyEntity->id, 'building_id' => 3],
        ]);
        DB::table('telephones')->insert([
            ['id' => 1, 'number' => '+7 (999) 222-33-44'],
            ['id' => 2, 'number' => '+7 (495) 123-45-67'],
            ['id' => 3, 'number' => '+7 (381) 765-43-21'],
        ]);
        DB::table('entity_telephone')->insert([
            ['entity_id' => $entity->id, 'telephone_id' => 1],
            ['entity_id' => $entity->id, 'telephone_id' => 2],
            ['entity_id' => $otherEntity->id, 'telephone_id' => 3],
        ]);
        $need = $entity->consumptions()->create([
            'product_id' => $product->id, 'quantity' => 5, 'measure_id' => $measure->id,
        ]);
        $entity->consumptions()->create(['product_id' => $otherProduct->id]);
        DB::table('consumptions')->insert(['unit_id' => 42, 'product_id' => $product->id, 'quantity' => 17]);

        $this->getJson("/api/entities/{$entity->id}/consumptions/meta")->assertOk()
            ->assertJsonCount(2, 'products')->assertJsonCount(1, 'measures')
            ->assertJsonPath('measures.0.name', 'кг')
            ->assertJsonPath('statuses.0.value', 'potential')
            ->assertJsonCount(3, 'statuses');

        $response = $this->getJson("/api/products/{$product->id}/entity-consumptions")->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('data.0.id', $need->id)
            ->assertJsonPath('data.0.entity.name', $entity->name)
            ->assertJsonPath('data.0.entity.INN', '7700000000')
            ->assertJsonPath('data.0.quantity', '5.000');
        $this->assertSame(['id', 'rus', 'eng'], array_keys($response->json('data.0.product')));
        $this->assertSame([
            'id' => $entity->id,
            'name' => $entity->name,
            'INN' => '7700000000',
            'cities' => [['id' => 2, 'name' => 'Казань'], ['id' => 1, 'name' => 'Москва']],
            'telephones' => [
                ['id' => 2, 'number' => '+7 (495) 123-45-67'],
                ['id' => 1, 'number' => '+7 (999) 222-33-44'],
            ],
        ], $response->json('data.0.entity'));
        $this->assertSame([
            'id' => $otherEntity->id,
            'name' => $otherEntity->name,
            'INN' => null,
            'cities' => [['id' => 3, 'name' => 'Омск']],
            'telephones' => [['id' => 3, 'number' => '+7 (381) 765-43-21']],
        ], $response->json('data.1.entity'));
        $this->assertSame([
            'id' => $emptyEntity->id,
            'name' => $emptyEntity->name,
            'INN' => null,
            'cities' => [],
            'telephones' => [],
        ], $response->json('data.2.entity'));
        $this->assertSame(['id', 'name'], array_keys($response->json('data.0.measure')));
        $this->assertDatabaseCount('consumptions', 1);
    }

    public function test_entity_and_product_expose_demand_relations_without_replacing_unit_consumption(): void
    {
        [$entity, $product, $measure] = $this->fixtures();
        $need = $entity->consumptions()->create([
            'product_id' => $product->id, 'quantity' => '1.125', 'measure_id' => $measure->id,
        ]);

        $this->assertTrue($need->entity()->withoutEagerLoads()->first()->is($entity));
        $this->assertTrue($need->product()->withoutEagerLoads()->first()->is($product));
        $this->assertTrue($need->measure->is($measure));
        $this->assertTrue($product->entityConsumptions()->first()->is($need));
        $this->assertTrue($entity->consumedProducts()->withoutEagerLoads()->first()->is($product));
        $this->assertSame('potential', $product->consumingEntities()->withoutEagerLoads()->first()->pivot->status);
        $this->assertSame(0, $product->consumers()->count());
    }

    public function test_entity_and_product_deletion_cascade_only_their_demands(): void
    {
        [$entity, $product] = $this->fixtures();
        $otherEntity = Entity::query()->create(['name' => 'Другой клиент']);
        $otherProduct = Product::query()->create(['rus' => 'Соль']);
        $entity->consumptions()->create(['product_id' => $product->id]);
        $otherEntity->consumptions()->create(['product_id' => $product->id]);
        $otherEntity->consumptions()->create(['product_id' => $otherProduct->id]);

        $entity->delete();
        $this->assertDatabaseCount('entity_consumptions', 2);
        $product->delete();
        $this->assertDatabaseCount('entity_consumptions', 1);
        $this->assertDatabaseHas('entity_consumptions', ['entity_id' => $otherEntity->id, 'product_id' => $otherProduct->id]);
    }

    public function test_measure_in_use_cannot_be_deleted_and_leave_an_unlabelled_quantity(): void
    {
        [$entity, $product, $measure] = $this->fixtures();
        $entity->consumptions()->create(['product_id' => $product->id, 'quantity' => 3, 'measure_id' => $measure->id]);

        $this->expectException(QueryException::class);
        $measure->delete();
    }

    public function test_migration_rolls_back_without_removing_existing_unit_consumptions(): void
    {
        DB::table('consumptions')->insert(['unit_id' => 42, 'product_id' => 17, 'quantity' => 100]);

        $this->migration()->down();
        $this->assertFalse(Schema::hasTable('entity_consumptions'));
        $this->assertDatabaseCount('consumptions', 1);
        $this->migration()->up();
        $this->assertTrue(Schema::hasTable('entity_consumptions'));
    }

    private function signIn(bool $verified = true): void
    {
        $user = (new User)->forceFill([
            'id' => 1, 'name' => 'Менеджер', 'email' => 'manager@example.test',
            'email_verified_at' => $verified ? now() : null,
        ]);
        $this->actingAs($user);
    }

    private function fixtures(): array
    {
        return [
            Entity::query()->create(['name' => 'ООО Пищепром', 'INN' => '7700000000']),
            Product::query()->create(['rus' => 'Сахар', 'eng' => 'Sugar', 'zh' => '糖']),
            Measure::query()->create(['name' => 'кг']),
        ];
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_11_120000_create_entity_consumptions_table.php');
    }
}
