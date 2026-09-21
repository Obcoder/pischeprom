<?php

namespace App\Services\Avito\AutoReply;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use JsonException;

class AvitoAutoReplyConversationContext
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * The local archive is authoritative; never replace it with a recent API page.
     * An answer is unsafe when the complete available history cannot fit, so fail
     * closed instead of silently dropping old messages or the end of their text.
     */
    public function build(?AvitoChat $chat, ?AvitoMessage $anchor = null, ?Collection $bundle = null): array
    {
        $limit = max(1, (int) config('avito.auto_reply.context_max_bytes', 60000));
        $pending = $bundle ?? collect($anchor ? [$anchor] : []);
        $bytes = 2 + strlen($this->encode($pending->map(fn (AvitoMessage $message) => $this->entry($message))->all()));
        $this->assertFits($bytes, $limit);

        if (! $chat) {
            return [];
        }

        $query = $chat->messages()->select([
            'id', 'direction', 'type', 'remote_type', 'text', 'quote',
            'remote_created_at', 'created_at', 'deleted_from_avito_at',
        ])->whereIn('direction', ['in', 'out']);

        if ($anchor) {
            $time = $anchor->remote_created_at ?: $anchor->created_at;
            $query->where(fn (Builder $query) => $query
                ->whereRaw('COALESCE(remote_created_at, created_at) < ?', [$time])
                ->orWhere(fn (Builder $query) => $query
                    ->whereRaw('COALESCE(remote_created_at, created_at) = ?', [$time])
                    ->where('id', '<=', $anchor->id)));
        }

        if ($bundle?->isNotEmpty()) {
            $query->whereNotIn('id', $bundle->pluck('id')->all());
        }

        $conversation = [];
        foreach ($query->orderByRaw('COALESCE(remote_created_at, created_at) ASC')->orderBy('id')->cursor() as $message) {
            $entry = $this->entry($message);
            $bytes += strlen($this->encode($entry)) + ($conversation === [] ? 0 : 1);
            $this->assertFits($bytes, $limit);
            $conversation[] = $entry;
        }

        return $conversation;
    }

    /** Read receipts and sync timestamps do not change the facts seen by AI. */
    public function fingerprint(array $conversation): string
    {
        return hash('sha256', $this->encode($conversation));
    }

    private function entry(AvitoMessage $message): array
    {
        $text = (string) $message->text;
        $type = (string) ($message->type ?: 'unknown');
        if ($type !== 'text') {
            $label = match ($type) {
                'image' => 'Изображение: содержимое вложения AI не просматривал.',
                'voice', 'audio' => 'Аудиосообщение: содержимое вложения AI не прослушивал.',
                'video' => 'Видео: содержимое вложения AI не просматривал.',
                'file' => 'Файл: содержимое вложения AI не просматривал.',
                'location' => 'Сообщение с местоположением: нетекстовое содержимое недоступно AI.',
                'deleted' => 'Сообщение удалено в Авито; исходное содержимое отсутствует в архиве.',
                default => 'Нетекстовое сообщение: содержимое недоступно AI.',
            };
            $text .= ($text === '' ? '' : "\n").'['.$label.']';
        } elseif ($text === '') {
            $text = '[Текст сообщения отсутствует в архиве.]';
        }

        // Only public quoted text is admitted; provider metadata, links to media
        // and arbitrary nested payload fields never become model instructions.
        $quote = $message->quote;
        $quotedText = is_array($quote) ? data_get($quote, 'content.text', $quote['text'] ?? null) : null;
        if (is_string($quotedText) && trim($quotedText) !== '') {
            $text .= "\n[Цитата в сообщении]: ".$quotedText;
        }

        return [
            'direction' => $message->direction,
            'text' => $text,
            'message_id' => $message->id,
            'occurred_at' => ($message->remote_created_at ?: $message->created_at)?->toIso8601String(),
            'type' => $type,
            'deleted' => $message->deleted_from_avito_at !== null || $message->remote_type === 'deleted',
        ];
    }

    private function assertFits(int $bytes, int $limit): void
    {
        if ($bytes > $limit) {
            throw new AvitoAutoReplyContextUnavailable;
        }
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode($value, self::JSON_FLAGS);
        } catch (JsonException) {
            throw new AvitoAutoReplyContextUnavailable('conversation_context_invalid');
        }
    }
}
