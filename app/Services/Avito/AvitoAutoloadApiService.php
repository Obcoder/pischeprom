<?php

namespace App\Services\Avito;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AvitoAutoloadApiService
{
    public const PROFILE_UNAVAILABLE_MESSAGE = 'Avito не открыл API профиля Автозагрузки для этого кабинета. Активируйте Автозагрузку в настройках кабинета Avito или запросите доступ у поддержки Avito.';

    public function __construct(
        private readonly AvitoApiCatalog $catalog,
        private readonly AvitoApiExecutor $executor,
    ) {}

    public function categories(?AvitoConnection $connection = null): array
    {
        $cacheKey = 'avito:autoload:category-tree:v2:'.($connection?->id ?: 'server');

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($connection): array {
            $result = $this->execute('userDocsTree', [], $connection);

            return [
                'items' => $this->normalizeCategoryTree($result['data'] ?? []),
                'remote' => $this->remoteMeta($result),
            ];
        });
    }

    public function categoryFields(string $nodeSlug, ?AvitoConnection $connection = null): array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,179}$/i', $nodeSlug)) {
            throw new AvitoException('Некорректный slug категории Avito.', 'autoload_category', 422);
        }

        $cacheKey = 'avito:autoload:category-fields:'.($connection?->id ?: 'server').':'.$nodeSlug;

        return Cache::remember($cacheKey, now()->addHours(3), function () use ($nodeSlug, $connection): array {
            $result = $this->execute('userDocsNodeFields', [
                'path' => ['node_slug' => $nodeSlug],
            ], $connection);

            return [
                'items' => $this->normalizeCategoryFields($result['data'] ?? []),
                'raw' => $result['data'] ?? [],
                'remote' => $this->remoteMeta($result),
            ];
        });
    }

    public function profile(?AvitoConnection $connection = null): array
    {
        try {
            $result = $this->execute('getProfileV2', [], $connection, allowNotFound: true);
        } catch (AvitoException $exception) {
            if (! $this->isProfileUnavailable($exception)) {
                throw $exception;
            }

            return [
                'available' => false,
                'exists' => false,
                'profile' => null,
                'unavailable_reason' => self::PROFILE_UNAVAILABLE_MESSAGE,
                'remote' => ['status' => $exception->httpStatus],
            ];
        }

        if (($result['status'] ?? null) === 404) {
            return [
                'available' => true,
                'exists' => false,
                'profile' => null,
                'remote' => $this->remoteMeta($result),
            ];
        }

        return [
            'available' => true,
            'exists' => true,
            'profile' => $this->profilePayload($result['data'] ?? []),
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function attachFeed(
        string $feedName,
        string $feedUrl,
        array $input,
        ?AvitoConnection $connection = null,
    ): array {
        $current = $this->profile($connection);
        if (! ($current['available'] ?? true)) {
            throw new AvitoException(
                (string) ($current['unavailable_reason'] ?? self::PROFILE_UNAVAILABLE_MESSAGE),
                'autoload_profile_unavailable',
                403,
            );
        }
        $profile = (array) ($current['profile'] ?? []);
        $feeds = collect((array) ($profile['feeds_data'] ?? []))
            ->filter(fn ($feed): bool => is_array($feed))
            ->reject(fn (array $feed): bool => ($feed['feed_name'] ?? null) === $feedName
                || rtrim((string) ($feed['feed_url'] ?? ''), '/') === rtrim($feedUrl, '/'))
            ->map(fn (array $feed): array => Arr::only($feed, ['feed_name', 'feed_url']))
            ->values()
            ->all();
        $feeds[] = ['feed_name' => $feedName, 'feed_url' => $feedUrl];

        $reportEmail = trim((string) ($input['report_email'] ?? ($profile['report_email'] ?? '')));
        if ($reportEmail === '' || ! filter_var($reportEmail, FILTER_VALIDATE_EMAIL)) {
            throw new AvitoException('Укажите корректную почту для отчётов Автозагрузки.', 'autoload_profile', 422);
        }
        if (! $current['exists'] && ! ($input['agreement'] ?? false)) {
            throw new AvitoException('Для нового профиля подтвердите правила Автозагрузки Avito.', 'autoload_agreement', 422);
        }

        $schedule = array_key_exists('schedule', $input)
            ? array_values((array) $input['schedule'])
            : array_values((array) ($profile['schedule'] ?? []));
        $autoloadEnabled = array_key_exists('autoload_enabled', $input)
            ? (bool) $input['autoload_enabled']
            : (bool) ($profile['autoload_enabled'] ?? false);
        $body = [
            'feeds_data' => $feeds,
            'schedule' => $schedule,
            'autoload_enabled' => $autoloadEnabled,
            'report_email' => $reportEmail,
        ];
        if (! $current['exists']) {
            $body['agreement'] = true;
        }

        $result = $this->execute('createOrUpdateProfileV2', [
            'body' => $body,
            'content_type' => 'application/json',
        ], $connection, mutation: true);

        return [
            'profile' => $body,
            'result' => $result['data'] ?? null,
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function upload(?AvitoConnection $connection = null): array
    {
        $result = $this->execute('upload', [], $connection, mutation: true);

        return [
            'result' => $result['data'] ?? null,
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function currentUpload(?AvitoConnection $connection = null): array
    {
        $result = $this->execute('getCurrentUpload', [], $connection, allowNotFound: true);

        return [
            'exists' => ($result['status'] ?? null) !== 404,
            'upload' => ($result['status'] ?? null) === 404 ? null : ($result['data'] ?? null),
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function itemReport(string $externalId, ?AvitoConnection $connection = null): array
    {
        $input = ['query' => ['query' => $externalId, 'perPage' => 100, 'page' => 1]];
        $result = $this->execute('getCurrentUploadItems', $input, $connection, allowNotFound: true);
        $item = ($result['status'] ?? null) === 404
            ? null
            : $this->findReportItem($result['data'] ?? [], $externalId);

        if (($result['status'] ?? null) === 404 || $item === null) {
            $result = $this->execute('getLastSuccessfulUploadItems', $input, $connection, allowNotFound: true);
            $item = ($result['status'] ?? null) === 404
                ? null
                : $this->findReportItem($result['data'] ?? [], $externalId);
        }

        return [
            'exists' => ($result['status'] ?? null) !== 404,
            'item' => $item,
            'raw' => $result['data'] ?? null,
            'remote' => $this->remoteMeta($result),
        ];
    }

    public function avitoItemId(string $externalId, ?AvitoConnection $connection = null): ?int
    {
        $result = $this->execute('getAvitoIdsByAdIds', [
            'query' => ['query' => $externalId],
        ], $connection, allowNotFound: true);

        return $this->extractAvitoItemId($result['data'] ?? [], $externalId);
    }

    public function extractAvitoItemId(mixed $payload, ?string $externalId = null): ?int
    {
        foreach ($this->objects($payload) as $object) {
            $candidate = Arr::get($object, 'avito_id')
                ?? Arr::get($object, 'avitoId')
                ?? Arr::get($object, 'avito_item_id')
                ?? Arr::get($object, 'avitoItemId');
            $documentId = Arr::get($object, 'ad_id') ?? Arr::get($object, 'adId') ?? Arr::get($object, 'external_id');

            if ($externalId !== null && $documentId !== null && (string) $documentId !== $externalId) {
                continue;
            }
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function execute(
        string $operationId,
        array $input,
        ?AvitoConnection $connection = null,
        bool $mutation = false,
        bool $allowNotFound = false,
    ): array {
        $capability = $this->catalog->findOperation('autoload', $operationId);
        if ($mutation) {
            $input['confirmation'] = (string) config('avito.mutation_confirmation');
        }
        $result = $this->executor->execute($capability['id'], $input, $connection);

        if (! ($result['ok'] ?? false) && ! ($allowNotFound && ($result['status'] ?? null) === 404)) {
            $message = (string) (Arr::get($result, 'data.result.message')
                ?: Arr::get($result, 'data.error.message')
                ?: Arr::get($result, 'data.error_description')
                ?: Arr::get($result, 'data.message')
                ?: "Avito Autoload вернул HTTP {$result['status']} для {$operationId}.");
            $status = (int) ($result['status'] ?? 0);

            throw new AvitoException(
                Str::limit(strip_tags($message), 1000),
                'autoload_remote',
                in_array($status, [400, 401, 403, 404, 409, 422, 429], true) ? $status : 502,
                $status === 429 || $status >= 500,
            );
        }

        return $result;
    }

    private function normalizeCategoryTree(mixed $payload): array
    {
        $items = [];
        $walk = function (mixed $value, array $parents = []) use (&$walk, &$items): void {
            if (! is_array($value)) {
                return;
            }
            if (array_is_list($value)) {
                foreach ($value as $item) {
                    $walk($item, $parents);
                }

                return;
            }

            $slug = $value['slug'] ?? $value['node_slug'] ?? $value['nodeSlug'] ?? null;
            $name = $value['name'] ?? $value['title'] ?? $value['label'] ?? null;
            $nextParents = $parents;
            if (is_string($slug) && $slug !== '' && is_string($name) && $name !== '') {
                $hasChildren = collect(['nested', 'children', 'nodes', 'items', 'categories'])
                    ->contains(fn (string $key): bool => is_array($value[$key] ?? null)
                        && $value[$key] !== []);
                $path = [...$parents, $name];
                $items[$slug] = [
                    'slug' => $slug,
                    'name' => $name,
                    'path' => implode(' → ', $path),
                    'is_leaf' => ! $hasChildren,
                ];
                $nextParents = $path;
            }

            foreach ($value as $key => $child) {
                if (in_array($key, ['nested', 'children', 'nodes', 'items', 'categories', 'result', 'data'], true)) {
                    $walk($child, $nextParents);
                }
            }
        };
        $walk($payload);

        return collect($items)->sortBy('path', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    private function normalizeCategoryFields(mixed $payload): array
    {
        $definitions = [];
        foreach ($this->objects($payload) as $object) {
            $xml = is_array($object['xml'] ?? null) ? $object['xml'] : [];
            $key = $object['xml_name'] ?? $object['xmlName'] ?? $object['tag'] ?? $xml['name'] ?? $xml['tag'] ?? null;
            if ($key === null
                && (array_key_exists('required', $object)
                    || array_key_exists('values', $object)
                    || array_key_exists('options', $object))) {
                $key = $object['name'] ?? null;
            }
            if (! is_string($key) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,119}$/', $key)) {
                continue;
            }
            $values = $object['values'] ?? $object['options'] ?? $xml['values'] ?? [];
            $options = collect(is_array($values) ? $values : [])
                ->map(function ($value): ?array {
                    if (is_scalar($value)) {
                        return ['title' => (string) $value, 'value' => (string) $value];
                    }
                    if (! is_array($value)) {
                        return null;
                    }
                    $optionValue = $value['value'] ?? $value['id'] ?? $value['name'] ?? null;
                    if (! is_scalar($optionValue)) {
                        return null;
                    }

                    return [
                        'title' => (string) ($value['title'] ?? $value['label'] ?? $value['name'] ?? $optionValue),
                        'value' => (string) $optionValue,
                    ];
                })
                ->filter()
                ->values()
                ->all();
            $rawType = $object['type'] ?? $xml['type'] ?? 'string';
            $maxValues = $object['max_values'] ?? $object['maxValues'] ?? 1;
            $definitions[$key] = [
                'key' => $key,
                'label' => (string) ($object['label'] ?? $object['title'] ?? $object['name'] ?? $key),
                'description' => (string) ($object['description'] ?? $object['hint'] ?? ''),
                'type' => is_scalar($rawType) ? (string) $rawType : 'string',
                'required' => (bool) ($object['required'] ?? $object['is_required'] ?? $object['isRequired'] ?? false),
                'multiple' => (bool) ($object['multiple'] ?? $object['is_multiple'] ?? $object['isMultiple'] ?? false)
                    || (is_numeric($maxValues) && (int) $maxValues > 1),
                'options' => $options,
                'values_url' => $object['values_url'] ?? $object['valuesUrl'] ?? $xml['values_url'] ?? null,
            ];
        }

        return array_values($definitions);
    }

    private function profilePayload(mixed $payload): array
    {
        foreach ($this->objects($payload) as $object) {
            if (array_key_exists('feeds_data', $object)
                || array_key_exists('report_email', $object)
                || array_key_exists('autoload_enabled', $object)) {
                return $object;
            }
        }

        return is_array($payload) ? $payload : [];
    }

    private function findReportItem(mixed $payload, string $externalId): ?array
    {
        foreach ($this->objects($payload) as $object) {
            $candidate = $object['ad_id'] ?? $object['adId'] ?? $object['external_id'] ?? $object['externalId'] ?? null;
            if ($candidate !== null && (string) $candidate === $externalId) {
                return $object;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function objects(mixed $payload): array
    {
        $objects = [];
        $walk = function (mixed $value) use (&$walk, &$objects): void {
            if (! is_array($value)) {
                return;
            }
            if (! array_is_list($value)) {
                $objects[] = $value;
            }
            foreach ($value as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($payload);

        return $objects;
    }

    private function remoteMeta(array $result): array
    {
        return Arr::only($result, ['request_id', 'status', 'duration_ms', 'headers']);
    }

    private function isProfileUnavailable(AvitoException $exception): bool
    {
        return $exception->httpStatus === 403
            && Str::contains(Str::lower($exception->getMessage()), [
                'получение профиля недоступно',
                'profile retrieval is unavailable',
            ]);
    }
}
