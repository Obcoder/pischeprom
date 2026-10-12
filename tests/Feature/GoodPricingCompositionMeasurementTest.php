<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodEntityPrice;
use App\Models\GoodPriceHistory;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodPricingElement;
use App\Models\Measure;
use App\Models\PriceType;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Goods\GoodMeasurement;
use App\Services\Goods\GoodPricingArchive;
use App\Services\Goods\GoodPricingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodPricingCompositionMeasurementTest extends TestCase
{
    use RefreshDatabase;

    private Good $good;

    private Currency $currency;

    private PriceType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Queue::fake();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->currency = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $this->good = Good::create(['name' => 'Мак для пересчёта']);
        $this->type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $this->currency->id, 'is_active' => true]);
    }

    private function input(array $override = []): array
    {
        return array_replace([
            'scope' => 'common', 'target_id' => $this->type->id, 'method' => 'margin', 'parameter' => 20,
            'source_key' => 'manual', 'purchase_amount' => 120, 'amount_includes_vat' => true,
            'source_includes_vat' => true, 'source_vat_rate' => 20, 'vat_rate' => 20,
            'currency_id' => $this->currency->id, 'rounding_step' => 7, 'measurement' => $this->good->measurement(),
            'elements' => [
                ['name' => 'Доставка', 'kind' => 'fixed', 'value' => 12, 'includes_vat' => true, 'vat_rate' => 20,
                    'currency_id' => $this->currency->id, 'measurement' => $this->good->measurement()],
                ['name' => 'Хранение', 'kind' => 'percent', 'value' => 10, 'percentage_base' => 'running_cost', 'includes_vat' => false, 'vat_rate' => 0],
            ],
        ], $override);
    }

    private function assign(array $input, array $result): GoodPriceTypeValue
    {
        return GoodPriceTypeValue::create([
            'good_id' => $this->good->id, 'price_type_id' => $this->type->id,
            'currency_id' => $this->currency->id, 'price_net' => $result['price_net'],
            'price_gross' => $result['price_gross'], 'vat_rate' => $result['vat_rate'],
            'pricing_input' => $input, 'pricing_result' => $result,
        ]);
    }

    private function assertBalanced(array $result): void
    {
        $subtotal = 0;
        foreach ($result['breakdown'] as $stage) {
            $subtotal = round($subtotal + $stage['amount'], 4);
            $this->assertEquals($subtotal, $stage['subtotal']);
        }
        $this->assertEquals($result['price_gross'], $subtotal);
        $this->assertEquals($result['price_gross'], round(array_sum(array_column($result['breakdown'], 'amount')), 4));
    }

    public function test_complete_composition_and_private_price_rescale_with_history_but_archive_and_library_remain_immutable(): void
    {
        $input = $this->input();
        $result = app(GoodPricingCalculator::class)->calculate($this->good, $input);
        $public = $this->assign($input, $result);
        $entity = Entity::forceCreate(['name' => 'Покупатель по договору']);
        $private = GoodEntityPrice::create([
            'good_id' => $this->good->id, 'entity_id' => $entity->id, 'currency_id' => $this->currency->id,
            'price_net' => $result['price_net'], 'price_gross' => $result['price_gross'], 'vat_rate' => 20,
            'pricing_input' => [...$input, 'scope' => 'entity', 'target_id' => $entity->id], 'pricing_result' => $result,
        ]);
        $archive = app(GoodPricingArchive::class)->record($this->good, $input, $result);
        $archiveBefore = $archive->fresh()->getAttributes();
        $library = GoodPricingElement::forceCreate(['good_id' => $this->good->id, ...$input['elements'][0]]);
        $libraryBefore = $library->fresh()->getAttributes();

        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25]);
        foreach ([$public->fresh(), $private->fresh()] as $price) {
            $this->assertEquals(4550, $price->price_gross);
            $this->assertEquals(2, $price->revision);
            $this->assertEquals(3000, $price->pricing_input['purchase_amount']);
            $this->assertEquals(175, $price->pricing_input['rounding_step']);
            $this->assertEquals(20, $price->pricing_input['parameter']);
            $this->assertEquals(300, $price->pricing_input['elements'][0]['value']);
            $this->assertEquals($this->good->measurement(), $price->pricing_input['elements'][0]['measurement']);
            $this->assertEquals(10, $price->pricing_input['elements'][1]['value']);
            $rescaled = $price->pricing_result;
            foreach (['price_net', 'price_gross', 'cost_net', 'profit', 'purchase_net', 'source_gross', 'source_vat',
                'extra_cost_net', 'profit_before_rounding', 'vat_amount', 'rounding_gross'] as $field) {
                $this->assertEquals(round($result[$field] * 25, 4), $rescaled[$field], $field);
            }
            $this->assertEquals(3000, $rescaled['source']['price']);
            $this->assertEquals(1, $rescaled['source']['conversion_factor']);
            $this->assertSame('коробка', $rescaled['source']['unit_label']);
            $this->assertEquals(300, $rescaled['breakdown'][2]['input_amount']);
            $this->assertEquals(10, $rescaled['breakdown'][3]['input_amount']);
            $this->assertEquals(2750, $rescaled['breakdown'][3]['basis']);
            $this->assertEquals(50, $rescaled['breakdown'][2]['vat_amount']);
            $this->assertBalanced($rescaled);
            $this->assertStringContainsString('× 25', $rescaled['breakdown'][0]['formula']);
            $this->assertStringContainsString('→ коробка', $rescaled['breakdown'][0]['formula']);
            $history = GoodPriceHistory::where('price_record_id', $price->id)->where('scope', $price instanceof GoodEntityPrice ? 'entity' : 'common')->latest('id')->first();
            $this->assertSame('unit_changed', $history->event);
            $this->assertEquals($input['measurement'], $history->before['measurement']);
            $this->assertEquals($this->good->measurement(), $history->after['measurement']);
            $this->assertEquals($result, $history->before['pricing_result']);
            $this->assertEquals($rescaled, $history->after['pricing_result']);
        }
        $this->assertSame($archiveBefore, $archive->fresh()->getAttributes());
        $this->assertSame($libraryBefore, $library->fresh()->getAttributes());
        $recalculated = app(GoodPricingCalculator::class)->calculate($this->good, $public->fresh()->pricing_input);
        $this->assertEquals(4550, $recalculated['price_gross']);
    }

    public function test_fractional_conversion_reconciles_chain_and_preserves_supplier_source_price_and_unit(): void
    {
        $supplier = Unit::create(['name' => 'Поставщик мака']);
        $quotation = Quotation::create(['good_id' => $this->good->id, 'unit_id' => $supplier->id, 'price' => 1200,
            'currency_id' => $this->currency->id, 'measure_id' => $this->good->measure_id, 'denominator' => 10]);
        $input = $this->input(['source_key' => 'quotation:'.$quotation->id]);
        unset($input['purchase_amount']);
        $before = app(GoodPricingCalculator::class)->calculate($this->good, $input);
        $price = $this->assign($input, $before);
        $gram = Measure::firstOrCreate(['name' => 'г']);
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => $gram->id]);
        $result = $price->fresh()->pricing_result;
        $this->assertEquals(0.182, $result['price_gross']);
        $this->assertBalanced($result);
        $this->assertEquals(1200, $result['source']['price']);
        $this->assertEquals(1200, $result['breakdown'][0]['input_amount']);
        $this->assertEquals(0.001, $result['source']['conversion_factor']);
        $this->assertSame('кг', $result['source']['unit_label']);
        $this->assertEquals(0.012, $price->fresh()->pricing_input['elements'][0]['value']);
        $this->assertEquals(10, $price->fresh()->pricing_input['elements'][1]['value']);
        $last = $result['breakdown'][array_key_last($result['breakdown'])];
        $this->assertEquals(0.0004, $last['amount']);
        $this->assertStringContainsString('согласование точности', $last['formula']);
    }

    public function test_manual_price_and_absolute_profit_amount_rescale_while_disabled_foreign_unit_template_is_preserved(): void
    {
        $input = $this->input(['method' => 'manual', 'amount' => 120, 'rounding_step' => 0]);
        $manual = $this->assign($input, app(GoodPricingCalculator::class)->calculate($this->good, $input));
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25]);
        $this->assertEquals(3000, $manual->fresh()->pricing_input['amount']);
        $this->assertEquals(3000, $manual->fresh()->price_gross);
        $this->assertNull($manual->fresh()->pricing_result['cost_net']);
        $this->assertBalanced($manual->fresh()->pricing_result);
        $manual->delete();
        $stale = ['name' => 'Старый шаблон', 'kind' => 'fixed', 'value' => 7, 'enabled' => false,
            'includes_vat' => false, 'vat_rate' => 0, 'currency_id' => $this->currency->id,
            'measurement' => ['measure_id' => Measure::where('name', 'кг')->value('id'), 'unit_label' => 'кг', 'kilograms_per_unit' => 1]];
        $absoluteInput = $this->input(['method' => 'absolute', 'parameter' => 13.37, 'rounding_step' => 0, 'elements' => [$stale]]);
        $absolute = $this->assign($absoluteInput, app(GoodPricingCalculator::class)->calculate($this->good, $absoluteInput));
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'т')->value('id')]);
        $this->assertEquals(534.8, $absolute->fresh()->pricing_input['parameter']);
        $this->assertSame($stale, $absolute->fresh()->pricing_input['elements'][0]);
        $this->assertEquals(7, $absolute->fresh()->pricing_result['breakdown'][2]['input_amount']);
        $profit = collect($absolute->fresh()->pricing_result['breakdown'])->firstWhere('key', 'profit');
        $this->assertEquals(534.8, $profit['parameter']);
        $this->assertBalanced($absolute->fresh()->pricing_result);
    }
}
