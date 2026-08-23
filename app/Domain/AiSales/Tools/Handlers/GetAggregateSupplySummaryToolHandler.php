<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\AggregateSupplySummary;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use Illuminate\Support\Facades\DB;

class GetAggregateSupplySummaryToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $goodId = (int) $input['good_id'];
        $days = (int) ($input['days'] ?? 90);
        $since = today()->subDays($days - 1)->toDateString();
        $good = DB::table('goods')
            ->where('id', $goodId)
            ->where('is_published', true)
            ->select(['id', 'name'])
            ->first();

        if (! $good) {
            throw new PolicyViolation('tool_subject_not_found', 'The aggregate catalog subject is unavailable.');
        }

        $base = DB::table('purchases')
            ->join('good_purchase', 'good_purchase.purchase_id', '=', 'purchases.id')
            ->where('good_purchase.good_id', $goodId)
            ->where('purchases.date', '>=', $since);
        $supplierCount = (clone $base)->distinct()->count('purchases.entity_id');
        $minimum = max(3, (int) config('ai-sales.tools.aggregate_minimum_cohort', 5));

        if ($supplierCount < $minimum) {
            throw new PolicyViolation('aggregate_privacy_threshold', 'Aggregate cohort is below the code-owned privacy threshold.');
        }

        $quantity = (float) (clone $base)->sum('good_purchase.quantity');

        return new AiToolHandlerResult([
            new AggregateSupplySummary(
                $good->name,
                'region_withheld',
                $this->band($quantity),
                $supplierCount,
                "last_{$days}_days",
            ),
        ], 'purchases_aggregate', $goodId);
    }

    private function band(float $quantity): string
    {
        return match (true) {
            $quantity < 100 => 'under_100',
            $quantity < 1_000 => '100_to_999',
            $quantity < 10_000 => '1000_to_9999',
            default => '10000_or_more',
        };
    }
}
