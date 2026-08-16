<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\CustomerOfferSummary;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use Illuminate\Support\Facades\DB;

class GetCustomerOfferSummaryToolHandler implements AiToolHandlerInterface
{
    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $quotation = DB::table('quotations')
            ->join('goods', 'goods.id', '=', 'quotations.good_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'quotations.currency_id')
            ->leftJoin('measures', 'measures.id', '=', 'quotations.measure_id')
            ->where('quotations.unit_id', $context->unitId)
            ->where('quotations.good_id', (int) $input['good_id'])
            ->where('goods.is_published', true)
            ->select([
                'quotations.id',
                'quotations.price',
                'goods.name as good_name',
                'currencies.name as currency_name',
                'measures.name as measure_name',
            ])
            ->orderByDesc('quotations.id')
            ->limit(1)
            ->first();

        if (! $quotation) {
            throw new PolicyViolation('tool_subject_not_found', 'No approved bounded offer summary is available.');
        }

        return new AiToolHandlerResult([
            new CustomerOfferSummary(
                $quotation->good_name,
                number_format((float) $quotation->price, 2, '.', ''),
                $quotation->currency_name ?? 'RUB',
                $quotation->measure_name,
            ),
        ], 'customer_offer_summary', $quotation->id);
    }
}
