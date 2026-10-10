<?php

namespace Tests\Feature;

use App\Models\CatalogLanding;
use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Good;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogLandingContent;
use App\Services\Catalog\CatalogLandingTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogLandingManagementTest extends TestCase
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
        Http::preventStrayRequests();
        config()->set('services.indexnow.key', null);
    }

    public function test_every_landing_endpoint_requires_active_staff(): void
    {
        $node = $this->node();
        foreach (['get' => '', 'put' => '', 'post' => '/publish'] as $method => $suffix) {
            $this->{$method.'Json'}($this->url($node).$suffix, [])->assertUnauthorized();
        }
        $this->postJson($this->url($node).'/unpublish')->assertUnauthorized();
        $this->postJson($this->url($node).'/image')->assertUnauthorized();
        foreach ([['customer', 'active'], ['employee', 'blocked']] as [$type, $status]) {
            $this->actingAs(User::factory()->create(compact('type', 'status')))
                ->getJson($this->url($node))->assertForbidden();
            $this->putJson($this->url($node), [])->assertForbidden();
        }
        $this->staff();
        $this->getJson($this->url($node))->assertOk();
    }

    public function test_reading_an_unassigned_node_offers_templates_without_creating_a_landing(): void
    {
        $this->staff();
        $node = $this->node();
        $response = $this->getJson($this->url($node))->assertOk()
            ->assertJsonPath('data.mode', 'catalog')->assertJsonPath('data.exists', false)
            ->assertJsonPath('data.version', 0)->assertJsonPath('data.draft_content', null)
            ->assertJsonPath('data.effective_visible', true)->assertJsonCount(2, 'data.templates');
        $this->assertSame($node->name, $response->json('data.templates.0.content.hero.title'));
        $this->assertStringContainsString('/catalog/', $response->json('data.public_url'));
        $this->assertDatabaseCount('catalog_landings', 0);
    }

    public function test_saved_drafts_publications_and_disable_are_distinct_snapshots(): void
    {
        $this->staff();
        $node = $this->node();
        $content = $this->content($node);
        $this->putJson($this->url($node), ['version' => 0, 'content' => $content])->assertOk()
            ->assertJsonPath('data.version', 1)->assertJsonPath('data.published_content', null)
            ->assertJsonPath('data.activated_at', null)
            ->assertJsonPath('data.has_changes', true);
        $this->postJson($this->url($node).'/publish', ['version' => 1])->assertOk()
            ->assertJsonPath('data.version', 2)->assertJsonPath('data.has_changes', false)
            ->assertJsonPath('data.published_content.hero.title', $node->name);
        $activatedAt = CatalogLanding::firstOrFail()->activated_at;
        $this->assertNotNull($activatedAt);
        $this->travel(1)->hour();
        $content['hero']['title'] = 'Новая редакция';
        $this->putJson($this->url($node), ['version' => 2, 'content' => $content])->assertOk()
            ->assertJsonPath('data.version', 3)->assertJsonPath('data.draft_content.hero.title', 'Новая редакция')
            ->assertJsonPath('data.published_content.hero.title', $node->name)->assertJsonPath('data.has_changes', true);
        $this->postJson($this->url($node).'/publish', ['version' => 3])->assertOk()
            ->assertJsonPath('data.published_content.hero.title', 'Новая редакция')->assertJsonPath('data.has_changes', false);
        $this->postJson($this->url($node).'/unpublish', ['version' => 4])->assertOk()
            ->assertJsonPath('data.exists', true)->assertJsonPath('data.version', 5)
            ->assertJsonPath('data.published_content', null)->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.draft_content.hero.title', 'Новая редакция');
        $this->assertDatabaseCount('catalog_landings', 1);
        $this->assertTrue($node->fresh()->is_published);
        $this->assertSame(auth()->id(), CatalogLanding::firstOrFail()->updated_by);
        $this->assertTrue($activatedAt->equalTo(CatalogLanding::firstOrFail()->activated_at));
    }

    public function test_stale_first_saves_and_publication_actions_cannot_overwrite_newer_work(): void
    {
        $this->staff();
        $node = $this->node();
        $content = $this->content($node);
        $this->putJson($this->url($node), ['version' => 0, 'content' => $content])->assertOk();
        $content['hero']['title'] = 'Устаревшая форма';
        $this->putJson($this->url($node), ['version' => 0, 'content' => $content])->assertConflict();
        $this->postJson($this->url($node).'/publish', ['version' => 0])->assertConflict();
        $this->postJson($this->url($node).'/unpublish', ['version' => 0])->assertConflict();
        $this->assertSame(1, CatalogLanding::firstOrFail()->version);
        $this->assertSame($node->name, CatalogLanding::firstOrFail()->draft_content['hero']['title']);
        $this->assertNull(CatalogLanding::firstOrFail()->published_content);
    }

    public function test_publishing_without_a_saved_draft_does_not_create_a_record(): void
    {
        $this->staff();
        $node = $this->node();
        $this->postJson($this->url($node).'/publish', ['version' => 0])->assertUnprocessable();
        $this->postJson($this->url($node).'/unpublish', ['version' => 0])->assertUnprocessable();
        $this->assertDatabaseCount('catalog_landings', 0);
    }

    public function test_landing_follows_the_node_across_levels_and_reports_hidden_ancestors(): void
    {
        $this->staff();
        $parent = $this->node(['name' => 'Раздел', 'slug' => 'section', 'is_published' => false]);
        $node = $this->node(['parent_id' => $parent->id]);
        $this->putJson($this->url($node), ['version' => 0, 'content' => $this->content($node)])->assertOk()
            ->assertJsonPath('data.effective_visible', false)->assertJsonPath('data.visibility_reason', 'hidden_ancestor');
        $level = CatalogLevel::create(['name' => 'Новый уровень', 'entity_type' => 'custom']);
        $node->update(['level_id' => $level->id, 'parent_id' => null]);
        $this->getJson($this->url($node))->assertOk()->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.effective_visible', true)->assertJsonPath('data.visibility_reason', null);
        $node->delete();
        $this->assertDatabaseCount('catalog_landings', 0);
    }

    public function test_goods_in_multiple_branches_share_the_existing_good_page(): void
    {
        $this->staff();
        $good = Good::create(['name' => 'Общий товар', 'slug' => 'shared', 'is_published' => true]);
        $hiddenParent = $this->node(['is_published' => false]);
        $one = $this->node(['entity_type' => 'good', 'entity_id' => $good->id]);
        $two = $this->node(['entity_type' => 'good', 'entity_id' => $good->id, 'parent_id' => $hiddenParent->id]);
        foreach ([$one, $two] as $node) {
            $this->getJson($this->url($node))->assertOk()->assertJsonPath('data.mode', 'good')
                ->assertJsonPath('data.good_id', $good->id)->assertJsonPath('data.public_url', route('public.goods.show', 'shared'))
                ->assertJsonPath('data.effective_visible', true)->assertJsonPath('data.visibility_reason', null);
            $this->putJson($this->url($node), ['version' => 0, 'content' => $this->content($node)])
                ->assertUnprocessable()->assertJsonValidationErrors('node');
            $this->postJson($this->url($node).'/publish', ['version' => 0])->assertUnprocessable();
            $this->postJson($this->url($node).'/image')->assertUnprocessable();
        }
        $this->assertDatabaseCount('catalog_landings', 0);
    }

    #[DataProvider('invalidContent')]
    public function test_content_rejects_unknown_or_unsafe_structures(string $path, mixed $value): void
    {
        $this->staff();
        $node = $this->node();
        $content = $this->content($node);
        data_set($content, $path, $value);
        $this->putJson($this->url($node), ['version' => 0, 'content' => $content])->assertUnprocessable();
        $this->assertDatabaseCount('catalog_landings', 0);
    }

    public static function invalidContent(): array
    {
        return [
            'javascript image' => ['hero.image', 'javascript:alert(1)'],
            'protocol relative image' => ['hero.image', '//evil.test/image.jpg'],
            'backslash url' => ['hero.actionUrl', '/\\evil.test'],
            'newline url' => ['hero.actionUrl', "java\nscript:alert(1)"],
            'data url' => ['contact.actionUrl', 'data:text/html,test'],
            'arbitrary html key' => ['hero.html', '<script>alert(1)</script>'],
            'unsupported template' => ['template', 'unsafe-vue'],
            'unknown block' => ['blocks', [['id' => 'one', 'type' => 'raw-html', 'title' => '', 'navTitle' => '', 'enabled' => true, 'data' => []]]],
            'array block type' => ['blocks', [['type' => ['text']]]],
            'scalar blocks' => ['blocks', 'bad'],
            'invalid blocks object' => ['blocks', ['custom' => []]],
            'unknown block data' => ['blocks', [['id' => 'one', 'type' => 'text', 'title' => '', 'navTitle' => '', 'enabled' => true, 'data' => ['script' => 'bad']]]],
            'boolean string' => ['catalog.enabled', 'false'],
            'invalid source' => ['catalog.source_product_ids', [-1]],
            'duplicate source' => ['catalog.source_product_ids', [1, 1]],
            'unbounded ids' => ['catalog.good_ids', range(1, 101)],
            'unsafe nested source' => ['sources.items', [['title' => 'Источник', 'url' => 'javascript:alert(1)']]],
            'invalid numeric range' => ['blocks', [['id' => 'chart', 'type' => 'glaze', 'title' => '', 'navTitle' => '', 'enabled' => true, 'data' => ['primaryPercent' => 101]]]],
            'reserved page anchor' => ['blocks', [['id' => 'hero-title', 'type' => 'text', 'title' => '', 'navTitle' => '', 'enabled' => true, 'data' => []]]],
            'duplicate faq anchor' => ['blocks', [['id' => 'faq', 'type' => 'faq', 'title' => '', 'navTitle' => '', 'enabled' => true, 'data' => ['items' => [['id' => 'faq', 'question' => 'Вопрос?']]]]]],
            'reserved source anchor' => ['sources.items', [['id' => 'catalog', 'title' => 'Источник']]],
        ];
    }

    public function test_literal_text_is_preserved_and_all_shipped_templates_validate(): void
    {
        $this->staff();
        $node = $this->node();
        foreach (app(CatalogLandingTemplates::class)->forNode($node) as $template) {
            app(CatalogLandingContent::class)->validate($template['content']);
        }
        $mackerel = app(CatalogLandingTemplates::class)->named('mackerel');
        $this->assertNotNull($mackerel);
        app(CatalogLandingContent::class)->validate($mackerel);
        $content = $this->content($node);
        $content['hero']['description'] = '5 < 10; <script>literal text</script>';
        $this->putJson($this->url($node), ['version' => 0, 'content' => $content])->assertOk()
            ->assertJsonPath('data.draft_content.hero.description', $content['hero']['description']);
    }

    public function test_empty_table_cells_are_preserved_without_weakening_integer_lists(): void
    {
        $this->staff();
        $node = $this->node();
        $content = $this->content($node);
        $content['blocks'] = [[
            'id' => 'comparison', 'type' => 'table', 'title' => 'Сравнение', 'navTitle' => '', 'enabled' => true,
            'data' => ['headers' => ['Показатель', 'Значение'], 'rows' => [
                ['cells' => ['Без значения', '']], ['cells' => [null, 'Значение']],
            ]],
        ]];
        $this->putJson($this->url($node), ['version' => 0, 'content' => $content])->assertOk()
            ->assertJsonPath('data.draft_content.blocks.0.data.rows.0.cells', ['Без значения', ''])
            ->assertJsonPath('data.draft_content.blocks.0.data.rows.1.cells', ['', 'Значение']);
        $this->postJson($this->url($node).'/publish', ['version' => 1])->assertOk()
            ->assertJsonPath('data.published_content.blocks.0.data.rows.0.cells', ['Без значения', '']);
        $content['catalog']['good_ids'] = [''];
        $this->putJson($this->url($node), ['version' => 2, 'content' => $content])->assertUnprocessable()
            ->assertJsonValidationErrors('content.catalog.good_ids.0');
    }

    public function test_image_upload_does_not_change_catalog_avatar_or_publish_draft(): void
    {
        Storage::fake('public');
        $this->staff();
        $node = $this->node(['image' => '/storage/avatar.jpg']);
        $response = $this->postJson($this->url($node).'/image', ['image' => UploadedFile::fake()->image('hero.jpg')])
            ->assertCreated()->assertJsonStructure(['url']);
        $this->assertStringContainsString('/catalog-landings/'.$node->id.'/', $response->json('url'));
        $this->assertSame('/storage/avatar.jpg', $node->fresh()->image);
        $this->assertDatabaseCount('catalog_landings', 0);
        $this->postJson($this->url($node).'/image', ['image' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])
            ->assertUnprocessable();
    }

    public function test_migration_preserves_mackerel_assortment_and_existing_seo(): void
    {
        $product = Product::forceCreate(['id' => 124, 'rus' => 'Скумбрия', 'is_published' => true]);
        $node = CatalogNode::forceCreate(['id' => 160, 'name' => 'Скумбрия', 'entity_type' => 'product', 'entity_id' => $product->id,
            'slug' => 'skumbriia', 'is_published' => true, 'meta_title' => 'Редакторский заголовок']);
        Schema::drop('catalog_landings');
        $migration = require database_path('migrations/2026_10_10_120000_create_catalog_landings_table.php');
        $migration->up();
        $landing = CatalogLanding::where('catalog_node_id', $node->id)->firstOrFail();
        $this->assertSame([201], $landing->published_content['catalog']['source_product_ids']);
        $this->assertSame([75, 104], $landing->published_content['catalog']['inline_good_ids']);
        $this->assertSame($landing->draft_content, $landing->published_content);
        $this->assertNotNull($landing->activated_at);
        $this->assertSame('Редакторский заголовок', $node->fresh()->meta_title);
        $this->assertSame(config('product-pages.pages.124.description'), $node->fresh()->meta_description);
    }

    public function test_first_legacy_draft_preserves_the_published_page_and_migrates_missing_seo(): void
    {
        $this->staff();
        $node = $this->legacyNode();
        $response = $this->getJson($this->url($node))->assertOk()
            ->assertJsonPath('data.exists', true)->assertJsonPath('data.legacy', true)
            ->assertJsonPath('data.version', 0)->assertJsonPath('data.has_changes', false)
            ->assertJsonPath('data.published_content.catalog.source_product_ids', [201]);
        $this->assertDatabaseCount('catalog_landings', 0);
        $this->assertNull($node->fresh()->meta_title);
        $original = $response->json('data.published_content');
        $draft = $original;
        $draft['hero']['title'] = 'Пока только черновик';
        // The card may have saved its authoritative SEO after the editor loaded.
        $node->update(['meta_description' => 'Новое описание из карточки']);
        $this->putJson($this->url($node), ['version' => 0, 'content' => $draft])->assertOk()
            ->assertJsonPath('data.legacy', false)->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.has_changes', true)->assertJsonPath('data.published_content', $original)
            ->assertJsonPath('data.draft_content.hero.title', 'Пока только черновик');
        $this->assertNotNull(CatalogLanding::firstOrFail()->activated_at);
        $this->assertSame(config('product-pages.pages.124.title'), $node->fresh()->meta_title);
        $this->assertSame('Новое описание из карточки', $node->fresh()->meta_description);
    }

    public function test_unpublishing_virtual_legacy_landing_keeps_its_content_and_activation(): void
    {
        $this->staff();
        $node = $this->legacyNode();
        $this->postJson($this->url($node).'/unpublish', ['version' => 0])->assertOk()
            ->assertJsonPath('data.exists', true)->assertJsonPath('data.legacy', false)
            ->assertJsonPath('data.version', 1)->assertJsonPath('data.published_content', null)
            ->assertJsonPath('data.draft_content.catalog.source_product_ids', [201]);
        $this->assertNotNull(CatalogLanding::firstOrFail()->activated_at);
        $this->getJson($this->url($node))->assertOk()->assertJsonPath('data.published_content', null);
    }

    private function legacyNode(): CatalogNode
    {
        $product = Product::forceCreate(['id' => 124, 'rus' => 'Скумбрия', 'is_published' => true]);

        return CatalogNode::forceCreate(['id' => 160, 'name' => 'Скумбрия', 'entity_type' => 'product', 'entity_id' => $product->id,
            'slug' => 'skumbriia', 'is_published' => true]);
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    private function node(array $attributes = []): CatalogNode
    {
        return CatalogNode::create(['name' => 'Раздел каталога', 'slug' => 'section', 'is_published' => true, ...$attributes]);
    }

    private function content(CatalogNode $node): array
    {
        return app(CatalogLandingTemplates::class)->forNode($node)[0]['content'];
    }

    private function url(CatalogNode $node): string
    {
        return '/api/catalog/nodes/'.$node->id.'/landing';
    }
}
