<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodPriceCalculation;
use App\Models\Measure;
use App\Models\Quotation;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Good $good;

    private Unit $supplier;

    private Currency $currency;

    private Measure $measure;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());
        $this->staff = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($this->staff);
        $this->good = Good::create(['name' => 'Манго слайсы']);
        $this->supplier = Unit::create(['name' => 'Лето-К']);
        $this->currency = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $this->measure = Measure::firstOrCreate(['name' => 'кг']);
    }

    private function payload(array $overrides = []): array
    {
        return [...[
            'good_id' => $this->good->id, 'unit_id' => $this->supplier->id,
            'price' => '258.1234', 'currency_id' => $this->currency->id,
            'measure_id' => $this->measure->id, 'denominator' => '2.5',
        ], ...$overrides];
    }

    public function test_offer_requisites_and_four_decimal_price_round_trip_through_both_creation_endpoints(): void
    {
        foreach (['/api/quotations', '/web/quotation/store'] as $endpoint) {
            $response = $this->postJson($endpoint, $this->payload([
                'quoted_at' => '2026-10-01', 'valid_until' => '2026-11-30',
                'price_includes_vat' => true, 'vat_rate' => '10.0000',
                'minimum_quantity' => '12.125', 'lead_time_days' => 3, 'notes' => 'Самовывоз. Оплата по счёту.',
            ]))->assertCreated()->assertJsonPath('price', '258.1234')
                ->assertJsonPath('denominator', 2.5)->assertJsonPath('currency.code', 'RUB')
                ->assertJsonPath('measure.name', 'кг')->assertJsonPath('quoted_at', '2026-10-01')
                ->assertJsonPath('valid_until', '2026-11-30')->assertJsonPath('price_includes_vat', true)
                ->assertJsonPath('vat_rate', '10.0000')->assertJsonPath('minimum_quantity', '12.1250')
                ->assertJsonPath('lead_time_days', 3)->assertJsonPath('missing_fields', [])
                ->assertJsonPath('is_complete', true)->assertJsonPath('is_current', true);
            $id = $response->json('id');
            $this->getJson('/api/quotations/'.$id)->assertOk()->assertJsonPath('price', '258.1234')
                ->assertJsonPath('notes', 'Самовывоз. Оплата по счёту.');
            $this->assertDatabaseHas('quotations', ['id' => $id, 'currency_id' => $this->currency->id, 'price' => 258.1234]);
            $this->patchJson('/api/quotations/'.$id, ['notes' => 'Доставка по вторникам.'])
                ->assertOk()->assertJsonPath('notes', 'Доставка по вторникам.')
                ->assertJsonPath('currency_id', $this->currency->id)->assertJsonPath('quoted_at', '2026-10-01');
        }
    }

    public function test_new_offer_requires_explicit_currency_and_unit_and_does_not_guess_them(): void
    {
        foreach (['/api/quotations', '/web/quotation/store'] as $endpoint) {
            $payload = $this->payload();
            unset($payload['currency_id'], $payload['measure_id']);
            $this->postJson($endpoint, $payload)->assertUnprocessable()
                ->assertJsonValidationErrors(['currency_id', 'measure_id']);
        }
        $this->assertDatabaseCount('quotations', 0);
        $payload = $this->payload();
        unset($payload['denominator']);
        $this->postJson('/api/quotations', $payload)->assertCreated()
            ->assertJsonPath('denominator', 1)->assertJsonPath('quoted_at', '2026-10-15')
            ->assertJsonPath('price_includes_vat', null)->assertJsonPath('vat_rate', null);
    }

    public function test_historical_offer_marks_missing_fields_and_can_be_repaired_without_recreating_it(): void
    {
        $legacy = Quotation::create(['good_id' => $this->good->id, 'unit_id' => $this->supplier->id,
            'price' => 258, 'measure_id' => null, 'currency_id' => null, 'denominator' => 1]);
        $createdAt = $legacy->created_at;
        $this->getJson('/api/quotations/'.$legacy->id)->assertOk()
            ->assertJsonPath('currency_id', null)->assertJsonPath('quoted_at', null)
            ->assertJsonPath('missing_fields', ['currency_id', 'measure_id'])->assertJsonPath('is_complete', false);
        $this->patchJson('/api/quotations/'.$legacy->id, ['price' => 260])
            ->assertUnprocessable()->assertJsonValidationErrors(['currency_id', 'measure_id']);
        $this->assertEquals(258, $legacy->fresh()->price);
        $this->patchJson('/api/quotations/'.$legacy->id, ['currency_id' => $this->currency->id, 'measure_id' => $this->measure->id])
            ->assertOk()->assertJsonPath('id', $legacy->id)->assertJsonPath('price', '258.0000')
            ->assertJsonPath('is_complete', true)->assertJsonPath('quoted_at', null);
        $this->assertTrue($createdAt->eq($legacy->fresh()->created_at));
        $this->assertDatabaseCount('quotations', 1);
        $this->patchJson('/api/quotations/'.$legacy->id, ['currency_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('currency_id');
    }

    public function test_invalid_financial_terms_are_rejected_atomically(): void
    {
        $id = $this->postJson('/api/quotations', $this->payload())->assertCreated()->json('id');
        $cases = [
            ['price' => -1], ['price' => '1.12345'], ['denominator' => 0],
            ['currency_id' => 99999], ['measure_id' => 99999], ['minimum_quantity' => 0],
            ['lead_time_days' => -1], ['vat_rate' => 101, 'price_includes_vat' => true],
            ['vat_rate' => 10], ['price_includes_vat' => false],
            ['quoted_at' => '2026-10-15', 'valid_until' => '2026-10-14'],
            ['quoted_at' => 'not-a-date'],
        ];
        foreach ($cases as $changes) {
            $this->patchJson('/api/quotations/'.$id, $changes)->assertUnprocessable();
            $quote = Quotation::findOrFail($id);
            $this->assertSame('258.1234', $quote->price);
            $this->assertSame(2.5, $quote->denominator);
            $this->assertNull($quote->vat_rate);
        }
        // An explicit zero rate is different from missing tax information.
        $this->patchJson('/api/quotations/'.$id, ['vat_rate' => 0, 'price_includes_vat' => false])
            ->assertOk()->assertJsonPath('vat_rate', '0.0000')->assertJsonPath('price_includes_vat', false);
        $this->patchJson('/api/quotations/'.$id, ['vat_rate' => null, 'price_includes_vat' => null])
            ->assertOk()->assertJsonPath('vat_rate', null)->assertJsonPath('price_includes_vat', null);
    }

    public function test_validity_is_inclusive_and_historical_unknown_dates_are_retained(): void
    {
        $unknown = Quotation::create($this->payload());
        $current = Quotation::create($this->payload(['quoted_at' => '2026-10-15', 'valid_until' => '2026-10-15']));
        $expired = Quotation::create($this->payload(['quoted_at' => '2026-10-01', 'valid_until' => '2026-10-14']));
        $future = Quotation::create($this->payload(['quoted_at' => '2026-10-16']));
        $this->assertTrue($unknown->is_current);
        $this->assertTrue($current->is_current);
        $this->assertFalse($expired->is_current);
        $this->assertFalse($future->is_current);
        $this->assertEqualsCanonicalizing([$unknown->id, $current->id], Quotation::current()->pluck('id')->all());
        $this->getJson('/api/quotations')->assertOk()->assertJsonCount(4);
    }

    public function test_delete_retains_calculation_and_sent_supplier_order_snapshots(): void
    {
        $quotation = Quotation::create($this->payload());
        $snapshot = ['price' => '258.1234', 'currency_id' => $this->currency->id, 'denominator' => 2.5];
        $calculation = GoodPriceCalculation::create(['good_id' => $this->good->id, 'quotation_id' => $quotation->id,
            'input' => ['source' => $snapshot], 'result' => ['sale_gross_per_kg' => 150], 'sale_gross_per_kg' => 150]);
        $order = SupplierOrder::create(['unit_id' => $this->supplier->id, 'to' => [], 'cc' => [],
            'create_key' => (string) Str::uuid(), 'create_payload_hash' => str_repeat('a', 64)]);
        $item = $order->items()->create(['position' => 1, 'good_id' => $this->good->id, 'name' => $this->good->name,
            'unit_label' => 'кг', 'quantity' => 5, 'unit_price' => 103.2494, 'currency_id' => $this->currency->id,
            'source_order_item_ids' => [], 'quotation_id' => $quotation->id, 'quotation_snapshot' => $snapshot]);
        $order->update(['status' => 'sent', 'sent_at' => now()]);
        $this->deleteJson('/api/quotations/'.$quotation->id)->assertNoContent();
        $this->assertNull($calculation->fresh()->quotation_id);
        $this->assertSame(['source' => $snapshot], $calculation->fresh()->input);
        $this->assertSame(150.0, $calculation->fresh()->sale_gross_per_kg);
        $this->assertNull($item->fresh()->quotation_id);
        $this->assertSame($snapshot, $item->fresh()->quotation_snapshot);
        $this->assertSame(103.2494, $item->fresh()->unit_price);
        $this->assertSame('sent', $order->fresh()->status);
    }

    public function test_customer_cannot_read_or_write_supplier_offers(): void
    {
        $quotation = Quotation::create($this->payload());
        $this->actingAs(User::factory()->create(['type' => 'individual', 'status' => 'active']));
        $this->getJson('/api/quotations')->assertForbidden();
        $this->getJson('/api/quotations/'.$quotation->id)->assertForbidden();
        $this->postJson('/api/quotations', $this->payload())->assertForbidden();
        $this->postJson('/web/quotation/store', $this->payload())->assertForbidden();
        $this->patchJson('/api/quotations/'.$quotation->id, ['price' => 0])->assertForbidden();
        $this->deleteJson('/api/quotations/'.$quotation->id)->assertForbidden();
        $this->assertDatabaseCount('quotations', 1);
    }
}
