<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Models\PriceListImport;
use App\Models\User;
use App\Notifications\PriceListAlertNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

class PriceListStatusNotificationService
{
    public function statusChanged(PriceListImport $import, PriceListStatus $status): void
    {
        $notification = $this->notificationFor($import, $status);

        if (! $notification) {
            return;
        }

        $flag = 'internal_notification_'.$status->value.'_sent_at';
        $metadata = $import->document_metadata ?: [];

        if (isset($metadata[$flag])) {
            return;
        }

        try {
            $recipients = $this->recipients($import, $notification['permission']);

            if ($recipients->isEmpty()) {
                return;
            }

            $metadata[$flag] = now()->toISOString();
            $import->forceFill(['document_metadata' => $metadata])->save();
            Notification::send($recipients, new PriceListAlertNotification(
                $notification['subject'],
                $notification['message'],
                rtrim((string) config('app.url'), '/').'/Ameise/ai/price-lists/'.$import->uuid,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array{subject:string,message:string,permission:string}|null */
    private function notificationFor(PriceListImport $import, PriceListStatus $status): ?array
    {
        $file = $import->safe_name ?: 'прайс-лист';

        return match ($status) {
            PriceListStatus::SupplierUnresolved => [
                'subject' => 'Не определён поставщик прайс-листа',
                'message' => "Для файла «{$file}» нужно вручную выбрать поставщика.",
                'permission' => 'ai_price_lists.assign_supplier',
            ],
            PriceListStatus::ReviewRequired => [
                'subject' => 'Новый прайс-лист требует проверки',
                'message' => "Распознавание файла «{$file}» завершено: строк {$import->items_total}.",
                'permission' => 'ai_price_lists.review',
            ],
            PriceListStatus::ReadyToApply => [
                'subject' => 'Прайс-лист готов к применению',
                'message' => "Все обязательные решения по файлу «{$file}» подтверждены.",
                'permission' => 'ai_price_lists.apply',
            ],
            PriceListStatus::Failed, PriceListStatus::UnsupportedFormat, PriceListStatus::Quarantined => [
                'subject' => 'Ошибка обработки прайс-листа',
                'message' => "Файл «{$file}»: ".($import->error_message ?: $status->label()).'.',
                'permission' => 'ai_price_lists.process',
            ],
            PriceListStatus::Applied, PriceListStatus::PartiallyApplied => [
                'subject' => $status === PriceListStatus::Applied ? 'Прайс-лист применён' : 'Прайс-лист применён частично',
                'message' => "Для файла «{$file}» записано закупочных цен: {$import->items_applied}.",
                'permission' => 'ai_price_lists.apply',
            ],
            default => null,
        };
    }

    private function recipients(PriceListImport $import, string $permission): Collection
    {
        $users = collect();

        if ($import->reviewed_by || $import->applied_by) {
            $users = User::query()
                ->whereIn('id', array_values(array_filter([$import->reviewed_by, $import->applied_by])))
                ->where('status', '!=', 'blocked')
                ->get();
        }

        try {
            $users = $users
                ->merge(User::permission($permission, 'crm')->where('status', '!=', 'blocked')->get())
                ->merge(User::role('admin', 'crm')->where('status', '!=', 'blocked')->get());
        } catch (Throwable) {
            // A fresh installation may not have seeded roles yet.
        }

        return $users->filter(fn (User $user) => filled($user->email))->unique('id')->values();
    }
}
