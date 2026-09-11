<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Services\Mail\MailAttachmentAnalysisException;
use App\Services\Mail\MailAttachmentAnalyzer;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MailAttachmentAnalysisController extends Controller
{
    public function __invoke(
        Request $request,
        MailMessage $mailMessage,
        int $index,
        MailAttachmentAnalyzer $analyzer,
        YandexMailboxService $mailbox,
    ): JsonResponse {
        $data = $request->validate([
            'attachment_id' => ['nullable', 'integer', 'min:1'],
            'ocr' => ['sometimes', 'boolean'],
        ]);

        $key = $mailMessage->id.':'.($data['attachment_id'] ?? 'index-'.$index);
        $lock = Cache::lock('mail-attachment-analysis:'.$key, 600);

        if (! $lock->get()) {
            return $this->failure('Это вложение уже обрабатывается. Повторите через несколько секунд.', 429);
        }

        try {
            $file = $this->readAttachment($mailMessage, $index, $data['attachment_id'] ?? null, $mailbox);
            $result = $analyzer->analyze($file['content'], (bool) ($data['ocr'] ?? false), (int) $mailMessage->id);

            return response()->json($result, 200, ['Cache-Control' => 'private, no-store'], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (MailAttachmentAnalysisException $exception) {
            return $this->failure($exception->getMessage(), $exception->status);
        } catch (Throwable) {
            // Provider, storage and PDF exceptions may contain document contents or credentials.
            return $this->failure('Не удалось прочитать PDF-вложение. Попробуйте ещё раз.', 503);
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
                throw new MailAttachmentAnalysisException('Сохранённое вложение не найдено.', 404);
            }
        } elseif (! $message->imap_uid) {
            $attachment = $message->attachments()->orderBy('id')->skip($index)->first();
        }

        if ($attachment) {
            return ['content' => $this->readSavedAttachment($attachment)];
        }

        $file = $mailbox->downloadAttachment($message, $index);

        if (! $file || ! is_string($file['content'] ?? null)) {
            throw new MailAttachmentAnalysisException('Вложение не найдено.', 404);
        }

        return $file;
    }

    private function readSavedAttachment(MailMessageAttachment $attachment): string
    {
        if (! $attachment->disk || ! $attachment->path) {
            throw new MailAttachmentAnalysisException('Сохранённое вложение недоступно.', 404);
        }

        if ((int) $attachment->size > MailAttachmentAnalyzer::MAX_FILE_BYTES) {
            throw new MailAttachmentAnalysisException('Для распознавания выберите PDF размером до 10 МБ.');
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            throw new MailAttachmentAnalysisException('Файл вложения не найден в хранилище.', 404);
        }

        $stream = $disk->readStream($attachment->path);

        if (! is_resource($stream)) {
            throw new MailAttachmentAnalysisException('Не удалось открыть файл вложения.', 503);
        }

        try {
            $content = stream_get_contents($stream, MailAttachmentAnalyzer::MAX_FILE_BYTES + 1);

            if ($content === false) {
                throw new MailAttachmentAnalysisException('Не удалось прочитать файл вложения.', 503);
            }

            return $content;
        } finally {
            fclose($stream);
        }
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status, ['Cache-Control' => 'private, no-store']);
    }
}
