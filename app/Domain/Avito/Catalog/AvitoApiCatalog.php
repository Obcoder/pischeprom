<?php

namespace App\Domain\Avito\Catalog;

use App\Domain\Avito\Exceptions\AvitoException;
use Illuminate\Support\Arr;

class AvitoApiCatalog
{
    /**
     * The normalized catalog is almost 2 MB on disk and substantially larger
     * as a PHP array. Keep one decoded copy per process instead of rebuilding
     * it for every controller/service resolution.
     *
     * @var array<string, array>
     */
    private static array $decodedSnapshots = [];

    public function snapshot(): array
    {
        $path = (string) config('avito.catalog_path');

        if (! is_file($path) || ! is_readable($path)) {
            throw new AvitoException('Каталог API Avito отсутствует. Выполните avito:catalog-sync.', 'catalog_missing', 503);
        }

        $cacheKey = (string) realpath($path).':'.(string) filemtime($path);

        if (isset(self::$decodedSnapshots[$cacheKey])) {
            return self::$decodedSnapshots[$cacheKey];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! isset($decoded['capabilities'], $decoded['sections'])) {
            throw new AvitoException('Каталог API Avito повреждён.', 'catalog_invalid', 503);
        }

        return self::$decodedSnapshots[$cacheKey] = $decoded;
    }

    public function capabilities(): array
    {
        return $this->snapshot()['capabilities'];
    }

    public function sections(): array
    {
        return $this->snapshot()['sections'];
    }

    public function find(string $id): array
    {
        $capability = Arr::first($this->capabilities(), fn (array $item) => $item['id'] === $id);

        if (! $capability) {
            throw new AvitoException('Функция отсутствует в проверенном каталоге Avito.', 'capability_not_found', 404);
        }

        return $capability;
    }

    public function findOperation(string $section, string $operationId): array
    {
        $capability = Arr::first(
            $this->capabilities(),
            fn (array $item) => $item['section'] === $section && $item['operation_id'] === $operationId
        );

        if (! $capability) {
            throw new AvitoException(
                "Операция {$section}.{$operationId} отсутствует в проверенном каталоге Avito.",
                'capability_not_found',
                503
            );
        }

        return $capability;
    }

    public function metadata(): array
    {
        return Arr::except($this->snapshot(), ['capabilities']);
    }
}
