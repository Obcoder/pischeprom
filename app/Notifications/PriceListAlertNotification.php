<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceListAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $message,
        public readonly string $actionUrl,
    ) {
        $this->onConnection((string) config('ai-price-lists.queue_connection'));
        $this->onQueue((string) config('ai-price-lists.queue'));
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->line($this->message)
            ->line('AI предлагает данные только для проверки: цены и каталог не изменяются без отдельного подтверждения сотрудника.')
            ->action('Открыть импорт', $this->actionUrl);
    }
}
