<?php

namespace Tests\Feature\Mail;

use App\Jobs\ArchiveSentMailJob;
use App\Models\AuthorizedMailDispatchAttempt;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\Mail\AuthorizedMailDispatchService;
use App\Services\Mail\MailDispatchException;
use App\Services\Mail\SentCopyPendingException;
use App\Services\Mail\SentMailArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class AuthorizedMailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        config(['mail.default' => 'array', 'services.yandex_mail.mailboxes' => [[
            'address' => 'office@example.test', 'from_name' => 'Office',
            'imap' => ['host' => 'imap.example.test', 'sent' => 'Sent'],
        ]]]);
        $archive = Mockery::mock(SentMailArchive::class)->makePartial();
        $archive->shouldReceive('archive')->andThrow(new SentCopyPendingException('Waiting for provider copy.'));
        $this->app->instance(SentMailArchive::class, $archive);
    }

    public function test_transmitted_identity_and_original_are_durable_before_smtp_and_concurrent_same_key_cannot_send(): void
    {
        $actor = $this->actor();
        $payload = $this->payload();
        $transport = Mockery::mock();
        $transport->shouldReceive('getSymfonyTransport')->once()->andReturnSelf();
        $transport->shouldReceive('send')->once()->andReturnUsing(function (Email $mime) use ($actor, $payload) {
            $local = MailMessage::sole();
            $this->assertSame('sending', $local->delivery_status);
            $this->assertSame('<'.$mime->getHeaders()->get('Message-ID')->getId().'>', $local->message_id);
            $this->assertStringNotContainsString('@local.pischeprom', $local->message_id);
            $this->assertStringContainsString($local->message_id, Storage::disk('local')->get($local->sent_mime_path));
            $duplicate = app(AuthorizedMailDispatchService::class)->dispatchMessage($actor, $payload, 'mail-messages.send');
            $this->assertTrue($duplicate['duplicate']);
            $this->assertSame($local->id, $duplicate['mail_message']->id);

            $receipt = new SentMessage($mime, Envelope::create($mime));
            $this->assertSame($receipt->toString(), Storage::disk('local')->get($local->sent_mime_path));

            return $receipt;
        });
        Mail::shouldReceive('mailer')->once()->with('array')->andReturn($transport);

        $result = app(AuthorizedMailDispatchService::class)->dispatchMessage($actor, $payload, 'mail-messages.send');

        $this->assertFalse($result['duplicate']);
        $this->assertSame('sent', MailMessage::sole()->delivery_status);
        $this->assertSame('pending', MailMessage::sole()->sent_copy_status);
        $this->assertNotNull(MailMessage::sole()->smtp_accepted_at);
        $this->assertSame('dispatched', AuthorizedMailDispatchAttempt::sole()->status);
        Queue::assertPushed(ArchiveSentMailJob::class, 1);
    }

    public function test_failed_private_spool_never_calls_smtp(): void
    {
        $archive = Mockery::mock(SentMailArchive::class);
        $archive->shouldReceive('prepare')->once()->andThrow(new RuntimeException('disk full'));
        $archive->shouldNotReceive('archive');
        $this->app->instance(SentMailArchive::class, $archive);
        Mail::shouldReceive('mailer')->never();
        try {
            app(AuthorizedMailDispatchService::class)->dispatchMessage($this->actor(), $this->payload(), 'mail-messages.send');
            $this->fail('Preparation failure must prevent SMTP.');
        } catch (MailDispatchException $exception) {
            $this->assertSame('preparation_failed', $exception->safeCode);
        }
        $this->assertSame('failed', MailMessage::sole()->delivery_status);
        Queue::assertNothingPushed();
    }

    public function test_invalid_reply_preparation_does_not_leave_a_claimed_request_on_repeat(): void
    {
        $actor = $this->actor();
        $payload = [...$this->payload(), 'reply_to_mail_message_id' => 987654];
        Mail::shouldReceive('mailer')->never();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                app(AuthorizedMailDispatchService::class)->dispatchMessage($actor, $payload, 'mail-messages.send');
                $this->fail('A missing reply must be rejected before claiming delivery.');
            } catch (MailDispatchException $exception) {
                $this->assertSame('reply_message_not_authorized', $exception->safeCode);
            }
        }
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
        $this->assertDatabaseCount('mail_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_transport_timeout_is_unknown_and_retrying_same_key_never_submits_again(): void
    {
        $actor = $this->actor();
        $payload = $this->payload();
        $transport = Mockery::mock();
        $transport->shouldReceive('getSymfonyTransport')->once()->andReturnSelf();
        $transport->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP reply lost'));
        Mail::shouldReceive('mailer')->once()->andReturn($transport);
        try {
            app(AuthorizedMailDispatchService::class)->dispatchMessage($actor, $payload, 'mail-messages.send');
            $this->fail('Unknown SMTP outcome must be reported honestly.');
        } catch (MailDispatchException $exception) {
            $this->assertSame('transport_uncertain', $exception->safeCode);
        }
        $this->assertSame('unknown', MailMessage::sole()->delivery_status);
        $duplicate = app(AuthorizedMailDispatchService::class)->dispatchMessage($actor, $payload, 'mail-messages.send');
        $this->assertTrue($duplicate['duplicate']);
        $this->assertStringContainsString('ещё не подтверждён', $duplicate['warning']);
        Queue::assertNothingPushed();
    }

    public function test_database_failure_after_smtp_acceptance_returns_sent_warning_instead_of_false_transport_failure(): void
    {
        $actor = $this->actor();
        $payload = $this->payload();
        $transport = Mockery::mock();
        $transport->shouldReceive('getSymfonyTransport')->once()->andReturnSelf();
        $transport->shouldReceive('send')->once()->andReturnUsing(fn (Email $mime) => new SentMessage($mime, Envelope::create($mime)));
        Mail::shouldReceive('mailer')->once()->andReturn($transport);
        Event::listen('eloquent.saving: '.MailMessage::class, function (MailMessage $message): void {
            if ($message->isDirty('delivery_status') && $message->delivery_status === 'sent') {
                throw new RuntimeException('Synthetic database outage after SMTP acknowledgement.');
            }
        });

        $result = app(AuthorizedMailDispatchService::class)->dispatchMessage($actor, $payload, 'mail-messages.send');

        $this->assertFalse($result['duplicate']);
        $this->assertStringContainsString('SMTP принял письмо', $result['warning']);
        $this->assertSame('sending', MailMessage::sole()->delivery_status);
        $this->assertTrue(app(AuthorizedMailDispatchService::class)->dispatchMessage($actor, $payload, 'mail-messages.send')['duplicate']);
        Queue::assertNothingPushed();
    }

    private function actor(): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'type' => 'employee']);
        Permission::findOrCreate('mail.send', 'crm');
        $user->givePermissionTo('mail.send');

        return $user;
    }

    private function payload(): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'to' => ['buyer@example.test'], 'subject' => 'Manual mail', 'body' => 'Private body'];
    }
}
