<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Services\Mail\MailboxRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MailboxRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yandex_mail.mailboxes' => [
                [
                    'address' => 'office@180022.ru',
                    'name' => 'Office',
                    'from_name' => 'Pischeprom',
                    'imap' => [
                        'host' => 'imap.yandex.com',
                        'port' => 993,
                        'encryption' => 'ssl',
                        'username' => 'office@180022.ru',
                        'password' => 'office-secret',
                    ],
                    'smtp' => [
                        'host' => 'smtp.yandex.com',
                        'port' => 465,
                        'encryption' => 'ssl',
                        'username' => 'office@180022.ru',
                        'password' => 'office-secret',
                    ],
                    'folders' => ['INBOX', 'Sent'],
                ],
                [
                    'address' => 'com@food-server.ru',
                    'name' => 'Food Server',
                    'from_name' => 'Pischeprom',
                    'imap' => [
                        'host' => 'imap.beget.com',
                        'port' => 993,
                        'encryption' => 'ssl',
                        'username' => 'com@food-server.ru',
                        'password' => 'food-secret',
                    ],
                    'smtp' => [
                        'host' => 'smtp.beget.com',
                        'port' => 465,
                        'encryption' => 'ssl',
                        'username' => 'com@food-server.ru',
                        'password' => 'food-secret',
                    ],
                    'folders' => ['INBOX', 'Sent'],
                ],
            ],
        ]);
    }

    public function test_it_exposes_configured_mailboxes_without_secrets(): void
    {
        $mailboxes = app(MailboxRegistry::class)->publicMailboxes();

        $this->assertCount(2, $mailboxes);
        $this->assertSame('office@180022.ru', $mailboxes[0]['address']);
        $this->assertSame('com@food-server.ru', $mailboxes[1]['address']);
        $this->assertTrue($mailboxes[0]['is_default']);
        $this->assertStringContainsString('Food Server', $mailboxes[1]['label']);
        $this->assertStringNotContainsString('secret', json_encode($mailboxes, JSON_UNESCAPED_UNICODE));
    }

    public function test_mailboxes_endpoint_returns_configured_mailboxes(): void
    {
        $response = $this->getJson('/api/mailboxes');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.address', 'office@180022.ru')
            ->assertJsonPath('data.1.address', 'com@food-server.ru');
    }

    public function test_it_merges_database_mailboxes_without_exposing_secrets(): void
    {
        $this->useInMemoryMailboxTable();

        Mailbox::query()->create([
            'address' => 'sales@example.test',
            'name' => 'Sales',
            'from_name' => 'Pischeprom Sales',
            'is_default' => true,
            'is_active' => true,
            'imap_host' => 'imap.example.test',
            'imap_username' => 'sales@example.test',
            'imap_password' => 'imap-secret',
            'imap_folders' => ['INBOX', 'Sent'],
            'smtp_host' => 'smtp.example.test',
            'smtp_username' => 'sales@example.test',
            'smtp_password' => 'smtp-secret',
        ]);

        $mailboxes = app(MailboxRegistry::class)->publicMailboxes(management: true);

        $this->assertSame('sales@example.test', $mailboxes[0]['address']);
        $this->assertSame('database', $mailboxes[0]['source']);
        $this->assertTrue($mailboxes[0]['can_edit']);
        $this->assertTrue($mailboxes[0]['is_default']);
        $this->assertTrue($mailboxes[0]['has_imap_password']);
        $this->assertTrue($mailboxes[0]['has_smtp_password']);
        $this->assertArrayNotHasKey('password', $mailboxes[0]['imap']);
        $this->assertArrayNotHasKey('password', $mailboxes[0]['smtp']);
        $this->assertStringNotContainsString('secret', json_encode($mailboxes, JSON_UNESCAPED_UNICODE));
    }

    public function test_mailboxes_endpoint_creates_database_mailboxes(): void
    {
        $this->useInMemoryMailboxTable();

        $response = $this->postJson('/api/mailboxes', [
            'address' => 'support@example.test',
            'name' => 'Support',
            'from_name' => 'Support Team',
            'is_default' => true,
            'is_active' => true,
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'support@example.test',
            'imap_password' => 'imap-secret',
            'imap_folders' => ['INBOX', 'Sent'],
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'support@example.test',
            'smtp_password' => 'smtp-secret',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.address', 'support@example.test')
            ->assertJsonPath('data.has_imap_password', true)
            ->assertJsonMissing(['imap_password' => 'imap-secret'])
            ->assertJsonMissing(['smtp_password' => 'smtp-secret']);

        $this->assertDatabaseHas('mailboxes', [
            'address' => 'support@example.test',
            'is_default' => true,
        ]);

        $index = $this->getJson('/api/mailboxes?management=1&include_inactive=1');

        $index
            ->assertOk()
            ->assertJsonPath('data.0.address', 'support@example.test')
            ->assertJsonMissing(['password' => 'imap-secret'])
            ->assertJsonMissing(['password' => 'smtp-secret']);
    }

    public function test_mailboxes_endpoint_updates_and_deletes_database_mailboxes(): void
    {
        $this->useInMemoryMailboxTable();

        $mailbox = Mailbox::query()->create([
            'address' => 'old@example.test',
            'name' => 'Old',
            'is_active' => true,
            'imap_host' => 'imap.old.test',
            'imap_username' => 'old@example.test',
            'smtp_host' => 'smtp.old.test',
            'smtp_username' => 'old@example.test',
        ]);

        $this->patchJson('/api/mailboxes/'.$mailbox->id, [
            'address' => 'new@example.test',
            'name' => 'New',
            'from_name' => 'New Sender',
            'is_default' => true,
            'is_active' => false,
            'imap_host' => 'imap.new.test',
            'imap_username' => 'new@example.test',
            'imap_folders' => ['INBOX'],
            'smtp_host' => 'smtp.new.test',
            'smtp_username' => 'new@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.address', 'new@example.test')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('mailboxes', [
            'id' => $mailbox->id,
            'address' => 'new@example.test',
            'is_default' => true,
            'is_active' => false,
        ]);

        $this->deleteJson('/api/mailboxes/'.$mailbox->id)
            ->assertOk();

        $this->assertDatabaseMissing('mailboxes', [
            'id' => $mailbox->id,
        ]);
    }

    private function useInMemoryMailboxTable(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('address')->unique();
            $table->string('name')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable()->default(993);
            $table->string('imap_encryption', 32)->nullable()->default('ssl');
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_inbox')->nullable()->default('INBOX');
            $table->string('imap_sent')->nullable()->default('Sent');
            $table->json('imap_folders')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable()->default(465);
            $table->string('smtp_encryption', 32)->nullable()->default('ssl');
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->unsignedSmallInteger('smtp_timeout')->default(30);
            $table->timestamps();
        });
    }
}
