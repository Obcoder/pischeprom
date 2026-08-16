<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Queries\UnitDossierTimelineQuery;
use App\Domain\AiSales\Queries\UnitTransactionAggregateQuery;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiSales\UnitGoodMatchResource;
use App\Models\AiToolCall;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UnitProspectingDossierController extends Controller
{
    public function __invoke(
        Request $request,
        Unit $unit,
        ProspectingFeatureGuard $features,
        ProspectingAuthorizationService $authorization,
        UnitContextAuthorizationService $contextAuthorization,
        UnitDossierTimelineQuery $timeline,
        UnitTransactionAggregateQuery $transactions,
    ): JsonResponse {
        $features->dossier();
        Gate::authorize('view', $unit);
        $validated = validator($request->query(), [
            'context_id' => ['required', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();
        $context = UnitBusinessContext::query()->findOrFail($validated['context_id']);
        $contextAuthorization->assertContextBelongsToUnit($unit, $context);
        $authorization->authorize($request->user(), ProspectingAuthorizationService::VIEW, $context->lane);
        $timelinePayload = $timeline->paginate($request->user(), $unit, $context, $validated['page'] ?? 1, $validated['per_page'] ?? 25);
        $visibleContactLinks = $unit->contactContextLinks()->where('unit_business_context_id', $context->id)
            ->whereNull('archived_at')->select([
                'id', 'email_id', 'channel_type', 'contact_role', 'verification_status', 'confidence',
                'data_classification', 'visibility_scope', 'communication_state', 'review_required', 'last_verified_at',
            ])->latest('id')->limit(100)->get()
            ->filter(fn ($link) => $contextAuthorization->canViewField($request->user(), $link->visibility_scope, $link->data_classification));
        $contacts = $visibleContactLinks->map(fn ($link) => [
            'id' => $link->id,
            'channel_type' => $link->channel_type,
            'contact_role' => $link->contact_role,
            'verification_status' => $link->verification_status->value,
            'confidence' => $link->confidence,
            'communication_state' => $link->communication_state->value,
            'review_required' => $link->review_required,
            'last_verified_at' => $link->last_verified_at?->toISOString(),
        ])->values()->all();
        $sources = $unit->sources()->where('unit_business_context_id', $context->id)
            ->where('data_classification', '!=', 'secret')->latest('id')->limit(100)->get()
            ->filter(fn ($source) => $contextAuthorization->canViewField($request->user(), $source->visibility_scope, $source->data_classification))
            ->map(fn ($source) => [
                'id' => $source->id, 'type' => $source->source_type, 'label' => $source->source_label,
                'reference' => $source->source_reference, 'url' => $source->source_url,
                'observed_at' => $source->observed_at?->toISOString(),
            ])->values()->all();
        $aliases = $unit->aliases()->where('unit_business_context_id', $context->id)
            ->where('data_classification', '!=', 'secret')->latest('id')->limit(100)->get()
            ->filter(fn ($alias) => $contextAuthorization->canViewField($request->user(), $alias->visibility_scope, $alias->data_classification))
            ->map(fn ($alias) => [
                'id' => $alias->id, 'alias' => $alias->alias, 'alias_type' => $alias->alias_type->value,
                'verification_status' => $alias->verification_status->value,
            ])->values()->all();
        $observations = $unit->observations()->where('unit_business_context_id', $context->id)
            ->where('data_classification', '!=', 'secret')->latest('observed_at')->limit(100)->get()
            ->filter(fn ($observation) => $contextAuthorization->canViewField($request->user(), $observation->visibility_scope, $observation->data_classification))
            ->map(fn ($observation) => [
                'id' => $observation->id, 'key' => $observation->observation_key,
                'summary' => $observation->summary, 'verification_status' => $observation->verification_status->value,
                'observed_at' => $observation->observed_at?->toISOString(),
            ])->values()->all();
        $matches = $unit->goodMatches()->where('unit_business_context_id', $context->id)
            ->with('good:id,name')->latest('id')->limit(100)->get();
        $hasAnySalesContext = $unit->businessContexts()->where('lane', 'sales')->whereNull('archived_at')->exists();
        $hasAnyProcurementContext = $unit->businessContexts()->where('lane', 'procurement')->whereNull('archived_at')->exists();
        $canViewAllIdentityLanes = (! $hasAnySalesContext || $contextAuthorization->canViewLane($request->user(), BusinessLane::Sales))
            && (! $hasAnyProcurementContext || $contextAuthorization->canViewLane($request->user(), BusinessLane::Procurement));
        $entities = $contextAuthorization->hasPermission($request->user(), UnitContextAuthorizationService::VIEW_CLASSIFICATIONS)
            && $canViewAllIdentityLanes
            ? $unit->entities()->without(['buildings', 'classification', 'country'])->select(['entities.id', 'entities.name'])->distinct()->limit(100)->get()->map(fn ($entity) => ['id' => $entity->id, 'name' => $entity->name])->all()
            : [];
        $emailIds = $visibleContactLinks->where('channel_type', 'email')->pluck('email_id')->filter()->unique()->values();
        $communicationCount = $emailIds->isEmpty() ? 0 : DB::table('email_mail_message')
            ->whereIn('email_id', $emailIds)->distinct()->count('mail_message_id');
        $attachmentCount = $emailIds->isEmpty() ? 0 : DB::table('mail_message_attachments')
            ->join('email_mail_message', 'email_mail_message.mail_message_id', '=', 'mail_message_attachments.mail_message_id')
            ->whereIn('email_mail_message.email_id', $emailIds)
            ->distinct()->count('mail_message_attachments.id');
        $aiRuns = $contextAuthorization->hasPermission($request->user(), 'ai_sales.runs.view')
            ? $unit->aiAgentRuns()->where('unit_business_context_id', $context->id)
                ->select(['public_id', 'definition_code', 'status', 'created_at', 'completed_at'])
                ->latest('id')->limit(50)->get()->map(fn ($run) => [
                    'id' => $run->public_id,
                    'definition_code' => $run->definition_code,
                    'status' => $run->status->value,
                    'created_at' => $run->created_at?->toISOString(),
                    'completed_at' => $run->completed_at?->toISOString(),
                ])->all()
            : [];
        $toolCalls = $contextAuthorization->hasPermission($request->user(), 'ai_sales.tools.view')
            ? AiToolCall::query()->where('unit_id', $unit->id)->where('unit_business_context_id', $context->id)
                ->select(['id', 'tool_code', 'status', 'row_count', 'query_count', 'redaction_count', 'created_at'])
                ->latest('id')->limit(100)->get()->map(fn ($call) => [
                    'id' => $call->id,
                    'tool_code' => $call->tool_code,
                    'status' => $call->status,
                    'row_count' => $call->row_count,
                    'query_count' => $call->query_count,
                    'redaction_count' => $call->redaction_count,
                    'created_at' => $call->created_at?->toISOString(),
                ])->all()
            : [];

        return response()->json(['data' => [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'context' => ['id' => $context->id, 'lane' => $context->lane->value, 'role_code' => $context->role_code->value, 'stage' => $context->stage->value],
            'dual_role_warning' => $unit->businessContexts()->whereIn('lane', ['sales', 'procurement'])->whereNull('archived_at')->distinct()->count('lane') > 1,
            'contact_links' => $contacts,
            'sources' => $sources,
            'aliases' => $aliases,
            'observations' => $observations,
            'good_matches' => UnitGoodMatchResource::collection($matches)->resolve($request),
            'linked_entities' => $entities,
            'transaction_count' => $transactions->transactionCount($request->user(), $context),
            'communications' => [
                'message_count' => $communicationCount,
                'attachment_count' => $attachmentCount,
                'raw_content_included' => false,
            ],
            'ai_runs' => $aiRuns,
            'tool_calls' => $toolCalls,
            'timeline' => $timelinePayload,
            'lane_isolation' => 'explicit_context_required',
        ]]);
    }
}
