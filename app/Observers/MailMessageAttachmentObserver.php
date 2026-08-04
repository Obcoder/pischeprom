<?php

namespace App\Observers;

use App\Domain\AiPriceLists\Services\PriceListIngestionService;
use App\Models\MailMessageAttachment;
use Throwable;

class MailMessageAttachmentObserver
{
    public function created(MailMessageAttachment $attachment): void
    {
        try {
            app(PriceListIngestionService::class)->ingestMailAttachment($attachment);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
