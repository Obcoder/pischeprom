<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Services\ProspectingCandidateService;
use App\Domain\AiSales\Services\ProspectingSearchQueryService;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Domain\AiSales\Services\UnitAliasService;
use App\Models\Entity;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingCandidateUnitMatch;
use App\Models\ProspectingPublicFetch;
use App\Models\ProspectingPublicResearchRecord;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchResult;
use App\Models\Unit;
use App\Models\UnitAlias;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

final class CandidateInvestigationReviewTest extends Stage08TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    public function test_production_shaped_unresolved_candidate_has_safe_investigation_projection(): void
    {
        [$actor, $candidate, $sameLaneUnit, $crossLaneUnit, $product] = $this->investigationFixture();
        $before = $this->readOnlyCounts();

        $response = $this->actingAs($actor)
            ->getJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id)
            ->assertOk()
            ->assertJsonPath('data.status', 'new_unit_review')
            ->assertJsonPath('data.resolution_outcome', 'new_unit_allowed')
            ->assertJsonPath('data.investigation.candidate.working_name_origin', 'source_page_title')
            ->assertJsonPath('data.investigation.identity.verification_status', 'unresolved')
            ->assertJsonPath('data.investigation.identity.inferred_company_name', null)
            ->assertJsonPath('data.investigation.identity.public_site_name', 'buyer-investigation.example')
            ->assertJsonPath('data.investigation.identity.registrable_domain', 'buyer-investigation.example')
            ->assertJsonPath('data.investigation.identity.requires_human_name_confirmation', true)
            ->assertJsonPath('data.investigation.product_scope.0.product.id', $product->id)
            ->assertJsonPath('data.investigation.product_scope.0.score_status', 'not_calculated')
            ->assertJsonPath('data.investigation.sources.0.fetch_status', 'completed')
            ->assertJsonPath('data.investigation.sources.0.research_status', 'completed')
            ->assertJsonPath('data.investigation.sources.0.safe_url', 'https://buyer-investigation.example/broccoli')
            ->assertJsonPath('data.investigation.public_contacts.0.kind', 'email')
            ->assertJsonPath('data.investigation.public_contacts.0.display', 'sa***@buyer-investigation.example')
            ->assertJsonCount(2, 'data.investigation.sources')
            ->assertJsonCount(1, 'data.investigation.public_contacts')
            ->assertJsonCount(1, 'data.investigation.duplicates')
            ->assertJsonPath('data.investigation.duplicates.0.unit.id', $sameLaneUnit->id)
            ->assertJsonPath('data.investigation.duplicates.0.unit.name', 'Existing Sales Buyer')
            ->assertJsonPath('data.investigation.duplicates.0.unit.city', 'Safe City')
            ->assertJsonPath('data.investigation.duplicates.0.unit.domain', 'buyer-investigation.example')
            ->assertJsonPath('data.investigation.duplicates.0.reason_code', 'normalized_or_fuzzy_name_review')
            ->assertJsonPath('data.investigation.duplicates.0.domain_match', true);

        $payload = json_encode($response->json(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Брокколи нужна покупателю для публичного ресторанного меню.', $payload);
        $this->assertStringContainsString('broccoli wholesale', $payload);
        $this->assertStringContainsString('Public food-service buyer in Saint Petersburg.', $payload);
        $this->assertStringNotContainsString('RAW_HTML_MUST_NEVER_LEAVE_SERVER', $payload);
        $this->assertStringNotContainsString('private.person@buyer-investigation.example', $payload);
        $this->assertStringNotContainsString('pr***@buyer-investigation.example', $payload);
        $this->assertStringNotContainsString('PROTECTED_CHANNEL_MUST_NOT_LEAK', $payload);
        $this->assertNotContains(
            $crossLaneUnit->id,
            collect($response->json('data.investigation.duplicates'))->pluck('unit.id')->all(),
        );
        $this->assertSame($before, $this->readOnlyCounts());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_candidate_review_get_is_auth_lane_gated_and_ui_has_explicit_safe_sections(): void
    {
        [$actor, $candidate] = $this->investigationFixture();
        $withoutPermission = $this->userWith([]);

        $this->actingAsGuest('sanctum');
        $this->actingAsGuest('web');
        $this->getJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id)->assertUnauthorized();
        $this->actingAs($withoutPermission)
            ->getJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id)
            ->assertForbidden();
        $this->actingAs($actor)
            ->getJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id)
            ->assertOk();

        $component = file_get_contents(resource_path('js/Components/AiSales/CandidateReviewCard.vue'));
        foreach (['Кто найден', 'Почему подходит', 'Источники', 'Публичные сведения', 'Проверка дублей', 'Решение'] as $section) {
            $this->assertStringContainsString($section, $component);
        }
        foreach (['Связать с существующим Unit', 'Подтвердить создание нового Unit', 'Отклонить', 'Открыть источник'] as $action) {
            $this->assertStringContainsString($action, $component);
        }
        $this->assertStringContainsString('Компания пока не идентифицирована.', $component);
        $this->assertStringContainsString('Product relevance score:', $component);
        $this->assertStringContainsString('не рассчитан', $component);
        $this->assertStringContainsString('reviewed_working_name:', $component);
        $this->assertStringContainsString('name_confirmed: true', $component);
        $this->assertStringNotContainsString('/entities', $component);
        $this->assertStringNotContainsString('send-email', $component);
    }

    public function test_create_new_unit_requires_human_name_and_never_uses_seo_title_as_unit_or_alias(): void
    {
        $actor = $this->prospectingUser();
        $seoTitle = 'Капуста брокколи оптом — купить с доставкой в СПб';
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => $seoTitle,
            'website' => 'https://human-name-review.example',
            'confidence_components' => ['relevance' => 90],
            'sources' => [[
                'type' => 'public_search',
                'reference' => 'repository-fixture:human-name-review',
                'url' => 'https://human-name-review.example/broccoli',
                'title' => $seoTitle,
                'excerpt' => 'Safe bounded public evidence.',
            ]],
        ]);
        app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $unitsBefore = Unit::query()->count();
        $entitiesBefore = Entity::query()->count();

        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id.'/create-unit', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reviewed_working_name', 'name_confirmed']);
        $this->assertSame($unitsBefore, Unit::query()->count());

        $created = $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id.'/create-unit', [
                'reviewed_working_name' => 'Петербургский покупатель брокколи',
                'name_confirmed' => true,
            ])->assertCreated()
            ->assertJsonPath('data.entity_created', false)
            ->json('data.unit');

        $this->assertSame('Петербургский покупатель брокколи', $created['name']);
        $this->assertSame($seoTitle, $candidate->fresh()->working_name);
        $this->assertDatabaseMissing('units', ['id' => $created['id'], 'name' => $seoTitle]);
        $this->assertFalse(UnitAlias::query()->where('unit_id', $created['id'])->where('alias', $seoTitle)->exists());
        $this->assertSame($entitiesBefore, Entity::query()->count());

        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id.'/create-unit', [
                'reviewed_working_name' => 'Петербургский покупатель брокколи',
                'name_confirmed' => true,
            ])->assertCreated()
            ->assertJsonPath('data.unit.id', $created['id']);
        $this->assertSame($unitsBefore + 1, Unit::query()->count());
    }

    public function test_human_name_is_used_for_final_duplicate_recheck_before_unit_creation(): void
    {
        $actor = $this->prospectingUser();
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'SEO title unrelated to reviewed identity',
            'website' => null,
        ]);
        app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $existing = $this->unit(['name' => 'Human Reviewed Duplicate']);
        $this->createContext($actor, $existing, ['lane' => 'sales', 'role_code' => 'prospective_customer']);
        $unitsBefore = Unit::query()->count();

        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id.'/create-unit', [
                'reviewed_working_name' => 'Human Reviewed Duplicate',
                'name_confirmed' => true,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['candidate']);

        $this->assertSame($unitsBefore, Unit::query()->count());
        $this->assertSame('new_unit_review', $candidate->fresh()->status->value);
        $this->assertDatabaseCount('entity_unit', 0);
    }

    public function test_link_reject_and_cross_lane_resolution_use_protected_services_without_entity_mutation(): void
    {
        $actor = $this->prospectingUser(['sales', 'procurement']);
        $salesUnit = $this->unit(['name' => 'Reviewed Existing Buyer']);
        $this->createContext($actor, $salesUnit, ['lane' => 'sales', 'role_code' => 'prospective_customer']);
        $salesCandidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'Reviewed Existing Buyer',
            'website' => null,
        ]);
        app(ResolveProspectingCandidate::class)->evaluate($salesCandidate, $actor);
        $entitiesBefore = Entity::query()->count();
        $unitsBefore = Unit::query()->count();

        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$salesCandidate->public_id.'/resolve-existing', [
                'unit_id' => $salesUnit->id,
            ])->assertOk()
            ->assertJsonPath('data.unit.id', $salesUnit->id)
            ->assertJsonPath('data.entity_mutated', false);

        $crossLaneUnit = $this->unit(['name' => 'Procurement-only Same Name']);
        $this->createContext($actor, $crossLaneUnit, ['lane' => 'procurement', 'role_code' => 'prospective_supplier']);
        $crossLaneCandidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'Procurement-only Same Name',
            'website' => null,
        ]);
        app(ResolveProspectingCandidate::class)->evaluate($crossLaneCandidate, $actor);
        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$crossLaneCandidate->public_id.'/resolve-existing', [
                'unit_id' => $crossLaneUnit->id,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_id']);

        $rejectCandidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'Rejected Safe Candidate',
            'website' => 'https://rejected-candidate.example',
        ]);
        $this->actingAs($actor)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$rejectCandidate->public_id.'/reject', [
                'reason_code' => 'irrelevant',
            ])->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame($unitsBefore + 1, Unit::query()->count());
        $this->assertSame($entitiesBefore, Entity::query()->count());
        $this->assertDatabaseCount('entity_unit', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @return array{User, ProspectingCandidate, Unit, Unit, \App\Models\Product} */
    private function investigationFixture(): array
    {
        $actor = $this->prospectingUser(['sales', 'procurement']);
        $job = $this->approvedJob($actor);
        $product = $job->products()->without(['category', 'manufacturers'])->firstOrFail();
        $product->update(['rus' => 'Брокколи']);
        $query = app(ProspectingSearchQueryService::class)->recordFixture($job, [
            'safe_display_query' => 'repository-owned broccoli buyers',
            'geography' => 'Saint Petersburg',
        ], true);
        $firstHash = hash('sha256', 'candidate-investigation-result-1');
        $secondHash = hash('sha256', 'candidate-investigation-result-2');
        $seoTitle = 'Капуста брокколи оптом — купить с доставкой в СПб';
        $candidate = app(ProspectingCandidateService::class)->createFixture($job, [
            'working_name' => $seoTitle,
            'website' => 'https://buyer-investigation.example',
            'location_display' => 'Санкт-Петербург',
            'public_activity_summary' => 'Public food-service buyer in Saint Petersburg.',
            'relevance_summary' => 'Брокколи нужна покупателю для публичного ресторанного меню.',
            'confidence_components' => ['relevance' => 88],
            'product_ids' => [$product->id],
            'sources' => [[
                'type' => 'public_search',
                'reference' => 'search-result:'.$firstHash,
                'url' => 'https://buyer-investigation.example/broccoli',
                'title' => $seoTitle,
                'excerpt' => 'Safe bounded broccoli source excerpt.',
                'confidence' => 82,
                'source_quality' => 75,
            ], [
                'type' => 'public_fetch',
                'reference' => 'search-result:'.$secondHash,
                'url' => 'https://buyer-investigation.example/about',
                'title' => 'О публичной деятельности сайта',
                'excerpt' => 'Safe bounded company activity excerpt.',
                'confidence' => 79,
                'source_quality' => 72,
            ]],
            'channels' => [[
                'kind' => 'email',
                'value' => 'sales@buyer-investigation.example',
                'contact_role' => 'business_general',
            ], [
                'kind' => 'email',
                'value' => 'private.person@buyer-investigation.example',
                'contact_role' => 'person_specific',
            ]],
        ], $actor, true, $query);
        app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);

        $execution = ProspectingSearchExecution::query()->create([
            'prospecting_search_job_id' => $job->id,
            'prospecting_search_query_id' => $query->id,
            'initiated_by' => $actor->id,
            'profile_code' => 'repository_fixture',
            'provider_code' => 'fake',
            'request_hash' => hash('sha256', 'candidate-investigation-request'),
            'plan_hash' => hash('sha256', 'candidate-investigation-plan'),
            'status' => 'completed',
            'attempt' => 1,
            'request_count' => 0,
            'result_count' => 2,
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
        ]);
        foreach ([
            [$firstHash, 1, 'https://buyer-investigation.example/broccoli', $seoTitle],
            [$secondHash, 2, 'https://buyer-investigation.example/about', 'О публичной деятельности сайта'],
        ] as [$resultHash, $rank, $url, $title]) {
            $result = ProspectingSearchResult::query()->create([
                'prospecting_search_execution_id' => $execution->id,
                'prospecting_search_job_id' => $job->id,
                'prospecting_search_query_id' => $query->id,
                'rank' => $rank,
                'result_type' => 'organic',
                'title' => $title,
                'snippet' => 'Safe synthetic snippet.',
                'url' => $url,
                'canonical_url' => $url,
                'url_hash' => hash('sha256', $url),
                'registrable_domain' => 'buyer-investigation.example',
                'domain_hash' => hash('sha256', 'buyer-investigation.example'),
                'result_hash' => $resultHash,
                'prospecting_candidate_id' => $candidate->id,
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
                'fetch_status' => 'completed',
                'research_status' => 'completed',
            ]);
            ProspectingPublicFetch::query()->create([
                'prospecting_search_result_id' => $result->id,
                'status' => 'completed',
                'final_url' => $url,
                'final_url_hash' => hash('sha256', $url),
                'registrable_domain' => 'buyer-investigation.example',
                'content_type' => 'text/html',
                'byte_count' => 512,
                'page_title' => $title,
                'text_excerpt' => 'RAW_HTML_MUST_NEVER_LEAVE_SERVER',
                'protected_channels' => ['PROTECTED_CHANNEL_MUST_NOT_LEAK'],
                'channel_count' => 1,
                'content_hash' => hash('sha256', 'candidate-investigation-content-'.$rank),
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
                'robots_status' => 'allowed',
                'fetched_at' => now()->subDays($rank),
            ]);
            ProspectingPublicResearchRecord::query()->create([
                'prospecting_search_result_id' => $result->id,
                'workflow_code' => 'public_company_research.v1',
                'workflow_version' => 'stage09-v1',
                'workflow_hash' => hash('sha256', 'candidate-investigation-workflow'),
                'status' => 'completed',
                'input_hash' => hash('sha256', 'candidate-investigation-input-'.$rank),
                'output_hash' => hash('sha256', 'candidate-investigation-output-'.$rank),
                'schema_valid' => true,
                'safe_summary' => 'Safe reviewed public research summary '.$rank.'.',
                'activity_mentions' => ['food-service wholesale'],
                'location_hints' => ['Санкт-Петербург'],
                'product_mentions' => ['broccoli wholesale'],
                'provider_code' => 'fake',
                'completed_at' => now()->subDays($rank),
            ]);
        }

        $source = $candidate->sources()->where('source_reference', 'search-result:'.$firstHash)->firstOrFail();
        $candidate->channels()->where('channel_kind', 'email')->where('contact_role', 'business_general')->update([
            'prospecting_candidate_source_id' => $source->id,
            'verification_status' => 'verified',
            'last_verified_at' => now(),
        ]);
        $candidate->channels()->where('channel_kind', 'email')->where('contact_role', 'person_specific')->update([
            'prospecting_candidate_source_id' => $source->id,
            'verification_status' => 'verified',
            'last_verified_at' => now(),
        ]);

        $countryId = DB::table('countries')->insertGetId(['name' => 'Safe Country', 'сodeISO' => 'SC']);
        $regionId = DB::table('regions')->insertGetId(['name' => 'Safe Region', 'country_id' => $countryId]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Safe City', 'region_id' => $regionId]);
        $sameLaneUnit = $this->unit(['name' => 'Existing Sales Buyer']);
        $sameContext = $this->createContext($actor, $sameLaneUnit, ['lane' => 'sales', 'role_code' => 'prospective_customer']);
        $sameLaneUnit->cities()->attach($cityId);
        app(UnitAliasService::class)->create($sameLaneUnit, [
            'unit_business_context_id' => $sameContext['id'],
            'alias' => 'buyer-investigation.example',
            'alias_type' => 'domain_name',
            'data_classification' => 'public',
            'visibility_scope' => 'sales_lane',
        ], $actor);
        $crossLaneUnit = $this->unit(['name' => 'Hidden Procurement Match']);
        $crossContext = $this->createContext($actor, $crossLaneUnit, ['lane' => 'procurement', 'role_code' => 'prospective_supplier']);
        app(UnitAliasService::class)->create($crossLaneUnit, [
            'unit_business_context_id' => $crossContext['id'],
            'alias' => 'buyer-investigation.example',
            'alias_type' => 'domain_name',
            'data_classification' => 'public',
            'visibility_scope' => 'procurement_lane',
        ], $actor);
        foreach ([[$sameLaneUnit, 85, 1], [$crossLaneUnit, 80, 2]] as [$unit, $strength, $rank]) {
            ProspectingCandidateUnitMatch::query()->create([
                'prospecting_candidate_id' => $candidate->id,
                'unit_id' => $unit->id,
                'signal_code' => 'normalized_or_fuzzy_name_review',
                'strength' => $strength,
                'rank' => $rank,
                'evidence_hash' => hash('sha256', 'candidate-investigation-match-'.$unit->id),
                'evidence_reference' => 'deterministic:repository-fixture',
                'review_status' => 'suggested',
            ]);
        }

        return [$actor, $candidate->fresh(), $sameLaneUnit, $crossLaneUnit, $product];
    }

    /** @return array<string, int> */
    private function readOnlyCounts(): array
    {
        return [
            'candidates' => ProspectingCandidate::query()->count(),
            'sources' => DB::table('prospecting_candidate_sources')->count(),
            'channels' => DB::table('prospecting_candidate_channels')->count(),
            'results' => ProspectingSearchResult::query()->count(),
            'fetches' => ProspectingPublicFetch::query()->count(),
            'research' => ProspectingPublicResearchRecord::query()->count(),
            'units' => Unit::query()->count(),
            'entities' => Entity::query()->count(),
            'emails' => DB::table('emails')->count(),
        ];
    }
}
