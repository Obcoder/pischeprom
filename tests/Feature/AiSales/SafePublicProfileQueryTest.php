<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Queries\UnitSharedPublicProfileQuery;
use App\Models\UnitAlias;
use App\Models\UnitContactContextLink;
use App\Models\UnitObservation;
use App\Models\Uri;

class SafePublicProfileQueryTest extends UnitContextsTestCase
{
    public function test_profile_uses_only_explicit_verified_shared_public_facts_and_contact_links(): void
    {
        $unit = $this->unit(['name' => 'Public Unit']);
        UnitAlias::query()->create([
            'unit_id' => $unit->id,
            'alias' => 'Verified public alias',
            'normalized_alias' => 'verified public alias',
            'alias_type' => 'trade_name',
            'verification_status' => 'verified',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
        ]);
        UnitAlias::query()->create([
            'unit_id' => $unit->id,
            'alias' => 'Internal alias must stay local',
            'normalized_alias' => 'internal alias must stay local',
            'alias_type' => 'other',
            'verification_status' => 'verified',
            'data_classification' => 'internal',
            'visibility_scope' => 'internal_only',
        ]);
        UnitObservation::query()->create([
            'unit_id' => $unit->id,
            'observation_key' => 'unit.public_fact',
            'summary' => 'Verified public fact',
            'verification_status' => 'verified',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
            'observed_at' => now(),
            'created_by_type' => 'system',
        ]);
        UnitObservation::query()->create([
            'unit_id' => $unit->id,
            'observation_key' => 'mail.body',
            'summary' => 'RAW_CORRESPONDENCE_MUST_NOT_EXPORT',
            'verification_status' => 'verified',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
            'observed_at' => now(),
            'created_by_type' => 'system',
        ]);
        UnitObservation::query()->create([
            'unit_id' => $unit->id,
            'observation_key' => 'unit.unverified_fact',
            'summary' => 'Unverified fact must stay local',
            'verification_status' => 'unverified',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
            'observed_at' => now(),
            'created_by_type' => 'system',
        ]);
        $explicitUri = Uri::query()->create(['address' => 'https://public.example.test']);
        $legacyUri = Uri::query()->create(['address' => 'https://legacy-unclassified.example.test']);
        $unit->uris()->attach([$explicitUri->id, $legacyUri->id]);
        UnitContactContextLink::query()->create([
            'unit_id' => $unit->id,
            'channel_type' => 'uri',
            'uri_id' => $explicitUri->id,
            'channel_value_snapshot' => $explicitUri->address,
            'verification_status' => 'verified',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
        ]);

        $fields = app(UnitSharedPublicProfileQuery::class)->get($unit->id)->fields();
        $encoded = json_encode($fields, JSON_UNESCAPED_UNICODE);

        $this->assertSame('Public Unit', $fields['name']);
        $this->assertSame(['Verified public alias'], $fields['aliases']);
        $this->assertSame(['Verified public fact'], $fields['observations']);
        $this->assertSame(['https://public.example.test'], $fields['public_uris']);
        $this->assertStringNotContainsString('Internal alias', $encoded);
        $this->assertStringNotContainsString('Unverified fact', $encoded);
        $this->assertStringNotContainsString('legacy-unclassified', $encoded);
        $this->assertStringNotContainsString('RAW_CORRESPONDENCE_MUST_NOT_EXPORT', $encoded);
        $this->assertArrayNotHasKey('entities', $fields);
        $this->assertArrayNotHasKey('sales', $fields);
        $this->assertArrayNotHasKey('purchases', $fields);
    }
}
