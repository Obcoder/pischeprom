<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

class YandexSearchService
{
    public function __construct(
        protected HttpFactory $http
    ) {}

    /**
     * Выполнить 1 запрос к Yandex Search API.
     */
    public function search(string $queryText, int $page = 0): array
    {
        $apiKey = config('services.yandex_search.api_key');
        $folderId = config('services.yandex_search.folder_id');
        $host = config('services.yandex_search.host');

        if (!$apiKey || !$folderId) {
            throw new \RuntimeException('YANDEX_SEARCH_API_KEY или YANDEX_SEARCH_FOLDER_ID не заданы.');
        }

        $payload = [
            'query' => [
                // Для RU-поиска:
                'searchType' => 'SEARCH_TYPE_RU',
                'queryText' => $queryText,
                'page' => $page,
                // можно управлять режимом опечаток при желании:
                'fixTypoMode' => 'FIX_TYPO_MODE_OFF',
            ],
            'folderId' => $folderId,

            // Просим XML-ответ в rawData — его удобнее разбирать.
            // Search API возвращает результаты в XML или HTML в rawData. :contentReference[oaicite:1]{index=1}
            'responseFormat' => 'FORMAT_XML',
        ];

        $response = $this->http
            ->withHeaders([
                              'Authorization' => 'Api-Key ' . $apiKey,
                              'Content-Type' => 'application/json',
                          ])
            ->acceptJson()
            ->timeout(30)
            ->post("https://{$host}/v2/web/search", $payload);

        $response->throw();

        $json = $response->json();

        return [
            'raw' => $json,
            'rawData' => Arr::get($json, 'rawData', ''),
        ];
    }

    /**
     * Разобрать XML rawData и вернуть плоский список результатов.
     */
    public function parseXmlResults(string $rawData): array
    {
        if (trim($rawData) === '') {
            return [];
        }

        $xml = base64_decode($rawData, true);

        if ($xml === false || trim($xml) === '') {
            // если вдруг rawData уже не base64, пробуем как есть
            $xml = $rawData;
        }

        libxml_use_internal_errors(true);
        $xmlObject = simplexml_load_string($xml);

        if (!$xmlObject) {
            return [];
        }

        $results = [];
        $position = 1;

        $docs = $xmlObject->xpath('//response/results/grouping/group/doc') ?: [];

        foreach ($docs as $doc) {
            $url = trim((string) ($doc->url ?? ''));
            $title = trim((string) ($doc->title ?? ''));
            $headline = trim((string) ($doc->headline ?? ''));
            $snippet = trim((string) ($doc->passages ?? ''));
            $domain = $url ? parse_url($url, PHP_URL_HOST) : null;

            $results[] = [
                'position' => $position++,
                'title' => $title !== '' ? $title : $headline,
                'url' => $url,
                'domain' => $domain,
                'snippet' => $snippet,
            ];
        }

        return $results;
    }
}
