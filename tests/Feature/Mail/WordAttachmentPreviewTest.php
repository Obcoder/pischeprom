<?php

namespace Tests\Feature\Mail;

use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Models\User;
use App\Services\Mail\WordAttachmentPreviewer;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class WordAttachmentPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Storage::fake('local');
        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->softDeletes();
            $table->timestamps();
            $table->string('mailbox');
            $table->string('folder');
            $table->string('direction');
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('subject')->nullable();
            $table->string('from_address')->nullable();
            $table->boolean('has_attachments')->default(true);
        });
        Schema::create('mail_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('mail_message_id');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
        });
    }

    public function test_preview_requires_a_verified_active_employee(): void
    {
        $message = $this->message();
        $this->postJson($this->url($message))->assertUnauthorized();

        foreach ([['email_verified_at' => null], ['status' => 'blocked'], ['type' => 'customer']] as $changes) {
            $this->actingAs($this->employee($changes))->postJson($this->url($message))->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_a_saved_private_doc_returns_real_preview_and_does_not_publish_the_file(): void
    {
        $message = $this->message();
        $attachment = $this->attachment($message, $this->doc());
        $mailbox = Mockery::mock(YandexMailboxService::class);
        $mailbox->shouldNotReceive('downloadAttachment');
        $this->app->instance(YandexMailboxService::class, $mailbox);

        $response = $this->actingAs($this->employee())->postJson($this->url($message), ['attachment_id' => $attachment->id])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonPath('format', 'doc')->assertJsonPath('has_text', true)
            ->assertJsonPath('text', trim(file_get_contents(base_path('tests/Fixtures/Mail/word-preview-cyrillic.txt'))));

        $this->assertStringNotContainsString('mail/attachments', $response->getContent());
        $this->assertStringNotContainsString('http://', $response->json('html'));
        $this->assertDatabaseCount('mail_message_attachments', 1);
        $this->assertSame([$attachment->path], Storage::disk('local')->allFiles());
        Http::assertNothingSent();
    }

    public function test_attachment_ids_are_scoped_to_the_selected_message(): void
    {
        $message = $this->message();
        $otherAttachment = $this->attachment($this->message(), $this->doc());
        $mailbox = Mockery::mock(YandexMailboxService::class);
        $mailbox->shouldNotReceive('downloadAttachment');
        $this->app->instance(YandexMailboxService::class, $mailbox);

        $this->actingAs($this->employee())->postJson($this->url($message), ['attachment_id' => $otherAttachment->id])->assertNotFound();
        $this->postJson($this->url($message), ['attachment_id' => 999])->assertNotFound();
        $this->postJson($this->url($message), ['attachment_id' => '../secret'])->assertUnprocessable();
    }

    public function test_an_unsaved_imap_doc_can_be_previewed_without_creating_public_files(): void
    {
        $message = $this->message(['imap_uid' => 42]);
        $mailbox = Mockery::mock(YandexMailboxService::class);
        $mailbox->shouldReceive('downloadAttachment')->once()->with(Mockery::on(fn (MailMessage $model): bool => $model->is($message)), 2)
            ->andReturn(['name' => 'invoice.doc', 'content' => $this->doc()]);
        $this->app->instance(YandexMailboxService::class, $mailbox);

        $this->actingAs($this->employee())->postJson($this->url($message, 2))->assertOk()->assertJsonPath('has_text', true);
        $this->assertDatabaseCount('mail_message_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_size_limits_use_actual_bytes_and_safe_errors_do_not_expose_storage_details(): void
    {
        $message = $this->message();
        $attachment = $this->attachment($message, $this->doc());
        Storage::disk('local')->put($attachment->path, str_repeat('x', WordAttachmentPreviewer::MAX_FILE_BYTES + 1));

        $this->actingAs($this->employee())->postJson($this->url($message))->assertUnprocessable()
            ->assertJsonPath('message', 'Для предпросмотра выберите непустой Word-документ размером до 10 МБ.');

        Storage::disk('local')->delete($attachment->path);
        $response = $this->postJson($this->url($message))->assertNotFound();
        $this->assertStringNotContainsString($attachment->path, $response->getContent());
    }

    public function test_provider_exceptions_are_redacted_and_parallel_previews_are_rejected(): void
    {
        $message = $this->message(['imap_uid' => 42]);
        $mailbox = Mockery::mock(YandexMailboxService::class);
        $mailbox->shouldReceive('downloadAttachment')->once()->andThrow(new \RuntimeException('imap password=secret-do-not-expose'));
        $this->app->instance(YandexMailboxService::class, $mailbox);

        $response = $this->actingAs($this->employee())->postJson($this->url($message))->assertStatus(503);
        $this->assertStringNotContainsString('secret-do-not-expose', $response->getContent());
        $lock = Cache::lock('mail-word-preview:'.$message->id.':index-0', 60);
        $this->assertTrue($lock->get());
        try {
            $this->postJson($this->url($message))->assertTooManyRequests();
        } finally {
            $lock->release();
        }
    }

    private function employee(array $changes = []): User
    {
        return (new User)->forceFill(['id' => 123, 'name' => 'Preview reviewer', 'email' => 'reviewer@example.test', 'email_verified_at' => now(), 'status' => 'active', 'type' => 'employee', ...$changes]);
    }

    private function message(array $changes = []): MailMessage
    {
        return MailMessage::query()->create(['mailbox' => 'office@example.test', 'folder' => 'INBOX', 'direction' => 'incoming', 'from_address' => 'supplier@example.test', ...$changes]);
    }

    private function attachment(MailMessage $message, string $content): MailMessageAttachment
    {
        $path = 'mail/attachments/'.$message->id.'/invoice.doc';
        Storage::disk('local')->put($path, $content);

        return MailMessageAttachment::query()->create(['mail_message_id' => $message->id, 'disk' => 'local', 'path' => $path, 'original_name' => 'invoice.doc', 'mime_type' => 'application/msword', 'size' => strlen($content)]);
    }

    private function doc(): string
    {
        return file_get_contents(base_path('tests/Fixtures/Mail/word-preview-cyrillic.doc'));
    }

    private function url(MailMessage $message, int $index = 0): string
    {
        return "/api/mail-messages/{$message->id}/attachments/{$index}/word-preview";
    }
}
