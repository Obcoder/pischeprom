<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Dotenv\Dotenv;
use RuntimeException;
use Throwable;

final class TelegramWebhookProvisioner
{
    /** @param Closure(string, string, array): array $request */
    public function __construct(private readonly Closure $request) {}

    public function provision(string $directory, bool $checkOnly = false): string
    {
        try {
            return $this->configure(rtrim($directory, '/').'/.env', $checkOnly);
        } catch (Throwable) {
            // Transport exceptions can contain a bot token in their request URL.
            throw new RuntimeException('Telegram webhook provisioning failed; configuration was not disclosed.');
        }
    }

    private function configure(string $path, bool $checkOnly): string
    {
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException;
        }
        $handle = fopen($path, $checkOnly ? 'r' : 'r+');
        if (! is_resource($handle) || ! flock($handle, $checkOnly ? LOCK_SH : LOCK_EX)) {
            throw new RuntimeException;
        }

        try {
            $contents = stream_get_contents($handle);
            $metadata = fstat($handle);
            if (! is_string($contents) || ! is_array($metadata)) {
                throw new RuntimeException;
            }
            $env = Dotenv::parse($contents);
            $token = trim($env['TELEGRAM_BOT_TOKEN'] ?? '');
            if ($token === '') {
                return 'disabled';
            }
            if (! preg_match('/\A[0-9]+:[A-Za-z0-9_-]+\z/D', $token)) {
                throw new RuntimeException;
            }

            $response = ($this->request)($token, 'getWebhookInfo', []);
            if (($response['ok'] ?? null) !== true || ! is_array($response['result'] ?? null)) {
                throw new RuntimeException;
            }
            $info = $response['result'];
            if (($info['url'] ?? null) === '') {
                return 'disabled';
            }
            $parameters = $this->parameters($info, $env['APP_URL'] ?? '');
            if ($checkOnly) {
                return 'checked';
            }
            $secret = $env['TELEGRAM_WEBHOOK_SECRET'] ?? '';
            if (! preg_match('/\A[A-Za-z0-9_-]{1,256}\z/D', $secret)) {
                $secret = bin2hex(random_bytes(32));
                $this->persistSecret($path, $contents, $env, $metadata, $secret);
            }
            $parameters['secret_token'] = $secret;
            $result = ($this->request)($token, 'setWebhook', $parameters);
            if (($result['ok'] ?? null) !== true || ($result['result'] ?? null) !== true) {
                throw new RuntimeException;
            }

            return 'secured';
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function parameters(array $info, string $appUrl): array
    {
        $known = ['url', 'has_custom_certificate', 'pending_update_count', 'ip_address', 'last_error_date', 'last_error_message', 'last_synchronization_error_date', 'max_connections', 'allowed_updates'];
        if (array_diff(array_keys($info), $known) !== []
            || ($info['has_custom_certificate'] ?? null) !== false
            || ! is_string($info['url'] ?? null)) {
            throw new RuntimeException;
        }
        $webhook = parse_url($info['url']);
        $application = parse_url($appUrl);
        if (! is_array($webhook) || ! is_array($application)
            || ($webhook['scheme'] ?? '') !== 'https'
            || ($application['scheme'] ?? '') !== 'https'
            || ($webhook['path'] ?? '') !== '/api/webhook'
            || ($webhook['port'] ?? 443) !== ($application['port'] ?? 443)
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($webhook)) !== []
            || $this->host($webhook['host'] ?? '') !== $this->host($application['host'] ?? '')) {
            throw new RuntimeException;
        }

        $parameters = ['url' => $info['url'], 'drop_pending_updates' => false];
        if (array_key_exists('max_connections', $info)) {
            if (! is_int($info['max_connections']) || $info['max_connections'] < 1 || $info['max_connections'] > 100) {
                throw new RuntimeException;
            }
            $parameters['max_connections'] = $info['max_connections'];
        }
        if (array_key_exists('allowed_updates', $info)) {
            if (! is_array($info['allowed_updates']) || ! array_is_list($info['allowed_updates'])) {
                throw new RuntimeException;
            }
            foreach ($info['allowed_updates'] as $update) {
                if (! is_string($update) || ! preg_match('/\A[a-z_]+\z/D', $update)) {
                    throw new RuntimeException;
                }
            }
            $parameters['allowed_updates'] = $info['allowed_updates'];
        }

        return $parameters;
    }

    private function host(string $host): string
    {
        $ascii = idn_to_ascii(strtolower(rtrim($host, '.')), IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (! is_string($ascii) || $ascii === '') {
            throw new RuntimeException;
        }

        return $ascii;
    }

    private function persistSecret(string $path, string $contents, array $before, array $metadata, string $secret): void
    {
        if (strpbrk($before['TELEGRAM_WEBHOOK_SECRET'] ?? '', "\r\n") !== false) {
            throw new RuntimeException;
        }
        $count = 0;
        $output = preg_replace('/^[\t ]*(?:export[\t ]+)?TELEGRAM_WEBHOOK_SECRET[\t ]*=[^\r\n]*(?=\r?$)/m', 'TELEGRAM_WEBHOOK_SECRET='.$secret, $contents, -1, $count);
        if (! is_string($output)) {
            throw new RuntimeException;
        }
        if ($count === 0) {
            $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
            $output .= ($output !== '' && ! str_ends_with($output, "\n") ? $newline : '').'TELEGRAM_WEBHOOK_SECRET='.$secret.$newline;
        }
        $after = Dotenv::parse($output);
        $key = ['TELEGRAM_WEBHOOK_SECRET' => true];
        if (($after['TELEGRAM_WEBHOOK_SECRET'] ?? null) !== $secret
            || array_diff_key($before, $key) !== array_diff_key($after, $key)) {
            throw new RuntimeException;
        }
        $temporary = tempnam(dirname($path), '.telegram-env-');
        if (! is_string($temporary)) {
            throw new RuntimeException;
        }
        try {
            if (! chmod($temporary, 0600)
                || (fileowner($temporary) !== $metadata['uid'] && ! chown($temporary, $metadata['uid']))
                || (filegroup($temporary) !== $metadata['gid'] && ! chgrp($temporary, $metadata['gid']))
                || file_put_contents($temporary, $output, LOCK_EX) !== strlen($output)
                || is_link($path) || file_get_contents($path) !== $contents
                || ! rename($temporary, $path)) {
                throw new RuntimeException;
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
