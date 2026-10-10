<?php

namespace Tests\Feature;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\Product;
use App\Services\Seo\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesClassCatalog;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use CreatesClassCatalog;
    use RefreshDatabase;

    public function test_sitemap_adds_one_published_configured_class_and_retains_existing_urls(): void
    {
        $guide = Product::forceCreate(['id' => 124, 'rus' => 'Скумбрия', 'is_published' => true]);
        $hidden = Product::forceCreate(['id' => 125, 'rus' => 'Скрытый гид', 'is_published' => false]);
        $ordinary = Product::forceCreate(['id' => 201, 'rus' => 'Замороженная', 'is_published' => true]);
        config()->set('product-pages.pages', [124 => ['guide' => 'mackerel', 'catalog_node_id' => 160],
            125 => ['guide' => 'hidden', 'catalog_node_id' => 161], 201 => ['guide' => '']]);
        $category = Category::create(['name' => 'Рыба', 'slug' => 'ryba', 'is_published' => true]);
        $node = $this->createClassCatalog($guide, $category);
        CatalogNode::forceCreate(['id' => 161, 'level_id' => $node->level_id, 'parent_id' => 25,
            'entity_type' => 'product', 'entity_id' => $hidden->id, 'name' => $hidden->rus,
            'slug' => 'hidden', 'is_published' => true]);
        $good = Good::create(['name' => 'Скумбрия', 'slug' => 'mackerel', 'is_published' => true]);
        $goodLevel = CatalogLevel::create(['name' => 'Товар SSR', 'entity_type' => 'good']);
        CatalogNode::create(['level_id' => $goodLevel->id, 'parent_id' => $node->id,
            'entity_type' => 'good', 'entity_id' => $good->id, 'name' => $good->name,
            'slug' => $good->slug, 'is_published' => true]);
        GoodSeo::create(['good_id' => $good->id, 'slug_override' => 'current-mackerel', 'is_active' => true,
            'include_in_sitemap' => true, 'robots' => 'index,follow']);

        $response = $this->get(route('seo.sitemap'))->assertOk();
        $xml = simplexml_load_string($response->getContent());
        $urls = [];
        foreach ($xml->url as $entry) {
            $urls[] = (string) $entry->loc;
        }
        $this->assertSame(1, count(array_filter($urls, fn ($url) => $url === $this->classUrl())));
        $this->assertContains(route('home'), $urls);
        $this->assertContains(route('category.show', $category->slug), $urls);
        $this->assertContains(route('public.goods.show', 'current-mackerel'), $urls);
        $this->assertNotContains(route('shop.products.show', $hidden), $urls);
        $this->assertNotContains(route('shop.products.show', $ordinary), $urls);
        $this->assertNotContains(route('shop.products.show', $guide), $urls);
        $this->assertNotContains(url('/catalog/160/skumbriia'), $urls);
        $this->assertNotContains(url('/catalog/ryba/hidden'), $urls);
        $category->update(['is_published' => false]);
        $this->assertStringNotContainsString($this->classUrl(), app(SitemapService::class)->xml());
        $category->update(['is_published' => true]);
        $guide->update(['is_published' => false]);
        $this->assertStringNotContainsString($this->classUrl(), app(SitemapService::class)->xml());
        $guide->update(['is_published' => true]);
        $node->update(['entity_id' => $ordinary->id]);
        $this->assertStringNotContainsString($this->classUrl(), app(SitemapService::class)->xml());
        $this->assertFileDoesNotExist(public_path('sitemap.xml'), 'A static sitemap must not shadow the live route.');
    }

    public function test_legacy_generation_command_writes_only_a_private_snapshot(): void
    {
        $previousStorage = $this->app->storagePath();
        $temporaryStorage = sys_get_temp_dir().'/pischeprom-sitemap-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($temporaryStorage);
        try {
            $expected = app(SitemapService::class)->xml();
            $this->artisan('app:generate-sitemap')->assertSuccessful();
            $this->assertSame($expected, File::get(storage_path('app/private/seo/sitemap.xml')));
            $this->assertFileDoesNotExist(public_path('sitemap.xml'));
        } finally {
            $this->app->useStoragePath($previousStorage);
            File::deleteDirectory($temporaryStorage);
        }
    }
}
