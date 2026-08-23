<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\DTO\Units\PublicProductSummary;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolRegistry;
use App\Domain\AiSales\Tools\Handlers\GetPublicGoodsForProductToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetPublicProductSummaryToolHandler;
use App\Domain\AiSales\Tools\Handlers\SearchPublicProductsToolHandler;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProductToolSafetyTest extends Stage08TestCase
{
    public function test_product_tools_use_explicit_published_allowlists_caps_and_no_lazy_graph(): void
    {
        Http::preventStrayRequests();
        $category = Category::query()->create(['name' => 'Public Stage08R Category', 'is_published' => true]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Stage08R Product 00',
            'eng' => 'Stage08R English 00',
            'category_id' => $category->id,
            'is_published' => true,
        ]);
        foreach (range(1, 24) as $index) {
            Product::query()->without(['category', 'manufacturers'])->create([
                'rus' => sprintf('Stage08R Product %02d', $index),
                'eng' => sprintf('Stage08R English %02d', $index),
                'is_published' => true,
            ]);
        }
        Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Stage08R Product hidden',
            'eng' => 'DO_NOT_DISCLOSE_UNPUBLISHED_PRODUCT',
            'is_published' => false,
        ]);
        $manufacturer = $this->unit(['name' => 'DO_NOT_DISCLOSE_MANUFACTURER']);
        $product->manufacturers()->attach($manufacturer->id);
        $visibleGood = Good::query()->create([
            'name' => 'Public Product offer',
            'description' => 'Bounded public description.',
            'is_published' => true,
        ]);
        $hiddenGood = Good::query()->create([
            'name' => 'DO_NOT_DISCLOSE_UNPUBLISHED_GOOD',
            'description' => 'Hidden.',
            'is_published' => false,
        ]);
        $visibleGood->products()->attach($product->id);
        $hiddenGood->products()->attach($product->id);
        DB::table('good_product')->insert([
            'good_id' => $visibleGood->id,
            'product_id' => $product->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Model::preventLazyLoading(true);
        try {
            $search = app(SearchPublicProductsToolHandler::class)->handle($this->toolContext(), [
                'query' => 'Stage08R',
                'limit' => 20,
                'sort' => 'name_asc',
            ]);
            $summary = app(GetPublicProductSummaryToolHandler::class)->handle($this->toolContext(), [
                'product_id' => $product->id,
            ]);
            $goods = app(GetPublicGoodsForProductToolHandler::class)->handle($this->toolContext(), [
                'product_id' => $product->id,
                'limit' => 20,
                'sort' => 'name_asc',
            ]);
        } finally {
            Model::preventLazyLoading(false);
        }

        $this->assertCount(20, $search->items);
        $this->assertSame(
            ['product_id', 'name', 'english_name', 'category'],
            array_keys($summary->items[0]->fields()),
        );
        $this->assertSame('Public Stage08R Category', $summary->items[0]->fields()['category']);
        $this->assertCount(1, $goods->items);
        $this->assertSame('Public Product offer', $goods->items[0]->fields()['name']);
        $encoded = json_encode([
            ...array_map(fn ($dto) => $dto->fields(), $search->items),
            ...array_map(fn ($dto) => $dto->fields(), $summary->items),
            ...array_map(fn ($dto) => $dto->fields(), $goods->items),
        ], JSON_THROW_ON_ERROR);
        foreach ([
            'DO_NOT_DISCLOSE_UNPUBLISHED_PRODUCT',
            'DO_NOT_DISCLOSE_UNPUBLISHED_GOOD',
            'DO_NOT_DISCLOSE_MANUFACTURER',
            'purchase_price',
            'margin',
            'supplier',
        ] as $blocked) {
            $this->assertStringNotContainsString($blocked, $encoded);
        }
        Http::assertNothingSent();
    }

    public function test_product_tool_definitions_are_code_owned_and_existing_good_tools_remain_registered(): void
    {
        $registry = app(AiToolRegistry::class);
        $codes = collect($registry->all())->pluck('code')->all();
        foreach ([
            'catalog.search_public_products',
            'catalog.get_public_product_summary',
            'catalog.get_public_goods_for_product',
            'catalog.search_public_goods',
            'catalog.get_public_good_summary',
            'catalog.get_synthetic_good',
        ] as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertSame(
            ['category', 'english_name', 'name', 'product_id'],
            app(AiDataClassificationRegistry::class)->registeredFields(PublicProductSummary::class),
        );
        $this->assertNull(app(AiDataClassificationRegistry::class)->find(PublicProductSummary::class, 'manufacturers'));

        foreach (['catalog.search_public_products', 'catalog.get_public_product_summary', 'catalog.get_public_goods_for_product'] as $code) {
            $definition = $registry->get($code, '1');
            $this->assertSame('read_only', $definition->sideEffectClass);
            $this->assertFalse($definition->syntheticOnly);
            $this->assertLessThanOrEqual(20, $definition->maxRows);
            $this->assertLessThanOrEqual(40_960, $definition->maxBytes);
        }
    }

    private function toolContext(): AiToolExecutionContext
    {
        return new AiToolExecutionContext(
            1,
            1,
            1,
            1,
            1,
            BusinessLane::Sales,
            UnitRoleCode::Customer,
            AiPurpose::UnitResearch,
            AiAudience::Internal,
            AiProcessingContour::ExternalSanitized,
            'stage08r.product-tools',
            '1',
            str_repeat('a', 64),
            1,
            str_repeat('b', 64),
            str_repeat('c', 64),
            1,
            50,
            65_536,
            5_000,
            '0.0000',
            false,
        );
    }
}
