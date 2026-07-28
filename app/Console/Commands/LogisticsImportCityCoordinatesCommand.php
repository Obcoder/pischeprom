<?php

namespace App\Console\Commands;

use App\Enums\Logistics\CoordinateSource;
use App\Models\City;
use App\Services\Logistics\LogisticsCityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class LogisticsImportCityCoordinatesCommand extends Command
{
    protected $signature = 'logistics:import-city-coordinates
        {file : UTF-8 CSV with city_id or city_name and latitude/longitude columns}
        {--delimiter=; : One-character CSV delimiter}
        {--verify : Mark imported routing points as verified}
        {--enable-matrix : Enable imported cities in the distance matrix}
        {--dry-run : Validate every row without writing}';

    protected $description = 'Safely import explicit routing points for logistics cities from CSV';

    public function handle(LogisticsCityService $cities): int
    {
        $path = realpath((string) $this->argument('file'));
        $delimiter = (string) $this->option('delimiter');

        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            $this->error('CSV file is not readable.');

            return self::INVALID;
        }

        if (mb_strlen($delimiter) !== 1) {
            $this->error('--delimiter must be exactly one character.');

            return self::INVALID;
        }

        try {
            $rows = $this->parse($path, $delimiter);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $prepared = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $city = $this->resolveCity($row);
            $latitude = filter_var($row['latitude'] ?? $row['routing_latitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($row['longitude'] ?? $row['routing_longitude'] ?? null, FILTER_VALIDATE_FLOAT);

            if (! $city) {
                $errors[] = "Line {$line}: city was not found or city_name is ambiguous.";

                continue;
            }
            if ($latitude === false || $latitude < -90 || $latitude > 90
                || $longitude === false || $longitude < -180 || $longitude > 180) {
                $errors[] = "Line {$line}: invalid latitude/longitude.";

                continue;
            }

            $payload = [
                'routing_latitude' => $latitude,
                'routing_longitude' => $longitude,
                'coordinate_source' => CoordinateSource::Import->value,
                'source_reference' => $row['source_reference'] ?? basename($path),
                'mark_verified' => (bool) $this->option('verify'),
            ];

            if ($this->option('enable-matrix')) {
                $payload['is_matrix_enabled'] = true;
            }

            $prepared[] = [$city, $payload];
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error('Nothing was imported because CSV validation is atomic.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: '.count($prepared).' rows are valid. No coordinates were changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($prepared, $cities): void {
            foreach ($prepared as [$city, $payload]) {
                $cities->upsert($city, $payload, null);
            }
        }, 3);

        $this->info('Imported routing points: '.count($prepared).'.');

        return self::SUCCESS;
    }

    private function parse(string $path, string $delimiter): array
    {
        $stream = fopen($path, 'rb');
        $headers = fgetcsv($stream, null, $delimiter);
        if (! is_array($headers)) {
            fclose($stream);
            throw new \RuntimeException('CSV header is missing.');
        }

        $headers = array_map(fn ($header) => mb_strtolower(trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B")), $headers);
        $rows = [];
        while (($values = fgetcsv($stream, null, $delimiter)) !== false) {
            if (count($values) !== count($headers)) {
                fclose($stream);
                throw new \RuntimeException('A CSV row has a different number of columns than the header.');
            }
            if (collect($values)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }
            $rows[] = array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
        }
        fclose($stream);

        if (! in_array('city_id', $headers, true) && ! in_array('city_name', $headers, true)) {
            throw new \RuntimeException('CSV needs city_id or city_name.');
        }

        return $rows;
    }

    private function resolveCity(array $row): ?City
    {
        $cityId = filter_var($row['city_id'] ?? null, FILTER_VALIDATE_INT);
        if ($cityId !== false && $cityId > 0) {
            return City::query()->find((int) $cityId);
        }

        $name = trim((string) ($row['city_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $matches = City::query()->where('name', $name)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
