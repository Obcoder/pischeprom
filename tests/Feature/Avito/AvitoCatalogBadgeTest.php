<?php

namespace Tests\Feature\Avito;

use App\Models\AvitoListingGoodLink;
use App\Models\Good;
use App\Models\User;
use App\Services\Catalog\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvitoCatalogBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_staff_catalog_and_goods_list_show_links_without_exposing_accounts_or_public_drafts(): void
    {
        $linked = Good::create(['name' => 'Связанный товар']);
        $unlinked = Good::create(['name' => 'Без объявления']);
        foreach ([101, 102] as $itemId) {
            AvitoListingGoodLink::create(['good_id' => $linked->id, 'avito_account_id' => 777, 'avito_item_id' => $itemId]);
        }
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));

        $catalog = collect($this->getJson('/api/catalog')->assertOk()->json('nodes'))->where('entity_type', 'good')->keyBy('entity_id');
        $this->assertSame(2, $catalog[$linked->id]['avito_listing_links_count']);
        $this->assertSame(0, $catalog[$unlinked->id]['avito_listing_links_count']);
        $this->assertArrayNotHasKey('avito_account_id', $catalog[$linked->id]);

        $goods = collect($this->getJson('/api/goods?view=table')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(2, $goods[$linked->id]['avito_listing_links_count']);
        $this->assertSame(0, $goods[$unlinked->id]['avito_listing_links_count']);

        $publicNode = app(CatalogService::class)->nodes()->firstWhere('id', $catalog[$linked->id]['id']);
        $this->assertArrayNotHasKey('avito_listing_links_count', $publicNode);

        AvitoListingGoodLink::where('good_id', $linked->id)->delete();
        $updated = collect($this->getJson('/api/catalog')->assertOk()->json('nodes'))->firstWhere('id', $catalog[$linked->id]['id']);
        $this->assertSame(0, $updated['avito_listing_links_count']);
    }
}
