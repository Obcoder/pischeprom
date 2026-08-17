<?php

namespace App\Domain\AiSales\Queries;

use App\Domain\AiSales\DTO\Prospecting\UnitDossierTimelineItem;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\AiToolCall;
use App\Models\ProspectingSearchJob;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UnitDossierTimelineQuery
{
    public function __construct(
        private readonly UnitContextAuthorizationService $contextAuthorization,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly UnitTransactionAggregateQuery $transactions,
    ) {}

    public function paginate(
        User $actor,
        Unit $unit,
        UnitBusinessContext $context,
        int $page = 1,
        int $perPage = 25,
    ): array {
        $this->contextAuthorization->assertContextBelongsToUnit($unit, $context);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::VIEW_TIMELINE, $context->lane);
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);
        $items = collect();
        $items->push(new UnitDossierTimelineItem(
            'unit.context',
            'Контекст: '.$context->lane->value.' / '.$context->role_code->value.' / '.$context->stage->value.'.',
            'unit_business_context',
            $context->id,
            $context->created_at,
        ));

        $unit->dossierAuditEvents()->where('unit_business_context_id', $context->id)
            ->select(['id', 'event_type', 'summary', 'created_at'])->latest('id')->limit(200)->get()
            ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                $row->event_type, $row->summary, 'unit_dossier_audit_event', $row->id, $row->created_at,
            )));
        $unit->sources()->where('unit_business_context_id', $context->id)
            ->select(['id', 'source_type', 'source_label', 'data_classification', 'visibility_scope', 'created_at'])
            ->latest('id')->limit(100)->get()
            ->filter(fn ($row) => $this->contextAuthorization->canViewField($actor, $row->visibility_scope, $row->data_classification))
            ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                'unit.source', 'Источник: '.($row->source_label ?: $row->source_type), 'unit_source', $row->id, $row->created_at,
            )));
        $unit->observations()->where('unit_business_context_id', $context->id)
            ->select(['id', 'observation_key', 'data_classification', 'visibility_scope', 'created_at'])
            ->latest('id')->limit(100)->get()
            ->filter(fn ($row) => $this->contextAuthorization->canViewField($actor, $row->visibility_scope, $row->data_classification))
            ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                'unit.observation', 'Наблюдение: '.$row->observation_key, 'unit_observation', $row->id, $row->created_at,
            )));
        $unit->aliases()->where('unit_business_context_id', $context->id)
            ->select(['id', 'alias_type', 'data_classification', 'visibility_scope', 'created_at'])
            ->latest('id')->limit(100)->get()
            ->filter(fn ($row) => $this->contextAuthorization->canViewField($actor, $row->visibility_scope, $row->data_classification))
            ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                'unit.alias', 'Добавлен alias типа '.$row->alias_type->value.'.', 'unit_alias', $row->id, $row->created_at,
            )));
        $visibleContactLinks = $unit->contactContextLinks()->where('unit_business_context_id', $context->id)
            ->whereNull('archived_at')
            ->select(['id', 'email_id', 'channel_type', 'communication_state', 'data_classification', 'visibility_scope', 'created_at'])
            ->latest('id')->limit(100)->get()
            ->filter(fn ($row) => $this->contextAuthorization->canViewField($actor, $row->visibility_scope, $row->data_classification));
        $visibleContactLinks->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
            'unit.contact_link', 'Контактный канал '.$row->channel_type.' ('.$row->communication_state->value.').', 'unit_contact_context_link', $row->id, $row->created_at,
        )));
        $unit->productMatches()->where('unit_business_context_id', $context->id)
            ->select(['id', 'match_type', 'status', 'created_at'])->latest('id')->limit(100)->get()
            ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                'unit.product_match', 'Product match: '.$row->match_type->value.' / '.$row->status->value.'.', 'unit_product_match', $row->id, $row->created_at,
            )));
        $unit->goodMatches()->where('unit_business_context_id', $context->id)
            ->whereNotNull('unit_product_match_id')
            ->select(['id', 'fit_status', 'compatibility_state', 'created_at'])->latest('id')->limit(100)->get()
            ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                'unit.good_offer_fit', 'Good offer fit: '.($row->fit_status?->value ?? 'review_required').' / '.$row->compatibility_state->value.'.', 'unit_good_match', $row->id, $row->created_at,
            )));
        if ((bool) config('ai-sales.prospecting.scoring_enabled', false)
            && $this->authorization->can($actor, ProspectingAuthorizationService::VIEW_SCORING, $context->lane)) {
            foreach ([
                'unit_product_relevance_snapshots' => ['score.product_relevance', 'unit_product_relevance_snapshot'],
                'unit_good_fit_snapshots' => ['score.good_fit', 'unit_good_fit_snapshot'],
                'unit_prospect_priority_snapshots' => ['score.prospect_priority', 'unit_prospect_priority_snapshot'],
            ] as $table => [$type, $referenceType]) {
                DB::table($table)->where('unit_business_context_id', $context->id)
                    ->select(['id', 'computed_score', 'effective_score', 'band', 'eligibility', 'created_at'])
                    ->orderByDesc('id')->limit(100)->get()
                    ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                        $type,
                        "Score: computed {$row->computed_score}; effective {$row->effective_score}; {$row->band}; {$row->eligibility}.",
                        $referenceType,
                        $row->id,
                        CarbonImmutable::parse($row->created_at),
                    )));
            }
        }
        if ($this->contextAuthorization->hasPermission($actor, UnitContextAuthorizationService::VIEW_CLASSIFICATIONS)) {
            $unit->goodMatches()->where('unit_business_context_id', $context->id)
                ->whereNull('unit_product_match_id')
                ->select(['id', 'status', 'compatibility_state', 'created_at'])->latest('id')->limit(100)->get()
                ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                    'unit.good_match.legacy', 'Legacy Good-first diagnostic: '.$row->status->value.' / '.$row->compatibility_state->value.'.', 'unit_good_match', $row->id, $row->created_at,
                )));
        }
        $unit->resolvedProspectingCandidates()->where('lane', $context->lane->value)
            ->where('role_code', $context->role_code->value)
            ->select(['id', 'public_id', 'status', 'reviewed_at', 'created_at'])->latest('id')->limit(100)->get()
            ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                'prospecting.candidate', 'Разрешение кандидата: '.$row->status->value.'.', 'prospecting_candidate', $row->public_id ?? $row->id, $row->reviewed_at ?? $row->created_at,
            )));
        if ($this->contextAuthorization->hasPermission($actor, 'ai_sales.runs.view')) {
            $unit->aiAgentRuns()->where('unit_business_context_id', $context->id)
                ->select(['id', 'public_id', 'status', 'created_at'])->latest('id')->limit(100)->get()
                ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                    'ai.run', 'AI-запуск: '.(is_object($row->status) ? $row->status->value : $row->status).'.', 'ai_agent_run', $row->public_id, $row->created_at,
                )));
        }
        ProspectingSearchJob::query()
            ->where('lane', $context->lane->value)
            ->where('default_role_code', $context->role_code->value)
            ->whereHas('candidates', fn ($query) => $query->where('resolved_unit_id', $unit->id))
            ->select(['id', 'public_id', 'purpose', 'status', 'created_at'])
            ->latest('id')->limit(100)->get()
            ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                'prospecting.job',
                'Prospecting job: '.$row->purpose->value.' / '.$row->status->value.'.',
                'prospecting_search_job',
                $row->public_id,
                $row->created_at,
            )));

        $emailIds = $visibleContactLinks->where('channel_type', 'email')->pluck('email_id')->filter()->unique()->values();
        if ($emailIds->isNotEmpty()) {
            $messageQuery = DB::table('email_mail_message')
                ->join('mail_messages', 'mail_messages.id', '=', 'email_mail_message.mail_message_id')
                ->whereIn('email_mail_message.email_id', $emailIds);
            $messageCount = (clone $messageQuery)->distinct()->count('mail_messages.id');
            if ($messageCount > 0) {
                $attachmentCount = DB::table('mail_message_attachments')
                    ->join('email_mail_message', 'email_mail_message.mail_message_id', '=', 'mail_message_attachments.mail_message_id')
                    ->whereIn('email_mail_message.email_id', $emailIds)
                    ->distinct()->count('mail_message_attachments.id');
                $latestMessageAt = (clone $messageQuery)->max('mail_messages.message_date');
                $items->push(new UnitDossierTimelineItem(
                    'communications.aggregate',
                    "Связанных сообщений: {$messageCount}; вложений: {$attachmentCount}. Raw content не включён.",
                    'communications_projection',
                    $context->id,
                    $latestMessageAt ? CarbonImmutable::parse($latestMessageAt) : $context->updated_at,
                ));
            }
        }

        $transactionCount = $this->transactions->transactionCount($actor, $context);
        $items->push(new UnitDossierTimelineItem(
            'transactions.aggregate',
            'Связанных транзакций в контуре: '.$transactionCount.'.',
            $context->lane->value === 'sales' ? 'sales_projection' : 'purchases_projection',
            $context->id,
            $context->updated_at,
        ));
        if ($this->contextAuthorization->hasPermission($actor, UnitContextAuthorizationService::PROPOSE_ENTITY)) {
            $unit->entityCandidateProposals()->where('unit_business_context_id', $context->id)
                ->select(['id', 'action', 'status', 'created_at'])->latest('id')->limit(100)->get()
                ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                    'entity.proposal',
                    'Entity proposal: '.$row->action->value.' / '.$row->status->value.'.',
                    'entity_candidate_proposal',
                    $row->id,
                    $row->created_at,
                )));
        }
        if ($this->canViewLinkedEntities($actor, $unit)) {
            $entityCount = $unit->entities()->distinct()->count('entities.id');
            if ($entityCount > 0) {
                $items->push(new UnitDossierTimelineItem(
                    'entities.aggregate',
                    'Связанных Entity: '.$entityCount.'.',
                    'entity_link_projection',
                    $context->id,
                    $context->updated_at,
                ));
            }
        }
        if ($this->contextAuthorization->hasPermission($actor, 'ai_sales.tools.view')) {
            AiToolCall::query()->where('unit_id', $unit->id)->where('unit_business_context_id', $context->id)
                ->select(['id', 'tool_code', 'status', 'created_at'])->latest('id')->limit(100)->get()
                ->each(fn ($row) => $items->push(new UnitDossierTimelineItem(
                    'ai.tool_call',
                    'Server-owned tool: '.$row->tool_code.' / '.$row->status.'.',
                    'ai_tool_call',
                    $row->id,
                    $row->created_at,
                )));
        }
        $sorted = $items->sortByDesc(fn (UnitDossierTimelineItem $item) => $item->occurredAt->getTimestamp().':'.$item->referenceType.':'.$item->referenceId)->values();
        $total = $sorted->count();

        return [
            'data' => $sorted->forPage($page, $perPage)->map->safeArray()->values()->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context->id,
                'lane' => $context->lane->value,
                'projection_only' => true,
            ],
        ];
    }

    private function canViewLinkedEntities(User $actor, Unit $unit): bool
    {
        if (! $this->contextAuthorization->hasPermission($actor, UnitContextAuthorizationService::VIEW_CLASSIFICATIONS)) {
            return false;
        }
        $hasSales = $unit->businessContexts()->where('lane', BusinessLane::Sales->value)->whereNull('archived_at')->exists();
        $hasProcurement = $unit->businessContexts()->where('lane', BusinessLane::Procurement->value)->whereNull('archived_at')->exists();

        return (! $hasSales || $this->contextAuthorization->canViewLane($actor, BusinessLane::Sales))
            && (! $hasProcurement || $this->contextAuthorization->canViewLane($actor, BusinessLane::Procurement));
    }
}
