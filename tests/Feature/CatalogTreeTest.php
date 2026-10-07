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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_only_active_staff_can_read_and_mutate_the_tree(): void
    {
        $this->getJson('/api/catalog')->assertUnauthorized();
        $this->postJson('/api/catalog/levels', ['name' => 'Вид'])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))
            ->getJson('/api/catalog')->assertForbidden();
        $this->staff();
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(4, 'levels');
    }

    public function test_import_preserves_drafts_unassigned_entries_and_multiple_product_links(): void
    {
        $category = Category::create(['name' => 'Черновая категория', 'is_published' => false]);
        $product = Product::create(['rus' => 'Первый', 'category_id' => $category->id, 'is_published' => false]);
        $other = Product::create(['rus' => 'Без категории', 'is_published' => true]);
        $good = Good::create(['name' => 'Общий товар', 'is_published' => false]);
        $good->products()->attach([$product->id, $other->id]);
        $unlinked = Good::create(['name' => 'Без связей', 'is_published' => true]);
        $this->staff();
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(6, 'nodes');
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(6, 'nodes');
        $this->assertDatabaseCount('catalog_nodes', 6);
        $this->assertDatabaseCount('good_product', 2);
        $this->assertSame(2, CatalogNode::where('entity_type', 'good')->where('entity_id', $good->id)->count());
        $this->assertFalse(CatalogNode::where('import_key', 'product:'.$product->id)->first()->is_published);
        $this->assertNull(CatalogNode::where('import_key', 'good:'.$unlinked->id.':root')->first()->parent_id);
    }

    public function test_linked_source_edits_are_visible_without_overwriting_source_data(): void
    {
        $category = Category::create(['name' => 'Старое', 'is_published' => true]);
        $this->staff();
        $this->getJson('/api/catalog')->assertOk();
        $category->update(['name' => 'Новое', 'is_published' => false, 'image' => '/storage/category.png']);
        $response = $this->getJson('/api/catalog')->assertOk();
        $this->assertSame('Новое', $response->json('nodes.0.name'));
        $this->assertFalse($response->json('nodes.0.is_published'));
        $this->assertSame('/storage/category.png', $response->json('nodes.0.image'));
    }

    public function test_custom_levels_fields_and_entries_support_complete_crud(): void
    {
        $this->staff();
        $levelId = $this->postJson('/api/catalog/levels', ['name' => 'Сорт'])->assertCreated()->json('data.id');
        $fieldId = $this->postJson('/api/catalog/fields', [
            'level_id' => $levelId, 'key' => 'origin', 'label' => 'Происхождение', 'type' => 'select',
            'required' => true, 'is_public' => true, 'options' => ['Россия', 'Китай'],
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/catalog/nodes', ['level_id' => $levelId, 'name' => 'Первый'])
            ->assertUnprocessable()->assertJsonValidationErrors('properties.origin');
        $this->postJson('/api/catalog/nodes', ['level_id' => $levelId, 'name' => 'Первый', 'properties' => ['origin' => 'Нет']])
            ->assertUnprocessable()->assertJsonValidationErrors('properties.origin');
        $nodeId = $this->postJson('/api/catalog/nodes', [
            'level_id' => $levelId, 'name' => 'Первый', 'is_published' => true, 'properties' => ['origin' => 'Россия'],
        ])->assertCreated()->json('data.id');
        $this->patchJson('/api/catalog/fields/'.$fieldId, ['key' => 'country', 'label' => 'Страна'])->assertOk();
        $this->assertSame(['country' => 'Россия'], CatalogNode::findOrFail($nodeId)->properties);
        $this->patchJson('/api/catalog/levels/'.$levelId, ['name' => 'Разновидность'])->assertOk()->assertJsonPath('data.name', 'Разновидность');
        $this->deleteJson('/api/catalog/levels/'.$levelId)->assertConflict();
        $this->deleteJson('/api/catalog/fields/'.$fieldId)->assertNoContent();
        $this->assertSame([], CatalogNode::findOrFail($nodeId)->properties);
        $this->deleteJson('/api/catalog/nodes/'.$nodeId)->assertNoContent();
        $this->deleteJson('/api/catalog/levels/'.$levelId)->assertNoContent();
    }

    public function test_property_definition_changes_are_transactional_and_private_by_default(): void
    {
        $this->staff();
        $level = CatalogLevel::create(['name' => 'Группа', 'entity_type' => 'custom']);
        $fieldId = $this->postJson('/api/catalog/fields', ['level_id' => $level->id, 'key' => 'code', 'label' => 'Код', 'type' => 'text'])
            ->assertCreated()->assertJsonPath('data.is_public', false)->json('data.id');
        $nodeId = $this->postJson('/api/catalog/nodes', ['level_id' => $level->id, 'name' => 'Элемент', 'properties' => ['code' => 'ABC']])
            ->assertCreated()->json('data.id');
        $this->patchJson('/api/catalog/fields/'.$fieldId, ['type' => 'number'])->assertUnprocessable();
        $this->assertSame('text', CatalogField::findOrFail($fieldId)->type);
        $this->assertSame(['code' => 'ABC'], CatalogNode::findOrFail($nodeId)->properties);
        $this->patchJson('/api/catalog/nodes/'.$nodeId, ['properties' => ['unknown' => 1]])->assertUnprocessable();
    }

    public function test_nested_moves_update_legacy_category_and_good_relations_without_losing_other_links(): void
    {
        $this->staff();
        $categoryLevel = CatalogLevel::where('entity_type', 'category')->firstOrFail();
        $productLevel = CatalogLevel::where('entity_type', 'product')->firstOrFail();
        $goodLevel = CatalogLevel::where('entity_type', 'good')->firstOrFail();
        $customLevel = CatalogLevel::create(['name' => 'Группа', 'entity_type' => 'custom']);
        $a = $this->createNode($categoryLevel, 'Категория А');
        $b = $this->createNode($categoryLevel, 'Категория Б');
        $branch = $this->createNode($customLevel, 'Группа', $a['id']);
        $product = $this->createNode($productLevel, 'Продукт', $branch['id']);
        $other = $this->createNode($productLevel, 'Другой продукт', $a['id']);
        $good = $this->createNode($goodLevel, 'Товар', $product['id']);
        $this->patchJson('/api/catalog/nodes/'.$branch['id'], ['parent_id' => $b['id']])->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product['entity_id'], 'category_id' => $b['entity_id']]);
        $this->assertDatabaseHas('good_product', ['good_id' => $good['entity_id'], 'product_id' => $product['entity_id']]);
        $this->patchJson('/api/catalog/nodes/'.$good['id'], ['parent_id' => $other['id']])->assertOk();
        $this->assertDatabaseMissing('good_product', ['good_id' => $good['entity_id'], 'product_id' => $product['entity_id']]);
        $this->assertDatabaseHas('good_product', ['good_id' => $good['entity_id'], 'product_id' => $other['entity_id']]);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(6, 'nodes');
    }

    public function test_tree_rejects_cycles_and_deleting_nonempty_branches(): void
    {
        $this->staff();
        $level = CatalogLevel::create(['name' => 'Уровень', 'entity_type' => 'custom']);
        $parent = $this->createNode($level, 'Родитель');
        $child = $this->createNode($level, 'Потомок', $parent['id']);
        $this->patchJson('/api/catalog/nodes/'.$parent['id'], ['parent_id' => $child['id']])->assertUnprocessable();
        $this->patchJson('/api/catalog/nodes/'.$parent['id'], ['parent_id' => $parent['id']])->assertUnprocessable();
        $this->deleteJson('/api/catalog/nodes/'.$parent['id'])->assertConflict();
        $this->assertNull(CatalogNode::findOrFail($parent['id'])->parent_id);
    }

    public function test_deleting_one_good_occurrence_only_unlinks_that_product(): void
    {
        $first = Product::create(['rus' => 'Первый']);
        $second = Product::create(['rus' => 'Второй']);
        $good = Good::create(['name' => 'Общий']);
        $good->products()->attach([$first->id, $second->id]);
        $this->staff();
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('import_key', 'good:'.$good->id.':product:'.$first->id)->firstOrFail();
        $this->deleteJson('/api/catalog/nodes/'.$node->id)->assertNoContent();
        $this->assertModelExists($good);
        $this->assertDatabaseMissing('good_product', ['good_id' => $good->id, 'product_id' => $first->id]);
        $this->assertDatabaseHas('good_product', ['good_id' => $good->id, 'product_id' => $second->id]);
        $this->getJson('/api/catalog')->assertOk();
        $this->assertSame(1, CatalogNode::where('entity_type', 'good')->count());
    }

    public function test_goods_with_customer_history_cannot_be_deleted(): void
    {
        $good = Good::create(['name' => 'С историей']);
        DB::table('good_stock_alerts')->insert(['good_id' => $good->id, 'start_token_hash' => str_repeat('a', 64)]);
        $this->staff();
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('entity_type', 'good')->firstOrFail();
        $this->deleteJson('/api/catalog/nodes/'.$node->id)->assertConflict();
        $this->assertModelExists($good);
        $this->assertModelExists($node);
    }

    public function test_linked_publication_avatar_and_seo_are_synchronized(): void
    {
        Storage::fake('public');
        $this->staff();
        $level = CatalogLevel::where('entity_type', 'category')->firstOrFail();
        $node = $this->createNode($level, 'Категория');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], [
            'name' => 'Новое имя', 'is_published' => true, 'is_featured' => true, 'meta_title' => 'SEO',
        ])->assertOk()->assertJsonPath('data.is_featured', true);
        $this->post('/api/catalog/nodes/'.$node['id'].'/image', [
            'image' => UploadedFile::fake()->image('avatar.png'),
        ], ['Accept' => 'application/json'])->assertOk();
        $category = Category::findOrFail($node['entity_id']);
        $this->assertTrue($category->is_published);
        $this->assertTrue($category->is_featured);
        $this->assertSame('SEO', $category->meta_title);
        $this->assertStringContainsString('catalog-images/', $category->image);
        $this->assertCount(1, Storage::disk('public')->allFiles('catalog-images'));
    }

    public function test_required_properties_do_not_block_publication_only_patch(): void
    {
        $this->staff();
        $level = CatalogLevel::create(['name' => 'Уровень', 'entity_type' => 'custom']);
        $node = $this->createNode($level, 'Элемент');
        CatalogField::create(['level_id' => $level->id, 'key' => 'required_value', 'label' => 'Обязательно', 'type' => 'text', 'required' => true]);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['is_published' => true])->assertOk();
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['properties' => []])->assertUnprocessable();
    }

    public function test_external_legacy_relation_changes_reconcile_placements_and_preserve_custom_children(): void
    {
        $this->staff();
        $good = Good::create(['name' => 'Товар']);
        $first = Product::create(['rus' => 'Первый']);
        $second = Product::create(['rus' => 'Второй']);
        $this->getJson('/api/catalog')->assertOk();
        $original = CatalogNode::where('entity_type', 'good')->firstOrFail();
        $level = CatalogLevel::create(['name' => 'Свой раздел', 'entity_type' => 'custom']);
        $custom = $this->createNode($level, 'Дочерний раздел', $original->id);
        $good->products()->sync([$first->id]);
        $this->getJson('/api/catalog')->assertOk();
        $this->assertSame(1, CatalogNode::where('entity_type', 'good')->count());
        $this->assertSame('good:'.$good->id.':product:'.$first->id, $original->fresh()->import_key);
        $this->assertSame($original->id, CatalogNode::findOrFail($custom['id'])->parent_id);
        $good->products()->sync([$second->id]);
        $this->getJson('/api/catalog')->assertOk();
        $this->assertSame(1, CatalogNode::where('entity_type', 'good')->count());
        $this->assertSame('good:'.$good->id.':product:'.$second->id, $original->fresh()->import_key);
        $good->products()->detach();
        $this->getJson('/api/catalog')->assertOk();
        $this->assertNull($original->fresh()->parent_id);
        $this->assertSame($original->id, CatalogNode::findOrFail($custom['id'])->parent_id);
    }

    public function test_deleted_sources_cannot_be_edited_and_import_preserves_custom_descendants(): void
    {
        $this->staff();
        $category = Category::create(['name' => 'Категория']);
        $product = Product::create(['rus' => 'Продукт', 'category_id' => $category->id]);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('entity_type', 'product')->firstOrFail();
        $level = CatalogLevel::create(['name' => 'Свой раздел', 'entity_type' => 'custom']);
        $custom = $this->createNode($level, 'Раздел', $node->id);
        $category->delete();
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['name' => 'Новое имя'])->assertConflict();
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(1, 'nodes');
        $this->assertNull(CatalogNode::findOrFail($custom['id'])->parent_id);
    }

    public function test_good_editor_keeps_full_resolution_avatar_on_unrelated_save(): void
    {
        $good = Good::create(['name' => 'Товар', 'ava_image' => '/storage/full.png', 'ava_thumb' => '/storage/thumb.png']);
        $this->staff();
        $payload = $this->getJson('/api/catalog')->assertOk()->json('nodes.0');
        $this->assertSame('/storage/full.png', $payload['image']);
        $this->assertStringContainsString('/catalog/', $payload['public_url']);
        $this->assertStringContainsString('/g/', $payload['offer_url']);
        $this->patchJson('/api/catalog/nodes/'.$payload['id'], ['name' => 'Новое имя', 'image' => $payload['image']])->assertOk();
        $this->assertSame('/storage/full.png', $good->fresh()->ava_image);
        $this->assertSame('/storage/thumb.png', $good->fresh()->ava_thumb);
    }

    public function test_migration_backfills_existing_legacy_rows_and_preserves_links(): void
    {
        $migration = require database_path('migrations/2026_10_07_150000_create_catalog_tree_tables.php');
        $migration->down();
        $category = Category::create(['name' => 'Черновик', 'is_published' => false]);
        $first = Product::create(['rus' => 'Первый', 'category_id' => $category->id, 'is_published' => false]);
        $second = Product::create(['rus' => 'Второй', 'is_published' => true]);
        $good = Good::create(['name' => 'Общий', 'is_published' => false]);
        $good->products()->attach([$first->id, $second->id]);
        $unlinked = Good::create(['name' => 'Без продукта', 'is_published' => true]);
        $migration->up();
        $this->assertDatabaseCount('catalog_levels', 3);
        $this->assertDatabaseCount('catalog_nodes', 6);
        $this->assertDatabaseCount('good_product', 2);
        $this->assertFalse(CatalogNode::where('import_key', 'category:'.$category->id)->firstOrFail()->is_published);
        $this->assertSame(2, CatalogNode::where('entity_type', 'good')->where('entity_id', $good->id)->count());
        $this->assertNull(CatalogNode::where('import_key', 'good:'.$unlinked->id.':root')->firstOrFail()->parent_id);
        $this->assertSame(CatalogNode::where('import_key', 'category:'.$category->id)->value('id'), CatalogNode::where('import_key', 'product:'.$first->id)->value('parent_id'));
    }

    public function test_manually_moved_good_root_survives_import_while_other_product_link_remains(): void
    {
        $this->staff();
        $first = Product::create(['rus' => 'Первый']);
        $second = Product::create(['rus' => 'Второй']);
        $good = Good::create(['name' => 'Общий']);
        $good->products()->attach([$first->id, $second->id]);
        $this->getJson('/api/catalog')->assertOk();
        $placement = CatalogNode::where('import_key', 'good:'.$good->id.':product:'.$first->id)->firstOrFail();
        $level = CatalogLevel::create(['name' => 'Раздел', 'entity_type' => 'custom']);
        $folder = $this->createNode($level, 'Самостоятельная группа');
        $child = $this->createNode($level, 'Сохранённый подраздел', $placement->id);
        $this->patchJson('/api/catalog/nodes/'.$placement->id, ['parent_id' => $folder['id']])->assertOk();
        $this->getJson('/api/catalog')->assertOk();
        $this->getJson('/api/catalog')->assertOk();
        $this->assertSame($folder['id'], $placement->fresh()->parent_id);
        $this->assertTrue($placement->fresh()->is_manual);
        $this->assertSame($placement->id, CatalogNode::findOrFail($child['id'])->parent_id);
        $this->assertSame(2, CatalogNode::where('entity_type', 'good')->where('entity_id', $good->id)->count());
        $this->assertDatabaseMissing('good_product', ['good_id' => $good->id, 'product_id' => $first->id]);
        $this->assertDatabaseHas('good_product', ['good_id' => $good->id, 'product_id' => $second->id]);
    }

    public function test_good_marketing_launch_history_is_protected_from_deletion(): void
    {
        $this->staff();
        $good = Good::create(['name' => 'Рекламируемый товар']);
        $sessionId = DB::table('direct_launch_sessions')->insertGetId(['good_id' => $good->id, 'status' => 'completed', 'external_ids' => json_encode(['ad_id' => '123'])]);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('entity_type', 'good')->firstOrFail();
        $this->deleteJson('/api/catalog/nodes/'.$node->id)->assertConflict();
        $this->assertModelExists($good);
        $this->assertDatabaseHas('direct_launch_sessions', ['id' => $sessionId, 'good_id' => $good->id]);
    }

    public function test_unused_builtin_entities_can_be_deleted_from_leaves_to_root(): void
    {
        $this->staff();
        $category = $this->createNode(CatalogLevel::where('entity_type', 'category')->firstOrFail(), 'Категория');
        $product = $this->createNode(CatalogLevel::where('entity_type', 'product')->firstOrFail(), 'Продукт', $category['id']);
        $good = $this->createNode(CatalogLevel::where('entity_type', 'good')->firstOrFail(), 'Товар', $product['id']);
        $this->deleteJson('/api/catalog/nodes/'.$good['id'])->assertNoContent();
        $this->deleteJson('/api/catalog/nodes/'.$product['id'])->assertNoContent();
        $this->deleteJson('/api/catalog/nodes/'.$category['id'])->assertNoContent();
        $this->assertDatabaseCount('goods', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'nodes');
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    private function createNode(CatalogLevel $level, string $name, ?int $parentId = null): array
    {
        return $this->postJson('/api/catalog/nodes', ['level_id' => $level->id, 'name' => $name, 'parent_id' => $parentId])
            ->assertCreated()->json('data');
    }
}
