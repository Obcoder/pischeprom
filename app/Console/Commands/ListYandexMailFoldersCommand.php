<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

class ListYandexMailFoldersCommand extends Command
{
    protected $signature = 'mail:yandex-folders';

    protected $description = 'List Yandex IMAP folders';

    public function handle(): int
    {
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

        $this->info('Folders:');

        foreach ($client->getFolders() as $folder) {
            $path = $folder->path ?? null;
            $name = $folder->name ?? null;

            $this->line(sprintf(
                            'path: %s | name: %s',
                            $path ?: '—',
                            $name ?: '—',
                        ));
        }

        $client->disconnect();

        return self::SUCCESS;
    }
}
