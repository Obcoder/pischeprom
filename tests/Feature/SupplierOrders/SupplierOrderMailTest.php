<?php

namespace Tests\Feature\SupplierOrders;

use App\Jobs\ArchiveSentMailJob;
use App\Models\AuthorizedMailDispatchAttempt;
use App\Models\Good;
use App\Models\MailMessage;
use App\Models\Measure;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Models\User;
use App\Services\Mail\AuthorizedMailDispatchService;
use App\Services\Mail\MailDispatchException;
use App\Services\Mail\PreparedMailContent;
use App\Services\Mail\SentCopyPendingException;
use App\Services\Mail\SentMailArchive;
use App\Services\SupplierOrders\SupplierOrderMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class SupplierOrderMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('yandex');
        Queue::fake();
        config(['mail.default' => 'array', 'services.yandex_mail.mailboxes' => [[
            'address' => 'office@example.test', 'from_name' => 'Office',
            'imap' => ['host' => 'imap.example.test', 'sent' => 'Sent'],
        ]]]);
        $this->pendingArchive();
    }

    public function test_preview_normalizes_names_escapes_html_and_does_not_save_or_send(): void
    {
        Mail::shouldReceive('mailer')->never();
        $actor = $this->actor();
        $good = Good::create(['name' => 'Брокколи <fresh>']);
        $unit = Unit::create(['name' => 'Поставщик']);

        $response = $this->actingAs($actor)->postJson('/api/supplier-orders/preview', [
            'unit_id' => $unit->id,
            'body' => "Здравствуйте!\n<script>alert(1)</script>",
            'internal_note' => 'internal-only-note',
            'include_prices' => false,
            'delivery_date' => '2026-10-20',
            'items' => [[
                'good_id' => $good->id, 'name' => 'client-supplied-false-name',
                'quantity' => 12.500001, 'measurement' => $good->measurement(),
                'note' => 'Без <повреждений>',
            ]],
        ])->assertOk();

        $this->assertStringContainsString('Брокколи &lt;fresh&gt;', $response->json('html'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('html'));
        $this->assertStringNotContainsString('<script>', $response->json('html'));
        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('12,500001', $response->json($format));
            $this->assertStringContainsString('20.10.2026', $response->json($format));
            $this->assertStringNotContainsString('internal-only-note', $response->json($format));
            $this->assertStringNotContainsString('client-supplied-false-name', $response->json($format));
            $this->assertStringContainsString('Цена за', $response->json($format));
            $this->assertStringContainsString('Уточнить', $response->json($format));
        }
        $this->assertDatabaseCount('supplier_orders', 0);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
        $this->assertDatabaseCount('mail_messages', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_send_uses_saved_snapshot_and_concurrent_other_actor_cannot_send_again(): void
    {
        $actor = $this->actor();
        $other = $this->actor();
        $order = $this->draft($actor);
        $order->update(['include_prices' => false]);
        $this->acceptMail(function (Email $mime) use ($order, $other): void {
            $claimed = $order->fresh();
            $this->assertSame('sending', $claimed->status);
            $this->assertNotNull($claimed->sending_key);
            $this->assertNotNull($claimed->sending_started_at);
            $this->assertSame('Сохранённый заказ', $mime->getSubject());
            $this->assertSame('supplier@example.test', $mime->getTo()[0]->getAddress());
            $this->assertSame('office@example.test', $mime->getFrom()[0]->getAddress());
            $this->assertStringContainsString('<table', $mime->getHtmlBody());
            $this->assertStringContainsString('Брокколи &lt;fresh&gt;', $mime->getHtmlBody());
            $this->assertStringContainsString('&lt;script&gt;', $mime->getHtmlBody());
            foreach ([$mime->getHtmlBody(), $mime->getTextBody()] as $body) {
                $this->assertStringContainsString('12,500001', $body);
                $this->assertStringContainsString('1 234,56 RUB', $body);
                $this->assertStringContainsString('15 432,0012 RUB', $body);
                foreach (['internal-only-note', '987654321', 'private-client-name', 'customer-sales-price'] as $private) {
                    $this->assertStringNotContainsString($private, $body);
                }
            }
            $duplicate = app(SupplierOrderMailSender::class)->send($other, $claimed, $claimed->revision);
            $this->assertSame('sending', $duplicate['order']->status);
            $this->assertNotEmpty($duplicate['warning']);
        });

        $this->actingAs($actor)->postJson("/api/supplier-orders/{$order->id}/send", ['revision' => $order->revision])
            ->assertOk()->assertJsonPath('order.status', 'sent');

        $order->refresh();
        $this->assertNotNull($order->sent_at);
        $this->assertSame(MailMessage::sole()->id, $order->mail_message_id);
        $this->assertSame('sent', MailMessage::sole()->delivery_status);
        $this->assertSame('pending', MailMessage::sole()->sent_copy_status);
        $this->assertSame('dispatched', AuthorizedMailDispatchAttempt::sole()->status);
        $this->assertSame($order->unit_id, AuthorizedMailDispatchAttempt::sole()->unit_id);
        $duplicate = app(SupplierOrderMailSender::class)->send($other, $order, $order->revision);
        $this->assertSame('sent', $duplicate['order']->status);
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 1);
        Queue::assertPushed(ArchiveSentMailJob::class, 1);
        Http::assertNothingSent();
    }

    public function test_saved_preview_preserves_historical_items_and_prices_despite_catalog_changes_and_query_overrides(): void
    {
        Mail::shouldReceive('mailer')->never();
        $actor = $this->actor();
        $boxes = Measure::firstOrCreate(['name' => 'коробка']);
        $this->actingAs($actor);

        foreach ([false, true] as $includePrices) {
            $order = $this->draft($actor);
            $order->update(['include_prices' => $includePrices, 'status' => 'sent', 'sent_at' => now()]);
            $savedRevision = $order->revision;
            $good = $order->items()->sole()->good;
            $good->update(['name' => 'Current changed catalog name', 'measure_id' => $boxes->id, 'unit_weight_kg' => 10]);
            if ($includePrices) {
                $good->delete();
                $this->assertNull($order->items()->sole()->good_id);
            }
            $query = http_build_query([
                'body' => 'query-body-must-not-replace-snapshot', 'include_prices' => (int) ! $includePrices,
                'items' => [['name' => 'query-item-must-not-replace-snapshot', 'quantity' => 999999, 'unit_label' => 'шт']],
            ]);

            $response = $this->getJson("/api/supplier-orders/{$order->id}/preview?{$query}")->assertOk();

            $this->assertStringContainsString('Брокколи &lt;fresh&gt;', $response->json('html'));
            $this->assertStringContainsString('Количество: 12,500001 кг', $response->json('text'));
            foreach (['html', 'text'] as $format) {
                $content = $response->json($format);
                $this->assertStringContainsString('Просим подтвердить.', $content);
                $this->assertStringContainsString('20.10.2026', $content);
                foreach (['Current changed catalog name', 'коробка', 'query-body-must-not-replace-snapshot', 'query-item-must-not-replace-snapshot', '999999', 'internal-only-note', '987654321', 'private-client-name', 'customer-sales-price'] as $absent) {
                    $this->assertStringNotContainsString($absent, $content);
                }
                $this->assertStringContainsString('1 234,56 RUB', $content);
                $this->assertStringContainsString('15 432,0012 RUB', $content);
            }
            $this->assertSame('sent', $order->fresh()->status);
            $this->assertSame($savedRevision, $order->fresh()->revision);
            $this->assertSame($includePrices, $order->fresh()->include_prices);
        }

        $this->assertDatabaseCount('supplier_orders', 2);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
        $this->assertDatabaseCount('mail_messages', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_send_requires_verified_staff_mail_permission_and_access_to_supplier(): void
    {
        Mail::shouldReceive('mailer')->never();
        $order = $this->draft($this->actor());
        $url = "/api/supplier-orders/{$order->id}/send";
        $payload = ['revision' => $order->revision];

        $this->postJson($url, $payload)->assertUnauthorized();
        $this->actingAs($this->actor(verified: false))->postJson($url, $payload)->assertForbidden();
        $this->actingAs($this->actor(permissions: ['ai_sales.view']))->postJson($url, $payload)->assertForbidden();
        $this->actingAs($this->actor(permissions: ['mail.send']))->postJson($url, $payload)->assertForbidden();
        $customer = $this->actor();
        $customer->update(['type' => 'customer']);
        $this->actingAs($customer)->postJson($url, $payload)->assertForbidden();

        $this->assertSame('draft', $order->fresh()->status);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
    }

    public function test_uploaded_attachment_survives_saved_mail_validation_and_reaches_smtp(): void
    {
        $actor = $this->actor();
        $order = $this->draft($actor);
        $contents = 'Supplier attachment: deliver intact boxes.';
        $this->acceptMail(function (Email $mime) use ($contents): void {
            $attachments = $mime->getAttachments();
            $this->assertCount(1, $attachments);
            $this->assertSame('instructions.txt', $attachments[0]->getFilename());
            $this->assertSame('text', $attachments[0]->getMediaType());
            $this->assertSame('plain', $attachments[0]->getMediaSubtype());
            $this->assertStringContainsString(base64_encode($contents), $mime->toString());
        });

        $this->actingAs($actor)->postJson("/api/supplier-orders/{$order->id}/send", [
            'revision' => $order->revision,
            'attachments' => [UploadedFile::fake()->createWithContent('instructions.txt', $contents)],
        ])->assertOk()->assertJsonPath('order.status', 'sent');

        $this->assertSame(1, AuthorizedMailDispatchAttempt::sole()->attachment_count);
        $this->assertTrue(MailMessage::sole()->has_attachments);
        $attachment = MailMessage::sole()->attachments()->sole();
        $this->assertSame('instructions.txt', $attachment->original_name);
        $this->assertSame($contents, Storage::disk($attachment->disk)->get($attachment->path));
    }

    public function test_forbidden_uploaded_mime_cannot_claim_or_send_mail(): void
    {
        Mail::shouldReceive('mailer')->never();
        $actor = $this->actor();
        $order = $this->draft($actor);

        $this->actingAs($actor)->postJson("/api/supplier-orders/{$order->id}/send", [
            'revision' => $order->revision,
            'attachments' => [UploadedFile::fake()->createWithContent('payload.html', '<!doctype html><html><script>alert(1)</script></html>')],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

        $this->assertSame('draft', $order->fresh()->status);
        $this->assertNull($order->fresh()->sending_key);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_send_rejects_stale_revision_and_cannot_override_saved_email_or_unsafe_paths(): void
    {
        Mail::shouldReceive('mailer')->never();
        $actor = $this->actor();
        $order = $this->draft($actor);
        $url = "/api/supplier-orders/{$order->id}/send";

        $this->actingAs($actor)->postJson($url, ['revision' => $order->revision + 1])->assertConflict();
        $this->postJson($url, [
            'revision' => $order->revision, 'to' => ['attacker@example.test'],
            'body' => 'Unreviewed replacement', 'html' => '<script>bad()</script>',
            'subject' => "Injected\r\nBcc: victim@example.test", 'from' => 'attacker@example.test',
            'storage_files' => ['../../.env'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['to', 'body', 'html', 'subject', 'from', 'storage_files']);

        $this->assertSame('draft', $order->fresh()->status);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
    }

    public function test_saved_invalid_recipient_is_rejected_without_claiming_delivery(): void
    {
        Mail::shouldReceive('mailer')->never();
        $actor = $this->actor();
        $order = $this->draft($actor);
        $order->update(['to' => []]);

        $this->actingAs($actor)->postJson("/api/supplier-orders/{$order->id}/send", ['revision' => $order->revision])
            ->assertUnprocessable();

        $this->assertSame('draft', $order->fresh()->status);
        $this->assertNull($order->fresh()->sending_key);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
    }

    public function test_preparation_failure_returns_draft_and_deliberate_retry_uses_new_key(): void
    {
        $actor = $this->actor();
        $order = $this->draft($actor);
        $failedKey = null;
        $archive = Mockery::mock(SentMailArchive::class);
        $archive->shouldReceive('prepare')->once()->andReturnUsing(function () use ($order, &$failedKey): never {
            $failedKey = $order->fresh()->sending_key;
            throw new RuntimeException('disk full');
        });
        $archive->shouldNotReceive('archive');
        $this->app->instance(SentMailArchive::class, $archive);

        $this->dispatchFails(fn () => app(SupplierOrderMailSender::class)->send($actor, $order, $order->revision), 'preparation_failed');

        $order->refresh();
        $this->assertSame('draft', $order->status);
        $this->assertNull($order->sent_at);
        $this->assertSame('failed', AuthorizedMailDispatchAttempt::sole()->status);
        $this->assertSame('failed', MailMessage::sole()->delivery_status);

        $this->pendingArchive();
        $this->acceptMail(function () use ($order, $failedKey): void {
            $this->assertNotSame($failedKey, $order->fresh()->sending_key);
        });
        $result = app(SupplierOrderMailSender::class)->send($actor, $order, $order->revision);

        $this->assertSame('sent', $result['order']->status);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 2);
        $this->assertSame(1, MailMessage::where('delivery_status', 'sent')->count());
    }

    public function test_timeout_blocks_retries_by_other_staff_and_reconcile_does_not_send(): void
    {
        $actor = $this->actor();
        $other = $this->actor();
        $order = $this->draft($actor);
        $transport = Mockery::mock();
        $transport->shouldReceive('getSymfonyTransport')->once()->andReturnSelf();
        $transport->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP reply lost'));
        Mail::shouldReceive('mailer')->once()->with('array')->andReturn($transport);

        $this->dispatchFails(fn () => app(SupplierOrderMailSender::class)->send($actor, $order, $order->revision), 'transport_uncertain');

        $order->refresh();
        $this->assertSame('send_unknown', $order->status);
        $this->assertNull($order->sent_at);
        $key = $order->sending_key;
        $retry = app(SupplierOrderMailSender::class)->send($other, $order, $order->revision);
        $this->assertSame('send_unknown', $retry['order']->status);
        $this->assertNotEmpty($retry['warning']);
        $reconciled = app(SupplierOrderMailSender::class)->reconcile($other, $order);
        $this->assertSame('send_unknown', $reconciled['order']->status);
        $this->assertNotEmpty($reconciled['warning']);
        $this->assertSame($key, $order->fresh()->sending_key);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 1);
        $this->assertDatabaseCount('mail_messages', 1);
        Queue::assertNothingPushed();
    }

    public function test_database_failure_after_smtp_acceptance_cannot_be_mistaken_for_sent_or_retryable(): void
    {
        $actor = $this->actor();
        $order = $this->draft($actor);
        $this->acceptMail();
        Event::listen('eloquent.saving: '.MailMessage::class, function (MailMessage $message): void {
            if ($message->isDirty('delivery_status') && $message->delivery_status === 'sent') {
                throw new RuntimeException('Synthetic database outage after SMTP acknowledgement.');
            }
        });

        $result = app(SupplierOrderMailSender::class)->send($actor, $order, $order->revision);

        $this->assertNotEmpty($result['warning']);
        $this->assertSame('send_unknown', $order->fresh()->status);
        $this->assertNull($order->fresh()->sent_at);
        $this->assertSame('sending', MailMessage::sole()->delivery_status);
        $this->assertNull(MailMessage::sole()->smtp_accepted_at);
        $this->assertSame('claimed', AuthorizedMailDispatchAttempt::sole()->status);
        $order->refresh();
        app(SupplierOrderMailSender::class)->send($actor, $order, $order->revision);
        $reconciled = app(SupplierOrderMailSender::class)->reconcile($actor, $order);
        $this->assertSame('send_unknown', $reconciled['order']->status);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 1);
        Queue::assertNothingPushed();
    }

    public function test_reconcile_recovers_durable_delivery_when_order_finalization_failed(): void
    {
        $actor = $this->actor();
        $order = $this->draft($actor);
        $this->acceptMail();
        $failOnce = true;
        Event::listen('eloquent.saving: '.SupplierOrder::class, function (SupplierOrder $saved) use (&$failOnce): void {
            if ($failOnce && $saved->isDirty('status') && $saved->status === 'sent') {
                $failOnce = false;
                throw new RuntimeException('Synthetic supplier order update outage.');
            }
        });

        $result = app(SupplierOrderMailSender::class)->send($actor, $order, $order->revision);

        $this->assertNotEmpty($result['warning']);
        $this->assertContains($order->fresh()->status, ['sending', 'send_unknown']);
        $this->assertSame('sent', MailMessage::sole()->delivery_status);
        $this->assertSame('dispatched', AuthorizedMailDispatchAttempt::sole()->status);
        $this->actingAs($actor)->postJson("/api/supplier-orders/{$order->id}/reconcile")
            ->assertOk()->assertJsonPath('order.status', 'sent');
        $this->assertSame(MailMessage::sole()->id, $order->fresh()->mail_message_id);
        $this->assertNotNull($order->fresh()->sent_at);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 1);
    }

    public function test_orphaned_claim_blocks_other_actor_send_and_reconcile_without_reopening_draft(): void
    {
        Mail::shouldReceive('mailer')->never();
        $actor = $this->actor();
        $other = $this->actor();
        $order = $this->draft($actor);
        $order->update([
            'status' => 'sending', 'sending_key' => (string) Str::uuid(),
            'sending_user_id' => $actor->id, 'sending_started_at' => now()->subHour(),
        ]);

        $retry = app(SupplierOrderMailSender::class)->send($other, $order, $order->revision);
        $this->assertSame('sending', $retry['order']->status);
        $this->assertNotEmpty($retry['warning']);
        $result = app(SupplierOrderMailSender::class)->reconcile($other, $order);

        $this->assertSame('sending', $result['order']->status);
        $this->assertNotEmpty($result['warning']);
        $this->assertSame($order->sending_key, $order->fresh()->sending_key);
        $this->assertSame($actor->id, $order->fresh()->sending_user_id);
        $this->assertSame($order->revision, $order->fresh()->revision);
        $this->assertNull($order->fresh()->sent_at);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_known_mailbox_failure_before_dispatch_claim_safely_reopens_draft(): void
    {
        Mail::shouldReceive('mailer')->never();
        $actor = $this->actor();
        $order = $this->draft($actor);
        $order->update(['mailbox' => 'unconfigured@example.test']);

        $this->dispatchFails(fn () => app(SupplierOrderMailSender::class)->send($actor, $order, $order->revision), 'mailbox_not_allowlisted');

        $order->refresh();
        $this->assertSame('draft', $order->status);
        $this->assertNull($order->sending_key);
        $this->assertNull($order->sending_user_id);
        $this->assertNull($order->sending_started_at);
        $this->assertNull($order->sent_at);
        $this->assertSame('mailbox_not_allowlisted', $order->send_error_code);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertSame('draft', app(SupplierOrderMailSender::class)->reconcile($actor, $order)['order']->status);
        $this->assertDatabaseCount('authorized_mail_dispatch_attempts', 0);
    }

    public function test_prepared_content_binds_both_html_and_text_to_the_dispatch_key(): void
    {
        $actor = $this->actor();
        $payload = ['idempotency_key' => (string) Str::uuid(), 'to' => ['supplier@example.test'],
            'subject' => 'Prepared supplier content', 'body' => 'Unchanged source body'];
        $this->acceptMail(function (Email $mime): void {
            $this->assertSame('<table><tr><td>Saved item</td></tr></table>', $mime->getHtmlBody());
            $this->assertSame('Saved item', $mime->getTextBody());
        });
        $service = app(AuthorizedMailDispatchService::class);
        $content = new PreparedMailContent('<table><tr><td>Saved item</td></tr></table>', 'Saved item');
        $service->dispatchMessage($actor, $payload, 'supplier-orders.test', preparedContent: $content);
        $this->assertTrue($service->dispatchMessage($actor, $payload, 'supplier-orders.test', preparedContent: $content)['duplicate']);

        foreach ([new PreparedMailContent('<p>Changed HTML</p>', $content->text), new PreparedMailContent($content->html, 'Changed text')] as $changed) {
            $this->dispatchFails(fn () => $service->dispatchMessage($actor, $payload, 'supplier-orders.test', preparedContent: $changed), 'idempotency_conflict');
        }
        $this->assertDatabaseCount('mail_messages', 1);
    }

    private function actor(array $permissions = ['mail.send', 'ai_sales.view'], bool $verified = true): User
    {
        $user = User::factory()->create([
            'email_verified_at' => $verified ? now() : null, 'status' => 'active', 'type' => 'employee',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'crm');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function draft(User $actor): SupplierOrder
    {
        $good = Good::create(['name' => 'Брокколи <fresh>']);
        $unit = Unit::create(['name' => 'Поставщик', 'is_supplier' => true]);
        $order = SupplierOrder::create([
            'unit_id' => $unit->id, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            'create_key' => (string) Str::uuid(), 'create_payload_hash' => hash('sha256', 'test draft'),
            'subject' => 'Сохранённый заказ', 'body' => "Просим подтвердить.\n<script>safe text</script>",
            'to' => ['supplier@example.test'], 'cc' => [], 'mailbox' => 'office@example.test',
            'delivery_date' => '2026-10-20', 'internal_note' => 'internal-only-note',
        ]);
        $order->items()->create([
            'position' => 0, 'good_id' => $good->id, 'name' => $good->name,
            'measure_id' => $good->measure_id, 'unit_label' => 'кг', 'kilograms_per_unit' => 1,
            'quantity' => 12.500001, 'unit_price' => 1234.56, 'currency_code' => 'RUB',
            'note' => 'Без повреждений', 'source_order_item_ids' => [987654321],
            'quotation_snapshot' => ['customer_name' => 'private-client-name', 'customer_price' => 'customer-sales-price'],
        ]);

        return $order;
    }

    private function pendingArchive(): void
    {
        $archive = Mockery::mock(SentMailArchive::class)->makePartial();
        $archive->shouldReceive('archive')->andThrow(new SentCopyPendingException('Waiting for provider copy.'));
        $this->app->instance(SentMailArchive::class, $archive);
    }

    private function acceptMail(?callable $inspect = null): void
    {
        $transport = Mockery::mock();
        $transport->shouldReceive('getSymfonyTransport')->once()->andReturnSelf();
        $transport->shouldReceive('send')->once()->andReturnUsing(function (Email $mime) use ($inspect): SentMessage {
            $inspect && $inspect($mime);

            return new SentMessage($mime, Envelope::create($mime));
        });
        Mail::shouldReceive('mailer')->once()->with('array')->andReturn($transport);
    }

    private function dispatchFails(callable $dispatch, string $code): void
    {
        try {
            $dispatch();
            $this->fail('Dispatch must report '.$code.'.');
        } catch (MailDispatchException $exception) {
            $this->assertSame($code, $exception->safeCode);
        }
    }
}
