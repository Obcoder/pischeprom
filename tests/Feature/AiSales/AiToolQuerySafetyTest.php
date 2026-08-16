<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\Handlers\GetAggregateDemandSummaryToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetSupportedRegionsToolHandler;
use App\Domain\AiSales\Tools\Handlers\SearchPublicGoodsToolHandler;
use App\Models\Country;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Measure;
use App\Models\Region;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;

class AiToolQuerySafetyTest extends Stage07TestCase
{
    public function test_catalog_search_uses_published_filter_allowlisted_sort_and_hard_row_cap_without_lazy_loading(): void
    {
        foreach (range(1, 25) as $index) {
            Good::query()->create([
                'name' => sprintf('Published synthetic %02d', $index),
                'description' => 'Public description',
                'is_published' => true,
            ]);
        }
        Good::query()->create([
            'name' => 'Published synthetic hidden',
            'description' => 'Must not leave unpublished row.',
            'is_published' => false,
        ]);

        Model::preventLazyLoading(true);

        try {
            $result = app(SearchPublicGoodsToolHandler::class)->handle($this->toolContext(), [
                'query' => 'Published synthetic',
                'limit' => 20,
                'sort' => 'name_desc',
            ]);
        } finally {
            Model::preventLazyLoading(false);
        }

        $names = collect($result->items)->map(fn ($dto) => $dto->fields()['name']);
        $this->assertCount(20, $names);
        $this->assertSame($names->sortDesc()->values()->all(), $names->values()->all());
        $this->assertNotContains('Published synthetic hidden', $names);
        $this->assertSame(
            ['name', 'description', 'published_attributes'],
            array_keys($result->items[0]->fields()),
        );
    }

    public function test_supported_regions_are_explicit_bounded_and_do_not_lazy_load_relations(): void
    {
        $country = Country::query()->create(['name' => 'Synthetic country', 'сodeISO' => 'ZZ']);

        foreach (range(1, 60) as $index) {
            Region::query()->create([
                'name' => sprintf('Region %02d', $index),
                'country_id' => $country->id,
            ]);
        }

        Model::preventLazyLoading(true);

        try {
            $result = app(GetSupportedRegionsToolHandler::class)->handle($this->toolContext(), [
                'limit' => 50,
                'sort' => 'name_asc',
            ]);
        } finally {
            Model::preventLazyLoading(false);
        }

        $this->assertCount(50, $result->items);
        $this->assertSame(['name', 'country'], array_keys($result->items[0]->fields()));
    }

    public function test_demand_aggregate_enforces_minimum_cohort_and_returns_no_customer_rows(): void
    {
        $good = Good::query()->create(['name' => 'Aggregate good', 'is_published' => true]);
        $measure = Measure::query()->create(['name' => 'kg']);

        foreach (range(1, 5) as $index) {
            $entity = Entity::query()->create(['name' => "Synthetic customer {$index}"]);
            $sale = Sale::query()->create([
                'date' => today(),
                'entity_id' => $entity->id,
                'total' => 10,
            ]);
            $sale->goods()->attach($good->id, [
                'quantity' => 25,
                'price' => 1,
                'measure_id' => $measure->id,
            ]);
        }

        $result = app(GetAggregateDemandSummaryToolHandler::class)->handle($this->toolContext(
            BusinessLane::Procurement,
            UnitRoleCode::Supplier,
            AiProcessingContour::LocalRu,
        ), ['good_id' => $good->id, 'days' => 90]);
        $fields = $result->items[0]->fields();

        $this->assertSame(5, $fields['sample_size']);
        $this->assertSame('100_to_999', $fields['quantity_band']);
        $this->assertSame(
            ['product_name', 'period', 'quantity_band', 'region_count', 'sample_size'],
            array_keys($fields),
        );
        $this->assertStringNotContainsString('Synthetic customer', json_encode($fields));

        $smallGood = Good::query()->create(['name' => 'Small cohort good', 'is_published' => true]);
        $sale = Sale::query()->firstOrFail();
        $sale->goods()->attach($smallGood->id, ['quantity' => 1, 'price' => 1, 'measure_id' => $measure->id]);

        try {
            app(GetAggregateDemandSummaryToolHandler::class)->handle($this->toolContext(
                BusinessLane::Procurement,
                UnitRoleCode::Supplier,
                AiProcessingContour::LocalRu,
            ), ['good_id' => $smallGood->id, 'days' => 90]);
            $this->fail('Small aggregate cohort was disclosed.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('aggregate_privacy_threshold', $violation->errorCode);
        }
    }

    private function toolContext(
        BusinessLane $lane = BusinessLane::Sales,
        UnitRoleCode $role = UnitRoleCode::Customer,
        AiProcessingContour $contour = AiProcessingContour::ExternalSanitized,
    ): AiToolExecutionContext {
        return new AiToolExecutionContext(
            1,
            1,
            1,
            1,
            1,
            $lane,
            $role,
            AiPurpose::UnitResearch,
            AiAudience::Internal,
            $contour,
            'test.workflow',
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
