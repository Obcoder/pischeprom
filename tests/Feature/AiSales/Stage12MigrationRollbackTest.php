<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Schema;

class Stage12MigrationRollbackTest extends Stage12TestCase
{
    public function test_stage12_migrations_are_additive_reversible_and_reapply_on_isolated_sqlite(): void
    {
        $migrations = [
            require database_path('migrations/2026_08_17_120000_create_authorized_mail_dispatch_attempts.php'),
            require database_path('migrations/2026_08_17_121000_create_communication_permission_ledger.php'),
            require database_path('migrations/2026_08_17_122000_create_outreach_draft_review_tables.php'),
        ];
        $tables = [
            'authorized_mail_dispatch_attempts', 'communication_permissions', 'communication_permission_evidence',
            'communication_permission_decisions', 'communication_suppressions', 'communication_suppression_decisions',
            'outreach_drafts', 'outreach_draft_revisions', 'outreach_draft_claims', 'outreach_draft_reviews',
            'outreach_dispatch_decisions',
        ];
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        try {
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }
            foreach ($tables as $table) {
                $this->assertFalse(Schema::hasTable($table));
            }
            $this->assertTrue(Schema::hasTable('units'));
            $this->assertTrue(Schema::hasTable('unit_business_contexts'));
            $this->assertTrue(Schema::hasTable('mail_messages'));
        } finally {
            foreach ($migrations as $migration) {
                $migration->up();
            }
        }
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }
}
