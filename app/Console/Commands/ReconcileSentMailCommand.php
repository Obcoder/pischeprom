<?php

namespace App\Console\Commands;

use App\Models\MailMessage;
use App\Services\Mail\SentCopyPendingException;
use App\Services\Mail\SentMailArchive;
use Illuminate\Console\Command;
use Throwable;

class ReconcileSentMailCommand extends Command
{
    protected $signature = 'mail:reconcile-sent {--mailbox=} {--limit=100}';

    protected $description = 'Retry pending IMAP Sent copies without resending SMTP';

    public function handle(SentMailArchive $archive): int
    {
        $messages = MailMessage::query()->where('delivery_status', 'sent')->whereNull('imap_uid')
            ->whereNotNull('sent_mime_path')->whereIn('sent_copy_status', ['pending', 'failed'])
            ->when($this->option('mailbox'), fn ($q, $mailbox) => $q->where('mailbox', $mailbox))
            ->oldest('updated_at')->limit(max(1, min(500, (int) $this->option('limit'))))->get();
        $failed = 0;
        foreach ($messages as $message) {
            try {
                $archive->archive($message);
                $this->info("{$message->id}: linked");
            } catch (Throwable $exception) {
                $message->forceFill([
                    'sent_copy_status' => $exception instanceof SentCopyPendingException ? 'pending' : 'failed',
                    'sent_copy_error' => 'sent_copy_unavailable',
                ])->save();
                $failed++;
                $this->warn("{$message->id}: copy unavailable; SMTP was not retried");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
