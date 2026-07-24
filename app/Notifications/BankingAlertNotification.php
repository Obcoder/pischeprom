<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BankingAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $message,
        public readonly ?string $actionUrl = null,
    ) {
        $this->onConnection((string) config('banking.queue_connection', 'redis'));
        $this->onQueue((string) config('banking.queue', 'banking'));
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->line($this->message)
            ->line('Уведомление не содержит токены, полные банковские реквизиты или исходный payload.');

        if ($this->actionUrl) {
            $mail->action('Открыть раздел «Банк»', $this->actionUrl);
        }

        return $mail;
    }
}
