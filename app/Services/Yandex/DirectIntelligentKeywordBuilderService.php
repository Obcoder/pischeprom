<?php

namespace App\Services\Yandex;

use App\Models\Good;
use App\Models\GoodSeo;

class DirectIntelligentKeywordBuilderService
{
    public function build(Good $good): array
    {
        $good->loadMissing(['seo', 'products.category']);
        $seo = $good->seo;

        $base = collect()
            ->merge($this->phrases($seo?->search_queries))
            ->merge($this->phrases($seo?->semantic_core))
            ->merge($this->phrases($seo?->keywords))
            ->merge($this->categorySignals($good))
            ->map(fn (string $phrase) => $this->normalize($phrase))
            ->filter(fn (string $phrase) => $phrase !== '')
            ->unique()
            ->values();

        $positive = $base
            ->take(120)
            ->flatMap(fn (string $phrase) => $this->variants($phrase))
            ->unique('phrase')
            ->values()
            ->all();

        return [
            'positive' => $positive,
            'negative' => $this->negative($good),
            'sources' => [
                'search_queries' => $this->phrases($seo?->search_queries),
                'semantic_core' => $this->phrases($seo?->semantic_core),
                'keywords' => $this->phrases($seo?->keywords),
                'category_signals' => $this->categorySignals($good),
            ],
        ];
    }

    public function hasSemanticCore(?GoodSeo $seo): bool
    {
        return filled($this->phrases($seo?->semantic_core));
    }

    public function hasKeywords(?GoodSeo $seo): bool
    {
        return filled($this->phrases($seo?->search_queries)) || filled($this->phrases($seo?->keywords));
    }

    private function variants(string $phrase): array
    {
        $phrase = $this->keywordCandidate($phrase);
        if ($phrase === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $phrase) ?: [];
        $withoutTinyWords = collect($words)
            ->filter(fn (string $word) => mb_strlen($word) > 1)
            ->values()
            ->all();

        $variants = [
            ['phrase' => $phrase, 'match' => 'phrase'],
        ];

        if (count($withoutTinyWords) > 1 && count($withoutTinyWords) <= 7) {
            $variants[] = [
                'phrase' => implode(' ', array_map(fn (string $word) => '!' . $word, $withoutTinyWords)),
                'match' => 'exact',
            ];
        }

        if (count($withoutTinyWords) >= 2) {
            $variants[] = [
                'phrase' => implode(' ', array_slice($withoutTinyWords, 0, 7)),
                'match' => 'broad',
            ];
        }

        return $variants;
    }

    private function negative(Good $good): array
    {
        return collect(config('yandex.default_minus_keywords', []))
            ->map(fn ($phrase) => $this->normalize((string) $phrase))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function categorySignals(Good $good): array
    {
        return $good->products
            ->pluck('category.name')
            ->filter()
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();
    }

    private function phrases(mixed $value): array
    {
        if ($value instanceof GoodSeo) {
            return collect()
                ->merge($this->phrases($value->search_queries))
                ->merge($this->phrases($value->keywords))
                ->merge($this->phrases($value->semantic_core))
                ->values()
                ->all();
        }

        if (is_string($value)) {
            return preg_split('/[\r\n,;]+/u', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(function ($item) {
                if (is_array($item)) {
                    return $this->phrases(implode("\n", $item));
                }

                return $this->phrases((string) $item);
            })
            ->all();
    }

    private function normalize(string $phrase): string
    {
        $phrase = mb_strtolower(strip_tags($phrase));
        $phrase = preg_replace('/\s+/u', ' ', $phrase) ?: '';

        return trim($phrase);
    }

    private function keywordCandidate(string $phrase): string
    {
        $words = preg_split('/\s+/u', $phrase) ?: [];

        return collect($words)
            ->map(fn (string $word) => trim($word))
            ->filter(fn (string $word) => $word !== '' && mb_strlen(ltrim($word, '-!+')) <= 35)
            ->take(7)
            ->implode(' ');
    }
}
