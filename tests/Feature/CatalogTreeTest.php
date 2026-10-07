<?php

namespace Tests\Feature;

use App\Models\CatalogField;
use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Country;
use App\Models\Field;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Models\Product;
use App\Models\User;
use App\Models\VatRate;
use App\Services\Catalog\CatalogService;
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

    public function test_import_keeps_trout_product_inside_its_class_with_published_and_draft_goods(): void
    {
        $category = Category::create(['name' => 'Рыба', 'is_published' => true]);
        $product = Product::create(['rus' => 'Форель филе', 'category_id' => $category->id, 'is_published' => true]);
        $published = Good::create(['name' => 'Форель филе охлаждённое', 'is_published' => true]);
        $draft = Good::create(['name' => 'Форель филе замороженное', 'is_published' => false]);
        $product->goods()->attach([$published->id, $draft->id]);
        $this->staff();
        $this->getJson('/api/catalog')->assertOk();
        $categoryNode = CatalogNode::where('import_key', 'category:'.$category->id)->firstOrFail();
        $productNode = CatalogNode::where('import_key', 'product:'.$product->id)->firstOrFail();
        $classLevel = CatalogLevel::create(['name' => 'Класс', 'entity_type' => 'custom', 'display_mode' => 'tree']);
        $class = $this->createNode($classLevel, 'Форель', $categoryNode->id);
        $this->patchJson('/api/catalog/nodes/'.$productNode->id, ['parent_id' => $class['id']])->assertOk();

        foreach ([1, 2] as $iteration) {
            $nodes = collect($this->getJson('/api/catalog')->assertOk()->assertJsonCount(5, 'nodes')->json('nodes'))->keyBy('id');
            $this->assertSame($categoryNode->id, $nodes[$class['id']]['parent_id']);
            $this->assertSame($class['id'], $nodes[$productNode->id]['parent_id']);
            $this->assertSame('product', $nodes[$productNode->id]['entity_type']);
            $this->assertSame('Форель филе', $nodes[$productNode->id]['name']);
            $children = $nodes->where('parent_id', $productNode->id)->keyBy('entity_id');
            $this->assertCount(2, $children);
            $this->assertSame('good', $children[$published->id]['entity_type']);
            $this->assertSame('good', $children[$draft->id]['entity_type']);
            $this->assertTrue($children[$published->id]['is_published']);
            $this->assertFalse($children[$draft->id]['is_published']);
        }

        $additional = Good::create(['name' => 'Форель филе порционное', 'is_published' => true]);
        $additional->products()->attach($product->id);
        $nodes = collect($this->getJson('/api/catalog')->assertOk()->assertJsonCount(6, 'nodes')->json('nodes'))->keyBy('id');
        $this->assertSame($class['id'], $nodes[$productNode->id]['parent_id']);
        $this->assertEqualsCanonicalizing(
            [$published->id, $draft->id, $additional->id],
            $nodes->where('parent_id', $productNode->id)->pluck('entity_id')->all(),
        );
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertDatabaseCount('good_product', 3);
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
        $this->assertSame('/storage/thumb.png', $payload['thumbnail_url']);
        $this->assertStringContainsString('/catalog/', $payload['public_url']);
        $this->assertStringContainsString('/g/', $payload['offer_url']);
        $this->patchJson('/api/catalog/nodes/'.$payload['id'], ['name' => 'Новое имя', 'image' => $payload['image']])->assertOk();
        $this->assertSame('/storage/full.png', $good->fresh()->ava_image);
        $this->assertSame('/storage/thumb.png', $good->fresh()->ava_thumb);
    }

    public function test_catalog_thumbnails_use_stored_metadata_and_cdn_without_replacing_original_images(): void
    {
        config()->set([
            'filesystems.disks.yandex.bucket' => 'catalog-images-test',
            'filesystems.disks.yandex.url' => 'https://storage.yandexcloud.net/catalog-images-test',
            'goods-media.avatar_cdn_url' => 'https://images.example.test/',
        ]);
        $source = 'https://storage.yandexcloud.net/catalog-images-test/goods/1/';
        $full = Good::create(['name' => 'С миниатюрой', 'ava_image' => $source.'original.jpg', 'ava_thumb' => $source.'thumb.jpg']);
        $external = Good::create(['name' => 'Только оригинал', 'ava_image' => 'https://external.example.test/photo.jpg']);
        $thumbOnly = Good::create(['name' => 'Только миниатюра', 'ava_thumb' => '/storage/legacy-thumb.jpg']);
        $empty = Good::create(['name' => 'Без фотографии']);
        $category = Category::create(['name' => 'Раздел', 'image' => '/storage/category.png']);
        $custom = CatalogNode::create(['name' => 'Произвольный объект', 'image' => '/storage/custom.png']);
        $this->staff();
        Storage::shouldReceive('disk')->never();
        DB::enableQueryLog();

        $nodes = collect($this->getJson('/api/catalog')->assertOk()->json('nodes'));
        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        $goods = $nodes->where('entity_type', 'good')->keyBy('entity_id');
        $this->assertSame($source.'original.jpg', $goods[$full->id]['image']);
        $this->assertSame('https://images.example.test/goods/1/thumb.jpg', $goods[$full->id]['thumbnail_url']);
        $this->assertSame('https://external.example.test/photo.jpg', $goods[$external->id]['thumbnail_url']);
        $this->assertSame('/storage/legacy-thumb.jpg', $goods[$thumbOnly->id]['thumbnail_url']);
        $this->assertNull($goods[$empty->id]['thumbnail_url']);
        $this->assertNull($goods[$empty->id]['image']);
        $this->assertSame('/storage/category.png', $nodes->where('entity_type', 'category')->firstWhere('entity_id', $category->id)['thumbnail_url']);
        $this->assertSame('/storage/custom.png', $nodes->firstWhere('id', $custom->id)['thumbnail_url']);
        $this->assertStringNotContainsString('good_media', $queries);
    }

    public function test_catalog_gallery_can_reuse_staff_media_endpoint_including_draft_images(): void
    {
        $good = Good::create(['name' => 'Неопубликованный товар', 'is_published' => false]);
        $published = GoodMedia::create([
            'good_id' => $good->id, 'type' => 'image', 'disk' => 'yandex', 'path' => 'goods/photo.jpg',
            'url' => 'https://images.example.test/photo.jpg', 'thumb_url' => 'https://images.example.test/thumb.jpg',
            'is_published' => true, 'sort_order' => 1, 'title' => 'Основное фото', 'is_ava' => true,
        ]);
        $draft = GoodMedia::create([
            'good_id' => $good->id, 'type' => 'image', 'disk' => 'yandex', 'path' => 'goods/draft.jpg',
            'url' => 'https://images.example.test/draft.jpg', 'is_published' => false, 'sort_order' => 2,
        ]);
        $uri = '/api/goods/'.$good->id.'/media';
        $this->getJson($uri)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))->getJson($uri)->assertForbidden();
        $this->staff();
        Storage::shouldReceive('disk')->never();
        $this->getJson($uri)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(2)
            ->assertJsonPath('0.id', $published->id)->assertJsonPath('0.type', 'image')
            ->assertJsonPath('0.url', $published->url)->assertJsonPath('0.thumb_url', $published->thumb_url)
            ->assertJsonPath('0.title', 'Основное фото')->assertJsonPath('0.is_ava', true)
            ->assertJsonPath('1.id', $draft->id)->assertJsonPath('1.is_published', false);
    }

    public function test_good_overview_is_lazy_staff_only_and_returns_narrow_source_data_and_counts(): void
    {
        $country = Country::create(['name' => 'Россия', 'сodeISO' => 'RU', 'flag' => 'ru']);
        $vat = VatRate::create(['title' => 'НДС 10%', 'rate' => 10]);
        $field = Field::create(['title' => 'Рыбная подборка']);
        $product = Product::create(['rus' => 'Форель']);
        $good = Good::create(['name' => 'Филе', 'country_id' => $country->id, 'vat_rate_id' => $vat->id, 'denominator' => 12,
            'hs_code' => '030449', 'ava_image' => '/storage/full.jpg', 'ava_thumb' => '/storage/thumb.jpg', 'is_published' => false]);
        $good->products()->attach($product->id);
        $good->fields()->attach($field->id);
        GoodMedia::create(['good_id' => $good->id, 'type' => 'image', 'disk' => 'yandex', 'path' => 'test.jpg', 'url' => '/storage/test.jpg']);
        app(CatalogService::class)->importMissing();
        $node = CatalogNode::where('entity_type', 'good')->firstOrFail();
        $uri = '/api/catalog/nodes/'.$node->id.'/overview';
        $this->getJson($uri)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))->getJson($uri)->assertForbidden();
        $this->staff();
        Storage::shouldReceive('disk')->never();
        $this->getJson($uri)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.id', $good->id)->assertJsonPath('data.denominator', 12)
            ->assertJsonPath('data.country.id', $country->id)->assertJsonPath('data.vat_rate.id', $vat->id)
            ->assertJsonPath('data.hs_code', '030449')->assertJsonPath('data.products.0.id', $product->id)
            ->assertJsonPath('data.fields.0.id', $field->id)->assertJsonPath('data.fields.0.title', 'Рыбная подборка')
            ->assertJsonPath('data.ava_image', '/storage/full.jpg')->assertJsonPath('data.ava_thumb', '/storage/thumb.jpg')
            ->assertJsonPath('data.counts', ['prices' => 0, 'sales' => 0, 'purchases' => 0, 'media' => 1, 'quotations' => 0])
            ->assertJsonMissingPath('data.media')->assertJsonMissingPath('data.sales')->assertJsonMissingPath('data.products.0.manufacturers');
        $this->getJson('/api/catalog')->assertOk()->assertJsonMissingPath('nodes.0.denominator')->assertJsonMissingPath('nodes.0.counts');
        $productNode = CatalogNode::where('entity_type', 'product')->firstOrFail();
        $this->getJson('/api/catalog/nodes/'.$productNode->id.'/overview')->assertNotFound();
    }

    public function test_good_overview_saves_source_and_catalog_fields_together_without_changing_unedited_data(): void
    {
        $this->staff();
        $country = Country::create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $vat = VatRate::create(['title' => 'НДС 10%', 'rate' => 10]);
        $field = Field::create(['title' => 'Подборка']);
        $good = Good::create(['name' => 'Товар', 'ava_image' => '/storage/full.jpg', 'ava_thumb' => '/storage/thumb.jpg', 'gtin' => '04601234567890']);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('entity_type', 'good')->firstOrFail();
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['name' => 'Обновлённый товар', 'is_published' => true, 'good' => [
            'denominator' => 12.5, 'country_id' => $country->id, 'vat_rate_id' => $vat->id,
            'hs_code' => '0304.49', 'eccn_code' => 'ear99', 'fields' => [$field->id], 'products' => [],
        ]])->assertOk()->assertJsonPath('data.id', $node->id)->assertJsonPath('data.name', 'Обновлённый товар');
        $this->assertDatabaseHas('goods', ['id' => $good->id, 'name' => 'Обновлённый товар', 'is_published' => true, 'denominator' => 12.5,
            'country_id' => $country->id, 'vat_rate_id' => $vat->id, 'hs_code' => '030449', 'eccn_code' => 'EAR99', 'gtin' => '04601234567890',
            'ava_image' => '/storage/full.jpg', 'ava_thumb' => '/storage/thumb.jpg']);
        $this->assertSame([$field->id], $good->fields()->pluck('fields.id')->all());
    }

    public function test_good_overview_validation_prevents_partial_writes_and_rejects_other_source_types(): void
    {
        $this->staff();
        $good = Good::create(['name' => 'Исходный', 'denominator' => 10]);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('entity_type', 'good')->firstOrFail();
        foreach (['denominator' => -1, 'country_id' => 999999, 'vat_rate_id' => 999999, 'hs_code' => 'invalid',
            'products' => [999999], 'fields' => [999999], 'avatar_source_url' => 'javascript:alert(1)'] as $key => $value) {
            $this->patchJson('/api/catalog/nodes/'.$node->id, ['name' => 'Не сохранять', 'good' => [$key => $value]])->assertUnprocessable();
        }
        $this->assertSame('Исходный', $good->fresh()->name);
        $this->assertSame('Исходный', $node->fresh()->name);
        $this->assertSame(10.0, $good->fresh()->denominator);
        $custom = $this->createNode(CatalogLevel::create(['name' => 'Свободный уровень']), 'Раздел');
        $this->patchJson('/api/catalog/nodes/'.$custom['id'], ['name' => 'Не сохранять', 'good' => ['denominator' => 2]])
            ->assertUnprocessable()->assertJsonValidationErrors('good');
        $this->assertSame('Раздел', CatalogNode::findOrFail($custom['id'])->name);
    }

    public function test_good_overview_product_changes_preserve_edited_node_id_metadata_and_optional_sublevels(): void
    {
        $this->staff();
        $first = Product::create(['rus' => 'Первый']);
        $second = Product::create(['rus' => 'Второй']);
        $good = Good::create(['name' => 'Товар']);
        $good->products()->attach($first->id);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('entity_type', 'good')->firstOrFail();
        $level = CatalogLevel::create(['name' => 'Упаковка']);
        $branch = $this->createNode($level, 'Необязательный подуровень', $node->parent_id);
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['parent_id' => $branch['id']])->assertOk();
        $node->update(['properties' => ['kept' => 'value'], 'is_featured' => true]);
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['good' => ['products' => [$first->id]]])
            ->assertOk()->assertJsonPath('data.parent_id', $branch['id']);
        $secondNode = CatalogNode::where('import_key', 'product:'.$second->id)->firstOrFail();
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['parent_id' => (string) $branch['id'], 'good' => ['products' => [$second->id]]])
            ->assertOk()->assertJsonPath('data.id', $node->id)->assertJsonPath('data.parent_id', $secondNode->id)
            ->assertJsonPath('data.properties.kept', 'value')->assertJsonPath('data.is_featured', true);
        $this->assertSame([$second->id], $good->products()->pluck('products.id')->all());
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['good' => ['products' => []]])
            ->assertOk()->assertJsonPath('data.id', $node->id)->assertJsonPath('data.parent_id', null);
        $this->getJson('/api/catalog')->assertOk();
        $this->assertSame(1, CatalogNode::where('entity_type', 'good')->count());
        $this->assertNull($node->fresh()->parent_id);
        $this->assertCount(0, $good->products()->get());
    }

    public function test_good_overview_adds_other_product_placements_and_rolls_back_ambiguous_moves(): void
    {
        $this->staff();
        $first = Product::create(['rus' => 'Первый']);
        $second = Product::create(['rus' => 'Второй']);
        $good = Good::create(['name' => 'Исходное имя', 'denominator' => 10]);
        $good->products()->attach($first->id);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('entity_type', 'good')->firstOrFail();
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['good' => ['products' => [$first->id, $second->id]]])
            ->assertOk()->assertJsonPath('data.id', $node->id)->assertJsonPath('data.parent_id', $node->parent_id);
        $this->assertSame(2, CatalogNode::where('entity_type', 'good')->count());
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['name' => 'Не сохранять', 'good' => [
            'denominator' => 100, 'products' => [$second->id], 'remove_ava' => true,
        ]])->assertUnprocessable()->assertJsonValidationErrors('good.products');
        $this->assertSame('Исходное имя', $good->fresh()->name);
        $this->assertSame('Исходное имя', $node->fresh()->name);
        $this->assertSame(10.0, $good->fresh()->denominator);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $good->products()->pluck('products.id')->all());
        $this->assertSame(2, CatalogNode::where('entity_type', 'good')->count());
    }

    public function test_good_overview_unchanged_links_do_not_reclassify_a_manual_root_placement(): void
    {
        $this->staff();
        $first = Product::create(['rus' => 'Первый']);
        $second = Product::create(['rus' => 'Второй']);
        $good = Good::create(['name' => 'Товар']);
        $good->products()->attach([$first->id, $second->id]);
        $this->getJson('/api/catalog')->assertOk();
        $node = CatalogNode::where('import_key', 'good:'.$good->id.':product:'.$first->id)->firstOrFail();
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['parent_id' => null])->assertOk();
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['parent_id' => null, 'good' => [
            'denominator' => 12, 'products' => [$second->id],
        ]])->assertOk()->assertJsonPath('data.id', $node->id)->assertJsonPath('data.parent_id', null);
        $this->assertSame(12.0, $good->fresh()->denominator);
        $this->assertSame([$second->id], $good->products()->pluck('products.id')->all());
        $this->getJson('/api/catalog')->assertOk();
        $this->assertNull($node->fresh()->parent_id);
        $this->assertSame(2, CatalogNode::where('entity_type', 'good')->count());
    }

    public function test_good_overview_supports_new_unclassified_goods_and_avatar_metadata_edits_without_storage_deletion(): void
    {
        $this->staff();
        $product = Product::create(['rus' => 'Продукт']);
        $response = $this->postJson('/api/catalog/nodes', ['name' => 'Новый', 'entity_type' => 'good', 'good' => [
            'denominator' => 6, 'products' => [$product->id], 'avatar_source_url' => 'https://cdn.example.test/original.jpg',
            'avatar_thumb_source_url' => 'https://cdn.example.test/thumb.jpg',
        ]])->assertCreated()->assertJsonPath('data.level_id', null);
        $nodeId = $response->json('data.id');
        $goodId = $response->json('data.entity_id');
        $this->assertDatabaseHas('good_product', ['good_id' => $goodId, 'product_id' => $product->id]);
        $this->assertSame(CatalogNode::where('import_key', 'product:'.$product->id)->value('id'), $response->json('data.parent_id'));
        Storage::shouldReceive('disk')->never();
        $this->patchJson('/api/catalog/nodes/'.$nodeId, ['good' => ['avatar_source_url' => 'https://cdn.example.test/original.jpg']])
            ->assertOk()->assertJsonPath('data.thumbnail_url', 'https://cdn.example.test/thumb.jpg');
        $this->patchJson('/api/catalog/nodes/'.$nodeId, ['good' => ['avatar_source_url' => 'https://cdn.example.test/updated.jpg']])
            ->assertOk()->assertJsonPath('data.image', 'https://cdn.example.test/updated.jpg');
        $this->assertNull(Good::findOrFail($goodId)->ava_thumb);
        $this->patchJson('/api/catalog/nodes/'.$nodeId, ['good' => ['remove_ava' => true]])
            ->assertOk()->assertJsonPath('data.image', null)->assertJsonPath('data.thumbnail_url', null);
        $this->assertDatabaseHas('goods', ['id' => $goodId, 'ava_image' => null, 'ava_thumb' => null]);
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

    public function test_trout_fillet_repair_moves_only_diagnosed_goods_without_losing_other_links_or_metadata(): void
    {
        $fixture = $this->troutFilletRepairFixture();
        $migration = require database_path('migrations/2026_10_07_180000_place_trout_fillet_goods_under_product.php');
        $before = $fixture['placements']->map(fn (CatalogNode $node): array => $node->fresh()->getAttributes());
        $migration->up();

        foreach ([99, 148] as $goodId) {
            $node = $fixture['placements'][$goodId]->fresh();
            $this->assertSame($fixture['fillet']->id, $node->parent_id);
            $this->assertSame('good:'.$goodId.':product:186', $node->import_key);
            $this->assertSame(
                collect($before[$goodId])->except(['parent_id', 'import_key', 'updated_at'])->all(),
                collect($node->getAttributes())->except(['parent_id', 'import_key', 'updated_at'])->all(),
            );
            $this->assertDatabaseHas('good_product', ['good_id' => $goodId, 'product_id' => 186]);
            $this->assertDatabaseMissing('good_product', ['good_id' => $goodId, 'product_id' => 99]);
        }
        $this->assertSame($fixture['class']->id, $fixture['placements'][110]->fresh()->parent_id);
        $this->assertDatabaseHas('good_product', ['good_id' => 110, 'product_id' => 99]);
        $this->assertDatabaseHas('good_product', ['good_id' => 99, 'product_id' => 250]);
        $this->assertSame($fixture['other']->id, $fixture['other_placement']->fresh()->parent_id);
        $this->assertTrue(Good::findOrFail(99)->is_published);
        $this->assertFalse(Good::findOrFail(148)->is_published);
        $this->assertDatabaseCount('goods', 3);
        $this->assertDatabaseCount('good_product', 4);

        $after = CatalogNode::orderBy('id')->get()->toArray();
        $migration->up();
        $migration->down();
        $this->getJson('/api/catalog')->assertOk();
        $this->getJson('/api/catalog')->assertOk();
        $this->assertSame($after, CatalogNode::orderBy('id')->get()->toArray());
    }

    public function test_trout_fillet_repair_respects_renamed_goods_and_manually_reparented_goods(): void
    {
        $fixture = $this->troutFilletRepairFixture();
        Good::findOrFail(99)->update(['name' => 'Новое назначение товара']);
        $branch = CatalogNode::create(['name' => 'Другой раздел', 'parent_id' => $fixture['class']->id]);
        $fixture['placements'][148]->update(['parent_id' => $branch->id]);
        $before = CatalogNode::orderBy('id')->get()->toArray();
        $migration = require database_path('migrations/2026_10_07_180000_place_trout_fillet_goods_under_product.php');
        $migration->up();
        $this->assertSame($before, CatalogNode::orderBy('id')->get()->toArray());
        $this->assertDatabaseMissing('good_product', ['product_id' => 186]);
    }

    public function test_trout_fillet_repair_leaves_existing_target_placements_and_external_link_edits_alone(): void
    {
        $fixture = $this->troutFilletRepairFixture();
        Good::findOrFail(99)->products()->attach(186);
        $this->getJson('/api/catalog')->assertOk();
        Good::findOrFail(148)->products()->detach(99);
        $before = CatalogNode::orderBy('id')->get()->toArray();
        $migration = require database_path('migrations/2026_10_07_180000_place_trout_fillet_goods_under_product.php');
        $migration->up();
        $this->assertSame($before, CatalogNode::orderBy('id')->get()->toArray());
        $this->assertDatabaseHas('good_product', ['good_id' => 99, 'product_id' => 99]);
        $this->assertDatabaseHas('good_product', ['good_id' => 99, 'product_id' => 186]);
        $this->assertDatabaseMissing('good_product', ['good_id' => 148, 'product_id' => 186]);
        $this->assertSame($fixture['class']->id, $fixture['placements'][99]->fresh()->parent_id);
    }

    public function test_trout_fillet_repair_does_not_guess_after_product_names_or_class_hierarchy_change(): void
    {
        $fixture = $this->troutFilletRepairFixture();
        $migration = require database_path('migrations/2026_10_07_180000_place_trout_fillet_goods_under_product.php');
        Product::without(['category', 'manufacturers'])->findOrFail(186)->update(['rus' => 'Другой продукт']);
        $before = CatalogNode::orderBy('id')->get()->toArray();
        $migration->up();
        $this->assertSame($before, CatalogNode::orderBy('id')->get()->toArray());
        Product::without(['category', 'manufacturers'])->findOrFail(186)->update(['rus' => 'Форель филе']);
        $fixture['fillet']->update(['parent_id' => $fixture['other']->id]);
        $before = CatalogNode::orderBy('id')->get()->toArray();
        $migration->up();
        $this->assertSame($before, CatalogNode::orderBy('id')->get()->toArray());
        $this->assertDatabaseMissing('good_product', ['product_id' => 186]);
    }

    private function troutFilletRepairFixture(): array
    {
        $this->staff();
        $category = Category::create(['name' => 'Рыба', 'is_published' => true]);
        foreach ([99 => 'Форель', 186 => 'Форель филе', 250 => 'Другое назначение'] as $id => $name) {
            Product::forceCreate(['id' => $id, 'rus' => $name, 'category_id' => $category->id, 'is_published' => true]);
        }
        $names = [
            99 => 'Форель филе-кусок б/к и/з вакуум 12/12',
            110 => 'Форель радужная ПБГ IQF 3.6-4.5 Турция',
            148 => 'Форель филе-кубики б/к зам. 1/12',
        ];
        foreach ($names as $id => $name) {
            $good = Good::forceCreate(['id' => $id, 'name' => $name, 'is_published' => $id !== 148]);
            $good->products()->attach($id === 99 ? [99, 250] : [99]);
        }
        $this->getJson('/api/catalog')->assertOk();
        $class = CatalogNode::where('import_key', 'product:99')->firstOrFail();
        $fillet = CatalogNode::where('import_key', 'product:186')->firstOrFail();
        $classLevel = CatalogLevel::create(['name' => 'Класс', 'entity_type' => 'custom', 'display_mode' => 'tree']);
        $class->update(['level_id' => $classLevel->id]);
        $fillet = app(CatalogService::class)->saveNode(['parent_id' => $class->id], $fillet);
        $placements = collect();
        foreach (array_keys($names) as $goodId) {
            $node = CatalogNode::where('import_key', 'good:'.$goodId.':product:99')->firstOrFail();
            $node->update([
                'properties' => ['origin' => 'Карелия'], 'properties_by_level' => [$classLevel->id => ['grade' => 'Высший']],
                'is_manual' => $goodId === 148, 'is_featured' => $goodId === 99, 'sort_order' => 7,
            ]);
            $placements[$goodId] = $node;
        }

        return [
            'class' => $class, 'fillet' => $fillet, 'placements' => $placements,
            'other' => CatalogNode::where('import_key', 'product:250')->firstOrFail(),
            'other_placement' => CatalogNode::where('import_key', 'good:99:product:250')->firstOrFail(),
        ];
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
