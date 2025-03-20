<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use TelegramBot\Api\BotApi;

class TelegramService
{
    protected BotApi $telegram;

    public function __construct()
    {
        $this->telegram = new BotApi(env('TELEGRAM_BOT_TOKEN'));
    }

    public function sendMessage($chatId, $message)
    {
        return $this->telegram->sendMessage($chatId, $message);
    }

    public function getFileUrl(string $fileId): string
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $response = Http::get("https://api.telegram.org/bot{$token}/getFile", [
            'file_id' => $fileId
        ]);

        $result = $response->json();
        if (!isset($result['result']['file_path'])) {
            return '';
        }

        $filePath = $result['result']['file_path'];
        return "https://api.telegram.org/file/bot{$token}/{$filePath}";
    }

}
