<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodEntityPrice;
use App\Models\GoodPriceHistory;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodPricingCalculation;
use App\Models\PriceType;
use App\Models\User;
use App\Services\Goods\GoodPricingArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodPricingArchiveTest extends TestCase
{
    use RefreshDatabase;

    private Good $good;

    private PriceType $type;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Queue::fake();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->currency = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $this->good = Good::create(['name' => 'Мак пищевой [25 кг] Чехия']);
        $this->type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $this->currency->id, 'is_active' => true]);
    }

    private function input(array $overrides = []): array
    {
        return array_replace([
            'scope' => 'common', 'target_id' => $this->type->id,
            'method' => 'margin', 'parameter' => 24,
            'calculation_name' => 'Мак — расчёт поставки', 'comment' => 'Первоначальная договорённость',
            'currency_id' => $this->currency->id, 'vat_rate' => 10,
            'source_key' => 'quotation:123', 'measurement' => $this->good->measurement(),
        ], $overrides);
    }

    private function pricingResult(array $overrides = []): array
    {
        return array_replace([
            'price_net' => 301.8182, 'price_gross' => 332,
            'vat_rate' => 10, 'currency_id' => $this->currency->id,
            'measurement' => $this->good->measurement(), 'cost_net' => 229.0909,
            'source' => ['key' => 'quotation:123', 'price' => 252, 'supplier' => 'АБ-Маркет'],
        ], $overrides);
    }

    public function test_explicit_calculation_save_persists_a_complete_snapshot_without_assigning_a_price(): void
    {
        $archive = app(GoodPricingArchive::class);
        $saved = $archive->record($this->good, $this->input(), $this->pricingResult());
        $this->assertSame('Мак — расчёт поставки', $saved->name);
        $this->assertEquals(332, $saved->price_gross);
        $this->assertSame(auth()->id(), $saved->created_by);
        $this->assertDatabaseCount('good_price_type_values', 0);
        $this->assertDatabaseCount('good_price_histories', 0);
        $this->assertDatabaseCount('good_price_calculations', 0);

        $this->type->update(['name' => 'Новое название']);
        $this->currency->forceFill(['code' => 'RUR'])->save();
        $dto = $archive->listing($this->good)->sole();
        $this->assertSame('workspace', $dto['archive_source']);
        $this->assertSame('Розничная', $dto['target_name']);
        $this->assertSame('RUB', $dto['currency_code']);
        $this->assertSame('кг', $dto['measurement']['unit_label']);
        $this->assertSame('АБ-Маркет', $dto['result']['source']['supplier']);
        $this->assertEquals(24, $dto['input']['parameter']);

        $saved->update(['name' => 'Уточнённое название']);
        $this->expectException(\LogicException::class);
        $saved->update(['price_gross' => 1]);
    }

    public function test_private_calculations_remain_bound_to_the_original_entity_and_never_enter_the_shared_archive(): void
    {
        $entity = Entity::forceCreate(['name' => 'Личный покупатель']);
        $archive = app(GoodPricingArchive::class);
        $saved = $archive->record($this->good, $this->input(['scope' => 'entity', 'target_id' => $entity->id]), $this->pricingResult());
        $this->assertSame('entity', $saved->scope);
        $this->assertSame($entity->id, $saved->entity_id);
        $this->assertSame($entity->id, $saved->input['target_id']);
        $this->assertDatabaseCount('good_price_calculations', 0);
        $this->assertDatabaseCount('good_entity_prices', 0);
        $this->assertDatabaseCount('good_price_type_values', 0);

        $entityId = $entity->id;
        $entity->delete();
        $saved->refresh();
        $this->assertNull($saved->entity_id);
        $this->assertSame('entity', $saved->scope);
        $this->assertSame($entityId, $saved->input['target_id']);
        $this->assertSame('Личный покупатель', $saved->target_name);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $archive->record($this->good, $this->input(['scope' => 'entity', 'target_id' => $entityId]), $this->pricingResult());
    }

    public function test_a_price_history_event_can_only_be_archived_once_and_joins_the_callers_transaction(): void
    {
        $price = GoodPriceTypeValue::create(['good_id' => $this->good->id, 'price_type_id' => $this->type->id,
            'currency_id' => $this->currency->id, 'price_net' => 301.8182, 'price_gross' => 332,
            'pricing_input' => $this->input(), 'pricing_result' => $this->pricingResult()]);
        $history = GoodPriceHistory::sole();
        $archive = app(GoodPricingArchive::class);
        $first = $archive->record($this->good, $this->input(), $this->pricingResult(), $history);
        $again = $archive->record($this->good, $this->input(), $this->pricingResult(['price_gross' => 999]), $history);
        $this->assertSame($first->id, $again->id);
        $this->assertEquals(332, $again->price_gross);
        $this->assertDatabaseCount('good_pricing_calculations', 1);

        try {
            DB::transaction(function () use ($archive, $price) {
                $price->update(['price_gross' => 500]);
                $archive->record($this->good, $this->input(), $this->pricingResult(['price_gross' => 500]), GoodPriceHistory::latest('id')->first());
                throw new \RuntimeException('Abort whole save');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Abort whole save', $exception->getMessage());
        }
        $this->assertEquals(332, $price->fresh()->price_gross);
        $this->assertDatabaseCount('good_price_histories', 1);
        $this->assertDatabaseCount('good_pricing_calculations', 1);
    }

    public function test_migration_recovers_missing_saved_calculations_from_price_history_without_recalculating_or_reassigning(): void
    {
        $input = collect($this->input())->except(['scope', 'target_id', 'measurement'])->all();
        $result = $this->pricingResult();
        $price = GoodPriceTypeValue::create(['good_id' => $this->good->id, 'price_type_id' => $this->type->id,
            'currency_id' => $this->currency->id, 'price_net' => 301.8182, 'price_gross' => 332,
            'pricing_input' => $input, 'pricing_result' => $result]);
        $oldHistory = GoodPriceHistory::sole();
        // The audit remains authoritative even after another calculation replaced this one.
        $price->update(['price_net' => 310, 'price_gross' => 341,
            'pricing_result' => $this->pricingResult(['price_net' => 310, 'price_gross' => 341])]);
        $entity = Entity::forceCreate(['name' => 'Закрытый покупатель']);
        $private = GoodEntityPrice::create(['good_id' => $this->good->id, 'entity_id' => $entity->id,
            'currency_id' => $this->currency->id, 'price_net' => 280, 'price_gross' => 308,
            'pricing_input' => $input, 'pricing_result' => $this->pricingResult(['price_net' => 280, 'price_gross' => 308])]);
        $private->delete();
        $entity->delete();
        // Baseline/manual legacy prices without a calculation must not invent one.
        GoodPriceTypeValue::create(['good_id' => $this->good->id, 'price_type_id' => $this->type->id, 'price_gross' => 777]);

        $migration = require database_path('migrations/2026_10_12_210000_create_good_pricing_calculations.php');
        $migration->down();
        $migration->up();
        $migration->recoverHistory();
        $this->assertDatabaseCount('good_pricing_calculations', 3);
        $restored = GoodPricingCalculation::where('price_history_id', $oldHistory->id)->sole();
        $this->assertEquals(332, $restored->price_gross);
        $this->assertSame($oldHistory->after['pricing_result'], $restored->result);
        $this->assertSame($this->type->id, $restored->input['target_id']);
        $this->assertSame('common', $restored->input['scope']);
        $this->assertEquals($oldHistory->created_at, $restored->created_at);
        $this->assertSame($oldHistory->actor_name, $restored->created_by_name);
        $privateRestored = GoodPricingCalculation::where('scope', 'entity')->sole();
        $this->assertNull($privateRestored->entity_id);
        $this->assertSame($entity->id, $privateRestored->input['target_id']);
        $this->assertSame('Закрытый покупатель', $privateRestored->target_name);
        $this->assertEquals(308, $privateRestored->price_gross);
        $this->assertEquals(341, $price->fresh()->price_gross);
        $this->assertDatabaseCount('good_price_calculations', 0);
        $this->assertDatabaseCount('good_entity_prices', 0);
    }
}
