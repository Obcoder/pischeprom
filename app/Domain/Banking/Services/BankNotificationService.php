<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Events\BankSyncFailed;
use App\Domain\Banking\Events\BankTransactionChanged;
use App\Domain\Banking\Events\ReceivablePaymentStatusChanged;
use App\Models\Entity;
use App\Models\User;
use App\Notifications\BankingAlertNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

class BankNotificationService
{
    public function paymentStatusChanged(ReceivablePaymentStatusChanged $event): void
    {
        $labels = [
            'partially_paid' => 'Продажа частично оплачена',
            'paid' => 'Продажа полностью оплачена',
            'overpaid' => 'По продаже обнаружена переплата',
            'unpaid' => 'Оплата продажи была пересчитана',
        ];
        $subject = $labels[$event->currentStatus] ?? 'Изменён статус оплаты продажи';
        $message = sprintf(
            'Продажа #%d: статус оплаты изменён с %s на %s.',
            $event->sale->id,
            $event->previousStatus,
            $event->currentStatus,
        );
        $this->notify($this->entityRecipients($event->sale->entity), $subject, $message);
    }

    public function syncFailed(BankSyncFailed $event): void
    {
        $this->notify(
            $this->administrators(),
            'Ошибка синхронизации банка',
            "Синхронизация завершилась ошибкой. Correlation ID: {$event->run->correlation_id}."
        );
    }

    public function connectionAttention(BankConnectionRequiresAttention $event): void
    {
        $reason = str_replace('_', ' ', $event->reason);
        $this->notify(
            $this->administrators(),
            'Банковское подключение требует внимания',
            "Причина: {$reason}. Подключение #{$event->connection->id}."
        );
    }

    public function transactionChanged(BankTransactionChanged $event): void
    {
        if (! array_intersect($event->changedFields, ['status', 'amount', 'direction'])) {
            return;
        }

        $this->notify(
            $this->entityRecipients($event->transaction->entity),
            'Банковская операция изменена',
            "Импортированная операция #{$event->transaction->id} изменена банком и требует проверки."
        );
    }

    private function notify(Collection $users, string $subject, string $message): void
    {
        if ($users->isEmpty()) {
            return;
        }

        Notification::send(
            $users->unique('id')->values(),
            new BankingAlertNotification(
                $subject,
                $message,
                rtrim((string) config('app.url'), '/').'/Ameise/bank'
            )
        );
    }

    private function entityRecipients(?Entity $entity): Collection
    {
        $users = $entity?->users()
            ->wherePivot('status', 'active')
            ->orderByDesc('entity_user.is_primary')
            ->get() ?? collect();

        return $users->isNotEmpty() ? $users : $this->administrators();
    }

    private function administrators(): Collection
    {
        try {
            return User::role('admin', 'crm')->where('status', '!=', 'blocked')->get();
        } catch (Throwable) {
            return collect();
        }
    }
}
