<?php

namespace App\Services;

use App\Services\Yandex\YandexSearchException;
use App\Services\Yandex\YandexSearchProfileRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use JsonException;

class YandexSearchService
{
    private const ALLOWLISTED_HOST = 'searchapi.api.cloud.yandex.net';

    public const ENDPOINT_PATH = '/v2/web/search';

    public function __construct(
        protected HttpFactory $http,
        protected YandexSearchProfileRegistry $profiles,
    ) {}

    public function search(
        string $queryText,
        int $page = 0,
        string $profileCode = YandexSearchProfileRegistry::PRODUCT_PAGE,
    ): array {
        $profile = $this->profiles->get($profileCode);
        $apiKey = config('services.yandex_search.api_key');
        $folderId = config('services.yandex_search.folder_id');
        $host = config('services.yandex_search.host', self::ALLOWLISTED_HOST);

        if (! $apiKey || ! $folderId) {
            throw new YandexSearchException('configuration', 'yandex_search_not_configured');
        }

        $queryText = trim($queryText);
        if ($queryText === '' || mb_strlen($queryText) > $profile->maxQueryCharacters) {
            throw new YandexSearchException('validation', 'yandex_search_query_invalid');
        }
        if ($page < 0 || $page >= $profile->maxPages) {
            throw new YandexSearchException('validation', 'yandex_search_page_out_of_bounds');
        }
        if (! $this->configuredHostIsAllowlisted()) {
            throw new YandexSearchException('configuration', 'yandex_search_host_not_allowlisted');
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

        try {
            $response = $this->http
                ->withHeaders([
                    'Authorization' => 'Api-Key '.$apiKey,
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'identity',
                ])
                ->acceptJson()
                ->asJson()
                ->connectTimeout($profile->connectTimeoutSeconds)
                ->timeout($profile->timeoutSeconds)
                ->withoutRedirecting()
                ->withOptions([
                    'verify' => true,
                    'allow_redirects' => false,
                    'decode_content' => false,
                ])
                ->post('https://'.$host.self::ENDPOINT_PATH, $payload);
        } catch (ConnectionException) {
            throw new YandexSearchException('network', 'yandex_search_connection_failed');
        }

        if ($response->redirect()) {
            throw new YandexSearchException('security', 'yandex_search_redirect_blocked');
        }
        if (! $response->successful()) {
            throw new YandexSearchException(
                $this->statusCategory($response->status()),
                'yandex_search_http_'.$response->status(),
            );
        }

        $contentEncoding = mb_strtolower(trim((string) $response->header('Content-Encoding')));
        if ($contentEncoding !== '' && $contentEncoding !== 'identity') {
            throw new YandexSearchException('security', 'yandex_search_compressed_response_blocked');
        }

        $contentLength = trim((string) $response->header('Content-Length'));
        if ($contentLength !== '' && (! ctype_digit($contentLength) || (int) $contentLength > $profile->maxResponseBytes)) {
            throw new YandexSearchException('response', 'yandex_search_response_too_large');
        }
        $body = $response->body();
        if (strlen($body) > $profile->maxResponseBytes) {
            throw new YandexSearchException('response', 'yandex_search_response_too_large');
        }

        $contentType = mb_strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'application/json') && ! str_contains($contentType, '+json')) {
            throw new YandexSearchException('response', 'yandex_search_content_type_invalid');
        }

        try {
            $json = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new YandexSearchException('response', 'yandex_search_json_invalid');
        }
        if (! is_array($json)) {
            throw new YandexSearchException('response', 'yandex_search_json_invalid');
        }

        $rawData = Arr::get($json, 'rawData', '');
        if (! is_string($rawData) || strlen($rawData) > (int) ceil($profile->maxXmlBytes * 1.4)) {
            throw new YandexSearchException('response', 'yandex_search_xml_envelope_invalid');
        }

        return [
            'raw' => $json,
            'rawData' => $rawData,
            'requestId' => $this->safeRequestId($response->header('X-Request-Id')
                ?: $response->header('X-Yandex-Request-Id')),
        ];
    }

    public function configuredHostIsAllowlisted(): bool
    {
        $host = config('services.yandex_search.host', self::ALLOWLISTED_HOST);

        return is_string($host)
            && $host === self::ALLOWLISTED_HOST
            && preg_match('/^[a-z0-9.-]+$/', $host) === 1;
    }

    public function parseXmlResults(
        string $rawData,
        int $positionOffset = 0,
        string $profileCode = YandexSearchProfileRegistry::PRODUCT_PAGE,
    ): array {
        $profile = $this->profiles->get($profileCode);
        if (trim($rawData) === '') {
            return [];
        }

        $xml = base64_decode($rawData, true);

        if ($xml === false || trim($xml) === '') {
            $xml = $rawData;
        }

        if (strlen($xml) > $profile->maxXmlBytes) {
            throw new YandexSearchException('response', 'yandex_search_xml_too_large');
        }
        if (str_contains($xml, "\0") || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) === 1) {
            throw new YandexSearchException('security', 'yandex_search_xml_dtd_blocked');
        }

        $previousErrors = libxml_use_internal_errors(true);
        try {
            $xmlObject = simplexml_load_string(
                $xml,
                \SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        if (! $xmlObject) {
            throw new YandexSearchException('response', 'yandex_search_xml_invalid');
        }

        $results = [];
        $position = $positionOffset + 1;

        $docs = $xmlObject->xpath('//response/results/grouping/group/doc') ?: [];

        foreach ($docs as $doc) {
            if (count($results) >= $profile->maxResults) {
                break;
            }
            $url = trim((string) ($doc->url ?? ''));
            $title = $this->flattenXmlText($doc->title ?? null);
            $headline = $this->flattenXmlText($doc->headline ?? null);
            $snippet = $this->extractPassages($doc);
            $domain = trim((string) ($doc->domain ?? ''));

            if (! $this->isSafeResultUrl($url)) {
                continue;
            }

            if ($domain === '' && $url !== '') {
                $domain = parse_url($url, PHP_URL_HOST) ?: null;
            }

            $results[] = [
                'position' => $position++,
                'title' => mb_substr($title !== '' ? $title : $headline, 0, 512) ?: null,
                'url' => mb_substr($url, 0, 2048),
                'domain' => $domain ? mb_substr(mb_strtolower($domain), 0, 253) : null,
                'snippet' => $snippet ? mb_substr($snippet, 0, 2000) : null,
            ];
        }

        return $results;
    }

    protected function extractPassages(\SimpleXMLElement $doc): ?string
    {
        if (! isset($doc->passages)) {
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
        if (! $node) {
            return '';
        }

        $dom = dom_import_simplexml($node);
        if (! $dom) {
            return trim((string) $node);
        }

        return trim($dom->textContent ?? '');
    }

    private function isSafeResultUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && in_array(mb_strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && filled($parts['host'] ?? null)
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    private function safeRequestId(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $value) === 1 ? $value : null;
    }

    private function statusCategory(int $status): string
    {
        return match (true) {
            in_array($status, [401, 403], true) => 'authentication',
            $status === 429 => 'rate_limit',
            $status >= 500 => 'provider_unavailable',
            $status >= 400 => 'provider_rejected',
            default => 'response',
        };
    }
}
