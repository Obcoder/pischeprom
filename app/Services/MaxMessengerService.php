<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
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

    public function getMessages(array $query = []): array
    {
        return $this->request('get', '/messages', $this->cleanQuery($query));
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

            return [
                'ok' => true,
                'status' => $response->status(),
                'data' => $response->json() ?? [],
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
}
