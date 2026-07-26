<?php

namespace App\Services\Mail;

use App\Models\MailMessage;

class IncomingMailMaxMessageFormatter
{
    public function textParts(MailMessage $message): array
    {
        $body = $this->plainBody($message);
        $from = trim(implode(' ', array_filter([
            $message->from_name,
            $message->from_address ? "<{$message->from_address}>" : null,
        ])));
        $date = $message->message_date?->timezone(config('app.timezone'))->format('d.m.Y H:i');

        $text = implode("\n", [
            '📨 Новое письмо',
            'Ящик: '.($message->mailbox ?: 'не указан'),
            'От: '.($from ?: 'не указан'),
            'Тема: '.($message->subject ?: '(без темы)'),
            'Дата: '.($date ?: 'не указана'),
            '',
            $body !== '' ? $body : '(письмо без текстовой части)',
        ]);

        $chunkLength = (int) config('services.max.mail_notifications.text_chunk_length', 3400);
        $chunkLength = min(3800, max(500, $chunkLength));
        $chunks = $this->split($text, max(400, $chunkLength - 100));
        $total = count($chunks);

        if ($total <= 1) {
            return $chunks;
        }

        return collect($chunks)
            ->map(function (string $chunk, int $index) use ($message, $total): string {
                if ($index === 0) {
                    return $chunk;
                }

                $part = $index + 1;

                return "📨 Письмо #{$message->id}, продолжение {$part}/{$total}\n\n{$chunk}";
            })
            ->all();
    }

    public function attachmentCaption(
        MailMessage $message,
        string $name,
        int $index,
        int $total,
    ): string {
        $part = $index + 1;

        return implode("\n", [
            "📎 Вложение {$part}/{$total} к письму #{$message->id}",
            $name,
        ]);
    }

    public function skippedAttachmentCaption(
        MailMessage $message,
        string $name,
        ?int $size,
        int $maxBytes,
    ): string {
        $sizeLabel = $size ? $this->formatBytes($size) : 'неизвестного размера';
        $limitLabel = $this->formatBytes($maxBytes);

        return implode("\n", [
            "⚠️ Вложение к письму #{$message->id} не переслано в MAX.",
            "Файл: {$name}",
            "Размер: {$sizeLabel}; установленный лимит: {$limitLabel}.",
            'Файл доступен по кнопке «Открыть письмо».',
        ]);
    }

    public function linkKeyboard(string $url): array
    {
        return [
            'type' => 'inline_keyboard',
            'payload' => [
                'buttons' => [[
                    [
                        'type' => 'link',
                        'text' => 'Открыть письмо',
                        'url' => $url,
                    ],
                ]],
            ],
        ];
    }

    private function plainBody(MailMessage $message): string
    {
        $text = $message->text;

        if (blank($text) && filled($message->html)) {
            $html = preg_replace(
                '/<(script|style)\b[^>]*>.*?<\/\1\s*>/is',
                '',
                (string) $message->html,
            );
            $html = preg_replace(
                '/<(br|hr)\b[^>]*>|<\/(p|div|li|tr|td|th|h[1-6])\s*>/i',
                "\n",
                (string) $html,
            );
            $text = html_entity_decode(
                strip_tags((string) $html),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
        }

        $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], (string) $text);
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?: $text;
        $text = preg_replace('/\n{4,}/u', "\n\n\n", $text) ?: $text;

        return trim($text);
    }

    private function split(string $text, int $limit): array
    {
        $text = trim($text);

        if ($text === '') {
            return [''];
        }

        $chunks = [];

        while (mb_strlen($text) > $limit) {
            $candidate = mb_substr($text, 0, $limit);
            $newline = mb_strrpos($candidate, "\n");
            $space = mb_strrpos($candidate, ' ');
            $breakAt = max($newline === false ? 0 : $newline, $space === false ? 0 : $space);

            if ($breakAt < (int) floor($limit * 0.6)) {
                $breakAt = $limit;
            }

            $chunks[] = trim(mb_substr($text, 0, $breakAt));
            $text = ltrim(mb_substr($text, $breakAt));
        }

        if ($text !== '') {
            $chunks[] = $text;
        }

        return $chunks;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', ' ').' МБ';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' КБ';
        }

        return "{$bytes} Б";
    }
}
