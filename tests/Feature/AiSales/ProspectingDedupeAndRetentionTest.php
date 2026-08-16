<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\CandidateResolutionOutcome;
use App\Domain\AiSales\Services\ProspectingRetentionService;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Domain\AiSales\Services\UnitAliasService;
use App\Models\ProspectingCandidate;
use App\Models\UnitObservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectingDedupeAndRetentionTest extends Stage08TestCase
{
    public function test_name_and_city_match_is_review_only_and_ambiguous_candidate_cannot_create_or_merge(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedJob($actor);
        $countryId = DB::table('countries')->insertGetId(['name' => 'Synthetic Country', 'сodeISO' => 'SX']);
        $regionId = DB::table('regions')->insertGetId(['name' => 'Synthetic Region', 'country_id' => $countryId]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Synthetic City', 'region_id' => $regionId]);
        $first = $this->unit(['name' => 'Same Synthetic Name']);
        $second = $this->unit(['name' => 'Same Synthetic Name']);
        $first->cities()->attach($cityId);
        $second->cities()->attach($cityId);
        $candidate = $this->candidate($job, $actor, [
            'working_name' => 'Same Synthetic Name',
            'website' => null,
            'city_id' => $cityId,
            'location_display' => 'Synthetic City',
        ]);

        $decision = app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $this->assertSame(CandidateResolutionOutcome::ProbableExistingReview, $decision->outcome);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $decision->matchedUnitIds);
        $unitsBefore = DB::table('units')->count();
        $this->expectException(ValidationException::class);
        try {
            app(ResolveProspectingCandidate::class)->createNewUnit($candidate->fresh(), $actor);
        } finally {
            $this->assertSame($unitsBefore, DB::table('units')->count());
            $this->assertDatabaseCount('entity_unit', 0);
        }
    }

    public function test_name_and_region_match_is_review_only_when_city_is_unknown(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedJob($actor);
        $countryId = DB::table('countries')->insertGetId(['name' => 'Synthetic Region Country', 'сodeISO' => 'SR']);
        $regionId = DB::table('regions')->insertGetId(['name' => 'Synthetic Match Region', 'country_id' => $countryId]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Synthetic Region City', 'region_id' => $regionId]);
        $unit = $this->unit(['name' => 'Regional Synthetic Name']);
        $unit->cities()->attach($cityId);
        $candidate = $this->candidate($job, $actor, [
            'working_name' => 'Regional Synthetic Name',
            'website' => null,
            'region_id' => $regionId,
            'location_display' => 'Synthetic Match Region',
        ]);

        $decision = app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);

        $this->assertSame(CandidateResolutionOutcome::ProbableExistingReview, $decision->outcome);
        $this->assertSame([$unit->id], $decision->matchedUnitIds);
        $this->assertContains('normalized_name_exact_region', $decision->signalCodes);
        $this->assertTrue($decision->humanReviewRequired);
    }

    public function test_candidate_fingerprint_is_idempotent_and_contradictory_observations_coexist(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedJob($actor);
        $attributes = [
            'working_name' => 'Idempotent Candidate',
            'website' => 'https://idempotent-stage08.example',
            'public_activity_summary' => 'First bounded statement.',
            'relevance_summary' => 'Synthetic relevance.',
            'confidence_components' => ['relevance' => 90],
            'sources' => [['type' => 'synthetic_fixture', 'reference' => 'fixture:one', 'excerpt' => 'One']],
        ];
        $service = app(\App\Domain\AiSales\Services\ProspectingCandidateService::class);
        $first = $service->createFixture($job, $attributes, $actor, true);
        $second = $service->createFixture($job, $attributes, $actor, true);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProspectingCandidate::query()->count());

        $unit = $this->unit();
        $context = $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer']);
        foreach (['Statement A', 'Statement B'] as $summary) {
            UnitObservation::query()->create([
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context['id'],
                'observation_key' => 'prospecting.conflict',
                'summary' => $summary,
                'verification_status' => 'unverified',
                'data_classification' => 'public',
                'visibility_scope' => 'sales_lane',
                'observed_at' => now(),
                'created_by_type' => 'human',
                'created_by_user_id' => $actor->id,
            ]);
        }
        $this->assertSame(2, UnitObservation::query()->where('observation_key', 'prospecting.conflict')->count());
    }

    public function test_fuzzy_or_name_only_signal_requires_review_and_never_auto_creates(): void
    {
        $actor = $this->prospectingUser();
        $this->unit(['name' => 'Fuzzy Synthetic Company']);
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'Fuzzy Synthetic Compny',
            'website' => null,
        ]);
        $decision = app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $this->assertSame(CandidateResolutionOutcome::ProbableExistingReview, $decision->outcome);
        $this->assertContains('normalized_or_fuzzy_name_review', $decision->signalCodes);
        $this->assertTrue($decision->humanReviewRequired);
    }

    public function test_opposite_lane_restricted_alias_is_not_a_sales_identity_signal(): void
    {
        $actor = $this->prospectingUser(['sales', 'procurement'], ['ai_sales.classifications.view_internal']);
        $unit = $this->unit(['name' => 'Supplier canonical identity']);
        $context = $this->createContext($actor, $unit, ['lane' => 'procurement', 'role_code' => 'prospective_supplier']);
        app(UnitAliasService::class)->create($unit, [
            'unit_business_context_id' => $context['id'],
            'alias' => 'Restricted Supplier Alias',
            'alias_type' => 'trade_name',
            'data_classification' => 'commercial_confidential',
            'visibility_scope' => 'procurement_lane',
        ], $actor);
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'Restricted Supplier Alias',
            'website' => null,
        ]);

        $decision = app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $this->assertSame(CandidateResolutionOutcome::NewUnitAllowed, $decision->outcome);
        $this->assertSame([], $decision->matchedUnitIds);
    }

    public function test_provider_neutral_query_fixture_is_bounded_idempotent_and_never_executed(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedJob($actor);
        $service = app(\App\Domain\AiSales\Services\ProspectingSearchQueryService::class);
        $attributes = [
            'safe_display_query' => 'synthetic food buyer fixture',
            'language' => 'en',
            'geography' => 'Synthetic Region',
            'industry_intent' => 'repository-only',
        ];
        $first = $service->recordFixture($job, $attributes, true);
        $second = $service->recordFixture($job, $attributes, true);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('fixture_recorded', $first->status);
        $this->assertNull($first->executed_at);
        $this->assertNull($first->search_provider_reference);
        $this->assertDatabaseCount('prospecting_search_queries', 1);
    }

    public function test_retention_is_dry_run_by_default_chunked_and_idempotently_anonymizes_transient_values(): void
    {
        $actor = $this->prospectingUser();
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'Expiring Candidate',
            'channels' => [['kind' => 'email', 'value' => 'expire@stage08.example', 'contact_role' => 'person_specific']],
        ]);
        $candidate->update(['expires_at' => now()->subMinute()]);
        $retention = app(ProspectingRetentionService::class);
        $dry = $retention->prune(false, 1);
        $this->assertSame(1, $dry['eligible_candidates']);
        $this->assertNull($candidate->fresh()->anonymized_at);
        $apply = $retention->prune(true, 1);
        $this->assertSame(1, $apply['anonymized_candidates']);
        $this->assertSame('anonymized', $candidate->fresh()->status->value);
        $this->assertNull($candidate->fresh()->canonical_website);
        $this->assertSame(0, $candidate->channels()->count());
        $this->assertNull($candidate->sources()->first()->canonical_url);
        $again = $retention->prune(true, 1);
        $this->assertSame(0, $again['eligible_candidates']);
        $this->assertSame(0, $again['anonymized_candidates']);
    }

    public function test_person_specific_transient_channel_has_shorter_retention_than_candidate(): void
    {
        $actor = $this->prospectingUser();
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'channels' => [['kind' => 'email', 'value' => 'short-retention@stage08.example', 'contact_role' => 'person_specific']],
        ]);
        $candidate->channels()->where('data_classification', 'personal_data')->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $retention = app(ProspectingRetentionService::class);
        $dry = $retention->prune(false, 1);
        $this->assertSame(0, $dry['eligible_candidates']);
        $this->assertSame(1, $dry['eligible_personal_channels']);
        $retention->prune(true, 1);
        $this->assertDatabaseHas('prospecting_candidates', ['id' => $candidate->id, 'anonymized_at' => null]);
        $this->assertSame(0, $candidate->channels()->where('data_classification', 'personal_data')->count());
        $this->assertGreaterThanOrEqual(1, $candidate->channels()->count());
    }
}
