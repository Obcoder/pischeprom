<?php

namespace App\Services\Goods;

use App\Mail\GoodInquiryReceivedMail;
use App\Models\GoodInquiry;
use App\Services\Mail\MailboxRegistry;
use App\Services\MaxMessengerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GoodInquiryNotificationService
{
    public const MANAGER_EMAIL = 'com@food-server.ru';

    public function __construct(
        private readonly MailboxRegistry $mailboxes,
        private readonly MaxMessengerService $max,
    ) {}

    public function deliver(int $inquiryId): void
    {
        // Atomic lease: queue workers and the recovery scheduler may overlap.
        $claimed = GoodInquiry::query()->whereKey($inquiryId)
            ->whereNotNull('next_notification_at')
            ->where('next_notification_at', '<=', now())
            ->update([
                'next_notification_at' => now()->addMinutes(15),
                'notification_attempts' => DB::raw('notification_attempts + 1'),
            ]);

        if (! $claimed) {
            return;
        }

        $inquiry = GoodInquiry::query()->with('order')->findOrFail($inquiryId);

        if (! $inquiry->email_notified_at) {
            try {
                $this->sendEmail($inquiry);
                $inquiry->forceFill(['email_notified_at' => now()])->save();
            } catch (Throwable $exception) {
                $this->logFailure($inquiry, 'email', $exception);
            }
        }

        $targets = array_values(array_unique(array_map('strval', config('services.max.manager_chat_ids', []))));
        $deliveredTo = $inquiry->max_delivered_to ?? [];

        foreach (array_diff($targets, $deliveredTo) as $chatId) {
            try {
                if ($this->max->sendToChat($chatId, $this->managerMessage($inquiry))) {
                    $deliveredTo[] = $chatId;
                    $inquiry->forceFill(['max_delivered_to' => $deliveredTo])->save();
                }
            } catch (Throwable $exception) {
                $this->logFailure($inquiry, 'max', $exception);
            }
        }

        $complete = $inquiry->email_notified_at && array_diff($targets, $deliveredTo) === [];
        $inquiry->forceFill([
            'next_notification_at' => $complete ? null : now()->addMinutes(min(60, 2 ** min(6, $inquiry->notification_attempts))),
        ])->save();

        if ($inquiry->order && ($inquiry->email_notified_at || $deliveredTo !== []) && ! $inquiry->order->notified_at) {
            $inquiry->order->forceFill(['notified_at' => now()])->save();
        }
    }

    private function sendEmail(GoodInquiry $inquiry): void
    {
        $mailbox = $this->mailboxes->find(self::MANAGER_EMAIL) ?: $this->mailboxes->default();
        $mailer = $mailbox ? $this->mailboxes->registerMailer($mailbox) : config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        if (! app()->environment('testing') && in_array($transport, [null, 'log', 'array', 'failover'], true)) {
            throw new RuntimeException('A delivery-capable mail transport is required for public inquiries.');
        }

        $mail = new GoodInquiryReceivedMail($inquiry);
        if ($mailbox) {
            $mail->from($mailbox['address'], $mailbox['from_name']);
        }

        Mail::mailer($mailer)->to(self::MANAGER_EMAIL)->send($mail);
    }

    private function managerMessage(GoodInquiry $inquiry): string
    {
        $unit = $inquiry->price_unit === 'kg' ? 'кг' : 'упак.';

        return implode("\n", array_filter([
            $inquiry->kindLabel().' '.$inquiry->number,
            $inquiry->good_name,
            'Количество: '.$inquiry->quantity.' упак.'.($inquiry->package_weight ? ' / '.($inquiry->quantity * $inquiry->package_weight).' кг' : ''),
            'Цена на сайте: '.($inquiry->listed_price !== null ? $inquiry->listed_price.' '.$inquiry->currency_code.' / '.$unit : 'уточняется'),
            $inquiry->proposed_price !== null ? 'Предложение: '.$inquiry->proposed_price.' '.$inquiry->currency_code.' / '.$unit : null,
            $inquiry->kind === 'bargain' ? 'Условия: '.$inquiry->scenarioLabel() : null,
            'Клиент: '.$inquiry->customer_name,
            'Email: '.$inquiry->customer_email,
            $inquiry->customer_phone ? 'Телефон: '.$inquiry->customer_phone : null,
            $inquiry->company ? 'Компания: '.$inquiry->company : null,
            'Доставка: '.Str::limit(implode(', ', array_filter([$inquiry->delivery_city, $inquiry->delivery_address])) ?: 'уточнить', 300),
            'Ответить: '.($inquiry->preferred_contact === 'max' ? 'MAX '.$inquiry->max_contact : 'по email'),
            $inquiry->comment ? 'Комментарий: '.Str::limit($inquiry->comment, 700) : null,
            $inquiry->order ? 'Заказ: '.$inquiry->order->number.' '.route('Ameise.orders.show', $inquiry->order) : null,
            Str::limit($inquiry->good_url, 500),
            'Данные клиента не проверены. Подтвердите цену, наличие и доставку.',
        ]));
    }

    private function logFailure(GoodInquiry $inquiry, string $channel, Throwable $exception): void
    {
        Log::warning('Good inquiry notification failed; retry scheduled.', [
            'inquiry_id' => $inquiry->id,
            'channel' => $channel,
            'exception' => $exception::class,
        ]);
    }
}
