<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BuildsIsolatedAvitoDatabase
{
    private function createAvitoTestDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'realtime.enabled' => false,
        ]);
        DB::purge();
        DB::setDefaultConnection('sqlite');

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/0001_01_01_000002_create_jobs_table.php'))->up();
        Schema::table('users', function (Blueprint $table): void {
            $table->string('type');
            $table->string('status');
        });
        (require database_path('migrations/2026_01_03_195751_create_permission_tables.php'))->up();
        foreach (['entities', 'telephones', 'buildings', 'orders'] as $name) {
            Schema::create($name, fn (Blueprint $table) => $table->id());
        }
        foreach ([
            '2026_08_05_100000_create_avito_integration_tables.php',
            '2026_08_05_110000_create_avito_messenger_archive_tables.php',
            '2026_08_05_130000_add_crm_context_to_avito_messenger.php',
            '2026_08_06_100000_create_avito_auto_reply_tables.php',
            '2026_09_12_160000_expand_avito_auto_replies.php',
            '2026_09_12_170000_add_avito_auto_reply_emergency_stop.php',
            '2026_09_21_110000_add_history_sync_to_avito_chats.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }
}
