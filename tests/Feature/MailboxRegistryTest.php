<?php

namespace Tests\Feature;

use App\Services\Mail\MailboxRegistry;
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
}
