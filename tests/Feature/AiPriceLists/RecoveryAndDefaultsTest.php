<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Jobs\AiPriceLists\NormalizePriceListRows;
use App\Models\PriceListEvent;
use App\Models\PriceListImportItem;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class RecoveryAndDefaultsTest extends AiPriceListTestCase
{
    public function test_scheduler_command_recovers_stale_stage_once_and_audits_dispatch(): void
    {
        Queue::fake();
        config()->set(['ai-price-lists.recovery.stale_after_minutes' => 10, 'ai-price-lists.recovery.max_recoveries' => 3]);
        $import = $this->import([
            'status' => PriceListStatus::Normalizing,
            'current_stage' => 'normalize',
            'stage_heartbeat_at' => now()->subHour(),
        ]);

        $this->artisan('price-lists:recover-stale')->assertSuccessful();

        Queue::assertPushed(NormalizePriceListRows::class, fn ($job) => $job->importId === $import->id);
        $this->assertDatabaseHas('price_list_events', ['price_list_import_id' => $import->id, 'event_type' => 'recovery_dispatched']);
        $this->assertTrue($import->fresh()->stage_heartbeat_at->greaterThan(now()->subMinute()));
    }

    public function test_recovery_stops_after_configured_attempts(): void
    {
        Queue::fake();
        config()->set(['ai-price-lists.recovery.stale_after_minutes' => 10, 'ai-price-lists.recovery.max_recoveries' => 3]);
        $import = $this->import([
            'status' => PriceListStatus::Normalizing,
            'current_stage' => 'normalize',
            'stage_heartbeat_at' => now()->subHour(),
        ]);
        foreach (range(1, 3) as $attempt) {
            PriceListEvent::query()->create([
                'price_list_import_id' => $import->id,
                'correlation_id' => (string) Str::uuid(),
                'event_type' => 'recovery_dispatched',
                'stage' => 'normalize',
                'metadata' => ['attempt' => $attempt],
                'created_at' => now()->subMinutes(40 - $attempt),
            ]);
        }

        $this->artisan('price-lists:recover-stale')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(PriceListStatus::Failed, $import->fresh()->status);
        $this->assertSame('recovery_exhausted', $import->fresh()->error_code);
        $this->assertFalse($import->fresh()->error_retryable);
    }

    public function test_bulk_defaults_preview_then_fills_only_missing_values(): void
    {
        $import = $this->import();
        $first = $this->item($import->id, 1, null, 'unknown', null);
        $second = $this->item($import->id, 2, 'EUR', 'included', '20.0000');
        $reviewer = $this->userWith(['ai_price_lists.review']);
        $url = "/api/ai/price-lists/{$import->uuid}/items/bulk-defaults";

        $this->actingAs($reviewer)->postJson($url, [
            'currency_code' => 'RUB', 'vat_mode' => 'excluded', 'vat_rate' => '10.00', 'preview' => true,
        ])->assertOk()->assertJsonPath('data.affected', 1)->assertJsonPath('data.preview', true);
        $this->assertNull($first->fresh()->currency_code);

        $this->actingAs($reviewer)->postJson($url, [
            'currency_code' => 'RUB', 'vat_mode' => 'excluded', 'vat_rate' => '10.00', 'preview' => false,
        ])->assertOk()->assertJsonPath('data.affected', 1)->assertJsonPath('data.preview', false);

        $this->assertSame('RUB', $first->fresh()->currency_code);
        $this->assertSame('excluded', $first->fresh()->vat_mode->value);
        $this->assertSame('10.0000', $first->fresh()->vat_rate);
        $this->assertSame('EUR', $second->fresh()->currency_code);
        $this->assertSame('included', $second->fresh()->vat_mode->value);
        $this->assertSame('20.0000', $second->fresh()->vat_rate);
        $this->assertDatabaseHas('price_list_events', ['price_list_import_id' => $import->id, 'event_type' => 'item_defaults_applied']);
    }

    private function item(int $importId, int $position, ?string $currency, string $vatMode, ?string $vatRate): PriceListImportItem
    {
        return PriceListImportItem::query()->create([
            'price_list_import_id' => $importId,
            'position' => $position,
            'raw_name' => 'Товар '.$position,
            'normalized_name' => 'товар '.$position,
            'price' => '100.000000',
            'currency_code' => $currency,
            'vat_mode' => $vatMode,
            'vat_rate' => $vatRate,
            'decision_status' => ItemDecisionStatus::Unreviewed,
            'match_class' => MatchClass::None,
            'row_fingerprint' => hash('sha256', 'defaults-'.$position),
        ]);
    }
}
