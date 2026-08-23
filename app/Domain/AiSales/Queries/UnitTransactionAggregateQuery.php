<?php

namespace App\Domain\AiSales\Queries;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UnitTransactionAggregateQuery
{
    public function __construct(private readonly UnitContextAuthorizationService $authorization) {}

    public function transactionCount(User $actor, UnitBusinessContext $context): int
    {
        $this->authorization->authorizeLane($actor, $context->lane);
        $entityIds = DB::table('entity_unit')
            ->where('unit_id', $context->unit_id)
            ->select('entity_id')
            ->distinct();

        return match ($context->lane) {
            BusinessLane::Sales => Sale::query()->whereIn('entity_id', $entityIds)->distinct()->count('id'),
            BusinessLane::Procurement => Purchase::query()->whereIn('entity_id', $entityIds)->distinct()->count('id'),
            default => throw new InvalidArgumentException('Transaction aggregates exist only for sales and procurement contexts.'),
        };
    }
}
