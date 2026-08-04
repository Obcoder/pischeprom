<?php

namespace App\Domain\AiPriceLists\Services;

use App\Jobs\AiPriceLists\IngestMaxPriceListAttachment;
use App\Models\MaxWebhookEvent;

class MaxPriceListWebhookDispatcher
{
    public function dispatch(MaxWebhookEvent $event): int
    {
        if (! config('ai-price-lists.enabled') || $event->update_type !== 'message_created') {
            return 0;
        }

        $attachments = data_get($event->payload, 'message.body.attachments', data_get($event->payload, 'body.attachments', []));

        if (! is_array($attachments)) {
            return 0;
        }

        $count = 0;

        $limit = max(1, min(20, (int) config('ai-price-lists.max.max_attachments_per_message', 10)));

        foreach (array_slice(array_values($attachments), 0, $limit) as $index => $attachment) {
            if (! is_array($attachment) || ! in_array(data_get($attachment, 'type'), ['file', 'image'], true)) {
                continue;
            }

            IngestMaxPriceListAttachment::dispatch($event->id, $index)
                ->onConnection((string) config('ai-price-lists.queue_connection'))
                ->onQueue((string) config('ai-price-lists.queue'))
                ->afterCommit();
            $count++;
        }

        return $count;
    }
}
