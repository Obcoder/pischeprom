<?php

namespace Tests\Feature\AiSales;

use App\Models\Entity;
use App\Models\UnitObservation;

class UnitBusinessContextsTest extends UnitContextsTestCase
{
    public function test_unit_can_hold_customer_and_supplier_contexts_simultaneously(): void
    {
        $actor = $this->manager();
        $unit = $this->unit();

        $sales = $this->createContext($actor, $unit, [
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
        ]);
        $procurement = $this->createContext($actor, $unit, [
            'lane' => 'procurement',
            'role_code' => 'supplier',
        ]);

        $this->assertSame('sales', $sales['lane']);
        $this->assertSame('procurement', $procurement['lane']);
        $this->assertDatabaseHas('unit_business_contexts', [
            'unit_id' => $unit->id,
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
        ]);
        $this->assertDatabaseHas('unit_business_contexts', [
            'unit_id' => $unit->id,
            'lane' => 'procurement',
            'role_code' => 'supplier',
        ]);
        $this->assertTrue($unit->fresh()->is_customer);
        $this->assertTrue($unit->fresh()->is_supplier);
    }

    public function test_context_survives_entity_link_and_converted_stage_does_not_delete_unit(): void
    {
        $actor = $this->manager();
        $unit = $this->unit();
        $context = $this->createContext($actor, $unit, [
            'lane' => 'sales',
            'role_code' => 'customer',
        ]);
        $entity = Entity::query()->create(['name' => 'ООО Проверенная Entity']);
        $unit->entities()->attach($entity->id);

        $this->actingAs($actor)
            ->patchJson("/api/ai-sales/units/{$unit->id}/contexts/{$context['id']}", [
                'stage' => 'converted_entity_linked',
            ])
            ->assertOk()
            ->assertJsonPath('data.stage', 'converted_entity_linked');

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
        $this->assertDatabaseHas('entity_unit', ['unit_id' => $unit->id, 'entity_id' => $entity->id]);
        $this->assertDatabaseHas('unit_business_contexts', ['id' => $context['id'], 'unit_id' => $unit->id]);
    }

    public function test_observations_do_not_overwrite_canonical_fields_and_contradictions_coexist(): void
    {
        $actor = $this->manager();
        $unit = $this->unit(['name' => 'Canonical name']);

        $first = $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations", [
                'observation_key' => 'unit.name',
                'normalized_value' => 'First observed name',
                'summary' => 'Источник утверждает первое название.',
                'source_reference' => 'synthetic:first',
                'confidence' => 70,
                'data_classification' => 'public',
                'visibility_scope' => 'shared_public',
            ])
            ->assertCreated()
            ->json('data');

        $second = $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations", [
                'observation_key' => 'unit.name',
                'normalized_value' => 'Second observed name',
                'summary' => 'Другой источник утверждает второе название.',
                'source_reference' => 'synthetic:second',
                'confidence' => 65,
                'data_classification' => 'public',
                'visibility_scope' => 'shared_public',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('Canonical name', $unit->fresh()->name);

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations/{$second['id']}/review", [
                'verification_status' => 'contradicted',
            ])
            ->assertOk();

        $this->assertSame(2, UnitObservation::query()->where('unit_id', $unit->id)->where('observation_key', 'unit.name')->count());
        $this->assertDatabaseHas('unit_observations', ['id' => $first['id'], 'verification_status' => 'unverified']);
        $this->assertDatabaseHas('unit_observations', ['id' => $second['id'], 'verification_status' => 'contradicted']);
        $this->assertSame('Canonical name', $unit->fresh()->name);
    }

    public function test_explicit_verified_promotion_is_audited_and_alias_never_creates_entity(): void
    {
        $actor = $this->manager();
        $unit = $this->unit(['name' => 'Old canonical']);
        $entitiesBefore = Entity::query()->count();

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/aliases", [
                'alias' => '  Новый   Бренд  ',
                'alias_type' => 'brand',
                'confidence' => 80,
                'data_classification' => 'public',
                'visibility_scope' => 'shared_public',
            ])
            ->assertCreated();

        $this->assertSame($entitiesBefore, Entity::query()->count());
        $this->assertDatabaseHas('unit_aliases', ['unit_id' => $unit->id, 'normalized_alias' => 'новый бренд']);

        $observationId = $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations", [
                'observation_key' => 'unit.name',
                'normalized_value' => 'New canonical',
                'summary' => 'Проверяемое новое имя.',
                'data_classification' => 'public',
                'visibility_scope' => 'shared_public',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations/{$observationId}/review", [
                'verification_status' => 'verified',
            ])
            ->assertOk();
        $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations/{$observationId}/promote")
            ->assertOk()
            ->assertJsonPath('data.name', 'New canonical');

        $this->assertDatabaseHas('unit_dossier_audit_events', [
            'unit_id' => $unit->id,
            'event_type' => 'unit.observation.promoted',
        ]);
        $this->assertSame($entitiesBefore, Entity::query()->count());
    }
}
