<?php

namespace Tests\Feature;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\User;
use App\Services\Catalog\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogNodeHeadingTest extends TestCase
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
        Http::preventStrayRequests();
        config()->set('services.indexnow.key', null);
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_non_good_entities_can_set_h1_at_every_classification_level_or_without_a_level(): void
    {
        CatalogLevel::create(['name' => 'Класс', 'entity_type' => 'custom']);
        $levels = [...CatalogLevel::pluck('id')->all(), null];

        foreach (['custom', 'category', 'product'] as $type) {
            foreach ($levels as $levelId) {
                $name = 'Запись '.$type.' '.($levelId ?? 'без уровня');
                $heading = 'SEO '.$name;
                $node = $this->postJson('/api/catalog/nodes', [
                    'name' => $name, 'entity_type' => $type, 'level_id' => $levelId, 'h1' => '  '.$heading.'  ',
                ])->assertCreated()->assertJsonPath('data.h1', $heading)->json('data');

                $this->assertDatabaseHas('catalog_nodes', ['id' => $node['id'], 'h1' => $heading, 'name' => $name]);
                if ($type === 'category') {
                    $this->assertDatabaseHas('categories', ['id' => $node['entity_id'], 'h1' => $heading, 'name' => $name]);
                }
            }
        }
    }

    public function test_h1_updates_do_not_rename_records_or_change_urls_and_survive_reclassification(): void
    {
        $level = CatalogLevel::create(['name' => 'Класс', 'entity_type' => 'custom']);
        foreach (['custom', 'category', 'product'] as $type) {
            $node = $this->postJson('/api/catalog/nodes', [
                'name' => 'Скумбрия '.$type, 'slug' => 'mackerel-'.$type, 'entity_type' => $type,
                'level_id' => $level->id, 'h1' => 'Первоначальный H1', 'meta_title' => 'SEO Title',
            ])->assertCreated()->json('data');

            $this->patchJson('/api/catalog/nodes/'.$node['id'], ['h1' => '  Скумбрия оптом  '])
                ->assertOk()->assertJsonPath('data.h1', 'Скумбрия оптом')
                ->assertJsonPath('data.name', $node['name'])->assertJsonPath('data.slug', $node['slug'])
                ->assertJsonPath('data.public_url', $node['public_url'])->assertJsonPath('data.meta_title', 'SEO Title');
            $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => null])
                ->assertOk()->assertJsonPath('data.h1', 'Скумбрия оптом')->assertJsonPath('data.level_id', null);

            $snapshot = collect($this->getJson('/api/catalog')->assertOk()->json('nodes'))->keyBy('id');
            $this->assertSame('Скумбрия оптом', $snapshot[$node['id']]['h1']);
        }
    }

    public function test_omitting_h1_preserves_it_and_explicit_null_or_blank_clears_it(): void
    {
        foreach (['custom', 'category', 'product'] as $type) {
            $node = $this->postJson('/api/catalog/nodes', [
                'name' => 'Запись '.$type, 'entity_type' => $type, 'h1' => 'Сохранённый H1',
            ])->assertCreated()->json('data');
            $this->patchJson('/api/catalog/nodes/'.$node['id'], ['description' => 'Новое описание'])
                ->assertOk()->assertJsonPath('data.h1', 'Сохранённый H1');

            foreach ([null, '  '] as $emptyHeading) {
                $this->patchJson('/api/catalog/nodes/'.$node['id'], ['h1' => 'Сохранённый H1'])->assertOk();
                $this->patchJson('/api/catalog/nodes/'.$node['id'], ['h1' => $emptyHeading])
                    ->assertOk()->assertJsonPath('data.h1', null);
                $this->assertNull(CatalogNode::findOrFail($node['id'])->h1);
                if ($type === 'category') {
                    $this->assertNull(Category::findOrFail($node['entity_id'])->h1);
                }
            }
        }
    }

    public function test_legacy_category_h1_is_authoritative_in_every_placement_and_both_editors(): void
    {
        $category = Category::create(['name' => 'Рыба', 'slug' => 'fish', 'h1' => 'Сохранённый заголовок категории']);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('import_key', 'category:'.$category->id)->firstOrFail();
        $node->update(['h1' => 'Устаревшая копия']);
        $duplicate = CatalogNode::create([
            'name' => $category->name, 'entity_type' => 'category', 'entity_id' => $category->id,
            'h1' => 'Другая устаревшая копия', 'level_id' => null,
        ]);
        foreach ([$node, $duplicate] as $placement) {
            $this->assertSame('Сохранённый заголовок категории', app(CatalogService::class)->nodePayload($placement)['h1']);
        }

        $this->patchJson('/api/catalog/nodes/'.$node->id, ['h1' => 'Рыба оптом'])
            ->assertOk()->assertJsonPath('data.h1', 'Рыба оптом');
        $this->getJson('/api/categories/'.$category->id)->assertOk()->assertJsonPath('data.h1', 'Рыба оптом');
        $this->assertSame('Рыба оптом', app(CatalogService::class)->nodePayload($duplicate)['h1']);
        $this->assertSame('Рыба', $category->fresh()->name);
        $this->assertSame('fish', $category->fresh()->slug);

        $this->patchJson('/api/categories/'.$category->id, ['name' => 'Рыба', 'h1' => 'Правка старого редактора'])
            ->assertOk()->assertJsonPath('data.h1', 'Правка старого редактора');
        $snapshot = collect($this->getJson('/api/catalog')->assertOk()->json('nodes'))->keyBy('id');
        foreach ([$node, $duplicate] as $placement) {
            $this->assertSame('Правка старого редактора', $snapshot[$placement->id]['h1']);
        }

        $this->patchJson('/api/categories/'.$category->id, ['name' => 'Рыба', 'h1' => null])->assertOk();
        foreach ([$node, $duplicate] as $placement) {
            $this->assertNull(app(CatalogService::class)->nodePayload($placement)['h1']);
        }
    }

    public function test_old_category_editor_preserves_omitted_h1_and_accepts_explicit_clearing(): void
    {
        $category = Category::create(['name' => 'Рыба', 'h1' => 'Редакторский заголовок']);
        $this->patchJson('/api/categories/'.$category->id, ['name' => 'Морская рыба'])
            ->assertOk()->assertJsonPath('data.h1', 'Редакторский заголовок');
        foreach ([null, '  '] as $emptyHeading) {
            $category->update(['h1' => 'Редакторский заголовок']);
            $this->patchJson('/api/categories/'.$category->id, ['name' => 'Морская рыба', 'h1' => $emptyHeading])
                ->assertOk()->assertJsonPath('data.h1', null);
        }

        $this->postJson('/api/categories', ['name' => 'Категория без явного H1'])
            ->assertCreated()->assertJsonPath('data.h1', 'Категория без явного H1');
        $this->postJson('/api/categories', ['name' => 'Категория с пустым H1', 'h1' => null])
            ->assertCreated()->assertJsonPath('data.h1', null);
    }

    public function test_good_h1_remains_owned_by_good_seo_regardless_of_classification(): void
    {
        $good = Good::create(['name' => 'Скумбрия 400–600']);
        $seo = GoodSeo::create(['good_id' => $good->id, 'h1' => 'Заголовок товара', 'is_active' => true]);
        $node = CatalogNode::create([
            'name' => $good->name, 'entity_type' => 'good', 'entity_id' => $good->id, 'h1' => 'Старая копия',
        ]);
        $this->assertNull(app(CatalogService::class)->nodePayload($node)['h1']);
        $level = CatalogLevel::create(['name' => 'Класс', 'entity_type' => 'custom']);
        foreach ([null, $level->id] as $levelId) {
            $this->patchJson('/api/catalog/nodes/'.$node->id, ['level_id' => $levelId, 'h1' => 'Не заменять H1 товара'])
                ->assertOk()->assertJsonPath('data.h1', null);
            $this->assertNull($node->fresh()->h1);
            $this->assertSame('Заголовок товара', $seo->fresh()->h1);
        }

        $created = $this->postJson('/api/catalog/nodes', [
            'name' => 'Новый товар', 'entity_type' => 'good', 'h1' => 'Не создавать SEO из общей карточки',
        ])->assertCreated()->assertJsonPath('data.h1', null)->json('data');
        $this->assertDatabaseMissing('good_seos', ['good_id' => $created['entity_id']]);
        Http::assertNothingSent();
    }

    public function test_h1_accepts_up_to_255_characters_and_rejects_invalid_values_without_overwriting_it(): void
    {
        $node = CatalogNode::create(['name' => 'Скумбрия', 'h1' => 'Исходный H1']);
        foreach ([str_repeat('я', 256), ['nested' => 'heading']] as $invalidHeading) {
            $this->patchJson('/api/catalog/nodes/'.$node->id, ['h1' => $invalidHeading])
                ->assertUnprocessable()->assertJsonValidationErrors('h1');
            $this->assertSame('Исходный H1', $node->fresh()->h1);
            $this->postJson('/api/catalog/nodes', ['name' => 'Не создавать', 'h1' => $invalidHeading])
                ->assertUnprocessable()->assertJsonValidationErrors('h1');
        }
        $heading = str_repeat('я', 255);
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['h1' => $heading])
            ->assertOk()->assertJsonPath('data.h1', $heading);
    }

    public function test_migration_preserves_existing_category_and_good_headings_without_copying_over_them(): void
    {
        $category = Category::create(['name' => 'Рыба', 'h1' => 'Имеющийся H1 категории']);
        $good = Good::create(['name' => 'Скумбрия 400–600']);
        $seo = GoodSeo::create(['good_id' => $good->id, 'h1' => 'Имеющийся H1 товара']);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('import_key', 'category:'.$category->id)->firstOrFail();
        $before = $node->getAttributes();
        $migration = require database_path('migrations/2026_10_10_130000_add_h1_to_catalog_nodes_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame('Имеющийся H1 категории', $category->fresh()->h1);
        $this->assertSame('Имеющийся H1 товара', $seo->fresh()->h1);
        $this->assertSame($before, $node->fresh()->getAttributes());
        $this->assertSame('Имеющийся H1 категории', app(CatalogService::class)->nodePayload($node->fresh())['h1']);
    }
}
