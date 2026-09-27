<?php

namespace App\Services\Mail;

use App\Models\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

/** Stores copies only. This service must never submit a message to SMTP. */
class SentMailArchive
{
    public function __construct(private readonly MailboxRegistry $mailboxes) {}

    public static function identity(string $value): string
    {
        return trim($value, " \t\r\n<>");
    }

    public static function lockName(string $mailbox, string $messageId): string
    {
        return 'mail-sent-copy:'.hash('sha256', strtolower($mailbox).'|'.self::identity($messageId));
    }

    public function prepare(MailMessage $message, Email $email): void
    {
        // Freeze the multipart tree/boundaries before cloning the archive copy.
        $email->getBody();
        $copy = clone $email;
        // Bcc belongs to the SMTP envelope, not the delivered MIME.
        $copy->getHeaders()->remove('Bcc');
        $path = 'private/mail-sent/'.$message->id.'.eml';
        if (! Storage::disk('local')->put($path, $copy->toString(), ['visibility' => 'private'])) {
            throw new RuntimeException('Sent MIME archive could not be persisted.');
        }
        $message->forceFill(['sent_mime_path' => $path])->save();
    }

    public function archive(MailMessage $message): void
    {
        $target = $message;
        $lock = Cache::lock(self::lockName($message->mailbox, $message->message_id), 120);
        $lock->block(5);
        $client = null;
        try {
            $message = $message->fresh();
            if (! $message || $message->delivery_status !== 'sent' || $message->imap_uid) {
                return;
            }
            if (! $message->sent_mime_path || str_ends_with(self::identity($message->message_id), '@local.pischeprom')) {
                throw new RuntimeException('Original transmitted MIME identity is unavailable.');
            }
            $mailbox = $this->mailboxes->find($message->mailbox);
            if (! $mailbox) {
                throw new RuntimeException('Mailbox is unavailable.');
            }
            $client = $this->client($mailbox);
            $client->connect();
            $folderName = $mailbox['imap']['sent'] ?? 'Sent';
            $folder = $client->getFolder($folderName);
            if (! $folder) {
                throw new RuntimeException('Sent folder is unavailable.');
            }
            $client->openFolder($folder->path);
            $uid = $this->findExactUid($client, $message->message_id);
            $appended = false;
            if ($uid === null) {
                if ($message->smtp_accepted_at && $message->smtp_accepted_at->gt(now()->subSeconds(10))) {
                    throw new SentCopyPendingException('Waiting for the provider Sent copy.');
                }
                if (! $message->is_reconstructed && ! in_array($message->mailbox, config('services.yandex_mail.sent_copy_append_mailboxes', []), true)) {
                    throw new SentCopyPendingException('Provider Sent copy has not appeared.');
                }
                $raw = Storage::disk('local')->get($message->sent_mime_path);
                if (! is_string($raw) || $raw === '') {
                    throw new RuntimeException('Sent MIME archive is unavailable.');
                }
                // APPEND can succeed even if its response is lost. Every retry starts with SEARCH.
                $folder->appendMessage($raw, ['\\Seen'], $message->message_date);
                $appended = true;
                $uid = $this->findExactUid($client, $message->message_id);
                if ($uid === null) {
                    throw new RuntimeException('Server did not confirm the Sent copy.');
                }
            }
            // A sync uses this same Message-ID lock and reuses the local row.
            $message->forceFill([
                'folder' => $folderName, 'imap_uid' => $uid, 'is_seen' => $appended ? true : null,
                'sent_copy_status' => $message->is_reconstructed ? 'reconstructed' : 'linked',
                'sent_copy_error' => null,
            ])->save();
            $target->setRawAttributes($message->getAttributes(), true);
        } finally {
            if ($client) {
                try {
                    $client->disconnect();
                } catch (\Throwable) {
                    // Disconnect cannot change the outcome of a confirmed APPEND.
                }
            }
            $lock->release();
        }
    }

    protected function client(array $mailbox)
    {
        return (new ClientManager)->make($this->mailboxes->imapClientConfig($mailbox));
    }

    /** Refuse to reconstruct historical mail into a folder with unaccounted-for originals. */
    public function assertReconstructionDestination(array $mailbox): void
    {
        $client = $this->client($mailbox);
        try {
            $client->connect();
            $folder = $client->getFolder($mailbox['imap']['sent'] ?? 'Sent');
            if (! $folder) {
                throw new RuntimeException('Sent folder is unavailable.');
            }
            $client->getConnection()->examineFolder($folder->path)->validatedData();
            $uids = array_map('intval', $client->getConnection()->search(['ALL'], IMAP::ST_UID)->validatedData());
            if ($uids === []) {
                return;
            }
            $known = [];
            foreach (MailMessage::query()->where('mailbox', $mailbox['address'])->where('is_reconstructed', true)->get() as $message) {
                if ($uid = $this->findExactUid($client, $message->message_id)) {
                    $known[] = $uid;
                }
            }
            sort($uids);
            sort($known);
            if ($uids !== $known) {
                throw new RuntimeException('Sent contains original or unrecognized messages; reconstruction would risk duplicates.');
            }
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
            }
        }
    }

    public function findExactUid($client, string $messageId): ?int
    {
        $expected = self::identity($messageId);
        if ($expected === '' || preg_match('/[\r\n\x00]/', $expected)) {
            throw new RuntimeException('Invalid sent Message-ID.');
        }
        $connection = $client->getConnection();
        $uids = $connection->search(['HEADER Message-ID '.$connection->escapeString($expected)], IMAP::ST_UID)->validatedData();
        if (! is_array($uids) || count($uids) > 20) {
            throw new RuntimeException('Ambiguous Sent search result.');
        }
        $matches = [];
        foreach ($uids as $uid) {
            $rows = $connection->fetch(['UID', 'BODY.PEEK[HEADER]'], [(int) $uid], null, IMAP::ST_UID)->validatedData();
            $row = $rows[$uid] ?? [];
            $header = $row['BODY[HEADER]'] ?? '';
            $header = preg_replace('/\r?\n[ \t]+/', ' ', $header);
            if (preg_match('/^Message-ID:\s*(.+)$/mi', $header, $match) && self::identity($match[1]) === $expected) {
                $matches[] = (int) $uid;
            }
        }
        if (count($matches) > 1) {
            throw new RuntimeException('More than one exact Sent copy exists; manual reconciliation required.');
        }

        return $matches[0] ?? null;
    }
}
