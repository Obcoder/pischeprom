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

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
                                            'chat_id' => 'required|string',
                                            'text' => 'required|string'
                                        ]);

        try {
            // Сначала сохраняем в БД
            Log::info("Сохранение сообщения в БД", ['chat_id' => $validated['chat_id'], 'text' => $validated['text']]);
            $chat = Chat::where('numbers', '=', $validated['chat_id'])->firstOrFail();
            $message = Message::create([
                                           'chat_id' => $chat->id,
                                           'content' => $validated['text'],
                                       ]);
            Log::info("Сообщение успешно сохранено", ['id' => $message->id]);
            // Отправка сообщения через сервис
            $this->telegramService->sendMessage($validated['chat_id'], $validated['text']);
        } catch (\Exception $e) {
            // Логирование ошибки
            Log::error("Ошибка при отправке сообщения: " . $e->getMessage());
//            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }


    public function webhook(Request $request)
    {
        // Получаем данные из тела запроса
        $update = $request->getContent();
        $update = json_decode($update, true);

        $update_id = $update['update_id'] ?? null;
        // Получаем сообщение
        $message = $update['message'] ?? null;
        if ($message) {
            $message_id = $message['message_id'] ?? null;
            $date = $message['date'] ?? null;
            $chatId = $message['chat']['id'];
            $first_name = $message['chat']['first_name'] ?? null;
            $last_name = $message['chat']['last_name'] ?? null;
            $text = $message['text'] ?? null; // Проверяем текст
            $photo = $message['photo'] ?? null; // Массив фото
            $caption = $message['caption'] ?? null; // Подпись к фото

            //Сохраняем chatID в БД
            $chat = Chat::firstOrCreate(['numbers' => $chatId]);

            if ($text) { // Добавляем проверку
                $message = Message::create(
                    [
                        'content' => $text,
                        'chat_id' => $chat->id,
                        'message_id' => $message_id,
                        'date' => $date,
                        'update_id' => $update_id,
                    ]
                );
            }

            // Логируем информацию о сообщении
            //Log::info("Received message:", ['chat_id' => $chatId, 'text' => $text]);

            // Ответное сообщение
            //$this->telegram->sendMessage($chatId, "Получено ваше сообщение: " . $text);
        }

        if ($photo) {
            // Берем самое большое фото (последний элемент массива)
            $largestPhoto = array_pop($photo);
            $fileId = $largestPhoto['file_id'];

            // Сохраняем в БД
            Message::create([
                                'content' => "[Фото] $fileId",
                                'chat_id' => $chat->id,
                            ]);

            // Получаем прямую ссылку на файл
            $fileUrl = $this->telegramService->getFileUrl($fileId);

            Log::info("Фото получено: $fileUrl");
        }

        Log::info('Пришли данные из Telegram:', $update);
        // Возвращаем успешный ответ
        //return response()->json(['status' => 'Message received']);
    }
}
