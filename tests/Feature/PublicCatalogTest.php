<?php

namespace Tests\Feature;

use App\Models\CatalogField;
use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\PublicCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
    }

    public function test_guests_can_browse_arbitrary_levels_and_only_explicitly_public_properties(): void
    {
        $root = $this->node('Ингредиенты');
        $node = $this->node('Пищевые волокна', [
            'parent_id' => $root->id,
            'description' => 'Волокна для пищевой промышленности.',
            'h1' => 'Пищевые волокна для производства',
            'meta_title' => 'Пищевые волокна оптом',
            'meta_description' => 'Описание для поиска',
            'properties' => ['origin' => 'Россия', 'margin' => 'private-margin-sentinel', 'unknown' => 'unregistered-value-sentinel'],
        ]);
        $child = $this->node('Цитрусовые волокна', ['parent_id' => $node->id]);
        $this->node('Закрытый раздел', ['parent_id' => $node->id, 'is_published' => false]);
        CatalogField::query()->create(['level_id' => $node->level_id, 'key' => 'origin', 'label' => 'Происхождение', 'type' => 'text', 'is_public' => true]);
        CatalogField::query()->create(['level_id' => $node->level_id, 'key' => 'margin', 'label' => 'Наценка', 'type' => 'text']);

        $response = $this->get($this->url($node), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('component', 'Catalog/Show')
            ->assertJsonPath('props.node.name', 'Пищевые волокна')
            ->assertJsonPath('props.seo.h1', 'Пищевые волокна для производства')
            ->assertJsonPath('props.breadcrumbs.0.id', $root->id)
            ->assertJsonPath('props.children.0.id', $child->id)
            ->assertJsonCount(1, 'props.children')
            ->assertJsonCount(1, 'props.properties')
            ->assertJsonPath('props.properties.0.value', 'Россия')
            ->assertJsonPath('props.seo.title', 'Пищевые волокна оптом')
            ->assertJsonPath('props.seo.description', 'Описание для поиска')
            ->assertJsonMissingPath('props.node.properties')
            ->assertJsonMissingPath('props.node.entity_id')
            ->assertJsonMissingPath('props.node.edit_url');

        foreach (['private-margin-sentinel', 'unregistered-value-sentinel', 'Закрытый раздел'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        Http::assertNothingSent();
    }

    public function test_unpublished_ancestors_hide_descendants_and_featured_cards(): void
    {
        $root = $this->node('Черновик', ['is_published' => false]);
        $middle = $this->node('Раздел', ['parent_id' => $root->id]);
        $child = $this->node('Скрытый товар', ['parent_id' => $middle->id, 'is_featured' => true]);
        $visible = $this->node('Открытый раздел', ['is_featured' => true]);
        $this->node('Вне витрины');

        $this->get($this->url($child))->assertNotFound();
        $this->get(route('public.catalog.show', ['node' => $child->id]))->assertNotFound();
        $this->assertSame([$visible->id], array_column(app(PublicCatalogService::class)->showcase(), 'id'));
        $this->get('/', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonCount(1, 'props.catalogShowcase')
            ->assertJsonPath('props.catalogShowcase.0.id', $visible->id);
    }

    public function test_canonical_source_publication_takes_effect_without_reimport(): void
    {
        $category = Category::query()->create(['name' => 'Категория источника', 'is_published' => true, 'is_featured' => true]);
        $node = $this->node('Устаревшее имя', ['entity_type' => 'category', 'entity_id' => $category->id]);
        $child = $this->node('Вложенный раздел', ['parent_id' => $node->id, 'is_featured' => true]);

        $this->assertNotNull(app(PublicCatalogService::class)->page($child->id));
        $this->assertContains($node->id, array_column(app(PublicCatalogService::class)->showcase(), 'id'));
        $category->update(['is_published' => false]);

        $this->get($this->url($child))->assertNotFound();
        $this->assertSame([], app(PublicCatalogService::class)->showcase());
    }

    public function test_missing_sources_and_cycles_fail_closed(): void
    {
        $missing = $this->node('Удалённый источник', ['entity_type' => 'good', 'entity_id' => 999999, 'is_featured' => true]);
        $first = $this->node('Первый', ['is_featured' => true]);
        $second = $this->node('Второй', ['parent_id' => $first->id, 'is_featured' => true]);
        $first->update(['parent_id' => $second->id]);

        foreach ([$missing, $first, $second] as $node) {
            $this->get($this->url($node))->assertNotFound();
        }
        $this->assertSame([], app(PublicCatalogService::class)->showcase());
    }

    public function test_alias_redirect_and_good_children_preserve_access_to_the_purchase_landing(): void
    {
        $node = $this->node('Новый раздел');
        $this->get(route('public.catalog.show', ['node' => $node->id, 'slug' => 'old-slug']))
            ->assertStatus(301)->assertRedirect($this->url($node));

        $good = Good::query()->create(['name' => 'Товар для заказа', 'is_published' => true]);
        $linked = $this->node('Товар', ['entity_type' => 'good', 'entity_id' => $good->id, 'properties' => ['origin' => 'Россия']]);
        $child = $this->node('Вложенный уровень товара', ['parent_id' => $linked->id]);
        CatalogField::query()->create(['level_id' => $linked->level_id, 'key' => 'origin', 'label' => 'Происхождение', 'type' => 'text', 'is_public' => true]);
        $url = route('public.catalog.show', ['node' => $linked->id, 'slug' => $good->slug]);
        $this->get($url, ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.node.offer_url', route('public.goods.show', $good->slug))
            ->assertJsonPath('props.children.0.id', $child->id)
            ->assertJsonPath('props.properties.0.value', 'Россия');
    }

    public function test_public_reads_do_not_import_or_publish_legacy_entities(): void
    {
        $node = $this->node('Открытый раздел', ['is_featured' => true]);
        Category::query()->create(['name' => 'Ещё не импортированная категория', 'is_published' => true]);
        $count = CatalogNode::query()->count();

        $this->get($this->url($node))->assertOk();
        app(PublicCatalogService::class)->showcase();

        $this->assertSame($count, CatalogNode::query()->count());
    }

    public function test_native_product_landing_respects_the_publication_switch(): void
    {
        $product = Product::query()->create(['rus' => 'Продукт на сайте', 'is_published' => false]);
        $this->get('/p/'.$product->id)->assertNotFound();

        $product->update(['is_published' => true]);
        $this->get('/p/'.$product->id, ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('component', 'Products/Show')
            ->assertJsonPath('props.product.id', $product->id);
    }

    public function test_human_paths_follow_ancestors_without_the_structural_domain_prefix(): void
    {
        $domainLevel = CatalogLevel::query()->create(['name' => 'Домен', 'is_domain' => true]);
        $domain = $this->node('Продукты пищевые', ['level_id' => $domainLevel->id, 'slug' => 'produkty-pishhevye']);
        $category = Category::query()->create(['name' => 'Рыба', 'slug' => 'ryba', 'is_published' => true]);
        $fish = $this->node('Устаревшее имя', [
            'parent_id' => $domain->id, 'entity_type' => 'category', 'entity_id' => $category->id, 'slug' => 'obsolete',
        ]);
        $mackerel = $this->node('Скумбрия', ['parent_id' => $fish->id, 'slug' => 'skumbriia', 'is_featured' => true]);
        $frozen = $this->node('Замороженная', ['parent_id' => $mackerel->id, 'slug' => 'zamorozhennaia']);
        $hiddenCategory = Category::query()->create(['name' => 'Закрытая', 'is_published' => false]);
        $this->node('Закрытая', ['entity_type' => 'category', 'entity_id' => $hiddenCategory->id]);
        $canonical = url('/catalog/ryba/skumbriia');

        $this->assertSame(url('/catalog/produkty-pishhevye'), $this->url($domain));
        $this->assertSame($canonical, $this->url($mackerel));
        $this->assertSame($canonical.'/zamorozhennaia', $this->url($frozen));
        $this->assertSame($canonical, app(CatalogService::class)->nodePayload($mackerel)['public_url']);
        $this->assertSame([$category->id => url('/catalog/ryba')], app(PublicCatalogService::class)->categoryUrls());

        $this->get($canonical, ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('component', 'Catalog/Show')
            ->assertJsonPath('props.node.id', $mackerel->id)
            ->assertJsonPath('props.seo.canonical', $canonical)
            ->assertJsonPath('props.breadcrumbs.1.public_url', url('/catalog/ryba'))
            ->assertJsonPath('props.children.0.public_url', $canonical.'/zamorozhennaia')
            ->assertJsonPath('props.publicCategoryUrls.'.$category->id, url('/catalog/ryba'))
            ->assertJsonMissingPath('props.publicCategoryUrls.'.$hiddenCategory->id)
            ->assertJsonMissingPath('props.node.catalog_path');

        foreach ([null, 'skumbriia', 'old-slug'] as $slug) {
            $this->get(route('public.catalog.show', ['node' => $mackerel->id, 'slug' => $slug]))
                ->assertStatus(301)->assertRedirect($canonical);
        }
        $this->get('/catalog/produkty-pishhevye/ryba/skumbriia')->assertNotFound();
        $this->get('/catalog/ryba/skumbriia/unrelated')->assertNotFound();

        $domain->update(['is_published' => false]);
        $this->get($canonical)->assertNotFound();
        $this->get(route('public.catalog.show', ['node' => $mackerel->id]))->assertNotFound();
        $this->assertSame([], app(PublicCatalogService::class)->categoryUrls());
    }

    public function test_duplicate_paths_are_disambiguated_before_descendants_and_remain_stable_when_hidden(): void
    {
        $first = $this->node('Рыба', ['slug' => 'fish']);
        $second = $this->node('Другая рыба', ['slug' => 'fish']);
        $firstChild = $this->node('Скумбрия', ['parent_id' => $first->id, 'slug' => 'mackerel']);
        $secondChild = $this->node('Скумбрия', ['parent_id' => $second->id, 'slug' => 'mackerel']);
        // A literal slug may already use the suffix chosen for another node.
        $literal = $this->node('Буквальный суффикс', ['slug' => 'fish-node-'.$first->id]);

        $this->assertSame(url('/catalog/fish-node-'.$first->id), $this->url($first));
        $this->assertSame(url('/catalog/fish-node-'.$second->id), $this->url($second));
        $this->assertSame(url('/catalog/fish-node-'.$first->id.'-node-'.$literal->id), $this->url($literal));
        $this->assertSame($this->url($first).'/mackerel', $this->url($firstChild));
        $this->assertSame($this->url($second).'/mackerel', $this->url($secondChild));
        $this->get('/catalog/fish')->assertNotFound();

        foreach ([$first, $second, $firstChild, $secondChild, $literal] as $node) {
            $this->get($this->url($node), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.node.id', $node->id);
        }

        $canonical = $this->url($firstChild);
        $second->update(['is_published' => false]);
        $this->assertSame($canonical, $this->url($firstChild));
        $this->get($this->url($secondChild))->assertNotFound();
        $this->get(route('public.catalog.show', ['node' => $secondChild->id]))->assertNotFound();
    }

    public function test_duplicate_sibling_and_domain_omitted_paths_never_resolve_to_an_arbitrary_node(): void
    {
        $domainLevel = CatalogLevel::query()->create(['name' => 'Домен', 'is_domain' => true]);
        $domain = $this->node('Домен', ['level_id' => $domainLevel->id, 'slug' => 'domain']);
        $root = $this->node('Рыба без домена', ['slug' => 'fish']);
        $otherRoot = $this->node('Рыба под доменом', ['slug' => 'fish', 'parent_id' => $domain->id]);
        $first = $this->node('Один раздел', ['slug' => 'same', 'parent_id' => $root->id]);
        $second = $this->node('Другой раздел', ['slug' => 'same', 'parent_id' => $root->id]);

        $this->assertSame(url('/catalog/fish-node-'.$otherRoot->id), $this->url($otherRoot));
        $this->assertSame($this->url($root).'/same-node-'.$first->id, $this->url($first));
        $this->assertSame($this->url($root).'/same-node-'.$second->id, $this->url($second));
        $this->get($this->url($root).'/same')->assertNotFound();
        $this->get($this->url($first), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.node.id', $first->id);
        $this->get($this->url($second), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.node.id', $second->id);
    }

    public function test_numeric_root_slug_cannot_shadow_the_legacy_id_route(): void
    {
        $root = $this->node('Числовой корень', ['slug' => '160']);
        $child = $this->node('Числовой потомок', ['parent_id' => $root->id, 'slug' => '201']);

        $this->assertSame(url('/catalog/160-node-'.$root->id), $this->url($root));
        $this->assertSame($this->url($root).'/201', $this->url($child));
        $this->get($this->url($child), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.node.id', $child->id);
        $this->get(route('public.catalog.show', ['node' => $child->id, 'slug' => $child->slug]))
            ->assertStatus(301)->assertRedirect($this->url($child));
    }

    public function test_source_ancestor_rename_and_node_move_change_all_generated_urls_without_reimport(): void
    {
        $category = Category::query()->create(['name' => 'Рыба', 'slug' => 'ryba', 'is_published' => true]);
        $root = $this->node('Рыба', ['entity_type' => 'category', 'entity_id' => $category->id]);
        $child = $this->node('Скумбрия', ['parent_id' => $root->id, 'slug' => 'skumbriia']);
        $category->update(['slug' => 'fish']);

        $this->assertSame(url('/catalog/fish/skumbriia'), app(CatalogService::class)->nodePayload($child)['public_url']);
        $this->get(route('public.catalog.show', ['node' => $child->id, 'slug' => $child->slug]))
            ->assertRedirect(url('/catalog/fish/skumbriia'));

        $other = $this->node('Другая категория', ['slug' => 'other']);
        $child->update(['parent_id' => $other->id]);
        $this->assertSame(url('/catalog/other/skumbriia'), app(CatalogService::class)->nodePayload($child)['public_url']);
        $this->get('/catalog/other/skumbriia', ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.node.id', $child->id);
    }

    private function node(string $name, array $attributes = []): CatalogNode
    {
        $level = CatalogLevel::query()->firstOrCreate(['name' => 'Произвольный уровень', 'entity_type' => 'custom']);

        return CatalogNode::query()->create([
            'level_id' => $level->id,
            'name' => $name,
            'slug' => 'node-'.(CatalogNode::query()->max('id') + 1),
            'is_published' => true,
            'is_featured' => false,
            'sort_order' => 0,
            ...$attributes,
        ]);
    }

    private function url(CatalogNode $node): string
    {
        return app(CatalogService::class)->nodes()->firstWhere('id', $node->id)['public_url'];
    }
}
