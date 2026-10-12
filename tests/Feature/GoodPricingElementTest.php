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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodPricingElementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Good $good;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Queue::fake();
        $this->staff = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($this->staff);
        $this->currency = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $this->good = Good::create(['name' => 'Мак пищевой']);
    }

    private function url(?int $element = null, ?Good $good = null): string
    {
        return '/api/goods/'.($good ?? $this->good)->id.'/pricing/elements'.($element ? '/'.$element : '');
    }

    private function input(array $override = []): array
    {
        return array_replace([
            'name' => 'Логистика', 'kind' => 'fixed', 'value' => 12.3456,
            'percentage_base' => 'purchase_net', 'includes_vat' => true, 'vat_rate' => 20,
            'currency_id' => $this->currency->id, 'measurement' => $this->good->fresh()->measurement(),
        ], $override);
    }

    public function test_templates_have_explicit_units_authorship_and_optimistic_revision_control(): void
    {
        $created = $this->postJson($this->url(), $this->input(['measurement' => [
            ...$this->good->measurement(), 'unit_label' => 'Недостоверная подпись',
        ]]))->assertCreated()->assertJsonPath('value', 12.3456)->assertJsonPath('revision', 1)
            ->assertJsonPath('measurement.unit_label', $this->good->measurement()['unit_label'])
            ->assertJsonPath('created_by', $this->staff->id)->assertJsonPath('currency_code', 'RUB')->json();
        $editor = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($editor)->patchJson($this->url($created['id']), $this->input(['revision' => 1, 'value' => 25, 'name' => 'Доставка']))
            ->assertOk()->assertJsonPath('revision', 2)->assertJsonPath('created_by', $this->staff->id)
            ->assertJsonPath('updated_by', $editor->id)->assertJsonPath('value', 25);
        $this->patchJson($this->url($created['id']), $this->input(['revision' => 1, 'value' => 999]))->assertConflict();
        $this->deleteJson($this->url($created['id']), ['revision' => 1])->assertConflict();
        $this->patchJson($this->url($created['id']), $this->input())->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->deleteJson($this->url($created['id']))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->getJson($this->url())->assertOk()->assertJsonCount(1)->assertJsonPath('0.value', 25);
        $this->deleteJson($this->url($created['id']), ['revision' => 2])->assertNoContent();
        $this->getJson($this->url())->assertOk()->assertExactJson([]);
    }

    public function test_templates_are_scoped_to_the_good_and_available_only_to_staff(): void
    {
        $created = $this->postJson($this->url(), $this->input())->assertCreated()->json();
        $other = Good::create(['name' => 'Другой товар']);
        $this->getJson($this->url(null, $other))->assertOk()->assertExactJson([]);
        $this->patchJson($this->url($created['id'], $other), $this->input(['revision' => 1]))->assertNotFound();
        $this->deleteJson($this->url($created['id'], $other), ['revision' => 1])->assertNotFound();
        $customer = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $this->actingAs($customer);
        $this->getJson($this->url())->assertForbidden();
        $this->postJson($this->url(), $this->input())->assertForbidden();
        $this->patchJson($this->url($created['id']), $this->input(['revision' => 1]))->assertForbidden();
        $this->deleteJson($this->url($created['id']), ['revision' => 1])->assertForbidden();
        $this->assertDatabaseCount('good_pricing_elements', 1);
        auth()->forgetGuards();
        $this->app['auth']->guard()->logout();
        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_currency_precision_and_measurement_are_validated_and_percent_has_an_explicit_net_basis(): void
    {
        foreach ([['currency_id' => 999999], ['value' => -1], ['value' => 1e12], ['value' => 0.00001],
            ['name' => '   '], ['percentage_base' => 'sale'], ['vat_rate' => 101], ['kind' => 'subtract']] as $invalid) {
            $this->postJson($this->url(), $this->input($invalid))->assertUnprocessable();
        }
        $this->postJson($this->url(), $this->input(['kind' => 'percent']))->assertUnprocessable()->assertJsonValidationErrors('includes_vat');
        $percent = $this->postJson($this->url(), $this->input([
            'kind' => 'percent', 'value' => 2.5, 'percentage_base' => 'running_cost', 'includes_vat' => false,
        ]))->assertCreated()->assertJsonPath('vat_rate', 0)->assertJsonPath('percentage_base', 'running_cost')->json();
        $old = $this->good->measurement();
        $this->good->update(['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25]);
        $this->postJson($this->url(), $this->input(['measurement' => $old]))->assertUnprocessable()->assertJsonValidationErrors('measurement');
        $this->patchJson($this->url($percent['id']), $this->input(['revision' => 1, 'measurement' => $old]))
            ->assertUnprocessable()->assertJsonValidationErrors('measurement');
        $this->postJson($this->url(), $this->input(['value' => 0]))->assertCreated()
            ->assertJsonPath('measurement.kilograms_per_unit', 25)->assertJsonPath('value', 0);
        $this->getJson($this->url())->assertOk()->assertJsonPath('0.measurement.measure_id', $old['measure_id']);
    }

    public function test_template_edits_and_deletion_preserve_assigned_public_private_and_history_snapshots(): void
    {
        $created = $this->postJson($this->url(), $this->input())->assertCreated()->json();
        $snapshot = ['method' => 'margin', 'elements' => [$created]];
        $type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $this->currency->id, 'is_active' => true]);
        $price = GoodPriceTypeValue::create(['good_id' => $this->good->id, 'price_type_id' => $type->id,
            'currency_id' => $this->currency->id, 'price_net' => 100, 'price_gross' => 120, 'vat_rate' => 20,
            'pricing_input' => $snapshot, 'pricing_result' => ['price_net' => 100, 'price_gross' => 120]]);
        $entity = Entity::forceCreate(['name' => 'Индивидуальный покупатель']);
        $private = GoodEntityPrice::create(['good_id' => $this->good->id, 'entity_id' => $entity->id,
            'currency_id' => $this->currency->id, 'price_net' => 200, 'price_gross' => 240, 'vat_rate' => 20,
            'pricing_input' => $snapshot, 'pricing_result' => ['price_net' => 200, 'price_gross' => 240]]);
        $history = GoodPriceHistory::orderBy('id')->get()->toArray();
        $this->patchJson($this->url($created['id']), $this->input(['revision' => 1, 'value' => 99, 'name' => 'Совсем другой элемент']))->assertOk();
        $this->deleteJson($this->url($created['id']), ['revision' => 2])->assertNoContent();
        $this->assertSame($snapshot, $price->fresh()->pricing_input);
        $this->assertSame($snapshot, $private->fresh()->pricing_input);
        $this->assertEquals(120, $price->fresh()->price_gross);
        $this->assertEquals(240, $private->fresh()->price_gross);
        $this->assertSame($history, GoodPriceHistory::orderBy('id')->get()->toArray());
        $this->assertNull(GoodPricingElement::find($created['id']));
    }
}
