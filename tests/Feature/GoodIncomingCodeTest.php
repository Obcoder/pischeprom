<?php

namespace Tests\Feature;

use App\Models\Good;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodIncomingCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_incoming_code_preserves_supplier_format_through_crud_and_export(): void
    {
        $code = '00012-АБ / 09.24';
        $id = $this->postJson(route('goods.store'), ['name' => 'Товар', 'incoming_code' => $code])
            ->assertCreated()->assertJsonPath('incoming_code', $code)->json('id');

        $this->getJson(route('goods.index', ['view' => 'table']))->assertOk()->assertJsonPath('data.0.incoming_code', $code);
        $this->getJson(route('good.fetch', ['id' => $id]))->assertOk()->assertJsonPath('incoming_code', $code);
        $export = json_decode(Storage::disk('yandex')->get("goods/{$id}/good.json"), true);
        $this->assertSame($code, $export['incoming_code']);

        $this->patchJson(route('goods.update', $id), ['name' => 'Новое название'])
            ->assertOk()->assertJsonPath('incoming_code', $code);
        $this->patchJson(route('goods.update', $id), ['incoming_code' => '0000001'])
            ->assertOk()->assertJsonPath('incoming_code', '0000001');
        $this->patchJson(route('goods.update', $id), ['incoming_code' => ''])
            ->assertOk()->assertJsonPath('incoming_code', null);
        $this->assertDatabaseHas('goods', ['id' => $id, 'incoming_code' => null]);
    }

    public function test_catalog_card_creates_reads_updates_and_clears_the_same_incoming_code(): void
    {
        $node = $this->postJson('/api/catalog/nodes', [
            'name' => 'Товар', 'entity_type' => 'good', 'good' => ['incoming_code' => '001-ЗАВОД / A'],
        ])->assertCreated()->json('data');
        $uri = '/api/catalog/nodes/'.$node['id'];
        $this->getJson($uri.'/overview')->assertOk()->assertJsonPath('data.incoming_code', '001-ЗАВОД / A');
        $this->patchJson($uri, ['good' => ['incoming_code' => '00002-B']])->assertOk();
        $this->patchJson($uri, ['name' => 'Переименованный товар'])->assertOk();
        $this->getJson($uri.'/overview')->assertOk()->assertJsonPath('data.incoming_code', '00002-B');
        $this->assertSame('00002-B', Good::findOrFail($node['entity_id'])->incoming_code);
        $this->patchJson($uri, ['good' => ['incoming_code' => null]])->assertOk();
        $this->getJson($uri.'/overview')->assertOk()->assertJsonPath('data.incoming_code', null);
    }

    public function test_incoming_code_rejects_non_strings_and_long_values_without_partial_writes(): void
    {
        $node = $this->postJson('/api/catalog/nodes', [
            'name' => 'Исходный', 'entity_type' => 'good', 'good' => ['incoming_code' => '0001'],
        ])->assertCreated()->json('data');
        foreach ([123, ['code'], str_repeat('а', 256)] as $invalid) {
            $this->postJson(route('goods.store'), ['name' => 'Не сохранять', 'incoming_code' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('incoming_code');
            $this->patchJson(route('goods.update', $node['entity_id']), ['name' => 'Не сохранять', 'incoming_code' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('incoming_code');
            $this->patchJson('/api/catalog/nodes/'.$node['id'], ['name' => 'Не сохранять', 'good' => ['incoming_code' => $invalid]])
                ->assertUnprocessable()->assertJsonValidationErrors('good.incoming_code');
        }
        $this->assertDatabaseCount('goods', 1);
        $this->assertDatabaseHas('goods', ['id' => $node['entity_id'], 'name' => 'Исходный', 'incoming_code' => '0001']);
    }
}
