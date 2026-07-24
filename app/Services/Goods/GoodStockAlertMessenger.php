<?php

namespace App\Services\Goods;

use App\Models\GoodStockAlert;
use App\Models\MaxChat;
use App\Models\MaxMessage;
use App\Services\MaxMessengerService;
use App\Services\Seo\GoodSeoService;

class GoodStockAlertMessenger
{
    public function __construct(
        private readonly MaxMessengerService $max,
        private readonly GoodSeoService $seo,
    ) {}

    public function sendConfirmation(GoodStockAlert $alert, string $token): array
    {
        $alert->loadMissing(['good', 'maxChat']);

        return $this->send(
            $alert->maxChat,
            implode("\n", [
                'Оповещение подключено.',
                "Сообщим, когда товар «{$alert->good->name}» появится в наличии.",
            ]),
            [
                'attachments' => [[
                    'type' => 'inline_keyboard',
                    'payload' => [
                        'buttons' => [[
                            [
                                'type' => 'callback',
                                'text' => 'Отписаться',
                                'payload' => GoodStockAlertManager::CANCEL_PREFIX.$token,
                            ],
                        ]],
                    ],
                ]],
            ]
        );
    }

    public function sendAvailable(GoodStockAlert $alert): array
    {
        $alert->loadMissing(['good.seo', 'maxChat']);
        $url = $this->seo->canonical($alert->good);

        return $this->send(
            $alert->maxChat,
            implode("\n", [
                "Товар «{$alert->good->name}» поступил на склад.",
                'Он снова доступен — посмотреть товар можно по кнопке ниже.',
            ]),
            [
                'attachments' => [[
                    'type' => 'inline_keyboard',
                    'payload' => [
                        'buttons' => [[
                            [
                                'type' => 'link',
                                'text' => 'Открыть товар',
                                'url' => $url,
                            ],
                        ]],
                    ],
                ]],
            ]
        );
    }

    private function send(?MaxChat $chat, string $text, array $payload): array
    {
        if (! $chat) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => 'MAX-чат подписчика не найден.',
            ];
        }

        $target = filled($chat->chat_id)
            ? ['chat_id' => $chat->chat_id]
            : (filled($chat->user_id) ? ['user_id' => $chat->user_id] : []);
        $result = $this->max->sendMessage($target, $text, $payload);
        $providerPayload = $result['data'] ?: [];

        MaxMessage::query()->create([
            'max_chat_id' => $chat->getKey(),
            'max_message_id' => $this->messageId($providerPayload),
            'direction' => MaxMessage::DIRECTION_OUTGOING,
            'status' => $result['ok'] ? 'sent' : 'failed',
            'phone_normalized' => $chat->phone_normalized,
            'chat_id' => $chat->chat_id,
            'user_id' => $chat->user_id,
            'text' => $text,
            'error_message' => $result['ok'] ? null : $result['error'],
            'payload' => $providerPayload,
            'sent_at' => $result['ok'] ? now() : null,
        ]);

        if ($result['ok']) {
            $chat->update([
                'is_active' => true,
                'last_message_at' => now(),
                'last_payload' => $providerPayload,
            ]);
        }

        return $result;
    }

    private function messageId(array $payload): ?string
    {
        foreach ([
            'message_id',
            'message.id',
            'message.mid',
            'message.body.mid',
            'body.mid',
            'mid',
            'id',
        ] as $path) {
            $value = data_get($payload, $path);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
