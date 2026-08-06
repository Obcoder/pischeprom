<?php

namespace App\Domain\AiPriceLists\Services;

use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Services\Mail\YandexMailboxService;
use RuntimeException;
use Throwable;

class EmailPriceListAttachmentCollector
{
    public function __construct(
        private readonly YandexMailboxService $mailbox,
        private readonly PriceListDocumentClassifier $classifier,
        private readonly PriceListIngestionService $ingestion,
    ) {}

    /**
     * @return array{
     *     available: int,
     *     eligible: int,
     *     saved: int,
     *     ingested: int,
     *     failed: int,
     *     skipped: array<string, int>
     * }
     */
    public function collect(MailMessage $message): array
    {
        $report = [
            'available' => 0,
            'eligible' => 0,
            'saved' => 0,
            'ingested' => 0,
            'failed' => 0,
            'skipped' => [],
        ];

        if (! config('ai-price-lists.enabled')) {
            $report['skipped']['module_disabled'] = 1;

            return $report;
        }

        if ($message->direction !== 'incoming' || ! $message->has_attachments || ! $message->imap_uid) {
            $report['skipped']['message_not_eligible'] = 1;

            return $report;
        }

        $collection = $this->mailbox->storeAttachmentsMatching(
            $message,
            fn (array $metadata): ?string => $this->classifier->mailAttachmentRejectionReason($metadata, $message),
        );
        $report['available'] = $collection['available'];
        $report['eligible'] = $collection['eligible'];
        $report['failed'] = $collection['failed'];
        $report['skipped'] = $collection['skipped'];
        $firstFailure = null;

        foreach ($collection['saved_attachment_ids'] as $attachmentId) {
            try {
                $attachment = MailMessageAttachment::query()->find($attachmentId);

                if (! $attachment) {
                    throw new RuntimeException('A stored mail attachment record disappeared.');
                }

                $report['saved']++;

                if ($this->ingestion->ingestMailAttachment($attachment)) {
                    $report['ingested']++;
                }
            } catch (Throwable $exception) {
                $report['failed']++;
                $firstFailure ??= $exception;
                logger()->warning('AI price-list email attachment processing failed', [
                    'mail_message_id' => $message->id,
                    'mail_attachment_id' => $attachmentId,
                    'exception' => $exception::class,
                ]);
            }
        }

        ksort($report['skipped']);

        if ($report['failed'] > 0) {
            logger()->warning('AI price-list email collection will be retried', [
                'mail_message_id' => $message->id,
                ...$report,
            ]);

            throw new RuntimeException(
                "Failed to process {$report['failed']} eligible mail attachment(s).",
                previous: $firstFailure,
            );
        }

        return $report;
    }
}
