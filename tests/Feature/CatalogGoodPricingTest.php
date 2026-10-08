<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\PriceType;
use App\Models\User;
use App\Services\Catalog\CatalogGoodPricing;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\PublicCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogGoodPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_latest_purchase_uses_document_date_and_current_private_prices_are_included(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $good = Good::create(['name' => 'Филе']);
        $purchaseId = $this->purchase($good, 100, '2026-10-07');
        $this->purchase($good, 20, '2026-10-01');
        $this->purchase($good, 999, '2026-10-09');
        $this->price($good, 125, ['valid_from' => '2026-10-08', 'valid_to' => '2026-10-08']);
        $this->price($good, 400, ['valid_to' => '2026-10-07']);
        $this->price($good, 500, ['valid_from' => '2026-10-09']);
        $inactive = $this->price($good, 600);
        $inactive->priceType->update(['is_active' => false]);

        $pricing = app(CatalogGoodPricing::class)->forGoods([$good->id])->get($good->id);

        $this->assertSame($purchaseId, $pricing['purchase']['purchase_id']);
        $this->assertSame(100.0, $pricing['purchase']['price']);
        $this->assertCount(1, $pricing['sales']);
        $this->assertSame(125.0, $pricing['sales'][0]['price']);
        $this->assertSame(25.0, $pricing['sales'][0]['markup_percent']);
        $this->assertSame('RUB', $pricing['sales'][0]['currency_label']);
    }

    public function test_markup_converts_known_weight_units_and_preserves_negative_markup(): void
    {
        $tonnes = Good::create(['name' => 'Тонны']);
        $grams = Good::create(['name' => 'Граммы']);
        $this->purchase($tonnes, 100000, today()->toDateString(), 'т');
        $this->purchase($grams, 0.1, today()->toDateString(), 'г');
        $this->price($tonnes, 120);
        $this->price($grams, 80);

        $pricing = app(CatalogGoodPricing::class)->forGoods([$tonnes->id, $grams->id]);

        $this->assertSame(20.0, $pricing[$tonnes->id]['sales'][0]['markup_percent']);
        $this->assertSame(-20.0, $pricing[$grams->id]['sales'][0]['markup_percent']);
        $this->assertSame(100000.0, $pricing[$tonnes->id]['purchase']['price']);
        $this->assertSame('т', $pricing[$tonnes->id]['purchase']['unit_label']);
    }

    public function test_missing_zero_and_incomparable_purchase_prices_do_not_invent_markup(): void
    {
        $goods = collect(['Без закупок', 'Бесплатно', 'Штуки', 'Доллары', 'Без валюты'])
            ->map(fn ($name) => Good::create(['name' => $name]));
        $this->purchase($goods[1], 0, today()->toDateString());
        $this->purchase($goods[2], 100, today()->toDateString(), 'шт.');
        $this->purchase($goods[3], 100, today()->toDateString(), 'кг', 'USD');
        $this->purchase($goods[4], 100, today()->toDateString(), 'кг', null);
        foreach ($goods as $good) {
            $this->price($good, 150);
        }

        $pricing = app(CatalogGoodPricing::class)->forGoods($goods->pluck('id')->all());

        foreach ($goods as $good) {
            $this->assertNull($pricing[$good->id]['sales'][0]['markup_percent']);
            $this->assertNotEmpty($pricing[$good->id]['sales'][0]['markup_unavailable_reason']);
        }
        $this->assertNull($pricing[$goods[0]->id]['purchase']);
        $this->assertSame(0.0, $pricing[$goods[1]->id]['purchase']['price']);
        $this->assertNull($pricing[$goods[4]->id]['purchase']['currency_label']);
    }

    public function test_net_only_sales_require_a_known_vat_rate_before_comparing_with_purchase(): void
    {
        $good = Good::create(['name' => 'НДС']);
        $this->purchase($good, 100, today()->toDateString());
        $this->price($good, null, ['price_net' => 100, 'vat_rate' => 20]);
        $this->price($good, null, ['price_net' => 100]);

        $sales = app(CatalogGoodPricing::class)->forGoods([$good->id])[$good->id]['sales'];

        $this->assertSame(120.0, $sales[0]['price']);
        $this->assertTrue($sales[0]['includes_vat']);
        $this->assertSame(20.0, $sales[0]['markup_percent']);
        $this->assertSame(100.0, $sales[1]['price']);
        $this->assertFalse($sales[1]['includes_vat']);
        $this->assertNull($sales[1]['markup_percent']);
    }

    public function test_pricing_is_available_to_staff_but_absent_from_public_nodes_and_pages(): void
    {
        $good = Good::create(['name' => 'Закрытая закупочная цена', 'is_published' => true]);
        $this->purchase($good, 123.45, today()->toDateString());
        $this->price($good, 180);
        $this->getJson('/api/catalog')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))
            ->getJson('/api/catalog')->assertForbidden();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));

        $node = collect($this->getJson('/api/catalog')->assertOk()->json('nodes'))->firstWhere('entity_id', $good->id);
        $this->assertEquals(123.45, $node['pricing']['purchase']['price']);
        $publicNode = app(CatalogService::class)->nodes()->firstWhere('id', $node['id']);
        $this->assertArrayNotHasKey('pricing', $publicNode);
        $page = app(PublicCatalogService::class)->page($node['id']);
        $this->assertStringNotContainsString('123.45', json_encode($page));
        $this->assertStringNotContainsString('markup_percent', json_encode($page));
    }

    public function test_queries_are_batched_for_multiple_goods_and_repeated_placements(): void
    {
        $goods = collect(range(1, 8))->map(fn ($id) => Good::create(['name' => 'Товар '.$id]));
        foreach ($goods as $good) {
            $this->purchase($good, 100, today()->toDateString());
            $this->price($good, 125);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $pricing = app(CatalogGoodPricing::class)->forGoods([...$goods->pluck('id'), $goods[0]->id]);
            $this->assertCount(8, $pricing);
            $this->assertCount(2, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    private function purchase(Good $good, float $price, string $date, string $unit = 'кг', ?string $currency = 'RUB'): int
    {
        $entityId = DB::table('entities')->insertGetId(['name' => 'Поставщик '.uniqid()]);
        $purchaseId = DB::table('purchases')->insertGetId(['entity_id' => $entityId, 'date' => $date, 'amount' => $price]);
        $measureId = DB::table('measures')->insertGetId(['name' => $unit]);
        DB::table('good_purchase')->insert([
            'good_id' => $good->id, 'purchase_id' => $purchaseId,
            'price' => $price, 'measure_id' => $measureId,
            'currency_id' => $currency ? $this->currency($currency) : null,
        ]);

        return $purchaseId;
    }

    private function price(Good $good, ?float $gross, array $attributes = []): GoodPriceTypeValue
    {
        $type = PriceType::create([
            'name' => 'Цена '.(PriceType::count() + 1), 'code' => 'price-'.uniqid(),
            'currency_id' => $this->currency('RUB'), 'is_active' => true, 'is_public' => false,
        ]);

        return GoodPriceTypeValue::create([
            'good_id' => $good->id, 'price_type_id' => $type->id, 'price_gross' => $gross,
            'is_published' => false, ...$attributes,
        ]);
    }

    private function currency(string $code): int
    {
        return Currency::where('code', $code)->value('id')
            ?? Currency::forceCreate(['code' => $code, 'name' => $code])->id;
    }
}
