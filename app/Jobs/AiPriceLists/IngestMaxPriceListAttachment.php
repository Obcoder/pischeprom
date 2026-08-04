<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Enums\SourceChannel;
use App\Domain\AiPriceLists\Exceptions\SafeRemoteDownloadException;
use App\Domain\AiPriceLists\Services\MaxPriceListNotifier;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Domain\AiPriceLists\Services\PriceListDocumentClassifier;
use App\Domain\AiPriceLists\Services\PriceListFileValidator;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Domain\AiPriceLists\Services\SafeRemoteFileDownloader;
use App\Jobs\Middleware\ObservePriceListJob;
use App\Models\MaxChat;
use App\Models\MaxWebhookEvent;
use App\Models\PriceListImport;
use App\Services\MaxMessengerService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class IngestMaxPriceListAttachment implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $eventId, public readonly int $attachmentIndex)
    {
        $this->tries = max(1, (int) config('ai-price-lists.limits.max_attempts'));
        $this->onConnection((string) config('ai-price-lists.queue_connection'));
        $this->onQueue((string) config('ai-price-lists.queue'));
    }

    public function uniqueId(): string
    {
        return "max-price-list:{$this->eventId}:{$this->attachmentIndex}";
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function middleware(): array
    {
        return [
            new ObservePriceListJob,
            (new WithoutOverlapping("max-price-list:{$this->eventId}:{$this->attachmentIndex}"))->expireAfter($this->timeout + 30),
        ];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function failed(?Throwable $exception): void
    {
        $import = PriceListImport::query()
            ->where('source_channel', SourceChannel::Max->value)
            ->where('document_metadata->max_event_id', $this->eventId)
            ->where('document_metadata->max_attachment_index', $this->attachmentIndex)
            ->first();

        if (! $import || in_array($import->status, [PriceListStatus::Applied, PriceListStatus::Cancelled], true)) {
            return;
        }

        try {
            $failed = app(PriceListStateMachine::class)->fail(
                $import,
                'max_ingestion_failed',
                'Вложение MAX не удалось получить после нескольких попыток. Его можно отправить повторно.',
                true,
                ['event_id' => $this->eventId, 'attachment_index' => $this->attachmentIndex],
            );
            app(MaxPriceListNotifier::class)->failed($failed, 'Не удалось получить файл из MAX. Пожалуйста, отправьте вложение повторно.');
        } catch (Throwable) {
            // Preserve the original queue exception.
        }
    }

    public function handle(
        MaxMessengerService $max,
        SafeRemoteFileDownloader $downloader,
        PriceListFileValidator $files,
        PriceListDocumentClassifier $classifier,
        PriceListAuditLogger $audit,
        PriceListStateMachine $states,
        MaxPriceListNotifier $notifier,
    ): void {
        $event = MaxWebhookEvent::query()->findOrFail($this->eventId);
        $payload = $event->payload;
        $message = data_get($payload, 'message', $payload);
        $messageId = $this->firstString($message, ['body.mid', 'id', 'mid', 'message_id'])
            ?: $this->firstString($payload, ['message_id', 'update_id']);
        $attachment = data_get($message, 'body.attachments.'.$this->attachmentIndex)
            ?: data_get($payload, 'body.attachments.'.$this->attachmentIndex);

        if (! is_array($attachment) || ! in_array(data_get($attachment, 'type'), ['file', 'image'], true)) {
            return;
        }

        if (! data_get($attachment, 'payload.url') && $messageId && $max->configured()) {
            $remote = $max->getMessage($messageId);
            $remoteMessage = data_get($remote, 'data.message', data_get($remote, 'data'));
            $hydrated = data_get($remoteMessage, 'body.attachments.'.$this->attachmentIndex);

            if (is_array($hydrated)) {
                $attachment = $hydrated;
                $message = is_array($remoteMessage) ? $remoteMessage : $message;
            }
        }

        $attachmentHash = hash('sha256', json_encode([
            data_get($attachment, 'type'),
            data_get($attachment, 'payload.token'),
            data_get($attachment, 'payload.url'),
            $this->attachmentIndex,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $sourceKey = 'max:'.hash('sha256', ($messageId ?: $event->deduplication_key).':'.$attachmentHash);
        $import = PriceListImport::query()->where('source_key', $sourceKey)->first();

        if ($import && $import->path !== '') {
            return;
        }

        $chat = $this->findChat($event);
        $caption = $this->firstString($message, ['body.text', 'text']);
        $remoteName = $this->firstString($attachment, ['payload.filename', 'payload.name', 'filename', 'name']);
        $fallbackName = data_get($attachment, 'type') === 'image' ? 'max-price-image.jpg' : 'max-price-file.bin';
        $name = $files->safeDisplayName($remoteName ?: $fallbackName);

        $import ??= PriceListImport::query()->create([
            'source_key' => $sourceKey,
            'source_channel' => SourceChannel::Max,
            'status' => PriceListStatus::Received,
            'current_stage' => PriceListStage::Ingest->value,
            'entity_id' => $chat?->entity_id,
            'source_external_message_id' => $messageId ?: $event->deduplication_key,
            'source_external_attachment_id' => $attachmentHash,
            'source_chat_id' => $event->chat_id,
            'source_user_id' => $event->user_id,
            'sender_name' => $this->firstString($message, ['sender.name', 'sender.username']),
            'source_subject' => $caption,
            'source_received_at' => $this->timestamp($message, $event),
            'disk' => (string) config('ai-price-lists.storage_disk'),
            'path' => '',
            'original_name' => $name,
            'safe_name' => $name,
            'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: null,
            'size_bytes' => 0,
            'document_class' => $classifier->classify($name, caption: $caption),
            'document_metadata' => [
                'max_event_id' => $event->id,
                'max_attachment_type' => data_get($attachment, 'type'),
                'max_attachment_index' => $this->attachmentIndex,
            ],
        ]);

        $url = data_get($attachment, 'payload.url');

        if (! is_string($url) || $url === '') {
            $states->fail($import, 'max_attachment_url_missing', 'MAX не предоставил доступную ссылку на вложение.', false);
            $notifier->failed($import->refresh(), 'Не удалось получить файл из MAX. Пожалуйста, отправьте его повторно как обычное вложение.');

            return;
        }

        try {
            $download = $downloader->download($url);
        } catch (SafeRemoteDownloadException $exception) {
            $states->fail($import, $exception->errorCode, $exception->getMessage(), $exception->retryable);

            if ($exception->retryable) {
                throw $exception;
            }

            $notifier->failed($import->refresh(), $exception->getMessage());

            return;
        }

        if (pathinfo($name, PATHINFO_EXTENSION) === '' || str_ends_with($name, '.bin')) {
            $name = $files->safeDisplayName(pathinfo($name, PATHINFO_FILENAME).'.'.$this->extensionForMime($download['content_type'], data_get($attachment, 'type')));
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $storagePath = sprintf(
            '%s/%s/%s/original.%s',
            config('ai-price-lists.storage_prefix'),
            now()->format('Y/m'),
            $import->uuid,
            $extension ?: 'bin',
        );
        $stored = Storage::disk($import->disk)->put($storagePath, $download['content'], [
            'visibility' => 'private',
            'ContentType' => $download['content_type'],
        ]);

        if (! $stored) {
            throw new RuntimeException('Не удалось сохранить вложение MAX в приватном хранилище.');
        }

        $import->forceFill([
            'path' => $storagePath,
            'original_name' => $name,
            'safe_name' => $name,
            'extension' => $extension,
            'mime_type' => $download['content_type'],
            'size_bytes' => $download['size'],
        ])->save();
        $audit->record($import, 'import_created', [
            'source_channel' => SourceChannel::Max->value,
            'supplier_resolved' => $import->entity_id !== null,
            'attachment_type' => data_get($attachment, 'type'),
        ]);
        $states->transition($import, PriceListStatus::Queued, PriceListStage::Validate, 2);
        $notifier->acknowledged($import->refresh());
        ValidatePriceListFile::dispatch($import->id)->afterCommit();
    }

    private function findChat(MaxWebhookEvent $event): ?MaxChat
    {
        return MaxChat::query()
            ->when($event->chat_id, fn ($query) => $query->where('chat_id', $event->chat_id))
            ->when(! $event->chat_id && $event->user_id, fn ($query) => $query->where('user_id', $event->user_id))
            ->first();
    }

    private function firstString(array $value, array $paths): ?string
    {
        foreach ($paths as $path) {
            $candidate = data_get($value, $path);

            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    private function timestamp(array $message, MaxWebhookEvent $event): CarbonImmutable
    {
        $value = (int) data_get($message, 'timestamp', 0);

        if ($value > 10_000_000_000) {
            return CarbonImmutable::createFromTimestampMs($value);
        }

        return $value > 0 ? CarbonImmutable::createFromTimestamp($value) : CarbonImmutable::parse($event->created_at);
    }

    private function extensionForMime(string $mime, string $type): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/csv' => 'csv',
            'text/tab-separated-values' => 'tsv',
            'image/png' => 'png',
            'image/tiff' => 'tiff',
            default => $type === 'image' ? 'jpg' : 'bin',
        };
    }
}
