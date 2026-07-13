<?php

namespace Tests\Feature;

use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MailMessageAttachmentDownloadTest extends TestCase
{
    public function test_saved_mail_attachment_can_be_downloaded(): void
    {
        $this->useInMemoryMailAttachmentTables();

        Storage::fake('local');
        Storage::disk('local')->put('mail/attachments/1/example.txt', 'local file content');

        $mailMessage = MailMessage::query()->create([
            'mailbox' => 'office@example.test',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'imap_uid' => null,
            'subject' => 'Attachment test',
            'from_address' => 'client@example.test',
            'has_attachments' => true,
        ]);

        $attachment = MailMessageAttachment::query()->create([
            'mail_message_id' => $mailMessage->id,
            'disk' => 'local',
            'path' => 'mail/attachments/1/example.txt',
            'original_name' => 'example.txt',
            'file_name' => 'example.txt',
            'mime_type' => 'text/plain',
            'size' => strlen('local file content'),
            'disposition' => 'attachment',
            'saved_to_disk_at' => now(),
        ]);

        $response = $this->get(
            "/api/mail-messages/{$mailMessage->id}/attachments/0/download?attachment_id={$attachment->id}"
        );

        $response->assertOk();
        $response->assertDownload('example.txt');
        $this->assertSame('local file content', $response->streamedContent());
    }

    private function useInMemoryMailAttachmentTables(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('mail_message_attachments');
        Schema::dropIfExists('mail_messages');

        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('mailbox')->index();
            $table->string('folder')->index();
            $table->string('direction')->index();
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('subject')->nullable();
            $table->timestamp('message_date')->nullable()->index();
            $table->string('from_address')->nullable()->index();
            $table->string('from_name')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->text('preview')->nullable();
            $table->longText('html')->nullable();
            $table->longText('text')->nullable();
            $table->timestamp('body_loaded_at')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->longText('raw_headers')->nullable();
        });

        Schema::create('mail_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('mail_message_id');
            $table->string('disk')->default('local')->index();
            $table->string('path', 1024);
            $table->string('original_name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('content_id')->nullable();
            $table->string('disposition', 32)->nullable();
            $table->timestamp('saved_to_disk_at')->nullable()->index();
        });
    }
}
