<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mail\SendMailMessageRequest;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Models\MailMessageNote;
use App\Models\Unit;
use App\Services\Mail\AuthorizedMailDispatchService;
use App\Services\Mail\MailDispatchException;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class MailMessageActionController extends Controller
{
    public function send(
        SendMailMessageRequest $request,
        AuthorizedMailDispatchService $dispatch,
    ): JsonResponse {
        try {
            $unit = $request->integer('unit_id') ? Unit::query()->findOrFail($request->integer('unit_id')) : null;
            $entity = $request->integer('entity_id') ? Entity::query()->findOrFail($request->integer('entity_id')) : null;

            // Deliberate controller-level check; the service repeats it immediately before dispatch.
            $dispatch->authorize($request->user(), $unit);
            $data = $request->validated();
            $data['attachments'] = $request->file('attachments', []);
            $result = $dispatch->dispatchMessage($request->user(), $data, 'mail-messages.send', $unit, $entity);

            return response()->json([
                'message' => $result['duplicate'] ? 'Письмо уже обработано.' : 'Письмо отправлено.',
                'duplicate' => $result['duplicate'],
                'mail_message' => $result['mail_message'] ? $this->messagePayload($result['mail_message']->fresh()) : null,
            ]);
        } catch (MailDispatchException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => $exception->safeCode], $exception->httpStatus);
        }
    }

    public function syncAttachments(
        Request $request,
        MailMessage $mailMessage,
        YandexMailboxService $service,
    ): JsonResponse {
        $mailMessage = $service->syncAttachments(
            mailMessage: $mailMessage,
            force: $request->boolean('force', true),
        );

        return response()->json($this->messagePayload($mailMessage), 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function saveAttachment(
        Request $request,
        MailMessage $mailMessage,
        int $index,
        YandexMailboxService $service,
    ): JsonResponse {
        $data = $request->validate([
            'folder' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $mailMessage = $service->saveAttachment(
                mailMessage: $mailMessage,
                index: $index,
                folder: $data['folder'] ?? null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось сохранить вложение в Yandex S3: '.$exception->getMessage(),
            ], 500);
        }

        return response()->json($this->messagePayload($mailMessage), 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function downloadAttachment(
        Request $request,
        MailMessage $mailMessage,
        int $index,
        YandexMailboxService $service,
    ) {
        if ($request->filled('attachment_id')) {
            $savedAttachment = MailMessageAttachment::query()
                ->where('mail_message_id', $mailMessage->id)
                ->find($request->integer('attachment_id'));

            if (! $savedAttachment) {
                return response()->json([
                    'message' => 'Сохранённое вложение не найдено.',
                ], 404);
            }

            return $this->downloadSavedAttachment($savedAttachment);
        }

        if (! $mailMessage->imap_uid) {
            $savedAttachment = $mailMessage->attachments()
                ->orderBy('id')
                ->skip($index)
                ->first();

            if ($savedAttachment) {
                return $this->downloadSavedAttachment($savedAttachment);
            }
        }

        try {
            $file = $service->downloadAttachment($mailMessage, $index);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось скачать вложение: '.$exception->getMessage(),
            ], 500);
        }

        if (! $file) {
            return response()->json([
                'message' => 'Вложение не найдено.',
            ], 404);
        }

        $name = $this->safeFileName($file['name'] ?? ('attachment-'.($index + 1).'.bin'));
        $mimeType = $file['mime_type'] ?? 'application/octet-stream';

        return response()->streamDownload(
            static function () use ($file): void {
                echo $file['content'];
            },
            $name,
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function attachmentFolders(
        MailMessage $mailMessage,
        YandexMailboxService $service,
    ): JsonResponse {
        return response()->json([
            'folders' => $service->attachmentFolders($mailMessage),
        ]);
    }

    public function storeAttachmentFolder(
        Request $request,
        MailMessage $mailMessage,
        YandexMailboxService $service,
    ): JsonResponse {
        $data = $request->validate([
            'folder' => ['required', 'string', 'max:500'],
        ]);

        return response()->json([
            'folder' => $service->createAttachmentFolder($data['folder'], $mailMessage),
            'folders' => $service->attachmentFolders($mailMessage),
        ], 201);
    }

    public function storeNote(Request $request, MailMessage $mailMessage): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'importance' => ['nullable', Rule::in(['normal', 'important', 'critical'])],
        ]);

        MailMessageNote::query()->create([
            'mail_message_id' => $mailMessage->id,
            'user_id' => $request->user()?->id,
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'importance' => $data['importance'] ?? 'important',
        ]);

        return response()->json($this->messagePayload($mailMessage->fresh()));
    }

    public function createLead(Request $request, MailMessage $mailMessage): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $leadPayload = [
            'source' => 'email',
            'status' => Lead::STATUS_OPEN,
            'title' => ($data['title'] ?? null) ?: ($mailMessage->subject ?: 'Лид из письма'),
            'description' => ($data['description'] ?? null) ?: $this->leadDescription($mailMessage),
            'entity_id' => $data['entity_id'] ?? $this->firstRelatedEntityId($mailMessage),
            'unit_id' => $data['unit_id'] ?? $this->firstRelatedUnitId($mailMessage),
            'last_activity_at' => $mailMessage->message_date ?: now(),
        ];

        if (Schema::hasColumn('leads', 'mail_message_id')) {
            $leadPayload['mail_message_id'] = $mailMessage->id;
        }

        $lead = Lead::query()->create($leadPayload);

        return response()->json([
            'lead' => $lead->fresh(['entity', 'unit']),
            'mail_message' => $this->messagePayload($mailMessage->fresh()),
        ], 201);
    }

    private function leadDescription(MailMessage $mailMessage): string
    {
        $body = trim($mailMessage->text ?: strip_tags((string) $mailMessage->html) ?: $mailMessage->preview ?: '');

        return trim(implode("\n\n", array_filter([
            'Источник: email',
            'Письмо #'.$mailMessage->id,
            'From: '.trim(($mailMessage->from_name ? $mailMessage->from_name.' ' : '').(string) $mailMessage->from_address),
            'Subject: '.(string) $mailMessage->subject,
            Str::limit($body, 2000),
        ])));
    }

    private function firstRelatedEntityId(MailMessage $mailMessage): ?int
    {
        return $mailMessage
            ->loadMissing('emails.entities')
            ->emails
            ->flatMap(fn (Email $email) => $email->entities)
            ->pluck('id')
            ->filter()
            ->first();
    }

    private function firstRelatedUnitId(MailMessage $mailMessage): ?int
    {
        return $mailMessage
            ->loadMissing(['emails.units', 'emails.entities.units'])
            ->emails
            ->flatMap(function (Email $email) {
                return $email->units->merge(
                    $email->entities->flatMap(fn ($entity) => $entity->units)
                );
            })
            ->pluck('id')
            ->filter()
            ->first();
    }

    private function safeFileName(string $name): string
    {
        $name = trim(str_replace(['\\', '/'], '-', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: '';
        $name = preg_replace('/\s+/', ' ', $name) ?: '';

        return $name !== '' ? $name : 'attachment.bin';
    }

    private function downloadSavedAttachment(MailMessageAttachment $attachment)
    {
        if (! $attachment->disk || ! $attachment->path) {
            return response()->json([
                'message' => 'У сохранённого вложения нет пути к файлу.',
            ], 404);
        }

        try {
            $disk = Storage::disk($attachment->disk);

            if (! $disk->exists($attachment->path)) {
                return response()->json([
                    'message' => 'Файл вложения не найден на диске.',
                ], 404);
            }

            return $disk->download(
                $attachment->path,
                $this->safeFileName($attachment->original_name ?: $attachment->file_name ?: basename($attachment->path)),
                [
                    'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                    'Cache-Control' => 'private, no-store',
                ]
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось скачать сохранённое вложение.',
            ], 500);
        }
    }

    private function messagePayload(MailMessage $mailMessage): MailMessage
    {
        return $mailMessage->load([
            'attachments',
            'notes.user:id,name',
            'leads:id,mail_message_id,title,status,entity_id,unit_id',
            'emails:id,address,name',
            'emails.units:id,name',
            'emails.entities:id,name',
        ]);
    }
}
