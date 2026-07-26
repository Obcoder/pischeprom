<?php

namespace App\Services\Mail;

use App\Jobs\SendIncomingMailMaxNotificationJob;
use App\Models\MailMessage;
use App\Models\MailMessageMaxDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IncomingMailMaxNotificationDispatcher
{
    public function register(MailMessage $message): Collection
    {
        if (! $this->shouldNotify($message)) {
            return collect();
        }

        return collect($this->targets())
            ->map(function (array $target) use ($message): ?MailMessageMaxDelivery {
                $existing = $this->existingMessageDelivery($message, $target);

                if ($existing) {
                    return $existing;
                }

                $delivery = MailMessageMaxDelivery::query()->firstOrCreate([
                    'mail_message_id' => $message->id,
                    'target_type' => $target['type'],
                    'target_id' => $target['id'],
                ], [
                    'status' => MailMessageMaxDelivery::STATUS_PENDING,
                ]);

                if ($delivery->wasRecentlyCreated) {
                    $this->dispatch($delivery);
                }

                return $delivery;
            })
            ->filter()
            ->values();
    }

    public function dispatch(MailMessageMaxDelivery $delivery): void
    {
        if ($delivery->status === MailMessageMaxDelivery::STATUS_SENT) {
            return;
        }

        $pending = SendIncomingMailMaxNotificationJob::dispatch($delivery->id);
        $queue = trim((string) config('services.max.mail_notifications.queue', 'mail-notifications'));

        if ($queue !== '') {
            $pending->onQueue($queue);
        }
    }

    public function safeRegister(MailMessage $message): void
    {
        try {
            $this->register($message);
        } catch (\Throwable $exception) {
            Log::warning('Incoming mail MAX notification was not queued.', [
                'mail_message_id' => $message->id,
                'mailbox' => $message->mailbox,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldNotify(MailMessage $message): bool
    {
        if (! (bool) config('services.max.mail_notifications.enabled', false)) {
            return false;
        }

        if ($message->direction !== 'incoming') {
            return false;
        }

        $mailboxes = collect(config('services.max.mail_notifications.mailboxes', []))
            ->map(fn ($mailbox) => mb_strtolower(trim((string) $mailbox)));

        if (! $mailboxes->contains(mb_strtolower(trim((string) $message->mailbox)))) {
            return false;
        }

        $folders = collect(config('services.max.mail_notifications.folders', ['INBOX']))
            ->map(fn ($folder) => mb_strtolower(trim((string) $folder)));

        if (! $folders->contains(mb_strtolower(trim((string) $message->folder)))) {
            return false;
        }

        $maxAgeHours = (int) config('services.max.mail_notifications.max_message_age_hours', 0);

        if (
            $maxAgeHours > 0
            && $message->message_date
            && $message->message_date->lt(now()->subHours($maxAgeHours))
        ) {
            return false;
        }

        return ! empty($this->targets());
    }

    private function targets(): array
    {
        $targets = [];

        foreach (config('services.max.mail_notifications.chat_ids', []) as $chatId) {
            if (filled($chatId)) {
                $targets[] = [
                    'type' => MailMessageMaxDelivery::TARGET_CHAT,
                    'id' => trim((string) $chatId),
                ];
            }
        }

        foreach (config('services.max.mail_notifications.user_ids', []) as $userId) {
            if (filled($userId)) {
                $targets[] = [
                    'type' => MailMessageMaxDelivery::TARGET_USER,
                    'id' => trim((string) $userId),
                ];
            }
        }

        return collect($targets)
            ->unique(fn (array $target) => "{$target['type']}:{$target['id']}")
            ->values()
            ->all();
    }

    private function existingMessageDelivery(
        MailMessage $message,
        array $target,
    ): ?MailMessageMaxDelivery {
        if (blank($message->message_id)) {
            return null;
        }

        return MailMessageMaxDelivery::query()
            ->where('target_type', $target['type'])
            ->where('target_id', $target['id'])
            ->whereHas('mailMessage', function ($query) use ($message): void {
                $query
                    ->where('mailbox', $message->mailbox)
                    ->where('direction', 'incoming')
                    ->where('message_id', $message->message_id);
            })
            ->first();
    }
}
