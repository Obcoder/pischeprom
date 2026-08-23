<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\AggregateDemandSummary;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use Illuminate\Support\Facades\DB;

class GetAggregateDemandSummaryToolHandler implements AiToolHandlerInterface
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

        $base = DB::table('sales')
            ->join('good_sale', 'good_sale.sale_id', '=', 'sales.id')
            ->where('good_sale.good_id', $goodId)
            ->where('sales.date', '>=', $since);
        $sampleSize = (clone $base)->distinct()->count('sales.entity_id');
        $minimum = max(3, (int) config('ai-sales.tools.aggregate_minimum_cohort', 5));

        if ($sampleSize < $minimum) {
            throw new PolicyViolation('aggregate_privacy_threshold', 'Aggregate cohort is below the code-owned privacy threshold.');
        }

        $quantity = (float) (clone $base)->sum('good_sale.quantity');
        $regionCount = DB::table('sales')
            ->join('good_sale', 'good_sale.sale_id', '=', 'sales.id')
            ->join('city_entity', 'city_entity.entity_id', '=', 'sales.entity_id')
            ->join('cities', 'cities.id', '=', 'city_entity.city_id')
            ->where('good_sale.good_id', $goodId)
            ->where('sales.date', '>=', $since)
            ->distinct()
            ->count('cities.region_id');

        return new AiToolHandlerResult([
            new AggregateDemandSummary(
                $good->name,
                "last_{$days}_days",
                $this->band($quantity),
                $regionCount,
                $sampleSize,
            ),
        ], 'sales_aggregate', $goodId);
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
