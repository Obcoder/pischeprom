<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaxMessengerService
{
    public function sendToChat(string|int|null $chatId, string $text): bool
    {
        return $this->send(['chat_id' => $chatId], $text);
    }

    public function sendToUser(string|int|null $userId, string $text): bool
    {
        return $this->send(['user_id' => $userId], $text);
    }

    private function send(array $query, string $text): bool
    {
        $token = trim((string) config('services.max.access_token'));
        $apiUrl = rtrim((string) config('services.max.api_url', 'https://platform-api2.max.ru'), '/');
        $query = collect($query)
            ->filter(fn ($value) => filled($value))
            ->all();

        if ($token === '' || $apiUrl === '' || empty($query) || trim($text) === '') {
            return false;
        }

        try {
            $response = Http::baseUrl($apiUrl)
                ->timeout(10)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'Authorization' => $token,
                ])
                ->withQueryParameters($query)
                ->post('/messages', [
                    'text' => $text,
                ]);

            if ($response->failed()) {
                Log::warning('MAX message was not delivered.', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'query' => $query,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('MAX message delivery failed.', [
                'message' => $exception->getMessage(),
                'query' => $query,
            ]);

            return false;
        }
    }
}
