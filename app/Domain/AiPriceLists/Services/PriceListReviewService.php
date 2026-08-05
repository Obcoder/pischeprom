<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Matching\PriceListItemMatcher;
use App\Domain\AiPriceLists\Normalization\TextNormalizer;
use App\Models\Good;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;
use App\Models\SupplierProductAlias;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceListReviewService
{
    private const EDITABLE_FIELDS = [
        'raw_name', 'supplier_sku', 'manufacturer_sku', 'barcode', 'manufacturer', 'brand',
        'country_of_origin', 'package_description', 'units_per_package', 'net_quantity',
        'net_quantity_unit', 'price_basis_quantity', 'price_basis_unit', 'minimum_order_quantity',
        'price', 'currency_code', 'vat_mode', 'vat_rate', 'availability', 'valid_from', 'valid_to', 'notes',
    ];

    private const IDENTITY_FIELDS = [
        'raw_name', 'supplier_sku', 'manufacturer_sku', 'barcode', 'manufacturer', 'brand',
        'country_of_origin', 'package_description', 'units_per_package', 'net_quantity',
        'net_quantity_unit', 'price_basis_quantity', 'price_basis_unit',
    ];

    public function __construct(
        private readonly TextNormalizer $text,
        private readonly PriceListItemMatcher $matcher,
        private readonly PriceListAuditLogger $audit,
        private readonly PriceListStateMachine $states,
    ) {}

    public function updateItem(PriceListImportItem $item, array $data, ?User $user): PriceListImportItem
    {
        return DB::transaction(function () use ($item, $data, $user): PriceListImportItem {
            $item = PriceListImportItem::query()->with('import')->lockForUpdate()->findOrFail($item->id);
            $this->assertReviewable($item->import);

            if ($item->applied_at !== null || $item->decision_status === ItemDecisionStatus::Applied) {
                throw ValidationException::withMessages(['item' => 'Применённую строку нельзя изменять; история цены неизменяема.']);
            }

            $before = Arr::only($item->getAttributes(), self::EDITABLE_FIELDS);
            $changes = Arr::only($data, self::EDITABLE_FIELDS);

            if (array_key_exists('raw_name', $changes)) {
                $changes['normalized_name'] = $this->text->search($changes['raw_name']);
            }

            $decisionWasMade = $item->decision_status !== ItemDecisionStatus::Unreviewed;
            $identityChanged = array_intersect(array_keys($changes), self::IDENTITY_FIELDS) !== [];
            $reviewReset = $decisionWasMade ? [
                'decision_status' => ItemDecisionStatus::Unreviewed,
                'review_reason' => 'После ручного исправления строку нужно подтвердить повторно.',
            ] : [];

            if ($identityChanged) {
                $item->candidates()->delete();
                $reviewReset = [
                    ...$reviewReset,
                    'good_id' => null,
                    'match_class' => MatchClass::None,
                    'match_method' => null,
                    'match_score' => null,
                    'review_reason' => 'Идентифицирующие поля изменены; товар нужно выбрать повторно.',
                ];
            }

            $item->forceFill([
                ...$changes,
                ...$reviewReset,
                'user_corrections' => array_merge($item->user_corrections ?: [], $changes),
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->record($item->import, 'item_corrected', [
                'item_id' => $item->id,
                'before' => $before,
                'after' => Arr::only($item->fresh()->getAttributes(), array_keys($changes)),
            ], $user);
            $this->refreshReadiness($item->import, $user);

            return $item->fresh(['good', 'candidates.good']);
        }, 3);
    }

    public function decide(PriceListImportItem $item, ItemDecisionStatus $decision, ?int $goodId, bool $saveAlias, ?User $user): PriceListImportItem
    {
        return DB::transaction(function () use ($item, $decision, $goodId, $saveAlias, $user): PriceListImportItem {
            $item = PriceListImportItem::query()->with('import')->lockForUpdate()->findOrFail($item->id);
            $import = $item->import;
            $this->assertReviewable($import);

            if ($decision === ItemDecisionStatus::Matched) {
                $good = Good::query()->find($goodId);

                if (! $good) {
                    throw ValidationException::withMessages(['good_id' => 'Выберите существующий товар.']);
                }

                $item->forceFill([
                    'good_id' => $good->id,
                    'decision_status' => $decision,
                    'match_class' => MatchClass::Exact,
                    'match_method' => 'manual',
                    'review_reason' => 'Товар подтверждён пользователем.',
                ])->save();

                if ($saveAlias) {
                    $this->saveAlias($import, $item, $good, $user);
                }
            } elseif ($decision === ItemDecisionStatus::CreateDraft) {
                if (! $item->raw_name || ! $this->isPositiveDecimal($item->price) || ! $item->currency_code) {
                    throw ValidationException::withMessages(['decision' => 'Для черновика нужны название, цена и валюта.']);
                }

                $item->forceFill([
                    'good_id' => null,
                    'decision_status' => $decision,
                    'match_class' => MatchClass::None,
                    'match_method' => 'manual_create_draft',
                    'review_reason' => 'Пользователь подтвердил создание непубличного черновика.',
                ])->save();
            } elseif ($decision === ItemDecisionStatus::Ignored) {
                $item->forceFill([
                    'good_id' => null,
                    'decision_status' => $decision,
                    'match_class' => MatchClass::Ignored,
                    'match_method' => 'manual_ignore',
                    'review_reason' => 'Строка пропущена пользователем.',
                ])->save();
            } elseif ($decision === ItemDecisionStatus::Unreviewed) {
                $item->forceFill([
                    'good_id' => null,
                    'decision_status' => $decision,
                    'match_class' => MatchClass::None,
                    'match_method' => null,
                    'match_score' => null,
                    'review_reason' => null,
                ])->save();
            } else {
                throw ValidationException::withMessages(['decision' => 'Недопустимое решение для review.']);
            }

            $item->forceFill(['reviewed_by' => $user?->id, 'reviewed_at' => now()])->save();
            $this->audit->record($import, 'item_decision_changed', [
                'item_id' => $item->id,
                'decision' => $decision->value,
                'good_id' => $item->good_id,
                'alias_saved' => $saveAlias,
            ], $user);
            $this->refreshReadiness($import, $user);

            return $item->fresh(['good', 'candidates.good']);
        }, 3);
    }

    public function assignSupplier(PriceListImport $import, int $entityId, bool $bindSource, ?User $user): PriceListImport
    {
        return DB::transaction(function () use ($import, $entityId, $bindSource, $user): PriceListImport {
            $import = PriceListImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->assertSupplierAssignable($import);
            $previousEntityId = $import->entity_id;

            if ($previousEntityId !== null && $previousEntityId !== $entityId && $import->items()->whereNotNull('applied_at')->exists()) {
                throw ValidationException::withMessages([
                    'entity_id' => 'Поставщика нельзя менять после применения хотя бы одной строки.',
                ]);
            }

            $import->forceFill(['entity_id' => $entityId, 'reviewed_by' => $user?->id, 'reviewed_at' => now()])->save();

            if ($bindSource && $import->source_channel->value === 'max' && $import->source_chat_id) {
                \App\Models\MaxChat::query()->where('chat_id', $import->source_chat_id)->update(['entity_id' => $entityId]);
            }

            if ($bindSource && $import->source_channel->value === 'email' && $import->sender_address) {
                $email = \App\Models\Email::query()->whereRaw('LOWER(address) = ?', [mb_strtolower($import->sender_address)])->first();
                $email?->entities()->syncWithoutDetaching([$entityId]);
            }

            $resetDecisions = 0;

            if ($previousEntityId !== $entityId) {
                $resetDecisions = $import->items()
                    ->whereNull('applied_at')
                    ->where('decision_status', ItemDecisionStatus::Matched->value)
                    ->where('match_method', '!=', 'manual')
                    ->update([
                        'decision_status' => ItemDecisionStatus::Unreviewed->value,
                        'good_id' => null,
                        'match_class' => MatchClass::None->value,
                        'match_method' => null,
                        'match_score' => null,
                        'review_reason' => 'Поставщик изменён; автоматическое совпадение проверяется заново.',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'updated_at' => now(),
                    ]);
                $this->matcher->matchImport($import);
            }

            $this->audit->record($import, 'supplier_assigned', [
                'entity_id_from' => $previousEntityId,
                'entity_id' => $entityId,
                'source_bound' => $bindSource,
                'automatic_decisions_reset' => $resetDecisions,
            ], $user);
            if ($import->status !== PriceListStatus::AwaitingClassification) {
                $this->refreshReadiness($import, $user);
            }

            return $import->fresh('supplier');
        }, 3);
    }

    /**
     * Fill only values that were not recognized. Existing source and user values are never overwritten.
     *
     * @return array{affected:int, preview:bool}
     */
    public function applyDefaults(PriceListImport $import, array $defaults, bool $preview, ?User $user): array
    {
        $defaults = Arr::only($defaults, ['currency_code', 'vat_mode', 'vat_rate']);
        $defaults = array_filter($defaults, static fn ($value): bool => $value !== null && $value !== '');

        return DB::transaction(function () use ($import, $defaults, $preview, $user): array {
            $import = PriceListImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->assertReviewable($import);
            $query = $import->items()
                ->whereNull('applied_at')
                ->where(function ($missing) use ($defaults): void {
                    if (array_key_exists('currency_code', $defaults)) {
                        $missing->orWhereNull('currency_code');
                    }
                    if (array_key_exists('vat_mode', $defaults)) {
                        $missing->orWhereNull('vat_mode')->orWhere('vat_mode', 'unknown');
                    }
                    if (array_key_exists('vat_rate', $defaults)) {
                        $missing->orWhereNull('vat_rate');
                    }
                });
            $affected = (clone $query)->count();

            if ($preview || $affected === 0) {
                return ['affected' => $affected, 'preview' => true];
            }

            $query->orderBy('id')->chunkById(500, function ($items) use ($defaults, $user): void {
                foreach ($items as $item) {
                    $changes = [];

                    if (array_key_exists('currency_code', $defaults) && ! $item->currency_code) {
                        $changes['currency_code'] = $defaults['currency_code'];
                    }
                    if (array_key_exists('vat_mode', $defaults) && (! $item->vat_mode || $item->vat_mode->value === 'unknown')) {
                        $changes['vat_mode'] = $defaults['vat_mode'];
                    }
                    if (array_key_exists('vat_rate', $defaults) && $item->vat_rate === null) {
                        $changes['vat_rate'] = $defaults['vat_rate'];
                    }

                    if ($changes !== []) {
                        $item->forceFill([
                            ...$changes,
                            'user_corrections' => array_merge($item->user_corrections ?: [], $changes),
                            'reviewed_by' => $user?->id,
                            'reviewed_at' => now(),
                        ])->save();
                    }
                }
            });

            $documentDefaults = $defaults;

            if (isset($documentDefaults['currency_code'])) {
                $documentDefaults['currency'] = $documentDefaults['currency_code'];
                unset($documentDefaults['currency_code']);
            }

            $import->forceFill([
                'document_defaults' => array_merge($import->document_defaults ?: [], $documentDefaults),
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
            ])->save();
            $this->audit->record($import, 'item_defaults_applied', [
                'defaults' => $defaults,
                'affected' => $affected,
            ], $user);

            return ['affected' => $affected, 'preview' => false];
        }, 3);
    }

    public function bulkConfirmExact(PriceListImport $import, ?User $user, int $limit = 5000): int
    {
        return DB::transaction(function () use ($import, $user, $limit): int {
            $import = PriceListImport::query()->lockForUpdate()->findOrFail($import->id);
            $this->assertReviewable($import);
            $ids = $import->items()
                ->where('match_class', MatchClass::Exact->value)
                ->where('decision_status', ItemDecisionStatus::Unreviewed->value)
                ->whereNotNull('good_id')
                ->limit(max(1, min(5000, $limit)))
                ->lockForUpdate()
                ->pluck('price_list_import_items.id');

            if ($ids->isEmpty()) {
                return 0;
            }

            PriceListImportItem::query()->whereIn('id', $ids)->update([
                'decision_status' => ItemDecisionStatus::Matched->value,
                'review_reason' => 'Однозначное совпадение массово подтверждено пользователем.',
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
            $import->forceFill(['reviewed_by' => $user?->id, 'reviewed_at' => now()])->save();
            $this->audit->record($import, 'exact_items_bulk_confirmed', ['count' => $ids->count()], $user);
            $this->refreshReadiness($import, $user);

            return $ids->count();
        }, 3);
    }

    private function refreshReadiness(PriceListImport $import, ?User $user): void
    {
        $import->refresh();

        if (! $import->entity_id) {
            if ($import->status !== PriceListStatus::SupplierUnresolved) {
                $this->states->transition($import, PriceListStatus::SupplierUnresolved, progress: 100, user: $user);
            }

            return;
        }

        $hasUnreviewed = $import->items()
            ->whereNotIn('match_class', [MatchClass::Ignored->value, MatchClass::Invalid->value])
            ->where('decision_status', ItemDecisionStatus::Unreviewed->value)
            ->exists();
        $hasApplicable = $import->items()->whereIn('decision_status', [ItemDecisionStatus::Matched->value, ItemDecisionStatus::CreateDraft->value])->exists();
        $target = ! $hasUnreviewed && $hasApplicable ? PriceListStatus::ReadyToApply : PriceListStatus::ReviewRequired;

        if (in_array($import->status, [PriceListStatus::SupplierUnresolved, PriceListStatus::PartiallyApplied], true)
            && $target === PriceListStatus::ReadyToApply) {
            $import = $this->states->transition($import, PriceListStatus::ReviewRequired, progress: 100, user: $user);
        }

        if ($import->status !== $target) {
            $this->states->transition($import, $target, progress: 100, user: $user);
        }
    }

    private function saveAlias(PriceListImport $import, PriceListImportItem $item, Good $good, ?User $user): void
    {
        if (! $import->entity_id || ! $item->normalized_name) {
            return;
        }

        $existing = SupplierProductAlias::query()
            ->where('entity_id', $import->entity_id)
            ->where('normalized_alias', $item->normalized_name)
            ->first();

        if ($existing && $existing->good_id !== $good->id) {
            throw ValidationException::withMessages(['save_alias' => 'Этот alias уже подтверждён для другого товара.']);
        }

        if ($item->supplier_sku) {
            $skuAlias = SupplierProductAlias::query()
                ->where('entity_id', $import->entity_id)
                ->where('supplier_sku', $item->supplier_sku)
                ->first();

            if ($skuAlias && ($skuAlias->good_id !== $good->id || $skuAlias->normalized_alias !== $item->normalized_name)) {
                throw ValidationException::withMessages(['save_alias' => 'Этот код поставщика уже закреплён за другим alias или товаром.']);
            }
        }

        SupplierProductAlias::query()->updateOrCreate([
            'entity_id' => $import->entity_id,
            'normalized_alias' => $item->normalized_name,
        ], [
            'good_id' => $good->id,
            'supplier_sku' => $item->supplier_sku,
            'alias' => $item->raw_name,
            'confirmed_by' => $user?->id,
            'confirmed_at' => now(),
        ]);
    }

    private function assertReviewable(PriceListImport $import): void
    {
        if (! in_array($import->status, [
            PriceListStatus::SupplierUnresolved,
            PriceListStatus::ReviewRequired,
            PriceListStatus::ReadyToApply,
            PriceListStatus::PartiallyApplied,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Строки можно редактировать только после завершения распознавания и не во время применения.',
            ]);
        }
    }

    private function assertSupplierAssignable(PriceListImport $import): void
    {
        if (! in_array($import->status, [
            PriceListStatus::AwaitingClassification,
            PriceListStatus::SupplierUnresolved,
            PriceListStatus::ReviewRequired,
            PriceListStatus::ReadyToApply,
            PriceListStatus::PartiallyApplied,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Поставщика нельзя менять во время обработки, применения или после завершения импорта.',
            ]);
        }
    }

    private function isPositiveDecimal(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[0-9]+(?:\.[0-9]{1,6})?$/', $value) === 1
            && preg_match('/[1-9]/', $value) === 1;
    }
}
