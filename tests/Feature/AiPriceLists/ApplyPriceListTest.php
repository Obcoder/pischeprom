<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Enums\VatMode;
use App\Domain\AiPriceLists\Services\ApplyPriceListService;
use App\Jobs\AiPriceLists\ApplyConfirmedPriceList;
use App\Models\Good;
use App\Models\PriceListImportItem;
use Illuminate\Validation\ValidationException;

class ApplyPriceListTest extends AiPriceListTestCase
{
    public function test_selected_rows_apply_partially_then_completely_without_duplicate_prices(): void
    {
        $user = $this->userWith([]);
        $import = $this->import(['status' => PriceListStatus::ReadyToApply]);
        $firstGood = Good::query()->create(['name' => 'Мука', 'is_published' => true]);
        $secondGood = Good::query()->create(['name' => 'Сахар', 'is_published' => true]);
        $first = $this->matchedItem($import->id, $firstGood, 1, '100.00');
        $second = $this->matchedItem($import->id, $secondGood, 2, '200.00');

        (new ApplyConfirmedPriceList($import->id, $user->id, [$first->id]))
            ->handle(app(ApplyPriceListService::class), app(\App\Domain\AiPriceLists\Services\PriceListStateMachine::class));

        $this->assertSame(PriceListStatus::PartiallyApplied, $import->fresh()->status);
        $this->assertDatabaseCount('supplier_good_prices', 1);
        $this->assertNotNull($first->fresh()->applied_at);
        $this->assertNull($second->fresh()->applied_at);

        (new ApplyConfirmedPriceList($import->id, $user->id, [$first->id]))
            ->handle(app(ApplyPriceListService::class), app(\App\Domain\AiPriceLists\Services\PriceListStateMachine::class));
        $this->assertDatabaseCount('supplier_good_prices', 1);

        (new ApplyConfirmedPriceList($import->id, $user->id, [$second->id]))
            ->handle(app(ApplyPriceListService::class), app(\App\Domain\AiPriceLists\Services\PriceListStateMachine::class));
        $this->assertSame(PriceListStatus::Applied, $import->fresh()->status);
        $this->assertDatabaseCount('supplier_good_prices', 2);
        $this->assertDatabaseHas('price_list_events', ['price_list_import_id' => $import->id, 'event_type' => 'prices_applied']);
    }

    public function test_create_draft_produces_only_one_unpublished_good_and_provenance(): void
    {
        $user = $this->userWith([]);
        $import = $this->import(['status' => PriceListStatus::ReadyToApply]);
        $item = PriceListImportItem::query()->create([
            'price_list_import_id' => $import->id,
            'position' => 1,
            'source_page' => 2,
            'source_row' => 7,
            'raw_name' => 'Новый тестовый товар',
            'normalized_name' => 'новый тестовый товар',
            'price' => '321.500000',
            'currency_code' => 'RUB',
            'vat_mode' => VatMode::Unknown,
            'decision_status' => ItemDecisionStatus::CreateDraft,
            'match_class' => MatchClass::None,
            'row_fingerprint' => hash('sha256', 'draft-row'),
        ]);
        $job = new ApplyConfirmedPriceList($import->id, $user->id);
        $job->handle(app(ApplyPriceListService::class), app(\App\Domain\AiPriceLists\Services\PriceListStateMachine::class));
        $job->handle(app(ApplyPriceListService::class), app(\App\Domain\AiPriceLists\Services\PriceListStateMachine::class));

        $good = Good::query()->where('name', 'Новый тестовый товар')->sole();
        $this->assertFalse($good->is_published);
        $this->assertDatabaseCount('supplier_good_prices', 1);
        $price = $item->fresh()->supplierPrice;
        $this->assertSame($import->uuid, $price->provenance['import_uuid']);
        $this->assertSame(2, $price->provenance['source_locator']['page']);
        $this->assertSame($good->id, $price->good_id);
    }

    public function test_apply_is_atomic_when_any_selected_row_is_invalid(): void
    {
        $user = $this->userWith([]);
        $import = $this->import(['status' => PriceListStatus::ReadyToApply]);
        $good = Good::query()->create(['name' => 'Валидный товар', 'is_published' => true]);
        $first = $this->matchedItem($import->id, $good, 1, '100.00');
        $second = $this->matchedItem($import->id, $good, 2, null);

        try {
            app(ApplyPriceListService::class)->apply($import, $user, [$first->id, $second->id]);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertDatabaseCount('supplier_good_prices', 0);
        $this->assertNull($first->fresh()->applied_at);
        $this->assertSame(ItemDecisionStatus::Matched, $first->fresh()->decision_status);
    }

    private function matchedItem(int $importId, Good $good, int $position, ?string $price): PriceListImportItem
    {
        return PriceListImportItem::query()->create([
            'price_list_import_id' => $importId,
            'position' => $position,
            'source_sheet' => 'CSV',
            'source_row' => $position + 1,
            'raw_name' => $good->name,
            'normalized_name' => mb_strtolower($good->name),
            'price' => $price,
            'currency_code' => 'RUB',
            'vat_mode' => VatMode::Included,
            'vat_rate' => '20.00',
            'decision_status' => ItemDecisionStatus::Matched,
            'match_class' => MatchClass::Exact,
            'good_id' => $good->id,
            'row_fingerprint' => hash('sha256', "matched-{$position}"),
        ]);
    }
}
