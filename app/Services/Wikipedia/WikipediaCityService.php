<?php

namespace App\Services\Wikipedia;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class WikipediaCityService
{
    public function enrichCityData(array $data): array
    {
        if (! array_key_exists('wiki', $data)) {
            return $data;
        }

        if (blank($data['wiki'])) {
            return $this->clearWikiMetadata($data);
        }

        $data = $this->clearWikiMetadata($data);

        $summary = $this->fetchSummaryFromUrl($data['wiki']);

        if (! $summary) {
            return $data;
        }

        return array_merge($data, $summary);
    }

    public function fetchSummaryFromUrl(string $wikiUrl): ?array
    {
        $page = $this->extractPageFromUrl($wikiUrl);

        if (! $page) {
            return null;
        }

        return $this->fetchSummary(
            lang: $page['lang'],
            title: $page['title'],
        );
    }

    private function fetchSummary(string $lang, string $title): ?array
    {
        $encodedTitle = rawurlencode($title);

        $url = "https://{$lang}.wikipedia.org/api/rest_v1/page/summary/{$encodedTitle}";

        try {
            $client = HttpClient::create();

            $response = $client->request('GET', $url, [
                'timeout' => 8,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => config('services.wikipedia.user_agent'),
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::warning('Wikipedia summary request failed', [
                    'url' => $url,
                    'status' => $response->getStatusCode(),
                ]);

                return null;
            }

            $payload = $response->toArray(false);

            return [
                'wiki_title' => $payload['title'] ?? $title,
                'wiki_summary' => $payload['extract'] ?? null,
                'wiki_thumbnail' => $payload['thumbnail']['source']
                    ?? $payload['originalimage']['source']
                        ?? null,
                'wiki_page_id' => $payload['pageid'] ?? null,
                'wiki_lang' => $lang,
            ];
        } catch (ExceptionInterface|\Throwable $exception) {
            Log::warning('Wikipedia summary request exception', [
                'message' => $exception->getMessage(),
                'lang' => $lang,
                'title' => $title,
            ]);

            return null;
        }
    }

    private function extractPageFromUrl(string $wikiUrl): ?array
    {
        $wikiUrl = trim($wikiUrl);

        if ($wikiUrl === '') {
            return null;
        }

        $parsed = parse_url($wikiUrl);

        $host = $parsed['host'] ?? null;
        $path = $parsed['path'] ?? null;

        if (! $host || ! $path) {
            return null;
        }

        if (! preg_match('/^([a-z-]+)\.wikipedia\.org$/i', $host, $hostMatches)) {
            return null;
        }

        $lang = strtolower($hostMatches[1]);

        if (! str_starts_with($path, '/wiki/')) {
            return null;
        }

        $title = substr($path, strlen('/wiki/'));

        $title = rawurldecode($title);
        $title = str_replace('_', ' ', $title);
        $title = trim($title);

        if ($title === '') {
            return null;
        }

        return [
            'lang' => $lang,
            'title' => $title,
        ];
    }

    private function clearWikiMetadata(array $data): array
    {
        $data['wiki_title'] = null;
        $data['wiki_summary'] = null;
        $data['wiki_thumbnail'] = null;
        $data['wiki_page_id'] = null;
        $data['wiki_lang'] = 'ru';

        return $data;
    }
}
