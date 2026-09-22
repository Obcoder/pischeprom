<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mail\PreviewWordAttachmentRequest;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Services\Mail\WordAttachmentPreviewer;
use App\Services\Mail\WordPreviewException;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WordAttachmentPreviewController extends Controller
{
    public function __invoke(PreviewWordAttachmentRequest $request, MailMessage $mailMessage, int $index, WordAttachmentPreviewer $previewer, YandexMailboxService $mailbox): JsonResponse
    {
        $data = $request->validated();
        $key = $mailMessage->id.':'.($data['attachment_id'] ?? 'index-'.$index);
        $lock = Cache::lock('mail-word-preview:'.$key, 60);

        if (! $lock->get()) {
            return $this->failure('Это вложение уже открывается. Повторите через несколько секунд.', 429);
        }

        try {
            $file = $this->readAttachment($mailMessage, $index, $data['attachment_id'] ?? null, $mailbox);
            $result = $previewer->preview($file['content'], $file['name']);

            return response()->json($result, 200, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (WordPreviewException $exception) {
            return $this->failure($exception->getMessage(), $exception->status);
        } catch (Throwable) {
            // Storage and IMAP errors can contain private file paths, content or credentials.
            return $this->failure('Не удалось прочитать Word-вложение. Попробуйте ещё раз.', 503);
        } finally {
            $lock->release();
        }
    }

    private function readAttachment(MailMessage $message, int $index, ?int $attachmentId, YandexMailboxService $mailbox): array
    {
        $attachment = null;
        if ($attachmentId !== null) {
            $attachment = $message->attachments()->find($attachmentId);
            if (! $attachment) {
                throw new WordPreviewException('Сохранённое вложение не найдено.', 404);
            }
        } elseif (! $message->imap_uid) {
            $attachment = $message->attachments()->orderBy('id')->skip($index)->first();
        }

        if ($attachment) {
            return $this->readSavedAttachment($attachment);
        }

        $file = $mailbox->downloadAttachment($message, $index);

        if (! $file || ! is_string($file['content'] ?? null)) {
            throw new WordPreviewException('Вложение не найдено.', 404);
        }

        return ['content' => $file['content'], 'name' => (string) ($file['name'] ?? '')];
    }

    private function readSavedAttachment(MailMessageAttachment $attachment): array
    {
        if (! $attachment->disk || ! $attachment->path) {
            throw new WordPreviewException('Сохранённое вложение недоступно.', 404);
        }

        if ((int) $attachment->size > WordAttachmentPreviewer::MAX_FILE_BYTES) {
            throw new WordPreviewException('Для предпросмотра выберите Word-документ размером до 10 МБ.');
        }

        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            throw new WordPreviewException('Вложение не найдено в хранилище.', 404);
        }
        $stream = $disk->readStream($attachment->path);
        if (! is_resource($stream)) {
            throw new WordPreviewException('Не удалось открыть вложение.', 503);
        }

        try {
            $content = stream_get_contents($stream, WordAttachmentPreviewer::MAX_FILE_BYTES + 1);
            if (! is_string($content)) {
                throw new WordPreviewException('Не удалось прочитать вложение.', 503);
            }

            return ['content' => $content, 'name' => (string) ($attachment->original_name ?: $attachment->file_name ?: '')];
        } finally {
            fclose($stream);
        }
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status, ['Cache-Control' => 'private, no-store']);
    }
}
