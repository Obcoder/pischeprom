<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\SupportedRegionSummary;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use Illuminate\Support\Facades\DB;

class GetSupportedRegionsToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $limit = min(50, max(1, (int) ($input['limit'] ?? 25)));
        $direction = ($input['sort'] ?? 'name_asc') === 'name_desc' ? 'desc' : 'asc';
        $regions = DB::table('regions')
            ->join('countries', 'countries.id', '=', 'regions.country_id')
            ->select(['regions.id', 'regions.name as region_name', 'countries.name as country_name'])
            ->orderBy('regions.name', $direction)
            ->orderBy('regions.id', $direction)
            ->limit($limit)
            ->get();

        return new AiToolHandlerResult(
            $regions->map(static fn (object $region): SupportedRegionSummary => new SupportedRegionSummary(
                $region->region_name,
                $region->country_name,
            ))->all(),
            'supported_regions',
        );
    }
}
