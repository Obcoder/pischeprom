<?php

namespace App\Services\Avito;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoMessage;
use App\Models\AvitoMessageAttachment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvitoMessengerMediaArchive
{
    public function __construct(
        private readonly AvitoApiCatalog $catalog,
        private readonly AvitoApiExecutor $executor,
    ) {}

    public function archiveMessage(AvitoMessage $message): int
    {
        $message->loadMissing('chat.account.connection', 'attachments');
        $archived = 0;

        foreach ($message->attachments as $attachment) {
            if ($attachment->archived_at || $attachment->archive_attempts >= 5) {
                continue;
            }

            try {
                $this->archiveAttachment($attachment);
                $archived++;
            } catch (\Throwable $exception) {
                $attachment->update([
                    'archive_attempts' => $attachment->archive_attempts + 1,
                    'last_attempted_at' => now(),
                    'archive_error' => Str::limit($exception->getMessage(), 1000),
                ]);

                if (! $exception instanceof AvitoException && ! $exception instanceof ConnectionException) {
                    report($exception);
                }
            }
        }

        return $archived;
    }

    private function archiveAttachment(AvitoMessageAttachment $attachment): void
    {
        $attachment->loadMissing('message.chat.account.connection');
        $message = $attachment->message;
        $chat = $message->chat;
        $account = $chat->account;
        $url = (string) $attachment->remote_url;

        if ($attachment->kind === 'voice' && $url === '') {
            $capability = $this->catalog->findOperation('messenger', 'getVoiceFiles');
            $result = $this->executor->execute($capability['id'], [
                'path' => ['user_id' => $account->external_user_id],
                'query' => ['voice_ids' => [$attachment->external_id]],
            ], $account->connection);
            $urls = (array) Arr::get($result, 'data.voices_urls', []);
            $url = (string) ($urls[$attachment->external_id] ?? Arr::first($urls) ?? '');

            if (! $result['ok'] || $url === '') {
                throw new AvitoException('Avito не вернул временную ссылку на голосовое сообщение.', 'voice_file', 502, true);
            }

            $attachment->remote_url = $url;
        }

        $this->assertOfficialMediaUrl($url);
        $response = Http::withUserAgent('Pischeprom-Ameise-Avito-Archive/1.0')
            ->connectTimeout((int) config('avito.connect_timeout_seconds'))
            ->timeout((int) config('avito.timeout_seconds'))
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if (! $response->successful()) {
            throw new AvitoException("Медиафайл Avito недоступен (HTTP {$response->status()}).", 'media_download', 502, true);
        }

        $maxBytes = (int) config('avito.messenger.max_attachment_bytes');
        $contentLength = (int) ($response->header('Content-Length') ?: 0);
        $body = $response->body();

        if (($contentLength > 0 && $contentLength > $maxBytes) || strlen($body) > $maxBytes) {
            throw new AvitoException('Медиафайл Avito превышает допустимый размер архива.', 'media_too_large', 422);
        }

        $mime = Str::lower(trim(strtok((string) $response->header('Content-Type'), ';') ?: 'application/octet-stream'));
        $extension = $this->extension($mime, $attachment->kind);
        $path = implode('/', [
            'accounts', hash('sha256', (string) $account->external_user_id),
            'chats', hash('sha256', (string) $chat->external_chat_id),
            'messages', hash('sha256', (string) $message->external_message_id),
            $attachment->kind.'.'.$extension,
        ]);
        $disk = (string) config('avito.messenger.archive_disk', 'avito');
        Storage::disk($disk)->put($path, $body);

        $attachment->update([
            'remote_url' => $url,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => $mime,
            'size_bytes' => strlen($body),
            'archive_attempts' => $attachment->archive_attempts + 1,
            'archived_at' => now(),
            'last_attempted_at' => now(),
            'archive_error' => null,
        ]);
    }

    private function assertOfficialMediaUrl(string $url): void
    {
        $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $port = parse_url($url, PHP_URL_PORT);
        $officialHost = $host === 'avito.ru'
            || Str::endsWith($host, '.avito.ru')
            || $host === 'avito.st'
            || Str::endsWith($host, '.avito.st');

        if ($scheme !== 'https'
            || ! $officialHost
            || ($port !== null && $port !== 443)
            || parse_url($url, PHP_URL_USER) !== null) {
            throw new AvitoException('Ссылка на медиафайл не принадлежит официальному CDN Avito.', 'media_host', 422);
        }
    }

    private function extension(string $mime, string $kind): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/mp4', 'video/mp4' => 'mp4',
            'audio/ogg', 'application/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            default => $kind === 'voice' ? 'mp4' : 'bin',
        };
    }
}
