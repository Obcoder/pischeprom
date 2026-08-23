<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Good;
use App\Models\Measure;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleGoodAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_good_can_be_added_and_increases_existing_sale_total(): void
    {
        $entity = Entity::query()->create(['name' => 'Покупатель']);
        $good = Good::query()->create([
            'name' => 'Какао-порошок',
            'denominator' => 25,
        ]);
        $measure = Measure::query()->create(['name' => 'кг']);
        $sale = Sale::query()->create([
            'date' => '2026-08-24',
            'entity_id' => $entity->id,
            'total' => '100.00',
        ]);

        $response = $this->postJson("/api/sales/{$sale->id}/goods", [
            'good_id' => $good->id,
            'measure_id' => $measure->id,
            'quantity' => 3,
            'total' => 60,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.id', $sale->id)
            ->assertJsonPath('data.total', 160)
            ->assertJsonPath('data.goods.0.id', $good->id)
            ->assertJsonPath('data.goods.0.pivot.quantity', 3)
            ->assertJsonPath('data.goods.0.pivot.price', 20)
            ->assertJsonPath('data.goods.0.pivot.total', 60);

        $pivotId = $response->json('data.goods.0.pivot.id');

        $this->assertNotEmpty($pivotId);
        $this->assertDatabaseHas('good_sale', [
            'id' => $pivotId,
            'sale_id' => $sale->id,
            'good_id' => $good->id,
            'measure_id' => $measure->id,
            'quantity' => 3,
            'price' => 20,
            'total' => 60,
        ]);
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'total' => 160,
            'payment_status' => 'unpaid',
            'outstanding_amount' => 160,
        ]);
    }

    public function test_good_is_not_added_without_two_line_values(): void
    {
        $entity = Entity::query()->create(['name' => 'Покупатель']);
        $good = Good::query()->create(['name' => 'Сахар']);
        $measure = Measure::query()->create(['name' => 'кг']);
        $sale = Sale::query()->create([
            'date' => '2026-08-24',
            'entity_id' => $entity->id,
            'total' => '100.00',
        ]);

        $this->postJson("/api/sales/{$sale->id}/goods", [
            'good_id' => $good->id,
            'measure_id' => $measure->id,
            'quantity' => 3,
        ])->assertUnprocessable()->assertJsonValidationErrors('goods');

        $this->assertDatabaseCount('good_sale', 0);
        $this->assertSame('100.00', $sale->fresh()->total);
    }
}
