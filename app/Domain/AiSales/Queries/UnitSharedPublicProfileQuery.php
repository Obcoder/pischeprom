<?php

namespace App\Domain\AiSales\Queries;

use App\Domain\AiSales\DTO\Units\UnitSharedPublicProfile;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UnitSharedPublicProfileQuery
{
    private const PUBLIC_OBSERVATION_KEYS = [
        'unit.public_fact',
        'unit.profile_fact',
        'unit.description',
        'unit.business_summary',
        'unit.capability',
        'unit.certification',
        'unit.location_summary',
    ];

    public function get(int $unitId): UnitSharedPublicProfile
    {
        $unit = Unit::query()
            ->without(['fields', 'labels', 'telephones', 'uris'])
            ->select(['id', 'name'])
            ->findOrFail($unitId);
        $aliases = $unit->aliases()
            ->select(['unit_aliases.id', 'unit_aliases.alias'])
            ->where('verification_status', ObservationVerificationStatus::Verified->value)
            ->where('data_classification', DataClassification::Public->value)
            ->where('visibility_scope', UnitVisibilityScope::SharedPublic->value)
            ->orderBy('unit_aliases.id')
            ->limit(20)
            ->pluck('alias')
            ->all();
        $observations = $unit->observations()
            ->select(['unit_observations.id', 'unit_observations.summary'])
            ->whereNull('unit_business_context_id')
            ->whereIn('observation_key', self::PUBLIC_OBSERVATION_KEYS)
            ->where('verification_status', ObservationVerificationStatus::Verified->value)
            ->where('data_classification', DataClassification::Public->value)
            ->where('visibility_scope', UnitVisibilityScope::SharedPublic->value)
            ->orderBy('unit_observations.id')
            ->limit(25)
            ->pluck('summary')
            ->all();
        $industries = $unit->industries()
            ->select(['industries.id', 'industries.title'])
            ->orderBy('industries.title')
            ->limit(20)
            ->pluck('industries.title')
            ->all();
        $cities = $unit->cities()
            ->select(['cities.id', 'cities.name'])
            ->orderBy('cities.name')
            ->limit(20)
            ->pluck('cities.name')
            ->all();
        $uris = DB::table('unit_contact_context_links')
            ->join('uris', 'uris.id', '=', 'unit_contact_context_links.uri_id')
            ->where('unit_contact_context_links.unit_id', $unit->id)
            ->whereNull('unit_contact_context_links.archived_at')
            ->where('unit_contact_context_links.verification_status', ObservationVerificationStatus::Verified->value)
            ->where('unit_contact_context_links.data_classification', DataClassification::Public->value)
            ->where('unit_contact_context_links.visibility_scope', UnitVisibilityScope::SharedPublic->value)
            ->orderBy('unit_contact_context_links.id')
            ->limit(10)
            ->pluck('uris.address')
            ->all();

        return new UnitSharedPublicProfile($unit->name, $aliases, $industries, $cities, $uris, $observations);
    }
}
