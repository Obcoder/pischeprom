<?php

namespace Tests\Feature;

use App\Models\CatalogField;
use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
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
        return route('public.catalog.show', ['node' => $node->id, 'slug' => $node->slug]);
    }
}
