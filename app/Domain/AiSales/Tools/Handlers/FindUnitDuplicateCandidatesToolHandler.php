<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\UnitDuplicateCandidateSummary;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\EntityDuplicateCheckService;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use Illuminate\Support\Facades\DB;

class FindUnitDuplicateCandidatesToolHandler implements AiToolHandlerInterface
{
    public function __construct(private readonly EntityDuplicateCheckService $duplicates) {}

    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $name = DB::table('units')
            ->where('id', $context->unitId)
            ->select(['id', 'name'])
            ->value('name');

        if (! is_string($name) || trim($name) === '') {
            throw new PolicyViolation('tool_subject_not_found', 'The bound Unit is unavailable for duplicate review.');
        }

        $items = collect($this->duplicates->candidateIds(['name' => $name]))
            ->take(20)
            ->map(fn (int $id): UnitDuplicateCandidateSummary => new UnitDuplicateCandidateSummary(
                substr(hash_hmac('sha256', 'entity:'.$id, (string) config('app.key', 'stage07')), 0, 32),
                'exact_normalized_name',
            ))
            ->all();

        return new AiToolHandlerResult($items, 'entity_duplicate_candidates', $context->unitId);
    }
}
