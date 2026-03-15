<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use RuntimeException;

class YandexSearchService
{
    public function __construct(
        protected HttpFactory $http
    ) {}

    public function search(string $queryText, int $page = 0): array
    {
        $apiKey = config('services.yandex_search.api_key');
        $folderId = config('services.yandex_search.folder_id');
        $host = config('services.yandex_search.host', 'searchapi.api.cloud.yandex.net');

        if (!$apiKey || !$folderId) {
            throw new RuntimeException('YANDEX_SEARCH_API_KEY или YANDEX_SEARCH_FOLDER_ID не заданы.');
        }

        $payload = [
            'query' => [
                'searchType' => 'SEARCH_TYPE_RU',
                'queryText' => $queryText,
                'page' => $page,
                'fixTypoMode' => 'FIX_TYPO_MODE_OFF',
            ],
            'folderId' => $folderId,
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

    public function parseXmlResults(string $rawData, int $positionOffset = 0): array
    {
        if (trim($rawData) === '') {
            return [];
        }

        $xml = base64_decode($rawData, true);

        if ($xml === false || trim($xml) === '') {
            $xml = $rawData;
        }

        libxml_use_internal_errors(true);
        $xmlObject = simplexml_load_string($xml);

        if (!$xmlObject) {
            return [];
        }

        $results = [];
        $position = $positionOffset + 1;

        $docs = $xmlObject->xpath('//response/results/grouping/group/doc') ?: [];

        foreach ($docs as $doc) {
            $url = trim((string) ($doc->url ?? ''));
            $title = $this->flattenXmlText($doc->title ?? null);
            $headline = $this->flattenXmlText($doc->headline ?? null);
            $snippet = $this->extractPassages($doc);
            $domain = trim((string) ($doc->domain ?? ''));

            if ($domain === '' && $url !== '') {
                $domain = parse_url($url, PHP_URL_HOST) ?: null;
            }

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

    protected function extractPassages(\SimpleXMLElement $doc): ?string
    {
        if (!isset($doc->passages)) {
            return null;
        }

        $parts = [];

        foreach ($doc->passages->passage ?? [] as $passage) {
            $text = $this->flattenXmlText($passage);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return count($parts) ? implode("\n", $parts) : null;
    }

    protected function flattenXmlText($node): string
    {
        if (!$node) {
            return '';
        }

        $dom = dom_import_simplexml($node);
        if (!$dom) {
            return trim((string) $node);
        }

        return trim($dom->textContent ?? '');
    }
}
