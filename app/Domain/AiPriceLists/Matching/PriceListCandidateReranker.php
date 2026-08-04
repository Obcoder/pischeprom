<?php

namespace App\Domain\AiPriceLists\Matching;

use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Domain\AiPriceLists\Services\AiUsageRecorder;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;
use Illuminate\Support\Facades\DB;
use Opis\JsonSchema\Validator;
use RuntimeException;
use Throwable;

class PriceListCandidateReranker
{
    public function __construct(
        private readonly StructuredTextModelProviderInterface $provider,
        private readonly CandidateSelectionValidator $selections,
        private readonly AiUsageRecorder $usage,
        private readonly PriceListAuditLogger $audit,
    ) {}

    /** @return array{attempted:int, reranked:int, failed_chunks:int} */
    public function rerank(PriceListImport $import): array
    {
        $stats = ['attempted' => 0, 'reranked' => 0, 'failed_chunks' => 0];

        if (! config('ai-price-lists.matching.ai_reranking_enabled') || ! $this->provider->configured()) {
            return $stats;
        }

        $schema = $this->schema();
        $instructions = (string) file_get_contents(resource_path('ai/prompts/price-list-candidate-rerank-v1.txt'));
        $chunkSize = max(1, min(50, (int) config('ai-price-lists.matching.ai_rerank_chunk_size', 20)));

        $import->items()
            ->whereIn('match_class', [MatchClass::Probable->value, MatchClass::Conflict->value])
            ->whereHas('candidates')
            ->with(['candidates.good:id,name'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($items) use ($import, $schema, $instructions, &$stats): void {
                $stats['attempted'] += $items->count();
                $data = $items->map(fn (PriceListImportItem $item) => [
                    'item_id' => $item->id,
                    'extracted' => [
                        'name' => $item->raw_name,
                        'manufacturer' => $item->manufacturer,
                        'brand' => $item->brand,
                        'country' => $item->country_of_origin,
                        'package' => $item->package_description,
                        'supplier_sku' => $item->supplier_sku,
                        'manufacturer_sku' => $item->manufacturer_sku,
                        'barcode' => $item->barcode,
                    ],
                    'candidates' => $item->candidates->map(fn ($candidate) => [
                        'good_id' => $candidate->good_id,
                        'name' => $candidate->good->name,
                        'deterministic_score' => $candidate->score,
                        'score_components' => $candidate->score_components,
                    ])->values()->all(),
                ])->values()->all();

                try {
                    $this->usage->guardBudget();
                    $response = $this->provider->generate(new StructuredModelRequest(
                        instructions: $instructions,
                        data: json_encode(['items' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        schema: $schema,
                        schemaName: 'price_list_candidate_rerank_v1',
                        promptVersion: 'price-list-candidate-rerank-v1',
                        schemaVersion: 'price-list-candidate-rerank-v1',
                        safetyIdentifier: hash('sha256', 'price-list-rerank:'.$import->uuid),
                    ));
                    $this->validateSchema($response->data, $schema);
                    $validated = $this->validateSelections($items, $response->data['selections']);
                } catch (ExternalAiException $exception) {
                    $this->usage->failure($import, 'yandex_ai_studio', 'candidate_reranking', $exception, (string) config('ai-price-lists.ai.model'));
                    $stats['failed_chunks']++;

                    return;
                } catch (Throwable) {
                    $exception = new ExternalAiException('AI-reranking не прошёл строгую проверку.', false, 'ai_rerank_invalid');
                    $this->usage->failure($import, 'yandex_ai_studio', 'candidate_reranking', $exception, (string) config('ai-price-lists.ai.model'));
                    $stats['failed_chunks']++;

                    return;
                }

                $this->usage->candidateRerank($import, $response);
                $import->forceFill(['model_id' => $response->model, 'stage_heartbeat_at' => now()])->save();

                try {
                    DB::transaction(function () use ($validated, &$stats): void {
                        foreach ($validated as $selection) {
                            if ($selection['good_id'] === null) {
                                continue;
                            }

                            /** @var PriceListImportItem $item */
                            $item = $selection['item'];
                            $candidate = $item->candidates->firstWhere('good_id', $selection['good_id']);
                            $applicationScore = min(0.94, (float) $candidate->score * 0.85 + $selection['confidence'] * 0.15);
                            $candidate->forceFill([
                                'score' => number_format($applicationScore, 4, '.', ''),
                                'method' => $candidate->method.'+ai_rerank',
                                'score_components' => [
                                    ...($candidate->score_components ?: []),
                                    'ai_rerank_confidence' => round($selection['confidence'], 4),
                                    'ai_rerank_reason' => mb_substr($selection['reason'], 0, 255),
                                ],
                            ])->save();

                            $ranked = $item->candidates()->reorder()->orderByDesc('score')->orderBy('id')->get();

                            foreach ($ranked as $rank => $rankedCandidate) {
                                $rankedCandidate->forceFill(['rank' => $rank + 1])->save();
                            }

                            $top = $ranked->first();
                            $item->forceFill([
                                'match_method' => 'deterministic+ai_rerank',
                                'match_score' => $top?->score,
                                'review_reason' => 'AI изменил порядок только среди найденных приложением кандидатов; решение сотрудника обязательно.',
                            ])->save();
                            $stats['reranked']++;
                        }
                    }, 3);
                } catch (Throwable) {
                    $stats['failed_chunks']++;
                }
            });

        $this->audit->record($import, 'candidate_reranking_completed', $stats, stage: 'match');

        return $stats;
    }

    private function schema(): array
    {
        $schema = json_decode(
            (string) file_get_contents(resource_path('ai/schemas/price-list-candidate-rerank-v1.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (! is_array($schema)) {
            throw new RuntimeException('Candidate reranking schema is unavailable.');
        }

        return $schema;
    }

    private function validateSchema(array $data, array $schema): void
    {
        $result = (new Validator)->validate(
            json_decode(json_encode($data, JSON_THROW_ON_ERROR)),
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR)),
        );

        if (! $result->isValid()) {
            throw new RuntimeException('Candidate reranking response does not match its schema.');
        }
    }

    /** @return list<array{item:PriceListImportItem, good_id:?int, confidence:float, reason:string}> */
    private function validateSelections($items, array $selections): array
    {
        $byId = $items->keyBy('id');
        $seen = [];
        $validated = [];

        foreach ($selections as $selection) {
            $itemId = (int) $selection['item_id'];
            /** @var PriceListImportItem|null $item */
            $item = $byId->get($itemId);

            if (! $item || isset($seen[$itemId])) {
                throw new RuntimeException('AI returned an absent or duplicate import item ID.');
            }

            $seen[$itemId] = true;
            $goodId = $selection['good_id'] === null ? null : (int) $selection['good_id'];

            if ($goodId !== null) {
                $this->selections->validateAiSelection($item, $goodId);
            }

            $validated[] = [
                'item' => $item,
                'good_id' => $goodId,
                'confidence' => (float) $selection['confidence'],
                'reason' => trim((string) $selection['reason']),
            ];
        }

        return $validated;
    }
}
