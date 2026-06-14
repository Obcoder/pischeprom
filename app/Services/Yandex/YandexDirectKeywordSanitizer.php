<?php

namespace App\Services\Yandex;

class YandexDirectKeywordSanitizer
{
    public function plain(string $phrase, int $maxWords = 7): string
    {
        $phrase = $this->base($phrase);
        $phrase = str_replace(['!', '+', '"'], ' ', $phrase);

        return $this->limitWords($phrase, $maxWords);
    }

    public function negative(string $phrase): string
    {
        $phrase = $this->plain($phrase, 4);

        return ltrim($phrase, '-');
    }

    public function exact(string $phrase, int $maxWords = 7): string
    {
        $words = preg_split('/\s+/u', $this->plain($phrase, $maxWords)) ?: [];

        return collect($words)
            ->map(function (string $word) {
                $word = trim($word);

                if ($word === '') {
                    return null;
                }

                // Direct's "!" operator is safe only for word-like terms, not sizes like 38/42.
                return preg_match('/^\p{L}[\p{L}-]*$/u', $word) ? '!' . $word : $word;
            })
            ->filter()
            ->implode(' ');
    }

    public function directPhrase(string $phrase, int $maxWords = 7): string
    {
        $phrase = $this->base($phrase);
        $words = preg_split('/\s+/u', $phrase) ?: [];

        return collect($words)
            ->map(fn (string $word) => $this->sanitizeOperatorWord($word))
            ->filter()
            ->take($maxWords)
            ->implode(' ');
    }

    private function base(string $phrase): string
    {
        $phrase = html_entity_decode(strip_tags($phrase), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $phrase = mb_strtolower($phrase, 'UTF-8');
        $phrase = str_replace(['ё', 'Ё'], ['е', 'Е'], $phrase);

        // Yandex Direct rejects punctuation like "/" in keyword text.
        $phrase = preg_replace('/[\/\\\\|,.;:()\[\]{}<>=*~@#$%^&?`№…_]+/u', ' ', $phrase) ?: '';
        $phrase = preg_replace('/[^\p{L}\p{N}\s"!+\-]+/u', ' ', $phrase) ?: '';
        $phrase = preg_replace('/\s+/u', ' ', $phrase) ?: '';

        return trim($phrase);
    }

    private function sanitizeOperatorWord(string $word): ?string
    {
        $word = trim($word);

        if ($word === '') {
            return null;
        }

        $operator = '';
        if (str_starts_with($word, '!') || str_starts_with($word, '+')) {
            $operator = $word[0];
            $word = ltrim($word, '!+');
        }

        $word = str_replace(['!', '+', '"'], '', $word);
        $word = trim($word);

        if ($word === '' || mb_strlen($word, 'UTF-8') > 35) {
            return null;
        }

        if ($operator !== '' && !preg_match('/^\p{L}[\p{L}-]*$/u', $word)) {
            $operator = '';
        }

        return $operator . $word;
    }

    private function limitWords(string $phrase, int $maxWords): string
    {
        $words = preg_split('/\s+/u', $phrase) ?: [];

        return collect($words)
            ->map(fn (string $word) => trim($word))
            ->filter(fn (string $word) => $word !== '' && mb_strlen($word, 'UTF-8') <= 35)
            ->take($maxWords)
            ->implode(' ');
    }
}
