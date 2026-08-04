<?php

namespace App\Domain\AiPriceLists\Matching;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Normalization\TextNormalizer;
use App\Models\Good;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;
use App\Models\SupplierProductAlias;
use Illuminate\Support\Collection;

class PriceListItemMatcher
{
    public function __construct(private readonly TextNormalizer $text) {}

    public function matchImport(PriceListImport $import): array
    {
        $stats = ['exact' => 0, 'probable' => 0, 'unmatched' => 0, 'invalid' => 0];
        $processed = 0;
        $expected = max(1, $import->items()->where('decision_status', ItemDecisionStatus::Unreviewed->value)->count());

        $import->items()
            ->where('decision_status', ItemDecisionStatus::Unreviewed->value)
            ->orderBy('position')
            ->chunkById(250, function (Collection $items) use ($import, &$stats, &$processed, $expected): void {
                foreach ($items as $item) {
                    $this->matchItem($import, $item, $stats);
                }

                $processed += $items->count();
                $import->forceFill([
                    'stage_heartbeat_at' => now(),
                    'progress' => min(94, 80 + (int) floor($processed / $expected * 14)),
                ])->save();
            });

        $stats = [
            'exact' => $import->items()->where('match_class', MatchClass::Exact->value)->count(),
            'probable' => $import->items()->where('match_class', MatchClass::Probable->value)->count(),
            'unmatched' => $import->items()->whereIn('match_class', [MatchClass::None->value, MatchClass::Conflict->value])->count(),
            'invalid' => $import->items()->where('match_class', MatchClass::Invalid->value)->count(),
        ];
        $total = $import->items()->whereNotIn('match_class', [MatchClass::Ignored->value])->count();

        $import->forceFill([
            'items_total' => $total,
            'items_exact' => $stats['exact'],
            'items_probable' => $stats['probable'],
            'items_unmatched' => $stats['unmatched'],
            'items_invalid' => $stats['invalid'],
        ])->save();

        return $stats;
    }

    private function matchItem(PriceListImport $import, PriceListImportItem $item, array &$stats): void
    {
        $item->candidates()->delete();
        $alias = $this->confirmedAlias($import, $item);

        if ($alias) {
            $item->candidates()->create([
                'good_id' => $alias->good_id,
                'rank' => 1,
                'method' => 'supplier_alias',
                'score' => '1.0000',
                'score_components' => ['supplier_alias' => 1],
                'is_selected' => true,
            ]);
            $item->forceFill([
                'good_id' => $alias->good_id,
                'match_class' => MatchClass::Exact,
                'match_method' => 'supplier_alias',
                'match_score' => '1.0000',
                'review_reason' => 'Подтверждённый alias этого поставщика.',
            ])->save();
            $alias->increment('use_count');
            $alias->forceFill(['last_used_at' => now()])->save();
            $stats['exact']++;

            return;
        }

        $goods = $this->candidateGoods($item);
        $ranked = $goods
            ->map(fn (Good $good) => $this->score($item, $good))
            ->filter(fn (array $candidate) => $candidate['score'] >= 0.25)
            ->sortByDesc('score')
            ->take((int) config('ai-price-lists.matching.max_candidates'))
            ->values();

        foreach ($ranked as $index => $candidate) {
            $item->candidates()->create([
                'good_id' => $candidate['good']->id,
                'rank' => $index + 1,
                'method' => $candidate['method'],
                'score' => number_format($candidate['score'], 4, '.', ''),
                'score_components' => $candidate['components'],
            ]);
        }

        $top = $ranked->first();
        $second = $ranked->get(1);
        $probableThreshold = (float) config('ai-price-lists.matching.probable_threshold');

        if (! $top) {
            $item->forceFill([
                'good_id' => null,
                'match_class' => MatchClass::None,
                'match_method' => null,
                'match_score' => null,
                'review_reason' => 'Подходящие товары не найдены.',
            ])->save();
            $stats['unmatched']++;

            return;
        }

        $conflict = $second && abs($top['score'] - $second['score']) < 0.03;
        $matchClass = $conflict
            ? MatchClass::Conflict
            : ($top['score'] >= $probableThreshold ? MatchClass::Probable : MatchClass::None);

        $item->forceFill([
            'good_id' => null,
            'match_class' => $matchClass,
            'match_method' => $top['method'],
            'match_score' => number_format($top['score'], 4, '.', ''),
            'review_reason' => $conflict
                ? 'Несколько кандидатов имеют близкий score.'
                : 'Совпадение по названию требует подтверждения сотрудника.',
        ])->save();

        if ($matchClass === MatchClass::Probable) {
            $stats['probable']++;
        } else {
            $stats['unmatched']++;
        }
    }

    private function confirmedAlias(PriceListImport $import, PriceListImportItem $item): ?SupplierProductAlias
    {
        if (! $import->entity_id) {
            return null;
        }

        return SupplierProductAlias::query()
            ->where('entity_id', $import->entity_id)
            ->where(function ($query) use ($item): void {
                if ($item->supplier_sku) {
                    $query->where('supplier_sku', $item->supplier_sku)
                        ->orWhere('normalized_alias', $item->normalized_name);
                } else {
                    $query->where('normalized_alias', $item->normalized_name);
                }
            })
            ->first();
    }

    private function candidateGoods(PriceListImportItem $item): Collection
    {
        $tokens = array_values(array_filter(explode(' ', (string) $item->normalized_name), fn ($token) => mb_strlen($token) >= 3));

        return Good::query()
            ->select(['id', 'name', 'country_id'])
            ->when($tokens !== [], function ($query) use ($tokens): void {
                $query->where(function ($nested) use ($tokens): void {
                    foreach (array_slice($tokens, 0, 4) as $token) {
                        $nested->orWhere('name', 'like', "%{$token}%");
                    }
                });
            })
            ->limit(80)
            ->get();
    }

    private function score(PriceListImportItem $item, Good $good): array
    {
        $left = $item->normalized_name ?: '';
        $right = $this->text->search($good->name) ?: '';
        $leftTokens = array_unique(array_filter(explode(' ', $left)));
        $rightTokens = array_unique(array_filter(explode(' ', $right)));
        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique([...$leftTokens, ...$rightTokens]));
        $jaccard = $union > 0 ? $intersection / $union : 0;
        $exactName = $left !== '' && $left === $right;
        $prefix = $left !== '' && ($exactName || str_starts_with($left, $right) || str_starts_with($right, $left));
        $score = min(0.94, ($exactName ? 0.90 : 0) + ($prefix ? 0.08 : 0) + $jaccard * 0.72);

        return [
            'good' => $good,
            'score' => $score,
            'method' => $exactName ? 'normalized_name' : 'token_similarity',
            'components' => [
                'exact_name' => $exactName ? 1 : 0,
                'prefix' => $prefix ? 1 : 0,
                'token_jaccard' => round($jaccard, 4),
            ],
        ];
    }
}
