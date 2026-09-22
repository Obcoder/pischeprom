<?php

namespace Tests\Feature\Mail;

use App\Models\Email;
use App\Models\MailMessage;
use App\Models\Unit;
use App\Services\Mail\UnansweredOutgoingMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnansweredOutgoingMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_messages_are_excluded_from_latest_outgoing_and_reply_detection(): void
    {
        $unit = Unit::query()->create(['name' => 'Synthetic mail unit']);
        $email = Email::query()->create(['address' => 'unit@example.test']);
        $unit->emails()->attach($email->id);
        $older = $this->message($email, 'outgoing', 3);
        $reply = $this->message($email, 'incoming', 2);
        $latest = $this->message($email, 'outgoing', 1);
        $service = app(UnansweredOutgoingMailService::class);

        $this->assertSame($latest->id, $service->summarizeForUnits([$unit->id])[$unit->id]['mail_message_id']);
        $latest->delete();
        $summary = $service->summarizeForUnits([$unit->id])[$unit->id];
        $this->assertSame($older->id, $summary['mail_message_id']);
        $this->assertTrue($summary['answered']);

        $reply->delete();
        $summary = $service->summarizeForUnits([$unit->id])[$unit->id];
        $this->assertFalse($summary['answered']);
        $this->assertTrue($summary['is_overdue']);
        $this->assertNull($summary['last_incoming_at']);

        $older->delete();
        $this->assertSame([], $service->summarizeForUnits([$unit->id]));
        $this->assertDatabaseCount('mail_messages', 3);
        $this->assertDatabaseCount('email_mail_message', 3);
    }

    private function message(Email $email, string $direction, int $daysAgo): MailMessage
    {
        $message = MailMessage::query()->create([
            'mailbox' => 'office@example.test',
            'folder' => $direction === 'incoming' ? 'INBOX' : 'Sent',
            'direction' => $direction,
            'subject' => 'Synthetic message',
            'message_date' => now()->subDays($daysAgo),
        ]);
        $message->emails()->attach($email->id, ['role' => $direction === 'incoming' ? 'from' : 'to']);

        return $message;
    }
}
