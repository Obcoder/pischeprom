<?php

namespace Tests\Feature;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\CatalogSiteDomain;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogAvatars;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\CatalogSiteContext;
use App\Services\Catalog\PublicCatalogService;
use App\Services\Goods\HomeGoodsModuleService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
    }

    public static function classifications(): array
    {
        return array_combine(
            ['category', 'product', 'custom', 'domain', 'unclassified'],
            array_map(fn (string $kind): array => [$kind], ['category', 'product', 'custom', 'domain', 'unclassified']),
        );
    }

    #[DataProvider('classifications')]
    public function test_every_classification_can_upload_replace_and_clear_its_avatar(string $kind): void
    {
        Storage::fake('public');
        $this->staff();
        $level = match ($kind) {
            'unclassified' => null,
            'domain' => CatalogLevel::where('is_domain', true)->firstOrFail(),
            'custom' => CatalogLevel::create(['name' => 'Класс', 'entity_type' => 'custom']),
            default => CatalogLevel::where('entity_type', $kind)->firstOrFail(),
        };
        $node = $this->postJson('/api/catalog/nodes', ['name' => 'Аватар '.$kind, 'level_id' => $level?->id])
            ->assertCreated()->json('data');
        $response = $this->postJson('/api/catalog/nodes/'.$node['id'].'/image', ['image' => UploadedFile::fake()->image('avatar.png')])
            ->assertOk();
        $image = $response->json('data.image');
        $this->assertStringContainsString('/storage/catalog-images/', $image);
        $response->assertJsonPath('data.thumbnail_url', $image);
        $this->assertCount(1, Storage::disk('public')->allFiles('catalog-images'));
        $this->assertSame($image, CatalogNode::findOrFail($node['id'])->image);
        if ($kind === 'category') {
            $this->assertSame($image, Category::findOrFail($node['entity_id'])->image);
        }

        $replacement = 'https://cdn.example.test/'.$kind.'.webp';
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['image' => $replacement])->assertOk()
            ->assertJsonPath('data.image', $replacement)->assertJsonPath('data.thumbnail_url', $replacement);
        $snapshot = collect($this->getJson('/api/catalog')->assertOk()->json('nodes'))->keyBy('id');
        $this->assertSame($replacement, $snapshot[$node['id']]['thumbnail_url']);

        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['image' => null])->assertOk()
            ->assertJsonPath('data.image', null)->assertJsonPath('data.thumbnail_url', null);
        $this->assertNull(CatalogNode::findOrFail($node['id'])->image);
        if ($kind === 'category') {
            $this->assertNull(Category::findOrFail($node['entity_id'])->image);
        }
    }

    public function test_avatar_survives_reclassification_and_entity_conversion_without_a_second_good_avatar(): void
    {
        $this->staff();
        $node = $this->postJson('/api/catalog/nodes', [
            'name' => 'Форель', 'entity_type' => 'product', 'image' => '/storage/trout.png',
        ])->assertCreated()->json('data');
        $level = CatalogLevel::create(['name' => 'Вид', 'entity_type' => 'custom']);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => $level->id])
            ->assertOk()->assertJsonPath('data.image', '/storage/trout.png');
        foreach (['category', 'custom', 'good', 'product'] as $type) {
            $response = $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => $type])
                ->assertOk()->assertJsonPath('data.image', '/storage/trout.png')->assertJsonPath('data.thumbnail_url', '/storage/trout.png');
            if ($type === 'good') {
                $this->assertDatabaseHas('goods', ['id' => $response->json('data.entity_id'), 'ava_image' => '/storage/trout.png']);
            }
        }
    }

    public function test_upload_permissions_and_validation_do_not_create_invalid_files(): void
    {
        Storage::fake('public');
        $node = CatalogNode::create(['name' => 'Раздел', 'image' => '/storage/existing.png']);
        $url = '/api/catalog/nodes/'.$node->id.'/image';
        $this->postJson($url, ['image' => UploadedFile::fake()->image('avatar.png')])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))
            ->postJson($url, ['image' => UploadedFile::fake()->image('avatar.png')])->assertForbidden();
        $this->staff();
        foreach ([UploadedFile::fake()->create('avatar.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.jpg')->size(5121)] as $file) {
            $this->postJson($url, ['image' => $file])->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['image' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertSame('/storage/existing.png', $node->fresh()->image);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_public_tree_breadcrumbs_children_and_showcase_include_each_levels_avatar(): void
    {
        $category = Category::create(['name' => 'Рыба', 'image' => '/storage/fish.png', 'is_published' => true]);
        $product = Product::create(['rus' => 'Форель', 'category_id' => $category->id, 'is_published' => true]);
        $good = Good::create(['name' => 'Форель свежая', 'ava_image' => '/storage/good-full.png', 'ava_thumb' => '/storage/good-thumb.png', 'is_published' => true]);
        $root = CatalogNode::create(['name' => 'Рыба', 'entity_type' => 'category', 'entity_id' => $category->id, 'image' => '/storage/obsolete.png']);
        $custom = CatalogNode::create(['name' => 'Лососёвые', 'parent_id' => $root->id, 'image' => '/storage/salmon.png', 'is_published' => true, 'is_featured' => true]);
        $productNode = CatalogNode::create(['name' => 'Форель', 'parent_id' => $custom->id, 'entity_type' => 'product', 'entity_id' => $product->id, 'image' => '/storage/trout.png']);
        $goodNode = CatalogNode::create(['name' => 'Форель свежая', 'parent_id' => $productNode->id, 'entity_type' => 'good', 'entity_id' => $good->id]);

        $response = $this->get('/g?node_id='.$productNode->id, ['X-Inertia' => 'true'])->assertOk();
        $tree = collect($response->json('props.catalogTree'))->keyBy('id');
        foreach ([$root->id => '/storage/fish.png', $custom->id => '/storage/salmon.png', $productNode->id => '/storage/trout.png'] as $id => $image) {
            $this->assertSame($image, $tree[$id]['image']);
            $this->assertSame($image, $tree[$id]['thumbnail_url']);
        }
        $response->assertJsonPath('props.breadcrumbs.0.image', '/storage/fish.png')
            ->assertJsonPath('props.breadcrumbs.1.thumbnail_url', '/storage/salmon.png')
            ->assertJsonPath('props.breadcrumbs.2.thumbnail_url', '/storage/trout.png');

        $catalog = app(PublicCatalogService::class);
        $page = $catalog->page($productNode->id);
        $this->assertSame('/storage/trout.png', $page['node']['thumbnail_url']);
        $this->assertSame('/storage/fish.png', $page['breadcrumbs'][0]['thumbnail_url']);
        $this->assertSame($goodNode->id, $page['children'][0]['id']);
        $this->assertSame('/storage/good-full.png', $page['children'][0]['image']);
        $this->assertSame('/storage/good-thumb.png', $page['children'][0]['thumbnail_url']);
        $this->assertSame('/storage/salmon.png', $catalog->showcase()[0]['thumbnail_url']);
        $this->assertSame('/storage/good-full.png', $good->fresh()->ava_image);
        $this->assertSame('/storage/good-thumb.png', $good->fresh()->ava_thumb);
    }

    public function test_domain_avatar_uses_the_current_category_source(): void
    {
        $category = Category::create(['name' => 'Пищепром', 'image' => '/storage/site.png', 'is_published' => true]);
        $node = CatalogNode::create([
            'name' => 'Пищепром', 'level_id' => CatalogLevel::where('is_domain', true)->value('id'),
            'entity_type' => 'category', 'entity_id' => $category->id, 'image' => '/storage/old.png', 'is_published' => true,
        ]);
        CatalogSiteDomain::create(['catalog_node_id' => $node->id, 'hostname' => 'localhost']);
        $this->app->instance('request', Request::create('https://localhost'));
        $this->assertSame('/storage/site.png', app(CatalogSiteContext::class)->site()['thumbnail_url']);
        $category->update(['image' => null]);
        $this->assertNull(app(CatalogSiteContext::class)->site()['image']);
    }

    public function test_legacy_product_api_hydrates_catalog_avatars_in_one_query_and_reflects_removal(): void
    {
        $this->staff();
        $category = Category::create(['name' => 'Рыба']);
        $products = collect(range(1, 8))->map(fn (int $index): Product => Product::create(['rus' => 'Рыба '.$index, 'category_id' => $category->id]));
        foreach ($products as $product) {
            CatalogNode::create(['name' => $product->rus, 'entity_type' => 'product', 'entity_id' => $product->id, 'image' => '/storage/product-'.$product->id.'.png']);
        }
        $avatarQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$avatarQueries): void {
            if (str_contains($query->sql, 'from "catalog_nodes"')) {
                $avatarQueries++;
            }
        });
        $response = $this->getJson('/api/products')->assertOk()->assertJsonCount(8);
        $this->assertSame(1, $avatarQueries);
        $payload = collect($response->json())->keyBy('id');
        foreach ($products as $product) {
            $this->assertSame('/storage/product-'.$product->id.'.png', $payload[$product->id]['thumbnail_url']);
        }
        $product = $products->first();
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('image', '/storage/product-'.$product->id.'.png');
        $this->getJson('/api/categories/'.$category->id)->assertOk()->assertJsonPath('data.products.0.thumbnail_url', '/storage/product-'.$product->id.'.png');
        CatalogNode::where('entity_id', $product->id)->where('entity_type', 'product')->update(['image' => null]);
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('image', null)->assertJsonPath('thumbnail_url', null);
    }

    public function test_legacy_public_pages_and_home_contents_use_catalog_product_avatars(): void
    {
        $category = Category::create(['name' => 'Рыба', 'image' => '/storage/fish.png', 'is_published' => true]);
        $product = Product::create(['rus' => 'Форель', 'category_id' => $category->id, 'is_published' => true]);
        $good = Good::create(['name' => 'Форель свежая', 'is_published' => true]);
        $good->products()->attach($product);
        app(CatalogService::class)->importMissing();
        CatalogNode::where('entity_type', 'product')->where('entity_id', $product->id)->update(['image' => '/storage/trout.png']);

        $this->get('/p/'.$product->id, ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.product.thumbnail_url', '/storage/trout.png');
        $this->get(route('public.goods.show', $good->slug), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.good.products.0.thumbnail_url', '/storage/trout.png');
        $this->getJson(route('goods.published'))->assertOk()->assertJsonPath('0.products.0.thumbnail_url', '/storage/trout.png');
        $this->get(route('category.show', $category->slug), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.category.products.0.thumbnail_url', '/storage/trout.png');
        $home = app(HomeGoodsModuleService::class)->build(null);
        $this->assertSame('/storage/fish.png', $home['table_of_contents'][0]['thumbnail_url']);
        $this->assertSame('/storage/trout.png', $home['table_of_contents'][0]['entries'][0]['thumbnail_url']);
    }

    public function test_public_product_avatar_hydration_hides_unpublished_branches(): void
    {
        $product = Product::create(['rus' => 'Продукт', 'is_published' => true]);
        $parent = CatalogNode::create(['name' => 'Закрытый раздел', 'is_published' => false]);
        CatalogNode::create(['name' => $product->rus, 'entity_type' => 'product', 'entity_id' => $product->id, 'parent_id' => $parent->id, 'image' => '/storage/private.png']);
        $avatars = app(CatalogAvatars::class);
        $avatars->appendProducts(collect([$product]), public: true);
        $this->assertNull($product->image);
        $avatars->appendProducts(collect([$product]));
        $this->assertSame('/storage/private.png', $product->image);
    }

    public function test_avatar_urls_accept_local_and_http_images_and_reject_malformed_sources(): void
    {
        $avatars = app(CatalogAvatars::class);
        foreach ([
            '//external.test/image.png', 'data:image/svg+xml,unsafe', 'javascript:alert(1)',
            '/\\external.test/image.png', "https://example.test/\x00avatar.png", 'https://user:pass@example.test/image.png',
            'https://bad host/image.png', 'https:///missing-host.png', 'https://example.test:99999/image.png',
            "https://example.test/avatar.png\x00", 'https://[::1]]/avatar.png',
        ] as $invalid) {
            $this->assertNull($avatars->url($invalid), $invalid);
        }
        foreach (['/storage/avatar.png', 'https://example.test/photo.jpg?size=small', 'https://пищепром-сервер.рф/фото.png', 'https://[::1]/avatar.png'] as $valid) {
            $this->assertSame($valid, $avatars->url(' '.$valid.' '));
        }
        $node = CatalogNode::create(['name' => 'Раздел', 'image' => 'https://user:pass@example.test/avatar.png', 'is_published' => true, 'is_featured' => true]);
        $page = app(PublicCatalogService::class)->page($node->id);
        $this->assertNull($page['node']['image']);
        $this->assertNull($page['node']['thumbnail_url']);
        $this->assertNull($page['seo']['image']);
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }
}
