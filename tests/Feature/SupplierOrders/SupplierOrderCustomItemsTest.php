<?php

namespace Tests\Feature\SupplierOrders;

use App\Models\Currency;
use App\Models\Good;
use App\Models\Measure;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOrderCustomItemsTest extends TestCase
{
    use RefreshDatabase;

    private Unit $supplier;

    private Currency $rub;

    private Measure $kg;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Queue::fake();
        Mail::fake();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->supplier = Unit::create(['name' => 'Поставщик новых товаров']);
        $this->rub = Currency::where('code', 'RUB')->first() ?? Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $this->kg = Measure::firstOrCreate(['name' => 'кг']);
    }

    private function item(array $overrides = []): array
    {
        return array_replace(['row_key' => (string) Str::uuid(), 'good_id' => null,
            'name' => 'Ягоды нового урожая', 'create_good' => false,
            'measurement' => ['measure_id' => null, 'unit_label' => 'ящик', 'kilograms_per_unit' => null],
            'quantity' => 2.25, 'unit_price' => 100.1234, 'currency_id' => $this->rub->id,
            'note' => 'Не замораживать', 'source_order_item_ids' => []], $overrides);
    }

    private function input(array $items): array
    {
        return ['create_key' => (string) Str::uuid(), 'unit_id' => $this->supplier->id, 'auto_subject' => true,
            'body' => 'Добрый день!', 'items' => $items];
    }

    public function test_multiple_free_rows_preview_save_reopen_and_reorder_without_creating_catalog_records(): void
    {
        $first = $this->item();
        $second = $this->item(['name' => 'Образцы продукции', 'unit_price' => null, 'currency_id' => null]);
        $input = $this->input([$first, $second]);
        $this->postJson('/api/supplier-orders/preview', $input)->assertOk()
            ->assertJsonPath('text', fn ($text) => str_contains($text, 'Ягоды нового урожая')
                && str_contains($text, 'Образцы продукции') && str_contains($text, 'ящик'));
        $this->assertDatabaseCount('supplier_orders', 0);
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()
            ->assertJsonPath('items.0.row_key', $first['row_key'])->assertJsonPath('items.1.row_key', $second['row_key'])
            ->assertJsonPath('items.0.good_id', null)->assertJsonPath('items.1.good_id', null)
            ->assertJsonPath('items.0.unit_label', 'ящик')->assertJsonPath('items.0.line_total', 225.2777)
            ->assertJsonPath('totals_by_currency.RUB', 225.2777)->json();
        $this->getJson('/api/supplier-orders/'.$saved['id'])->assertOk()->assertJsonPath('items.0.name', $first['name']);
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 1,
            'items' => [$second, [...$first, 'name' => 'Ягоды, точное название', 'quantity' => 3]]])->assertOk()
            ->assertJsonPath('items.0.row_key', $second['row_key'])->assertJsonPath('items.1.name', 'Ягоды, точное название');
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 2, 'items' => [$second]])
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.row_key', $second['row_key']);
        $this->getJson('/api/supplier-orders/'.$saved['id'].'/preview')->assertOk()
            ->assertJsonPath('text', fn ($text) => str_contains($text, 'Образцы продукции') && ! str_contains($text, 'Ягоды'));
        $this->assertDatabaseCount('goods', 0);
        $this->assertDatabaseCount('quotations', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
        Mail::assertNothingSent();
    }

    public function test_registration_creates_unpublished_product_only_on_save_and_retry_reuses_it(): void
    {
        $item = $this->item(['create_good' => true,
            'measurement' => ['measure_id' => $this->kg->id, 'unit_label' => 'ложная единица', 'kilograms_per_unit' => 999]]);
        $input = $this->input([$item, $this->item(['name' => 'Разовая позиция'])]);
        $this->postJson('/api/supplier-orders/preview', $input)->assertOk()
            ->assertJsonPath('text', fn ($text) => str_contains($text, 'кг') && ! str_contains($text, 'ложная единица'));
        $this->assertDatabaseCount('goods', 0);
        $this->assertDatabaseCount('supplier_orders', 0);
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()
            ->assertJsonPath('items.0.create_good', false)->assertJsonPath('items.0.unit_label', 'кг')
            ->assertJsonPath('items.0.measurement.kilograms_per_unit', 1)->json();
        $goodId = $saved['items'][0]['good_id'];
        $this->assertNotNull($goodId);
        $this->assertDatabaseHas('goods', ['id' => $goodId, 'name' => $item['name'], 'measure_id' => $this->kg->id,
            'is_published' => false, 'unit_weight_kg' => null]);
        $this->postJson('/api/supplier-orders', $input)->assertOk()->assertJsonPath('id', $saved['id'])
            ->assertJsonPath('items.0.good_id', $goodId);
        // Even a subsequent edit that repeats the registration intent resolves
        // the row key back to the already-created Good, not another new Good.
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 1])
            ->assertOk()->assertJsonPath('items.0.good_id', $goodId);
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 2,
            'items' => $saved['items']])->assertOk()->assertJsonPath('items.0.good_id', $goodId);
        $this->assertDatabaseCount('goods', 1);
        $this->assertDatabaseCount('quotations', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
        $this->getJson('/api/units/'.$this->supplier->id.'/supplier-order-context?tab=catalog')
            ->assertOk()->assertJsonPath('data.0.good_id', $goodId)->assertJsonPath('data.0.measurement.measure_id', $this->kg->id);
        $this->getJson('/api/units/'.$this->supplier->id.'/supplier-order-context?tab=assortment')->assertOk()
            ->assertJsonPath('meta.total', 0);
        Mail::assertNothingSent();
    }

    public function test_existing_free_row_can_be_registered_without_creating_a_second_order_line(): void
    {
        $input = $this->input([$this->item()]);
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()->json();
        $items = [[...$saved['items'][0], 'create_good' => true,
            'measurement' => ['measure_id' => $this->kg->id, 'unit_label' => 'кг', 'kilograms_per_unit' => null]]];
        $this->postJson('/api/supplier-orders/preview', [...$input, 'items' => $items,
            'supplier_order_id' => $saved['id'], 'revision' => 1])->assertOk();
        $this->assertDatabaseCount('goods', 0);
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 1, 'items' => $items])
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.row_key', $input['items'][0]['row_key'])
            ->assertJsonPath('items.0.good_id', fn ($id) => is_int($id) && $id > 0);
        $this->assertDatabaseCount('goods', 1);
        $this->assertDatabaseCount('supplier_order_items', 1);
    }

    public function test_invalid_later_item_rolls_back_registered_goods_and_order_changes(): void
    {
        $registered = $this->item(['create_good' => true, 'measurement' => ['measure_id' => $this->kg->id]]);
        $bad = $this->item(['currency_id' => null]);
        $this->postJson('/api/supplier-orders', $this->input([$registered, $bad]))->assertUnprocessable()
            ->assertJsonValidationErrors('items.1.currency_id');
        $this->assertDatabaseCount('goods', 0);
        $this->assertDatabaseCount('supplier_orders', 0);
        $savedInput = $this->input([$this->item()]);
        $saved = $this->postJson('/api/supplier-orders', $savedInput)->assertCreated()->json();
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$savedInput,
            'revision' => 1, 'items' => [$registered, $bad]])->assertUnprocessable();
        $this->assertDatabaseCount('goods', 0);
        $this->assertDatabaseHas('supplier_orders', ['id' => $saved['id'], 'revision' => 1]);
        $this->assertDatabaseHas('supplier_order_items', ['supplier_order_id' => $saved['id'],
            'row_key' => $savedInput['items'][0]['row_key'], 'good_id' => null]);
    }

    public function test_free_row_identity_name_unit_and_registration_intent_participate_in_retry_fingerprint(): void
    {
        $input = $this->input([$this->item(['measurement' => ['measure_id' => $this->kg->id, 'unit_label' => 'кг']])]);
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()->json();
        $this->postJson('/api/supplier-orders', $input)->assertOk()->assertJsonPath('id', $saved['id']);
        foreach ([['name' => 'Другой товар'], ['row_key' => (string) Str::uuid()], ['create_good' => true],
            ['measurement' => ['measure_id' => $this->kg->id, 'unit_label' => 'коробка']]] as $change) {
            $this->postJson('/api/supplier-orders', [...$input, 'items' => [array_replace($input['items'][0], $change)]])
                ->assertConflict();
        }
        $this->assertDatabaseCount('goods', 0);
        $this->assertDatabaseCount('supplier_orders', 1);
    }

    public function test_custom_names_units_and_notes_are_escaped_in_email_preview_and_saved_render(): void
    {
        $input = $this->input([$this->item(['name' => '<script>alert(1)</script>', 'note' => '<img src=x onerror=alert(1)>',
            'measurement' => ['measure_id' => null, 'unit_label' => '<b>комплект</b>']])]);
        $check = fn ($html) => ! str_contains($html, '<script>') && ! str_contains($html, '<img src=x')
            && str_contains($html, '&lt;script&gt;') && str_contains($html, '&lt;b&gt;комплект&lt;/b&gt;');
        $this->postJson('/api/supplier-orders/preview', $input)->assertOk()->assertJsonPath('html', $check);
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()->json();
        $this->getJson('/api/supplier-orders/'.$saved['id'].'/preview')->assertOk()->assertJsonPath('html', $check);
        Mail::assertNothingSent();
    }

    public function test_required_custom_fields_duplicate_keys_and_catalog_relationships_are_validated(): void
    {
        $cases = [
            [['row_key' => null], 'row_key'], [['name' => '   '], 'name'],
            [['measurement' => ['measure_id' => null, 'unit_label' => ' ']], 'measurement.unit_label'],
            [['create_good' => true], 'measurement.measure_id'],
            [['measurement' => ['measure_id' => 9999999]], 'measurement.measure_id'],
            [['source_order_item_ids' => [9999999]], 'good_id'], [['quotation_id' => 9999999], 'good_id'],
        ];
        foreach ($cases as [$changes, $field]) {
            $this->postJson('/api/supplier-orders', $this->input([$this->item($changes)]))->assertUnprocessable()
                ->assertJsonValidationErrors('items.0.'.$field);
        }
        $item = $this->item();
        $this->postJson('/api/supplier-orders', $this->input([$item, [...$item, 'row_key' => strtoupper($item['row_key'])]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.row_key');
        $this->assertDatabaseCount('goods', 0);
        $this->assertDatabaseCount('supplier_orders', 0);
    }

    public function test_catalog_measurement_guards_and_duplicate_goods_remain_enforced_in_mixed_orders(): void
    {
        $good = Good::create(['name' => 'Каталожный товар']);
        $catalog = ['good_id' => $good->id, 'quantity' => 2, 'measurement' => $good->fresh()->measurement()];
        $this->postJson('/api/supplier-orders', $this->input([$this->item(), [...$catalog,
            'measurement' => ['measure_id' => null]]]))->assertUnprocessable()->assertJsonValidationErrors('items.1.measurement');
        $this->postJson('/api/supplier-orders', $this->input([$this->item(), $catalog, $catalog]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.good_id');
        $this->postJson('/api/supplier-orders', $this->input([$this->item(), [...$catalog, 'create_good' => true]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.create_good');
        $this->postJson('/api/supplier-orders', $this->input([$this->item(), $catalog]))->assertCreated()
            ->assertJsonPath('items.0.good_id', null)->assertJsonPath('items.1.good_id', $good->id);
        $this->assertDatabaseCount('goods', 1);
    }

    public function test_forged_custom_mass_is_ignored_and_context_has_known_units(): void
    {
        $piece = Measure::firstOrCreate(['name' => 'шт']);
        $this->getJson('/api/units/'.$this->supplier->id.'/supplier-order-context?tab=catalog')->assertOk()
            ->assertJsonFragment(['id' => $this->kg->id, 'name' => 'кг'])
            ->assertJsonFragment(['id' => $piece->id, 'name' => 'шт']);
        $input = $this->input([$this->item(['measurement' => [
            'measure_id' => null, 'unit_label' => 'ящик', 'kilograms_per_unit' => 100]]),
            $this->item(['create_good' => true, 'measurement' => [
                'measure_id' => $piece->id, 'unit_label' => 'шт', 'kilograms_per_unit' => 100]])]);
        $this->postJson('/api/supplier-orders', $input)->assertCreated()
            ->assertJsonPath('items.0.measurement.kilograms_per_unit', null)
            ->assertJsonPath('items.1.measurement.kilograms_per_unit', null);
        $this->assertDatabaseHas('goods', ['measure_id' => $piece->id, 'unit_weight_kg' => null]);
    }

    public function test_saved_catalog_row_key_cannot_be_transferred_to_another_product(): void
    {
        $first = Good::create(['name' => 'Первый товар']);
        $second = Good::create(['name' => 'Второй товар']);
        $items = array_map(fn ($good) => ['good_id' => $good->id, 'quantity' => 1,
            'measurement' => $good->fresh()->measurement()], [$first, $second]);
        $input = $this->input($items);
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()->json();
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 1,
            'items' => [$items[0], [...$items[1], 'row_key' => $saved['items'][0]['row_key']]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.row_key');
        $this->assertDatabaseHas('supplier_orders', ['id' => $saved['id'], 'revision' => 1]);
        $this->assertDatabaseHas('supplier_order_items', ['supplier_order_id' => $saved['id'],
            'good_id' => $first->id, 'row_key' => $saved['items'][0]['row_key']]);
    }

    public function test_editing_free_row_in_a_sent_document_is_blocked(): void
    {
        $input = $this->input([$this->item()]);
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()->json();
        DB::table('supplier_orders')->where('id', $saved['id'])->update(['status' => 'sent']);
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 1])->assertConflict();
        $this->assertSame('sent', SupplierOrder::findOrFail($saved['id'])->status);
        $this->assertDatabaseCount('goods', 0);
    }
}
