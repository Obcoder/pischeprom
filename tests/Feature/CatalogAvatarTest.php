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
use DOMDocument;
use DOMXPath;
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

    #[DataProvider('classifications')]
    public function test_every_classification_accepts_svg_without_losing_shapes_styles_or_gradients(string $kind): void
    {
        Storage::fake('public');
        $this->staff();
        $level = match ($kind) {
            'unclassified' => null,
            'domain' => CatalogLevel::where('is_domain', true)->firstOrFail(),
            'custom' => CatalogLevel::create(['name' => 'Класс SVG', 'entity_type' => 'custom']),
            default => CatalogLevel::where('entity_type', $kind)->firstOrFail(),
        };
        $node = $this->postJson('/api/catalog/nodes', ['name' => 'SVG '.$kind, 'level_id' => $level?->id])
            ->assertCreated()->json('data');
        $response = $this->postJson('/api/catalog/nodes/'.$node['id'].'/image', [
            'image' => UploadedFile::fake()->createWithContent('avatar.svg', $this->svg()),
        ])->assertOk();
        $image = $response->json('data.image');
        $response->assertJsonPath('data.thumbnail_url', $image);
        $this->assertStringEndsWith('.svg', $image);
        $files = Storage::disk('public')->allFiles('catalog-images');
        $this->assertCount(1, $files);
        $this->assertSame(Storage::disk('public')->url($files[0]), $image);
        $this->assertSame($image, CatalogNode::findOrFail($node['id'])->image);
        if ($kind === 'category') {
            $this->assertSame($image, Category::findOrFail($node['entity_id'])->image);
        }
        $this->assertSvgArtworkPreserved(Storage::disk('public')->get($files[0]));
    }

    public function test_svg_upload_removes_executable_content_and_external_references(): void
    {
        Storage::fake('public');
        $this->staff();
        $node = CatalogNode::create(['name' => 'Аватар SVG']);
        $svg = str_replace('viewBox="0 0 32 32"', 'viewBox="0 0 32 32" onload="alert(1)"', $this->svg());
        $svg = str_replace('</svg>', <<<'SVG'
            <script>alert('unsafe-script')</script>
            <foreignObject><div xmlns="http://www.w3.org/1999/xhtml">unsafe-html</div></foreignObject>
            <image href="https://remote.example.test/avatar.png" width="32" height="32" />
            <use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="https://remote.example.test/symbols.svg#icon" />
            <rect onclick="alert(2)" width="1" height="1" />
            <path style="fill: url(https://remote.example.test/fill.svg#gradient)" d="M0 0h1v1Z" />
            <style>@import url("https://remote.example.test/style.css"); .remote { fill: url("https://remote.example.test/fill.svg#gradient"); }</style>
            </svg>
            SVG, $svg);
        $this->postJson('/api/catalog/nodes/'.$node->id.'/image', [
            'image' => UploadedFile::fake()->createWithContent('avatar.svg', $svg),
        ])->assertOk();

        $files = Storage::disk('public')->allFiles('catalog-images');
        $this->assertCount(1, $files);
        $stored = Storage::disk('public')->get($files[0]);
        foreach (['<script', 'onload=', 'onclick=', 'foreignObject', 'unsafe-script', 'unsafe-html', 'remote.example.test'] as $removed) {
            $this->assertStringNotContainsString($removed, $stored);
        }
        $this->assertSvgArtworkPreserved($stored);
        Http::assertNothingSent();
    }

    public function test_invalid_svg_documents_preserve_the_current_catalog_avatar_and_files(): void
    {
        Storage::fake('public');
        $this->staff();
        $path = 'catalog-images/existing.svg';
        Storage::disk('public')->put($path, $this->svg());
        $image = Storage::disk('public')->url($path);
        $node = CatalogNode::create(['name' => 'Существующий аватар', 'image' => $image]);
        foreach ([
            '<svg xmlns="http://www.w3.org/2000/svg"><path></svg>',
            '<svg xmlns="urn:wrong-namespace"><path d="M0 0h1v1Z" /></svg>',
            '<div xmlns="http://www.w3.org/2000/svg">Not an SVG root</div>',
        ] as $invalid) {
            $this->postJson('/api/catalog/nodes/'.$node->id.'/image', [
                'image' => UploadedFile::fake()->createWithContent('broken.svg', $invalid),
            ])->assertUnprocessable()->assertJsonValidationErrors('image');
            $this->assertSame($image, $node->fresh()->image);
            $this->assertSame([$path], Storage::disk('public')->allFiles('catalog-images'));
            $this->assertSame($this->svg(), Storage::disk('public')->get($path));
        }
    }

    public function test_legacy_categories_can_create_and_replace_svg_avatars(): void
    {
        Storage::fake('public');
        $this->staff();
        $response = $this->postJson('/api/categories', [
            'name' => 'Категория SVG', 'avatar' => UploadedFile::fake()->createWithContent('avatar.svg', $this->svg()),
        ])->assertCreated();
        $id = $response->json('data.id');
        $firstFiles = Storage::disk('public')->allFiles('category-avatars');
        $this->assertCount(1, $firstFiles);
        $response->assertJsonPath('data.image', Storage::disk('public')->url($firstFiles[0]));
        $this->assertSvgArtworkPreserved(Storage::disk('public')->get($firstFiles[0]));

        $replacement = str_replace('</svg>', '<script>alert(1)</script></svg>', $this->svg());
        $response = $this->patchJson('/api/categories/'.$id, [
            'name' => 'Категория SVG', 'avatar' => UploadedFile::fake()->createWithContent('replacement.svg', $replacement),
        ])->assertOk();
        $files = Storage::disk('public')->allFiles('category-avatars');
        $this->assertCount(1, $files);
        $this->assertNotSame($firstFiles[0], $files[0]);
        Storage::disk('public')->assertMissing($firstFiles[0]);
        $response->assertJsonPath('data.image', Storage::disk('public')->url($files[0]));
        $this->assertSame($response->json('data.image'), Category::findOrFail($id)->image);
        $stored = Storage::disk('public')->get($files[0]);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertSvgArtworkPreserved($stored);
    }

    public function test_invalid_legacy_category_upload_keeps_the_existing_avatar_even_when_removal_is_requested(): void
    {
        Storage::fake('public');
        $this->staff();
        $path = 'category-avatars/existing.svg';
        Storage::disk('public')->put($path, $this->svg());
        $image = Storage::disk('public')->url($path);
        $category = Category::create(['name' => 'Категория с аватаром', 'image' => $image]);
        foreach ([
            UploadedFile::fake()->createWithContent('broken.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path></svg>'),
            UploadedFile::fake()->createWithContent('wrong-root.svg', '<div xmlns="http://www.w3.org/2000/svg">Not an SVG root</div>'),
            UploadedFile::fake()->createWithContent('not-image.txt', 'This is not an image'),
            UploadedFile::fake()->createWithContent('large.svg', $this->svg())->size(4097),
        ] as $file) {
            $this->patchJson('/api/categories/'.$category->id, [
                'name' => $category->name, 'avatar' => $file, 'remove_avatar' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('avatar');
            $this->assertSame($image, $category->fresh()->image);
            $this->assertSame([$path], Storage::disk('public')->allFiles('category-avatars'));
            $this->assertSame($this->svg(), Storage::disk('public')->get($path));
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
        foreach ([
            // An empty file claiming the SVG MIME type is still invalid XML.
            UploadedFile::fake()->create('avatar.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->createWithContent('not-image.txt', 'This is not an image'),
            UploadedFile::fake()->image('large.jpg')->size(5121),
            UploadedFile::fake()->createWithContent('large.svg', $this->svg())->size(5121),
        ] as $file) {
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

    private function svg(): string
    {
        return <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
              <defs><linearGradient id="avatar-gradient"><stop offset="0" stop-color="#123456" /><stop offset="1" stop-color="#abcdef" /></linearGradient></defs>
              <style>.avatar-shape { fill: url(#avatar-gradient); stroke: #654321; }</style>
              <path id="avatar-shape" class="avatar-shape" style="opacity: 0.8" d="M2 2h28v28H2Z" />
            </svg>
            SVG;
    }

    private function assertSvgArtworkPreserved(string $svg): void
    {
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($svg, LIBXML_NONET));
        $this->assertSame('svg', $document->documentElement->localName);
        $this->assertSame('http://www.w3.org/2000/svg', $document->documentElement->namespaceURI);
        $this->assertSame('0 0 32 32', $document->documentElement->getAttribute('viewBox'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
        $path = $xpath->query('//svg:path[@id="avatar-shape"]')->item(0);
        $this->assertNotNull($path);
        $this->assertSame('M2 2h28v28H2Z', $path->getAttribute('d'));
        $this->assertSame('opacity: 0.8', $path->getAttribute('style'));
        $this->assertSame(2, $xpath->query('//svg:linearGradient[@id="avatar-gradient"]/svg:stop')->length);
        $this->assertSame('#123456', $xpath->query('//svg:linearGradient/svg:stop')->item(0)->getAttribute('stop-color'));
        $this->assertStringContainsString('fill: url(#avatar-gradient)', $xpath->query('//svg:style')->item(0)->textContent);
    }
}
