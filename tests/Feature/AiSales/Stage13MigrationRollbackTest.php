<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Schema;

class Stage13MigrationRollbackTest extends Stage13TestCase
{
    public function test_stage13_migration_is_additive_reversible_and_reapplicable(): void
    {
        $migration = require database_path('migrations/2026_08_17_124000_create_outreach_dispatch_lifecycle_tables.php');
        $tables = ['outreach_dispatches', 'outreach_reply_links', 'outreach_follow_up_plans', 'outreach_follow_up_steps'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasColumn('sendings', 'mail_message_id'));
        $this->assertTrue(Schema::hasColumn('outreach_dispatch_decisions', 'checkpoint'));

        $migration->down();
        foreach ($tables as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('sendings', 'mail_message_id'));
        $this->assertFalse(Schema::hasColumn('outreach_dispatch_decisions', 'checkpoint'));

        $migration->up();
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasColumn('sendings', 'safe_error_code'));
    }
}
