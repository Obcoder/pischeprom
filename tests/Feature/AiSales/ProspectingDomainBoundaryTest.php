<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\CandidateResolutionOutcome;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Unit;
use App\Models\UnitContactContextLink;
use App\Models\Uri;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProspectingDomainBoundaryTest extends Stage08TestCase
{
    public function test_stage08_schema_preserves_unit_entity_and_transaction_boundaries(): void
    {
        $this->assertTrue(Schema::hasTable('leads'));
        $this->assertFalse(Schema::hasTable('unit_contacts'));
        $this->assertFalse(Schema::hasTable('unit_events'));
        $this->assertFalse(Schema::hasTable('prospect_score_snapshots'));
        $this->assertFalse(class_exists(\App\Models\ProspectingLead::class));
        foreach (['prospecting_candidates', 'prospecting_candidate_sources', 'prospecting_candidate_channels'] as $table) {
            $columns = Schema::getColumnListing($table);
            $this->assertEmpty(array_intersect($columns, ['raw_body', 'provider_body', 'full_html', 'prompt', 'api_key', 'token']));
        }
        $this->assertFalse(Schema::hasColumn('units', 'sale_id'));
        $this->assertFalse(Schema::hasColumn('units', 'purchase_id'));
    }

    public function test_exact_verified_domain_enriches_existing_unit_idempotently_without_entity_mutation(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedJob($actor);
        $unit = $this->unit(['name' => 'Existing canonical name']);
        $context = $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer']);
        $uri = Uri::query()->create(['address' => 'https://exact-stage08.example']);
        $unit->uris()->syncWithoutDetaching([$uri->id]);
        UnitContactContextLink::query()->create([
            'unit_id' => $unit->id, 'unit_business_context_id' => $context['id'], 'channel_type' => 'uri',
            'uri_id' => $uri->id, 'channel_value_snapshot' => 'exact-stage08.example',
            'normalized_hash' => hash('sha256', 'uri|https://exact-stage08.example'),
            'contact_role' => 'business_general', 'verification_status' => ObservationVerificationStatus::Verified,
            'data_classification' => DataClassification::Public, 'visibility_scope' => UnitVisibilityScope::SalesLane,
            'communication_state' => 'review_required', 'review_required' => true,
        ]);
        $candidate = $this->candidate($job, $actor, [
            'working_name' => 'Observed trade alias',
            'website' => 'https://exact-stage08.example',
            'channels' => [['kind' => 'uri', 'value' => 'https://exact-stage08.example', 'contact_role' => 'business_general']],
        ]);
        $entitiesBefore = Entity::query()->count();
        $decision = app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $this->assertSame(CandidateResolutionOutcome::ExactExisting, $decision->outcome);
        $this->assertSame([$unit->id], $decision->matchedUnitIds);
        $resolved = app(ResolveProspectingCandidate::class)->enrichExisting($candidate->fresh(), $unit, $actor);
        $this->assertDatabaseHas('prospecting_candidate_unit_matches', [
            'prospecting_candidate_id' => $candidate->id,
            'unit_id' => $unit->id,
            'review_status' => 'accepted',
        ]);
        $sourceCount = $resolved->sources()->count();
        $aliasCount = $resolved->aliases()->count();
        $contactCount = $resolved->contactContextLinks()->count();
        $goodMatchCount = $resolved->goodMatches()->count();
        app(ResolveProspectingCandidate::class)->enrichExisting($candidate->fresh(), $unit, $actor);
        $this->assertSame($sourceCount, $resolved->sources()->count());
        $this->assertSame($aliasCount, $resolved->aliases()->count());
        $this->assertSame($contactCount, $resolved->contactContextLinks()->count());
        $this->assertSame($goodMatchCount, $resolved->goodMatches()->count());

        $secondJob = $this->approvedJob($actor, 'buyer_discovery', $job->primaryGood);
        $secondCandidate = $this->candidate($secondJob, $actor, [
            'working_name' => 'Observed trade alias',
            'website' => 'https://exact-stage08.example',
            'channels' => [['kind' => 'uri', 'value' => 'https://exact-stage08.example', 'contact_role' => 'business_general']],
        ]);
        app(ResolveProspectingCandidate::class)->enrichExisting($secondCandidate, $unit, $actor);
        $this->assertSame($sourceCount, $resolved->sources()->count());
        $this->assertSame($aliasCount, $resolved->aliases()->count());
        $this->assertSame($contactCount, $resolved->contactContextLinks()->count());
        $this->assertSame($goodMatchCount, $resolved->goodMatches()->count());
        $this->assertSame('Existing canonical name', $unit->fresh()->name);
        $this->assertSame($entitiesBefore, Entity::query()->count());
        $this->assertDatabaseHas('unit_aliases', ['unit_id' => $unit->id, 'normalized_alias' => 'observed trade alias']);
        $this->assertDatabaseHas('unit_good_matches', ['unit_id' => $unit->id, 'match_type' => 'potential_need', 'origin' => 'candidate']);
    }

    public function test_human_approved_new_unit_reuses_contacts_but_creates_no_entity_transaction_or_consent(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedJob($actor);
        $candidate = $this->candidate($job, $actor, [
            'working_name' => 'Synthetic Working Dossier',
            'website' => 'https://new-stage08.example',
            'channels' => [
                ['kind' => 'email', 'value' => 'office@new-stage08.example', 'contact_role' => 'business_general'],
                ['kind' => 'telephone', 'value' => '+79990001122', 'contact_role' => 'person_specific'],
                ['kind' => 'uri', 'value' => 'https://new-stage08.example', 'contact_role' => 'business_general', 'communication_state' => 'do_not_contact'],
            ],
        ]);
        $rawEncrypted = DB::table('prospecting_candidate_channels')->where('channel_kind', 'email')->value('protected_value');
        $this->assertNotSame('office@new-stage08.example', $rawEncrypted);
        $entities = Entity::query()->count();
        $sales = DB::table('sales')->count();
        $purchases = DB::table('purchases')->count();
        $unit = app(ResolveProspectingCandidate::class)->createNewUnit($candidate, $actor);
        $unitCount = Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->count();
        $replayedUnit = app(ResolveProspectingCandidate::class)->createNewUnit($candidate->fresh(), $actor);

        $this->assertSame('Synthetic Working Dossier', $unit->name);
        $this->assertSame($unit->id, $replayedUnit->id);
        $this->assertSame($unitCount, Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->count());
        $this->assertDatabaseHas('unit_business_contexts', ['unit_id' => $unit->id, 'lane' => 'sales', 'role_code' => 'prospective_customer', 'stage' => 'researching']);
        $this->assertDatabaseHas('unit_sources', ['unit_id' => $unit->id, 'source_type' => 'prospecting_candidate']);
        $this->assertDatabaseHas('unit_dossier_audit_events', ['unit_id' => $unit->id, 'event_type' => 'prospecting.candidate.created_unit']);
        $this->assertDatabaseHas('emails', ['address' => 'office@new-stage08.example']);
        $this->assertDatabaseHas('telephones', ['number' => '+79990001122']);
        $this->assertDatabaseHas('unit_contact_context_links', ['unit_id' => $unit->id, 'data_classification' => 'personal_data', 'visibility_scope' => 'internal_only', 'review_required' => 1]);
        $this->assertDatabaseHas('unit_contact_context_links', ['unit_id' => $unit->id, 'communication_state' => 'do_not_contact']);
        $this->assertSame($entities, Entity::query()->count());
        $this->assertSame($sales, DB::table('sales')->count());
        $this->assertSame($purchases, DB::table('purchases')->count());
        $this->assertFalse(Schema::hasColumn('unit_contact_context_links', 'consent'));
    }

    public function test_verified_corporate_email_domain_is_an_explainable_exact_signal(): void
    {
        $actor = $this->prospectingUser();
        $unit = $this->unit(['name' => 'Corporate email owner']);
        $context = $this->createContext($actor, $unit, ['lane' => 'sales', 'role_code' => 'prospective_customer']);
        $email = Email::query()->create(['address' => 'office@corporate-stage08.example', 'is_active' => true]);
        $unit->emails()->syncWithoutDetaching([$email->id]);
        UnitContactContextLink::query()->create([
            'unit_id' => $unit->id, 'unit_business_context_id' => $context['id'], 'channel_type' => 'email',
            'email_id' => $email->id, 'channel_value_snapshot' => 'of***@corporate-stage08.example',
            'normalized_hash' => null,
            'contact_role' => 'business_general', 'verification_status' => 'verified',
            'data_classification' => 'public', 'visibility_scope' => 'sales_lane',
            'communication_state' => 'review_required', 'review_required' => true,
        ]);
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'Corporate Candidate',
            'website' => 'https://another-public-stage08.example',
            'channels' => [['kind' => 'email', 'value' => 'office@corporate-stage08.example', 'contact_role' => 'business_general']],
        ]);
        $decision = app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $this->assertSame(CandidateResolutionOutcome::ExactExisting, $decision->outcome);
        $this->assertContains('exact_corporate_email_domain', $decision->signalCodes);
        $this->assertSame([$unit->id], $decision->matchedUnitIds);
        $this->assertDatabaseHas('prospecting_candidate_unit_matches', [
            'prospecting_candidate_id' => $candidate->id,
            'unit_id' => $unit->id,
            'signal_code' => 'exact_corporate_email_domain',
            'strength' => 95,
        ]);
        app(ResolveProspectingCandidate::class)->enrichExisting($candidate->fresh(), $unit, $actor);
        $this->assertSame(1, $unit->contactContextLinks()->where('channel_type', 'email')->count());
        $this->assertDatabaseHas('unit_contact_context_links', [
            'unit_id' => $unit->id,
            'email_id' => $email->id,
            'normalized_hash' => hash('sha256', 'email|office@corporate-stage08.example'),
        ]);
    }

    public function test_candidate_without_source_is_rejected_and_cannot_create_unit(): void
    {
        $actor = $this->prospectingUser();
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'No Source Candidate',
            'sources' => [],
        ]);
        $decision = app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $this->assertSame(CandidateResolutionOutcome::RejectedInvalid, $decision->outcome);
        $units = Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->count();
        try {
            app(ResolveProspectingCandidate::class)->createNewUnit($candidate->fresh(), $actor);
            $this->fail('Invalid candidate must not create a Unit.');
        } catch (\DomainException) {
            $this->assertSame($units, Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->count());
        }
    }
}
