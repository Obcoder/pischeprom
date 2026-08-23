<?php

namespace Tests\Feature\AiSales;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class ProspectingCandidateMigrationRollForwardTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_08_16_211000_create_prospecting_candidates.php';

    private const TABLES = [
        'prospecting_candidates',
        'prospecting_candidate_sources',
        'prospecting_candidate_channels',
        'prospecting_candidate_unit_matches',
    ];

    private array $temporaryConnections = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryConnections as $connection) {
            Schema::connection($connection)->dropAllTables();
            DB::purge($connection);
        }

        parent::tearDown();
    }

    public function test_sqlite_fresh_rollback_and_reapply_are_safe(): void
    {
        $connection = $this->sqliteConnection();
        $this->createPrerequisites($connection);

        $this->runMigration($connection);
        $this->assertCompleteSchema($connection);
        $this->assertMigrationRecord($connection, 1);

        $this->artisan('migrate:rollback', [
            '--database' => $connection,
            '--path' => $this->migrationPath(),
            '--realpath' => true,
            '--force' => true,
        ])->assertSuccessful();
        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::connection($connection)->hasTable($table));
        }
        $this->assertMigrationRecord($connection, 0);

        $this->runMigration($connection);
        $this->assertCompleteSchema($connection);
        $this->assertMigrationRecord($connection, 1);
    }

    public function test_sqlite_production_shaped_partial_state_preserves_rows(): void
    {
        $connection = $this->sqliteConnection();
        $this->createPrerequisites($connection);
        $this->createProductionShapedPartialSchema($connection);
        $before = $this->insertAndSnapshotSyntheticRows($connection);

        $this->runMigration($connection);
        $this->runMigration($connection);
        $this->runMigrationUpDirectly($connection);

        $this->assertSame($before, $this->snapshotRows($connection));
        $this->assertCompleteSchema($connection);
        $this->assertMigrationRecord($connection, 1);
    }

    public function test_existing_unexpected_schema_fails_before_reconciliation(): void
    {
        $connection = $this->sqliteConnection();
        $this->createPrerequisites($connection);
        Schema::connection($connection)->create('prospecting_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('unexpected_column');
        });

        try {
            $this->runMigrationUpDirectly($connection);
            $this->fail('The incompatible schema was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'prospecting_candidate_migration_schema_mismatch:prospecting_candidates:columns',
                $exception->getMessage(),
            );
        }

        $this->assertFalse(Schema::connection($connection)->hasTable('prospecting_candidate_sources'));
    }

    public function test_mysql_8_fresh_rollback_and_reapply_are_safe(): void
    {
        $connection = $this->mysqlConnection();
        $this->createPrerequisites($connection);

        $this->runMigration($connection);
        $this->assertCompleteSchema($connection);
        $this->assertFreshShortForeignKeyNames($connection);
        $this->assertMigrationRecord($connection, 1);

        $this->artisan('migrate:rollback', [
            '--database' => $connection,
            '--path' => $this->migrationPath(),
            '--realpath' => true,
            '--force' => true,
        ])->assertSuccessful();
        $this->assertMigrationRecord($connection, 0);

        $this->runMigration($connection);
        $this->assertCompleteSchema($connection);
        $this->assertFreshShortForeignKeyNames($connection);
        $this->assertMigrationRecord($connection, 1);
    }

    public function test_mysql_8_production_shaped_partial_state_preserves_rows(): void
    {
        $connection = $this->mysqlConnection();
        $this->createPrerequisites($connection);
        $this->createProductionShapedPartialSchema($connection);

        $schema = Schema::connection($connection);
        $this->assertSame(
            ['primary', 'prospecting_candidate_channels_prospecting_candidate_id_foreign'],
            collect($schema->getIndexes('prospecting_candidate_channels'))->pluck('name')->sort()->values()->all(),
        );
        $this->assertSame(
            ['prospecting_candidate_channels_prospecting_candidate_id_foreign'],
            collect($schema->getForeignKeys('prospecting_candidate_channels'))->pluck('name')->all(),
        );
        $before = $this->insertAndSnapshotSyntheticRows($connection);

        $this->runMigration($connection);
        $this->runMigration($connection);
        $this->runMigrationUpDirectly($connection);

        $this->assertSame($before, $this->snapshotRows($connection));
        $this->assertCompleteSchema($connection);
        $this->assertMigrationRecord($connection, 1);
        $channelForeignKeys = collect($schema->getForeignKeys('prospecting_candidate_channels'))->pluck('name')->sort()->values()->all();
        $this->assertSame([
            'pc_channels_source_fk',
            'prospecting_candidate_channels_prospecting_candidate_id_foreign',
        ], $channelForeignKeys);
    }

    private function sqliteConnection(): string
    {
        $name = 'sqlite_rollforward_'.count($this->temporaryConnections);
        config()->set('database.connections.'.$name, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
        $this->temporaryConnections[] = $name;

        return $name;
    }

    private function mysqlConnection(): string
    {
        $host = getenv('ROLLFORWARD_MYSQL_HOST');
        $database = getenv('ROLLFORWARD_MYSQL_DATABASE');
        if ($host === false || $database === false) {
            $this->markTestSkipped('Isolated MySQL roll-forward connection is not configured.');
        }
        $this->assertContains($host, ['127.0.0.1', 'localhost']);
        $this->assertMatchesRegularExpression('/^pischeprom_rollforward_[a-z0-9_]+$/', $database);

        $name = 'mysql_rollforward_'.count($this->temporaryConnections);
        config()->set('database.connections.'.$name, array_replace(config('database.connections.mysql'), [
            'url' => null,
            'host' => $host,
            'port' => (int) (getenv('ROLLFORWARD_MYSQL_PORT') ?: 3306),
            'database' => $database,
            'username' => (string) (getenv('ROLLFORWARD_MYSQL_USERNAME') ?: 'root'),
            'password' => (string) (getenv('ROLLFORWARD_MYSQL_PASSWORD') ?: ''),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'options' => [],
        ]));
        DB::purge($name);
        $version = (string) DB::connection($name)->selectOne('SELECT VERSION() AS version')->version;
        $this->assertMatchesRegularExpression('/^8\.0\./', $version);
        Schema::connection($name)->dropAllTables();
        $this->temporaryConnections[] = $name;

        return $name;
    }

    private function createPrerequisites(string $connection): void
    {
        $schema = Schema::connection($connection);
        foreach (['prospecting_search_jobs', 'prospecting_search_queries', 'ai_agent_runs', 'countries', 'regions', 'cities', 'units', 'users'] as $table) {
            $schema->create($table, fn (Blueprint $blueprint) => $blueprint->id());
        }
    }

    private function createProductionShapedPartialSchema(string $connection): void
    {
        $schema = Schema::connection($connection);
        $schema->create('prospecting_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('prospecting_search_job_id')->nullable()->constrained('prospecting_search_jobs')->nullOnDelete();
            $table->foreignId('prospecting_search_query_id')->nullable()->constrained('prospecting_search_queries')->nullOnDelete();
            $table->foreignId('ai_agent_run_id')->nullable()->constrained('ai_agent_runs')->nullOnDelete();
            $table->string('purpose', 32);
            $table->string('lane', 24);
            $table->string('role_code', 32);
            $table->string('working_name', 255);
            $table->string('normalized_name', 255);
            $table->string('normalized_domain', 255)->nullable();
            $table->string('canonical_website', 2048)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->string('location_display', 255)->nullable();
            $table->string('public_activity_summary', 1000)->nullable();
            $table->string('relevance_summary', 1000)->nullable();
            $table->json('confidence_components')->nullable();
            $table->unsignedSmallInteger('source_count')->default(0);
            $table->char('fingerprint_hash', 64);
            $table->char('normalized_payload_hash', 64);
            $table->string('status', 32)->default('pending_resolution');
            $table->string('resolution_outcome', 32)->nullable();
            $table->foreignId('resolved_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('resolution_reason_code', 96)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('anonymized_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['prospecting_search_job_id', 'fingerprint_hash'], 'prospecting_candidate_fingerprint_unique');
            $table->index(['lane', 'status', 'expires_at'], 'prospecting_candidate_review_idx');
            $table->index(['normalized_domain', 'status'], 'prospecting_candidate_domain_idx');
            $table->index(['resolved_unit_id', 'status'], 'prospecting_candidate_unit_idx');
        });

        $schema->create('prospecting_candidate_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_candidate_id')->constrained('prospecting_candidates')->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->string('canonical_url', 2048)->nullable();
            $table->string('source_reference', 512)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('source_domain', 255)->nullable();
            $table->string('bounded_excerpt', 1000)->nullable();
            $table->char('evidence_hash', 64);
            $table->timestamp('accessed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('data_classification', 32)->default('public');
            $table->string('visibility_scope', 32);
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->unsignedTinyInteger('source_quality')->nullable();
            $table->timestamps();

            $table->unique(['prospecting_candidate_id', 'evidence_hash'], 'prospecting_candidate_source_unique');
            $table->index(['prospecting_candidate_id', 'source_domain'], 'prospecting_source_domain_idx');
        });

        $schema->create('prospecting_candidate_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_candidate_id');
            $table->foreignId('prospecting_candidate_source_id')->nullable();
            $table->string('channel_kind', 16);
            $table->char('normalized_hash', 64);
            $table->text('protected_value');
            $table->string('masked_display', 255);
            $table->string('contact_role', 32)->default('business_general');
            $table->string('verification_status', 24)->default('unverified');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('data_classification', 32);
            $table->string('communication_state', 24)->default('review_required');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->foreign(
                'prospecting_candidate_id',
                'prospecting_candidate_channels_prospecting_candidate_id_foreign',
            )->references('id')->on('prospecting_candidates')->cascadeOnDelete();
        });
    }

    private function insertAndSnapshotSyntheticRows(string $connection): array
    {
        $db = DB::connection($connection);
        $now = '2026-08-19 12:00:00';
        $db->table('prospecting_candidates')->insert([
            'id' => 1,
            'public_id' => '11111111-1111-4111-8111-111111111111',
            'purpose' => 'product_discovery',
            'lane' => 'buyer',
            'role_code' => 'buyer',
            'working_name' => 'Synthetic Candidate',
            'normalized_name' => 'synthetic candidate',
            'source_count' => 1,
            'fingerprint_hash' => str_repeat('a', 64),
            'normalized_payload_hash' => str_repeat('b', 64),
            'status' => 'pending_resolution',
            'expires_at' => '2026-09-19 12:00:00',
            'lock_version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $db->table('prospecting_candidate_sources')->insert([
            'id' => 1,
            'prospecting_candidate_id' => 1,
            'source_type' => 'public_web',
            'evidence_hash' => str_repeat('c', 64),
            'data_classification' => 'public',
            'visibility_scope' => 'reviewer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $db->table('prospecting_candidate_channels')->insert([
            'id' => 1,
            'prospecting_candidate_id' => 1,
            'prospecting_candidate_source_id' => 1,
            'channel_kind' => 'email',
            'normalized_hash' => str_repeat('d', 64),
            'protected_value' => 'synthetic-protected-value',
            'masked_display' => 's***@example.test',
            'contact_role' => 'business_general',
            'verification_status' => 'unverified',
            'data_classification' => 'public_business',
            'communication_state' => 'review_required',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->snapshotRows($connection);
    }

    private function snapshotRows(string $connection): array
    {
        $db = DB::connection($connection);

        return collect(['prospecting_candidates', 'prospecting_candidate_sources', 'prospecting_candidate_channels'])
            ->mapWithKeys(fn (string $table): array => [
                $table => $db->table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            ])->all();
    }

    private function runMigration(string $connection): void
    {
        $this->artisan('migrate', [
            '--database' => $connection,
            '--path' => $this->migrationPath(),
            '--realpath' => true,
            '--force' => true,
        ])->assertSuccessful();
    }

    private function runMigrationUpDirectly(string $connection): void
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);
        try {
            $migration = require $this->migrationPath();
            $migration->up();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    private function assertCompleteSchema(string $connection): void
    {
        $schema = Schema::connection($connection);
        foreach (self::TABLES as $table) {
            $this->assertTrue($schema->hasTable($table));
            foreach (array_merge($schema->getIndexes($table), $schema->getForeignKeys($table)) as $identifier) {
                $name = $identifier['name'] ?? null;
                $this->assertTrue($name === null || strlen($name) <= 64, (string) $name);
            }
        }

        $channelForeignKeys = $schema->getForeignKeys('prospecting_candidate_channels');
        $this->assertTrue(collect($channelForeignKeys)->contains(
            fn (array $foreign): bool => ($foreign['columns'] ?? []) === ['prospecting_candidate_source_id']
                && ($foreign['foreign_table'] ?? null) === 'prospecting_candidate_sources'
                && ($foreign['foreign_columns'] ?? []) === ['id']
                && strtolower((string) ($foreign['on_delete'] ?? '')) === 'set null',
        ));
        $this->assertTrue(collect($schema->getIndexes('prospecting_candidate_channels'))->contains(
            fn (array $index): bool => ($index['columns'] ?? []) === ['prospecting_candidate_id', 'channel_kind', 'normalized_hash']
                && (bool) ($index['unique'] ?? false),
        ));
    }

    private function assertFreshShortForeignKeyNames(string $connection): void
    {
        $names = collect(self::TABLES)
            ->flatMap(fn (string $table) => Schema::connection($connection)->getForeignKeys($table))
            ->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'pc_candidates_agent_run_fk',
            'pc_candidates_city_fk',
            'pc_candidates_country_fk',
            'pc_candidates_region_fk',
            'pc_candidates_resolved_unit_fk',
            'pc_candidates_reviewer_fk',
            'pc_candidates_search_job_fk',
            'pc_candidates_search_query_fk',
            'pc_channels_candidate_fk',
            'pc_channels_source_fk',
            'pc_sources_candidate_fk',
            'pc_unit_matches_candidate_fk',
            'pc_unit_matches_unit_fk',
        ], $names);
    }

    private function assertMigrationRecord(string $connection, int $expected): void
    {
        $this->assertSame(
            $expected,
            DB::connection($connection)->table('migrations')
                ->where('migration', '2026_08_16_211000_create_prospecting_candidates')->count(),
        );
    }

    private function migrationPath(): string
    {
        return dirname(__DIR__, 3).'/'.self::MIGRATION;
    }
}
