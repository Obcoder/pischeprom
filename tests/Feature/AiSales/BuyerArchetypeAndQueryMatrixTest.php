<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Prospecting\BuyerArchetypeRegistry;
use App\Domain\AiSales\Prospecting\ProductBuyerArchetypePlanner;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Models\Category;
use App\Models\Consumption;
use App\Models\Industry;
use App\Models\Product;
use App\Models\Unit;

class BuyerArchetypeAndQueryMatrixTest extends Stage09TestCase
{
    public function test_product_consumers_category_and_reviewed_metadata_map_to_code_owned_archetypes(): void
    {
        $category = Category::query()->create(['name' => 'Овощи', 'is_published' => true]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Брокколи', 'eng' => 'Broccoli', 'category_id' => $category->id, 'is_published' => true,
        ]);
        $consumer = Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->create(['name' => 'Synthetic ready-meal producer']);
        $industry = Industry::query()->create(['code' => '10.85', 'title' => 'Производство готовых блюд']);
        $consumer->industries()->attach($industry->id, ['is_primary' => true]);
        Consumption::query()->create(['product_id' => $product->id, 'unit_id' => $consumer->id, 'quantity' => 1]);

        $planned = app(ProductBuyerArchetypePlanner::class)->plan($product->fresh(), limit: 6);
        $codes = collect($planned)->pluck('code');

        $this->assertGreaterThanOrEqual(4, $codes->count());
        $this->assertContains('ready_meal_manufacturer', $codes);
        $this->assertContains('vegetable_processor', $codes);
        $this->assertContains('frozen_food_manufacturer', $codes);
        $this->assertSame(64, strlen(app(BuyerArchetypeRegistry::class)->hash()));
    }

    public function test_query_matrix_is_bounded_deterministic_and_not_product_literal_only(): void
    {
        $actor = $this->prospectingUser();
        $category = Category::query()->create(['name' => 'Овощи', 'is_published' => true]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Брокколи', 'eng' => 'Broccoli', 'category_id' => $category->id, 'is_published' => true,
        ]);
        $job = $this->approvedJob($actor, product: $product);
        $job->update(['max_queries' => 10]);

        $first = app(PlanProspectingQueries::class)->handle($job->fresh(), $actor);
        $second = app(PlanProspectingQueries::class)->handle($job->fresh(), $actor);

        $this->assertCount(10, $first);
        $this->assertSame($first->pluck('query_hash')->all(), $second->pluck('query_hash')->all());
        $this->assertTrue($first->contains(fn ($query): bool => str_contains(mb_strtolower($query->safe_display_query), 'брокколи')));
        $this->assertTrue($first->contains(fn ($query): bool => ! str_contains(mb_strtolower($query->safe_display_query), 'брокколи')));
        $this->assertGreaterThanOrEqual(4, $first->map(fn ($query) => explode(':', $query->industry_intent)[0])->unique()->count());
        $this->assertTrue($first->every(fn ($query): bool => str_starts_with($query->template_code, 'buyer.matrix.')));
    }
}
