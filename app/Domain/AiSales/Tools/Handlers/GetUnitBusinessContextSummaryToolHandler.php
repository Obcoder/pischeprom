<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\UnitBusinessContextSummary;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use Illuminate\Support\Facades\DB;

class GetUnitBusinessContextSummaryToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $record = DB::table('unit_business_contexts')
            ->leftJoin('users as owners', 'owners.id', '=', 'unit_business_contexts.owner_user_id')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'unit_business_contexts.reviewer_user_id')
            ->leftJoin('goods', 'goods.id', '=', 'unit_business_contexts.primary_good_id')
            ->where('unit_business_contexts.id', $context->unitBusinessContextId)
            ->where('unit_business_contexts.unit_id', $context->unitId)
            ->select([
                'unit_business_contexts.id',
                'unit_business_contexts.lane',
                'unit_business_contexts.role_code',
                'unit_business_contexts.stage',
                'unit_business_contexts.status',
                'unit_business_contexts.last_activity_at',
                'owners.name as owner_name',
                'reviewers.name as reviewer_name',
                'goods.name as primary_good_name',
            ])
            ->first();

        if (! $record) {
            throw new PolicyViolation('tool_context_binding_stale', 'The bound Unit business context is unavailable.');
        }

        return new AiToolHandlerResult([
            new UnitBusinessContextSummary(
                'context:'.$record->id,
                $record->lane,
                $record->role_code,
                $record->stage,
                $record->status,
                $record->owner_name,
                $record->reviewer_name,
                $record->primary_good_name,
                $record->last_activity_at,
            ),
        ], 'unit_business_context', $context->unitBusinessContextId);
    }
}
