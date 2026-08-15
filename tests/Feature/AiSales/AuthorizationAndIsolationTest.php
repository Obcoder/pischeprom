<?php

namespace Tests\Feature\AiSales;

use App\Models\Entity;

class AuthorizationAndIsolationTest extends UnitContextsTestCase
{
    public function test_all_new_routes_require_authentication_and_server_side_permissions(): void
    {
        $unit = $this->unit();

        $this->getJson("/api/ai-sales/units/{$unit->id}/dossier")->assertUnauthorized();
        $this->postJson("/api/ai-sales/units/{$unit->id}/contexts", [
            'lane' => 'sales',
            'role_code' => 'customer',
        ])->assertUnauthorized();

        $viewer = $this->userWith(['ai_sales.view', 'ai_sales.sales.view']);
        $this->actingAs($viewer)
            ->getJson("/api/ai-sales/units/{$unit->id}/dossier")
            ->assertOk();
        $this->actingAs($viewer)
            ->postJson("/api/ai-sales/units/{$unit->id}/roles", ['role_code' => 'customer'])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->postJson("/api/ai-sales/units/{$unit->id}/contexts", [
                'lane' => 'sales',
                'role_code' => 'customer',
            ])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations", [
                'observation_key' => 'unit.fact',
                'summary' => 'Unauthorized write',
                'data_classification' => 'public',
                'visibility_scope' => 'shared_public',
            ])
            ->assertForbidden();

        $manager = $this->manager();
        $observation = $this->createObservation($manager, $unit->id, [
            'observation_key' => 'unit.authorization_fact',
            'summary' => 'Requires verification permission.',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
        ]);
        $this->actingAs($viewer)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations/{$observation['id']}/review", [
                'verification_status' => 'verified',
            ])
            ->assertForbidden();
    }

    public function test_sales_and_procurement_users_receive_only_their_lane_for_a_dual_role_unit(): void
    {
        $manager = $this->manager();
        $unit = $this->unit();
        $salesContext = $this->createContext($manager, $unit, [
            'lane' => 'sales',
            'role_code' => 'customer',
        ]);
        $procurementContext = $this->createContext($manager, $unit, [
            'lane' => 'procurement',
            'role_code' => 'supplier',
        ]);

        $this->createObservation($manager, $unit->id, [
            'unit_business_context_id' => $salesContext['id'],
            'observation_key' => 'sales.customer_marker',
            'summary' => 'SALES_ONLY_CUSTOMER_MARKER',
            'data_classification' => 'internal',
            'visibility_scope' => 'sales_lane',
        ]);
        $this->createObservation($manager, $unit->id, [
            'unit_business_context_id' => $procurementContext['id'],
            'observation_key' => 'procurement.supplier_marker',
            'summary' => 'PROCUREMENT_ONLY_SUPPLIER_MARKER',
            'data_classification' => 'internal',
            'visibility_scope' => 'procurement_lane',
        ]);
        $this->createObservation($manager, $unit->id, [
            'observation_key' => 'unit.public_marker',
            'summary' => 'SHARED_PUBLIC_MARKER',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
        ]);
        $this->createObservation($manager, $unit->id, [
            'observation_key' => 'security.secret_marker',
            'summary' => 'SECRET_MUST_NEVER_REACH_CLIENT',
            'data_classification' => 'secret',
            'visibility_scope' => 'internal_only',
        ]);
        $this->actingAs($manager)
            ->postJson("/api/ai-sales/units/{$unit->id}/aliases", [
                'unit_business_context_id' => $salesContext['id'],
                'alias' => 'SALES_ONLY_ALIAS',
                'alias_type' => 'other',
                'data_classification' => 'internal',
                'visibility_scope' => 'sales_lane',
            ])
            ->assertCreated();
        $this->actingAs($manager)
            ->postJson("/api/ai-sales/units/{$unit->id}/aliases", [
                'unit_business_context_id' => $procurementContext['id'],
                'alias' => 'PROCUREMENT_ONLY_ALIAS',
                'alias_type' => 'other',
                'data_classification' => 'internal',
                'visibility_scope' => 'procurement_lane',
            ])
            ->assertCreated();
        $linkedEntity = Entity::query()->create(['name' => 'CROSS_LANE_ENTITY_IDENTITY']);
        $unit->entities()->attach($linkedEntity->id);

        $salesUser = $this->userWith(['ai_sales.view', 'ai_sales.sales.view']);
        $salesPayload = $this->actingAs($salesUser)
            ->getJson("/api/ai-sales/units/{$unit->id}/dossier")
            ->assertOk()
            ->json('data');
        $salesJson = json_encode($salesPayload, JSON_UNESCAPED_UNICODE);

        $this->assertSame(['sales'], collect($salesPayload['contexts'])->pluck('lane')->unique()->values()->all());
        $this->assertStringContainsString('SALES_ONLY_CUSTOMER_MARKER', $salesJson);
        $this->assertStringContainsString('SALES_ONLY_ALIAS', $salesJson);
        $this->assertStringContainsString('SHARED_PUBLIC_MARKER', $salesJson);
        $this->assertStringNotContainsString('PROCUREMENT_ONLY_SUPPLIER_MARKER', $salesJson);
        $this->assertStringNotContainsString('PROCUREMENT_ONLY_ALIAS', $salesJson);
        $this->assertStringNotContainsString('supplier', $salesJson);
        $this->assertStringNotContainsString('SECRET_MUST_NEVER_REACH_CLIENT', $salesJson);
        $this->assertNull($salesPayload['dual_role_warning']);

        $procurementUser = $this->userWith(['ai_sales.view', 'ai_sales.procurement.view']);
        $procurementPayload = $this->actingAs($procurementUser)
            ->getJson("/api/ai-sales/units/{$unit->id}/dossier")
            ->assertOk()
            ->json('data');
        $procurementJson = json_encode($procurementPayload, JSON_UNESCAPED_UNICODE);

        $this->assertSame(['procurement'], collect($procurementPayload['contexts'])->pluck('lane')->unique()->values()->all());
        $this->assertStringContainsString('PROCUREMENT_ONLY_SUPPLIER_MARKER', $procurementJson);
        $this->assertStringContainsString('PROCUREMENT_ONLY_ALIAS', $procurementJson);
        $this->assertStringContainsString('SHARED_PUBLIC_MARKER', $procurementJson);
        $this->assertStringNotContainsString('SALES_ONLY_CUSTOMER_MARKER', $procurementJson);
        $this->assertStringNotContainsString('SALES_ONLY_ALIAS', $procurementJson);
        $this->assertStringNotContainsString('customer', $procurementJson);
        $this->assertStringNotContainsString('SECRET_MUST_NEVER_REACH_CLIENT', $procurementJson);

        $salesInternal = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.classifications.view_internal',
        ]);
        $this->actingAs($salesInternal)
            ->getJson("/api/ai-sales/units/{$unit->id}/dossier")
            ->assertOk()
            ->assertJsonPath('data.linked_entities', []);

        $dualViewer = $this->userWith(['ai_sales.view', 'ai_sales.sales.view', 'ai_sales.procurement.view']);
        $this->actingAs($dualViewer)
            ->getJson("/api/ai-sales/units/{$unit->id}/dossier")
            ->assertOk()
            ->assertJsonPath('data.dual_role_warning', true);
    }

    public function test_shared_public_requires_public_classification_and_context_subjects_are_isolated(): void
    {
        $manager = $this->manager();
        $firstUnit = $this->unit();
        $secondUnit = $this->unit();
        $context = $this->createContext($manager, $firstUnit, [
            'lane' => 'sales',
            'role_code' => 'customer',
        ]);

        $this->actingAs($manager)
            ->postJson("/api/ai-sales/units/{$firstUnit->id}/observations", [
                'observation_key' => 'unit.invalid_shared',
                'summary' => 'Internal is not shared public.',
                'data_classification' => 'internal',
                'visibility_scope' => 'shared_public',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_classification');

        $observationId = $this->createObservation($manager, $firstUnit->id, [
            'observation_key' => 'unit.valid_shared',
            'summary' => 'Explicit public fact.',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
        ])['id'];

        $this->actingAs($manager)
            ->patchJson("/api/ai-sales/units/{$secondUnit->id}/contexts/{$context['id']}", ['stage' => 'qualified'])
            ->assertNotFound();
        $this->actingAs($manager)
            ->postJson("/api/ai-sales/units/{$secondUnit->id}/observations/{$observationId}/review", [
                'verification_status' => 'verified',
            ])
            ->assertNotFound();
    }

    public function test_classification_badges_are_only_returned_with_internal_permission(): void
    {
        $manager = $this->manager();
        $unit = $this->unit();
        $this->createObservation($manager, $unit->id, [
            'observation_key' => 'unit.public',
            'summary' => 'Public fact.',
            'data_classification' => 'public',
            'visibility_scope' => 'shared_public',
        ]);

        $viewer = $this->userWith(['ai_sales.view', 'ai_sales.sales.view']);
        $payload = $this->actingAs($viewer)
            ->getJson("/api/ai-sales/units/{$unit->id}/dossier")
            ->assertOk()
            ->json('data.observations.0');

        $this->assertArrayNotHasKey('data_classification', $payload);
        $this->assertArrayNotHasKey('visibility_scope', $payload);

        $internal = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.classifications.view_internal',
        ]);
        $this->actingAs($internal)
            ->getJson("/api/ai-sales/units/{$unit->id}/dossier")
            ->assertOk()
            ->assertJsonPath('data.observations.0.data_classification', 'public')
            ->assertJsonPath('data.observations.0.visibility_scope', 'shared_public');
    }

    public function test_target_lane_and_context_bound_review_are_authorized_fail_closed(): void
    {
        $manager = $this->manager();
        $unit = $this->unit();
        $salesContext = $this->createContext($manager, $unit, [
            'lane' => 'sales',
            'role_code' => 'customer',
        ]);
        $procurementContext = $this->createContext($manager, $unit, [
            'lane' => 'procurement',
            'role_code' => 'supplier',
        ]);
        $observation = $this->createObservation($manager, $unit->id, [
            'unit_business_context_id' => $procurementContext['id'],
            'observation_key' => 'procurement.internal_review',
            'summary' => 'Procurement-only internal observation.',
            'data_classification' => 'internal',
            'visibility_scope' => 'internal_only',
        ]);
        $salesManager = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.contexts.manage',
            'ai_sales.observation.verify',
            'ai_sales.classifications.view_internal',
        ]);

        $this->actingAs($salesManager)
            ->patchJson("/api/ai-sales/units/{$unit->id}/contexts/{$salesContext['id']}", [
                'lane' => 'procurement',
                'role_code' => 'supplier',
            ])
            ->assertForbidden();
        $this->actingAs($salesManager)
            ->postJson("/api/ai-sales/units/{$unit->id}/observations/{$observation['id']}/review", [
                'verification_status' => 'verified',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('unit_business_contexts', [
            'id' => $salesContext['id'],
            'lane' => 'sales',
            'role_code' => 'customer',
        ]);
        $this->assertDatabaseHas('unit_observations', [
            'id' => $observation['id'],
            'verification_status' => 'unverified',
        ]);
    }

    private function createObservation($actor, int $unitId, array $attributes): array
    {
        return $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unitId}/observations", $attributes)
            ->assertCreated()
            ->json('data');
    }
}
