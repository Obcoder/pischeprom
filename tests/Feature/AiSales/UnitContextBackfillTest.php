<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Queries\UnitTransactionAggregateQuery;
use App\Models\Entity;
use App\Models\UnitBusinessContext;
use Illuminate\Support\Facades\DB;

class UnitContextBackfillTest extends UnitContextsTestCase
{
    public function test_dry_run_is_chunked_and_does_not_change_any_domain_data(): void
    {
        $unit = $this->unit(['is_customer' => true, 'is_supplier' => true]);
        $entity = Entity::query()->create(['name' => 'Dry-run Entity']);
        $unit->entities()->attach($entity->id);
        $this->sale($entity->id, '100.00');
        $this->purchase($entity->id, '75.00');
        $before = $this->domainCounts();

        $this->artisan('ai-sales:backfill-unit-contexts', ['--chunk' => 1])
            ->expectsOutputToContain('dry-run')
            ->expectsOutputToContain('no roles, contexts, Unit flags, Entity records, links or transactions were changed')
            ->assertSuccessful();

        $this->assertSame($before, $this->domainCounts());
        $this->assertTrue($unit->fresh()->is_customer);
        $this->assertTrue($unit->fresh()->is_supplier);
        $this->assertDatabaseCount('unit_business_contexts', 0);
        $this->assertDatabaseCount('market_role_unit', 0);
        $this->assertDatabaseCount('unit_dossier_audit_events', 0);
    }

    public function test_apply_is_idempotent_and_mixed_transaction_signals_create_both_contexts(): void
    {
        $unit = $this->unit();
        $entity = Entity::query()->create(['name' => 'Mixed Entity']);
        $unit->entities()->attach($entity->id);
        $this->sale($entity->id, '100.00');
        $this->purchase($entity->id, '50.00');
        $domainBefore = $this->transactionalCounts();

        $this->artisan('ai-sales:backfill-unit-contexts', ['--apply' => true, '--chunk' => 1])
            ->expectsOutputToContain('Apply completed idempotently in chunks')
            ->assertSuccessful();

        $this->assertDatabaseHas('unit_business_contexts', [
            'unit_id' => $unit->id,
            'lane' => 'sales',
            'role_code' => 'customer',
            'source' => 'entity_sales_history',
        ]);
        $this->assertDatabaseHas('unit_business_contexts', [
            'unit_id' => $unit->id,
            'lane' => 'procurement',
            'role_code' => 'supplier',
            'source' => 'entity_purchase_history',
        ]);
        $this->assertDatabaseCount('unit_business_contexts', 2);
        $this->assertDatabaseCount('market_role_unit', 2);
        $this->assertDatabaseCount('unit_dossier_audit_events', 2);
        $this->assertSame($domainBefore, $this->transactionalCounts());

        $this->artisan('ai-sales:backfill-unit-contexts', ['--apply' => true, '--chunk' => 1])
            ->assertSuccessful();

        $this->assertDatabaseCount('unit_business_contexts', 2);
        $this->assertDatabaseCount('market_role_unit', 2);
        $this->assertDatabaseCount('unit_dossier_audit_events', 2);
        $this->assertSame($domainBefore, $this->transactionalCounts());
        $this->assertFalse($unit->fresh()->is_customer);
        $this->assertFalse($unit->fresh()->is_supplier);
    }

    public function test_legacy_flags_without_transactions_create_reviewable_prospective_contexts(): void
    {
        $unit = $this->unit(['is_customer' => true, 'is_supplier' => true]);

        $this->artisan('ai-sales:backfill-unit-contexts', ['--apply' => true, '--chunk' => 1])
            ->expectsOutputToContain('prospective role requires review')
            ->assertSuccessful();

        $this->assertDatabaseHas('unit_business_contexts', [
            'unit_id' => $unit->id,
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
            'stage' => 'review_required',
            'confidence' => 50,
        ]);
        $this->assertDatabaseHas('unit_business_contexts', [
            'unit_id' => $unit->id,
            'lane' => 'procurement',
            'role_code' => 'prospective_supplier',
            'stage' => 'review_required',
            'confidence' => 50,
        ]);
    }

    public function test_duplicate_links_and_multiple_entities_do_not_duplicate_transaction_aggregates(): void
    {
        $actor = $this->manager();
        $unit = $this->unit();
        $first = Entity::query()->create(['name' => 'First Entity']);
        $second = Entity::query()->create(['name' => 'Second Entity']);
        $unit->entities()->attach([$first->id, $first->id, $second->id]);
        $this->sale($first->id, '10.00');
        $this->sale($second->id, '20.00');
        $this->purchase($first->id, '30.00');
        $this->purchase($second->id, '40.00');
        $sales = UnitBusinessContext::query()->create([
            'unit_id' => $unit->id,
            'lane' => 'sales',
            'role_code' => 'customer',
            'stage' => 'active',
            'status' => 'active',
            'source' => 'test',
        ]);
        $procurement = UnitBusinessContext::query()->create([
            'unit_id' => $unit->id,
            'lane' => 'procurement',
            'role_code' => 'supplier',
            'stage' => 'active',
            'status' => 'active',
            'source' => 'test',
        ]);
        $aggregates = app(UnitTransactionAggregateQuery::class);

        $this->assertSame(2, $aggregates->transactionCount($actor, $sales));
        $this->assertSame(2, $aggregates->transactionCount($actor, $procurement));
    }

    private function sale(int $entityId, string $total): void
    {
        DB::table('sales')->insert([
            'entity_id' => $entityId,
            'date' => '2026-08-01',
            'total' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function purchase(int $entityId, string $amount): void
    {
        DB::table('purchases')->insert([
            'entity_id' => $entityId,
            'date' => '2026-08-02',
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function transactionalCounts(): array
    {
        return [
            'units' => DB::table('units')->count(),
            'entities' => DB::table('entities')->count(),
            'links' => DB::table('entity_unit')->count(),
            'sales' => DB::table('sales')->count(),
            'purchases' => DB::table('purchases')->count(),
        ];
    }

    private function domainCounts(): array
    {
        return [
            ...$this->transactionalCounts(),
            'contexts' => DB::table('unit_business_contexts')->count(),
            'roles' => DB::table('market_role_unit')->count(),
            'audit' => DB::table('unit_dossier_audit_events')->count(),
        ];
    }
}
