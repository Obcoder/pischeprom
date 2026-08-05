<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SyncAvitoApiCatalogCommand extends Command
{
    /**
     * Avito uses POST for many searches and reports. Only endpoints reviewed as
     * side-effect-free are listed here; every new/ambiguous non-GET operation
     * defaults to mutation so an OpenAPI update cannot silently bypass the gate.
     */
    private const READ_ONLY_NON_GET = [
        'POST /listItemsByEmployeeIdV1',
        'POST /ads/v1/account/{accountID}/advertisers',
        'POST /ads/v1/account/{accountID}/campaigns',
        'POST /ads/v1/account/{accountID}/campaigns/{campaignID}/creatives/stats',
        'POST /ads/v1/account/{accountID}/campaigns/{campaignID}/groups/stats',
        'POST /ads/v1/account/{accountID}/campaigns/{campaignID}/stats',
        'POST /ads/v1/account/{accountID}/contracts',
        'POST /ads/v1/account/{accountID}/creatives',
        'POST /ads/v1/account/{accountID}/groups',
        'POST /autostrategy/v1/budget',
        'POST /autostrategy/v1/campaign/info',
        'POST /autostrategy/v1/campaigns',
        'POST /autostrategy/v1/stat',
        'POST /api/1/agency/clients',
        'POST /api/1/agency/clients/target/result',
        'POST /api/1/agency/finances/transactionsHistory',
        'POST /api/1/agency/users/invite/status',
        'POST /api/1/agency/users/verificationStatus',
        'POST /stats/v2/accounts/{user_id}/items',
        'POST /stats/v2/accounts/{user_id}/spendings',
        'POST /calltracking/v1/getCallById/',
        'POST /calltracking/v1/getCalls/',
        'POST /cpa/v1/chatsByTime',
        'POST /cpa/v1/phonesInfoFromChats',
        'POST /cpa/v2/balanceInfo',
        'POST /cpa/v2/callById',
        'POST /cpa/v2/callsByTime',
        'POST /cpa/v2/chatsByTime',
        'POST /cpa/v3/balanceInfo',
        'POST /cpxpromo/1/getPromotionsByItemIds',
        'POST /delivery-sandbox/order/checkConfirmationCode',
        'POST /delivery-sandbox/v1/getChangeParcelInfo',
        'POST /delivery-sandbox/v1/getParcelInfo',
        'POST /delivery-sandbox/v1/getRegisteredParcelID',
        'POST /core/v1/accounts/{userId}/vas/prices',
        'POST /core/v1/accounts/{user_id}/calls/stats/',
        'POST /job/v1/applications/get_by_ids',
        'POST /job/v2/vacancies/batch',
        'POST /job/v2/vacancies/statuses',
        'POST /messenger/v1/subscriptions',
        'POST /order-management/1/order/checkConfirmationCode',
        'POST /promotion/v1/items/services/bbip/forecasts/get',
        'POST /promotion/v1/items/services/bbip/suggests/get',
        'POST /promotion/v1/items/services/dict',
        'POST /promotion/v1/items/services/get',
        'POST /promotion/v1/items/services/orders/get',
        'POST /promotion/v1/items/services/orders/status',
        'POST /special-offers/v1/available',
        'POST /special-offers/v1/stats',
        'POST /special-offers/v1/tariffInfo',
        'POST /stock-management/1/info',
        'POST /core/v1/accounts/operations_history/',
    ];

    protected $signature = 'avito:catalog-sync {--check : Exit with an error when the committed snapshot is outdated}';

    protected $description = 'Download and normalize the official Avito OpenAPI catalog';

    public function handle(): int
    {
        try {
            $snapshot = $this->downloadSnapshot();
        } catch (\Throwable $exception) {
            $this->error('Не удалось получить официальный каталог Avito: '.$exception->getMessage());

            return self::FAILURE;
        }

        $path = (string) config('avito.catalog_path');

        if ($this->option('check')) {
            $current = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $matches = is_array($current)
                && hash_equals((string) ($current['source_hash'] ?? ''), $snapshot['source_hash'])
                && (int) ($current['counts']['capabilities'] ?? 0) === $snapshot['counts']['capabilities'];

            if (! $matches) {
                $this->error('Зафиксированный каталог Avito устарел. Выполните php artisan avito:catalog-sync.');

                return self::FAILURE;
            }

            $this->info("Каталог актуален: {$snapshot['counts']['sections']} разделов, {$snapshot['counts']['capabilities']} функций.");

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

        $this->info("Каталог сохранён: {$snapshot['counts']['sections']} разделов, {$snapshot['counts']['capabilities']} функций.");
        $this->line($path);

        return self::SUCCESS;
    }

    private function downloadSnapshot(): array
    {
        $http = $this->officialHttp();
        $catalogResponse = $http->get((string) config('avito.catalog_url'));

        if (! $catalogResponse->successful() || ! is_array($catalogResponse->json())) {
            throw new RuntimeException("catalog HTTP {$catalogResponse->status()}");
        }

        $catalog = $catalogResponse->json();
        $sections = [];
        $capabilities = [];
        $deduplication = [];
        $sourceParts = [(string) $catalogResponse->body()];

        foreach ($catalog as $sectionIndex => $section) {
            $slug = (string) ($section['slug'] ?? '');

            if ($slug === '' || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
                throw new RuntimeException('invalid catalog section slug');
            }

            $infoResponse = $http->get(sprintf((string) config('avito.catalog_info_url'), $slug));
            $info = $infoResponse->json();

            if (! $infoResponse->successful() || ! is_array($info) || ! is_string($info['swagger'] ?? null)) {
                throw new RuntimeException("{$slug} HTTP {$infoResponse->status()}");
            }

            $document = json_decode($info['swagger'], true);

            if (! is_array($document) || ! is_array($document['paths'] ?? null)) {
                throw new RuntimeException("{$slug}: invalid OpenAPI document");
            }

            $sourceParts[] = $info['swagger'];
            $sectionRecord = [
                'slug' => $slug,
                'title' => trim((string) ($section['title'] ?? $slug)),
                'description' => trim((string) ($section['description'] ?? '')),
                'documentation_url' => "https://developers.avito.ru/api-catalog/{$slug}/documentation",
                'operation_count' => 0,
                'order' => $sectionIndex,
            ];

            $servers = array_values(array_filter(array_map(
                fn (array $server) => rtrim((string) ($server['url'] ?? ''), '/'),
                (array) ($document['servers'] ?? [])
            )));

            foreach ($document['paths'] as $rawPath => $pathItem) {
                if (! is_array($pathItem)) {
                    continue;
                }

                $path = $this->normalizePath((string) $rawPath);
                $pathParameters = $this->parameters((array) ($pathItem['parameters'] ?? []), $document);

                foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                    $operation = $pathItem[$method] ?? null;

                    if (! is_array($operation)) {
                        continue;
                    }

                    $operationId = trim((string) ($operation['operationId'] ?? ''));
                    $operationId = $operationId !== '' ? $operationId : Str::camel($method.' '.$path);
                    $dedupeKey = strtoupper($method).' '.$path;

                    // Avito documents three different OAuth grant flows on the same
                    // /token path. They are separate capabilities, while identical
                    // cross-published operations in other sections are merged.
                    if ($path === '/token') {
                        $dedupeKey .= '|'.$operationId;
                    }

                    if (isset($deduplication[$dedupeKey])) {
                        $index = $deduplication[$dedupeKey];
                        $capabilities[$index]['also_listed_in'][] = $slug;

                        continue;
                    }

                    $parameters = $this->mergeParameters(
                        $pathParameters,
                        $this->parameters((array) ($operation['parameters'] ?? []), $document)
                    );
                    $security = $this->security((array) ($operation['security'] ?? $document['security'] ?? []));
                    $requestBody = $this->requestBody($operation['requestBody'] ?? null, $document);
                    $summary = trim((string) ($operation['summary'] ?? $operationId));
                    $description = trim((string) ($operation['description'] ?? ''));
                    $classification = $this->classify(strtoupper($method), $path, $summary.' '.$description);
                    $server = rtrim((string) Arr::get($operation, 'servers.0.url', Arr::get($pathItem, 'servers.0.url', $servers[0] ?? 'https://api.avito.ru')), '/');
                    $id = $slug.'.'.Str::slug($operationId).'.'.substr(sha1(strtoupper($method).' '.$path), 0, 10);

                    $capability = [
                        'id' => $id,
                        'section' => $slug,
                        'section_title' => $sectionRecord['title'],
                        'operation_id' => $operationId,
                        'method' => strtoupper($method),
                        'path' => $path,
                        'server' => $server,
                        'summary' => $summary,
                        'description' => $description,
                        'tags' => array_values((array) ($operation['tags'] ?? [])),
                        'security' => $security,
                        'parameters' => $parameters,
                        'request_body' => $requestBody,
                        'responses' => $this->responses((array) ($operation['responses'] ?? [])),
                        'deprecated' => (bool) ($operation['deprecated'] ?? false),
                        'access' => $classification['access'],
                        'risk' => $classification['risk'],
                        'requires_confirmation' => $classification['access'] === 'mutation',
                        'managed_by_integration' => $path === '/token',
                        'documentation_url' => $sectionRecord['documentation_url'].'#operation/'.$operationId,
                        'also_listed_in' => [],
                    ];

                    $deduplication[$dedupeKey] = count($capabilities);
                    $capabilities[] = $capability;
                    $sectionRecord['operation_count']++;
                }
            }

            $sections[] = $sectionRecord;
        }

        $methodCounts = array_count_values(array_column($capabilities, 'method'));
        ksort($methodCounts);

        return [
            'schema_version' => 1,
            'generated_at' => now()->utc()->toIso8601String(),
            'source' => (string) config('avito.catalog_url'),
            'source_hash' => hash('sha256', implode("\n", $sourceParts)),
            'counts' => [
                'sections' => count($sections),
                'capabilities' => count($capabilities),
                'deprecated' => count(array_filter($capabilities, fn (array $item) => $item['deprecated'])),
                'methods' => $methodCounts,
            ],
            'sections' => $sections,
            'capabilities' => $capabilities,
        ];
    }

    private function officialHttp(): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent('Pischeprom-Ameise-Avito-Catalog/1.0')
            ->connectTimeout(10)
            ->timeout(30)
            ->retry(3, 500, throw: false);
    }

    private function normalizePath(string $path): string
    {
        $path = preg_replace('/[\p{Cf}\x{200E}\x{200F}]/u', '', $path) ?? $path;

        return '/'.ltrim(trim($path), '/');
    }

    private function parameters(array $parameters, array $document): array
    {
        $result = [];

        foreach ($parameters as $parameter) {
            $parameter = $this->resolveReference($parameter, $document);

            if (! is_array($parameter) || blank($parameter['name'] ?? null) || blank($parameter['in'] ?? null)) {
                continue;
            }

            $location = (string) $parameter['in'];
            $style = (string) ($parameter['style'] ?? match ($location) {
                'query', 'cookie' => 'form',
                'path', 'header' => 'simple',
                default => 'form',
            });
            $media = collect((array) ($parameter['content'] ?? []))->first();
            $schema = (array) ($parameter['schema'] ?? (is_array($media) ? ($media['schema'] ?? []) : []));

            $result[] = [
                'name' => (string) $parameter['name'],
                'in' => $location,
                'required' => (bool) ($parameter['required'] ?? false),
                'description' => trim((string) ($parameter['description'] ?? '')),
                'style' => $style,
                'explode' => (bool) ($parameter['explode'] ?? ($style === 'form')),
                'allow_reserved' => (bool) ($parameter['allowReserved'] ?? false),
                'schema' => $this->schema($schema, $document),
                'example' => $parameter['example'] ?? Arr::get($schema, 'example') ?? (is_array($media) ? ($media['example'] ?? null) : null),
            ];
        }

        return $result;
    }

    private function mergeParameters(array $pathParameters, array $operationParameters): array
    {
        $merged = [];

        foreach (array_merge($pathParameters, $operationParameters) as $parameter) {
            $merged[$parameter['in'].':'.$parameter['name']] = $parameter;
        }

        return array_values($merged);
    }

    private function security(array $security): array
    {
        $result = [];

        foreach ($security as $alternative) {
            if (! is_array($alternative)) {
                continue;
            }

            foreach ($alternative as $scheme => $scopes) {
                $normalizedScheme = Str::contains(Str::lower((string) $scheme), 'client')
                    ? 'client_credentials'
                    : (Str::contains(Str::lower((string) $scheme), 'authorization') ? 'authorization_code' : (string) $scheme);
                $key = $normalizedScheme.'|'.implode(',', (array) $scopes);
                $result[$key] = [
                    'scheme' => $normalizedScheme,
                    'scopes' => array_values((array) $scopes),
                ];
            }
        }

        return array_values($result);
    }

    private function requestBody(mixed $requestBody, array $document): ?array
    {
        if (! is_array($requestBody)) {
            return null;
        }

        $requestBody = $this->resolveReference($requestBody, $document);
        $content = [];

        foreach ((array) ($requestBody['content'] ?? []) as $contentType => $media) {
            $content[$contentType] = [
                'schema' => $this->schema((array) ($media['schema'] ?? []), $document),
                'example' => $media['example'] ?? null,
            ];
        }

        return [
            'required' => (bool) ($requestBody['required'] ?? false),
            'description' => trim((string) ($requestBody['description'] ?? '')),
            'content' => $content,
        ];
    }

    private function schema(array $schema, array $document, int $depth = 0, array $seen = []): array
    {
        if ($depth > 7) {
            return ['$truncated' => true];
        }

        if (isset($schema['$ref'])) {
            $reference = (string) $schema['$ref'];

            if (in_array($reference, $seen, true)) {
                return ['$ref' => basename(str_replace('~1', '/', $reference)), '$recursive' => true];
            }

            $resolved = $this->resolveReference($schema, $document);
            $seen[] = $reference;
            $schema = is_array($resolved) ? $resolved : $schema;
        }

        $result = Arr::only($schema, [
            'type', 'format', 'title', 'description', 'enum', 'default', 'example',
            'nullable', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern',
            'minItems', 'maxItems', 'required', 'additionalProperties',
        ]);

        if (is_array($schema['properties'] ?? null)) {
            $result['properties'] = [];

            foreach ($schema['properties'] as $name => $property) {
                $result['properties'][$name] = $this->schema((array) $property, $document, $depth + 1, $seen);
            }
        }

        if (is_array($schema['items'] ?? null)) {
            $result['items'] = $this->schema($schema['items'], $document, $depth + 1, $seen);
        }

        foreach (['allOf', 'oneOf', 'anyOf'] as $composition) {
            if (is_array($schema[$composition] ?? null)) {
                $result[$composition] = array_map(
                    fn ($item) => $this->schema((array) $item, $document, $depth + 1, $seen),
                    $schema[$composition]
                );
            }
        }

        return $result;
    }

    private function resolveReference(mixed $value, array $document): mixed
    {
        if (! is_array($value) || ! isset($value['$ref']) || ! str_starts_with((string) $value['$ref'], '#/')) {
            return $value;
        }

        $segments = array_map(
            fn (string $segment) => str_replace(['~1', '~0'], ['/', '~'], $segment),
            explode('/', substr((string) $value['$ref'], 2))
        );
        $resolved = Arr::get($document, implode('.', $segments));

        if (! is_array($resolved)) {
            return $value;
        }

        return array_replace_recursive($resolved, Arr::except($value, ['$ref']));
    }

    private function responses(array $responses): array
    {
        $result = [];

        foreach ($responses as $status => $response) {
            $result[] = [
                'status' => (string) $status,
                'description' => trim((string) (is_array($response) ? ($response['description'] ?? '') : '')),
                'content_types' => is_array($response) ? array_keys((array) ($response['content'] ?? [])) : [],
            ];
        }

        return $result;
    }

    private function classify(string $method, string $path, string $text): array
    {
        if ($path === '/token') {
            return ['access' => 'managed', 'risk' => 'protected'];
        }

        if ($method === 'GET') {
            return ['access' => 'read', 'risk' => 'safe'];
        }

        $haystack = Str::lower($path.' '.$text);
        $destructiveMarkers = [
            'delete', 'remove', 'cancel', 'unsubscribe', 'blacklist', 'deactivate', 'archive',
            'удален', 'удалён', 'отмен', 'отпис', 'черный список', 'чёрный список', 'деактив',
            'архив', 'закрыт', 'снят', 'возврат',
        ];

        if (Str::contains($haystack, $destructiveMarkers) || $method === 'DELETE') {
            return ['access' => 'mutation', 'risk' => 'destructive'];
        }

        if (in_array($method.' '.$path, self::READ_ONLY_NON_GET, true)) {
            return ['access' => 'read', 'risk' => 'safe'];
        }

        return ['access' => 'mutation', 'risk' => 'write'];
    }
}
