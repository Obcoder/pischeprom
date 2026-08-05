<?php

namespace App\Console\Commands;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Services\Avito\AvitoApiExecutor;
use App\Services\Avito\AvitoTokenManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AvitoPreflightCommand extends Command
{
    protected $signature = 'avito:preflight {--schema : Require the integration database tables} {--live : Validate credentials against /core/v1/accounts/self}';

    protected $description = 'Validate the Avito API catalog, configuration, schema, and optional live access';

    public function handle(AvitoApiCatalog $catalog, AvitoTokenManager $tokens, AvitoApiExecutor $executor): int
    {
        $snapshot = $catalog->snapshot();
        $capabilities = $snapshot['capabilities'];
        $sections = $snapshot['sections'];

        if (count($sections) !== 25 || count($capabilities) !== 245) {
            $this->error('Каталог Avito неполный: ожидается 25 разделов и 245 функций.');

            return self::FAILURE;
        }

        $allowedHosts = (array) config('avito.allowed_hosts');
        $unknownHosts = collect($capabilities)
            ->map(fn (array $item) => parse_url($item['server'], PHP_URL_HOST))
            ->filter(fn ($host) => ! in_array($host, $allowedHosts, true))
            ->unique()
            ->values();

        if ($unknownHosts->isNotEmpty()) {
            $this->error('Каталог содержит недоверенные hosts: '.$unknownHosts->implode(', '));

            return self::FAILURE;
        }

        if ($this->option('schema')) {
            foreach ([
                'avito_connections',
                'avito_capability_settings',
                'avito_api_calls',
                'avito_webhook_events',
                'avito_messenger_accounts',
                'avito_chats',
                'avito_messages',
                'avito_message_attachments',
                'avito_messenger_sync_runs',
                'avito_contact_candidates',
                'avito_chat_order',
            ] as $table) {
                if (! Schema::hasTable($table)) {
                    $this->error("Отсутствует таблица {$table}.");

                    return self::FAILURE;
                }
            }

            foreach ([
                'avito_chats' => ['entity_id'],
                'avito_messages' => ['crm_scanned_at'],
            ] as $table => $columns) {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $this->error("Отсутствует поле {$table}.{$column}.");

                        return self::FAILURE;
                    }
                }
            }
        }

        $this->info('Каталог: 25 разделов / 245 функций; allowlist hosts корректен.');

        if (! $tokens->clientCredentialsConfigured()) {
            if ($this->option('live')) {
                $this->error('Для live-проверки нужны AVITO_CLIENT_ID и AVITO_CLIENT_SECRET.');

                return self::FAILURE;
            }

            $this->warn('Client credentials пока не заданы; интерфейс и каталог доступны, внешние вызовы — нет.');

            return self::SUCCESS;
        }

        $this->info('Client credentials присутствуют; значения не выводятся.');

        if ($this->option('live')) {
            $result = $executor->execute('user.getuserinfoself.4f59f9b2ea', []);

            if (! $result['ok']) {
                $this->error("Avito preflight отклонён, HTTP {$result['status']}.");

                return self::FAILURE;
            }

            $this->info('Live preflight пройден: Avito вернул профиль авторизованного пользователя.');
        }

        return self::SUCCESS;
    }
}
