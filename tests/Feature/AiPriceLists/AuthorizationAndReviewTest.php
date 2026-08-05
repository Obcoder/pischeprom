<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Models\Entity;
use App\Models\Good;
use App\Models\PriceListImportItem;
use App\Models\SupplierProductAlias;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

class AuthorizationAndReviewTest extends AiPriceListTestCase
{
    public function test_header_exposes_the_protected_price_list_entry_point(): void
    {
        $layout = (string) file_get_contents(resource_path('js/Layouts/VerwalterLayout.vue'));

        $this->assertStringContainsString("route('Ameise.ai.price-lists.index')", $layout);
        $this->assertStringContainsString('mdi-robot-outline', $layout);
        $this->assertStringNotContainsString('v-if="canViewAiPriceLists"', $layout);

        $this->get('/Ameise/ai/price-lists')
            ->assertRedirect('/login');
    }

    public function test_pages_metadata_and_private_download_require_permissions(): void
    {
        $import = $this->import();
        Storage::disk('local')->put($import->path, 'private supplier document');
        $viewer = $this->userWith(['ai_price_lists.view']);
        $technical = $this->userWith(['ai_price_lists.view', 'ai_price_lists.view_technical']);
        $outsider = $this->userWith([]);

        $this->get('/Ameise/ai/price-lists')->assertRedirect();
        $this->getJson('/api/ai/price-lists')->assertUnauthorized();
        $this->actingAs($outsider)->getJson("/api/ai/price-lists/{$import->uuid}")->assertForbidden();

        $this->actingAs($viewer)
            ->get("/Ameise/ai/price-lists/{$import->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Ai/PriceLists/Show')
                ->where('importUuid', $import->uuid));
        $this->actingAs($viewer)
            ->get("/api/ai/price-lists/{$import->uuid}/download")
            ->assertOk()
            ->assertDownload('test.csv')
            ->assertStreamedContent('private supplier document');
        $this->actingAs($viewer)
            ->getJson("/api/ai/price-lists/{$import->uuid}")
            ->assertOk()
            ->assertJsonPath('data.usage', null)
            ->assertJsonPath('permissions.apply', false);

        $import->forceFill(['status' => PriceListStatus::Quarantined])->save();
        $this->actingAs($viewer)
            ->getJson("/api/ai/price-lists/{$import->uuid}")
            ->assertOk()
            ->assertJsonPath('permissions.download', false);
        $this->actingAs($viewer)->get("/api/ai/price-lists/{$import->uuid}/download")->assertForbidden();
        $this->actingAs($technical)->get("/api/ai/price-lists/{$import->uuid}/download")->assertOk();
    }

    public function test_user_can_correct_row_choose_candidate_and_save_supplier_alias_with_audit(): void
    {
        $import = $this->import(['items_total' => 1, 'items_probable' => 1]);
        $good = Good::query()->create(['name' => 'Мука пшеничная высший сорт', 'is_published' => true]);
        $item = $this->item($import->id, [
            'raw_name' => 'Мука пш.',
            'normalized_name' => 'мука пш',
            'match_class' => MatchClass::Probable,
            'match_score' => '0.8000',
        ]);
        $item->candidates()->create([
            'good_id' => $good->id,
            'rank' => 1,
            'method' => 'token_similarity',
            'score' => '0.8000',
            'score_components' => ['token_jaccard' => 0.8],
        ]);
        $reviewer = $this->userWith(['ai_price_lists.view', 'ai_price_lists.review']);

        $this->actingAs($reviewer)
            ->patchJson("/api/ai/price-lists/{$import->uuid}/items/{$item->id}", [
                'raw_name' => 'Мука пшеничная',
                'price' => '1250.50',
                'currency_code' => 'RUB',
                'vat_mode' => 'included',
                'vat_rate' => '20.00',
            ])->assertOk()->assertJsonPath('data.raw_name', 'Мука пшеничная');

        $this->actingAs($reviewer)
            ->postJson("/api/ai/price-lists/{$import->uuid}/items/{$item->id}/decision", [
                'decision' => 'matched',
                'good_id' => $good->id,
                'save_alias' => true,
            ])->assertOk()->assertJsonPath('data.decision_status', 'matched');

        $this->assertDatabaseHas('supplier_product_aliases', [
            'entity_id' => $import->entity_id,
            'good_id' => $good->id,
            'normalized_alias' => 'мука пшеничная',
            'confirmed_by' => $reviewer->id,
        ]);
        $this->assertDatabaseHas('price_list_events', ['price_list_import_id' => $import->id, 'event_type' => 'item_corrected']);
        $this->assertDatabaseHas('price_list_events', ['price_list_import_id' => $import->id, 'event_type' => 'item_decision_changed']);
        $this->assertSame('ready_to_apply', $import->fresh()->status->value);
    }

