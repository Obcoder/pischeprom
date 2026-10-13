<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodEntityPrice;
use App\Models\GoodPriceTypeValue;
use App\Models\Measure;
use App\Models\Order;
use App\Models\PriceType;
use App\Models\Quotation;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Goods\GoodMeasurement;
use App\Services\SupplierOrders\SupplierOrderWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Unit $supplier;

    private Good $good;

    private Currency $rub;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Queue::fake();
        Mail::fake();
        $this->staff = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($this->staff);
        $this->supplier = Unit::create(['name' => 'Поставщик свежих ягод']);
        $this->good = Good::create(['name' => 'Облепиха замороженная']);
        $this->rub = Currency::forceCreate(['name' => 'Рубль', 'code' => 'RUB']);
    }

    private function item(array $override = []): array
    {
        return array_replace(['good_id' => $this->good->id, 'quantity' => 1.123456,
            'measurement' => $this->good->fresh()->measurement()], $override);
    }

    private function input(array $override = []): array
    {
        return array_replace(['create_key' => (string) Str::uuid(), 'unit_id' => $this->supplier->id,
            'items' => [$this->item()]], $override);
    }

    private function url(?int $id = null, string $suffix = ''): string
    {
        return '/api/supplier-orders'.($id ? '/'.$id : '').$suffix;
    }

    public function test_draft_crud_revisions_and_idempotency_preserve_explicit_snapshots_without_stock_or_mail(): void
    {
        $input = $this->input(['warehouse_id' => Warehouse::first()->id, 'delivery_date' => '2026-10-20',
            'items' => [$this->item(['unit_price' => 12.3456, 'currency_id' => $this->rub->id])]]);
        $created = $this->postJson($this->url(), $input)->assertCreated()->assertJsonPath('status', 'draft')
            ->assertJsonPath('revision', 1)->assertJsonPath('include_prices', true)->assertJsonPath('items.0.quantity', 1.123456)
            ->assertJsonPath('items.0.unit_price', 12.3456)->assertJsonPath('items.0.name', $this->good->name)
            ->assertJsonPath('items.0.measurement.unit_label', 'кг')->assertJsonPath('delivery_date', '2026-10-20')->json();
        $this->assertStringStartsWith('СО-', $created['number']);
        $this->postJson($this->url(), $input)->assertOk()->assertJsonPath('id', $created['id']);
        $this->postJson($this->url(), [...$input, 'body' => 'Другое письмо'])->assertConflict();
        $this->assertDatabaseCount('supplier_orders', 1);
        $this->assertDatabaseCount('supplier_order_items', 1);
        $next = [...$input, 'revision' => 1, 'internal_note' => 'Согласовать транспорт', 'items' => [$this->item(['quantity' => 3])]];
        $this->patchJson($this->url($created['id']), $next)->assertOk()->assertJsonPath('revision', 2)
            ->assertJsonPath('items.0.quantity', 3)->assertJsonPath('items.0.unit_price', null);
        $this->patchJson($this->url($created['id']), $next)->assertConflict();
        $this->deleteJson($this->url($created['id']), ['revision' => 1])->assertConflict();
        $this->getJson($this->url($created['id']))->assertOk()->assertJsonPath('items_count', 1)
            ->assertJsonPath('warehouse.id', Warehouse::first()->id);
        // A transport retry does not undo later edits to the same order.
        $this->postJson($this->url(), $input)->assertOk()->assertJsonPath('revision', 2)->assertJsonPath('items.0.quantity', 3);
        $this->deleteJson($this->url($created['id']), ['revision' => 2])->assertNoContent();
        $this->assertDatabaseCount('supplier_order_items', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
        $this->assertDatabaseCount('purchases', 0);
        Mail::assertNothingSent();
    }

    public function test_price_is_never_inferred_from_retail_private_or_supplier_prices_without_explicit_selection(): void
    {
        $type = PriceType::create(['name' => 'Розничная', 'code' => 'retail', 'currency_id' => $this->rub->id]);
        GoodPriceTypeValue::create(['good_id' => $this->good->id, 'price_type_id' => $type->id, 'price_gross' => 999, 'currency_id' => $this->rub->id]);
        $entity = Entity::forceCreate(['name' => 'Покупатель со специальной ценой']);
        GoodEntityPrice::create(['good_id' => $this->good->id, 'entity_id' => $entity->id, 'currency_id' => $this->rub->id,
            'price_net' => 600, 'price_gross' => 720, 'vat_rate' => 20]);
        Quotation::create(['good_id' => $this->good->id, 'unit_id' => $this->supplier->id,
            'price' => 500, 'denominator' => 1, 'measure_id' => $this->good->measure_id, 'currency_id' => $this->rub->id]);
        $this->postJson($this->url(), $this->input())->assertCreated()->assertJsonPath('items.0.unit_price', null)
            ->assertJsonPath('items.0.currency_id', null)->assertJsonPath('totals_by_currency', [])
            ->assertJsonPath('has_unpriced_items', true);
        $this->postJson($this->url(), $this->input(['items' => [$this->item(['unit_price' => 20, 'currency_id' => $this->rub->id])]]))
            ->assertCreated()->assertJsonPath('items.0.unit_price', 20)->assertJsonPath('include_prices', true);
    }

    public function test_supplier_quotation_is_scoped_and_converted_to_current_accounting_unit(): void
    {
        $quotation = Quotation::create(['good_id' => $this->good->id, 'unit_id' => $this->supplier->id,
            'price' => 1200, 'denominator' => 10, 'measure_id' => $this->good->measure_id, 'currency_id' => $this->rub->id]);
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25]);
        $this->postJson($this->url(), $this->input(['items' => [$this->item(['quotation_id' => $quotation->id])]]))
            ->assertCreated()->assertJsonPath('items.0.unit_price', 3000)->assertJsonPath('items.0.currency_code', 'RUB')
            ->assertJsonPath('items.0.measurement.kilograms_per_unit', 25)->assertJsonPath('items.0.quotation_snapshot.conversion_factor', 25);
        $quotation->update(['unit_id' => Unit::create(['name' => 'Другой поставщик'])->id]);
        $this->postJson($this->url(), $this->input(['items' => [$this->item(['quotation_id' => $quotation->id])]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.quotation_id');
        $quotation->update(['unit_id' => $this->supplier->id, 'measure_id' => Measure::where('name', 'л')->value('id')]);
        $this->postJson($this->url(), $this->input(['items' => [$this->item(['quotation_id' => $quotation->id])]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.quotation_id');
        $this->assertDatabaseCount('supplier_orders', 1);
    }

    public function test_unknown_units_stale_snapshots_duplicates_currency_and_precision_are_rejected_atomically(): void
    {
        $payload = $this->input();
        foreach ([['quantity' => 0], ['quantity' => 0.0000001], ['unit_price' => 0.00001, 'currency_id' => $this->rub->id],
            ['unit_price' => 5], ['currency_id' => 99999], ['good_id' => 99999],
            ['measurement' => ['measure_id' => 99999, 'unit_label' => 'кг', 'kilograms_per_unit' => 1]]] as $invalid) {
            $this->postJson($this->url(), [...$payload, 'items' => [$this->item($invalid)]])->assertUnprocessable();
        }
        $this->postJson($this->url(), [...$payload, 'items' => [$this->item(), $this->item()]])->assertUnprocessable();
        $this->postJson($this->url(), [...$payload, 'unit_id' => 99999])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->postJson($this->url(), [...$payload, 'warehouse_id' => 99999])->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');
        $this->postJson($this->url(), [...$payload, 'create_key' => null])->assertUnprocessable()->assertJsonValidationErrors('create_key');
        $missingUnit = Good::create(['name' => 'Без единицы', 'measure_id' => null]);
        $this->postJson($this->url(), [...$payload, 'items' => [$this->item(['good_id' => $missingUnit->id])]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.measurement');
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25]);
        $this->postJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.measurement');
        $this->assertDatabaseCount('supplier_orders', 0);
        $this->assertDatabaseCount('supplier_order_items', 0);
    }

    public function test_source_order_items_must_match_the_good_and_are_only_internal_links(): void
    {
        $customerOrder = Order::create(['entity_id' => Entity::forceCreate(['name' => 'Заказчик'])->id]);
        $source = $customerOrder->items()->create(['good_id' => $this->good->id, 'good_name' => $this->good->name,
            'quantity' => 5, 'price_gross' => 777, 'line_total' => 3885]);
        $created = $this->postJson($this->url(), $this->input(['items' => [$this->item(['source_order_item_ids' => [$source->id]])]]))
            ->assertCreated()->assertJsonPath('items.0.source_order_item_ids', [$source->id])->assertJsonPath('items.0.unit_price', null)->json();
        $this->postJson($this->url(), $this->input(['items' => [$this->item(['source_order_item_ids' => [99999]])]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.source_order_item_ids');
        $other = Good::create(['name' => 'Морковь']);
        $this->postJson($this->url(), $this->input(['items' => [$this->item(['good_id' => $other->id, 'source_order_item_ids' => [$source->id]])]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.source_order_item_ids');
        $source->delete();
        $this->getJson($this->url($created['id']))->assertOk()->assertJsonPath('items.0.source_order_item_ids', [$source->id]);
    }

    public function test_state_transitions_cannot_forge_sending_and_sent_items_keep_original_snapshot(): void
    {
        $created = $this->postJson($this->url(), $this->input())->assertCreated()->json();
        foreach (['sending', 'sent', 'send_unknown', 'confirmed', 'closed'] as $status) {
            $this->postJson($this->url($created['id'], '/status'), ['revision' => 1, 'status' => $status])->assertUnprocessable();
        }
        $order = SupplierOrder::findOrFail($created['id']);
        $order->update(['status' => 'sent', 'revision' => 2, 'sent_at' => now()]);
        $this->good->update(['name' => 'Новое название']);
        app(GoodMeasurement::class)->update($this->good, ['measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25]);
        $this->getJson($this->url($order->id))->assertOk()->assertJsonPath('items.0.name', 'Облепиха замороженная')
            ->assertJsonPath('items.0.measurement.unit_label', 'кг')->assertJsonPath('editable', false);
        $this->patchJson($this->url($order->id), $this->input(['revision' => 2]))->assertConflict();
        $this->deleteJson($this->url($order->id), ['revision' => 2])->assertConflict();
        $this->postJson($this->url($order->id, '/status'), ['revision' => 1, 'status' => 'confirmed'])->assertConflict();
        $this->postJson($this->url($order->id, '/status'), ['revision' => 2, 'status' => 'confirmed'])->assertOk()->assertJsonPath('revision', 3);
        $this->postJson($this->url($order->id, '/status'), ['revision' => 3, 'status' => 'closed'])->assertOk()->assertJsonPath('revision', 4);
        $this->postJson($this->url($order->id, '/status'), ['revision' => 4, 'status' => 'cancelled'])->assertUnprocessable();
        $this->assertDatabaseCount('good_stock_movements', 0);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('orders', 0);
        try {
            $order->items()->first()->update(['quantity' => 20]);
            $this->fail('Sent item modification must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertEquals(1.123456, $order->items()->first()->quantity);
    }

    public function test_registry_filters_by_supplier_status_number_and_good_and_normalization_has_no_writes(): void
    {
        $writer = app(SupplierOrderWriter::class);
        $normalized = $writer->normalize($this->input());
        $this->assertSame($this->good->name, $normalized['items'][0]['name']);
        $this->assertSame([], $normalized['to']);
        $this->assertTrue($normalized['include_prices']);
        $this->assertDatabaseCount('supplier_orders', 0);
        $first = $this->postJson($this->url(), $this->input())->assertCreated()->json();
        $other = Unit::create(['name' => 'Мясокомбинат']);
        $second = $this->postJson($this->url(), $this->input(['unit_id' => $other->id]))->assertCreated()->json();
        $this->postJson($this->url($second['id'], '/status'), ['revision' => 1, 'status' => 'cancelled'])->assertOk();
        $this->getJson($this->url().'?status=draft&unit_id='.$this->supplier->id)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $first['id']);
        foreach ([$first['number'], $this->supplier->name] as $search) {
            $this->getJson($this->url().'?search='.urlencode($search))->assertOk()->assertJsonPath('total', 1);
        }
        $this->getJson($this->url().'?search='.urlencode($this->good->name).'&per_page=1')->assertOk()
            ->assertJsonPath('total', 2)->assertJsonPath('last_page', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/supplier-order-units?search='.urlencode('свежих'))->assertOk()
            ->assertExactJson([['id' => $this->supplier->id, 'name' => $this->supplier->name]]);
    }

    public function test_supplier_registry_and_mutations_require_verified_staff(): void
    {
        $created = $this->postJson($this->url(), $this->input())->assertCreated()->json();
        foreach ([['type' => 'customer', 'status' => 'active'], ['type' => 'employee', 'status' => 'inactive'],
            ['type' => 'employee', 'status' => 'active', 'email_verified_at' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->getJson($this->url())->assertForbidden();
            $this->getJson($this->url($created['id']))->assertForbidden();
            $this->postJson($this->url(), $this->input())->assertForbidden();
            $this->patchJson($this->url($created['id']), $this->input(['revision' => 1]))->assertForbidden();
            $this->deleteJson($this->url($created['id']), ['revision' => 1])->assertForbidden();
            $this->getJson('/api/supplier-order-units')->assertForbidden();
        }
        auth()->forgetGuards();
        $this->app['auth']->guard()->logout();
        $this->getJson($this->url())->assertUnauthorized();
        $this->assertSame('draft', DB::table('supplier_orders')->where('id', $created['id'])->value('status'));
    }
}
