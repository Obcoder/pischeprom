<?php

namespace App\Domain\AiPriceLists\Services;

use App\Jobs\AiPriceLists\IngestEmailPriceListAttachments;
use App\Models\MailMessage;
use Throwable;

class EmailPriceListIngestionDispatcher
{
    public function register(MailMessage $message): bool
    {
        if (
            ! config('ai-price-lists.enabled')
            || $message->direction !== 'incoming'
            || ! $message->has_attachments
            || ! $message->imap_uid
        ) {
            return false;
        }

        IngestEmailPriceListAttachments::dispatch($message->id)->afterCommit();

        return true;
    }

    public function safeRegister(MailMessage $message): bool
    {
        try {
            return $this->register($message);
        } catch (Throwable $exception) {
            report($exception);
            logger()->error('AI price-list email ingestion dispatch failed', [
                'mail_message_id' => $message->id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
