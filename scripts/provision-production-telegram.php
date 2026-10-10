<?php

declare(strict_types=1);

use App\Services\TelegramWebhookProvisioner;
use GuzzleHttp\Client;

set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, "Telegram webhook provisioning failed; inspect configuration privately.\n");
    exit(1);
});
set_error_handler(static function (): never {
    throw new RuntimeException('Telegram provisioning operation failed.');
});

if (($argc !== 2 && $argc !== 3) || ! is_dir($argv[1]) || ($argc === 3 && $argv[2] !== '--check')) {
    fwrite(STDERR, "Expected an application directory and optional --check.\n");
    exit(2);
}

require rtrim($argv[1], '/').'/vendor/autoload.php';
// Preflight stages this script and its matching service from the selected
// commit, while dependencies still belong to the running application.
require_once dirname(__DIR__).'/app/Services/TelegramWebhookProvisioner.php';

$client = new Client(['connect_timeout' => 10, 'timeout' => 30, 'allow_redirects' => false, 'http_errors' => false]);
$provisioner = new TelegramWebhookProvisioner(static function (string $token, string $method, array $parameters) use ($client): array {
    $response = $client->post('https://api.telegram.org/bot'.$token.'/'.$method, ['json' => $parameters ?: (object) []]);
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException;
    }
    $body = $response->getBody()->read(1048577);
    if (strlen($body) > 1048576) {
        throw new RuntimeException;
    }
    $result = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (! is_array($result)) {
        throw new RuntimeException;
    }

    return $result;
});

$status = $provisioner->provision($argv[1], $argc === 3);
fwrite(STDOUT, match ($status) {
    'checked' => "Telegram webhook preflight passed; no remote or environment changes.\n",
    'secured' => "Telegram webhook secret configured; pending updates retained.\n",
    default => "Telegram webhook not configured; no remote changes.\n",
});
