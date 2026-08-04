<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Exceptions\SafeRemoteDownloadException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SafeRemoteFileDownloader
{
    public function download(string $url): array
    {
        $redirects = 0;
        $maxRedirects = (int) config('ai-price-lists.max.max_redirects');

        while (true) {
            $resolvedIps = $this->assertSafeUrl($url);
            $parts = parse_url($url);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $port = (int) ($parts['port'] ?? 443);
            $apiHost = strtolower((string) parse_url((string) config('services.max.api_url'), PHP_URL_HOST));
            $options = ['allow_redirects' => false, 'stream' => true];

            if (defined('CURLOPT_RESOLVE') && $resolvedIps !== []) {
                $ip = $resolvedIps[0];
                $pinnedIp = str_contains($ip, ':') ? '['.$ip.']' : $ip;
                $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedIp}"]];
            }

            $response = Http::timeout((int) config('ai-price-lists.max.download_timeout_seconds'))
                ->connectTimeout(5)
                ->withOptions($options)
                ->withHeaders(array_filter([
                    'Accept' => 'application/octet-stream,image/*,application/pdf,*/*',
                    'Authorization' => $host === $apiHost ? (trim((string) config('services.max.access_token')) ?: null) : null,
                ]))
                ->get($url);

            if ($response->redirect()) {
                if ($redirects >= $maxRedirects) {
                    throw new SafeRemoteDownloadException('MAX вернул слишком много перенаправлений.', 'max_too_many_redirects');
                }

                $location = $response->header('Location');

                if (! is_string($location) || trim($location) === '') {
                    throw new SafeRemoteDownloadException('MAX вернул небезопасное перенаправление.', 'max_unsafe_redirect');
                }

                try {
                    $url = (string) UriResolver::resolve(new Uri($url), new Uri(trim($location)));
                } catch (\Throwable) {
                    throw new SafeRemoteDownloadException('MAX вернул некорректное перенаправление.', 'max_unsafe_redirect');
                }
                $redirects++;

                continue;
            }

            if ($response->failed()) {
                $retryable = $response->status() === 429 || $response->serverError();
                throw new SafeRemoteDownloadException(
                    $retryable ? 'Файл MAX временно недоступен.' : 'MAX не разрешил скачать вложение.',
                    $response->status() === 429 ? 'max_rate_limited' : 'max_download_failed',
                    $retryable,
                );
            }

            return $this->validatedBody($response);
        }
    }

    /** @return list<string> */
    public function assertSafeUrl(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw new SafeRemoteDownloadException('URL вложения MAX должен использовать HTTPS.', 'max_unsafe_url');
        }

        $allowed = collect(config('ai-price-lists.max.allowed_download_hosts', []))->contains(
            fn (string $allowedHost): bool => $host === $allowedHost || str_ends_with($host, '.'.$allowedHost)
        );

        if (! $allowed) {
            throw new SafeRemoteDownloadException('Хост вложения MAX не входит в allowlist.', 'max_host_not_allowed');
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records) || $records === []) {
            throw new SafeRemoteDownloadException('Не удалось безопасно определить адрес хоста MAX.', 'max_dns_failed', true);
        }

        $ips = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($ip === null) {
                continue;
            }

            if (! is_string($ip) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new SafeRemoteDownloadException('Хост вложения MAX указывает на запрещённую сеть.', 'max_private_address');
            }

            $ips[] = $ip;
        }

        if ($ips === []) {
            throw new SafeRemoteDownloadException('Не удалось безопасно определить адрес хоста MAX.', 'max_dns_failed', true);
        }

        return array_values(array_unique($ips));
    }

    private function validatedBody(Response $response): array
    {
        $limit = (int) config('ai-price-lists.limits.max_file_bytes');
        $contentLength = (int) ($response->header('Content-Length') ?: 0);

        if ($contentLength > $limit) {
            throw new SafeRemoteDownloadException('Файл MAX превышает допустимый размер.', 'file_too_large');
        }

        $stream = $response->toPsrResponse()->getBody();
        $content = '';
        $emptyReads = 0;

        while (! $stream->eof()) {
            $chunk = $stream->read(64 * 1024);

            if ($chunk === '') {
                $emptyReads++;

                if ($emptyReads >= 3) {
                    throw new SafeRemoteDownloadException('Загрузка файла MAX прервалась.', 'max_download_interrupted', true);
                }

                continue;
            }

            $emptyReads = 0;
            $content .= $chunk;

            if (strlen($content) > $limit) {
                throw new SafeRemoteDownloadException('Файл MAX превышает допустимый размер.', 'file_too_large');
            }
        }

        if ($content === '') {
            throw new SafeRemoteDownloadException(
                'Файл MAX пуст.',
                'empty_file',
            );
        }

        return [
            'content' => $content,
            'content_type' => trim(explode(';', (string) $response->header('Content-Type'))[0]) ?: 'application/octet-stream',
            'size' => strlen($content),
        ];
    }
}
