<?php

namespace Tests\Feature;

use App\Models\CatalogLanding;
use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogEntityConversionTest extends TestCase
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
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_product_type_can_change_without_changing_node_identity_level_or_catalog_fields(): void
    {
        $node = $this->createNode('product', null, [
            'level_id' => CatalogLevel::where('entity_type', 'product')->value('id'),
            'slug' => 'forel', 'image' => '/storage/forel.jpg', 'description' => 'Описание',
            'h1' => 'Форель оптом', 'meta_title' => 'SEO форели', 'meta_description' => 'SEO описание',
            'is_published' => true, 'is_featured' => true, 'sort_order' => 7,
        ]);
        $originalId = $node['entity_id'];
        Product::findOrFail($originalId)->update(['rus' => 'Свежая форель']);
        $response = $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'category'])
            ->assertOk()->assertJsonPath('data.id', $node['id'])->assertJsonPath('data.entity_type', 'category')
            ->assertJsonPath('data.name', 'Свежая форель')->assertJsonPath('data.level_id', $node['level_id']);
        foreach (['slug', 'image', 'description', 'h1', 'meta_title', 'meta_description', 'is_published', 'is_featured', 'sort_order'] as $field) {
            $response->assertJsonPath('data.'.$field, $node[$field]);
        }
        $this->assertDatabaseMissing('products', ['id' => $originalId]);
        $this->assertDatabaseHas('categories', ['id' => $response->json('data.entity_id'), 'name' => 'Свежая форель', 'image' => '/storage/forel.jpg', 'meta_title' => 'SEO форели']);
        foreach ([1, 2] as $iteration) {
            $this->getJson('/api/catalog')->assertOk()->assertJsonCount(1, 'nodes')->assertJsonPath('nodes.0.id', $node['id']);
        }
    }

    public function test_category_source_fields_are_copied_when_the_imported_node_has_no_copy(): void
    {
        $category = Category::create([
            'name' => 'Категория', 'slug' => 'category-keep', 'image' => '/storage/category.jpg', 'description' => 'Текст',
            'h1' => 'Заголовок', 'meta_title' => 'SEO', 'meta_description' => 'Описание SEO', 'is_published' => true,
        ]);
        $node = $this->getJson('/api/catalog')->assertOk()->json('nodes.0');
        $this->assertNull(CatalogNode::findOrFail($node['id'])->image);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'product'])->assertOk()
            ->assertJsonPath('data.slug', 'category-keep')->assertJsonPath('data.image', '/storage/category.jpg')
            ->assertJsonPath('data.h1', 'Заголовок')->assertJsonPath('data.meta_title', 'SEO')
            ->assertJsonPath('data.meta_description', 'Описание SEO')->assertJsonPath('data.description', 'Текст');
        $this->assertModelMissing($category);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(1, 'nodes');
    }

    public function test_product_with_goods_can_become_a_category_and_reassign_tree_links(): void
    {
        $outer = $this->createNode('product');
        $node = $this->createNode('product', $outer['id']);
        $branch = $this->createNode('custom', $node['id']);
        $good = $this->createNode('good', $branch['id']);

        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'category'])->assertOk();
        $this->assertDatabaseMissing('good_product', ['product_id' => $node['entity_id']]);
        $this->assertDatabaseHas('good_product', ['good_id' => $good['entity_id'], 'product_id' => $outer['entity_id']]);
        $this->assertDatabaseHas('catalog_nodes', ['id' => $good['id'], 'parent_id' => $branch['id']]);
        foreach ([1, 2] as $iteration) {
            $this->getJson('/api/catalog')->assertOk()->assertJsonCount(4, 'nodes');
            $this->assertDatabaseHas('catalog_nodes', ['id' => $good['id'], 'parent_id' => $branch['id']]);
        }
    }

    public function test_category_with_products_can_become_custom_and_use_the_next_ancestor_category(): void
    {
        $outer = $this->createNode('category');
        $node = $this->createNode('category', $outer['id']);
        $product = $this->createNode('product', $node['id']);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'custom'])->assertOk()
            ->assertJsonPath('data.entity_type', null)->assertJsonPath('data.entity_id', null);
        $this->assertDatabaseHas('products', ['id' => $product['entity_id'], 'category_id' => $outer['entity_id']]);
        $this->assertNull(CatalogNode::findOrFail($node['id'])->import_key);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(3, 'nodes');
        $this->assertDatabaseHas('catalog_nodes', ['id' => $product['id'], 'parent_id' => $node['id']]);
    }

    public function test_conversion_to_and_from_good_preserves_image_and_canonical_seo(): void
    {
        $node = $this->createNode('custom', null, [
            'slug' => 'good-keep', 'image' => '/storage/good.jpg', 'description' => 'Описание товара',
            'h1' => 'Заголовок товара', 'meta_title' => 'Название в поиске', 'meta_description' => 'Описание в поиске',
        ]);
        $goodId = $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'good'])
            ->assertOk()->assertJsonPath('data.image', '/storage/good.jpg')->assertJsonPath('data.h1', null)
            ->assertJsonPath('data.meta_title', 'Название в поиске')->json('data.entity_id');
        $this->assertDatabaseHas('goods', ['id' => $goodId, 'slug' => 'good-keep', 'ava_image' => '/storage/good.jpg', 'description' => 'Описание товара']);
        $this->assertDatabaseHas('good_seos', ['good_id' => $goodId, 'h1' => 'Заголовок товара', 'meta_title' => 'Название в поиске']);
        $this->assertDatabaseHas('catalog_nodes', ['id' => $node['id'], 'meta_title' => null, 'h1' => null]);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'custom'])
            ->assertOk()->assertJsonPath('data.entity_id', null)->assertJsonPath('data.slug', 'good-keep')
            ->assertJsonPath('data.h1', 'Заголовок товара')->assertJsonPath('data.meta_title', 'Название в поиске')
            ->assertJsonPath('data.meta_description', 'Описание в поиске')->assertJsonPath('data.image', '/storage/good.jpg');
        $this->assertDatabaseMissing('goods', ['id' => $goodId]);
        $this->assertDatabaseMissing('good_seos', ['good_id' => $goodId]);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(1, 'nodes');
    }

    public function test_linked_references_roll_back_the_entire_conversion_including_descendant_relations(): void
    {
        $node = $this->createNode('product');
        $good = $this->createNode('good', $node['id']);
        DB::table('home_banners')->insert(['title' => 'Ссылка', 'product_id' => $node['entity_id']]);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'category', 'name' => 'Не сохранять'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseHas('catalog_nodes', ['id' => $node['id'], 'name' => $node['name'], 'entity_type' => 'product', 'entity_id' => $node['entity_id']]);
        $this->assertDatabaseHas('products', ['id' => $node['entity_id'], 'rus' => $node['name']]);
        $this->assertDatabaseHas('good_product', ['good_id' => $good['entity_id'], 'product_id' => $node['entity_id']]);
        $this->assertDatabaseHas('home_banners', ['title' => 'Ссылка', 'product_id' => $node['entity_id']]);
    }

    public function test_goods_with_operational_history_and_products_with_translations_keep_their_data(): void
    {
        $good = $this->createNode('good');
        DB::table('good_stock_alerts')->insert(['good_id' => $good['entity_id'], 'start_token_hash' => str_repeat('a', 64)]);
        $this->patchJson('/api/catalog/nodes/'.$good['id'], ['entity_type' => 'product'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
        $this->assertDatabaseHas('goods', ['id' => $good['entity_id']]);
        $this->assertDatabaseCount('products', 0);
        $product = $this->createNode('product');
        Product::findOrFail($product['entity_id'])->update(['eng' => 'Trout']);
        $this->patchJson('/api/catalog/nodes/'.$product['id'], ['entity_type' => 'custom'])
            ->assertOk()->assertJsonPath('data.entity_type', null)->assertJsonPath('data.translations.en', 'Trout');
        $this->assertDatabaseMissing('products', ['id' => $product['entity_id']]);
        $this->assertDatabaseHas('catalog_node_translations', ['node_id' => $product['id'], 'locale' => 'en', 'name' => 'Trout']);
    }

    public function test_unrelated_foreign_keys_do_not_block_conversion_but_legacy_unconstrained_references_do(): void
    {
        $category = $this->createNode('category');
        $tripId = DB::table('logistics_trips')->insertGetId(['number' => 'CATALOG-CONVERSION']);
        DB::table('logistics_expense_categories')->updateOrInsert(
            ['id' => $category['entity_id']], ['code' => 'catalog-conversion', 'name' => 'Расходы'],
        );
        $expenseId = DB::table('logistics_trip_expenses')->insertGetId([
            'trip_id' => $tripId, 'expense_category_id' => $category['entity_id'], 'allocated_amount' => 100,
        ]);
        $this->patchJson('/api/catalog/nodes/'.$category['id'], ['entity_type' => 'custom'])->assertOk();
        $this->assertDatabaseHas('logistics_trip_expenses', ['id' => $expenseId, 'expense_category_id' => $category['entity_id']]);

        $product = $this->createNode('product');
        $offerId = DB::table('mailing_offer_items')->insertGetId(['product_id' => $product['entity_id'], 'title' => 'Предложение']);
        $this->patchJson('/api/catalog/nodes/'.$product['id'], ['entity_type' => 'custom'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
        $this->assertDatabaseHas('mailing_offer_items', ['id' => $offerId, 'product_id' => $product['entity_id']]);
        $this->assertDatabaseHas('products', ['id' => $product['entity_id']]);
    }

    public function test_a_good_with_only_a_thumbnail_preserves_it_as_the_catalog_image(): void
    {
        $good = Good::create(['name' => 'Товар с миниатюрой', 'ava_thumb' => '/storage/thumb.jpg']);
        $node = $this->getJson('/api/catalog')->assertOk()->json('nodes.0');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'product'])
            ->assertOk()->assertJsonPath('data.image', '/storage/thumb.jpg');
        $this->assertModelMissing($good);
    }

    public function test_conversion_rejects_special_good_seo_and_shared_goods(): void
    {
        $first = $this->createNode('product');
        $goodNode = $this->createNode('good', $first['id']);
        $good = Good::findOrFail($goodNode['entity_id']);
        $good->seo()->create(['meta_title' => 'SEO', 'faq' => [['question' => 'Вопрос', 'answer' => 'Ответ']]]);
        $this->patchJson('/api/catalog/nodes/'.$goodNode['id'], ['entity_type' => 'custom'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
        $good->seo()->delete();
        $second = $this->createNode('product');
        $good->products()->attach($second['entity_id']);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(4, 'nodes');
        $this->patchJson('/api/catalog/nodes/'.$goodNode['id'], ['entity_type' => 'custom'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
        $this->assertSame(2, $good->products()->count());
    }

    public function test_a_leaf_good_can_become_a_product_in_its_existing_branch(): void
    {
        $parent = $this->createNode('product');
        $good = $this->createNode('good', $parent['id']);
        $this->patchJson('/api/catalog/nodes/'.$good['id'], ['entity_type' => 'product'])
            ->assertOk()->assertJsonPath('data.entity_type', 'product')->assertJsonPath('data.parent_id', $parent['id']);
        $this->assertDatabaseMissing('goods', ['id' => $good['entity_id']]);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(2, 'nodes');
    }

    public function test_goods_selected_in_draft_published_or_configured_landings_cannot_be_converted(): void
    {
        $good = $this->createNode('good');
        $page = $this->createNode('custom');
        foreach (['good_ids', 'inline_good_ids'] as $key) {
            foreach (['draft_content', 'published_content'] as $state) {
                $landing = CatalogLanding::create([
                    'catalog_node_id' => $page['id'], $state => ['catalog' => [$key => [$good['entity_id']]]],
                ]);
                $this->patchJson('/api/catalog/nodes/'.$good['id'], ['entity_type' => 'custom', 'name' => 'Не сохранять'])
                    ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
                $this->assertDatabaseHas('goods', ['id' => $good['entity_id'], 'name' => $good['name']]);
                $this->assertDatabaseHas('catalog_nodes', ['id' => $good['id'], 'entity_type' => 'good', 'name' => $good['name']]);
                $this->assertSame([$good['entity_id']], $landing->fresh()->{$state}['catalog'][$key]);
                $landing->delete();
            }
            config()->set('product-pages.pages', [123 => [$key => [$good['entity_id']]]]);
            $this->patchJson('/api/catalog/nodes/'.$good['id'], ['entity_type' => 'custom'])
                ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
            config()->set('product-pages.pages', []);
        }
    }

    public function test_only_leaf_nodes_without_landing_pages_can_become_goods(): void
    {
        $parent = $this->createNode('custom');
        $this->createNode('custom', $parent['id']);
        $this->patchJson('/api/catalog/nodes/'.$parent['id'], ['entity_type' => 'good'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
        $landing = $this->createNode('custom');
        CatalogLanding::create(['catalog_node_id' => $landing['id'], 'draft_content' => ['template' => 'overview']]);
        $this->patchJson('/api/catalog/nodes/'.$landing['id'], ['entity_type' => 'good'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');
        $this->assertDatabaseCount('goods', 0);
    }

    public function test_goods_cannot_be_parents_for_new_nodes_or_existing_branch_moves(): void
    {
        $good = $this->createNode('good', null, ['level_id' => CatalogLevel::where('entity_type', 'product')->value('id')]);
        foreach (['custom', 'category', 'product', 'good'] as $type) {
            $this->postJson('/api/catalog/nodes', ['name' => 'Нельзя добавить', 'entity_type' => $type, 'parent_id' => $good['id']])
                ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        }
        $branch = $this->createNode('custom');
        $child = $this->createNode('product', $branch['id']);
        $this->patchJson('/api/catalog/nodes/'.$branch['id'], ['parent_id' => $good['id'], 'name' => 'Нельзя перенести'])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->assertDatabaseHas('catalog_nodes', ['id' => $branch['id'], 'parent_id' => null, 'name' => $branch['name']]);
        $this->assertDatabaseHas('catalog_nodes', ['id' => $child['id'], 'parent_id' => $branch['id']]);
        $this->assertDatabaseCount('catalog_nodes', 3);
    }

    private function createNode(string $type, ?int $parentId = null, array $data = []): array
    {
        return $this->postJson('/api/catalog/nodes', [
            'entity_type' => $type, 'name' => 'Элемент '.$type, 'parent_id' => $parentId, ...$data,
        ])->assertCreated()->json('data');
    }
}
