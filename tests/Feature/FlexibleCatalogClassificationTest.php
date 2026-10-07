<?php

namespace Tests\Feature;

use App\Models\CatalogField;
use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FlexibleCatalogClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_any_source_can_be_reclassified_without_changing_business_identity_or_relations(): void
    {
        $category = Category::create(['name' => 'Категория', 'is_published' => true, 'meta_title' => 'SEO категории']);
        $product = Product::create(['rus' => 'Продукт', 'category_id' => $category->id]);
        $good = Good::create(['name' => 'Товар', 'description' => 'Описание товара']);
        $good->products()->attach($product->id);
        $this->staff();
        $this->getJson('/api/catalog')->assertOk();
        $level = CatalogLevel::where('entity_type', 'good')->firstOrFail();
        foreach (['category' => $category, 'product' => $product, 'good' => $good] as $type => $source) {
            $node = CatalogNode::where('entity_type', $type)->firstOrFail();
            $this->patchJson('/api/catalog/nodes/'.$node->id, ['level_id' => $level->id])
                ->assertOk()->assertJsonPath('data.level_id', $level->id)
                ->assertJsonPath('data.entity_type', $type)->assertJsonPath('data.entity_id', $source->id);
            $this->patchJson('/api/catalog/nodes/'.$node->id, ['level_id' => null])
                ->assertOk()->assertJsonPath('data.level_id', null)
                ->assertJsonPath('data.entity_type', $type)->assertJsonPath('data.entity_id', $source->id);
        }
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(3, 'nodes');
        $this->assertSame(3, CatalogNode::whereNull('level_id')->count());
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertSame('SEO категории', $category->fresh()->meta_title);
        $this->assertSame('Описание товара', $good->fresh()->description);
        $this->assertDatabaseHas('good_product', ['good_id' => $good->id, 'product_id' => $product->id]);
    }

    public function test_creation_has_independent_optional_level_and_source_type(): void
    {
        $this->staff();
        $level = CatalogLevel::where('entity_type', 'category')->firstOrFail();
        $this->postJson('/api/catalog/nodes', ['name' => 'Свободный объект'])
            ->assertCreated()->assertJsonPath('data.level_id', null)->assertJsonPath('data.entity_type', null);
        $this->postJson('/api/catalog/nodes', ['name' => 'Свободный товар', 'entity_type' => 'good', 'level_id' => null])
            ->assertCreated()->assertJsonPath('data.level_id', null)->assertJsonPath('data.entity_type', 'good');
        $classified = $this->postJson('/api/catalog/nodes', ['name' => 'Товар на уровне категории', 'entity_type' => 'good', 'level_id' => $level->id])
            ->assertCreated()->assertJsonPath('data.level_id', $level->id)->assertJsonPath('data.entity_type', 'good')->json('data');
        $this->postJson('/api/catalog/nodes', ['name' => 'Произвольная группа', 'entity_type' => 'custom', 'level_id' => $level->id])
            ->assertCreated()->assertJsonPath('data.entity_type', null);
        $this->postJson('/api/catalog/nodes', ['name' => 'Прежний формат создания', 'level_id' => $level->id])
            ->assertCreated()->assertJsonPath('data.entity_type', 'category');
        $this->patchJson('/api/catalog/nodes/'.$classified['id'], ['entity_type' => 'product'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
        $this->assertDatabaseCount('goods', 2);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_domains_are_optional_and_intermediate_levels_can_be_skipped(): void
    {
        $this->staff();
        $domainLevel = CatalogLevel::where('is_domain', true)->firstOrFail();
        $goodLevel = CatalogLevel::where('entity_type', 'good')->firstOrFail();
        $domain = $this->postJson('/api/catalog/nodes', ['level_id' => $domainLevel->id, 'name' => 'Пищевая продукция'])
            ->assertCreated()->json('data');
        $this->postJson('/api/catalog/nodes', ['level_id' => $goodLevel->id, 'name' => 'Товар сразу в домене', 'parent_id' => $domain['id']])
            ->assertCreated()->assertJsonPath('data.parent_id', $domain['id']);
        $this->postJson('/api/catalog/nodes', ['level_id' => $goodLevel->id, 'name' => 'Товар без домена'])
            ->assertCreated()->assertJsonPath('data.parent_id', null);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(3, 'nodes');
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('goods', 2);
    }

    public function test_properties_are_archived_and_restored_when_levels_change_or_are_removed(): void
    {
        $this->staff();
        $first = CatalogLevel::create(['name' => 'Сорт', 'entity_type' => 'custom']);
        $second = CatalogLevel::create(['name' => 'Размер', 'entity_type' => 'custom']);
        CatalogField::create(['level_id' => $first->id, 'key' => 'quality', 'label' => 'Качество', 'type' => 'text']);
        CatalogField::create(['level_id' => $second->id, 'key' => 'size', 'label' => 'Размер', 'type' => 'number']);
        $node = $this->postJson('/api/catalog/nodes', [
            'name' => 'Продукция', 'level_id' => $first->id, 'properties' => ['quality' => 'Высшее'],
        ])->assertCreated()->json('data');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => $second->id, 'properties' => ['size' => 25]])
            ->assertOk()->assertJsonPath('data.properties.size', 25)
            ->assertJsonPath('data.properties_by_level.'.$first->id.'.quality', 'Высшее');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => null])->assertOk()->assertJsonPath('data.level_id', null);
        $this->assertSame([], CatalogNode::findOrFail($node['id'])->properties);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => $first->id])
            ->assertOk()->assertJsonPath('data.properties.quality', 'Высшее');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => $second->id])
            ->assertOk()->assertJsonPath('data.properties.size', 25);
        $this->deleteJson('/api/catalog/levels/'.$first->id)->assertNoContent();
        $this->assertSame(['quality' => 'Высшее'], CatalogNode::findOrFail($node['id'])->properties_by_level[$first->id]);
    }

    public function test_field_changes_apply_to_archived_properties_without_touching_another_level(): void
    {
        $this->staff();
        $level = CatalogLevel::create(['name' => 'Сорт', 'entity_type' => 'custom']);
        $field = CatalogField::create(['level_id' => $level->id, 'key' => 'code', 'label' => 'Код', 'type' => 'text']);
        $node = $this->postJson('/api/catalog/nodes', [
            'name' => 'Продукция', 'level_id' => $level->id, 'properties' => ['code' => 'ABC'],
        ])->assertCreated()->json('data');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => null])->assertOk();
        $this->patchJson('/api/catalog/fields/'.$field->id, ['key' => 'designation'])->assertOk();
        $this->patchJson('/api/catalog/fields/'.$field->id, ['type' => 'number'])->assertUnprocessable();
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => $level->id])
            ->assertOk()->assertJsonPath('data.properties.designation', 'ABC');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => null])->assertOk();
        $this->deleteJson('/api/catalog/fields/'.$field->id)->assertNoContent();
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => $level->id])->assertOk();
        $this->assertSame([], CatalogNode::findOrFail($node['id'])->properties);
    }

    public function test_deleted_builtin_levels_are_not_recreated_and_new_sources_still_import(): void
    {
        $this->staff();
        foreach (CatalogLevel::all() as $level) {
            $this->deleteJson('/api/catalog/levels/'.$level->id)->assertNoContent();
        }
        $category = Category::create(['name' => 'Категория']);
        $product = Product::create(['rus' => 'Продукт', 'category_id' => $category->id]);
        $good = Good::create(['name' => 'Товар']);
        $good->products()->attach($product->id);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'levels')->assertJsonCount(3, 'nodes');
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'levels')->assertJsonCount(3, 'nodes');
        $this->assertSame(3, CatalogNode::whereNull('level_id')->count());
        $this->assertSame(CatalogNode::where('entity_type', 'category')->value('id'), CatalogNode::where('entity_type', 'product')->value('parent_id'));
        $this->assertSame(CatalogNode::where('entity_type', 'product')->value('id'), CatalogNode::where('entity_type', 'good')->value('parent_id'));
    }

    public function test_builtin_level_can_be_deleted_after_reassigning_its_sources(): void
    {
        $this->staff();
        $category = Category::create(['name' => 'Категория']);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('entity_type', 'category')->firstOrFail();
        $this->deleteJson('/api/catalog/levels/'.$node->level_id)->assertConflict();
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['level_id' => null])->assertOk();
        $this->deleteJson('/api/catalog/levels/'.$node->level_id)->assertNoContent();
        Category::create(['name' => 'Новая категория']);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(2, 'nodes');
        $this->assertModelExists($category);
        $this->assertDatabaseMissing('catalog_levels', ['entity_type' => 'category']);
        $this->assertSame(2, CatalogNode::whereNull('level_id')->count());
    }

    public function test_level_display_preferences_persist_and_list_branches_keep_their_children(): void
    {
        $this->staff();
        $level = $this->postJson('/api/catalog/levels', ['name' => 'Объекты', 'display_mode' => 'list', 'is_domain' => false])
            ->assertCreated()->assertJsonPath('data.display_mode', 'list')->assertJsonPath('data.is_domain', false)->json('data');
        $parent = $this->postJson('/api/catalog/nodes', ['name' => 'Группа', 'level_id' => $level['id']])->assertCreated()->json('data');
        $this->postJson('/api/catalog/nodes', ['name' => 'Дочерний объект', 'level_id' => null, 'parent_id' => $parent['id']])->assertCreated();
        $this->patchJson('/api/catalog/levels/'.$level['id'], ['display_mode' => 'tabs'])->assertOk()->assertJsonPath('data.display_mode', 'tabs');
        $this->patchJson('/api/catalog/levels/'.$level['id'], ['display_mode' => 'tree'])->assertOk()->assertJsonPath('data.display_mode', 'tree');
        $this->patchJson('/api/catalog/levels/'.$level['id'], ['is_domain' => true])->assertOk()->assertJsonPath('data.display_mode', 'tabs');
        $this->patchJson('/api/catalog/levels/'.$level['id'], ['display_mode' => 'tree'])->assertOk()->assertJsonPath('data.is_domain', true);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(2, 'nodes');
        $this->assertSame($parent['id'], CatalogNode::where('name', 'Дочерний объект')->value('parent_id'));
        $this->patchJson('/api/catalog/levels/'.$level['id'], ['display_mode' => 'invalid'])->assertUnprocessable();
    }

    public function test_domain_nodes_require_root_placement_but_can_be_reclassified_as_children(): void
    {
        $this->staff();
        $domainLevel = CatalogLevel::where('is_domain', true)->firstOrFail();
        $root = $this->postJson('/api/catalog/nodes', ['name' => 'Корень'])->assertCreated()->json('data');
        $child = $this->postJson('/api/catalog/nodes', ['name' => 'Подраздел', 'parent_id' => $root['id']])->assertCreated()->json('data');
        $this->postJson('/api/catalog/nodes', ['name' => 'Вложенный домен', 'level_id' => $domainLevel->id, 'parent_id' => $root['id']])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->patchJson('/api/catalog/nodes/'.$child['id'], ['level_id' => $domainLevel->id])->assertUnprocessable();
        $this->patchJson('/api/catalog/nodes/'.$child['id'], ['level_id' => $domainLevel->id, 'parent_id' => null])
            ->assertOk()->assertJsonPath('data.parent_id', null);
        $this->patchJson('/api/catalog/nodes/'.$child['id'], ['parent_id' => $root['id']])->assertUnprocessable();
        $this->patchJson('/api/catalog/nodes/'.$child['id'], ['level_id' => null, 'parent_id' => $root['id']])->assertOk();
        $level = CatalogLevel::create(['name' => 'Подраздел', 'entity_type' => 'custom']);
        $this->patchJson('/api/catalog/nodes/'.$child['id'], ['level_id' => $level->id])->assertOk();
        $this->patchJson('/api/catalog/levels/'.$level->id, ['is_domain' => true])->assertUnprocessable()->assertJsonValidationErrors('is_domain');
    }

    public function test_classification_migration_preserves_existing_rows_and_adopts_existing_domain_level(): void
    {
        $migration = require database_path('migrations/2026_10_07_170000_make_catalog_classification_optional.php');
        $migration->down();
        CatalogLevel::where('name', 'Домены')->delete();
        $domain = CatalogLevel::create(['name' => 'Домен', 'entity_type' => 'custom']);
        $category = Category::create(['name' => 'Наследованная категория']);
        $node = CatalogNode::create([
            'name' => $category->name, 'entity_type' => 'category', 'entity_id' => $category->id,
            'level_id' => CatalogLevel::where('entity_type', 'category')->value('id'), 'properties' => ['existing' => 'Данные'],
        ]);
        $before = $node->fresh()->getAttributes();
        $migration->up();
        $this->assertSame($before, array_intersect_key($node->fresh()->getAttributes(), $before));
        $this->assertTrue($domain->fresh()->is_domain);
        $this->assertSame('tabs', $domain->fresh()->display_mode);
        $this->assertDatabaseCount('catalog_levels', 4);
        $this->assertDatabaseCount('catalog_nodes', 1);
        $this->assertSame('tabs', CatalogLevel::where('entity_type', 'category')->value('display_mode'));
        $this->assertSame('tree', CatalogLevel::where('entity_type', 'product')->value('display_mode'));
        $this->assertSame('list', CatalogLevel::where('entity_type', 'good')->value('display_mode'));
        $node->update(['level_id' => null]);
        $this->assertNull($node->fresh()->level_id);
    }

    public function test_classification_migration_keeps_legacy_nested_domain_named_levels_unchanged(): void
    {
        $migration = require database_path('migrations/2026_10_07_170000_make_catalog_classification_optional.php');
        $migration->down();
        CatalogLevel::where('name', 'Домены')->delete();
        $legacyLevel = CatalogLevel::create(['name' => 'Домен', 'entity_type' => 'custom']);
        $root = CatalogNode::create([
            'name' => 'Существующий раздел', 'level_id' => CatalogLevel::where('entity_type', 'category')->value('id'),
        ]);
        $nested = CatalogNode::create(['name' => 'Существующий подвид', 'level_id' => $legacyLevel->id, 'parent_id' => $root->id]);

        // RefreshDatabase wraps this test in a transaction, where SQLite cannot
        // disable FKs for Laravel's table rebuild as it does during migrations.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $migration->up();
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        DB::statement('PRAGMA defer_foreign_keys = OFF');

        $this->assertSame($root->id, $nested->fresh()->parent_id);
        $this->assertSame($legacyLevel->id, $nested->fresh()->level_id);
        $this->assertSame('Домен', $legacyLevel->fresh()->name);
        $this->assertFalse($legacyLevel->fresh()->is_domain);
        $domainLevel = CatalogLevel::where('is_domain', true)->sole();
        $this->assertSame('Домены', $domainLevel->name);
        $this->assertSame(0, $domainLevel->nodes()->count());
        $this->assertDatabaseCount('catalog_nodes', 2);
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }
}
