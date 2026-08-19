<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\FindBuyers\BuildFindBuyersQueryPlan;
use App\Domain\AiSales\FindBuyers\FindBuyersDraftOrchestrator;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchExecution;
use App\Models\Unit;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class FindBuyersLaunchAndWorkflowTest extends Stage11TestCase
{
    public function test_product_and_good_launch_contexts_are_product_first_and_distinct(): void
    {
        $actor = $this->prospectingUser();
        $primary = $this->product('Брокколи');
        $second = $this->product('Цветная капуста');
        $missing = $this->good('Good without Product');
        $one = $this->good('Брокколи 10 кг');
        $many = $this->good('Овощное ассорти');
        $one->products()->attach($primary->id);
        $many->products()->attach([$primary->id, $second->id]);
        DB::table('good_product')->insert([
            'good_id' => $many->id,
            'product_id' => $primary->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=product&source_id='.$primary->id)
            ->assertOk()
            ->assertJsonPath('data.primary_product.id', $primary->id)
            ->assertJsonPath('data.eligibility.eligible', true)
            ->assertJsonPath('data.runtime.live_execution_allowed', false)
            ->assertJsonPath('data.summary_counts.available_goods', 2)
            ->json('data');
        $this->assertNotEmpty($product['disclosure_preview']['policy_hash']);
        $this->assertContains('name', array_column($product['disclosure_preview']['allowed_fields'], 'field'));
        $this->assertContains('credentials_and_secrets', array_column($product['disclosure_preview']['blocked_classes'], 'code'));
        $encoded = json_encode($product, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('manufacturers', $encoded);
        $this->assertStringNotContainsString('suppliers', $encoded);

        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=good&source_id='.$missing->id)
            ->assertOk()
            ->assertJsonPath('data.eligibility.eligible', false)
            ->assertJsonPath('data.eligibility.reason_code', 'missing_product_mapping')
            ->assertJsonPath('data.primary_product', null);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=good&source_id='.$one->id)
            ->assertOk()
            ->assertJsonPath('data.primary_product.id', $primary->id)
            ->assertJsonPath('data.originating_good.id', $one->id);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=good&source_id='.$many->id)
            ->assertOk()
            ->assertJsonCount(2, 'data.product_options')
            ->assertJsonPath('data.eligibility.reason_code', 'product_selection_required');
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=good&source_id='.$many->id.'&selected_product_id='.$primary->id)
            ->assertOk()
            ->assertJsonPath('data.primary_product.id', $primary->id)
            ->assertJsonPath('data.eligibility.eligible', true);
        $outside = $this->product('Unrelated Product');
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=good&source_id='.$many->id.'&selected_product_id='.$outside->id)
            ->assertUnprocessable()->assertJsonValidationErrors('selected_product_id');
    }

    public function test_draft_plan_review_and_cancel_are_server_owned_idempotent_and_http_free(): void
    {
        $actor = $this->prospectingUser();
        $product = $this->product('Брокколи');
        $good = $this->good('Брокколи замороженная');
        $good->products()->attach($product->id);
        $countryId = DB::table('countries')->insertGetId(['name' => 'Россия', 'сodeISO' => 'RU']);
        $regionId = DB::table('regions')->insertGetId([
            'name' => 'Санкт-Петербург', 'country_id' => $countryId, 'use_for_yandex_direct' => true,
        ]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Санкт-Петербург', 'region_id' => $regionId]);
        $idempotency = (string) Str::uuid();
        $payload = [
            'source_type' => 'good',
            'source_id' => $good->id,
            'selected_product_id' => $product->id,
            'idempotency_key' => $idempotency,
            'company_activity_codes' => ['food_manufacturing'],
            'country_id' => $countryId,
            'region_id' => $regionId,
            'city_id' => $cityId,
            'limits' => [
                'max_queries' => 3, 'max_results_per_query' => 5, 'max_domains' => 3,
                'max_page_fetch_attempts' => 0, 'max_candidates' => 5,
            ],
        ];
        $unitCount = Unit::query()->count();
        $entityCount = Entity::query()->without(['buildings', 'classification', 'country'])->count();

        $first = $this->actingAs($actor)->postJson('/api/ai-sales/find-buyers/drafts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'buyer_discovery')
            ->assertJsonPath('data.lane', 'sales')
            ->assertJsonPath('data.default_role_code', 'prospective_customer')
            ->assertJsonPath('data.auto_create_unit', false)
            ->assertJsonPath('data.find_buyers.live_execution_allowed', false)
            ->assertJsonPath('unit_created', false)
            ->assertJsonPath('entity_created', false);
        $jobId = $first->json('data.id');
        $this->actingAs($actor)->postJson('/api/ai-sales/find-buyers/drafts', $payload)
            ->assertOk()->assertJsonPath('data.id', $jobId)->assertJsonPath('idempotent_replay', true);
        $this->assertDatabaseCount('prospecting_search_jobs', 1);
        $stored = DB::table('prospecting_search_jobs')->first();
        $this->assertSame(hash('sha256', $actor->id.'|'.$idempotency), $stored->draft_idempotency_key_hash);
        $this->assertSame('mapped', $stored->product_mapping_state);
        $this->assertSame(0, (int) $stored->max_searches);
        $this->assertSame(0.0, (float) $stored->max_cost_rub);
        $this->actingAs($actor)->patchJson("/api/ai-sales/find-buyers/drafts/{$jobId}", [
            'limits' => ['max_candidates' => 4],
        ])->assertOk()
            ->assertJsonPath('data.criteria.segments.0', 'Пищевое производство')
            ->assertJsonPath('data.limits.max_candidates', 4);

        $plan = $this->actingAs($actor)->postJson("/api/ai-sales/find-buyers/drafts/{$jobId}/plan", [])
            ->assertCreated()->assertJsonPath('data.0.plan_status', 'review_required')->json('data');
        $this->assertNotEmpty($plan);
        $this->assertTrue(collect($plan)->every(fn (array $row): bool => str_contains(mb_strtolower($row['query']), 'брокколи')
            && ! str_contains($row['query'], $good->name)));
        $this->actingAs($actor)->postJson("/api/ai-sales/find-buyers/drafts/{$jobId}/submit", [])
            ->assertOk()->assertJsonPath('data.status', 'review_required');
        $this->actingAs($actor)->postJson("/api/ai-sales/find-buyers/jobs/{$jobId}/cancel", [])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame($unitCount, Unit::query()->count());
        $this->assertSame($entityCount, Entity::query()->without(['buildings', 'classification', 'country'])->count());
        $this->assertSame(0, ProspectingCandidate::query()->count());
        Http::assertNothingSent();
    }

    public function test_browser_runtime_inputs_permissions_and_revocation_fail_closed(): void
    {
        $product = $this->product('Protected Product');
        $actor = $this->prospectingUser();
        $forbidden = $this->userWith(['ai_sales.view', 'ai_sales.sales.view', 'ai_sales.prospecting.view']);

        $this->getJson('/api/ai-sales/find-buyers/launch-context?source_type=product&source_id='.$product->id)
            ->assertUnauthorized();
        $this->actingAs($forbidden)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=product&source_id='.$product->id)
            ->assertForbidden();
        $this->actingAs($actor)->postJson('/api/ai-sales/find-buyers/drafts', [
            'source_type' => 'product', 'source_id' => $product->id,
            'idempotency_key' => (string) Str::uuid(),
            'provider' => 'existing_yandex', 'model' => 'browser-model', 'contour' => 'external_sanitized',
            'prompt' => 'arbitrary prompt', 'query' => 'arbitrary query', 'url' => 'https://example.org',
            'tool' => 'shell', 'execute' => true, 'auto_create_unit' => true, 'entity_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'provider', 'model', 'contour', 'prompt', 'query', 'url', 'tool', 'execute', 'auto_create_unit', 'entity_id',
        ]);

        $result = app(FindBuyersDraftOrchestrator::class)->create([
            'source_type' => 'product', 'source_id' => $product->id,
            'idempotency_key' => (string) Str::uuid(),
        ], $actor);
        $actor->revokePermissionTo('ai_sales.search.plan', 'crm');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(BuildFindBuyersQueryPlan::class)->handle($result->job, $actor->fresh());
    }

    public function test_limits_geography_and_default_off_flags_are_enforced(): void
    {
        $actor = $this->prospectingUser();
        $product = $this->product('Bounded Product');
        $countryA = DB::table('countries')->insertGetId(['name' => 'A', 'сodeISO' => 'AA']);
        $countryB = DB::table('countries')->insertGetId(['name' => 'B', 'сodeISO' => 'BB']);
        $regionB = DB::table('regions')->insertGetId([
            'name' => 'B region', 'country_id' => $countryB, 'use_for_yandex_direct' => false,
        ]);
        $base = [
            'source_type' => 'product', 'source_id' => $product->id, 'idempotency_key' => (string) Str::uuid(),
        ];
        $this->actingAs($actor)->postJson('/api/ai-sales/find-buyers/drafts', [
            ...$base, 'country_id' => $countryA, 'region_id' => $regionB,
        ])->assertUnprocessable()->assertJsonValidationErrors('region_id');
        $this->actingAs($actor)->postJson('/api/ai-sales/find-buyers/drafts', [
            ...$base, 'idempotency_key' => (string) Str::uuid(), 'limits' => ['max_queries' => 11],
        ])->assertUnprocessable()->assertJsonValidationErrors('limits.max_queries');

        config()->set('ai-sales.find_buyers.ui_enabled', false);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=product&source_id='.$product->id)
            ->assertNotFound();
        config()->set(['ai-sales.find_buyers.ui_enabled' => true, 'ai-sales.prospecting.search_execution_enabled' => true]);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/launch-context?source_type=product&source_id='.$product->id)
            ->assertOk()
            ->assertJsonPath('data.runtime.search_execution_enabled', true)
            ->assertJsonPath('data.runtime.live_execution_allowed', false);
        Http::assertNothingSent();
    }

    public function test_ui_and_assisted_search_execution_coexistence_matrix_is_read_only_and_fail_closed(): void
    {
        Http::fake();
        Bus::fake();
        $actor = $this->prospectingUser();
        $product = $this->product('Coexistence Product');
        $draft = $this->actingAs($actor)->postJson('/api/ai-sales/find-buyers/drafts', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $jobId = $draft->json('data.id');
        $before = [
            'executions' => ProspectingSearchExecution::query()->count(),
            'candidates' => ProspectingCandidate::query()->count(),
            'units' => Unit::query()->count(),
            'entities' => Entity::query()->without(['buildings', 'classification', 'country'])->count(),
        ];

        // UI-only mode remains available while execution is disabled.
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/dashboard?limit=25')
            ->assertOk()
            ->assertJsonPath('data.runtime.search_execution_enabled', false)
            ->assertJsonPath('data.live_execution_action_available', false);
        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/jobs/{$jobId}/search-execute", [])
            ->assertNotFound();

        // Server-side assisted execution may be enabled, but a Find Buyers browser
        // job still cannot use the generic execution action.
        config()->set([
            'ai-sales.web_search_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
        ]);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/dashboard?limit=25')
            ->assertOk()
            ->assertJsonPath('data.runtime.search_execution_enabled', true)
            ->assertJsonPath('data.live_execution_action_available', false);
        $this->actingAs($actor)
            ->postJson("/api/ai-sales/prospecting/jobs/{$jobId}/search-execute", [])
            ->assertForbidden();
        $this->actingAs($actor)->postJson("/api/ai-sales/prospecting/jobs/{$jobId}/search-execute", [
            'query' => 'browser supplied query',
            'provider' => 'browser-provider',
            'model' => 'browser-model',
            'url' => 'https://example.org',
        ])->assertUnprocessable()->assertJsonValidationErrors(['query', 'provider', 'model', 'url']);

        // Bounded server-side research flags can coexist with read projections;
        // they do not add a browser execution action.
        config()->set([
            'ai-sales.prospecting.page_fetch_enabled' => true,
            'ai-sales.prospecting.public_research_enabled' => true,
        ]);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/dashboard?limit=25')
            ->assertOk()
            ->assertJsonPath('data.live_execution_action_available', false);

        $this->assertSame($before, [
            'executions' => ProspectingSearchExecution::query()->count(),
            'candidates' => ProspectingCandidate::query()->count(),
            'units' => Unit::query()->count(),
            'entities' => Entity::query()->without(['buildings', 'classification', 'country'])->count(),
        ]);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_disabled_and_browser_automation_conflicts_return_safe_non_500_responses(): void
    {
        Http::fake();
        $actor = $this->prospectingUser();

        config()->set('ai-sales.find_buyers.ui_enabled', false);
        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/dashboard')->assertNotFound();
        config()->set('ai-sales.find_buyers.ui_enabled', true);

        foreach ([
            'ai-sales.find_buyers.live_execution_enabled',
            'ai-sales.find_buyers.auto_research_enabled',
            'ai-sales.find_buyers.auto_scoring_enabled',
            'ai-sales.prospecting.auto_candidate_ingestion_enabled',
            'ai-sales.prospecting.auto_scoring_enabled',
        ] as $key) {
            config()->set($key, true);
            $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/dashboard')
                ->assertConflict()
                ->assertJsonPath('message', 'find_buyers_browser_automation_conflict');
            config()->set($key, false);
        }

        foreach (['ai-sales.external_calls_enabled', 'ai-sales.provider_failover_enabled'] as $key) {
            config()->set($key, true);
            $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/dashboard')
                ->assertConflict()
                ->assertJsonPath('message', 'find_buyers_runtime_safety_conflict');
            config()->set($key, false);
        }
        Http::assertNothingSent();
    }

    private function product(string $name): Product
    {
        return Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => $name, 'eng' => null, 'is_published' => true,
        ]);
    }

    private function good(string $name): Good
    {
        return Good::query()->create(['name' => $name, 'is_published' => true]);
    }
}
