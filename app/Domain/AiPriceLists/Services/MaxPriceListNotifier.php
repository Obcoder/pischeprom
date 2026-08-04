<?php

namespace App\Domain\AiPriceLists\Services;

use App\Models\PriceListImport;
use App\Services\MaxMessengerService;

class MaxPriceListNotifier
{
    public function __construct(private readonly MaxMessengerService $max) {}

    public function acknowledged(PriceListImport $import): void
    {
        $this->sendOnce($import, 'max_ack_sent_at', 'Прайс-лист получен и поставлен в обработку.');
    }

    public function ready(PriceListImport $import): void
    {
        $this->sendOnce(
            $import,
            'max_ready_sent_at',
            "Прайс-лист обработан. Распознано строк: {$import->items_total}. Результат передан сотруднику на проверку.",
        );
    }

    public function failed(PriceListImport $import, string $message): void
    {
        $this->sendOnce($import, 'max_error_sent_at', $message);
    }

    private function sendOnce(PriceListImport $import, string $flag, string $message): void
    {
        if ($import->source_channel->value !== 'max' || ! config('ai-price-lists.max.send_acknowledgement')) {
            return;
        }

        $metadata = $import->document_metadata ?: [];

        if (isset($metadata[$flag]) || ! $this->max->configured()) {
            return;
        }

        $sent = $import->source_chat_id
            ? $this->max->sendToChat($import->source_chat_id, $message)
            : $this->max->sendToUser($import->source_user_id, $message);

        if ($sent) {
            $metadata[$flag] = now()->toISOString();
            $import->forceFill(['document_metadata' => $metadata])->save();
        }
    }
}
