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

    public function test_seo_address_fields_cannot_override_the_primary_product_url(): void
    {
        $good = Good::create(['name' => 'Первый', 'slug' => 'first-product']);
        $canonical = route('public.goods.show', $good->slug);
        $this->putJson('/api/goods/'.$good->id.'/seo', [
            'robots' => 'index,follow', 'is_active' => true,
            'slug_override' => 'different-address', 'canonical_url' => 'https://elsewhere.test/product',
        ])->assertOk()->assertJsonPath('slug_override', 'first-product')->assertJsonPath('canonical_url', $canonical);
        $this->getJson('/api/goods/'.$good->id.'/seo')->assertOk()->assertJsonPath('primary_slug', 'first-product');
        Http::assertNothingSent();
    }

    public function test_manual_primary_url_survives_renames_and_updates_derived_seo_with_redirects(): void
    {
        $good = Good::create(['name' => 'Форель', 'slug' => 'manual-trout', 'is_published' => true]);
        $node = $this->placement($good);
        $seo = GoodSeo::create(['good_id' => $good->id, 'slug_override' => 'old-public-trout',
            'canonical_url' => 'https://old.example.test/trout', 'meta_title' => 'Особый заголовок', 'is_active' => true]);
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['name' => 'Форель охлаждённая'])
            ->assertOk()->assertJsonPath('data.slug', 'manual-trout');
        $this->assertSame('manual-trout', $seo->fresh()->slug_override);
        $this->assertSame(route('public.goods.show', 'manual-trout'), $seo->fresh()->canonical_url);

        $canonical = route('public.goods.show', 'fresh-manual-trout');
        $this->patchJson('/api/catalog/nodes/'.$node->id, ['slug' => 'fresh-manual-trout'])
            ->assertOk()->assertJsonPath('data.slug', 'fresh-manual-trout')
            ->assertJsonPath('data.public_url', $canonical)->assertJsonPath('data.public_seo.canonical', $canonical);
        $this->assertSame('fresh-manual-trout', $seo->fresh()->slug_override);
        $this->assertSame($canonical, $seo->fresh()->canonical_url);
        $this->assertSame('Особый заголовок', $seo->fresh()->meta_title);
        foreach (['manual-trout', 'old-public-trout'] as $previous) {
            $this->get(route('public.goods.show', $previous))->assertStatus(301)->assertRedirect($canonical);
            $this->get(route('public.goods.redirect', $previous))->assertStatus(301)
                ->assertRedirect(route('public.goods.show', $previous));
        }
        $this->get($canonical)->assertOk();

        // SEO loaded before the primary URL save cannot put back old addresses.
        $this->putJson('/api/goods/'.$good->id.'/seo', ['robots' => 'index,follow', 'is_active' => true,
            'slug_override' => 'old-public-trout', 'canonical_url' => 'https://old.example.test/trout', 'h1' => 'Свежая форель'])
            ->assertOk()->assertJsonPath('slug_override', 'fresh-manual-trout')->assertJsonPath('canonical_url', $canonical);
        $good->refresh()->update(['slug' => 'trout-next']);
        foreach (['manual-trout', 'old-public-trout', 'fresh-manual-trout'] as $previous) {
            $this->get(route('public.goods.show', $previous))->assertStatus(301)
                ->assertRedirect(route('public.goods.show', 'trout-next'));
        }
        $good->update(['is_published' => false]);
        $this->get(route('public.goods.show', 'old-public-trout'))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_primary_urls_reserve_other_products_current_and_previous_aliases(): void
    {
        $first = Good::create(['name' => 'Первый', 'slug' => 'first-product']);
        GoodSeo::create(['good_id' => $first->id, 'slug_override' => 'first-alias', 'is_active' => true]);
        $other = Good::create(['name' => 'Другой', 'slug' => 'first-alias']);
        $this->assertSame('first-alias-2', $other->slug);
        $first->update(['slug' => 'first-new']);
        $other->update(['slug' => 'first-product']);
        $this->assertSame('first-product-2', $other->slug);
        $other->update(['slug' => 'first-alias']);
        $this->assertSame('first-alias-2', $other->slug);
        $first->update(['slug' => 'first-product']);
        $this->assertSame('first-product', $first->slug, 'A product can reclaim its own URL');
        $this->get(route('public.goods.show', 'first-alias'))->assertStatus(301)
            ->assertRedirect(route('public.goods.show', 'first-product'));
        $first->delete();
        $this->assertDatabaseMissing('good_url_aliases', ['good_id' => $first->id]);
        Http::assertNothingSent();
    }

    public function test_saving_seo_normalizes_legacy_addresses_and_keeps_alias_redirects_when_seo_is_disabled(): void
    {
        $good = Good::create(['name' => 'Публичный товар', 'is_published' => true]);
        GoodSeo::create(['good_id' => $good->id, 'slug_override' => 'Прежний-Адрес', 'meta_title' => 'Исходный заголовок', 'is_active' => true]);
        $this->putJson('/api/goods/'.$good->id.'/seo', ['robots' => 'index,follow', 'is_active' => true,
            'slug_override' => 'Прежний-Адрес', 'meta_title' => 'Обновлённый заголовок'])->assertOk();
        $canonical = app(GoodSeoService::class)->publicUrl($good->fresh('seo'));
        $this->assertSame(route('public.goods.show', $good->slug), $canonical);
        $this->get(route('public.goods.show', 'Прежний-Адрес'))->assertStatus(301)->assertRedirect($canonical);
        $this->get($canonical)->assertOk();
        $good->seo()->update(['is_active' => false]);
        $node = $this->placement($good);
        $payload = app(CatalogService::class)->nodePayload($node);
        $this->assertSame(route('public.goods.show', $good->slug), $payload['public_url']);
        $this->assertSame('noindex,follow', $payload['public_seo']['robots']);
        $this->get($canonical)->assertOk();
        $this->get(route('public.goods.show', 'Прежний-Адрес'))->assertStatus(301)->assertRedirect($canonical);
        Http::assertNothingSent();
    }

    public function test_unchanged_product_saves_normalize_seo_and_stale_models_cannot_restore_old_addresses(): void
    {
        $good = Good::create(['name' => 'Товар', 'slug' => 'primary-product']);
        $seo = GoodSeo::create(['good_id' => $good->id, 'slug_override' => 'legacy-alias',
            'canonical_url' => 'https://elsewhere.test/product', 'is_active' => true]);
        $stale = $good->fresh('seo');
        $good->save();
        $this->assertSame('primary-product', $seo->fresh()->slug_override);
        $this->assertSame(route('public.goods.show', 'primary-product'), $seo->fresh()->canonical_url);
        $this->assertDatabaseHas('good_url_aliases', ['good_id' => $good->id, 'slug' => 'legacy-alias']);
        $good->update(['slug' => 'latest-product']);
        $stale->synchronizeSeoAddress();
        $this->assertSame('latest-product', $seo->fresh()->slug_override);
        $this->assertSame(route('public.goods.show', 'latest-product'), $seo->fresh()->canonical_url);
        $this->get(route('public.goods.show', 'legacy-alias'))->assertStatus(301)
            ->assertRedirect(route('public.goods.show', 'latest-product'));
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

    public function test_legacy_blank_slugs_keep_catalog_home_and_public_landing_available(): void
    {
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
        auth()->logout();

        $visible = null;
        foreach ([null, ''] as $slug) {
            $good = Good::create(['name' => 'Старый товар '.($slug === null ? 'публичный' : 'черновик'), 'is_published' => $slug === null]);
            DB::table('goods')->where('id', $good->id)->update(['slug' => $slug]);
            $node = $this->placement($good, ['is_featured' => $slug === null]);
            $url = route('public.goods.show', (string) $good->id);
            $payload = app(CatalogService::class)->nodePayload($node);
            $this->assertSame($url, $payload['public_url']);
            $this->assertSame($url, $payload['offer_url']);
            $this->assertSame($url, $payload['public_seo']['canonical']);

            if ($slug === null) {
                $visible = $node;
                $this->get($url, ['X-Inertia' => 'true'])->assertOk()
                    ->assertJsonPath('props.good.id', $good->id)
                    ->assertJsonPath('props.seo.canonical', $url)
                    ->assertJsonPath('props.seo.jsonLd.0.url', $url);
            } else {
                $this->get($url)->assertNotFound();
            }
        }

        $this->get('/', ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonCount(1, 'props.catalogShowcase')
            ->assertJsonPath('props.catalogShowcase.0.id', $visible->id);
        $this->assertNull(Good::findOrFail($visible->entity_id)->slug);
        Http::assertNothingSent();
    }

    public function test_numeric_public_lookup_preserves_exact_slug_and_alias_priority_and_publication(): void
    {
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
        auth()->logout();

        $legacyForSlug = Good::create(['name' => 'Старый первый', 'is_published' => true]);
        $legacyForAlias = Good::create(['name' => 'Старый второй', 'is_published' => true]);
        DB::table('goods')->whereIn('id', [$legacyForSlug->id, $legacyForAlias->id])->update(['slug' => null]);
        $numericSlug = Good::create(['name' => 'Числовой адрес', 'slug' => (string) $legacyForSlug->id, 'is_published' => true]);
        $numericAlias = Good::create(['name' => 'Числовой SEO адрес', 'is_published' => true]);
        GoodSeo::create(['good_id' => $numericAlias->id, 'slug_override' => (string) $legacyForAlias->id, 'is_active' => true]);

        foreach ([[$legacyForSlug, $numericSlug], [$legacyForAlias, $numericAlias]] as [$legacy, $exact]) {
            $url = route('public.goods.show', (string) $legacy->id);
            $this->get($url, ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.good.id', $exact->id);
            $exact->update(['is_published' => false]);
            $this->get($url)->assertNotFound();
        }

        $ordinary = Good::create(['name' => 'Обычный адрес', 'is_published' => true]);
        $this->get(route('public.goods.show', (string) $ordinary->id))
            ->assertStatus(301)->assertRedirect(route('public.goods.show', $ordinary->slug));
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
