<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TelegramBot\Api\BotApi;

class TelegramService
{
    protected BotApi $telegram;

    public function __construct()
    {
        $this->telegram = new BotApi((string) config('services.telegram.bot_token'));
    }

    public function sendMessage($chatId, $message)
    {
        try {
            // Отправка сообщения в Telegram
            $this->telegram->sendMessage($chatId, $message);
        } catch (\Exception $e) {
            Log::error('Telegram message delivery failed.', ['exception' => get_class($e)]);
            // Provider exceptions can contain a URL with the bot token.
            throw new \RuntimeException('Telegram message delivery failed.');
        }
    }

    public function getFileUrl(string $fileId): string
    {
        $token = (string) config('services.telegram.bot_token');
        try {
            $response = Http::get("https://api.telegram.org/bot{$token}/getFile", [
                'file_id' => $fileId,
            ]);
        } catch (\Throwable) {
            throw new \RuntimeException('Telegram file lookup failed.');
        }

        $result = $response->json();
        if (! isset($result['result']['file_path'])) {
            return '';
        }

        $filePath = $result['result']['file_path'];

        return "https://api.telegram.org/file/bot{$token}/{$filePath}";
    }
}
