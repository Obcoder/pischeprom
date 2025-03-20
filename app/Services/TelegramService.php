<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use TelegramBot\Api\BotApi;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected BotApi $telegram;

    public function __construct()
    {
        $this->telegram = new BotApi(env('TELEGRAM_BOT_TOKEN'));
    }

    public function sendMessage($chatId, $message)
    {
        try {
            // Отправка сообщения в Telegram
            $this->telegram->sendMessage($chatId, $message);
        } catch (\Exception $e) {
            Log::error("Ошибка при отправке сообщения: " . $e->getMessage());
            throw $e;  // Перебрасываем исключение для дальнейшей обработки в контроллере
        }
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
