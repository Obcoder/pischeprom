<?php

use App\Support\Backups\ApplicationSnapshot;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Do not print exceptions: database clients may include credentials or customer data.
ini_set('display_errors', '0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
umask(0077);
try {
    if (! in_array($argv[1] ?? '', ['inspect', 'export'], true) || ! isset($argv[2])) {
        throw new RuntimeException('usage_invalid');
    }
    $snapshot = ApplicationSnapshot::forApplication($argv[2]);
    $result = $argv[1] === 'inspect' ? $snapshot->inspect() : $snapshot->export($argv[3] ?? '');
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    $message = $exception instanceof RuntimeException && preg_match('/\A[a-z_]+\z/', $exception->getMessage())
        ? $exception->getMessage() : 'snapshot_operation_failed';
    fwrite(STDERR, json_encode(['ok' => false, 'error' => $message])."\n");
    exit(1);
}
