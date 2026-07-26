<?php

namespace App\Services\Mail;

use App\Models\MailMessage;
use App\Models\MailMessageMaxDelivery;
use App\Services\MaxMessengerService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class IncomingMailMaxNotificationSender
{
    private ?float $lastMessageSentAt = null;

    public function __construct(
        private readonly YandexMailboxService $mailbox,
        private readonly MaxMessengerService $max,
        private readonly IncomingMailMaxMessageFormatter $formatter,
    ) {}

    public function send(MailMessageMaxDelivery $delivery): void
    {
        if ($delivery->status === MailMessageMaxDelivery::STATUS_SENT) {
            return;
        }

        $delivery->loadMissing('mailMessage');
        $message = $delivery->mailMessage;

        if (! $message) {
            throw new RuntimeException('Письмо для MAX-уведомления больше не существует.');
        }

        $delivery->forceFill([
            'status' => MailMessageMaxDelivery::STATUS_SENDING,
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'last_error' => null,
        ])->save();

        try {
            $message = $this->loadMessage($message);
            $textParts = $this->formatter->textParts($message);
            $attachments = array_values((array) $message->getAttribute('available_attachments'));
            $delivery->forceFill([
                'text_parts_total' => count($textParts),
                'attachments_total' => count($attachments),
            ])->save();

            $url = route('Ameise.mail', [
                'mail_message_id' => $message->id,
            ]);

            $this->sendTextParts($delivery, $textParts, $url);
            $this->sendAttachments($delivery, $message, $attachments, $url);

            $delivery->forceFill([
                'status' => MailMessageMaxDelivery::STATUS_SENT,
                'last_error' => null,
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $delivery->forceFill([
                'status' => MailMessageMaxDelivery::STATUS_RETRYING,
                'last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function loadMessage(MailMessage $message): MailMessage
    {
        $loaded = $this->mailbox->loadBody(
            mailMessage: $message,
            force: false,
            withAttachments: false,
            includeAttachmentList: true,
        );

        $syncError = $loaded->getAttribute('mail_sync_error');

        if (filled($syncError)) {
            throw new RuntimeException((string) $syncError);
        }

        if ($loaded->imap_uid && ! $loaded->body_loaded_at) {
            throw new RuntimeException('IMAP не вернул полный текст письма.');
        }

        return $loaded;
    }

    private function sendTextParts(
        MailMessageMaxDelivery $delivery,
        array $parts,
        string $url,
    ): void {
        $start = min((int) $delivery->text_parts_sent, count($parts));

        for ($index = $start; $index < count($parts); $index++) {
            $payload = $index === 0
                ? ['attachments' => [$this->formatter->linkKeyboard($url)]]
                : [];
            $result = $this->sendMaxMessage(
                $delivery,
                $parts[$index],
                $payload,
            );

            $this->recordProgress(
                delivery: $delivery,
                providerKey: "text:{$index}",
                result: $result,
                field: 'text_parts_sent',
                value: $index + 1,
            );
        }
    }

    private function sendAttachments(
        MailMessageMaxDelivery $delivery,
        MailMessage $message,
        array $attachments,
        string $url,
    ): void {
        $total = count($attachments);
        $start = min((int) $delivery->attachments_sent, $total);

        for ($index = $start; $index < $total; $index++) {
            $metadata = is_array($attachments[$index]) ? $attachments[$index] : [];
            $name = $this->attachmentName($metadata, $index);
            $size = is_numeric($metadata['size'] ?? null) ? (int) $metadata['size'] : null;
            $maxBytes = (int) config('services.max.mail_notifications.max_attachment_bytes', 52428800);

            if ($maxBytes > 0 && $size !== null && $size > $maxBytes) {
                $this->sendSkippedAttachment(
                    $delivery,
                    $message,
                    $name,
                    $size,
                    $maxBytes,
                    $index,
                    $url,
                );

                continue;
            }

            $tokens = $delivery->attachment_tokens ?: [];
            $tokenPayload = $tokens[(string) $index] ?? null;

            if (! is_array($tokenPayload) || blank($tokenPayload['token'] ?? null)) {
                $file = $this->mailbox->downloadAttachment($message, $index);

                if (! is_array($file) || ! array_key_exists('content', $file)) {
                    throw new RuntimeException("Не удалось получить вложение «{$name}» из IMAP.");
                }

                $content = $file['content'];

                if (! is_string($content) || $content === '') {
                    $this->sendSkippedAttachment(
                        $delivery,
                        $message,
                        $name,
                        is_string($content) ? strlen($content) : $size,
                        $maxBytes,
                        $index,
                        $url,
                        'MAX не принимает пустые вложения.',
                    );

                    continue;
                }

                if ($maxBytes > 0 && strlen($content) > $maxBytes) {
                    $this->sendSkippedAttachment(
                        $delivery,
                        $message,
                        $name,
                        strlen($content),
                        $maxBytes,
                        $index,
                        $url,
                    );

                    continue;
                }

                $name = $this->attachmentName($file + $metadata, $index);
                $mimeType = trim((string) ($file['mime_type'] ?? $metadata['mime_type'] ?? ''))
                    ?: 'application/octet-stream';
                $type = $this->maxAttachmentType($mimeType, $name);
                $upload = $this->max->uploadAttachment(
                    content: $content,
                    fileName: $name,
                    mimeType: $mimeType,
                    type: $type,
                );

                $this->ensureSuccess($upload, "MAX не загрузил вложение «{$name}»");

                $tokenPayload = [
                    'type' => $type,
                    'token' => (string) data_get($upload, 'data.token'),
                    'name' => $name,
                    'mime_type' => $mimeType,
                    'size' => strlen($content),
                ];
                $tokens[(string) $index] = $tokenPayload;
                $delivery->forceFill([
                    'attachment_tokens' => $tokens,
                ])->save();

                $delayMs = min(
                    60000,
                    max(0, (int) config('services.max.mail_notifications.upload_processing_delay_ms', 1000)),
                );

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }

            $caption = $this->formatter->attachmentCaption(
                $message,
                (string) ($tokenPayload['name'] ?? $name),
                $index,
                $total,
            );
            $result = $this->sendMaxMessage($delivery, $caption, [
                'attachments' => [
                    [
                        'type' => (string) ($tokenPayload['type'] ?? 'file'),
                        'payload' => [
                            'token' => (string) $tokenPayload['token'],
                        ],
                    ],
                    $this->formatter->linkKeyboard($url),
                ],
            ]);

            $this->recordProgress(
                delivery: $delivery,
                providerKey: "attachment:{$index}",
                result: $result,
                field: 'attachments_sent',
                value: $index + 1,
            );
        }
    }

    private function sendSkippedAttachment(
        MailMessageMaxDelivery $delivery,
        MailMessage $message,
        string $name,
        ?int $size,
        int $maxBytes,
        int $index,
        string $url,
        ?string $reason = null,
    ): void {
        $caption = $reason
            ? implode("\n", [
                "⚠️ Вложение к письму #{$message->id} не переслано в MAX.",
                "Файл: {$name}",
                $reason,
                'Файл доступен по кнопке «Открыть письмо».',
            ])
            : $this->formatter->skippedAttachmentCaption(
                $message,
                $name,
                $size,
                $maxBytes,
            );
        $result = $this->sendMaxMessage($delivery, $caption, [
            'attachments' => [$this->formatter->linkKeyboard($url)],
        ]);
        $skipped = $delivery->skipped_attachments ?: [];
        $skipped[(string) $index] = [
            'name' => $name,
            'size' => $size,
            'reason' => $reason ?: 'attachment_size_limit',
        ];

        $this->recordProgress(
            delivery: $delivery,
            providerKey: "attachment-skipped:{$index}",
            result: $result,
            field: 'attachments_sent',
            value: $index + 1,
            attributes: [
                'skipped_attachments' => $skipped,
            ],
        );
    }

    private function sendMaxMessage(
        MailMessageMaxDelivery $delivery,
        string $text,
        array $payload = [],
    ): array {
        $this->throttleMessages($delivery);
        $result = $this->max->sendMessage($delivery->targetQuery(), $text, $payload);
        $this->ensureSuccess($result, 'MAX не отправил уведомление о письме');
        $this->lastMessageSentAt = microtime(true);
        $this->rememberMessageSentAt($delivery, $this->lastMessageSentAt);

        return $result;
    }

    private function throttleMessages(MailMessageMaxDelivery $delivery): void
    {
        $configuredInterval = (int) config('services.max.mail_notifications.send_interval_ms', 600);

        if ($configuredInterval <= 0) {
            return;
        }

        $intervalMs = min(60000, max(500, $configuredInterval));
        $lastSentAt = $this->lastMessageSentAt;

        try {
            $cachedSentAt = Cache::get($this->throttleCacheKey($delivery));

            if (is_numeric($cachedSentAt)) {
                $lastSentAt = max((float) ($lastSentAt ?? 0), (float) $cachedSentAt);
            }
        } catch (\Throwable) {
            // A cache outage must not turn a successful MAX send into a duplicate on retry.
        }

        if ($lastSentAt === null) {
            return;
        }

        $elapsedMs = (microtime(true) - $lastSentAt) * 1000;
        $remainingMs = $intervalMs - $elapsedMs;

        if ($remainingMs > 0) {
            usleep((int) ceil($remainingMs * 1000));
        }
    }

    private function rememberMessageSentAt(
        MailMessageMaxDelivery $delivery,
        float $sentAt,
    ): void {
        try {
            Cache::put(
                $this->throttleCacheKey($delivery),
                $sentAt,
                now()->addMinutes(10),
            );
        } catch (\Throwable) {
            // Delivery progress is more important than the optional cross-job throttle state.
        }
    }

    private function throttleCacheKey(MailMessageMaxDelivery $delivery): string
    {
        return 'max-mail-notifications:last-sent:'.sha1(
            "{$delivery->target_type}:{$delivery->target_id}",
        );
    }

    private function ensureSuccess(array $result, string $fallback): void
    {
        if ($result['ok'] ?? false) {
            return;
        }

        $error = trim((string) ($result['error'] ?? ''));

        throw new RuntimeException($error !== '' ? "{$fallback}: {$error}" : $fallback);
    }

    private function recordProgress(
        MailMessageMaxDelivery $delivery,
        string $providerKey,
        array $result,
        string $field,
        int $value,
        array $attributes = [],
    ): void {
        $messageIds = $delivery->provider_message_ids ?: [];
        $providerId = $this->providerMessageId($result['data'] ?? []);

        if ($providerId) {
            $messageIds[$providerKey] = $providerId;
        }

        $delivery->forceFill(array_merge($attributes, [
            $field => $value,
            'provider_message_ids' => $messageIds,
            'last_error' => null,
        ]))->save();
    }

    private function providerMessageId(array $payload): ?string
    {
        foreach ([
            'message_id',
            'message.id',
            'message.mid',
            'message.body.mid',
            'body.mid',
            'mid',
            'id',
        ] as $path) {
            $value = data_get($payload, $path);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function attachmentName(array $attachment, int $index): string
    {
        $name = trim((string) (
            $attachment['name']
            ?? $attachment['original_name']
            ?? $attachment['file_name']
            ?? ''
        ));
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $name) ?: '';

        return $name !== '' ? $name : 'attachment-'.($index + 1).'.bin';
    }

    private function maxAttachmentType(string $mimeType, string $name): string
    {
        $mimeType = strtolower($mimeType);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (
            str_starts_with($mimeType, 'image/')
            && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'tiff', 'tif', 'bmp', 'heic'], true)
        ) {
            return 'image';
        }

        if (
            str_starts_with($mimeType, 'video/')
            && in_array($extension, ['mp4', 'mov', 'mkv', 'webm'], true)
        ) {
            return 'video';
        }

        if (
            str_starts_with($mimeType, 'audio/')
            && in_array($extension, ['mp3', 'wav', 'm4a'], true)
        ) {
            return 'audio';
        }

        return 'file';
    }
}
