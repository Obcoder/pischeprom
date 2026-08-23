<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\EntityProposalAction;
use App\Domain\AiSales\Enums\EntityProposalStatus;
use App\Models\Entity;
use App\Models\EntityCandidateProposal;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntityCandidateProposalService
{
    private const PROPOSED_ATTRIBUTE_ALLOWLIST = [
        'full_name',
        'entity_classification_id',
        'INN',
        'KPP',
        'OGRN',
        'legal_address',
        'country_id',
    ];

    public function __construct(
        private readonly UnitContextAuthorizationService $authorization,
        private readonly EntityDuplicateCheckService $duplicates,
        private readonly UnitDossierAuditLogger $audit,
    ) {}

    public function propose(
        Unit $unit,
        UnitBusinessContext $context,
        array $attributes,
        User $actor,
    ): EntityCandidateProposal {
        $this->authorization->assertContextBelongsToUnit($unit, $context);
        $this->authorization->authorizeLane($actor, $context->lane);

        if (! $this->authorization->hasPermission($actor, UnitContextAuthorizationService::PROPOSE_ENTITY)) {
            throw new AuthorizationException('Entity proposal permission is required.');
        }

        $action = EntityProposalAction::from($attributes['action']);

        if ($action === EntityProposalAction::LinkExisting
            && ! $this->authorization->hasPermission($actor, UnitContextAuthorizationService::VIEW_CLASSIFICATIONS)) {
            throw new AuthorizationException('Internal Entity identity permission is required for a link-existing proposal.');
        }

        $existingEntity = filled($attributes['existing_entity_id'] ?? null)
            ? Entity::query()->without(['buildings', 'classification', 'country'])->select(['id', 'name'])->findOrFail((int) $attributes['existing_entity_id'])
            : null;

        if ($action === EntityProposalAction::LinkExisting && ! $existingEntity) {
            throw ValidationException::withMessages([
                'existing_entity_id' => 'Link proposals require an existing Entity.',
            ]);
        }
        if ($action === EntityProposalAction::Create && $existingEntity) {
            throw ValidationException::withMessages([
                'existing_entity_id' => 'Create and link are separate proposal actions.',
            ]);
        }

        $proposed = Arr::only($attributes['proposed_attributes'] ?? [], self::PROPOSED_ATTRIBUTE_ALLOWLIST);
        $name = trim((string) ($attributes['proposed_name'] ?? $existingEntity?->name));
        $duplicateIds = $this->duplicates->candidateIds([
            'name' => $name,
            ...$proposed,
        ], $existingEntity?->id);

        return DB::transaction(function () use ($unit, $context, $attributes, $actor, $action, $existingEntity, $proposed, $name, $duplicateIds): EntityCandidateProposal {
            $proposal = EntityCandidateProposal::query()->create([
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context->id,
                'action' => $action,
                'existing_entity_id' => $existingEntity?->id,
                'entity_name_snapshot' => $existingEntity?->name,
                'proposed_name' => mb_substr($name, 0, 255),
                'proposed_attributes' => $proposed,
                'evidence_summary' => mb_substr(trim($attributes['evidence_summary']), 0, 4000),
                'duplicate_candidate_ids' => $duplicateIds,
                'status' => EntityProposalStatus::ReviewRequired,
                'proposer_type' => 'human',
                'proposed_by' => $actor->id,
            ]);

            $this->audit->record(
                $unit,
                'unit.entity_proposal.created',
                'Создано review-предложение Entity; Entity и связи не изменены.',
                $actor,
                $context,
                'entity_candidate_proposal',
                $proposal->id,
                [
                    'action' => $action->value,
                    'existing_entity_id' => $existingEntity?->id,
                    'duplicate_candidate_ids' => $duplicateIds,
                ],
            );

            return $proposal->fresh([
                'businessContext',
                'existingEntity' => fn ($query) => $query
                    ->without(['buildings', 'classification', 'country'])
                    ->select(['id', 'name']),
            ]);
        }, 3);
    }
}
