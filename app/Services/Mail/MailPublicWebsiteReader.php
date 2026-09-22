<?php

namespace App\Services\Mail;

use App\Domain\AiSales\Web\PublicFetchPolicy;
use App\Domain\AiSales\Web\PublicPageTextExtractor;
use App\Domain\AiSales\Web\PublicUrlNormalizer;
use App\Domain\AiSales\Web\ResolvedPublicUrl;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class MailPublicWebsiteReader
{
    public const MAX_PAGES = 5;

    public function __construct(
        private readonly PublicFetchPolicy $policy,
        private readonly PublicUrlNormalizer $urls,
        private readonly PublicPageTextExtractor $extractor,
    ) {}

    public function normalize(string $url): string
    {
        try {
            return $this->urls->normalize($url);
        } catch (Throwable) {
            throw new MailResearchException('Укажите публичный адрес сайта с http:// или https://.');
        }
    }

    /** Bounded public pages only. No mail body, credentials or authenticated pages are fetched. */
    public function read(string $url): array
    {
        $url = $this->normalize($url);
        $host = $this->siteHost($url);
        $queue = [$url];
        $visited = [];
        $pages = [];
        $warnings = [];
        $deadline = microtime(true) + 18;
        while ($queue !== [] && count($visited) < self::MAX_PAGES && microtime(true) < $deadline) {
            $next = array_shift($queue);
            if (isset($visited[$next])) {
                continue;
            }
            $visited[$next] = true;
            try {
                [$body, $finalUrl, $contentType] = $this->page($next, $host, $deadline);
                $spaced = preg_replace('/(<\/(?:p|div|li|h[1-6]|tr|td|a)>|<br\s*\/?>)/iu', '$1 ', $body) ?? $body;
                $extract = $this->extractor->extract($spaced, $contentType, $finalUrl);
                $pages[] = [
                    'url' => $finalUrl,
                    'title' => $extract->title,
                    'text' => mb_substr(implode("\n", array_filter([
                        $extract->title, $extract->metaDescription, ...$extract->headings, $extract->visibleText,
                    ])), 0, 14000),
                ];
                foreach ($this->catalogLinks($body, $finalUrl, $host) as $link) {
                    if (! isset($visited[$link]) && ! in_array($link, $queue, true)) {
                        $queue[] = $link;
                    }
                }
            } catch (Throwable) {
                if ($pages === []) {
                    throw new MailResearchException('Не удалось прочитать публичную страницу сайта. Проверьте адрес; сайт может быть недоступен или запрещать сканирование.', 422);
                }
                $warnings[] = 'Одна из страниц каталога недоступна; список может быть неполным.';
            }
        }
        if ($pages === []) {
            throw new MailResearchException('На сайте не найден доступный текст.');
        }

        return ['pages' => $pages, 'warnings' => array_values(array_unique($warnings))];
    }

    private function page(string $url, string $siteHost, float $deadline): array
    {
        for ($redirect = 0; $redirect <= 2; $redirect++) {
            $this->remaining($deadline);
            if ($this->siteHost($url) !== $siteHost) {
                throw new MailResearchException('Переход на другой сайт запрещён.');
            }
            $target = $this->policy->authorize($url);
            $this->remaining($deadline);
            $this->robots($target, $deadline);
            $response = $this->request($target, 524288, $deadline);
            if ($response->redirect()) {
                $url = $this->absolute((string) $response->header('Location'), $target->url);

                continue;
            }
            $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            if (! $response->successful() || ! in_array($type, ['text/html', 'text/plain', 'application/xhtml+xml'], true)) {
                throw new MailResearchException('Страница не содержит доступного HTML-каталога.');
            }
            $body = $response->body();
            if (preg_match('/charset\s*=\s*["\']?([a-z0-9_-]+)/i', (string) $response->header('Content-Type'), $charset)
                && in_array(strtolower($charset[1]), ['windows-1251', 'cp1251', 'koi8-r', 'iso-8859-1'], true)) {
                $body = mb_convert_encoding($body, 'UTF-8', $charset[1]);
            } elseif (! mb_check_encoding($body, 'UTF-8')) {
                $body = mb_convert_encoding($body, 'UTF-8', 'Windows-1251');
            }

            return [$body, $target->url, $type];
        }
        throw new MailResearchException('Слишком много перенаправлений сайта.');
    }

    private function request(ResolvedPublicUrl $target, int $maxBytes, float $deadline): Response
    {
        $this->remaining($deadline);
        foreach ($target->ipAddresses as $ip) {
            $packed = inet_pton($ip);
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
                || (strlen($packed) === 16 && ((ord($packed[0]) & 0xE0) !== 0x20
                    || substr($packed, 0, 2) === "\x20\x02" || substr($packed, 0, 4) === "\x20\x01\x00\x00"))) {
                throw new MailResearchException('Непубличный адрес сайта запрещён.');
            }
        }
        $this->policy->assertDnsStable($target);
        // DNS checks are synchronous: do not give HTTP a timeout calculated before them.
        $remaining = $this->remaining($deadline);
        $response = Http::withHeaders([
            'User-Agent' => 'PischepromCatalog/1.0',
            'Accept' => 'text/html,text/plain;q=0.9',
            'Accept-Encoding' => 'identity',
        ])->connectTimeout(min(2, $remaining))->timeout(min(4, $remaining))
            ->withOptions([
                ...$this->policy->pinnedTransportOptions($target),
                'proxy' => '',
                'on_headers' => static function ($response) use ($maxBytes): void {
                    if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                        throw new MailResearchException('Страница слишком велика.');
                    }
                },
                'progress' => static function ($total, $downloaded) use ($maxBytes): void {
                    if ($downloaded > $maxBytes) {
                        throw new MailResearchException('Страница слишком велика.');
                    }
                },
            ])->get($target->url);
        $this->policy->assertDnsStable($target);
        $this->remaining($deadline);
        if (strlen($response->body()) > $maxBytes
            || ! in_array(strtolower(trim((string) $response->header('Content-Encoding'))), ['', 'identity'], true)) {
            throw new MailResearchException('Страница слишком велика или использует неподдерживаемое сжатие.');
        }

        return $response;
    }

    private function robots(ResolvedPublicUrl $target, float $deadline): void
    {
        $origin = parse_url($target->url, PHP_URL_SCHEME).'://'.$target->host;
        $rules = Cache::remember('mail-catalog-robots:v2:'.hash('sha256', $origin), 3600, function () use ($origin, $deadline): array {
            $response = $this->request($this->policy->authorize($origin.'/robots.txt'), 65536, $deadline);
            if ($response->status() === 404) {
                return [];
            }
            if (! $response->successful()) {
                throw new MailResearchException('Не удалось проверить правила сайта.');
            }

            return $this->robotRules($response->body());
        });
        $path = (string) parse_url($target->url, PHP_URL_PATH);
        $query = (string) parse_url($target->url, PHP_URL_QUERY);
        $path .= $query !== '' ? '?'.$query : '';
        $longest = -1;
        $allowed = true;
        foreach ($rules as $rule) {
            $end = str_ends_with($rule['path'], '$');
            $prefix = $end ? substr($rule['path'], 0, -1) : $rule['path'];
            $pattern = str_replace('\*', '.*', preg_quote($prefix, '~')).($end ? '$' : '');
            $match = preg_match('~(*LIMIT_MATCH=10000)^'.$pattern.'~', $path);
            if ($match === false) {
                throw new MailResearchException('Не удалось проверить правила сайта.');
            }
            $length = strlen(str_replace('*', '', $prefix));
            if ($match === 1 && ($length > $longest || ($length === $longest && $rule['allow']))) {
                $longest = $length;
                $allowed = $rule['allow'];
            }
        }
        if (! $allowed) {
            throw new MailResearchException('Сайт запрещает сканирование этой страницы.');
        }
    }

    /** Merge matching user-agent groups, then apply the most specific bot group before '*'. */
    private function robotRules(string $body): array
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }
        $groups = [];
        $group = ['agents' => [], 'rules' => []];
        $hasRules = false;
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'user-agent') {
                if ($hasRules) {
                    $groups[] = $group;
                    $group = ['agents' => [], 'rules' => []];
                    $hasRules = false;
                }
                if ($value !== '') {
                    $group['agents'][] = strtolower($value);
                }
            } elseif (in_array($key, ['allow', 'disallow'], true) && $group['agents'] !== []) {
                $hasRules = true;
                if ($value !== '') {
                    $group['rules'][] = ['path' => $value, 'allow' => $key === 'allow'];
                }
            }
        }
        $groups[] = $group;

        $rules = [];
        $specificity = -1;
        foreach ($groups as $candidate) {
            $score = -1;
            foreach ($candidate['agents'] as $agent) {
                if ($agent === '*') {
                    $score = max($score, 0);
                } elseif (str_starts_with('pischepromcatalog/1.0', $agent)) {
                    $score = max($score, strlen($agent));
                }
            }
            if ($score < 0 || $score < $specificity) {
                continue;
            }
            if ($score > $specificity) {
                $rules = [];
                $specificity = $score;
            }
            $rules = [...$rules, ...$candidate['rules']];
        }

        return $rules;
    }

    private function remaining(float $deadline): float
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            throw new MailResearchException('Время сканирования истекло.');
        }

        return $remaining;
    }

    private function catalogLinks(string $html, string $source, string $host): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $links = [];
            foreach ((new DOMXPath($document))->query('//a[@href]') ?: [] as $anchor) {
                if (! preg_match('/каталог|продукц|товар|ассортимент|catalog|product|assortment|shop/iu', $anchor->textContent.' '.$anchor->getAttribute('href'))) {
                    continue;
                }
                try {
                    $url = $this->absolute($anchor->getAttribute('href'), $source);
                    if ($this->siteHost($url) === $host && ! preg_match('/\.(pdf|docx?|xlsx?|zip|jpe?g|png)$/i', (string) parse_url($url, PHP_URL_PATH))) {
                        $links[] = $url;
                    }
                } catch (Throwable) {
                    continue;
                }
                if (count($links) >= 30) {
                    break;
                }
            }

            return array_values(array_unique($links));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function absolute(string $value, string $source): string
    {
        return $this->normalize((string) UriResolver::resolve(new Uri($source), new Uri(trim($value))));
    }

    private function siteHost(string $url): string
    {
        return preg_replace('/^www\./', '', $this->urls->host($url));
    }
}
