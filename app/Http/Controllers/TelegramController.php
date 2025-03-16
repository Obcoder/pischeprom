<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;

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
}
