<?php

namespace App\Services\Avito;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoApiCall;
use App\Models\AvitoCapabilitySetting;
use App\Models\AvitoConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AvitoApiExecutor
{
    public function __construct(
        private readonly AvitoApiCatalog $catalog,
        private readonly AvitoTokenManager $tokens,
        private readonly AvitoPayloadRedactor $redactor,
    ) {}

    public function execute(
        string $capabilityId,
        array $input,
        ?AvitoConnection $connection = null,
        array $files = [],
    ): array {
        if (! config('avito.enabled')) {
            throw new AvitoException('Интеграция Avito отключена серверной настройкой.', 'disabled', 503);
        }

        $capability = $this->catalog->find($capabilityId);

        if ($capability['managed_by_integration']) {
            throw new AvitoException('OAuth-токены управляются кнопками подключения и не выполняются из консоли.', 'managed_operation', 422);
        }

        $setting = AvitoCapabilitySetting::query()->firstOrCreate(
            ['capability_id' => $capabilityId],
            ['enabled' => ! $capability['deprecated']]
        );

        if (! $setting->enabled) {
            throw new AvitoException('Функция отключена в реестре Avito.', 'capability_disabled', 403);
        }

        if ($capability['access'] === 'mutation') {
            if (! config('avito.mutations_enabled')) {
                throw new AvitoException(
                    'Изменяющие операции заблокированы. После общей авторизации Ameise задайте AVITO_MUTATIONS_ENABLED=true.',
                    'mutations_disabled',
                    403
                );
            }

            if (! hash_equals((string) config('avito.mutation_confirmation'), (string) ($input['confirmation'] ?? ''))) {
                throw new AvitoException('Для изменяющей операции требуется контрольное подтверждение.', 'confirmation_required', 422);
            }
        }

        $requestId = (string) Str::uuid();
        $startedAt = hrtime(true);
        $url = $this->buildUrl($capability, (array) ($input['path'] ?? []));
        $query = $this->allowedValues($capability, 'query', (array) ($input['query'] ?? []));
        $headers = $this->allowedValues($capability, 'header', (array) ($input['headers'] ?? []), ['authorization']);
        $headers = $this->serializeHeaders($headers);
        $body = $input['body'] ?? null;
        $contentType = $this->contentType($capability, (string) ($input['content_type'] ?? ''));
        $body = $this->prepareWebhookBody($capability, $body);
        $files = $this->mapFilesToSchema($capability, $contentType, $files);

        $log = AvitoApiCall::query()->create([
            'avito_connection_id' => $connection?->id,
            'request_id' => $requestId,
            'capability_id' => $capabilityId,
            'method' => $capability['method'],
            'endpoint' => $url,
            'status' => 'running',
            'request_meta' => $this->redactor->redact([
                'path' => $input['path'] ?? [],
                'query' => $query,
                'headers' => $headers,
                'body' => $body,
                'files' => array_keys($files),
                'content_type' => $contentType,
            ]),
        ]);

        try {
            $token = $this->tokens->tokenFor($capability, $connection);
            $response = $this->send($capability, $url, $query, $headers, $body, $contentType, $files, $token);
            $result = $this->result($response, $requestId);
            if (! $result['binary']) {
                $result['data'] = $this->redactor->redact($result['data']);
            }
            $duration = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            $log->update([
                'status' => $response->successful() ? 'success' : 'remote_error',
                'http_status' => $response->status(),
                'duration_ms' => $duration,
                'response_meta' => $this->redactor->redact([
                    'headers' => $result['headers'],
                    'body' => $result['binary'] ? '[binary response]' : $result['data'],
                ]),
                'error_message' => $response->successful() ? null : $this->remoteError($result['data']),
            ]);
            $setting->update([
                'last_status' => $response->successful() ? 'success' : 'remote_error',
                'last_used_at' => now(),
            ]);

            return $result + [
                'ok' => $response->successful(),
                'duration_ms' => $duration,
                'capability_id' => $capabilityId,
            ];
        } catch (\Throwable $exception) {
            $duration = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $message = $exception instanceof AvitoException
                ? $exception->getMessage()
                : 'Сетевой запрос к Avito не выполнен.';

            $log->update([
                'status' => 'error',
                'duration_ms' => $duration,
                'error_message' => Str::limit($message, 1000),
            ]);
            $setting->update(['last_status' => 'error', 'last_used_at' => now()]);

            if ($exception instanceof AvitoException) {
                throw $exception;
            }

            report($exception);

            throw new AvitoException($message, 'network', 502, true);
        }
    }

    private function buildUrl(array $capability, array $values): string
    {
        $documentServer = rtrim((string) $capability['server'], '/');
        $documentHost = Str::lower((string) parse_url($documentServer, PHP_URL_HOST));
        $base = match ($documentHost) {
            'api.avito.ru' => rtrim((string) config('avito.api_base_url'), '/'),
            'pro.autoteka.ru' => rtrim((string) config('avito.autoteka_base_url'), '/'),
            default => throw new AvitoException('OpenAPI содержит недоверенный сервер.', 'host_not_allowed', 503),
        };

        $scheme = Str::lower((string) parse_url($base, PHP_URL_SCHEME));
        $host = Str::lower((string) parse_url($base, PHP_URL_HOST));
        $basePath = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
        $port = parse_url($base, PHP_URL_PORT);

        if ($scheme !== 'https'
            || ! in_array($host, (array) config('avito.allowed_hosts'), true)
            || $basePath !== ''
            || ($port !== null && $port !== 443)
            || parse_url($base, PHP_URL_USER) !== null
            || parse_url($base, PHP_URL_QUERY) !== null
            || parse_url($base, PHP_URL_FRAGMENT) !== null) {
            throw new AvitoException('Адрес API Avito не прошёл серверный allowlist.', 'host_not_allowed', 503);
        }

        $path = (string) $capability['path'];
        $pathParameters = collect($capability['parameters'])->where('in', 'path');

        foreach ($pathParameters as $parameter) {
            $name = $parameter['name'];
            $value = $values[$name] ?? null;
            $placeholder = $this->pathPlaceholder($path, $name) ?: $name;

            if (($parameter['required'] ?? false) && ($value === null || $value === '')) {
                throw new AvitoException("Не заполнен path-параметр {$name}.", 'validation', 422);
            }

            if ($value !== null) {
                if (! is_scalar($value)) {
                    throw new AvitoException("Path-параметр {$name} должен быть строкой или числом.", 'validation', 422);
                }

                $path = str_replace('{'.$placeholder.'}', rawurlencode((string) $value), $path);
            }
        }

        if (preg_match('/\{[^}]+}/', $path)) {
            throw new AvitoException('Заполнены не все path-параметры.', 'validation', 422);
        }

        return $base.'/'.ltrim($path, '/');
    }

    private function pathPlaceholder(string $path, string $parameter): ?string
    {
        preg_match_all('/\{([^}]+)}/', $path, $matches);
        $normalizedParameter = Str::lower(preg_replace('/[^a-z0-9]/i', '', $parameter) ?? $parameter);

        foreach ($matches[1] ?? [] as $placeholder) {
            $normalizedPlaceholder = Str::lower(preg_replace('/[^a-z0-9]/i', '', $placeholder) ?? $placeholder);

            if ($normalizedPlaceholder === $normalizedParameter) {
                return $placeholder;
            }
        }

        return null;
    }

    private function allowedValues(array $capability, string $location, array $values, array $excluded = []): array
    {
        $parameters = collect($capability['parameters'])->where('in', $location);
        $allowed = $parameters->pluck('name')->all();
        $normalizedExcluded = array_map('strtolower', $excluded);
        $result = [];

        foreach ($values as $name => $value) {
            if (! in_array($name, $allowed, true)) {
                throw new AvitoException("Параметр {$name} не разрешён официальной схемой.", 'validation', 422);
            }

            if (in_array(strtolower((string) $name), $normalizedExcluded, true)) {
                continue;
            }

            if ($value !== null && $value !== '') {
                $result[$name] = $value;
            }
        }

        foreach ($parameters as $parameter) {
            $name = $parameter['name'];

            if (($parameter['required'] ?? false)
                && ! in_array(strtolower($name), $normalizedExcluded, true)
                && (! array_key_exists($name, $result) || $result[$name] === '')) {
                throw new AvitoException("Не заполнен {$location}-параметр {$name}.", 'validation', 422);
            }
        }

        return $result;
    }

    private function contentType(array $capability, string $requested): ?string
    {
        $available = array_keys((array) Arr::get($capability, 'request_body.content', []));

        if ($available === []) {
            return null;
        }

        if ($requested === '') {
            return $available[0];
        }

        if (! in_array($requested, $available, true)) {
            throw new AvitoException('Content-Type отсутствует в официальной схеме функции.', 'validation', 422);
        }

        return $requested;
    }

    private function send(
        array $capability,
        string $url,
        array $query,
        array $headers,
        mixed $body,
        ?string $contentType,
        array $files,
        ?string $token,
    ): Response {
        $request = Http::acceptJson()
            ->withUserAgent('Pischeprom-Ameise-Avito/1.0')
            ->connectTimeout((int) config('avito.connect_timeout_seconds'))
            ->timeout((int) config('avito.timeout_seconds'))
            ->withOptions(['allow_redirects' => false, 'stream' => true]);

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        $url = $this->appendQuery($url, $capability, $query);

        if ($capability['access'] === 'read') {
            $request = $request->retry(2, 350, throw: false);
        }

        return $this->sendBody($request, $capability['method'], $url, $body, $contentType, $files);
    }

    private function appendQuery(string $url, array $capability, array $values): string
    {
        if ($values === []) {
            return $url;
        }

        $parameters = collect($capability['parameters'])->where('in', 'query')->keyBy('name');
        $pairs = [];

        foreach ($values as $name => $rawValue) {
            $parameter = $parameters->get($name, []);
            $schemaType = Arr::get($parameter, 'schema.type');
            $value = $this->coerceStructuredValue($rawValue, $schemaType);
            $style = (string) ($parameter['style'] ?? 'form');
            $explode = (bool) ($parameter['explode'] ?? true);

            if (is_array($value) && array_is_list($value)) {
                if ($style === 'spaceDelimited') {
                    $pairs[] = [$name, implode(' ', array_map([$this, 'scalarString'], $value))];
                } elseif ($style === 'pipeDelimited') {
                    $pairs[] = [$name, implode('|', array_map([$this, 'scalarString'], $value))];
                } elseif ($explode) {
                    foreach ($value as $item) {
                        $pairs[] = [$name, $this->scalarString($item)];
                    }
                } else {
                    $pairs[] = [$name, implode(',', array_map([$this, 'scalarString'], $value))];
                }

                continue;
            }

            if (is_array($value)) {
                if ($style === 'deepObject') {
                    foreach ($value as $key => $item) {
                        $pairs[] = ["{$name}[{$key}]", $this->scalarString($item)];
                    }
                } elseif ($explode) {
                    foreach ($value as $key => $item) {
                        $pairs[] = [(string) $key, $this->scalarString($item)];
                    }
                } else {
                    $flattened = [];
                    foreach ($value as $key => $item) {
                        $flattened[] = (string) $key;
                        $flattened[] = $this->scalarString($item);
                    }
                    $pairs[] = [$name, implode(',', $flattened)];
                }

                continue;
            }

            $pairs[] = [$name, $this->scalarString($value)];
        }

        $query = implode('&', array_map(
            fn (array $pair) => rawurlencode((string) $pair[0]).'='.rawurlencode((string) $pair[1]),
            $pairs
        ));

        return $query === '' ? $url : $url.'?'.$query;
    }

    private function coerceStructuredValue(mixed $value, ?string $schemaType): mixed
    {
        if (! is_string($value) || ! in_array($schemaType, ['array', 'object'], true)) {
            return $value;
        }

        $trimmed = trim($value);
        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if ($schemaType === 'array') {
            return array_values(array_filter(array_map('trim', explode(',', $trimmed)), fn (string $item) => $item !== ''));
        }

        throw new AvitoException('Object query-параметр должен быть JSON-объектом.', 'validation', 422);
    }

    private function serializeHeaders(array $headers): array
    {
        return collect($headers)->map(fn ($value) => is_array($value)
            ? implode(',', array_map([$this, 'scalarString'], $value))
            : $this->scalarString($value))->all();
    }

    private function scalarString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($encoded !== false) {
                return $encoded;
            }
        }

        throw new AvitoException('Параметр содержит неподдерживаемое значение.', 'validation', 422);
    }

    private function sendBody(
        PendingRequest $request,
        string $method,
        string $url,
        mixed $body,
        ?string $contentType,
        array $files,
    ): Response {
        if ($contentType === 'multipart/form-data') {
            foreach ($files as $name => $file) {
                foreach (Arr::wrap($file) as $uploadedFile) {
                    if (! $uploadedFile instanceof UploadedFile || ! $uploadedFile->isValid()) {
                        throw new AvitoException("Файл {$name} не прошёл проверку загрузки.", 'validation', 422);
                    }

                    $request = $request->attach(
                        (string) $name,
                        fopen($uploadedFile->getRealPath(), 'rb'),
                        $uploadedFile->getClientOriginalName(),
                        ['Content-Type' => $uploadedFile->getMimeType() ?: 'application/octet-stream']
                    );
                }
            }

            return $request->send($method, $url, ['multipart' => $this->multipartFields(is_array($body) ? $body : [])]);
        }

        if ($contentType === 'application/x-www-form-urlencoded') {
            return $request->asForm()->send($method, $url, ['form_params' => is_array($body) ? $body : []]);
        }

        if ($contentType !== null) {
            $encoded = json_encode($body ?? new \stdClass, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                throw new AvitoException('Тело запроса нельзя преобразовать в JSON.', 'validation', 422);
            }

            return $request->withBody($encoded, $contentType)->send($method, $url);
        }

        return $request->send($method, $url);
    }

    private function mapFilesToSchema(array $capability, ?string $contentType, array $files): array
    {
        if ($contentType !== 'multipart/form-data' || $files === []) {
            return $files;
        }

        $schema = (array) Arr::get($capability, 'request_body.content.multipart/form-data.schema', []);
        $binaryFields = collect((array) ($schema['properties'] ?? []))
            ->filter(fn (array $property) => ($property['format'] ?? null) === 'binary')
            ->keys()
            ->values()
            ->all();

        if (count($binaryFields) !== 1) {
            return $files;
        }

        $uploaded = [];
        array_walk_recursive($files, function ($file) use (&$uploaded): void {
            if ($file instanceof UploadedFile) {
                $uploaded[] = $file;
            }
        });

        return [$binaryFields[0] => $uploaded];
    }

    private function prepareWebhookBody(array $capability, mixed $body): mixed
    {
        $operationId = (string) ($capability['operation_id'] ?? '');

        if (! in_array($operationId, [
            'applicationsWebhookPut',
            'postWebhookV3',
            'postWebhookUnsubscribe',
        ], true)) {
            return $body;
        }

        if (! is_array($body)) {
            $body = [];
        }

        $secret = (string) config('avito.webhook_secret');
        if ($secret === '') {
            throw new AvitoException('Для webhook-функции не задан AVITO_WEBHOOK_SECRET.', 'configuration', 503);
        }

        $publicUrl = route('api.avito.webhook');

        if ($operationId === 'applicationsWebhookPut') {
            $requestedUrl = (string) ($body['url'] ?? '');
            if ($requestedUrl !== '' && rtrim($requestedUrl, '/') !== rtrim($publicUrl, '/')) {
                throw new AvitoException('Webhook «Работы» можно связать только с защищённым endpoint Ameise.', 'validation', 422);
            }

            $body['url'] = $publicUrl;
            $body['secret'] = $secret;

            return $body;
        }

        $requestedUrl = (string) ($body['url'] ?? '');
        if ($requestedUrl === '' || rtrim(strtok($requestedUrl, '?'), '/') === rtrim($publicUrl, '/')) {
            $body['url'] = $publicUrl.'?'.http_build_query(['secret' => $secret]);
        }

        return $body;
    }

    private function multipartFields(array $body): array
    {
        $fields = [];

        foreach ($body as $name => $value) {
            $fields[] = [
                'name' => (string) $name,
                'contents' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $fields;
    }

    private function result(Response $response, string $requestId): array
    {
        $maxBytes = (int) config('avito.max_response_bytes');
        $contentLength = (int) ($response->header('Content-Length') ?: 0);

        if ($contentLength > $maxBytes) {
            throw new AvitoException('Ответ Avito превышает допустимый размер.', 'response_too_large', 502);
        }

        $stream = $response->toPsrResponse()->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';
        while (! $stream->eof() && strlen($body) <= $maxBytes) {
            $body .= $stream->read(min(65536, $maxBytes + 1 - strlen($body)));
        }

        if (strlen($body) > $maxBytes || ! $stream->eof()) {
            throw new AvitoException('Ответ Avito превышает допустимый размер.', 'response_too_large', 502);
        }

        $contentType = Str::lower((string) $response->header('Content-Type'));
        $isJson = Str::contains($contentType, ['application/json', '+json']);
        $isText = $isJson || Str::startsWith($contentType, 'text/') || $contentType === '';
        $decodedJson = null;
        $hasDecodedJson = false;

        // Some live Messenger endpoints return a valid JSON document with an
        // empty or text Content-Type. Accept only syntactically valid JSON;
        // ordinary textual and binary responses keep their original handling.
        if ($isText && $body !== '') {
            $decodedJson = json_decode($body, true);
            $hasDecodedJson = json_last_error() === JSON_ERROR_NONE;
        }

        $data = $hasDecodedJson
            ? $decodedJson
            : ($isText ? $body : base64_encode($body));

        return [
            'request_id' => $requestId,
            'status' => $response->status(),
            'headers' => array_filter([
                'content_type' => $response->header('Content-Type'),
                'x_request_id' => $response->header('X-Request-Id'),
                'x_rate_limit_remaining' => $response->header('X-RateLimit-Remaining'),
                'retry_after' => $response->header('Retry-After'),
            ], fn ($value) => $value !== null),
            'binary' => ! $isText,
            'encoding' => ! $isText ? 'base64' : null,
            'data' => $data,
        ];
    }

    private function remoteError(mixed $data): string
    {
        if (is_array($data)) {
            $message = Arr::get($data, 'error.message')
                ?: Arr::get($data, 'error_description')
                ?: Arr::get($data, 'message');

            if (is_string($message) && $message !== '') {
                return Str::limit(strip_tags($message), 1000);
            }
        }

        return 'Avito вернул ошибочный HTTP-статус.';
    }
}
