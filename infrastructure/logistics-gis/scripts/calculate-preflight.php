#!/usr/bin/env php
<?php

declare(strict_types=1);

$input = json_decode(stream_get_contents(STDIN), true);
if (! is_array($input)) {
    fwrite(STDERR, "Preflight calculator expects a JSON object on stdin.\n");
    exit(64);
}

$mode = (string) ($input['mode'] ?? 'full');
if (! in_array($mode, ['download', 'valhalla', 'planetiler', 'verify', 'activate', 'full'], true)) {
    fwrite(STDERR, "Unsupported preflight mode.\n");
    exit(64);
}

$integer = static fn (mixed $value, int $default = 0): int => is_numeric($value)
    ? max(0, (int) round((float) $value))
    : $default;
$number = static fn (mixed $value, float $default = 0.0): float => is_numeric($value)
    ? (float) $value
    : $default;
$strings = static fn (mixed $value): array => is_array($value)
    ? array_values(array_unique(array_filter($value, 'is_string')))
    : [];

$pbfBytes = max(
    $integer($input['pbf']['remote_size_bytes'] ?? null),
    $integer($input['pbf']['current_size_bytes'] ?? null),
);
$activeGraphBytes = $integer($input['existing']['active_graph_bytes'] ?? null);
$activePmtilesBytes = $integer($input['existing']['active_pmtiles_bytes'] ?? null);
$graphMultiplier = max(1.0, $number($input['thresholds']['valhalla_graph_pbf_multiplier'] ?? null, 3.0));
$pmtilesMultiplier = max(0.5, $number($input['thresholds']['pmtiles_pbf_multiplier'] ?? null, 1.5));
$planetilerDiskMultiplier = max(5.0, $number($input['thresholds']['planetiler_disk_multiplier'] ?? null, 10.0));
$planetilerRamMultiplier = max(0.5, $number($input['thresholds']['planetiler_ram_multiplier'] ?? null, 0.5));
$valhallaRamMultiplier = max(0.5, $number($input['thresholds']['valhalla_ram_multiplier'] ?? null, 0.75));
$appDiskReserve = $integer($input['thresholds']['app_disk_reserve_bytes'] ?? null, 20 * 1024 ** 3);
$appRamReserve = $integer($input['thresholds']['app_ram_reserve_bytes'] ?? null, 2 * 1024 ** 3);
$graphEstimate = max($activeGraphBytes, (int) ceil($pbfBytes * $graphMultiplier));
$pmtilesEstimate = max($activePmtilesBytes, (int) ceil($pbfBytes * $pmtilesMultiplier));
$planetilerScratch = (int) ceil($pbfBytes * $planetilerDiskMultiplier);
$downloadWorkspace = $pbfBytes * 2;
$valhallaOperationRam = max(4 * 1024 ** 3, (int) ceil($pbfBytes * $valhallaRamMultiplier));
$planetilerOperationRam = (int) ceil($pbfBytes * $planetilerRamMultiplier);

$operationDisk = match ($mode) {
    'download' => $downloadWorkspace,
    'valhalla' => $graphEstimate,
    'planetiler' => $planetilerScratch + $pmtilesEstimate,
    'verify' => 1024 ** 3,
    'activate' => 1024 ** 3,
    default => $downloadWorkspace + $graphEstimate + $planetilerScratch + $pmtilesEstimate,
};
$operationRam = match ($mode) {
    'download', 'activate' => 512 * 1024 ** 2,
    'valhalla' => $valhallaOperationRam,
    'planetiler' => $planetilerOperationRam,
    'verify' => 4 * 1024 ** 3,
    default => max($valhallaOperationRam, $planetilerOperationRam),
};
$diskRequired = $operationDisk + $appDiskReserve;
$ramRequired = $operationRam + $appRamReserve;

$failures = $strings($input['failures'] ?? null);
$warnings = $strings($input['warnings'] ?? null);
$notices = $strings($input['notices'] ?? null);
$diskFree = $integer($input['disk']['free_bytes'] ?? null);
$diskInodes = $integer($input['disk']['free_inodes'] ?? null);
$ramAvailable = $integer($input['memory']['available_bytes'] ?? null);
$swapTotal = $integer($input['memory']['swap_total_bytes'] ?? null);
$logicalCores = max(1, $integer($input['cpu']['logical_cores'] ?? null, 1));
$loadOne = $number($input['cpu']['load_average']['one'] ?? null);
$loadPerCore = $loadOne / $logicalCores;
$warnLoad = max(0.1, $number($input['thresholds']['warn_load_per_core'] ?? null, 0.7));
$failLoad = max($warnLoad, $number($input['thresholds']['fail_load_per_core'] ?? null, 1.0));

