<?php

namespace App\Domain\Avito\Catalog;

use App\Domain\Avito\Exceptions\AvitoException;
use Illuminate\Support\Arr;

class AvitoApiCatalog
{
    private ?array $snapshot = null;

    public function snapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        $path = (string) config('avito.catalog_path');

        if (! is_file($path) || ! is_readable($path)) {
            throw new AvitoException('Каталог API Avito отсутствует. Выполните avito:catalog-sync.', 'catalog_missing', 503);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! isset($decoded['capabilities'], $decoded['sections'])) {
            throw new AvitoException('Каталог API Avito повреждён.', 'catalog_invalid', 503);
        }

        return $this->snapshot = $decoded;
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

    public function metadata(): array
    {
        return Arr::except($this->snapshot(), ['capabilities']);
    }
}
