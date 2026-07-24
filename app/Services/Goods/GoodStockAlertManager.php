<?php

namespace App\Services\Goods;

use App\Jobs\SendGoodStockAlertConfirmationJob;
use App\Jobs\SendGoodStockAlertNotificationJob;
use App\Models\Good;
use App\Models\GoodStockAlert;
use App\Models\MaxChat;
use App\Models\User;
use App\Services\MaxMessengerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GoodStockAlertManager
{
    public const START_PREFIX = 'stock_';

    public const CANCEL_PREFIX = 'stock_cancel_';

    public function __construct(
        private readonly GoodStockService $stock,
        private readonly MaxMessengerService $max,
    ) {}

    public function createPending(Good $good, ?User $user = null): array
    {
        if (! $this->max->configured()) {
            throw new RuntimeException(
                'Интеграция MAX не настроена. Укажите MAX_ACCESS_TOKEN.'
            );
        }

        if (
            ! $this->stock->canSubscribe($good)
            || $this->stock->isInStock($good)
        ) {
            throw new RuntimeException('Товар уже есть в наличии.');
        }

        $token = Str::random(48);
        $deepLink = $this->max->botDeepLink(self::START_PREFIX.$token);

        if (! $deepLink) {
            throw new RuntimeException(
                'Интеграция MAX не настроена. Укажите MAX_BOT_URL или MAX_BOT_USERNAME.'
            );
        }

        $this->stock->ensureUnavailableState($good);

        $alert = GoodStockAlert::query()->create([
            'good_id' => $good->getKey(),
            'user_id' => $user?->getKey(),
            'start_token_hash' => $this->tokenHash($token),
            'status' => GoodStockAlert::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        return [
            'alert' => $alert,
            'deep_link' => $deepLink,
        ];
    }

    public function handleMaxUpdate(?string $updateType, array $payload, ?MaxChat $chat): void
    {
        if (! $chat) {
            return;
        }

        if ($updateType === 'bot_started') {
            $this->activateFromPayload($payload, $chat);

            return;
        }

        if ($updateType === 'message_callback') {
            $this->cancelFromCallback($payload, $chat);

            return;
        }

        if (in_array($updateType, ['bot_stopped', 'dialog_removed'], true)) {
            GoodStockAlert::query()
                ->where('max_chat_id', $chat->getKey())
                ->whereIn('status', [
                    GoodStockAlert::STATUS_ACTIVE,
                    GoodStockAlert::STATUS_FAILED,
                ])
                ->update([
                    'status' => GoodStockAlert::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);
        }
    }

    private function activateFromPayload(array $payload, MaxChat $chat): void
    {
        $startPayload = $this->firstString($payload, [
            'payload',
            'start_payload',
            'update.payload',
        ]);

        if (! $startPayload || ! str_starts_with($startPayload, self::START_PREFIX)) {
            return;
        }

        $token = substr($startPayload, strlen(self::START_PREFIX));

        if ($token === '') {
            return;
        }

        $alertId = DB::transaction(function () use ($token, $chat): ?int {
            $alert = GoodStockAlert::query()
                ->where('start_token_hash', $this->tokenHash($token))
                ->lockForUpdate()
                ->first();

            if (! $alert || $alert->status !== GoodStockAlert::STATUS_PENDING) {
                return null;
            }

            if ($alert->expires_at?->isPast()) {
                $alert->update([
                    'status' => GoodStockAlert::STATUS_EXPIRED,
                ]);

                return null;
            }

            GoodStockAlert::query()
                ->where('good_id', $alert->good_id)
                ->where('max_chat_id', $chat->getKey())
                ->whereKeyNot($alert->getKey())
                ->whereIn('status', [
                    GoodStockAlert::STATUS_ACTIVE,
                    GoodStockAlert::STATUS_FAILED,
                ])
                ->update([
                    'status' => GoodStockAlert::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

            $alert->update([
                'max_chat_id' => $chat->getKey(),
                'status' => GoodStockAlert::STATUS_ACTIVE,
                'activated_at' => now(),
                'expires_at' => null,
                'error_message' => null,
            ]);

            if ($alert->user_id && filled($chat->chat_id)) {
                User::query()
                    ->whereKey($alert->user_id)
                    ->update([
                        'max_chat_id' => $chat->chat_id,
                    ]);
            }

            return $alert->getKey();
        }, 3);

        if (! $alertId) {
            return;
        }

        SendGoodStockAlertConfirmationJob::dispatch($alertId, $token);

        $alert = GoodStockAlert::query()->find($alertId);

        if ($alert && $this->stock->isInStock($alert->good_id)) {
            SendGoodStockAlertNotificationJob::dispatch($alertId);
        }
    }

    private function cancelFromCallback(array $payload, MaxChat $chat): void
    {
        $callbackPayload = $this->firstString($payload, [
            'callback.payload',
            'callback.button.payload',
            'message.callback.payload',
            'update.callback.payload',
        ]);
        $callbackId = $this->firstString($payload, [
            'callback.callback_id',
            'callback.id',
            'update.callback.callback_id',
        ]);

        if (! $callbackPayload || ! str_starts_with($callbackPayload, self::CANCEL_PREFIX)) {
            return;
        }

        $token = substr($callbackPayload, strlen(self::CANCEL_PREFIX));
        $cancelled = false;

        if ($token !== '') {
            $alert = GoodStockAlert::query()
                ->where('start_token_hash', $this->tokenHash($token))
                ->where('max_chat_id', $chat->getKey())
                ->whereIn('status', [
                    GoodStockAlert::STATUS_ACTIVE,
                    GoodStockAlert::STATUS_FAILED,
                ])
                ->first();

            if ($alert) {
                $alert->update([
                    'status' => GoodStockAlert::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);
                $cancelled = true;
            }
        }

        if ($callbackId) {
            $this->max->answerCallback(
                $callbackId,
                $cancelled
                    ? 'Оповещение о поступлении отменено.'
                    : 'Эта подписка уже не активна.'
            );
        }
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function firstString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
