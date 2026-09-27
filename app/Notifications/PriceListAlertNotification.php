<?php

namespace App\Notifications;

use App\Domain\AiPriceLists\Services\PriceListRuntimePolicy;
use App\Models\User;
use App\Services\Auth\StaffAccess;
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
        public readonly ?string $requiredPermission = null,
    ) {
        $this->onConnection((string) config('ai-price-lists.queue_connection'));
        $this->onQueue((string) config('ai-price-lists.queue'));
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->shouldSend($notifiable, 'mail') ? ['mail'] : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        // Laravel calls this again for serialized jobs with precomputed channels.
        // Old jobs lack an authorization scope and must never be resumed blindly.
        if (! PriceListRuntimePolicy::notificationsEnabled() || $channel !== 'mail'
            || ! ($this->requiredPermission ?? null) || ! $notifiable instanceof User
            || ! app(StaffAccess::class)->allows($notifiable) || ! $notifiable->hasVerifiedEmail()
            || blank($notifiable->email)) {
            return false;
        }

        try {
            return $notifiable->hasRole('admin', 'crm')
                || $notifiable->hasPermissionTo($this->requiredPermission, 'crm');
        } catch (\Throwable) {
            return false;
        }
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
