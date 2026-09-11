<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Measure;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SaleStockIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Entity $entity;

    private Good $good;

    private Measure $measure;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $user = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($user);

        $this->entity = Entity::query()->create(['name' => 'Покупатель']);
        $this->good = Good::query()->create(['name' => 'Сахар']);
        $this->measure = Measure::query()->create(['name' => 'кг']);
        GoodStockMovement::query()->create([
            'warehouse_id' => Warehouse::query()->where('code', Warehouse::GOODS_CODE)->sole()->id,
            'good_id' => $this->good->id,
            'measure_id' => $this->measure->id,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => 10,
            'unit_price' => 20,
            'moved_at' => '2026-09-10',
        ]);
    }

    public function test_retrying_sale_creation_returns_the_original_sale_and_deducts_once(): void
    {
        $payload = $this->salePayload();
        $saleId = $this->postJson('/api/sales', $payload)->assertCreated()->json('data.id');

        $this->postJson('/api/sales', $payload)->assertCreated()->assertJsonPath('data.id', $saleId);
        $payload['request_id'] = strtoupper($payload['request_id']);
        $this->postJson('/api/sales', $payload)->assertCreated()->assertJsonPath('data.id', $saleId);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('good_sale', 1);
        $this->assertDatabaseCount('sale_stock_requests', 1);
        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $saleId)->count());
        $this->assertEquals(7, $this->balance());
    }

    public function test_retrying_an_appended_line_deducts_and_increases_sale_total_once(): void
    {
        $sale = $this->emptySale();
        $payload = ['request_id' => (string) Str::uuid(), ...$this->line()];

        $this->postJson("/api/sales/{$sale->id}/goods", $payload)->assertCreated()->assertJsonPath('data.total', 400);
        $this->postJson("/api/sales/{$sale->id}/goods", $payload)->assertCreated()->assertJsonPath('data.total', 400);

        $this->assertDatabaseCount('good_sale', 1);
        $this->assertSame('400.00', $sale->fresh()->total);
        $this->assertSame('400.00', $sale->fresh()->outstanding_amount);
        $this->assertEquals(7, $this->balance());
    }

    public function test_legacy_endpoints_share_idempotency_with_the_modern_attachment_endpoint(): void
    {
        $sale = $this->emptySale();
        $payload = ['request_id' => (string) Str::uuid(), 'sale_id' => $sale->id, ...$this->line()];

        $this->postJson('/api/goodsales', $payload)->assertCreated();
        $this->postJson('/web/goodsale/store', $payload)->assertCreated();
        $this->postJson("/api/sales/{$sale->id}/goods", $payload)->assertCreated();

        $this->assertDatabaseCount('good_sale', 1);
        $this->assertDatabaseCount('sale_stock_requests', 1);
        $this->assertSame('400.00', $sale->fresh()->total);
        $this->assertEquals(7, $this->balance());
    }

    public function test_reusing_a_request_with_changed_payload_or_a_different_action_is_rejected(): void
    {
        $payload = $this->salePayload();
        $saleId = $this->postJson('/api/sales', $payload)->assertCreated()->json('data.id');
        $changed = $payload;
        $changed['goods'][0]['quantity'] = 4;

        $this->postJson('/api/sales', $changed)->assertConflict();
        $this->postJson("/api/sales/{$saleId}/goods", [
            'request_id' => $payload['request_id'],
            ...$this->line(),
        ])->assertConflict();

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('good_sale', 1);
        $this->assertEquals(7, $this->balance());
    }

    public function test_an_employee_cannot_reuse_another_employees_request(): void
    {
        $payload = $this->salePayload();
        $this->postJson('/api/sales', $payload)->assertCreated();

        $other = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $other->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($other)->postJson('/api/sales', $payload)->assertConflict();

        $this->assertDatabaseCount('sales', 1);
        $this->assertEquals(7, $this->balance());
    }

    public function test_failed_stock_validation_rolls_back_the_request_so_it_can_be_corrected_and_retried(): void
    {
        $payload = $this->salePayload();
        $payload['goods'][0]['quantity'] = 11;

        $this->postJson('/api/sales', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('sale_stock_requests', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_sale', 0);
        $this->assertEquals(10, $this->balance());

        $payload['goods'][0]['quantity'] = 3;
        $this->postJson('/api/sales', $payload)->assertCreated();
        $this->assertDatabaseCount('sale_stock_requests', 1);
        $this->assertEquals(7, $this->balance());
    }

    public function test_line_total_mismatch_is_rejected_and_a_one_kopeck_difference_is_canonicalized(): void
    {
        $payload = $this->salePayload();
        $payload['goods'][0]['total'] = 300.02;

        $this->postJson('/api/sales', $payload)->assertUnprocessable()->assertJsonValidationErrors('goods');
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_stock_requests', 0);

        $payload['goods'][0]['total'] = 300.01;
        $this->postJson('/api/sales', $payload)->assertCreated()
            ->assertJsonPath('data.total', 300)
            ->assertJsonPath('data.goods.0.pivot.total', 300);
    }

    public function test_non_uuid_request_identifiers_are_rejected_before_writing_stock(): void
    {
        $payload = $this->salePayload();
        $payload['request_id'] = 'reused-fixed-string';

        $this->postJson('/api/sales', $payload)->assertUnprocessable()->assertJsonValidationErrors('request_id');

        $this->assertDatabaseCount('sale_stock_requests', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertEquals(10, $this->balance());
    }

    private function salePayload(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'date' => '2026-09-11',
            'entity_id' => $this->entity->id,
            'goods' => [$this->line()],
        ];
    }

    private function line(): array
    {
        return [
            'good_id' => $this->good->id,
            'measure_id' => $this->measure->id,
            'quantity' => 3,
            'price' => 100,
        ];
    }

    private function emptySale(): Sale
    {
        return Sale::query()->create([
            'date' => '2026-09-11',
            'entity_id' => $this->entity->id,
            'total' => 100,
        ]);
    }

    private function balance(): float
    {
        return (float) DB::table('good_stock_movements')->where('good_id', $this->good->id)->sum('quantity_delta');
    }
}