if ($pbfBytes <= 0 && $mode !== 'activate') {
    $failures[] = 'Не удалось определить фактический размер полного russia-latest.osm.pbf.';
}
if (! (bool) ($input['disk']['writable'] ?? false)) {
    $failures[] = 'Staging-каталог отсутствует или недоступен для записи.';
}
if ($diskFree < $diskRequired) {
    $failures[] = 'Недостаточно свободного диска для операции и обязательного резерва приложения.';
} elseif ($diskRequired > 0 && $diskFree < (int) ceil($diskRequired * 1.2)) {
    $warnings[] = 'Запас диска после операции будет менее 20% от рассчитанного требования.';
}
if ($diskInodes <= 100000) {
    $failures[] = 'Недостаточно свободных inode для staging-графа и временных данных.';
}
if ($ramAvailable < $ramRequired) {
    $failures[] = 'Недостаточно доступной RAM с учётом резерва Laravel/MySQL/Redis/Nginx.';
} elseif ($ramRequired > 0 && $ramAvailable < (int) ceil($ramRequired * 1.2)) {
    $warnings[] = 'Запас RAM после операции будет менее 20% от рассчитанного требования.';
}
if ($loadPerCore >= $failLoad) {
    $failures[] = 'Текущая production-нагрузка слишком высока для старта GIS-операции.';
} elseif ($loadPerCore >= $warnLoad) {
    $warnings[] = 'Текущая нагрузка повышена; production-сборку запускать нельзя без отдельного окна.';
}
if ($swapTotal === 0 && $ramAvailable < (int) ceil($ramRequired * 1.5)) {
    $warnings[] = 'Swap отсутствует, а запас доступной RAM невелик.';
}
if ($mode === 'activate' && ! (bool) ($input['valhalla']['healthy'] ?? false)) {
    $failures[] = 'Действующий Valhalla не проходит healthcheck; активация заблокирована.';
} elseif (in_array($mode, ['download', 'valhalla', 'planetiler', 'verify', 'full'], true)
    && (bool) ($input['valhalla']['current_required'] ?? true)
    && ! (bool) ($input['valhalla']['healthy'] ?? false)) {
    $failures[] = 'Действующий Valhalla не проходит healthcheck; сначала восстановите production routing.';
}
if (in_array($mode, ['planetiler', 'full'], true)
    && $integer($input['components']['java_major'] ?? null) < 21) {
    $failures[] = 'Planetiler требует Java 21 или новее.';
}

$failures = array_values(array_unique($failures));
$warnings = array_values(array_unique($warnings));
$result = $failures !== [] ? 'FAIL' : ($warnings !== [] ? 'WARN' : 'PASS');
$exitCode = match ($result) {
    'PASS' => 0,
    'WARN' => 2,
    default => 3,
};

$output = [
    'result' => $result,
    'exit_code' => $exitCode,
    'mode' => $mode,
    'checked_at' => (string) ($input['checked_at'] ?? gmdate('c')),
    'system' => $input['system'] ?? [],
    'cpu' => [
        ...($input['cpu'] ?? []),
        'load_per_core' => round($loadPerCore, 4),
    ],
    'memory' => $input['memory'] ?? [],
    'disk' => $input['disk'] ?? [],
    'pbf' => $input['pbf'] ?? [],
    'existing' => $input['existing'] ?? [],
    'components' => $input['components'] ?? [],
    'valhalla' => $input['valhalla'] ?? [],
    'requirements' => [
        'disk_required_bytes' => $diskRequired,
        'ram_required_bytes' => $ramRequired,
        'operation_disk_bytes' => $operationDisk,
        'operation_ram_bytes' => $operationRam,
        'download_workspace_bytes' => $downloadWorkspace,
        'estimated_valhalla_graph_bytes' => $graphEstimate,
        'estimated_pmtiles_bytes' => $pmtilesEstimate,
        'planetiler_scratch_bytes' => $planetilerScratch,
        'app_disk_reserve_bytes' => $appDiskReserve,
        'app_ram_reserve_bytes' => $appRamReserve,
    ],
    'failures' => $failures,
    'warnings' => $warnings,
    'notices' => $notices,
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
