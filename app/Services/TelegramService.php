<?php
namespace App\Services;

use TelegramBot\Api\BotApi;

class TelegramService
{
    protected $telegram;

    public function __construct()
    {
        $this->telegram = new BotApi(env('TELEGRAM_BOT_TOKEN'));
    }

    public function sendMessage($chatId, $message)
    {
        return $this->telegram->sendMessage($chatId, $message);
    }
}
