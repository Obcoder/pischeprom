<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Contracts\EntityCreateLinkGuard;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\Entity;
use App\Models\EntityCandidateProposal;

class EntityCreateLinkGuardTest extends UnitContextsTestCase
{
    public function test_entity_proposal_does_not_create_or_link_entity(): void
    {
        $actor = $this->manager();
        $unit = $this->unit();
        $context = $this->createContext($actor, $unit, [
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
        ]);
        $entitiesBefore = Entity::query()->count();
        $linksBefore = $unit->entities()->count();

        $proposal = $this->actingAs($actor)
            ->postJson("/api/ai-sales/units/{$unit->id}/entity-proposals", [
                'unit_business_context_id' => $context['id'],
                'action' => 'create',
                'proposed_name' => 'ООО Кандидат',
                'proposed_attributes' => [
                    'INN' => '7700000001',
                    'legal_address' => 'Публичный адрес-кандидат',
                ],
                'evidence_summary' => 'Synthetic evidence for human review.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.review_required', true)
            ->assertJsonPath('data.entity_was_changed', false)
            ->json('data');

        $this->assertSame($entitiesBefore, Entity::query()->count());
        $this->assertSame($linksBefore, $unit->entities()->count());
        $this->assertDatabaseHas('entity_candidate_proposals', [
            'id' => $proposal['id'],
            'unit_id' => $unit->id,
            'status' => 'review_required',
        ]);
    }

    public function test_create_and_link_guard_require_separate_permissions_and_duplicate_check(): void
    {
        $proposer = $this->manager();
        $unit = $this->unit();
        $context = $this->createContext($proposer, $unit, [
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
        ]);
        $proposalId = $this->actingAs($proposer)
            ->postJson("/api/ai-sales/units/{$unit->id}/entity-proposals", [
                'unit_business_context_id' => $context['id'],
                'action' => 'create',
                'proposed_name' => 'Уникальная Entity-кандидат',
                'evidence_summary' => 'Synthetic evidence.',
            ])
            ->assertCreated()
            ->json('data.id');
        $proposal = EntityCandidateProposal::query()->with('businessContext')->findOrFail($proposalId);
        $guard = app(EntityCreateLinkGuard::class);

        $this->assertPolicyCode(
            fn () => $guard->assertCreateAllowed($proposer, $proposal),
            'entity_create_forbidden',
        );

        $creator = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.entity.propose',
            'ai_sales.entity.create',
        ]);
        $guard->assertCreateAllowed($creator, $proposal);
        $this->assertSame(0, Entity::query()->where('name', 'Уникальная Entity-кандидат')->count());

        Entity::query()->create(['name' => 'Дубликат', 'INN' => '1234567890']);
        $duplicateProposalId = $this->actingAs($proposer)
            ->postJson("/api/ai-sales/units/{$unit->id}/entity-proposals", [
                'unit_business_context_id' => $context['id'],
                'action' => 'create',
                'proposed_name' => 'Дубликат',
                'proposed_attributes' => ['INN' => '1234567890'],
                'evidence_summary' => 'Duplicate test.',
            ])
            ->assertCreated()
            ->json('data.id');
        $duplicateProposal = EntityCandidateProposal::query()->with('businessContext')->findOrFail($duplicateProposalId);

        $this->assertPolicyCode(
            fn () => $guard->assertCreateAllowed($creator, $duplicateProposal),
            'entity_duplicate_review_required',
        );
    }

    public function test_link_guard_never_attaches_entity_and_requires_link_permission(): void
    {
        $proposer = $this->manager();
        $unit = $this->unit();
        $context = $this->createContext($proposer, $unit, [
            'lane' => 'sales',
            'role_code' => 'customer',
        ]);
        $entity = Entity::query()->create(['name' => 'Existing Entity']);
        $proposalId = $this->actingAs($proposer)
            ->postJson("/api/ai-sales/units/{$unit->id}/entity-proposals", [
                'unit_business_context_id' => $context['id'],
                'action' => 'link_existing',
                'existing_entity_id' => $entity->id,
                'evidence_summary' => 'Entity identity must be reviewed.',
            ])
            ->assertCreated()
            ->json('data.id');
        $proposal = EntityCandidateProposal::query()->with('businessContext')->findOrFail($proposalId);
        $guard = app(EntityCreateLinkGuard::class);
        $creatorOnly = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.entity.propose',
            'ai_sales.entity.create',
        ]);

        $this->assertPolicyCode(
            fn () => $guard->assertLinkAllowed($creatorOnly, $proposal, $entity),
            'entity_link_forbidden',
        );

        $linker = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.entity.propose',
            'ai_sales.entity.link',
        ]);
        $guard->assertLinkAllowed($linker, $proposal, $entity);

        $this->assertDatabaseMissing('entity_unit', [
            'unit_id' => $unit->id,
            'entity_id' => $entity->id,
        ]);
    }

    public function test_proposer_without_internal_identity_permission_cannot_link_or_receive_duplicate_ids(): void
    {
        $manager = $this->manager();
        $unit = $this->unit();
        $context = $this->createContext($manager, $unit, [
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
        ]);
        $entity = Entity::query()->create(['name' => 'Protected duplicate']);
        $proposer = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.entity.propose',
        ]);

        $this->actingAs($proposer)
            ->postJson("/api/ai-sales/units/{$unit->id}/entity-proposals", [
                'unit_business_context_id' => $context['id'],
                'action' => 'link_existing',
                'existing_entity_id' => $entity->id,
                'evidence_summary' => 'Identity lookup must fail closed.',
            ])
            ->assertForbidden();

        $this->actingAs($proposer)
            ->postJson("/api/ai-sales/units/{$unit->id}/entity-proposals", [
                'unit_business_context_id' => $context['id'],
                'action' => 'create',
                'proposed_name' => 'Protected duplicate',
                'evidence_summary' => 'Duplicate IDs are reviewer-only.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.duplicate_candidate_ids', []);
    }

    private function assertPolicyCode(callable $operation, string $expected): void
    {
        try {
            $operation();
            $this->fail("Expected policy violation {$expected}.");
        } catch (PolicyViolation $violation) {
            $this->assertSame($expected, $violation->errorCode);
        }
    }
}
