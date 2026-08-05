<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Models\Good;
use App\Models\PriceListImport;
use App\Models\SupplierGoodPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyPriceListService
{
    public function __construct(private readonly PriceListAuditLogger $audit) {}

    /** @param list<int> $selectedItemIds */
    public function apply(PriceListImport $import, ?User $user, array $selectedItemIds = []): array
    {
        return DB::transaction(function () use ($import, $user, $selectedItemIds): array {
            $import = PriceListImport::query()->lockForUpdate()->findOrFail($import->id);

            if (! $import->entity_id) {
                throw ValidationException::withMessages(['supplier' => 'Перед применением выберите поставщика.']);
            }

            $query = $import->items()
                ->whereIn('decision_status', [ItemDecisionStatus::Matched->value, ItemDecisionStatus::CreateDraft->value])
                ->whereNull('applied_at')
                ->orderBy('position');

            if ($selectedItemIds !== []) {
                $query->whereIn('id', $selectedItemIds);
            }

            $items = $query->lockForUpdate()->get();

            if ($items->isEmpty()) {
                return ['applied' => 0, 'drafts' => 0, 'prices' => 0];
            }

            $result = ['applied' => 0, 'drafts' => 0, 'prices' => 0];

            foreach ($items as $item) {
                if (! $this->isPositiveDecimal($item->price) || ! $item->currency_code) {
                    throw ValidationException::withMessages(['items' => "В строке #{$item->position} не заполнены цена или валюта."]);
                }

                $good = $item->good;

                if ($item->decision_status === ItemDecisionStatus::CreateDraft) {
                    if (! $item->raw_name) {
                        throw ValidationException::withMessages(['items' => "В строке #{$item->position} не заполнено название."]);
                    }

                    $good = Good::query()->create([
                        'name' => $item->raw_name,
                        'description' => $item->notes,
                        'is_published' => false,
                    ]);
                    $item->forceFill(['good_id' => $good->id])->save();
                    $result['drafts']++;
                }

                if (! $good) {
                    throw ValidationException::withMessages(['items' => "В строке #{$item->position} не выбран товар."]);
                }

                $idempotencyKey = hash('sha256', implode('|', [
                    $item->id,
                    $import->entity_id,
                    $good->id,
                    $item->price,
                    $item->currency_code,
                ]));

                $price = SupplierGoodPrice::query()->firstOrCreate([
                    'price_list_import_item_id' => $item->id,
                ], [
                    'entity_id' => $import->entity_id,
                    'good_id' => $good->id,
                    'price_list_import_id' => $import->id,
                    'created_by' => $user?->id,
                    'price' => $item->price,
                    'currency_code' => $item->currency_code,
                    'vat_mode' => $item->vat_mode,
                    'vat_rate' => $item->vat_rate,
                    'price_basis_quantity' => $item->price_basis_quantity,
                    'price_basis_unit' => $item->price_basis_unit,
                    'minimum_order_quantity' => $item->minimum_order_quantity,
                    'valid_from' => $item->valid_from,
                    'valid_to' => $item->valid_to,
                    'supplier_sku' => $item->supplier_sku,
                    'idempotency_key' => $idempotencyKey,
                    'provenance' => [
                        'import_uuid' => $import->uuid,
                        'source_channel' => $import->source_channel->value,
                        'source_locator' => [
                            'sheet' => $item->source_sheet,
                            'page' => $item->source_page,
                            'table' => $item->source_table,
                            'row' => $item->source_row,
                            'range' => $item->source_range,
                        ],
                        'row_fingerprint' => $item->row_fingerprint,
                    ],
                    'created_at' => now(),
                ]);

                $item->forceFill([
                    'decision_status' => ItemDecisionStatus::Applied,
                    'applied_at' => $item->applied_at ?: now(),
                ])->save();
                $result['applied']++;
                $result['prices'] += $price->wasRecentlyCreated ? 1 : 0;
            }

            $import->forceFill([
                'items_applied' => $import->items()->whereNotNull('applied_at')->count(),
                'applied_by' => $user?->id,
            ])->save();
            $this->audit->record($import, 'prices_applied', $result, $user);

            return $result;
        }, 3);
    }

    private function isPositiveDecimal(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[0-9]+(?:\.[0-9]{1,6})?$/', $value) === 1
            && preg_match('/[1-9]/', $value) === 1;
    }
}
