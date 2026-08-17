<?php

namespace App\Services\CommercialOffers;

use App\Models\MailingContact;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class UnisenderGoClient
{
    private const MAX_RESPONSE_BYTES = 1_048_576;

    public function __construct(private readonly SafeMailProviderIdentifier $identifiers) {}

    public function sendEmail(
        array $message,
        UnisenderRequestProfile $profile = UnisenderRequestProfile::LegacyManual,
    ): UnisenderSendResult {
        $message = $this->normalizeSendMessage($message);
        $recipients = Arr::get($message, 'recipients', []);
        if (count($recipients) > 500) {
            throw new RuntimeException('Unisender Go email/send.json accepts at most 500 recipients per request.');
        }

        if (count($recipients) > 1 && blank(Arr::get($message, 'idempotence_key'))) {
            throw new RuntimeException('Bulk Unisender sends require message.idempotence_key.');
        }

        $response = $this->post(
            'email/send.json',
            ['message' => $message],
            $profile,
            ambiguousOnConnection: $profile === UnisenderRequestProfile::OutreachZeroRetry,
        );
        $jobId = $this->safeProviderId(
            Arr::get($response->payload, 'job_id')
                ?: Arr::get($response->payload, 'result.job_id')
                ?: Arr::get($response->payload, 'message.job_id')
        );
        $failedEmails = Arr::get(
            $response->payload,
            'failed_emails',
            Arr::get($response->payload, 'result.failed_emails', [])
        );

        if (! $jobId) {
            throw new MailProviderException(
                MailProviderSafeErrorCode::AmbiguousAcceptance,
                $response->httpStatusCategory,
                $response->safeRequestId,
                $response->responseHash,
                ambiguousAcceptance: true,
            );
        }

        return new UnisenderSendResult(
            successful: true,
            jobId: (string) $jobId,
            failedEmails: $this->normalizeFailedEmails(is_array($failedEmails) ? $failedEmails : []),
            httpStatusCategory: $response->httpStatusCategory,
            safeRequestId: $response->safeRequestId,
            responseHash: $response->responseHash,
            requestProfile: $profile,
        );
    }

    public function setTemplate(array $template): array
    {
        $response = $this->post('template/set.json', $template);
        $templateId = $this->safeProviderId(
            Arr::get($response->payload, 'template_id')
            ?: Arr::get($response->payload, 'result.template_id')
            ?: Arr::get($response->payload, 'id')
        );

        return array_filter([
            'status' => 'accepted',
            'template_id' => $templateId,
            '_meta' => $response->safeMetadata(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function setWebhook(array $config): array
    {
        $response = $this->post('webhook/set.json', $config);

        return ['status' => 'accepted', '_meta' => $response->safeMetadata()];
    }

    public function setSuppression(string $email, string $cause, ?DateTimeInterface $created = null): array
    {
        $response = $this->post('suppression/set.json', array_filter([
            'email' => $email,
            'cause' => $cause,
            'created' => $created?->format(DateTimeInterface::ATOM),
        ], fn ($value) => $value !== null));

        return ['status' => 'accepted', '_meta' => $response->safeMetadata()];
    }

    public function getSuppression(string $email): array
    {
        $response = $this->post('suppression/get.json', ['email' => $email]);

        return [
            'status' => 'ok',
            'found' => ! empty($response->payload['items'] ?? $response->payload['result'] ?? []),
            '_meta' => $response->safeMetadata(),
        ];
    }

    public function listSuppression(array $filters = []): array
    {
        $response = $this->post('suppression/list.json', $filters);
        $items = $response->payload['items'] ?? Arr::get($response->payload, 'result.items', []);

        return [
            'status' => 'ok',
            'items_count' => is_array($items) ? count($items) : 0,
            '_meta' => $response->safeMetadata(),
        ];
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

        return $this->verifyWebhookRawBodyWithPayload($rawBody, $payload);
    }

    public function verifyWebhookRawBodyWithPayload(string $rawBody, array $payload): bool
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '' || trim($rawBody) === '') {
            return false;
        }

        $authValues = $this->findAuthValues($payload);
        if (count($authValues) !== 1 || preg_match('/\A[a-f0-9]{32}\z/i', $authValues[0]) !== 1) {
            return false;
        }
        $auth = mb_strtolower($authValues[0]);

        $escapedKey = json_encode($apiKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $replacementCount = 0;
        $bodyForHash = preg_replace(
            '/("auth"\s*:\s*)"(?:\\\\.|[^"\\\\])*"/u',
            '$1'.$escapedKey,
            $rawBody,
            1,
            $replacementCount,
        );

        if (! is_string($bodyForHash) || $replacementCount !== 1) {
            return false;
        }

        return hash_equals(md5($bodyForHash), $auth);
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

    private function post(
        string $endpoint,
        array $payload,
        ?UnisenderRequestProfile $profile = null,
        bool $ambiguousOnConnection = false,
    ): UnisenderApiResponse {
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

        if (($profile?->transportRetries() ?? 0) > 0) {
            $request->retry(
                $profile->transportRetries(),
                300,
                fn ($exception) => $exception instanceof ConnectionException,
                throw: false,
            );
        }

        try {
            $response = $request->post($url, $payload);
        } catch (ConnectionException $exception) {
            $isTimeout = str_contains(mb_strtolower($exception->getMessage()), 'timed out');
            $code = $ambiguousOnConnection
                ? MailProviderSafeErrorCode::AmbiguousAcceptance
                : ($isTimeout ? MailProviderSafeErrorCode::Timeout : MailProviderSafeErrorCode::ConnectionFailed);

            throw new MailProviderException(
                $code,
                ambiguousAcceptance: $ambiguousOnConnection,
            );
        }

        $rawResponse = $response->body();
        $responseHash = hash('sha256', $rawResponse);
        $httpStatusCategory = $this->httpStatusCategory($response->status());
        $safeRequestId = $this->safeRequestId($response->header('X-Request-ID') ?: $response->header('X-Trace-ID'));

        if (strlen($rawResponse) > self::MAX_RESPONSE_BYTES) {
            throw new MailProviderException(
                MailProviderSafeErrorCode::MalformedResponse,
                $httpStatusCategory,
                $safeRequestId,
                $responseHash,
            );
        }

        try {
            $json = $rawResponse === '' ? [] : json_decode($rawResponse, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MailProviderException(
                MailProviderSafeErrorCode::MalformedResponse,
                $httpStatusCategory,
                $safeRequestId,
                $responseHash,
            );
        }

        if (! is_array($json)) {
            throw new MailProviderException(
                MailProviderSafeErrorCode::MalformedResponse,
                $httpStatusCategory,
                $safeRequestId,
                $responseHash,
            );
        }

        Log::info('Unisender Go API request', [
            'provider' => 'unisender_go',
            'endpoint' => $endpoint,
            'http_status_category' => $httpStatusCategory,
            'safe_request_id' => $safeRequestId,
            'response_hash' => $responseHash,
        ]);

        if (! $response->successful()) {
            throw new MailProviderException(
                $this->errorCodeForStatus($response->status()),
                $httpStatusCategory,
                $safeRequestId,
                $responseHash,
                safeDetailCode: $this->safeProviderDetailCode($json),
            );
        }

        return new UnisenderApiResponse(
            payload: $json,
            httpStatus: $response->status(),
            httpStatusCategory: $httpStatusCategory,
            responseHash: $responseHash,
            safeRequestId: $safeRequestId,
        );
    }

    private function normalizeSendMessage(array $message): array
    {
        $message['recipients'] = collect((array) ($message['recipients'] ?? []))
            ->map(function (array $recipient) {
                if (isset($recipient['metadata']) && is_array($recipient['metadata'])) {
                    $recipient['metadata'] = $this->stringMap($recipient['metadata']);
                }

                if (isset($recipient['substitutions']) && is_array($recipient['substitutions'])) {
                    $recipient['substitutions'] = $this->stringMap($recipient['substitutions']);
                }

                return $recipient;
            })
            ->all();

        foreach (['global_metadata', 'global_substitutions'] as $key) {
            if (isset($message[$key]) && is_array($message[$key])) {
                $message[$key] = $this->stringMap($message[$key]);
            }
        }

        return $message;
    }

    private function stringMap(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif ($value instanceof DateTimeInterface) {
                $value = $value->format(DateTimeInterface::ATOM);
            } elseif (is_scalar($value)) {
                $value = (string) $value;
            } else {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            $result[(string) $key] = $value;
        }

        return $result;
    }

    private function normalizeFailedEmails(array $failedEmails): array
    {
        $normalized = [];
        foreach ($failedEmails as $entry) {
            $email = is_string($entry)
                ? $entry
                : (is_array($entry) ? ($entry['email'] ?? $entry['address'] ?? '') : '');
            $email = MailingContact::normalizeEmail((string) $email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $normalized[] = [
                    'email' => $email,
                    'reason_code' => MailProviderSafeErrorCode::PermissionDenied->value,
                ];
            }
        }

        return $normalized;
    }

    private function apiKey(): string
    {
        return (string) config('services.unisender_go.api_key', '');
    }

    private function findAuthValues(array $payload): array
    {
        $values = [];
        if (isset($payload['auth']) && is_string($payload['auth'])) {
            $values[] = $payload['auth'];
        }

        foreach ($payload as $key => $value) {
            if ($key === 'auth') {
                continue;
            }

            if (is_array($value)) {
                array_push($values, ...$this->findAuthValues($value));
            }
        }

        return $values;
    }

    private function errorCodeForStatus(int $status): MailProviderSafeErrorCode
    {
        return match (true) {
            $status === 401 => MailProviderSafeErrorCode::AuthenticationFailed,
            $status === 403 => MailProviderSafeErrorCode::PermissionDenied,
            $status === 429 => MailProviderSafeErrorCode::RateLimited,
            $status >= 500 => MailProviderSafeErrorCode::Provider5xx,
            default => MailProviderSafeErrorCode::PermissionDenied,
        };
    }

    private function safeProviderDetailCode(array $payload): ?string
    {
        $message = mb_strtolower((string) (
            Arr::get($payload, 'message')
            ?: Arr::get($payload, 'error')
            ?: ''
        ));

        return match (true) {
            str_contains($message, 'tracking domain'),
            str_contains($message, 'custom backend domain') => 'tracking_configuration_required',
            str_contains($message, 'free_tier') && str_contains($message, 'checked') => 'checked_recipient_required',
            str_contains($message, 'no valid recipients') => 'no_valid_recipients',
            str_contains($message, 'message size limits'),
            str_contains($message, 'exceeded google') => 'message_too_large',
            default => null,
        };
    }

    private function httpStatusCategory(int $status): string
    {
        return intdiv(max(100, min(599, $status)), 100).'xx';
    }

    private function safeRequestId(mixed $value): ?string
    {
        return is_string($value) ? $this->identifiers->normalize($value) : null;
    }

    private function safeProviderId(mixed $value): ?string
    {
        return $this->identifiers->normalize($value);
    }
}
