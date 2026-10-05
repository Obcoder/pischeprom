<?php

namespace App\Services\Goods;

use DOMDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class GoodTradeCodeRegistryService
{
    private const HS_URL = 'https://comtradeapi.un.org/files/v1/app/reference/H6.json';

    private const EEC_URL = 'https://eec.eaeunion.org/comission/department/catr/ett/';

    private const TTL = 86400;

    private const FAILURE_TTL = 60;

    public function __construct(private readonly GoodTnVedPdfCatalog $pdfCatalog) {}

    public function supportedFields(): array
    {
        return ['hs_code', 'tn_ved_code'];
    }

    public function lookup(string $field, string $code): array
    {
        $code = GoodTradeCodes::normalize([$field => $code])[$field] ?? $code;
        $source = config("goods-trade-codes-ai.sources.{$field}", []);
        $result = [
            'field' => $field,
            'code' => $code,
            'status' => 'not_supported',
            'title' => null,
            'source_url' => $source['url'] ?? null,
            'source_name' => $source['title'] ?? null,
            'version' => null,
            'checked_at' => now()->toIso8601String(),
            'message' => 'Автоматическая проверка этого справочника пока не подключена. Проверьте код в официальном источнике.',
        ];
        if (! in_array($field, $this->supportedFields(), true)) {
            return $result;
        }
        if ($field === 'hs_code') {
            $result['source_url'] = self::HS_URL;
            $result['source_name'] = 'UN Comtrade: справочник HS 2022';
            $result['version'] = 'HS 2022 (H6)';
        }
        if (Validator::make([$field => $code], [$field => ['required', ...GoodTradeCodes::rules()[$field]]])->fails()) {
            return [...$result, 'status' => 'unavailable', 'message' => 'Для проверки нужен полный код в допустимом формате.'];
        }

        try {
            return $field === 'hs_code' ? $this->hs($result) : $this->tnVed($result);
        } catch (Throwable) {
            return [...$result, 'status' => 'unavailable', 'message' => 'Официальный справочник временно недоступен или его формат изменился. Повторите проверку позже.'];
        }
    }

    private function hs(array $result): array
    {
        $chapter = (int) substr($result['code'], 0, 2);
        if ($chapter < 1 || $chapter > 97 || $chapter === 77) {
            return [...$result, 'status' => 'not_found', 'message' => 'Код не относится к товарным субпозициям HS 2022; технические коды статистической отчётности не подтверждаются.'];
        }

        $catalog = $this->cached('hs-h6-v1', function (): array {
            $data = json_decode($this->download(self::HS_URL, 3_000_000, 'json'), true, 16, JSON_THROW_ON_ERROR);
            if (($data['classCode'] ?? null) !== 'H6' || ($data['className'] ?? null) !== 'HS2022'
                || ($data['more'] ?? true) !== false || ! is_array($data['results'] ?? null)) {
                throw new RuntimeException('Invalid HS edition or incomplete response');
            }

            $codes = [];
            foreach ($data['results'] as $row) {
                $code = $row['id'] ?? null;
                if (! is_string($code) || ! preg_match('/^\d{6}$/D', $code)) {
                    continue;
                }
                $chapter = (int) substr($code, 0, 2);
                if ($chapter < 1 || $chapter > 97 || $chapter === 77) {
                    continue;
                }
                if (! is_string($row['text'] ?? null) || ! str_starts_with($row['text'], $code.' - ')) {
                    throw new RuntimeException('Malformed HS entry');
                }
                $title = trim(substr($row['text'], 9));
                if ($title === '' || isset($codes[$code])) {
                    throw new RuntimeException('Invalid HS title or duplicate code');
                }
                $codes[$code] = $title;
            }
            // A short/partial response must never turn an existing code into not_found.
            if (count($codes) < 5000 || count($codes) > 6500) {
                throw new RuntimeException('Incomplete HS catalog');
            }

            return $codes;
        });

        if (! isset($catalog[$result['code']])) {
            return [...$result, 'status' => 'not_found', 'message' => 'Точный код не найден в полном справочнике HS 2022.'];
        }

        return [...$result, 'status' => 'found', 'title' => $catalog[$result['code']],
            'message' => 'Код существует в HS 2022. Соответствие конкретному товару требует отдельной классификации.'];
    }

    private function tnVed(array $result): array
    {
        $chapter = substr($result['code'], 0, 2);
        $links = $this->cached('eec-chapter-index-v1', function (): array {
            $html = $this->download(self::EEC_URL, 1_000_000, 'html');
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (! $loaded) {
                throw new RuntimeException('Invalid EEC index');
            }
            $links = [];
            foreach ($document->getElementsByTagName('a') as $anchor) {
                $href = $anchor->getAttribute('href');
                $path = str_starts_with($href, 'https://eec.eaeunion.org/') ? substr($href, strlen('https://eec.eaeunion.org')) : $href;
                if (preg_match('~^/(?:upload/files/catr/ett|comission/department/catr/ett/ru\.2022)/ru\.(\d{2})_2022(?:_\d{2}\.\d{2}\.\d{4})?\.pdf$~D', $path, $match)) {
                    $url = 'https://eec.eaeunion.org'.$path;
                    if (isset($links[$match[1]]) && $links[$match[1]] !== $url) {
                        throw new RuntimeException('Ambiguous EEC chapter version');
                    }
                    $links[$match[1]] = $url;
                }
            }
            if (count($links) < 90) {
                throw new RuntimeException('Incomplete EEC chapter index');
            }

            return $links;
        });

        if (! isset($links[$chapter])) {
            return [...$result, 'status' => 'unavailable', 'message' => 'Для этой главы не найден действующий документ на сайте ЕЭК.'];
        }
        $url = $links[$chapter];
        $result['source_url'] = $url;
        $result['source_name'] = 'ЕЭК: ТН ВЭД ЕАЭС, группа '.$chapter;
        $result['version'] = basename(parse_url($url, PHP_URL_PATH));
        $title = $this->cached('eec-title-v1-'.hash('sha256', $url.'|'.$result['code']), function () use ($url, $result): array {
            // Database-backed UTF-8 cache columns must not receive arbitrary PDF bytes.
            $pdf = $this->cached('eec-pdf-v1-'.hash('sha256', $url), fn (): array => ['base64' => base64_encode($this->download($url, 4_000_000, 'pdf'))]);
            $bytes = base64_decode($pdf['base64'], true);
            if ($bytes === false) {
                throw new RuntimeException('Invalid cached PDF');
            }

            return ['title' => $this->pdfCatalog->title($bytes, $result['code'])];
        });

        if ($title['title'] === null) {
            return [...$result, 'status' => 'unavailable', 'message' => 'Точную строку кода не удалось подтвердить в PDF ЕЭК. Это не доказывает отсутствие кода; проверьте документ по ссылке.'];
        }

        return [...$result, 'status' => 'found', 'title' => $title['title'],
            'message' => 'Точная строка кода найдена в таблице ЕЭК. Показан фрагмент строки; полное наименование, родительские позиции и примечания смотрите в PDF. Соответствие товару не проверялось.'];
    }

    private function cached(string $key, callable $load): array
    {
        $key = 'goods-code-registry:'.$key;
        $stored = Cache::get($key);
        if (is_array($stored)) {
            if (isset($stored['error'])) {
                throw new RuntimeException('Source recently unavailable');
            }

            return $stored['data'];
        }

        try {
            $data = $load();
            Cache::put($key, ['data' => $data], self::TTL);

            return $data;
        } catch (Throwable $exception) {
            Cache::put($key, ['error' => true], self::FAILURE_TTL);
            throw $exception;
        }
    }

    private function download(string $url, int $limit, string $kind): string
    {
        $response = Http::connectTimeout(2)->timeout(6)->withUserAgent('pischeprom-code-registry/1.0')
            ->withOptions([
                'allow_redirects' => false, 'verify' => true, 'http_errors' => false,
                'on_headers' => static function ($response) use ($limit): void {
                    if ((int) $response->getHeaderLine('Content-Length') > $limit) {
                        throw new RuntimeException('Source too large');
                    }
                },
                'progress' => static function ($total, $downloaded) use ($limit): void {
                    if ($downloaded > $limit) {
                        throw new RuntimeException('Source too large');
                    }
                },
            ])->get($url);
        if (! $response->successful() || strlen($response->body()) > $limit) {
            throw new RuntimeException('Source unavailable');
        }
        $contentType = strtolower(explode(';', $response->header('Content-Type') ?? '')[0]);
        $expected = ['json' => 'application/json', 'html' => 'text/html', 'pdf' => 'application/pdf'][$kind];
        if ($contentType !== $expected) {
            throw new RuntimeException('Unexpected source content type');
        }

        return $response->body();
    }
}
