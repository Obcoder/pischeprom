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

if ($argc !== 2 || ! is_dir($argv[1])) {
    fwrite(STDERR, "Expected an application directory.\n");
    exit(2);
}

require dirname(__DIR__).'/vendor/autoload.php';

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

$status = $provisioner->provision($argv[1]);
fwrite(STDOUT, $status === 'secured' ? "Telegram webhook secret configured; pending updates retained.\n" : "Telegram webhook not configured; no remote changes.\n");
