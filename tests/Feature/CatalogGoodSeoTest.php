<?php

namespace Tests\Feature;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Good;
use App\Models\GoodSeo;
use App\Models\User;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\PublicCatalogService;
use App\Services\Seo\GoodSeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogGoodSeoTest extends TestCase
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
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_goods_use_canonical_seo_after_reclassification_and_stale_generic_saves_cannot_replace_it(): void
    {
        $good = Good::create(['name' => 'Филе форели', 'is_published' => true]);
        $level = CatalogLevel::create(['name' => 'Любой уровень', 'entity_type' => 'custom']);
        $node = $this->placement($good, ['level_id' => $level->id, 'meta_title' => 'Устаревший заголовок']);
        $seo = GoodSeo::create(['good_id' => $good->id, 'meta_title' => 'Канонический заголовок', 'meta_description' => 'Каноническое описание',
            'slug_override' => 'fresh-trout', 'canonical_url' => 'https://shop.example.test/fresh-trout', 'robots' => 'noindex,follow', 'is_active' => true]);
        $payload = app(CatalogService::class)->nodePayload($node);
        $this->assertSame('Канонический заголовок', $payload['meta_title']);
        $this->assertSame(route('public.goods.show', 'fresh-trout'), $payload['public_url']);
        $this->assertSame($payload['public_url'], $payload['offer_url']);
        $page = app(PublicCatalogService::class)->page($node->id);
        $this->assertSame('Канонический заголовок', $page['seo']['title']);
        $this->assertSame('https://shop.example.test/fresh-trout', $page['seo']['canonical']);
        $this->assertSame('noindex,follow', $page['seo']['robots']);

        $this->patchJson('/api/catalog/nodes/'.$node->id, ['level_id' => null, 'name' => 'Филе форели обновлённое',
            'meta_title' => 'Несвежая форма', 'meta_description' => 'Не заменять актуальное SEO'])
            ->assertOk()->assertJsonPath('data.meta_title', 'Канонический заголовок')->assertJsonPath('data.level_id', null);
        $this->assertSame('Каноническое описание', $seo->fresh()->meta_description);
        $this->assertNull($node->fresh()->meta_title);
        $this->assertNull($node->fresh()->meta_description);

        $custom = CatalogNode::create(['name' => 'Произвольная запись', 'level_id' => CatalogLevel::where('entity_type', 'good')->value('id')]);
        $this->patchJson('/api/catalog/nodes/'.$custom->id, ['meta_title' => 'SEO раздела', 'meta_description' => 'Описание раздела'])
            ->assertOk()->assertJsonPath('data.meta_title', 'SEO раздела');
        $this->assertSame('SEO раздела', $custom->fresh()->meta_title);
        Http::assertNothingSent();
    }

    public function test_consolidation_only_fills_missing_canonical_fields_and_preserves_other_entities(): void
    {
        $first = Good::create(['name' => 'Первый']);
        $second = Good::create(['name' => 'Второй']);
        $empty = Good::create(['name' => 'Без SEO']);
        $seo = GoodSeo::create(['good_id' => $first->id, 'meta_title' => 'Актуальное SEO', 'meta_description' => '',
            'slug_override' => 'known-alias', 'robots' => 'noindex,follow', 'is_active' => false]);
        $one = $this->placement($first, ['meta_title' => 'Старое SEO', 'meta_description' => 'Первое прежнее описание', 'properties' => ['kept' => true]]);
        $two = $this->placement($first, ['import_key' => null, 'meta_title' => 'Другая копия', 'meta_description' => 'Другое описание']);
        $this->placement($second, ['meta_title' => 'Перенесённый заголовок']);
        $this->placement($empty);
        $custom = CatalogNode::create(['name' => 'Свободная запись', 'meta_title' => 'Собственный заголовок']);
        $migration = require database_path('migrations/2026_10_07_190000_consolidate_catalog_good_seo.php');
        $migration->up();
        $this->assertSame('Актуальное SEO', $seo->fresh()->meta_title);
        $this->assertSame('Первое прежнее описание', $seo->fresh()->meta_description);
        $this->assertSame('known-alias', $seo->fresh()->slug_override);
        $this->assertFalse($seo->fresh()->is_active);
        $this->assertSame('Перенесённый заголовок', $second->seo()->firstOrFail()->meta_title);
        $this->assertFalse($empty->seo()->exists());
        $this->assertNull($one->fresh()->meta_title);
        $this->assertNull($two->fresh()->meta_description);
        $this->assertSame(['kept' => true], $one->fresh()->properties);
        $this->assertSame('Собственный заголовок', $custom->fresh()->meta_title);
        $seo->update(['meta_description' => 'Последующее редактирование']);
        $migration->up();
        $migration->down();
        $this->assertSame('Последующее редактирование', $seo->fresh()->meta_description);
        Http::assertNothingSent();
    }

    public function test_seo_aliases_reject_collisions_and_new_goods_reserve_existing_aliases(): void
    {
        $first = Good::create(['name' => 'Первый', 'slug' => 'first-product']);
        $second = Good::create(['name' => 'Второй', 'slug' => 'second-product']);
        GoodSeo::create(['good_id' => $second->id, 'slug_override' => 'reserved-alias', 'is_active' => true]);
        foreach (['second-product', 'reserved-alias', 'bad slug'] as $alias) {
            $this->putJson('/api/goods/'.$first->id.'/seo', ['robots' => 'index,follow', 'is_active' => true, 'slug_override' => $alias])
                ->assertUnprocessable()->assertJsonValidationErrors('slug_override');
        }
        $this->putJson('/api/goods/'.$first->id.'/seo', ['robots' => 'index,follow', 'is_active' => true,
            'slug_override' => 'first-product', 'canonical_url' => 'javascript:alert(1)'])
            ->assertUnprocessable()->assertJsonValidationErrors('canonical_url');
        $this->putJson('/api/goods/'.$first->id.'/seo', ['robots' => 'index,follow', 'is_active' => true, 'slug_override' => 'first-product'])
            ->assertOk()->assertJsonPath('slug_override', 'first-product');
        $third = Good::create(['name' => 'Reserved alias', 'slug' => 'reserved-alias']);
        $this->assertSame('reserved-alias-2', $third->slug);
        Http::assertNothingSent();
    }

    public function test_unchanged_legacy_aliases_and_inactive_seo_keep_their_public_behavior(): void
    {
        $good = Good::create(['name' => 'Публичный товар', 'is_published' => true]);
        GoodSeo::create(['good_id' => $good->id, 'slug_override' => 'Прежний-Адрес', 'meta_title' => 'Исходный заголовок', 'is_active' => true]);
        $this->putJson('/api/goods/'.$good->id.'/seo', ['robots' => 'index,follow', 'is_active' => true,
            'slug_override' => 'Прежний-Адрес', 'meta_title' => 'Обновлённый заголовок'])->assertOk();
        $canonical = app(GoodSeoService::class)->publicUrl($good->fresh('seo'));
        $this->get(route('public.goods.show', $good->slug))->assertRedirect($canonical);
        $this->get($canonical)->assertOk();
        $good->seo()->update(['is_active' => false]);
        $node = $this->placement($good);
        $payload = app(CatalogService::class)->nodePayload($node);
        $this->assertSame(route('public.goods.show', $good->slug), $payload['public_url']);
        $this->assertSame('noindex,follow', $payload['public_seo']['robots']);
        $this->get($canonical)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_direct_lookup_identifies_exact_good_beyond_name_search_pagination(): void
    {
        for ($index = 0; $index < 12; $index++) {
            Good::create(['name' => 'Похожее название '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
        }
        $good = Good::create(['name' => 'Похожее название 99']);
        $accountId = DB::table('yandex_accounts')->insertGetId(['name' => 'Тестовый аккаунт']);
        $campaignId = DB::table('yandex_direct_campaigns')->insertGetId(['yandex_account_id' => $accountId, 'name' => 'Тестовая кампания']);
        $groupId = DB::table('yandex_direct_ad_groups')->insertGetId(['yandex_direct_campaign_id' => $campaignId, 'name' => 'Тестовая группа']);
        $adId = DB::table('yandex_direct_ads')->insertGetId(['good_id' => $good->id, 'yandex_direct_ad_group_id' => $groupId,
            'title_1' => 'Объявление товара', 'text' => 'Описание', 'href' => '/g/'.$good->slug, 'status' => 'draft']);
        DB::table('yandex_direct_daily_stats')->insert(['good_id' => $good->id, 'yandex_account_id' => $accountId,
            'date' => '2026-10-07', 'impressions' => 120, 'clicks' => 12]);

        $response = $this->getJson('/api/marketing/direct/goods?search='.urlencode('Похожее название').'&per_page=10')
            ->assertOk()->assertJsonPath('total', 13)->assertJsonCount(10, 'data');
        $this->assertNotContains($good->id, array_column($response->json('data'), 'id'));
        $this->getJson('/api/marketing/direct/goods?good_id='.$good->id.'&per_page=1')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $good->id)->assertJsonPath('data.0.direct_ad_id', $adId)
            ->assertJsonPath('data.0.stats.impressions', 120)->assertJsonPath('data.0.stats.clicks', 12);
        foreach (['0', '-1', 'wrong'] as $invalidId) {
            $this->getJson('/api/marketing/direct/goods?good_id='.$invalidId)
                ->assertUnprocessable()->assertJsonValidationErrors('good_id');
        }
        $this->getJson('/api/marketing/direct/goods?good_id=999999')->assertOk()->assertJsonCount(0, 'data');
        Http::assertNothingSent();
    }

    private function placement(Good $good, array $attributes = []): CatalogNode
    {
        return CatalogNode::create([
            'level_id' => null, 'entity_type' => 'good', 'entity_id' => $good->id, 'name' => $good->name,
            'import_key' => 'good:'.$good->id.':root', 'is_published' => (bool) $good->is_published, ...$attributes,
        ]);
    }
}
