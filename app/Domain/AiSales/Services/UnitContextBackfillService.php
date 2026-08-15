<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitContextStage;
use App\Domain\AiSales\Enums\UnitContextStatus;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Models\MarketRole;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitMarketRole;
use Illuminate\Support\Facades\DB;

class UnitContextBackfillService
{
    public function __construct(private readonly UnitDossierAuditLogger $audit) {}

    public function run(bool $apply, int $chunkSize): array
    {
        $report = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'units_scanned' => 0,
            'context_candidates' => 0,
            'would_create' => 0,
            'created' => 0,
            'already_present' => 0,
            'review_required' => 0,
            'review' => [],
        ];

        Unit::query()
            ->without(['fields', 'labels', 'telephones', 'uris'])
            ->select(['id', 'name', 'is_customer', 'is_supplier', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($units) use ($apply, &$report): void {
                foreach ($units as $unit) {
                    $report['units_scanned']++;
                    $signals = $this->signals($unit);
                    $candidates = $this->candidates($signals);
                    $report['context_candidates'] += count($candidates);

                    foreach ($this->reviewMessages($unit, $signals) as $message) {
                        $report['review_required']++;
                        if (count($report['review']) < 200) {
                            $report['review'][] = $message;
                        }
                    }

                    foreach ($candidates as $candidate) {
                        $exists = UnitBusinessContext::query()
                            ->where('unit_id', $unit->id)
                            ->where('lane', $candidate['lane']->value)
                            ->where('role_code', $candidate['role']->value)
                            ->exists();

                        if ($exists) {
                            $report['already_present']++;

                            continue;
                        }

                        if (! $apply) {
                            $report['would_create']++;

                            continue;
                        }

                        if ($this->createCandidate($unit, $candidate)) {
                            $report['created']++;
                        } else {
                            $report['review_required']++;
                            if (count($report['review']) < 200) {
                                $report['review'][] = "Unit #{$unit->id}: archived role assignment requires human review.";
                            }
                        }
                    }
                }
            }, 'id');

        return $report;
    }

    private function signals(Unit $unit): array
    {
        $hasSales = DB::table('entity_unit')
            ->join('sales', 'sales.entity_id', '=', 'entity_unit.entity_id')
            ->where('entity_unit.unit_id', $unit->id)
            ->exists();
        $hasPurchases = DB::table('entity_unit')
            ->join('purchases', 'purchases.entity_id', '=', 'entity_unit.entity_id')
            ->where('entity_unit.unit_id', $unit->id)
            ->exists();
        $classifications = $unit->classifications()
            ->select(['entity_classifications.id', 'entity_classifications.name'])
            ->limit(20)
            ->pluck('entity_classifications.name')
            ->all();

        return [
            'has_sales' => $hasSales,
            'has_purchases' => $hasPurchases,
            'customer_flag' => (bool) $unit->is_customer,
            'supplier_flag' => (bool) $unit->is_supplier,
            'classifications' => $classifications,
        ];
    }

    private function candidates(array $signals): array
    {
        $candidates = [];

        if ($signals['has_sales']) {
            $candidates[] = $this->candidate(BusinessLane::Sales, UnitRoleCode::Customer, UnitContextStage::Active, 'entity_sales_history');
        } elseif ($signals['customer_flag']) {
            $candidates[] = $this->candidate(BusinessLane::Sales, UnitRoleCode::ProspectiveCustomer, UnitContextStage::ReviewRequired, 'legacy_unit_flag');
        }

        if ($signals['has_purchases']) {
            $candidates[] = $this->candidate(BusinessLane::Procurement, UnitRoleCode::Supplier, UnitContextStage::Active, 'entity_purchase_history');
        } elseif ($signals['supplier_flag']) {
            $candidates[] = $this->candidate(BusinessLane::Procurement, UnitRoleCode::ProspectiveSupplier, UnitContextStage::ReviewRequired, 'legacy_unit_flag');
        }

        return $candidates;
    }

    private function candidate(
        BusinessLane $lane,
        UnitRoleCode $role,
        UnitContextStage $stage,
        string $source,
    ): array {
        return compact('lane', 'role', 'stage', 'source');
    }

    private function reviewMessages(Unit $unit, array $signals): array
    {
        $messages = [];

        if ($signals['has_sales'] && ! $signals['customer_flag']) {
            $messages[] = "Unit #{$unit->id}: sales history exists while is_customer=false.";
        }
        if ($signals['has_purchases'] && ! $signals['supplier_flag']) {
            $messages[] = "Unit #{$unit->id}: purchase history exists while is_supplier=false.";
        }
        if ($signals['customer_flag'] && ! $signals['has_sales']) {
            $messages[] = "Unit #{$unit->id}: customer flag has no linked sales; prospective role requires review.";
        }
        if ($signals['supplier_flag'] && ! $signals['has_purchases']) {
            $messages[] = "Unit #{$unit->id}: supplier flag has no linked purchases; prospective role requires review.";
        }
        if (! $signals['has_sales'] && ! $signals['has_purchases'] && ! $signals['customer_flag'] && ! $signals['supplier_flag'] && $signals['classifications'] !== []) {
            $messages[] = "Unit #{$unit->id}: classifications [".implode(', ', $signals['classifications']).'] are supporting signals only; no role inferred.';
        }

        return $messages;
    }

    private function createCandidate(Unit $unit, array $candidate): bool
    {
        return DB::transaction(function () use ($unit, $candidate): bool {
            $role = MarketRole::query()->where('code', $candidate['role']->value)->firstOrFail();
            $assignment = UnitMarketRole::query()->firstOrNew([
                'unit_id' => $unit->id,
                'market_role_id' => $role->id,
            ]);

            if ($assignment->exists && $assignment->archived_at !== null) {
                return false;
            }

            if (! $assignment->exists) {
                $assignment->fill([
                    'source' => 'stage03_backfill',
                    'assigned_by' => null,
                    'removed_by' => null,
                    'archived_at' => null,
                ])->save();
            }

            $context = UnitBusinessContext::query()->firstOrCreate(
                [
                    'unit_id' => $unit->id,
                    'lane' => $candidate['lane']->value,
                    'role_code' => $candidate['role']->value,
                ],
                [
                    'stage' => $candidate['stage']->value,
                    'status' => UnitContextStatus::Active->value,
                    'confidence' => $candidate['source'] === 'legacy_unit_flag' ? 50 : 100,
                    'source' => $candidate['source'],
                    'first_activity_at' => $unit->created_at,
                    'last_activity_at' => $unit->updated_at,
                    'created_by' => null,
                    'updated_by' => null,
                ],
            );

            if (! $context->wasRecentlyCreated) {
                return true;
            }

            $this->audit->record(
                $unit,
                'unit.context.backfilled',
                'Business context создан production-safe backfill-командой.',
                context: $context,
                subjectType: 'unit_business_context',
                subjectId: $context->id,
                metadata: [
                    'lane' => $candidate['lane']->value,
                    'role_code' => $candidate['role']->value,
                    'source' => $candidate['source'],
                ],
            );

            return true;
        }, 3);
    }
}
