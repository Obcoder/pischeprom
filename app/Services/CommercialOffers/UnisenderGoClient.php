<?php

namespace App\Services\CommercialOffers;

use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class UnisenderGoClient
{
    public function sendEmail(array $message): UnisenderSendResult
    {
        $recipients = Arr::get($message, 'recipients', []);
        if (count($recipients) > 500) {
            throw new RuntimeException('Unisender Go email/send.json accepts at most 500 recipients per request.');
        }

        if (count($recipients) > 1 && blank(Arr::get($message, 'idempotence_key'))) {
            throw new RuntimeException('Bulk Unisender sends require message.idempotence_key.');
        }

        $payload = ['message' => $message];
        $response = $this->post('email/send.json', $payload, retry: true);
        $jobId = Arr::get($response, 'job_id') ?: Arr::get($response, 'result.job_id') ?: Arr::get($response, 'message.job_id');
        $failedEmails = Arr::get($response, 'failed_emails', Arr::get($response, 'result.failed_emails', []));

        return new UnisenderSendResult(
            successful: true,
            jobId: $jobId ? (string) $jobId : null,
            response: $response,
            failedEmails: is_array($failedEmails) ? $failedEmails : [],
        );
    }

    public function setTemplate(array $template): array
    {
        return $this->post('template/set.json', $template);
    }

    public function setWebhook(array $config): array
    {
        return $this->post('webhook/set.json', $config);
    }

    public function setSuppression(string $email, string $cause, ?DateTimeInterface $created = null): array
    {
        return $this->post('suppression/set.json', array_filter([
            'email' => $email,
            'cause' => $cause,
            'created' => $created?->format(DateTimeInterface::ATOM),
        ], fn ($value) => $value !== null));
    }

    public function getSuppression(string $email): array
    {
        return $this->post('suppression/get.json', ['email' => $email]);
    }

    public function listSuppression(array $filters = []): array
    {
        return $this->post('suppression/list.json', $filters);
    }

    public function verifyWebhookRawBody(string $rawBody): bool
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '' || trim($rawBody) === '') {
            return false;
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return false;
        }

        $auth = $this->findAuth($payload);
        if (! is_string($auth) || $auth === '') {
            return false;
        }

        $escapedKey = json_encode($apiKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $bodyForHash = preg_replace('/("auth"\s*:\s*)"(?:\\\\.|[^"\\\\])*"/u', '$1'.$escapedKey, $rawBody, 1);

        if (! is_string($bodyForHash)) {
            return false;
        }

        return hash_equals(md5($bodyForHash), $auth);
    }

    public function parseWebhook(string $rawBody): UnisenderWebhookPayload
    {
        $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        $events = [];

        foreach ((array) Arr::get($payload, 'events_by_user', []) as $group) {
            foreach ((array) Arr::get($group, 'events', []) as $event) {
                if (is_array($event)) {
                    $events[] = $event;
                }
            }
        }

        return new UnisenderWebhookPayload($payload, $events);
    }

    public function defaultWebhookConfig(): array
    {
        return [
            'url' => config('services.unisender_go.webhook_url'),
            'status' => 'active',
            'event_format' => 'json_post',
            'delivery_info' => (bool) config('services.unisender_go.webhook_delivery_info', true) ? 1 : 0,
            'single_event' => 0,
            'max_parallel' => (int) config('services.unisender_go.webhook_max_parallel', 10),
            'events' => [
                'email_status' => ['delivered', 'opened', 'clicked', 'unsubscribed', 'subscribed', 'soft_bounced', 'hard_bounced', 'spam'],
                'spam_block' => ['*'],
            ],
        ];
    }

    private function post(string $endpoint, array $payload, bool $retry = false): array
    {
        if (! (bool) config('services.unisender_go.enabled', false)) {
            throw new RuntimeException('Unisender Go is disabled. Set UNISENDER_GO_ENABLED=true.');
        }

        if ($this->apiKey() === '') {
            throw new RuntimeException('UNISENDER_GO_API_KEY is not configured.');
        }

        $url = rtrim((string) config('services.unisender_go.api_base'), '/').'/'.ltrim($endpoint, '/');
        $request = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-KEY' => $this->apiKey(),
                'X-Mailer' => 'pischeprom-commercial-offers',
            ])
            ->withOptions(['query' => ['platform' => 'pischeprom.laravel']])
            ->timeout((int) config('services.unisender_go.timeout', 20));

        if ($retry) {
            $request->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false);
        }

        $response = $request->post($url, $payload);
        $json = $response->json() ?: [];

        Log::info('Unisender Go API request', [
            'provider' => 'unisender_go',
            'endpoint' => $endpoint,
            'http_code' => $response->status(),
            'job_id' => Arr::get($json, 'job_id') ?: Arr::get($json, 'result.job_id'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException((string) (Arr::get($json, 'message') ?: Arr::get($json, 'error') ?: 'Unisender Go request failed.'));
        }

        return is_array($json) ? $json : [];
    }

    private function apiKey(): string
    {
        return (string) config('services.unisender_go.api_key', '');
    }

    private function findAuth(array $payload): ?string
    {
        if (isset($payload['auth']) && is_string($payload['auth'])) {
            return $payload['auth'];
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $auth = $this->findAuth($value);
                if ($auth !== null) {
                    return $auth;
                }
            }
        }

        return null;
    }
}
