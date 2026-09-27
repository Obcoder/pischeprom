<?php

namespace App\Services\Mail;

use App\Models\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class LegacySentReconstruction
{
    public function __construct(private readonly MailboxRegistry $mailboxes, private readonly SentMailArchive $archive) {}

    public function run(string $address, bool $apply = false): array
    {
        $mailbox = $this->mailboxes->find($address);
        if (! $mailbox) {
            throw new RuntimeException('Mailbox is unavailable.');
        }
        $lock = Cache::lock('legacy-sent-reconstruction:'.hash('sha256', $address), 600);
        $lock->block(5);
        try {
            $this->archive->assertReconstructionDestination($mailbox);
            $messages = MailMessage::query()->where('mailbox', $address)->where('direction', 'outgoing')->whereNull('imap_uid')
                ->where('delivery_status', 'sent')
                ->where(fn ($q) => $q->where('is_reconstructed', true)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('sent_mime_path')->whereNull('smtp_accepted_at')))
                ->withCount('attachments')->orderBy('id')->get()
                ->filter(fn (MailMessage $message) => $message->is_reconstructed || LegacySentIdentity::matches($message->message_id));
            $result = [];
            foreach ($messages as $message) {
                if ($message->has_attachments || $message->attachments_count) {
                    $result[] = ['id' => $message->id, 'status' => 'skipped', 'reason' => 'attachment_completeness_unproven'];

                    continue;
                }
                if (! $message->body_loaded_at || (! filled($message->text) && ! filled($message->html))) {
                    $result[] = ['id' => $message->id, 'status' => 'skipped', 'reason' => 'body_unavailable'];

                    continue;
                }
                if (strtolower((string) $message->from_address) !== $address || empty($message->to) || ! $message->message_date) {
                    $result[] = ['id' => $message->id, 'status' => 'skipped', 'reason' => 'envelope_unavailable'];

                    continue;
                }
                $mime = $this->mime($message, $address);
                if ($apply) {
                    // Recheck before every APPEND in case another client added an original.
                    $this->archive->assertReconstructionDestination($mailbox);
                    if (! $message->is_reconstructed || ! $message->sent_mime_path) {
                        DB::transaction(function () use ($message, $mime): void {
                            $message->forceFill([
                                'message_id' => '<'.$mime->getHeaders()->get('Message-ID')->getId().'>',
                                'is_reconstructed' => true, 'delivery_status' => 'sent', 'sent_copy_status' => 'pending',
                            ])->save();
                            $this->archive->prepare($message, $mime);
                        });
                    }
                    $this->archive->archive($message);
                }
                $result[] = ['id' => $message->id, 'status' => $apply ? 'reconstructed' : 'eligible', 'reason' => null];
            }

            return $result;
        } finally {
            $lock->release();
        }
    }

    private function mime(MailMessage $message, string $address): Email
    {
        $mime = (new Email)->from(new Address($address, $message->from_name ?: ''))
            ->subject($message->subject ?: '(без темы)')->date($message->message_date->toDateTimeImmutable());
        foreach (['to', 'cc'] as $field) {
            $addresses = array_map(fn ($value) => new Address($value['address'], $value['name'] ?? ''), $message->{$field} ?: []);
            if ($addresses) {
                $mime->{$field}(...$addresses);
            }
        }
        if ($message->text !== null) {
            $mime->text($message->text);
        }
        if ($message->html !== null) {
            $mime->html($message->html);
        }
        $mime->getHeaders()->addIdHeader('Message-ID', 'pischeprom-reconstructed-'.$message->id.'@'.substr(strrchr($address, '@'), 1));
        $mime->getHeaders()->addTextHeader('X-Pischeprom-Reconstructed-From', (string) $message->id);
        $mime->getHeaders()->addTextHeader('X-Pischeprom-Archive-Type', 'reconstructed; not an original SMTP message');
        $mime->getHeaders()->addTextHeader('X-Pischeprom-Legacy-Message-ID', $message->message_id);

        return $mime;
    }
}
