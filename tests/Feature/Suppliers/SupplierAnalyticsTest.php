<?php

namespace Tests\Feature\Suppliers;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Measure;
use App\Models\Purchase;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use App\Services\Suppliers\SupplierAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Currency $rub;

    private Currency $usd;

    private SupplierAnalytics $analytics;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        config(['app.timezone' => 'UTC']);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->unit = Unit::create(['name' => 'Поставщик']);
        $this->rub = Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $this->usd = Currency::forceCreate(['code' => 'USD', 'name' => 'Доллар']);
        $this->analytics = app(SupplierAnalytics::class);
    }

    public function test_order_money_is_exact_by_currency_frequency_uses_documents_and_monitoring_ignores_period(): void
    {
        $first = $this->order('2026-10-01 09:00:00', [
            $this->item('Мак', '999999998.123456', '999.9999'),
            $this->item('Мука', '0.500000', '0.0001'),
            $this->item('Соль', '1', null),
            $this->item('Рыба', '2', '3.5000', 'USD'),
        ]);
        $this->order('2026-10-11 09:00:00', [$this->item('Зерно', '0.500000', '0.0001'), $this->item('Ягоды', '3', '1', null)], 'closed');
        $this->order('2026-11-03 09:00:00', [$this->item('Мясо', '1', '500')], 'confirmed', ['delivery_date' => '2026-11-04']);
        $this->order('2026-10-15 09:00:00', [$this->item('Отмена', '1000', '1000')], 'cancelled');
        $this->order(null, [$this->item('Черновик', '500', '1000')], 'draft');
        $this->order(null, [$this->item('Проверить отправку', '500', '1000')], 'send_unknown');
        $first->update(['delivery_date' => null]);
        $summary = $this->analytics->summaries([$this->unit->id], $this->period())[$this->unit->id];

        $this->assertSame(2, $summary['orders']['count']);
        $this->assertSame('999999898123.4564', $summary['orders']['totals_by_currency']['RUB']);
        $this->assertSame('7.0000', $summary['orders']['totals_by_currency']['USD']);
        $this->assertSame('3.0000', $summary['orders']['totals_by_currency']['UNKNOWN']);
        $this->assertSame(1, $summary['orders']['unpriced_items_count']);
        $this->assertSame(6, $summary['orders']['distinct_goods_count']);
        $this->assertEquals(10, $summary['orders']['average_interval_days']);
        $this->assertEquals(1.94, $summary['orders']['per_30_days']);
        $this->assertSame('2026-11-03T12:00:00+03:00', $summary['orders']['last_at_all_time']);
        $this->assertSame(2, $summary['orders']['days_since_last']);
        $this->assertSame(['open_orders_count' => 2, 'overdue_delivery_count' => 1,
            'unknown_delivery_count' => 1, 'uncertain_send_count' => 1], $summary['monitoring']);
        $this->assertSame(0, $summary['purchases']['count']);
        Mail::assertNothingSent();
    }

    public function test_unique_entity_purchases_are_deduplicated_shared_are_separate_and_purchase_amount_is_ignored(): void
    {
        $other = Unit::create(['name' => 'Другой Unit']);
        $entity = Entity::forceCreate(['name' => 'Однозначный поставщик']);
        $this->link($entity, $this->unit);
        $this->link($entity, $this->unit); // Legacy duplicate pair must not multiply lines.
        $broccoli = $this->purchaseItem('Брокколи', '2', '12.5000', $this->rub);
        $this->purchase($entity, '2026-10-01', [
            $broccoli,
            $this->purchaseItem('Морковь', '3', '2.0000', $this->usd),
            $this->purchaseItem('Лук', '1', '8', null),
        ]);
        $this->purchase($entity, '2026-10-11', [$this->purchaseItem('Тыква', '2', '5', $this->rub)]);
        $this->purchase($entity, '2026-11-01', [$this->purchaseItem('Шпинат', '9', '10', $this->rub)]);
        $shared = Entity::forceCreate(['name' => 'Общий Entity']);
        $this->link($shared, $this->unit);
        $this->link($shared, $this->unit);
        $this->link($shared, $other);
        $this->purchase($shared, '2026-10-06', [$this->purchaseItem('Горох', '4', '100', $this->rub)]);
        $unrelated = Entity::forceCreate(['name' => 'Посторонний']);
        $this->purchase($unrelated, '2026-10-20', [$this->purchaseItem('Постороннее', '999', '999', $this->rub)]);
        $summaries = $this->analytics->summaries([$this->unit->id, $other->id], $this->period());
        $summary = $summaries[$this->unit->id];

        $this->assertSame(2, $summary['purchases']['count']);
        $this->assertSame(['RUB' => '35.0000', 'UNKNOWN' => '8.0000', 'USD' => '6.0000'], $summary['purchases']['totals_by_currency']);
        $this->assertEquals(10, $summary['purchases']['average_interval_days']);
        $this->assertSame('2026-11-01', $summary['purchases']['last_at_all_time']);
        $this->assertSame(4, $summary['purchases']['days_since_last']);
        $this->assertSame(4, $summary['purchases']['distinct_goods_count']);
        $this->assertSame('unique_current_entity_link', $summary['purchases']['attribution']);
        $this->assertSame(['count' => 1, 'totals_by_currency' => ['RUB' => '400.0000'], 'entity_ids' => [$shared->id]], $summary['shared_purchases']);
        $this->assertSame(0, $summaries[$other->id]['purchases']['count']);
        $this->assertSame(1, $summaries[$other->id]['shared_purchases']['count']);

        DB::table('goods')->where('id', $broccoli['good_id'])->update([
            'measure_id' => Measure::where('name', 'коробка')->value('id'), 'unit_weight_kg' => 25,
        ]);
        $detail = $this->analytics->show($this->unit, [...$this->period(), 'per_page' => 2]);
        $this->assertSame(3, $detail['purchases']['meta']['total']);
        $this->assertSame(2, $detail['purchases']['meta']['last_page']);
        $this->assertSame(2, $detail['monthly'][0]['purchases_count']);
        $this->assertSame('35.0000', $detail['monthly'][0]['purchase_totals_by_currency']['RUB']);
        $sharedRow = collect($detail['purchases']['data'])->firstWhere('shared', true);
        $this->assertSame([$this->unit->id, $other->id], $sharedRow['linked_unit_ids']);
        $this->assertSame(['RUB' => '400.0000'], $sharedRow['totals_by_currency']);
        $this->assertSame(4, $detail['goods']['meta']['total']);
        $historical = collect($detail['goods']['data'])->firstWhere('good_id', $broccoli['good_id']);
        $this->assertSame('кг', $historical['unit_label']);
        $this->assertSame('2.000000', $historical['quantity']);
        $this->assertNull($historical['kilograms_per_unit']);
    }

    public function test_moscow_period_bounds_months_defaults_and_strict_validation(): void
    {
        $this->order('2026-09-30 20:59:59', [$this->item('До', '1', '10')]);
        $this->order('2026-09-30 21:00:00', [$this->item('Начало', '1', '20')]);
        $this->order('2026-10-31 20:59:59', [$this->item('Окончание', '1', '30')]);
        $this->order('2026-10-31 21:00:00', [$this->item('После', '1', '40')]);
        $data = $this->analytics->show($this->unit, $this->period());
        $this->assertSame(2, $data['summary']['orders']['count']);
        $this->assertSame('50.0000', $data['summary']['orders']['totals_by_currency']['RUB']);
        $this->assertSame('2026-10', $data['monthly'][0]['month']);
        $this->assertSame(2, $data['monthly'][0]['orders_count']);
        $this->assertSame('50.0000', $data['monthly'][0]['order_totals_by_currency']['RUB']);
        $this->assertSame(['2026-09-30 21:00:00', '2026-10-31 21:00:00'], $this->analytics->datetimeBounds($this->period()));
        $default = $this->analytics->period([]);
        $this->assertSame(90, $default['days']);
        $this->assertSame('2026-11-05', $default['date_to']);
        $this->assertSame('Europe/Moscow', $default['timezone']);
        $zeroMonths = $this->analytics->show($this->unit, ['date_from' => '2026-07-15', 'date_to' => '2026-09-01']);
        $this->assertCount(3, $zeroMonths['monthly']);
        $this->assertSame(0, $zeroMonths['monthly'][1]['orders_count']);
        foreach ([['date_from' => '2026-10-32'], ['date_from' => '2026-11-01', 'date_to' => '2026-10-31'],
            ['date_from' => '2010-01-01', 'date_to' => '2026-01-01'], ['per_page' => 51], ['goods_source' => 'retail']] as $invalid) {
            $this->getJson($this->url($invalid))->assertUnprocessable();
        }
    }

    public function test_goods_are_paged_by_snapshot_units_weights_and_missing_identity_with_consistent_name_search(): void
    {
        $good = Good::create(['name' => 'Мука']);
        $box = Measure::where('name', 'коробка')->firstOrFail();
        $base = ['good_id' => $good->id, 'measure_id' => $box->id, 'unit_label' => 'коробка'];
        $this->order('2026-10-01 09:00:00', [array_replace($this->item('Старое название', '2', '10'), $base, ['kilograms_per_unit' => 10])]);
        $this->order('2026-10-02 09:00:00', [array_replace($this->item('Яркое новое название', '3', '10'), $base, ['kilograms_per_unit' => 10])]);
        $this->order('2026-10-03 09:00:00', [array_replace($this->item('Яркое новое название', '4', '20'), $base, ['kilograms_per_unit' => 25])]);
        $this->order('2026-10-04 09:00:00', [array_replace($this->item('Удалённый А', '1', null), ['good_id' => null]),
            array_replace($this->item('Удалённый Б', '2', null), ['good_id' => null])]);
        $data = $this->analytics->show($this->unit, [...$this->period(), 'goods_source' => 'orders', 'goods_search' => 'Яркое', 'per_page' => 1]);
        $this->assertSame(2, $data['goods']['meta']['total']);
        $row = $data['goods']['data'][0];
        $this->assertSame('10.000000', $row['kilograms_per_unit']);
        $this->assertSame('5.000000', $row['quantity']);
        $this->assertSame(2, $row['documents_count']);
        $this->assertSame(['RUB' => '50.0000'], $row['totals_by_currency']);
        $this->assertSame('коробка', $row['unit_label']);
        $next = $this->analytics->show($this->unit, [...$this->period(), 'goods_source' => 'orders', 'goods_search' => 'Яркое', 'per_page' => 1, 'goods_page' => 2]);
        $this->assertSame('25.000000', $next['goods']['data'][0]['kilograms_per_unit']);
        $this->assertSame('4.000000', $next['goods']['data'][0]['quantity']);
        $this->assertNotSame($row['key'], $next['goods']['data'][0]['key']);
        $deleted = $this->analytics->show($this->unit, [...$this->period(), 'goods_search' => 'Удалённый']);
        $this->assertSame(2, $deleted['goods']['meta']['total']);
        $this->assertNotSame($deleted['goods']['data'][0]['key'], $deleted['goods']['data'][1]['key']);
        $this->assertSame('1.000000', $deleted['goods']['data'][0]['quantity']);
        $this->assertSame('2.000000', $deleted['goods']['data'][1]['quantity']);
        $this->assertSame(1, $deleted['goods']['data'][0]['unpriced_items_count']);
    }

    public function test_batch_summaries_have_constant_query_count_and_reads_do_not_enroll_units(): void
    {
        $ids = [$this->unit->id];
        for ($index = 0; $index < 8; $index++) {
            $ids[] = Unit::create(['name' => 'Другой '.$index])->id;
        }
        DB::enableQueryLog();
        $this->analytics->summaries([$this->unit->id], $this->period());
        $single = count(DB::getQueryLog());
        DB::flushQueryLog();
        $all = $this->analytics->summaries($ids, $this->period());
        $batch = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($single, $batch);
        $this->assertCount(9, $all);
        $this->assertNull($all[$ids[1]]['orders']['average_interval_days']);
        $this->getJson($this->url($this->period()))->assertOk()->assertJsonPath('summary.orders.count', 0);
        $this->assertFalse($this->unit->fresh()->is_supplier);
        $this->assertDatabaseCount('supplier_profiles', 0);
        $this->assertDatabaseCount('supplier_orders', 0);
        $this->assertDatabaseCount('purchases', 0);
    }

    private function period(): array
    {
        return $this->analytics->period(['date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
    }

    private function url(array $query): string
    {
        return '/api/suppliers/'.$this->unit->id.'/analytics?'.http_build_query($query);
    }

    private function item(string $name, string $quantity, ?string $price, ?string $currency = 'RUB'): array
    {
        $good = Good::create(['name' => $name]);

        return ['good_id' => $good->id, 'name' => $name, ...$good->measurement(), 'quantity' => $quantity,
            'unit_price' => $price, 'currency_code' => $currency, 'source_order_item_ids' => []];
    }

    private function order(?string $date, array $items, string $status = 'sent', array $attributes = []): SupplierOrder
    {
        $order = SupplierOrder::create(['unit_id' => $this->unit->id, 'to' => [], 'cc' => [],
            'create_key' => (string) Str::uuid(), 'create_payload_hash' => str_repeat('a', 64)]);
        foreach ($items as $index => $item) {
            $order->items()->create(['position' => $index, ...$item]);
        }
        $order->update(['status' => $status, 'sent_at' => $date, ...$attributes]);

        return $order;
    }

    private function link(Entity $entity, Unit $unit): void
    {
        DB::table('entity_unit')->insert(['entity_id' => $entity->id, 'unit_id' => $unit->id]);
    }

    private function purchaseItem(string $name, string $quantity, string $price, ?Currency $currency): array
    {
        $good = Good::create(['name' => $name]);

        return ['good_id' => $good->id, 'measure_id' => $good->measure_id, 'quantity' => $quantity, 'price' => $price, 'currency_id' => $currency?->id];
    }

    private function purchase(Entity $entity, string $date, array $items): Purchase
    {
        $purchase = Purchase::create(['entity_id' => $entity->id, 'date' => $date, 'amount' => 99999999]);
        foreach ($items as $item) {
            DB::table('good_purchase')->insert(['purchase_id' => $purchase->id, ...$item]);
        }

        return $purchase;
    }
}
