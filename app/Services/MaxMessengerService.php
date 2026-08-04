<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaxMessengerService
{
    public function sendToChat(string|int|null $chatId, string $text): bool
    {
        return $this->sendMessage(['chat_id' => $chatId], $text)['ok'];
    }

    public function sendToUser(string|int|null $userId, string $text): bool
    {
        return $this->sendMessage(['user_id' => $userId], $text)['ok'];
    }

    public function configured(): bool
    {
        return $this->token() !== '' && $this->apiUrl() !== '';
    }

    public function uploadAttachment(
        string $content,
        string $fileName,
        string $mimeType = 'application/octet-stream',
        string $type = 'file',
    ): array {
        $type = strtolower(trim($type));
        $fileName = $this->safeFileName($fileName);
        $mimeType = trim($mimeType) ?: 'application/octet-stream';

        if (! in_array($type, ['image', 'video', 'audio', 'file'], true)) {
            return $this->failure('MAX: указан неподдерживаемый тип вложения.');
        }

        if ($content === '') {
            return $this->failure('MAX: вложение пустое.');
        }

        $slot = $this->request('post', '/uploads', ['type' => $type]);

        if (! $slot['ok']) {
            return $slot;
        }

        $uploadUrl = $this->firstString($slot['data'] ?: [], [
            'url',
            'upload_url',
            'data.url',
        ]);

        if (! $this->validUploadUrl($uploadUrl)) {
            return $this->failure('MAX не вернул безопасный HTTPS URL для загрузки вложения.');
        }

        try {
            $response = Http::timeout(max(10, (int) config('services.max.upload_timeout', 120)))
                ->acceptJson()
                ->withOptions($this->tlsOptions())
                ->withHeaders([
                    'Authorization' => $this->token(),
                ])
                ->attach('data', $content, $fileName, [
                    'Content-Type' => $mimeType,
                ])
                ->post($uploadUrl);

            if ($response->failed()) {
                Log::warning('MAX attachment upload failed.', [
                    'endpoint' => $this->safeUploadEndpoint($uploadUrl),
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'size' => strlen($content),
                ]);

                return $this->failure(
                    $response->json('message') ?: $response->body() ?: 'MAX не загрузил вложение.',
                    $response->status(),
                );
            }

            $data = $response->json() ?? [];
            $token = $this->uploadToken($data)
                ?: $this->uploadToken($slot['data'] ?: [])
                ?: $this->uploadRetval($data)
                ?: $this->uploadRetval($slot['data'] ?: []);

            if (! $token) {
                return $this->failure('MAX загрузил файл, но не вернул token вложения.', $response->status());
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'data' => [
                    'type' => $type,
                    'token' => $token,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'size' => strlen($content),
                ],
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('MAX attachment upload crashed.', [
                'endpoint' => $this->safeUploadEndpoint($uploadUrl),
                'message' => $exception->getMessage(),
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'size' => strlen($content),
            ]);

            return $this->failure($this->exceptionMessage($exception));
        }
    }

    public function sendMessage(array $query, string $text, array $payload = []): array
    {
        $query = $this->cleanQuery($query);
        $text = trim($text);

        if (empty($query) || $text === '') {
            return $this->failure('Не указан MAX chat_id/user_id или текст сообщения.');
        }

        return $this->request('post', '/messages', $query, [
            ...$payload,
            'text' => $text,
        ]);
    }

    public function editMessage(string $messageId, string $text, array $payload = []): array
    {
        if (trim($messageId) === '' || trim($text) === '') {
            return $this->failure('Не указан message_id или новый текст сообщения.');
        }

        return $this->request('put', '/messages', [
            'message_id' => $messageId,
        ], [
            ...$payload,
            'text' => $text,
        ]);
    }

    public function deleteMessage(string $messageId): array
    {
        if (trim($messageId) === '') {
            return $this->failure('Не указан message_id.');
        }

        return $this->request('delete', '/messages', [
            'message_id' => $messageId,
        ]);
    }

    public function getChat(string|int $chatId): array
    {
        if (blank($chatId)) {
            return $this->failure('Не указан chat_id.');
        }

        return $this->request('get', "/chats/{$chatId}");
    }

    public function getChats(array $query = []): array
    {
        return $this->request('get', '/chats', $this->cleanQuery($query));
    }

    public function getMe(): array
    {
        return $this->request('get', '/me');
    }

    public function getMessages(array $query = []): array
    {
        return $this->request('get', '/messages', $this->cleanQuery($query));
    }

    public function getMessage(string $messageId): array
    {
        if (trim($messageId) === '') {
            return $this->failure('Не указан MAX message_id.');
        }

        return $this->request('get', '/messages/'.rawurlencode($messageId));
    }

    public function createSubscription(string $url, array $updateTypes = [], ?string $secret = null): array
    {
        $payload = [
            'url' => $url,
            'update_types' => array_values(array_filter($updateTypes)),
        ];

        if (filled($secret)) {
            $payload['secret'] = $secret;
        }

        return $this->request('post', '/subscriptions', [], $payload);
    }

    public function deleteSubscription(string $url): array
    {
        if (trim($url) === '') {
            return $this->failure('Не указан webhook URL.');
        }

        return $this->request('delete', '/subscriptions', [
            'url' => $url,
        ]);
    }

    public function getSubscriptions(): array
    {
        return $this->request('get', '/subscriptions');
    }

    public function answerCallback(string $callbackId, ?string $notification = null): array
    {
        if (trim($callbackId) === '') {
            return $this->failure('Не указан MAX callback_id.');
        }

        return $this->request('post', '/answers', [
            'callback_id' => $callbackId,
        ], array_filter([
            'notification' => $notification,
        ], fn ($value) => filled($value)));
    }

    public function botDeepLink(string $payload): ?string
    {
        $payload = trim($payload);

        if ($payload === '' || strlen($payload) > 128) {
            return null;
        }

        $url = $this->botUrl();

        if (! $url) {
            return null;
        }

        $encodedPayload = rawurlencode($payload);
        $urlWithPayload = preg_replace(
            '/([?&])start=[^&#]*/',
            '$1start='.$encodedPayload,
            $url,
            1,
            $replacementCount,
        );

        if ($replacementCount > 0) {
            return $urlWithPayload;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        if (str_ends_with($url, '?') || str_ends_with($url, '&')) {
            $separator = '';
        }

        return $url.$separator.'start='.$encodedPayload;
    }

    private function request(string $method, string $path, array $query = [], array $payload = []): array
    {
        if (! $this->configured()) {
            return $this->failure('MAX не настроен: добавьте MAX_ACCESS_TOKEN или MAX_BOT_TOKEN.');
        }

        try {
            $request = Http::baseUrl($this->apiUrl())
                ->timeout(10)
                ->acceptJson()
                ->asJson()
                ->withOptions($this->tlsOptions())
                ->withHeaders([
                    'Authorization' => $this->token(),
                ]);

            if (! empty($query)) {
                $request = $request->withQueryParameters($query);
            }

            /** @var Response $response */
            $response = match (strtolower($method)) {
                'get' => $request->get($path),
                'put' => $request->put($path, $payload),
                'delete' => $request->delete($path),
                default => $request->post($path, $payload),
            };

            if ($response->failed()) {
                Log::warning('MAX API request failed.', [
                    'method' => $method,
                    'path' => $path,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'query' => $query,
                ]);

                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'data' => $response->json(),
                    'error' => $response->json('message') ?: $response->body(),
                ];
            }

            $data = $response->json() ?? [];

            if (data_get($data, 'success') === false) {
                $error = data_get($data, 'message') ?: 'MAX API returned success=false.';

                Log::warning('MAX API rejected a successful HTTP response.', [
                    'method' => $method,
                    'path' => $path,
                    'status' => $response->status(),
                    'response' => $data,
                    'query' => $query,
                ]);

                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'data' => $data,
                    'error' => $error,
                ];
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'data' => $data,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('MAX API request crashed.', [
                'method' => $method,
                'path' => $path,
                'message' => $exception->getMessage(),
                'query' => $query,
            ]);

            return $this->failure($this->exceptionMessage($exception));
        }
    }

    private function cleanQuery(array $query): array
    {
        return collect($query)
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    private function failure(string $message, int $status = 0): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => $message,
        ];
    }

    private function token(): string
    {
        return trim((string) config('services.max.access_token'));
    }

    private function apiUrl(): string
    {
        return rtrim((string) config('services.max.api_url', 'https://platform-api2.max.ru'), '/');
    }

    private function tlsOptions(): array
    {
        if (! (bool) config('services.max.ssl_verify', true)) {
            return ['verify' => false];
        }

        $caBundle = trim((string) config('services.max.ca_bundle'));

        return ['verify' => $caBundle !== '' ? $caBundle : true];
    }

    private function exceptionMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (
            str_contains($message, 'SSL certificate problem')
            || str_contains($message, 'SSL CA bundle not found')
        ) {
            $caBundle = trim((string) config('services.max.ca_bundle'));
            $bundleHint = $caBundle !== '' ? " ({$caBundle})" : '';

            return 'MAX SSL: приложение не доверяет сертификату platform-api2.max.ru. '
                .'Проверьте CA-bundle'.$bundleHint.' или установку сертификатов Минцифры. '
                .$message;
        }

        return $message;
    }

    private function botUrl(): ?string
    {
        $configuredUrl = trim((string) config('services.max.bot_url'));

        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        $username = trim((string) config('services.max.bot_username'));

        if ($username === '') {
            $username = (string) Cache::remember(
                'max.bot_username',
                now()->addHours(6),
                function (): ?string {
                    if (! $this->configured()) {
                        return null;
                    }

                    $result = $this->getMe();
                    $remoteUsername = $result['ok']
                        ? data_get($result['data'], 'username')
                        : null;

                    return filled($remoteUsername) ? (string) $remoteUsername : null;
                }
            );
        }

        if ($username === '') {
            return null;
        }

        if (str_starts_with($username, 'http://') || str_starts_with($username, 'https://')) {
            return $username;
        }

        return 'https://max.ru/'.ltrim($username, '@');
    }

    private function firstString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function uploadToken(array $payload): ?string
    {
        return $this->firstString($payload, [
            'token',
            'payload.token',
            'data.token',
            'retval.token',
            'result.token',
        ]);
    }

    private function uploadRetval(array $payload): ?string
    {
        $retval = data_get($payload, 'retval');

        return is_string($retval) && trim($retval) !== ''
            ? trim($retval)
            : null;
    }

    private function validUploadUrl(?string $url): bool
    {
        if (! $url || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && filled(parse_url($url, PHP_URL_HOST));
    }

    private function safeUploadEndpoint(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        return ($scheme && $host)
            ? "{$scheme}://{$host}{$path}"
            : '[invalid upload URL]';
    }

    private function safeFileName(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', trim($fileName)));
        $fileName = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $fileName) ?: '';

        return $fileName !== '' ? $fileName : 'attachment.bin';
    }
}
