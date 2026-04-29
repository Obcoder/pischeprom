<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

class DebugYandexMailMessageCommand extends Command
{
    protected $signature = 'mail:yandex-debug-message {folder=INBOX}';

    protected $description = 'Debug first Yandex IMAP message structure';

    public function handle(): int
    {
        $folderName = $this->argument('folder');

        $manager = new ClientManager();

        $client = $manager->make([
                                     'host' => config('services.yandex_mail.imap.host'),
                                     'port' => config('services.yandex_mail.imap.port', 993),
                                     'encryption' => config('services.yandex_mail.imap.encryption', 'ssl'),
                                     'validate_cert' => true,
                                     'username' => config('services.yandex_mail.imap.username'),
                                     'password' => config('services.yandex_mail.imap.password'),
                                     'protocol' => 'imap',
                                 ]);

        $client->connect();

        $folder = $client->getFolder($folderName);

        if (!$folder) {
            $this->error("Folder not found: {$folderName}");

            foreach ($client->getFolders() as $availableFolder) {
                $this->line('path: ' . ($availableFolder->path ?? '—') . ' | name: ' . ($availableFolder->name ?? '—'));
            }

            $client->disconnect();

            return self::FAILURE;
        }

        $messages = $folder
            ->query()
            ->all()
            ->leaveUnread()
            ->setFetchBody(false)
            ->setFetchOrderDesc()
            ->limit(1)
            ->get();

        $message = $messages->first();

        if (!$message) {
            $this->warn('No messages found');

            $client->disconnect();

            return self::SUCCESS;
        }

        $this->info('Class: ' . get_class($message));

        $this->newLine();
        $this->info('Public object vars:');
        dump(get_object_vars($message));

        $this->newLine();
        $this->info('Methods contains:');
        dump(array_values(array_filter(get_class_methods($message), function ($method) {
            return str_contains(strtolower($method), 'subject')
                || str_contains(strtolower($method), 'from')
                || str_contains(strtolower($method), 'to')
                || str_contains(strtolower($method), 'date')
                || str_contains(strtolower($method), 'uid')
                || str_contains(strtolower($method), 'message')
                || str_contains(strtolower($method), 'header');
        })));

        $this->newLine();
        $this->info('Direct properties:');

        foreach ([
                     'uid',
                     'message_id',
                     'messageId',
                     'subject',
                     'date',
                     'from',
                     'to',
                     'cc',
                     'reply_to',
                     'header',
                     'headers',
                 ] as $property) {
            $this->line("PROPERTY {$property}:");
            dump($message->{$property} ?? null);
        }

        $this->newLine();
        $this->info('Getter methods:');

        foreach ([
                     'getUid',
                     'getUID',
                     'getMessageId',
                     'getSubject',
                     'getDate',
                     'getFrom',
                     'getTo',
                     'getCc',
                     'getHeader',
                     'getHeaders',
                 ] as $method) {
            if (method_exists($message, $method)) {
                $this->line("METHOD {$method}:");
                dump($message->{$method}());
            }
        }

        $client->disconnect();

        return self::SUCCESS;
    }
}
