<?php

namespace App\Jobs;

use App\Models\MailMessage;
use App\Services\Mail\SentCopyPendingException;
use App\Services\Mail\SentMailArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ArchiveSentMailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 90;

    public int $uniqueFor = 3600;

    public function __construct(public int $mailMessageId)
    {
        $this->onQueue('mail-sync');
    }

    public function uniqueId(): string
    {
        return (string) $this->mailMessageId;
    }

    public function backoff(): array
    {
        return [30, 60, 300, 600];
    }

    public function handle(SentMailArchive $archive): void
    {
        $message = MailMessage::find($this->mailMessageId);
        if (! $message || $message->imap_uid || $message->delivery_status !== 'sent') {
            return;
        }
        try {
            $archive->archive($message);
        } catch (Throwable $exception) {
            $message->forceFill([
                'sent_copy_status' => $exception instanceof SentCopyPendingException ? 'pending' : 'failed',
                'sent_copy_error' => 'sent_copy_unavailable',
            ])->save();
            // Provider exceptions can include private MIME or credentials. Never serialize those into failed_jobs.
            throw new \RuntimeException('Sent copy is unavailable; SMTP must not be retried.');
        }
    }
}
