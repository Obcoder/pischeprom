<?php

namespace Tests\Feature\SupplierOrders;

use App\Models\Good;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use App\Services\SupplierOrders\SupplierOrderDefaults;
use App\Services\SupplierOrders\SupplierOrderWriter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOrderDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Unit $supplier;

    private Good $good;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('yandex');
        Http::preventStrayRequests();
        Queue::fake();
        Mail::fake();
        $this->staff = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->actingAs($this->staff);
        $this->supplier = Unit::create(['name' => 'Поставщик для нумерации']);
        $this->good = Good::create(['name' => 'Манго', 'ava_thumb' => 'https://cdn.example.com/goods/mango.jpg']);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 22:10:00', 'UTC'));
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['create_key' => (string) Str::uuid(), 'unit_id' => $this->supplier->id,
            'auto_subject' => true, 'subject' => 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0001',
            'items' => [['good_id' => $this->good->id, 'quantity' => 2,
                'measurement' => $this->good->fresh()->measurement()]]], $overrides);
    }

    public function test_context_defaults_use_moscow_date_and_do_not_reserve_numbers(): void
    {
        $url = '/api/units/'.$this->supplier->id.'/supplier-order-context?tab=catalog';
        for ($i = 0; $i < 2; $i++) {
            $this->getJson($url)->assertOk()->assertJsonPath('defaults.mailbox', 'com@food-server.ru')
                ->assertJsonPath('defaults.subject', 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0001')
                ->assertJsonPath('defaults.subject_date', '2026-10-16')->assertJsonPath('defaults.supplier_sequence', 1)
                ->assertJsonPath('defaults.auto_subject', true)->assertJsonPath('defaults.body', SupplierOrderDefaults::BODY)
                ->assertJsonPath('data.0.avatar_url', 'https://cdn.example.com/goods/mango.jpg');
        }
        $this->assertDatabaseCount('supplier_orders', 0);
        $this->assertDatabaseCount('supplier_order_sequences', 0);
    }

    public function test_numbers_belong_to_supplier_and_stale_preliminary_subject_is_corrected_on_first_save(): void
    {
        $this->postJson('/api/supplier-orders', $this->input())->assertCreated()->assertJsonPath('supplier_sequence', 1)
            ->assertJsonPath('subject', 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0001')
            ->assertJsonPath('body', SupplierOrderDefaults::BODY)->assertJsonPath('auto_subject', true);
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $this->postJson('/api/supplier-orders', $this->input())->assertCreated()->assertJsonPath('supplier_sequence', 2)
            ->assertJsonPath('subject', 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0002');
        $other = Unit::create(['name' => 'Другой поставщик']);
        $this->postJson('/api/supplier-orders', $this->input(['unit_id' => $other->id]))->assertCreated()
            ->assertJsonPath('supplier_sequence', 1)->assertJsonPath('subject', 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0001');
        $this->assertDatabaseHas('supplier_order_sequences', ['unit_id' => $this->supplier->id, 'last_sequence' => 2]);
    }

    public function test_retry_and_later_edits_preserve_number_and_original_date_but_manual_subject_is_respected(): void
    {
        $input = $this->input();
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()->json();
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'UTC'));
        $this->postJson('/api/supplier-orders', [...$input, 'subject' => 'ПИЩЕПРОМ-СЕРВЕР: заказ от 18.10.2026 0002'])
            ->assertOk()->assertJsonPath('id', $saved['id'])->assertJsonPath('supplier_sequence', 1)
            ->assertJsonPath('subject', 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0001');
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 1, 'internal_note' => 'Обновление'])
            ->assertOk()->assertJsonPath('supplier_sequence', 1)->assertJsonPath('subject_date', '2026-10-16')
            ->assertJsonPath('subject', 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0001');
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'revision' => 2,
            'auto_subject' => false, 'subject' => 'Согласованный заказ на следующую неделю'])
            ->assertOk()->assertJsonPath('auto_subject', false)->assertJsonPath('supplier_sequence', 1)
            ->assertJsonPath('subject', 'Согласованный заказ на следующую неделю');
        $this->assertDatabaseCount('supplier_orders', 1);
        $this->assertDatabaseHas('supplier_order_sequences', ['unit_id' => $this->supplier->id, 'last_sequence' => 1]);
        Mail::assertNothingSent();
    }

    public function test_deleted_draft_number_is_not_reused_and_supplier_cannot_change(): void
    {
        $input = $this->input();
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()->json();
        $other = Unit::create(['name' => 'Другой поставщик']);
        $this->patchJson('/api/supplier-orders/'.$saved['id'], [...$input, 'unit_id' => $other->id, 'revision' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->assertDatabaseHas('supplier_orders', ['id' => $saved['id'], 'unit_id' => $this->supplier->id, 'revision' => 1]);
        $this->deleteJson('/api/supplier-orders/'.$saved['id'], ['revision' => 1])->assertNoContent();
        $this->postJson('/api/supplier-orders', $this->input())->assertCreated()->assertJsonPath('supplier_sequence', 2)
            ->assertJsonPath('subject', 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0002');
    }

    public function test_old_client_retries_match_legacy_fingerprint_without_allowing_changed_payloads(): void
    {
        foreach (['Заказ поставщику до обновления', ''] as $subject) {
            $input = $this->input(['subject' => $subject, 'body' => 'Ранее сохранённый текст']);
            unset($input['auto_subject']);
            $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()->json();
            // Exact fingerprint format deployed before automatic subject metadata.
            $legacyPayload = ['unit_id' => $this->supplier->id, 'warehouse_id' => null, 'delivery_date' => null,
                'subject' => $subject, 'body' => $input['body'], 'mailbox' => null, 'to' => [], 'cc' => [],
                'include_prices' => true, 'internal_note' => null, 'items' => [[
                    'good_id' => $this->good->id, 'quantity' => '2.000000',
                    'measurement' => ['measure_id' => $this->good->measure_id,
                        'kilograms_per_unit' => $input['items'][0]['measurement']['kilograms_per_unit']],
                    'unit_price' => null, 'currency_id' => null, 'quotation_id' => null, 'note' => null, 'source_order_item_ids' => [],
                ]]];
            DB::table('supplier_orders')->where('id', $saved['id'])->update([
                'create_payload_hash' => hash('sha256', json_encode($legacyPayload, JSON_THROW_ON_ERROR)),
                'subject' => $subject, 'auto_subject' => false,
            ]);
            $this->postJson('/api/supplier-orders', $input)->assertOk()->assertJsonPath('id', $saved['id'])
                ->assertJsonPath('subject', $subject)->assertJsonPath('auto_subject', false)->assertJsonPath('revision', 1);
            $this->postJson('/api/supplier-orders', [...$input, 'body' => 'Другой текст'])->assertConflict();
            $this->postJson('/api/supplier-orders', [...$input, 'subject' => 'Другая тема'])->assertConflict();
            $this->postJson('/api/supplier-orders', [...$input, 'auto_subject' => false])->assertConflict();
        }
        $this->assertDatabaseCount('supplier_orders', 2);
        $this->assertDatabaseHas('supplier_order_sequences', ['unit_id' => $this->supplier->id, 'last_sequence' => 2]);
    }

    public function test_validation_failure_does_not_consume_a_number_and_manual_subject_does_not_opt_in_to_template(): void
    {
        $this->postJson('/api/supplier-orders', $this->input(['items' => []]))->assertUnprocessable();
        $this->assertDatabaseCount('supplier_order_sequences', 0);
        $manual = $this->input(['auto_subject' => false, 'subject' => 'Заказ из согласования', 'body' => 'Наш текст']);
        $this->postJson('/api/supplier-orders', $manual)->assertCreated()->assertJsonPath('supplier_sequence', 1)
            ->assertJsonPath('subject', 'Заказ из согласования')->assertJsonPath('body', 'Наш текст')->assertJsonPath('auto_subject', false);
        $this->assertSame('ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 10001',
            app(SupplierOrderDefaults::class)->subject('2026-10-16', 10001));
    }

    public function test_avatars_are_derived_from_goods_and_ignore_client_supplied_urls(): void
    {
        $input = $this->input();
        $input['items'][0]['avatar_url'] = 'https://attacker.example.com/forged.jpg';
        $normalized = app(SupplierOrderWriter::class)->normalize($input);
        $this->assertSame('https://cdn.example.com/goods/mango.jpg', $normalized['items'][0]['avatar_url']);
        $saved = $this->postJson('/api/supplier-orders', $input)->assertCreated()
            ->assertJsonPath('items.0.avatar_url', 'https://cdn.example.com/goods/mango.jpg')->json();
        $this->good->update(['ava_thumb' => null]);
        $this->getJson('/api/supplier-orders/'.$saved['id'])->assertOk()->assertJsonPath('items.0.avatar_url', null);
    }

    public function test_migration_backfills_chronological_supplier_sequences_without_rewriting_documents(): void
    {
        $writer = app(SupplierOrderWriter::class);
        $newer = $writer->save(null, $this->input(['auto_subject' => false, 'subject' => 'Исходная тема 2', 'body' => 'Исходный текст']), $this->staff);
        $older = $writer->save(null, $this->input(['auto_subject' => false, 'subject' => 'Исходная тема 1']), $this->staff);
        DB::table('supplier_orders')->where('id', $newer->id)->update(['created_at' => '2026-10-15 22:00:00']);
        DB::table('supplier_orders')->where('id', $older->id)->update(['created_at' => '2026-10-13 12:00:00']);
        $other = Unit::create(['name' => 'Ещё один поставщик']);
        $separate = $writer->save(null, $this->input(['unit_id' => $other->id]), $this->staff);
        $migration = require database_path('migrations/2026_10_15_200000_add_supplier_order_sequences.php');
        $migration->down();
        $migration->up();
        $this->assertSame(1, $older->fresh()->supplier_sequence);
        $this->assertSame(2, $newer->fresh()->supplier_sequence);
        $this->assertSame('2026-10-16', $newer->fresh()->subject_date->toDateString());
        $this->assertSame('Исходная тема 2', $newer->fresh()->subject);
        $this->assertSame('Исходный текст', $newer->fresh()->body);
        $this->assertFalse($newer->fresh()->auto_subject);
        $this->assertSame(1, $separate->fresh()->supplier_sequence);
        $this->assertDatabaseHas('supplier_order_sequences', ['unit_id' => $this->supplier->id, 'last_sequence' => 2]);
        $this->assertSame(3, $writer->save(null, $this->input(), $this->staff)->supplier_sequence);
        $this->assertCount(4, SupplierOrder::all());
    }
}
