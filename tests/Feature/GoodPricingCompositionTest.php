<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodPriceHistory;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodPricingCalculation;
use App\Models\Measure;
use App\Models\PriceType;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Goods\GoodMeasurement;
use App\Services\Goods\GoodPricingArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodPricingCompositionTest extends TestCase
{
    use RefreshDatabase;

    private Good $good;

    private PriceType $type;

    private int $rub;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Queue::fake();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->rub = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль'])->id;
        $this->good = Good::create(['name' => 'Мак пищевой [25 кг] Чехия']);
        $this->type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $this->rub, 'is_active' => true]);
    }

    private function url(string $suffix = ''): string
    {
        return '/api/goods/'.$this->good->id.'/pricing'.$suffix;
    }

    private function input(array $overrides = []): array
    {
        return array_replace([
            'scope' => 'common', 'target_id' => $this->type->id, 'revision' => 0,
            'method' => 'manual', 'amount' => 120, 'amount_includes_vat' => true,
            'source_includes_vat' => true, 'source_vat_rate' => 20, 'vat_rate' => 20,
            'currency_id' => $this->rub, 'active' => true, 'rounding_step' => 0,
            'measurement' => $this->good->fresh()->measurement(),
        ], $overrides);
    }

    private function element(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Логистика', 'kind' => 'fixed', 'value' => 24,
            'percentage_base' => 'purchase_net', 'includes_vat' => true,
            'vat_rate' => 20, 'enabled' => true, 'currency_id' => $this->rub,
            'measurement' => $this->good->fresh()->measurement(),
        ], $overrides);
    }

    private function purchaseInput(array $overrides = []): array
    {
        return $this->input(array_replace([
            'method' => 'margin', 'source_key' => 'manual', 'purchase_amount' => 120, 'parameter' => 20,
        ], $overrides));
    }

    public function test_composition_explains_supplier_vat_each_cost_profit_and_final_rounding(): void
    {
        $quotation = Quotation::create([
            'good_id' => $this->good->id, 'unit_id' => Unit::create(['name' => 'Поставщик мака'])->id,
            'price' => 1200, 'currency_id' => $this->rub,
            'measure_id' => Measure::where('name', 'кг')->value('id'), 'denominator' => 10,
        ]);
        $result = $this->postJson($this->url('/preview'), $this->purchaseInput([
            'source_key' => 'quotation:'.$quotation->id, 'rounding_step' => 7,
            'elements' => [
                $this->element(),
                $this->element(['name' => 'Хранение', 'kind' => 'percent', 'value' => 10, 'includes_vat' => false]),
                $this->element(['name' => 'Реклама', 'kind' => 'percent', 'value' => 10, 'includes_vat' => false, 'percentage_base' => 'running_cost']),
                $this->element(['name' => 'Не включённая услуга', 'value' => 1000, 'enabled' => false]),
            ],
        ]))->assertOk()->assertJsonPath('purchase_net', 100)->assertJsonPath('source_vat', 20)
            ->assertJsonPath('extra_cost_net', 43)->assertJsonPath('cost_net', 143)
            ->assertJsonPath('profit_before_rounding', 35.75)->assertJsonPath('price_gross', 217)
            ->assertJsonPath('price_net', 180.8333)->assertJsonPath('rounding_gross', 2.5)->json();

        $steps = collect($result['breakdown'])->keyBy('key');
        $this->assertEquals(-20, $steps['purchase_vat']['amount']);
        $this->assertEquals(20, $steps['element:0']['amount']);
        $this->assertEquals(4, $steps['element:0']['vat_amount']);
        $this->assertEquals(100, $steps['element:1']['basis']);
        $this->assertEquals(10, $steps['element:1']['amount']);
        $this->assertEquals(130, $steps['element:2']['basis']);
        $this->assertEquals(13, $steps['element:2']['amount']);
        $this->assertEquals(0, $steps['element:3']['amount']);
        $this->assertFalse($steps['element:3']['enabled']);
        $this->assertEquals(35.75, $steps['profit']['amount']);
        $this->assertEquals(35.75, $steps['sale_vat']['amount']);
        $this->assertEquals(217, round($steps->sum('amount'), 4));
        $this->assertEquals(217, $steps['rounding']['subtotal']);
        foreach ($steps as $step) {
            $this->assertNotEmpty($step['formula']);
        }
        $this->assertDatabaseCount('good_price_type_values', 0);
        $this->assertDatabaseCount('good_pricing_calculations', 0);
    }

    public function test_running_cost_percent_depends_on_order_but_purchase_percent_does_not(): void
    {
        $fixed = $this->element(['value' => 20, 'includes_vat' => false]);
        $percent = $this->element(['name' => 'Хранение', 'kind' => 'percent', 'value' => 10, 'includes_vat' => false, 'percentage_base' => 'running_cost']);
        $input = $this->purchaseInput(['method' => 'markup', 'parameter' => 0, 'vat_rate' => 0]);
        $this->postJson($this->url('/preview'), [...$input, 'elements' => [$fixed, $percent]])->assertOk()->assertJsonPath('price_gross', 132);
        $this->postJson($this->url('/preview'), [...$input, 'elements' => [$percent, $fixed]])->assertOk()->assertJsonPath('price_gross', 130);
        $percent['percentage_base'] = 'purchase_net';
        foreach ([[$fixed, $percent], [$percent, $fixed]] as $elements) {
            $this->postJson($this->url('/preview'), [...$input, 'elements' => $elements])->assertOk()->assertJsonPath('price_gross', 130);
        }
    }

    public function test_manual_final_price_does_not_apply_old_source_elements_or_margin(): void
    {
        $this->postJson($this->url('/preview'), $this->input([
            'amount' => 99.9999, 'source_key' => 'quotation:999999', 'parameter' => 100,
            'elements' => [$this->element(['value' => 700])],
        ]))->assertOk()->assertJsonPath('price_gross', 99.9999)->assertJsonPath('cost_net', null)
            ->assertJsonPath('extra_cost_net', 0)->assertJsonPath('source', null)
            ->assertJsonCount(3, 'breakdown')->assertJsonPath('breakdown.0.kind', 'manual');
        $this->postJson($this->url('/preview'), $this->input(['amount' => 100, 'amount_includes_vat' => false]))
            ->assertOk()->assertJsonPath('price_net', 100)->assertJsonPath('price_gross', 120);
    }

    public function test_rounding_up_never_rounds_a_small_excess_down_at_large_steps(): void
    {
        $result = $this->postJson($this->url('/preview'), $this->input([
            'amount' => 1000000.0001, 'rounding_step' => 1000000,
        ]))->assertOk()->assertJsonPath('price_gross', 2000000)->json();
        $this->assertEquals(2000000, round(array_sum(array_column($result['breakdown'], 'amount')), 4));
    }

    public function test_saving_price_archives_the_complete_calculation_and_draft_save_does_not_assign(): void
    {
        $input = $this->purchaseInput(['elements' => [$this->element()], 'calculation_name' => 'Мак — полная себестоимость']);
        $row = $this->putJson($this->url(), $input)->assertOk()->assertJsonPath('price_gross', 180)
            ->assertJsonPath('saved_calculation.name', 'Мак — полная себестоимость')->json();
        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'saved_calculations')
            ->assertJsonPath('saved_calculations.0.id', $row['saved_calculation']['id'])
            ->assertJsonPath('saved_calculations.0.input.elements.0.name', 'Логистика')
            ->assertJsonPath('saved_calculations.0.result.cost_net', 120);
        $this->assertEquals(GoodPriceHistory::sole()->id, GoodPricingCalculation::sole()->price_history_id);
        $this->assertDatabaseCount('good_price_calculations', 0);

        $draft = $this->postJson($this->url('/calculations'), $this->input([
            'target_id' => null, 'revision' => null, 'amount' => 250, 'calculation_name' => 'Черновой сценарий',
        ]))->assertCreated()->assertJsonPath('price_gross', 250)->assertJsonPath('target_name', null)->json();
        $this->assertNull(GoodPricingCalculation::findOrFail($draft['id'])->price_history_id);
        $this->assertEquals(180, GoodPriceTypeValue::sole()->price_gross);
        $this->assertDatabaseCount('good_price_histories', 1);
        $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'saved_calculations');
    }

    public function test_saved_manual_price_is_exact_for_pieces_without_known_mass(): void
    {
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'шт.')->value('id'), 'unit_weight_kg' => null]);
        $saved = $this->postJson($this->url('/calculations'), $this->input(['amount' => 99.9999]))
            ->assertCreated()->assertJsonPath('measurement.kilograms_per_unit', null)->json();
        $input = $this->input(['method' => 'saved', 'saved_calculation_id' => $saved['id']]);
        $row = $this->putJson($this->url(), $input)->assertOk()->assertJsonPath('price_gross', 99.9999)
            ->assertJsonPath('price_net', 83.3333)->json();
        $this->putJson($this->url(), [...$input, 'id' => $row['id'], 'revision' => 1, 'saved_calculation_id' => $row['saved_calculation']['id']])
            ->assertOk()->assertJsonPath('price_gross', 99.9999);
    }

    public function test_saved_calculation_converts_units_once_without_mutating_its_original_snapshot(): void
    {
        $saved = $this->postJson($this->url('/calculations'), $this->purchaseInput())
            ->assertCreated()->assertJsonPath('price_gross', 150)->json();
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25]);
        $this->putJson($this->url(), $this->input(['method' => 'saved', 'saved_calculation_id' => $saved['id']]))
            ->assertOk()->assertJsonPath('price_gross', 3750)->assertJsonPath('result.cost_net', 2500)
            ->assertJsonPath('result.source.conversion_factor', 25);
        $original = GoodPricingCalculation::findOrFail($saved['id']);
        $this->assertEquals(150, $original->price_gross);
        $this->assertSame('кг', $original->measurement['unit_label']);
    }

    public function test_private_saved_calculations_cannot_move_to_public_prices_other_entities_or_products(): void
    {
        $entity = Entity::create(['name' => 'Индивидуальный покупатель']);
        $otherEntity = Entity::create(['name' => 'Другая организация']);
        $private = $this->postJson($this->url('/calculations'), $this->input(['scope' => 'entity', 'target_id' => $entity->id]))
            ->assertCreated()->assertJsonPath('entity_id', $entity->id)->json();
        $saved = $this->input(['method' => 'saved', 'saved_calculation_id' => $private['id']]);
        $this->putJson($this->url(), $saved)->assertUnprocessable()->assertJsonValidationErrors('saved_calculation_id');
        $this->putJson($this->url(), [...$saved, 'scope' => 'entity', 'target_id' => $otherEntity->id])
            ->assertUnprocessable()->assertJsonValidationErrors('saved_calculation_id');
        $otherGood = Good::create(['name' => 'Другой товар']);
        $this->postJson('/api/goods/'.$otherGood->id.'/pricing/preview', [...$saved, 'scope' => 'entity', 'target_id' => $entity->id])
            ->assertNotFound();
        $this->assertDatabaseCount('good_price_type_values', 0);
        $this->assertDatabaseCount('good_price_histories', 0);
        $this->assertDatabaseCount('good_price_calculations', 0);
        $this->putJson($this->url(), [...$saved, 'scope' => 'entity', 'target_id' => $entity->id])
            ->assertOk()->assertJsonPath('scope', 'entity')->assertJsonPath('price_gross', 120);
        $this->assertDatabaseCount('good_price_type_values', 0);
        $this->assertDatabaseCount('good_entity_prices', 1);
    }

    public function test_enabled_fixed_elements_require_matching_currency_and_unit_snapshots(): void
    {
        $usd = Currency::forceCreate(['code' => 'USD', 'name' => 'Доллар'])->id;
        $input = $this->purchaseInput();
        $this->postJson($this->url('/preview'), [...$input, 'elements' => [$this->element(['currency_id' => $usd])]])
            ->assertUnprocessable()->assertJsonValidationErrors('elements.0.currency_id');
        $this->postJson($this->url('/preview'), [...$input, 'elements' => [$this->element(['measurement' => null])]])
            ->assertUnprocessable()->assertJsonValidationErrors('elements.0.measurement');
        $oldUnit = ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_label' => 'коробка', 'kilograms_per_unit' => 25];
        $this->postJson($this->url('/preview'), [...$input, 'elements' => [$this->element(['measurement' => $oldUnit])]])
            ->assertUnprocessable()->assertJsonValidationErrors('elements.0.measurement');
        $this->postJson($this->url('/preview'), [...$input, 'elements' => [$this->element(['kind' => 'percent'])]])
            ->assertUnprocessable()->assertJsonValidationErrors('elements.0.includes_vat');
        $this->postJson($this->url('/preview'), [...$input, 'elements' => [$this->element(['currency_id' => $usd, 'measurement' => $oldUnit, 'enabled' => false])]])
            ->assertOk()->assertJsonPath('price_gross', 150)->assertJsonPath('extra_cost_net', 0);
        $this->assertDatabaseCount('good_pricing_calculations', 0);
    }

    public function test_revision_conflict_and_archive_failure_leave_price_history_and_archive_unchanged(): void
    {
        $row = $this->putJson($this->url(), $this->input())->assertOk()->json();
        $this->putJson($this->url(), $this->input(['id' => $row['id'], 'revision' => 0, 'amount' => 200]))->assertConflict();
        $this->assertDatabaseCount('good_pricing_calculations', 1);
        $this->assertDatabaseCount('good_price_histories', 1);

        $archive = app(GoodPricingArchive::class);
        $this->mock(GoodPricingArchive::class)->shouldReceive('record')->once()->andReturnUsing(function (...$arguments) use ($archive) {
            $archive->record(...$arguments);
            throw new \RuntimeException('Archive write failed after insert');
        });
        $this->withoutExceptionHandling();
        try {
            $this->putJson($this->url(), $this->input(['id' => $row['id'], 'revision' => 1, 'amount' => 200]));
            $this->fail('The failed archive write must abort the assignment.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Archive write failed after insert', $exception->getMessage());
        }
        $this->assertEquals(120, GoodPriceTypeValue::sole()->price_gross);
        $this->assertEquals(1, GoodPriceTypeValue::sole()->revision);
        $this->assertDatabaseCount('good_pricing_calculations', 1);
        $this->assertDatabaseCount('good_price_histories', 1);
    }

    public function test_unit_change_rescales_the_editable_chain_but_keeps_archive_evidence_immutable(): void
    {
        $row = $this->putJson($this->url(), $this->purchaseInput(['elements' => [$this->element()]]))
            ->assertOk()->assertJsonPath('price_gross', 180)->json();
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25]);
        $price = GoodPriceTypeValue::findOrFail($row['id']);
        $this->assertEquals(3000, $price->pricing_input['purchase_amount']);
        $this->assertEquals(600, $price->pricing_input['elements'][0]['value']);
        $this->assertSame('коробка', $price->pricing_input['elements'][0]['measurement']['unit_label']);
        $this->assertEquals(4500, $price->pricing_result['price_gross']);
        $this->assertEquals(4500, round(array_sum(array_column($price->pricing_result['breakdown'], 'amount')), 4));
        $this->postJson($this->url('/preview'), $this->input($price->pricing_input))->assertOk()->assertJsonPath('price_gross', 4500);
        $archived = GoodPricingCalculation::findOrFail($row['saved_calculation']['id']);
        $this->assertEquals(180, $archived->price_gross);
        $this->assertEquals(24, $archived->input['elements'][0]['value']);
        $this->assertSame('кг', $archived->measurement['unit_label']);
    }
}
