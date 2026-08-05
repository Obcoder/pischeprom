<?php

namespace App\Http\Controllers\API\AiPriceLists;

use App\Domain\AiPriceLists\Enums\DocumentClass;
use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Domain\AiPriceLists\Services\PriceListReviewService;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiPriceLists\ApplyPriceListRequest;
use App\Http\Requests\AiPriceLists\AssignPriceListSupplierRequest;
use App\Jobs\AiPriceLists\ApplyConfirmedPriceList;
use App\Jobs\AiPriceLists\ExtractPriceListContent;
use App\Jobs\AiPriceLists\MatchPriceListItems;
use App\Jobs\AiPriceLists\NormalizePriceListRows;
use App\Jobs\AiPriceLists\RecognizePriceListWithOcr;
use App\Jobs\AiPriceLists\ValidatePriceListFile;
use App\Models\Entity;
use App\Models\Good;
use App\Models\PriceListImport;
use App\Models\SupplierGoodPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PriceListImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PriceListImport::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'source_channel' => ['nullable', 'in:email,max'],
            'status' => ['nullable', 'string', 'max:40'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'supplier_state' => ['nullable', 'in:resolved,unresolved'],
            'extension' => ['nullable', 'in:xls,xlsx,csv,tsv,docx,pdf,jpg,jpeg,png,tif,tiff,bmp,gif,heic'],
            'has_error' => ['nullable', 'boolean'],
            'requires_review' => ['nullable', 'boolean'],
            'applied' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $perPage = min(100, max(10, (int) $request->integer('per_page', 25)));
        $imports = PriceListImport::query()
            ->with(['supplier:id,name', 'reviewer:id,name'])
            ->search($filters['search'] ?? null)
            ->when(isset($filters['source_channel']), fn (Builder $query) => $query->where('source_channel', $filters['source_channel']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['entity_id']), fn (Builder $query) => $query->where('entity_id', $filters['entity_id']))
            ->when(($filters['supplier_state'] ?? null) === 'resolved', fn (Builder $query) => $query->whereNotNull('entity_id'))
            ->when(($filters['supplier_state'] ?? null) === 'unresolved', fn (Builder $query) => $query->whereNull('entity_id'))
            ->when(isset($filters['extension']), fn (Builder $query) => $query->where('extension', $filters['extension']))
            ->when($request->boolean('has_error'), fn (Builder $query) => $query->whereNotNull('error_code'))
            ->when($request->boolean('requires_review'), fn (Builder $query) => $query->whereIn('status', [PriceListStatus::ReviewRequired->value, PriceListStatus::SupplierUnresolved->value]))
            ->when($request->filled('applied'), fn (Builder $query) => $request->boolean('applied') ? $query->whereNotNull('applied_at') : $query->whereNull('applied_at'))
            ->when(isset($filters['from']), fn (Builder $query) => $query->where('source_received_at', '>=', $filters['from'].' 00:00:00'))
            ->when(isset($filters['to']), fn (Builder $query) => $query->where('source_received_at', '<=', $filters['to'].' 23:59:59'))
            ->latest('source_received_at')
            ->latest('id')
            ->paginate($perPage);

        $imports->getCollection()->transform(fn (PriceListImport $import) => $this->summary($import));

        return response()->json([
            ...$imports->toArray(),
            'statuses' => collect(PriceListStatus::cases())->map(fn ($status) => ['value' => $status->value, 'title' => $status->label()])->all(),
        ]);
    }

    public function show(PriceListImport $priceListImport): JsonResponse
    {
        Gate::authorize('view', $priceListImport);
        $technical = Gate::allows('viewTechnical', $priceListImport);
        $priceListImport->load([
            'supplier:id,name,INN',
            'reviewer:id,name',
            'appliedBy:id,name',
            'mailMessage:id,subject,from_address,from_name,message_date',
            'events.user:id,name',
            'supplierPrices.good:id,name,slug',
        ]);

        if ($technical) {
            $priceListImport->load('usageRecords');
        }

        return response()->json([
            'data' => [
                ...$this->summary($priceListImport),
                'sha256' => $priceListImport->sha256,
                'document_defaults' => $priceListImport->document_defaults,
                'document_class' => $priceListImport->document_class->value,
                'document_type' => $priceListImport->document_type,
                'parser_type' => $priceListImport->parser_type,
                'versions' => [
                    'extractor' => $priceListImport->extractor_version,
                    'prompt' => $priceListImport->prompt_version,
                    'schema' => $priceListImport->schema_version,
                ],
                'document_metadata' => $this->safeDocumentMetadata($priceListImport->document_metadata ?: [], $technical),
                'duplicate_of_uuid' => $priceListImport->duplicateOf?->uuid,
                'error' => $priceListImport->error_code ? [
                    'code' => $priceListImport->error_code,
                    'message' => $priceListImport->error_message,
                    'retryable' => $priceListImport->error_retryable,
                ] : null,
                'source' => [
                    'mail_message' => $priceListImport->mailMessage,
                    'max' => $priceListImport->source_channel->value === 'max' ? [
                        'message_id' => $priceListImport->source_external_message_id,
                        'chat_id' => $priceListImport->source_chat_id,
                        'user_id' => $priceListImport->source_user_id,
                        'sender_name' => $priceListImport->sender_name,
                        'caption' => $priceListImport->source_subject,
                    ] : null,
                ],
                'events' => $priceListImport->events->map(fn ($event) => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'stage' => $event->stage,
                    'status_from' => $event->status_from,
                    'status_to' => $event->status_to,
                    'metadata' => $event->metadata,
                    'duration_ms' => $event->duration_ms,
                    'user' => $event->user?->only(['id', 'name']),
                    'created_at' => $event->created_at?->toISOString(),
                ]),
                'usage' => $technical ? $priceListImport->usageRecords : null,
                'applied_prices' => $priceListImport->supplierPrices,
            ],
            'permissions' => $this->permissions($priceListImport),
        ]);
    }

    public function download(PriceListImport $priceListImport): StreamedResponse
    {
        Gate::authorize('download', $priceListImport);

        abort_if($priceListImport->path === '' || ! Storage::disk($priceListImport->disk)->exists($priceListImport->path), 404);
        $stream = Storage::disk($priceListImport->disk)->readStream($priceListImport->path);
        abort_unless(is_resource($stream), 404);

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $priceListImport->safe_name, [
            'Content-Type' => $priceListImport->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function assignSupplier(AssignPriceListSupplierRequest $request, PriceListImport $priceListImport, PriceListReviewService $review): JsonResponse
    {
        $import = $review->assignSupplier(
            $priceListImport,
            (int) $request->validated('entity_id'),
            (bool) $request->boolean('bind_source'),
            $request->user(),
        );

        return response()->json(['data' => $this->summary($import)]);
    }

    public function classify(Request $request, PriceListImport $priceListImport, PriceListStateMachine $states, PriceListAuditLogger $audit): JsonResponse
    {
        Gate::authorize('reprocess', $priceListImport);
        $data = $request->validate(['classification' => ['required', 'in:price_list,not_price_list']]);

        if ($priceListImport->status !== PriceListStatus::AwaitingClassification) {
            throw ValidationException::withMessages(['status' => 'Ручная классификация доступна только для неоднозначного документа.']);
        }

        if ($data['classification'] === 'not_price_list') {
            $priceListImport->forceFill(['document_class' => DocumentClass::NotPriceList])->save();
            $states->transition($priceListImport, PriceListStatus::NotAPriceList, PriceListStage::Classify, 100, user: $request->user());
        } else {
            $priceListImport->forceFill(['document_class' => DocumentClass::PriceList])->save();
            $states->transition($priceListImport, PriceListStatus::Extracting, PriceListStage::Extract, 18, user: $request->user());
            ExtractPriceListContent::dispatch($priceListImport->id)->afterCommit();
        }

        $audit->record($priceListImport, 'classification_confirmed', $data, $request->user());

        return response()->json(['data' => $this->summary($priceListImport->refresh())]);
    }

    public function retry(Request $request, PriceListImport $priceListImport, PriceListStateMachine $states): JsonResponse
    {
        Gate::authorize('reprocess', $priceListImport);

        if (! in_array($priceListImport->status, [
            PriceListStatus::Failed,
            PriceListStatus::UnsupportedFormat,
            PriceListStatus::Quarantined,
            PriceListStatus::Cancelled,
            PriceListStatus::NotAPriceList,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Для текущего состояния повтор этапа не требуется.']);
        }

        if ($priceListImport->items()->whereNotNull('reviewed_at')->exists()) {
            throw ValidationException::withMessages(['retry' => 'Повтор распознавания после ручных правок требует их явного сброса и поэтому заблокирован.']);
        }

        $restartValidation = in_array($priceListImport->status, [
            PriceListStatus::UnsupportedFormat,
            PriceListStatus::Quarantined,
            PriceListStatus::Cancelled,
            PriceListStatus::NotAPriceList,
        ], true);

        if ($restartValidation) {
            $priceListImport = $states->transition($priceListImport, PriceListStatus::Queued, PriceListStage::Validate, 2, user: $request->user());
        }

        $job = match ($restartValidation ? PriceListStage::Validate->value : $priceListImport->current_stage) {
            PriceListStage::Extract->value => new ExtractPriceListContent($priceListImport->id),
            PriceListStage::Ocr->value => new RecognizePriceListWithOcr($priceListImport->id),
            PriceListStage::Normalize->value => new NormalizePriceListRows($priceListImport->id),
            PriceListStage::Match->value, PriceListStage::Finalize->value => new MatchPriceListItems($priceListImport->id),
            default => new ValidatePriceListFile($priceListImport->id),
        };

        dispatch($job->afterCommit());

        return response()->json(['message' => 'Этап поставлен в очередь.'], 202);
    }

    public function cancel(Request $request, PriceListImport $priceListImport, PriceListStateMachine $states): JsonResponse
    {
        Gate::authorize('reprocess', $priceListImport);

        if (! $states->canTransition($priceListImport->status, PriceListStatus::Cancelled)) {
            throw ValidationException::withMessages(['status' => 'Текущий этап уже нельзя отменить.']);
        }

        $states->transition($priceListImport, PriceListStatus::Cancelled, progress: (int) $priceListImport->progress, user: $request->user());

        return response()->json(['data' => $this->summary($priceListImport->refresh())]);
    }

    public function applyPreview(Request $request, PriceListImport $priceListImport): JsonResponse
    {
        Gate::authorize('apply', $priceListImport);
        $data = $request->validate([
            'item_ids' => ['sometimes', 'array', 'max:20000'],
            'item_ids.*' => ['integer', 'distinct'],
        ]);
        $ids = array_values(array_map('intval', $data['item_ids'] ?? []));

        if ($ids !== [] && $priceListImport->items()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['item_ids' => 'Выбраны строки другого импорта.']);
        }

        $items = $priceListImport->items();

        if ($ids !== []) {
            $items->whereIn('id', $ids);
        }

        $matched = (clone $items)
            ->where('decision_status', ItemDecisionStatus::Matched->value)
            ->whereNull('applied_at')
            ->get(['id', 'good_id', 'price', 'currency_code', 'price_basis_quantity', 'price_basis_unit']);
        $latest = SupplierGoodPrice::query()
            ->where('entity_id', $priceListImport->entity_id)
            ->whereIn('good_id', $matched->pluck('good_id')->filter()->unique())
            ->latest('id')
            ->get()
            ->unique('good_id')
            ->keyBy('good_id');
        $warningThreshold = max(0, (float) config('ai-price-lists.matching.price_change_warning_percent', 25));
        $priceChanges = $matched->map(function ($item) use ($latest, $warningThreshold): ?array {
            $previous = $latest->get($item->good_id);

            if (! $previous || $previous->currency_code !== $item->currency_code || (float) $previous->price <= 0) {
                return null;
            }

            if (($previous->price_basis_unit ?: null) !== ($item->price_basis_unit ?: null)) {
                return null;
            }

            if (abs((float) ($previous->price_basis_quantity ?: 1) - (float) ($item->price_basis_quantity ?: 1)) > 0.000001) {
                return null;
            }

            $percent = ((float) $item->price - (float) $previous->price) / (float) $previous->price * 100;

            if (abs($percent) < $warningThreshold) {
                return null;
            }

            return [
                'item_id' => $item->id,
                'good_id' => $item->good_id,
                'previous_price' => $previous->price,
                'new_price' => $item->price,
                'currency_code' => $item->currency_code,
                'change_percent' => round($percent, 2),
            ];
        })->filter()->take(50)->values();
        $draftNames = (clone $items)
            ->where('decision_status', ItemDecisionStatus::CreateDraft->value)
            ->whereNull('applied_at')
            ->pluck('raw_name')
            ->filter()
            ->unique();
        $draftDuplicateWarnings = $draftNames->isEmpty()
            ? 0
            : Good::query()->whereIn('name', $draftNames)->count();

        return response()->json(['data' => [
            'supplier' => $priceListImport->supplier?->only(['id', 'name']),
            'selected' => $ids !== [],
            'selected_count' => $ids !== [] ? count($ids) : null,
            'prices' => $matched->count(),
            'drafts' => (clone $items)->where('decision_status', ItemDecisionStatus::CreateDraft->value)->whereNull('applied_at')->count(),
            'ignored' => (clone $items)->where('decision_status', ItemDecisionStatus::Ignored->value)->count(),
            'unreviewed' => (clone $items)->where('decision_status', ItemDecisionStatus::Unreviewed->value)->whereNotIn('match_class', [MatchClass::Invalid->value, MatchClass::Ignored->value])->count(),
            'currency' => data_get($priceListImport->document_defaults, 'currency'),
            'vat_mode' => data_get($priceListImport->document_defaults, 'vat_mode'),
            'valid_from' => data_get($priceListImport->document_defaults, 'valid_from'),
            'valid_to' => data_get($priceListImport->document_defaults, 'valid_to'),
            'price_change_warnings' => $priceChanges->count(),
            'price_changes' => $priceChanges,
            'draft_duplicate_warnings' => $draftDuplicateWarnings,
        ]]);
    }

    public function apply(ApplyPriceListRequest $request, PriceListImport $priceListImport, PriceListStateMachine $states): JsonResponse
    {
        if (! in_array($priceListImport->status, [PriceListStatus::ReadyToApply, PriceListStatus::PartiallyApplied], true)) {
            throw ValidationException::withMessages(['status' => 'Импорт пока не готов к применению.']);
        }

        $ids = array_values(array_map('intval', $request->validated('item_ids', [])));

        if ($ids !== [] && $priceListImport->items()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['item_ids' => 'Выбраны строки другого импорта.']);
        }

        $actorId = $request->user()?->id;
        $priceListImport->forceFill(['applied_by' => $actorId])->save();
        $states->transition($priceListImport, PriceListStatus::Applying, PriceListStage::Apply, 95, user: $request->user());
        ApplyConfirmedPriceList::dispatch($priceListImport->id, $actorId, $ids)->afterCommit();

        return response()->json(['message' => 'Подтверждённые изменения поставлены в очередь.'], 202);
    }

    public function entities(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PriceListImport::class);

        return response()->json(['data' => Entity::query()
            ->select(['id', 'name', 'INN'])
            ->when($request->filled('search'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%')->orWhere('INN', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')->limit(30)->get()]);
    }

    public function goods(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PriceListImport::class);
        $search = $request->string('search')->toString();

        return response()->json(['data' => Good::query()->select(['id', 'name', 'slug', 'is_published'])
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->limit(30)->get()]);
    }

    private function summary(PriceListImport $import): array
    {
        return [
            'uuid' => $import->uuid,
            'source_channel' => $import->source_channel->value,
            'source_label' => $import->source_channel->label(),
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'current_stage' => $import->current_stage,
            'progress' => $import->progress,
            'supplier' => $import->supplier?->only(['id', 'name', 'INN']),
            'file' => [
                'name' => $import->safe_name,
                'original_name' => $import->original_name,
                'extension' => $import->extension,
                'mime_type' => $import->mime_type,
                'size_bytes' => $import->size_bytes,
            ],
            'received_at' => $import->source_received_at?->toISOString(),
            'counts' => [
                'total' => $import->items_total,
                'exact' => $import->items_exact,
                'probable' => $import->items_probable,
                'unmatched' => $import->items_unmatched,
                'invalid' => $import->items_invalid,
                'applied' => $import->items_applied,
            ],
            'reviewer' => $import->reviewer?->only(['id', 'name']),
            'applied_at' => $import->applied_at?->toISOString(),
            'has_error' => filled($import->error_code),
            'requires_review' => in_array($import->status, [PriceListStatus::ReviewRequired, PriceListStatus::SupplierUnresolved], true),
            'ocr_pages' => $import->ocr_pages,
            'model_id' => $import->model_id,
            'created_at' => $import->created_at?->toISOString(),
        ];
    }

    private function permissions(PriceListImport $import): array
    {
        return [
            'download' => Gate::allows('download', $import),
            'process' => Gate::allows('reprocess', $import),
            'review' => Gate::allows('review', $import),
            'assign_supplier' => Gate::allows('assignSupplier', $import),
            'apply' => Gate::allows('apply', $import),
            'view_technical' => Gate::allows('viewTechnical', $import),
        ];
    }

    private function safeDocumentMetadata(array $metadata, bool $technical): array
    {
        unset($metadata['url'], $metadata['token'], $metadata['content'], $metadata['prompt']);

        if (! $technical) {
            unset($metadata['parser_warnings'], $metadata['ai_warnings']);
        }

        return $metadata;
    }
}
