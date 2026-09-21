<?php

use App\Support\Backups\ApplicationSnapshot;

require dirname(__DIR__, 2).'/vendor/autoload.php';

ini_set('display_errors', '0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
umask(0077);
try {
    $result = ApplicationSnapshot::verify($argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? null);
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    $message = $exception instanceof RuntimeException && preg_match('/\A[a-z_]+\z/', $exception->getMessage())
        ? $exception->getMessage() : 'restore_verification_failed';
    fwrite(STDERR, json_encode(['ok' => false, 'error' => $message])."\n");
    exit(1);
}
