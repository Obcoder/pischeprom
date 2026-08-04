<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Enums\SourceChannel;
use App\Jobs\AiPriceLists\ValidatePriceListFile;
use App\Models\Email;
use App\Models\Entity;
use App\Models\MailMessageAttachment;
use App\Models\PriceListImport;
use Illuminate\Support\Facades\DB;

class PriceListIngestionService
{
    public function __construct(
        private readonly PriceListDocumentClassifier $classifier,
        private readonly PriceListFileValidator $validator,
        private readonly PriceListStateMachine $states,
        private readonly PriceListAuditLogger $audit,
    ) {}

    public function ingestMailAttachment(MailMessageAttachment $attachment): ?PriceListImport
    {
        if (! config('ai-price-lists.enabled')) {
            return null;
        }

        $attachment->loadMissing('mailMessage');
        $message = $attachment->mailMessage;

        if (! $message || $message->direction !== 'incoming' || ! $attachment->disk || ! $attachment->path) {
            return null;
        }

        if (! $this->classifier->eligibleMailAttachment($attachment, $message)) {
            return null;
        }

        $sourceKey = 'email:attachment:'.$attachment->id;
        $existing = PriceListImport::query()->where('source_key', $sourceKey)->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($attachment, $message, $sourceKey): PriceListImport {
            $supplier = $this->supplierForEmail($message->from_address);
            $name = $attachment->original_name ?: $attachment->file_name ?: 'attachment';
            $safeName = $this->validator->safeDisplayName($name);

            $import = PriceListImport::query()->create([
                'source_key' => $sourceKey,
                'source_channel' => SourceChannel::Email,
                'status' => PriceListStatus::Received,
                'current_stage' => PriceListStage::Ingest->value,
                'entity_id' => $supplier?->id,
                'mail_message_id' => $message->id,
                'source_external_message_id' => $message->message_id ?: $message->mailbox.':'.$message->folder.':'.$message->imap_uid,
                'source_external_attachment_id' => (string) $attachment->id,
                'sender_address' => mb_strtolower(trim((string) $message->from_address)) ?: null,
                'sender_name' => $message->from_name,
                'source_subject' => $message->subject,
                'source_received_at' => $message->message_date ?: $attachment->created_at,
                'disk' => $attachment->disk,
                'path' => $attachment->path,
                'original_name' => $name,
                'safe_name' => $safeName,
                'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: null,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => (int) $attachment->size,
                'document_class' => $this->classifier->classify($name, $message->subject, $message->preview),
                'document_metadata' => [
                    'mail_attachment_id' => $attachment->id,
                    'mailbox' => $message->mailbox,
                    'folder' => $message->folder,
                ],
            ]);

            $this->audit->record($import, 'import_created', [
                'source_channel' => SourceChannel::Email->value,
                'supplier_resolved' => $supplier !== null,
            ]);
            $this->states->transition($import, PriceListStatus::Queued, PriceListStage::Validate, 2);

            DB::afterCommit(fn () => ValidatePriceListFile::dispatch($import->id)
                ->onConnection((string) config('ai-price-lists.queue_connection'))
                ->onQueue((string) config('ai-price-lists.queue')));

            return $import->refresh();
        }, 3);
    }

    private function supplierForEmail(?string $address): ?Entity
    {
        $address = mb_strtolower(trim((string) $address));

        if ($address === '') {
            return null;
        }

        $email = Email::query()
            ->whereRaw('LOWER(address) = ?', [$address])
            ->with(['entities' => fn ($query) => $query->select('entities.id', 'entities.name')])
            ->first();

        return $email && $email->entities->count() === 1 ? $email->entities->first() : null;
    }
}
