<?php

namespace Tests\Feature;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\CatalogSiteDomain;
use App\Models\Category;
use App\Models\Good;
use App\Models\User;
use App\Services\Catalog\CatalogHost;
use App\Services\Catalog\CatalogSiteContext;
use App\Services\Catalog\PublicCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogSiteDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_host_sees_only_its_own_goods_and_identical_category_paths_are_independent(): void
    {
        [$food, $fish, $foodGood] = $this->site('Продукты пищевые', 'food.test');
        [$fabrics, $cloth, $fabricGood] = $this->site('Ткани', 'fabric.test');
        $foodSection = CatalogNode::create(['name' => 'Общий адрес food', 'slug' => 'same', 'parent_id' => $food->id, 'is_published' => true]);
        $fabricSection = CatalogNode::create(['name' => 'Общий адрес fabric', 'slug' => 'same', 'parent_id' => $fabrics->id, 'is_published' => true]);
        $this->host('food.test');
        $context = app(CatalogSiteContext::class);

        $this->assertSame($food->id, $context->domainNodeId());
        $this->assertSame([$foodGood->id], $context->scopeGoods(Good::query())->pluck('goods.id')->all());
        $this->assertFalse($context->allowsGood($fabricGood->id));
        $this->assertSame([$fish->id], $context->scopeEntities(Category::query(), 'category')->pluck('categories.id')->all());
        $this->assertNull(app(PublicCatalogService::class)->page($fabrics->id));
        $page = app(PublicCatalogService::class)->pageByPath('assortment');
        $this->assertSame($fish->name, $page['node']['name']);
        $this->assertSame([], $page['breadcrumbs']);
        $this->assertSame(null, $context->visibleNodes()->firstWhere('entity_type', 'category')['parent_id']);

        $this->assertSame($foodSection->id, app(PublicCatalogService::class)->pageByPath('same')['node']['id']);
        $this->host('fabric.test');
        $this->assertSame([$fabricGood->id], $context->scopeGoods(Good::query())->pluck('goods.id')->all());
        $this->assertSame($cloth->name, app(PublicCatalogService::class)->pageByPath($cloth->slug)['node']['name']);
        $this->assertSame($fabricSection->id, app(PublicCatalogService::class)->pageByPath('same')['node']['id']);
        $this->assertNull(app(PublicCatalogService::class)->page($food->id));
        $this->assertFalse($context->allowsGood($foodGood->id));
    }

    public function test_unmatched_hosts_unbound_domains_and_hidden_ancestors_fail_closed(): void
    {
        [$domain, $category, $good] = $this->site('Продукты пищевые', 'food.test');
        $this->host('unknown.test');
        $context = app(CatalogSiteContext::class);
        $this->assertSame([], $context->goodIds());
        $this->assertTrue($context->visibleNodes()->isEmpty());
        $this->host('food.test');
        $category->update(['is_published' => false]);
        $this->assertFalse($context->allowsGood($good->id));
        $category->update(['is_published' => true]);
        $domain->update(['is_published' => false]);
        $this->assertSame([], $context->goodIds());
        $domain->update(['is_published' => true]);
        CatalogSiteDomain::query()->delete();
        $this->assertSame([], $context->goodIds());
    }

    public function test_legacy_installation_without_domain_nodes_continues_to_work(): void
    {
        $good = Good::create(['name' => 'Legacy food', 'is_published' => true]);
        $context = app(CatalogSiteContext::class);
        $this->assertNull($context->goodIds());
        $this->assertTrue($context->allowsGood($good->id));
        $this->assertSame([$good->id], $context->scopeGoods(Good::query())->pluck('id')->all());
    }

    public function test_staff_can_bind_unicode_aliases_but_cannot_reassign_another_sites_hostname(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $level = CatalogLevel::query()->where('is_domain', true)->firstOrFail();
        $created = $this->postJson('/api/catalog/nodes', [
            'level_id' => $level->id, 'name' => 'Продукты пищевые', 'is_published' => true,
            'domain_hosts' => ['ПИЩЕПРОМ-СЕРВЕР.РФ.', 'www.пищепром-сервер.рф'],
        ])->assertCreated();
        $id = $created->json('data.id');
        $ascii = CatalogHost::normalize('пищепром-сервер.рф');
        $created->assertJsonPath('data.domain_hosts.0', $ascii);
        $this->assertDatabaseHas('catalog_site_domains', ['catalog_node_id' => $id, 'hostname' => $ascii]);
        $this->assertSame($ascii, CatalogHost::normalize('ПИЩЕПРОМ-СЕРВЕР.РФ.'));
        $this->host($ascii);
        $this->assertSame($id, app(CatalogSiteContext::class)->domainNodeId());

        $other = $this->postJson('/api/catalog/nodes', ['level_id' => $level->id, 'name' => 'Ткани'])->assertCreated()->json('data.id');
        $this->patchJson('/api/catalog/nodes/'.$other, ['domain_hosts' => ['пищепром-сервер.рф']])
            ->assertUnprocessable()->assertJsonValidationErrors('domain_hosts.0');
        $this->patchJson('/api/catalog/nodes/'.$id, ['domain_hosts' => ['https://food.test/catalog']])
            ->assertUnprocessable()->assertJsonValidationErrors('domain_hosts.0');
        $this->patchJson('/api/catalog/levels/'.$level->id, ['is_domain' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('is_domain');
        $this->patchJson('/api/catalog/nodes/'.$id, ['level_id' => null])->assertUnprocessable()->assertJsonValidationErrors('level_id');
        $this->patchJson('/api/catalog/nodes/'.$id, ['domain_hosts' => []])->assertOk()->assertJsonCount(0, 'data.domain_hosts');
    }

    public function test_food_site_binding_migration_preserves_tree_and_binds_configured_host(): void
    {
        [$food] = $this->site('Продукты пищевые', 'unused.test');
        [$fabrics] = $this->site('Ткани', 'fabric.test');
        $count = CatalogNode::query()->count();
        Schema::drop('catalog_site_domains');
        config()->set('app.url', 'https://production.example.test');
        $migration = require database_path('migrations/2026_10_11_120000_create_catalog_site_domains_table.php');
        $migration->up();

        $this->assertSame($count, CatalogNode::query()->count());
        $this->assertDatabaseHas('catalog_site_domains', ['catalog_node_id' => $food->id, 'hostname' => 'production.example.test']);
        $this->assertDatabaseHas('catalog_site_domains', ['catalog_node_id' => $food->id, 'hostname' => CatalogHost::normalize('пищепром-сервер.рф')]);
        $this->assertDatabaseMissing('catalog_site_domains', ['catalog_node_id' => $fabrics->id]);
    }

    private function site(string $name, string $host): array
    {
        $domain = CatalogNode::create(['name' => $name, 'slug' => 'site', 'level_id' => CatalogLevel::where('is_domain', true)->firstOrFail()->id, 'is_published' => true]);
        CatalogSiteDomain::create(['catalog_node_id' => $domain->id, 'hostname' => $host]);
        $category = Category::create(['name' => $name.' ассортимент', 'slug' => 'assortment', 'is_published' => true]);
        $categoryNode = CatalogNode::create(['parent_id' => $domain->id, 'name' => $category->name, 'entity_type' => 'category', 'entity_id' => $category->id, 'is_published' => true]);
        $good = Good::create(['name' => $name.' товар', 'is_published' => true]);
        CatalogNode::create(['parent_id' => $categoryNode->id, 'name' => $good->name, 'entity_type' => 'good', 'entity_id' => $good->id, 'is_published' => true]);

        return [$domain, $category, $good];
    }

    private function host(string $host): void
    {
        $this->app->instance('request', Request::create('https://'.$host));
    }
}
