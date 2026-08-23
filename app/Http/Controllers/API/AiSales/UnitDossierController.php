<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\EntityProposalAction;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitAliasType;
use App\Domain\AiSales\Enums\UnitContextStage;
use App\Domain\AiSales\Enums\UnitContextStatus;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiSales\EntityCandidateProposalResource;
use App\Http\Resources\AiSales\UnitAliasResource;
use App\Http\Resources\AiSales\UnitBusinessContextResource;
use App\Http\Resources\AiSales\UnitObservationResource;
use App\Models\MarketRole;
use App\Models\Unit;
use App\Models\UnitAlias;
use App\Models\UnitObservation;
use App\Models\UnitSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UnitDossierController extends Controller
{
    public function __invoke(
        Request $request,
        Unit $unit,
        UnitContextAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('view', $unit);
        $user = $request->user();
        $capabilities = $authorization->capabilities($user, $unit);
        $visibleLanes = collect(BusinessLane::cases())
            ->filter(fn (BusinessLane $lane) => $authorization->canViewLane($user, $lane))
            ->values();

        $contexts = $unit->businessContexts()
            ->select([
                'id', 'unit_id', 'role_code', 'lane', 'stage', 'status', 'confidence',
                'owner_user_id', 'reviewer_user_id', 'primary_good_id', 'primary_segment',
                'source', 'first_activity_at', 'last_activity_at', 'archived_at', 'created_at', 'updated_at',
            ])
            ->whereIn('lane', $visibleLanes->pluck('value'))
            ->with([
                'marketRole:id,code,display_name',
                'owner:id,name',
                'reviewer:id,name',
                'primaryGood:id,name',
            ])
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('lane')
            ->orderBy('id')
            ->limit(100)
            ->get();
        $visibleContextIds = $contexts->pluck('id');

        $roles = MarketRole::query()
            ->select(['market_roles.id', 'market_roles.code', 'market_roles.display_name'])
            ->join('market_role_unit', 'market_role_unit.market_role_id', '=', 'market_roles.id')
            ->where('market_role_unit.unit_id', $unit->id)
            ->whereNull('market_role_unit.archived_at')
            ->orderBy('market_roles.display_name')
            ->get()
            ->filter(function (MarketRole $role) use ($authorization, $user): bool {
                $code = UnitRoleCode::tryFrom($role->code);

                return $code !== null && $authorization->canViewRole($user, $code);
            })
            ->map(fn (MarketRole $role) => [
                'id' => $role->id,
                'code' => $role->code,
                'display_name' => $role->display_name,
            ])
            ->values()
            ->all();

        $aliases = $unit->aliases()
            ->select([
                'id', 'unit_id', 'unit_business_context_id', 'unit_source_id', 'alias', 'normalized_alias', 'alias_type',
                'confidence', 'verification_status', 'data_classification', 'visibility_scope',
                'reviewed_at', 'created_at',
            ])
            ->where(fn ($query) => $query->whereNull('unit_business_context_id')->orWhereIn('unit_business_context_id', $visibleContextIds))
            ->where('data_classification', '!=', DataClassification::Secret->value)
            ->with('source:id,unit_business_context_id,source_label,source_reference,data_classification,visibility_scope')
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn (UnitAlias $alias) => $authorization->canViewField($user, $alias->visibility_scope, $alias->data_classification))
            ->each(function (UnitAlias $alias) use ($authorization, $user): void {
                $source = $alias->source;

                if ($source && ! $authorization->canViewField($user, $source->visibility_scope, $source->data_classification)) {
                    $alias->setRelation('source', null);
                }
            });

        $observations = $unit->observations()
            ->select([
                'id', 'unit_id', 'unit_business_context_id', 'unit_source_id', 'observation_key',
                'normalized_value', 'summary', 'source_reference', 'verification_status', 'confidence',
                'data_classification', 'visibility_scope', 'observed_at', 'last_checked_at',
                'reviewed_by', 'reviewed_at', 'created_at',
            ])
            ->where(fn ($query) => $query->whereNull('unit_business_context_id')->orWhereIn('unit_business_context_id', $visibleContextIds))
            ->where('data_classification', '!=', DataClassification::Secret->value)
            ->with([
                'source:id,source_type,source_label,source_reference,source_url,data_classification,visibility_scope',
                'reviewer:id,name',
            ])
            ->latest('observed_at')
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (UnitObservation $observation) => $authorization->canViewField(
                $user,
                $observation->visibility_scope,
                $observation->data_classification,
            ))
            ->each(function (UnitObservation $observation) use ($authorization, $user): void {
                $source = $observation->source;

                if ($source && ! $authorization->canViewField($user, $source->visibility_scope, $source->data_classification)) {
                    $observation->setRelation('source', null);
                }
            });

        $sources = $unit->sources()
            ->select([
                'id', 'unit_id', 'unit_business_context_id', 'source_type', 'source_label',
                'source_reference', 'source_url', 'data_classification', 'visibility_scope', 'observed_at',
            ])
            ->where(fn ($query) => $query->whereNull('unit_business_context_id')->orWhereIn('unit_business_context_id', $visibleContextIds))
            ->where('data_classification', '!=', DataClassification::Secret->value)
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn (UnitSource $source) => $authorization->canViewField($user, $source->visibility_scope, $source->data_classification))
            ->map(fn (UnitSource $source) => [
                'id' => $source->id,
                'unit_business_context_id' => $source->unit_business_context_id,
                'type' => $source->source_type,
                'label' => $source->source_label,
                'reference' => $source->source_reference,
                'url' => $source->source_url,
                'observed_at' => $source->observed_at?->toISOString(),
            ])
            ->values()
            ->all();

        $proposalContextIds = $contexts->pluck('id');
        $proposals = $capabilities['propose_entity']
            ? $unit->entityCandidateProposals()
                ->select([
                    'id', 'unit_id', 'unit_business_context_id', 'action', 'existing_entity_id',
                    'proposed_name', 'evidence_summary', 'duplicate_candidate_ids', 'status', 'created_at',
                ])
                ->whereIn('unit_business_context_id', $proposalContextIds)
                ->with(['existingEntity' => fn ($query) => $query
                    ->without(['buildings', 'classification', 'country'])
                    ->select(['id', 'name'])])
                ->latest('id')
                ->limit(50)
                ->get()
            : collect();

        $audit = $capabilities['view_audit']
            ? $unit->dossierAuditEvents()
                ->select([
                    'id', 'unit_id', 'unit_business_context_id', 'event_type', 'subject_type',
                    'subject_id', 'actor_type', 'actor_user_id', 'summary', 'created_at',
                ])
                ->where(fn ($query) => $query->whereNull('unit_business_context_id')->orWhereIn('unit_business_context_id', $visibleContextIds))
                ->with('actor:id,name')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'unit_business_context_id' => $event->unit_business_context_id,
                    'event_type' => $event->event_type,
                    'subject_type' => $event->subject_type,
                    'subject_id' => $event->subject_id,
                    'actor' => $event->actor ? ['id' => $event->actor->id, 'name' => $event->actor->name] : ['type' => $event->actor_type],
                    'summary' => $event->summary,
                    'created_at' => $event->created_at?->toISOString(),
                ])
                ->all()
            : [];

        $hasAnySalesContext = $unit->businessContexts()->where('lane', BusinessLane::Sales->value)->whereNull('archived_at')->exists();
        $hasAnyProcurementContext = $unit->businessContexts()->where('lane', BusinessLane::Procurement->value)->whereNull('archived_at')->exists();
        $canViewAllIdentityLanes = (! $hasAnySalesContext || $capabilities['view_sales_lane'])
            && (! $hasAnyProcurementContext || $capabilities['view_procurement_lane']);

        $linkedEntities = $capabilities['view_internal_classifications'] && $canViewAllIdentityLanes
            ? $unit->entities()->without(['buildings', 'classification', 'country'])->select(['entities.id', 'entities.name'])->distinct()->orderBy('entities.name')->limit(100)->get()
                ->map(fn ($entity) => ['id' => $entity->id, 'name' => $entity->name])
                ->all()
            : [];

        $contextPayload = $contexts->map(fn ($context) => (new UnitBusinessContextResource($context))->resolve($request))->all();
        $aliasPayload = $aliases->map(fn ($alias) => (new UnitAliasResource($alias))->resolve($request))->values()->all();
        $observationPayload = $observations->map(fn ($observation) => (new UnitObservationResource($observation))->resolve($request))->values()->all();

        if (! $capabilities['view_internal_classifications']) {
            $aliasPayload = collect($aliasPayload)->map(fn (array $item) => collect($item)->except(['data_classification', 'visibility_scope'])->all())->all();
            $observationPayload = collect($observationPayload)->map(fn (array $item) => collect($item)->except(['data_classification', 'visibility_scope'])->all())->all();
        }

        $hasSales = $contexts->contains(fn ($context) => $context->lane === BusinessLane::Sales && $context->archived_at === null);
        $hasProcurement = $contexts->contains(fn ($context) => $context->lane === BusinessLane::Procurement && $context->archived_at === null);

        return response()->json([
            'data' => [
                'unit' => ['id' => $unit->id, 'name' => $unit->name],
                'roles' => $roles,
                'contexts' => $contextPayload,
                'aliases' => $aliasPayload,
                'sources' => $sources,
                'observations' => $observationPayload,
                'linked_entities' => $linkedEntities,
                'entity_proposals' => $proposals->map(fn ($proposal) => (new EntityCandidateProposalResource($proposal))->resolve($request))->all(),
                'audit' => $audit,
                'dual_role_warning' => $capabilities['view_sales_lane'] && $capabilities['view_procurement_lane']
                    ? $hasSales && $hasProcurement
                    : null,
                'capabilities' => $capabilities,
                'options' => $this->options($authorization, $user),
            ],
        ]);
    }

    private function options(UnitContextAuthorizationService $authorization, $user): array
    {
        $roleOptions = MarketRole::query()
            ->select(['id', 'code', 'display_name'])
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get()
            ->filter(function (MarketRole $role) use ($authorization, $user): bool {
                $code = UnitRoleCode::tryFrom($role->code);

                return $code !== null && $authorization->canViewRole($user, $code);
            })
            ->map(fn (MarketRole $role) => ['id' => $role->id, 'code' => $role->code, 'label' => $role->display_name])
            ->values()
            ->all();

        $visibility = collect(UnitVisibilityScope::cases())
            ->filter(fn (UnitVisibilityScope $scope) => match ($scope) {
                UnitVisibilityScope::SharedPublic => true,
                UnitVisibilityScope::SalesLane => $authorization->canViewLane($user, BusinessLane::Sales),
                UnitVisibilityScope::ProcurementLane => $authorization->canViewLane($user, BusinessLane::Procurement),
                UnitVisibilityScope::InternalOnly => $authorization->hasPermission($user, UnitContextAuthorizationService::VIEW_CLASSIFICATIONS),
            })
            ->pluck('value')
            ->all();

        $classifications = $authorization->hasPermission($user, UnitContextAuthorizationService::VIEW_CLASSIFICATIONS)
            ? collect(DataClassification::cases())->reject(fn (DataClassification $classification) => $classification === DataClassification::Secret)->pluck('value')->all()
            : [DataClassification::Public->value];

        return [
            'lanes' => collect(BusinessLane::cases())
                ->filter(fn (BusinessLane $lane) => $authorization->canViewLane($user, $lane))
                ->map(fn (BusinessLane $lane) => ['code' => $lane->value, 'label' => $lane->label()])
                ->values()
                ->all(),
            'roles' => $roleOptions,
            'stages' => collect(UnitContextStage::cases())->map(fn (UnitContextStage $stage) => ['code' => $stage->value, 'label' => $stage->label()])->all(),
            'statuses' => collect(UnitContextStatus::cases())->map(fn (UnitContextStatus $status) => [
                'code' => $status->value,
                'label' => $status->label(),
            ])->all(),
            'alias_types' => UnitAliasType::values(),
            'verification_statuses' => ObservationVerificationStatus::values(),
            'data_classifications' => $classifications,
            'visibility_scopes' => $visibility,
            'entity_proposal_actions' => $authorization->hasPermission($user, UnitContextAuthorizationService::VIEW_CLASSIFICATIONS)
                ? EntityProposalAction::values()
                : [EntityProposalAction::Create->value],
        ];
    }
}
