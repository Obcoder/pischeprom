<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Chat;

class TelegramController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
                                            'chat_id' => 'required|string',
                                            'text' => 'required|string'
                                        ]);

        $this->telegramService->sendMessage($validated['chat_id'], $validated['text']);

        return response()->json(['status' => 'Message sent!']);
    }

    public function webhook(Request $request)
    {
        $chat = null;
        // Получаем данные из тела запроса
        $update = $request->getContent();
        $update = json_decode($update, true);

        // Получаем сообщение
        $message = $update['message'] ?? null;
        if ($message) {
            $chatId = $message['chat']['id'];
            $text = $message['text'];

            //Сохраняем chatID в БД
            $chat = Chat::create([
                'numbers' => $chatId,
                                     ]);
            $message = Message::create([
                'content' => $text,
                                       ]);

            // Логируем информацию о сообщении
            Log::info("Received message:", ['chat_id' => $chatId, 'text' => $text]);

            // Ответное сообщение
            //$this->telegram->sendMessage($chatId, "Получено ваше сообщение: " . $text);
        }

        // Возвращаем успешный ответ
        return response()->json(['status' => 'Message received',
                                    'chat' => $chat,
                                    ]);
    }
}
