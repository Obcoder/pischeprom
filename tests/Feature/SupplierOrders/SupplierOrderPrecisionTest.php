<?php

namespace Tests\Feature\SupplierOrders;

use App\Models\Currency;
use App\Models\Good;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use App\Services\SupplierOrders\SupplierOrderMailRenderer;
use App\Services\SupplierOrders\SupplierOrderWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOrderPrecisionTest extends TestCase
{
    use RefreshDatabase;

    private Unit $supplier;

    private Currency $rub;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->supplier = Unit::create(['name' => 'Поставщик']);
        $this->rub = Currency::forceCreate(['name' => 'Рубль', 'code' => 'RUB']);
    }

    public function test_maximum_quantity_precision_matches_api_draft_and_saved_mail_totals(): void
    {
        $payload = $this->payload([$this->item('Ягоды', 999999998.123456, 999.9999)]);
        $draft = $this->postJson('/api/supplier-orders/preview', $payload)->assertOk();
        $created = $this->postJson('/api/supplier-orders', $payload)->assertCreated()
            ->assertJsonPath('items.0.quantity', 999999998.123456)
            ->assertJsonPath('items.0.unit_price', 999.9999)
            ->assertJsonPath('items.0.line_total', 999999898123.4562)
            ->assertJsonPath('totals_by_currency.RUB', 999999898123.4562);
        $saved = $this->getJson('/api/supplier-orders/'.$created->json('id').'/preview')->assertOk();
        foreach ([$draft, $saved] as $preview) {
            foreach (['html', 'text'] as $format) {
                $this->assertStringContainsString('999 999 998,123456', $preview->json($format));
                $this->assertStringContainsString('999 999 898 123,4562 RUB', $preview->json($format));
            }
        }

        $largest = $this->payload([$this->item('Зерно', '999999999', '999.9999')]);
        $this->postJson('/api/supplier-orders', $largest)->assertCreated()
            ->assertJsonPath('items.0.line_total', 999999899000.0001);
        $largest['create_key'] = (string) Str::uuid();
        $largest['items'][0]['unit_price'] = '1000.0001';
        $this->postJson('/api/supplier-orders', $largest)->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.unit_price');
        $this->assertDatabaseCount('supplier_orders', 2);
        Mail::assertNothingSent();
    }

    public function test_line_rounding_is_half_up_and_currency_totals_sum_rounded_lines(): void
    {
        $usd = Currency::forceCreate(['name' => 'Доллар', 'code' => 'USD']);
        $payload = $this->payload([
            $this->item('Мука', '0.500000', '0.0001'),
            $this->item('Соль', '0.500000', '0.0001'),
            $this->item('Сахар', '2', '0.0025', $usd),
        ]);
        $created = $this->postJson('/api/supplier-orders', $payload)->assertCreated()
            ->assertJsonPath('items.0.line_total', 0.0001)
            ->assertJsonPath('items.1.line_total', 0.0001)
            ->assertJsonPath('totals_by_currency.RUB', 0.0002)
            ->assertJsonPath('totals_by_currency.USD', 0.005);
        $preview = $this->getJson('/api/supplier-orders/'.$created->json('id').'/preview')->assertOk();
        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('0,0002 RUB', $preview->json($format));
            $this->assertStringContainsString('0,005 USD', $preview->json($format));
        }
    }

    public function test_mail_snapshots_keep_decimal_strings_beyond_float_precision(): void
    {
        $payload = $this->payload([$this->item('Мясо', '0.000001', '999999999998.9999')]);
        $preview = $this->postJson('/api/supplier-orders/preview', $payload)->assertOk();
        $this->assertStringContainsString('999 999 999 998,9999 RUB', $preview->json('text'));
        $writer = app(SupplierOrderWriter::class);
        $normalized = $writer->normalize($payload);
        $this->assertSame('0.000001', $normalized['items'][0]['quantity']);
        $this->assertSame('999999999998.9999', $normalized['items'][0]['unit_price']);

        // MySQL DECIMAL returns exact strings. SQLite's numeric affinity in this
        // test database cannot store sixteen significant digits, so hydrate that
        // documented database snapshot directly before exercising mailData().
        $payload['items'][0]['unit_price'] = 1;
        $created = $this->postJson('/api/supplier-orders', $payload)->assertCreated();
        $order = SupplierOrder::with(['items', 'unit', 'warehouse'])->findOrFail($created->json('id'));
        $item = $order->items->first();
        $item->setRawAttributes([...$item->getAttributes(), 'unit_price' => '999999999998.9999'], true);
        $mailData = $writer->mailData($order);
        $this->assertSame('999999999998.9999', $mailData['items'][0]['unit_price']);
        $this->assertSame('0.000001', $mailData['items'][0]['quantity']);
        $rendered = app(SupplierOrderMailRenderer::class)->render($mailData);
        $this->assertStringContainsString('999 999 999 998,9999 RUB', $rendered['text']);
        $this->assertStringContainsString('1 000 000,00 RUB', $rendered['text']);
    }

    private function item(string $name, float|string $quantity, float|string $price, ?Currency $currency = null): array
    {
        $good = Good::create(['name' => $name]);

        return ['good_id' => $good->id, 'measurement' => $good->measurement(), 'quantity' => $quantity,
            'unit_price' => $price, 'currency_id' => ($currency ?? $this->rub)->id];
    }

    private function payload(array $items): array
    {
        return ['create_key' => (string) Str::uuid(), 'unit_id' => $this->supplier->id, 'items' => $items];
    }
}
