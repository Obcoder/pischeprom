<?php

namespace Tests\Feature;

use App\Models\CatalogLanding;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogLandingTemplates;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\PublicClassPage;
use App\Services\Seo\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesClassCatalog;
use Tests\TestCase;

class PublicCatalogLandingTest extends TestCase
{
    use CreatesClassCatalog;
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
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
    }

    public function test_any_unclassified_node_renders_published_content_and_current_card_seo_without_exposing_its_draft(): void
    {
        $node = $this->node('Бакалея', ['meta_title' => 'Оптовая бакалея', 'meta_description' => 'Поставки продуктов']);
        $landing = $this->landing($node);
        $draft = $landing->draft_content;
        $draft['hero']['title'] = 'Только для редактора';
        $landing->update(['draft_content' => $draft]);
        $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.classPage.content.hero.title', 'Бакалея')
            ->assertJsonPath('props.classPage.seo.title', 'Оптовая бакалея')
            ->assertJsonPath('props.classPage.seo.description', 'Поставки продуктов')
            ->assertDontSee('Только для редактора');
        $node->update(['meta_title' => 'Заголовок из карточки']);
        $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.seo.title', 'Заголовок из карточки');
    }

    public function test_branch_assortment_deduplicates_goods_and_excludes_hidden_descendant_branches(): void
    {
        $node = $this->node('Каталог');
        $visible = $this->node('Видимая ветка', ['parent_id' => $node->id]);
        $hidden = $this->node('Скрытая ветка', ['parent_id' => $node->id, 'is_published' => false]);
        $good = Good::create(['name' => 'Общий товар', 'slug' => 'shared-good', 'is_published' => true]);
        $private = Good::create(['name' => 'Товар скрытой ветки', 'slug' => 'private-branch-good', 'is_published' => true]);
        foreach ([$node, $visible] as $parent) {
            $this->node('Размещение', ['parent_id' => $parent->id, 'entity_type' => 'good', 'entity_id' => $good->id]);
        }
        $this->node('Скрытое размещение', ['parent_id' => $hidden->id, 'entity_type' => 'good', 'entity_id' => $private->id]);
        $this->landing($node);
        $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonCount(1, 'props.classPage.goods')->assertJsonPath('props.classPage.goods.0.id', $good->id)
            ->assertJsonPath('props.seo.jsonLd.0.mainEntity.numberOfItems', 1)->assertDontSee('private-branch-good');
        $good->update(['is_published' => false]);
        $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()->assertJsonCount(0, 'props.classPage.goods');
    }

    public function test_explicit_selection_preserves_source_assignments_and_uses_only_published_goods(): void
    {
        $node = $this->node('Редакционная подборка');
        $source = Product::create(['rus' => 'Источник', 'is_published' => true]);
        $first = Good::create(['name' => 'Из источника', 'is_published' => true]);
        $second = Good::create(['name' => 'Выбран вручную', 'is_published' => true]);
        $private = Good::create(['name' => 'Неопубликован', 'is_published' => false]);
        $first->products()->attach($source);
        $landing = $this->landing($node);
        $content = $landing->published_content;
        $content['catalog'] = [...$content['catalog'], 'mode' => 'selection',
            'source_product_ids' => [$source->id], 'good_ids' => [$first->id, $second->id, $private->id],
            'inline_good_ids' => [$first->id, $private->id]];
        $landing->update(['published_content' => $content]);
        $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonCount(2, 'props.classPage.goods')->assertJsonMissingPath('props.classPage.inlineGoods.'.$private->id);
        $this->assertDatabaseCount('good_product', 1);
        $content['catalog']['enabled'] = false;
        $landing->update(['published_content' => $content]);
        $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonCount(0, 'props.classPage.goods')->assertJsonPath('props.seo.jsonLd.0.mainEntity.numberOfItems', 0);
    }

    public function test_staff_preview_uses_saved_draft_even_for_a_hidden_node_and_is_not_cacheable(): void
    {
        $node = $this->node('Скрытый раздел', ['is_published' => false]);
        $landing = $this->landing($node);
        $draft = $landing->draft_content;
        $draft['hero']['title'] = 'Черновик лендинга';
        $landing->update(['draft_content' => $draft]);
        $url = '/Ameise/catalog/nodes/'.$node->id.'/landing/preview';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']))
            ->get($url, ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.classPage.preview', true)
            ->assertJsonPath('props.classPage.content.hero.title', 'Черновик лендинга')
            ->assertJsonPath('props.seo.robots', 'noindex,nofollow')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get($this->url($node))->assertNotFound();
        $this->assertStringNotContainsString($this->url($node), app(SitemapService::class)->xml());
    }

    public function test_disabled_mackerel_landing_returns_regular_page_and_keeps_legacy_redirects(): void
    {
        $product = Product::forceCreate(['id' => 124, 'rus' => 'Скумбрия', 'is_published' => true]);
        $node = $this->createClassCatalog($product);
        $landing = $this->landing($node);
        $landing->update(['published_content' => null]);
        $this->get($this->classUrl(), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.classPage', null);
        $this->get('/p/124')->assertStatus(301)->assertRedirect($this->classUrl());
        $this->get('/catalog/160/skumbriia')->assertStatus(301)->assertRedirect($this->classUrl());
        $this->assertStringNotContainsString($this->classUrl(), app(SitemapService::class)->xml());
        $this->assertCount(0, app(PublicClassPage::class)->publishedCatalogPages());
        CatalogNode::whereKey($node->parent_id)->update(['is_published' => false]);
        // Category-backed nodes read publication from their source Category.
        $product->update(['is_published' => false]);
        $this->get('/p/124')->assertNotFound();
    }

    public function test_sitemap_and_discovery_follow_publication_ancestors_and_level_changes(): void
    {
        $parent = $this->node('Раздел');
        $node = $this->node('Группа', ['parent_id' => $parent->id]);
        $landing = $this->landing($node);
        $this->assertStringContainsString($this->url($node), app(SitemapService::class)->xml());
        $this->assertSame([$node->id], app(PublicClassPage::class)->publishedCatalogPages()->keys()->all());
        $parent->update(['is_published' => false]);
        $this->get($this->url($node))->assertNotFound();
        $this->assertCount(0, app(PublicClassPage::class)->publishedCatalogPages());
        $parent->update(['is_published' => true]);
        $node->update(['parent_id' => null]);
        $this->assertSame($landing->id, CatalogLanding::where('catalog_node_id', $node->id)->sole()->id);
        $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.classPage.seo.canonical', $this->url($node));
    }

    public function test_entity_routes_switch_only_after_first_publication_and_keep_one_canonical_after_disabling(): void
    {
        $category = Category::create(['name' => 'Бакалея', 'slug' => 'bakaleia', 'is_published' => true]);
        $product = Product::create(['rus' => 'Крупы', 'is_published' => true]);
        foreach ([['category', $category, 'category.show', $category->slug], ['product', $product, 'shop.products.show', $product->id]] as [$type, $entity, $route, $key]) {
            $node = $this->node('Раздел '.$type, ['entity_type' => $type, 'entity_id' => $entity->id]);
            $landing = $this->landing($node);
            $content = $landing->published_content;
            $landing->update(['activated_at' => null, 'published_content' => null, 'published_at' => null]);
            $this->get(route($route, $key), ['X-Inertia' => 'true'])->assertOk();
            $landing->update(['activated_at' => now(), 'published_content' => $content, 'published_at' => now()]);
            $this->get(route($route, $key))->assertStatus(301)->assertRedirect($this->url($node));
            $this->assertStringNotContainsString(route($route, $key), app(SitemapService::class)->xml());
            $landing->update(['published_content' => null, 'published_at' => null]);
            $this->get(route($route, $key))->assertStatus(301)->assertRedirect($this->url($node));
            $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.classPage', null);
        }
    }

    private function node(string $name, array $attributes = []): CatalogNode
    {
        return CatalogNode::create(['name' => $name, 'is_published' => true, ...$attributes]);
    }

    private function landing(CatalogNode $node): CatalogLanding
    {
        $content = app(CatalogLandingTemplates::class)->forNode($node)[0]['content'];

        return CatalogLanding::create(['catalog_node_id' => $node->id, 'draft_content' => $content,
            'published_content' => $content, 'version' => 1, 'published_at' => now(), 'activated_at' => now()]);
    }

    private function url(CatalogNode $node): string
    {
        return app(CatalogService::class)->nodePayload($node)['public_url'];
    }
}