    public function test_conflicting_supplier_alias_cannot_be_reassigned(): void
    {
        $import = $this->import();
        $first = Good::query()->create(['name' => 'Первый товар', 'is_published' => true]);
        $second = Good::query()->create(['name' => 'Второй товар', 'is_published' => true]);
        SupplierProductAlias::query()->create([
            'entity_id' => $import->entity_id,
            'good_id' => $first->id,
            'alias' => 'Одинаковое имя',
            'normalized_alias' => 'одинаковое имя',
        ]);
        $item = $this->item($import->id, ['raw_name' => 'Одинаковое имя', 'normalized_name' => 'одинаковое имя']);
        $reviewer = $this->userWith(['ai_price_lists.review']);

        $this->actingAs($reviewer)
            ->postJson("/api/ai/price-lists/{$import->uuid}/items/{$item->id}/decision", [
                'decision' => 'matched',
                'good_id' => $second->id,
                'save_alias' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('save_alias');

        $this->assertSame($first->id, SupplierProductAlias::query()->sole()->good_id);
    }

    public function test_review_is_locked_during_apply_and_partial_review_can_return_to_ready(): void
    {
        $import = $this->import(['status' => PriceListStatus::Applying]);
        $item = $this->item($import->id);
        $reviewer = $this->userWith(['ai_price_lists.review']);

        $this->actingAs($reviewer)
            ->patchJson("/api/ai/price-lists/{$import->uuid}/items/{$item->id}", ['price' => '101.00'])
            ->assertForbidden();

        $import->forceFill(['status' => PriceListStatus::PartiallyApplied])->save();
        $good = Good::query()->create(['name' => 'Мука для частичного импорта', 'is_published' => true]);
        $this->actingAs($reviewer)
            ->postJson("/api/ai/price-lists/{$import->uuid}/items/{$item->id}/decision", [
                'decision' => 'matched',
                'good_id' => $good->id,
            ])->assertOk();

        $this->assertSame(PriceListStatus::ReadyToApply, $import->fresh()->status);
    }

    public function test_partial_date_update_cannot_invert_existing_validity_period(): void
    {
        $import = $this->import();
        $item = $this->item($import->id, ['valid_from' => '2026-08-10', 'valid_to' => '2026-08-31']);
        $reviewer = $this->userWith(['ai_price_lists.review']);

        $this->actingAs($reviewer)
            ->patchJson("/api/ai/price-lists/{$import->uuid}/items/{$item->id}", [
                'valid_to' => '2026-08-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valid_to');
    }

    public function test_changing_supplier_rematches_automatic_alias_and_is_blocked_after_apply(): void
    {
        $import = $this->import(['status' => PriceListStatus::ReadyToApply]);
        $newSupplier = Entity::query()->create(['name' => 'Новый поставщик']);
        $oldGood = Good::query()->create(['name' => 'Старое сопоставление', 'is_published' => true]);
        $newGood = Good::query()->create(['name' => 'Новое сопоставление', 'is_published' => true]);
        $item = $this->item($import->id, [
            'decision_status' => ItemDecisionStatus::Matched,
            'match_class' => MatchClass::Exact,
            'match_method' => 'supplier_alias',
            'good_id' => $oldGood->id,
        ]);
        SupplierProductAlias::query()->create([
            'entity_id' => $newSupplier->id,
            'good_id' => $newGood->id,
            'alias' => 'Мука',
            'normalized_alias' => 'мука',
        ]);
        $assigner = $this->userWith(['ai_price_lists.assign_supplier']);

        $this->actingAs($assigner)
            ->patchJson("/api/ai/price-lists/{$import->uuid}/supplier", ['entity_id' => $newSupplier->id])
            ->assertOk();

        $this->assertSame($newGood->id, $item->fresh()->good_id);
        $this->assertSame(ItemDecisionStatus::Unreviewed, $item->fresh()->decision_status);
        $this->assertSame(PriceListStatus::ReviewRequired, $import->fresh()->status);

        $item->forceFill(['applied_at' => now(), 'decision_status' => ItemDecisionStatus::Applied])->save();
        $thirdSupplier = Entity::query()->create(['name' => 'Третий поставщик']);

        $this->actingAs($assigner)
            ->patchJson("/api/ai/price-lists/{$import->uuid}/supplier", ['entity_id' => $thirdSupplier->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entity_id');
    }

    private function item(int $importId, array $attributes = []): PriceListImportItem
    {
        return PriceListImportItem::query()->create([
            'price_list_import_id' => $importId,
            'position' => 1,
            'source_sheet' => 'CSV',
            'source_row' => 2,
            'raw_text' => 'Мука;1250,50;RUB',
            'raw_name' => 'Мука',
            'normalized_name' => 'мука',
            'price' => '1250.500000',
            'currency_code' => 'RUB',
            'decision_status' => ItemDecisionStatus::Unreviewed,
            'match_class' => MatchClass::None,
            'row_fingerprint' => hash('sha256', uniqid('item', true)),
            ...$attributes,
        ]);
    }
}
