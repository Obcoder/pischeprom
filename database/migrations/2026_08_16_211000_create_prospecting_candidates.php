<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'prospecting_candidates',
        'prospecting_candidate_sources',
        'prospecting_candidate_channels',
        'prospecting_candidate_unit_matches',
    ];

    /**
     * [MySQL type, nullable, default, auto increment].
     */
    private const COLUMNS = [
        'prospecting_candidates' => [
            'id' => ['bigint unsigned', false, null, true],
            'public_id' => ['char(36)', false, null, false],
            'prospecting_search_job_id' => ['bigint unsigned', true, null, false],
            'prospecting_search_query_id' => ['bigint unsigned', true, null, false],
            'ai_agent_run_id' => ['bigint unsigned', true, null, false],
            'purpose' => ['varchar(32)', false, null, false],
            'lane' => ['varchar(24)', false, null, false],
            'role_code' => ['varchar(32)', false, null, false],
            'working_name' => ['varchar(255)', false, null, false],
            'normalized_name' => ['varchar(255)', false, null, false],
            'normalized_domain' => ['varchar(255)', true, null, false],
            'canonical_website' => ['varchar(2048)', true, null, false],
            'country_id' => ['bigint unsigned', true, null, false],
            'region_id' => ['bigint unsigned', true, null, false],
            'city_id' => ['bigint unsigned', true, null, false],
            'location_display' => ['varchar(255)', true, null, false],
            'public_activity_summary' => ['varchar(1000)', true, null, false],
            'relevance_summary' => ['varchar(1000)', true, null, false],
            'confidence_components' => ['json', true, null, false],
            'source_count' => ['smallint unsigned', false, '0', false],
            'fingerprint_hash' => ['char(64)', false, null, false],
            'normalized_payload_hash' => ['char(64)', false, null, false],
            'status' => ['varchar(32)', false, 'pending_resolution', false],
            'resolution_outcome' => ['varchar(32)', true, null, false],
            'resolved_unit_id' => ['bigint unsigned', true, null, false],
            'reviewed_by' => ['bigint unsigned', true, null, false],
            'reviewed_at' => ['timestamp', true, null, false],
            'resolution_reason_code' => ['varchar(96)', true, null, false],
            'expires_at' => ['timestamp', false, null, false],
            'anonymized_at' => ['timestamp', true, null, false],
            'lock_version' => ['int unsigned', false, '0', false],
            'created_at' => ['timestamp', true, null, false],
            'updated_at' => ['timestamp', true, null, false],
        ],
        'prospecting_candidate_sources' => [
            'id' => ['bigint unsigned', false, null, true],
            'prospecting_candidate_id' => ['bigint unsigned', false, null, false],
            'source_type' => ['varchar(32)', false, null, false],
            'canonical_url' => ['varchar(2048)', true, null, false],
            'source_reference' => ['varchar(512)', true, null, false],
            'title' => ['varchar(255)', true, null, false],
            'source_domain' => ['varchar(255)', true, null, false],
            'bounded_excerpt' => ['varchar(1000)', true, null, false],
            'evidence_hash' => ['char(64)', false, null, false],
            'accessed_at' => ['timestamp', true, null, false],
            'published_at' => ['timestamp', true, null, false],
            'data_classification' => ['varchar(32)', false, 'public', false],
            'visibility_scope' => ['varchar(32)', false, null, false],
            'confidence' => ['tinyint unsigned', true, null, false],
            'source_quality' => ['tinyint unsigned', true, null, false],
            'created_at' => ['timestamp', true, null, false],
            'updated_at' => ['timestamp', true, null, false],
        ],
        'prospecting_candidate_channels' => [
            'id' => ['bigint unsigned', false, null, true],
            'prospecting_candidate_id' => ['bigint unsigned', false, null, false],
            'prospecting_candidate_source_id' => ['bigint unsigned', true, null, false],
            'channel_kind' => ['varchar(16)', false, null, false],
            'normalized_hash' => ['char(64)', false, null, false],
            'protected_value' => ['text', false, null, false],
            'masked_display' => ['varchar(255)', false, null, false],
            'contact_role' => ['varchar(32)', false, 'business_general', false],
            'verification_status' => ['varchar(24)', false, 'unverified', false],
            'confidence' => ['tinyint unsigned', true, null, false],
            'data_classification' => ['varchar(32)', false, null, false],
            'communication_state' => ['varchar(24)', false, 'review_required', false],
            'last_verified_at' => ['timestamp', true, null, false],
            'created_at' => ['timestamp', true, null, false],
            'updated_at' => ['timestamp', true, null, false],
        ],
        'prospecting_candidate_unit_matches' => [
            'id' => ['bigint unsigned', false, null, true],
            'prospecting_candidate_id' => ['bigint unsigned', false, null, false],
            'unit_id' => ['bigint unsigned', false, null, false],
            'signal_code' => ['varchar(64)', false, null, false],
            'strength' => ['tinyint unsigned', false, null, false],
            'rank' => ['smallint unsigned', false, '1', false],
            'evidence_hash' => ['char(64)', false, null, false],
            'evidence_reference' => ['varchar(512)', true, null, false],
            'review_status' => ['varchar(24)', false, 'suggested', false],
            'created_at' => ['timestamp', true, null, false],
            'updated_at' => ['timestamp', true, null, false],
        ],
    ];

    private const INDEXES = [
        'prospecting_candidates' => [
            ['pc_candidates_public_id_unique', ['public_id'], true],
            ['prospecting_candidate_fingerprint_unique', ['prospecting_search_job_id', 'fingerprint_hash'], true],
            ['prospecting_candidate_review_idx', ['lane', 'status', 'expires_at'], false],
            ['prospecting_candidate_domain_idx', ['normalized_domain', 'status'], false],
            ['prospecting_candidate_unit_idx', ['resolved_unit_id', 'status'], false],
        ],
        'prospecting_candidate_sources' => [
            ['prospecting_candidate_source_unique', ['prospecting_candidate_id', 'evidence_hash'], true],
            ['prospecting_source_domain_idx', ['prospecting_candidate_id', 'source_domain'], false],
        ],
        'prospecting_candidate_channels' => [
            ['prospecting_candidate_channel_unique', ['prospecting_candidate_id', 'channel_kind', 'normalized_hash'], true],
            ['prospecting_channel_policy_idx', ['communication_state', 'data_classification'], false],
        ],
        'prospecting_candidate_unit_matches' => [
            ['prospecting_candidate_unit_signal_unique', ['prospecting_candidate_id', 'unit_id', 'signal_code'], true],
            ['prospecting_candidate_match_rank_idx', ['prospecting_candidate_id', 'rank'], false],
        ],
    ];

    /**
     * [constraint, child columns, parent table, parent columns, delete action, fallback child index].
     */
    private const FOREIGN_KEYS = [
        'prospecting_candidates' => [
            ['pc_candidates_search_job_fk', ['prospecting_search_job_id'], 'prospecting_search_jobs', ['id'], 'set null', 'pc_candidates_search_job_idx'],
            ['pc_candidates_search_query_fk', ['prospecting_search_query_id'], 'prospecting_search_queries', ['id'], 'set null', 'pc_candidates_search_query_idx'],
            ['pc_candidates_agent_run_fk', ['ai_agent_run_id'], 'ai_agent_runs', ['id'], 'set null', 'pc_candidates_agent_run_idx'],
            ['pc_candidates_country_fk', ['country_id'], 'countries', ['id'], 'restrict', 'pc_candidates_country_idx'],
            ['pc_candidates_region_fk', ['region_id'], 'regions', ['id'], 'restrict', 'pc_candidates_region_idx'],
            ['pc_candidates_city_fk', ['city_id'], 'cities', ['id'], 'restrict', 'pc_candidates_city_idx'],
            ['pc_candidates_resolved_unit_fk', ['resolved_unit_id'], 'units', ['id'], 'restrict', 'pc_candidates_resolved_unit_idx'],
            ['pc_candidates_reviewer_fk', ['reviewed_by'], 'users', ['id'], 'set null', 'pc_candidates_reviewer_idx'],
        ],
        'prospecting_candidate_sources' => [
            ['pc_sources_candidate_fk', ['prospecting_candidate_id'], 'prospecting_candidates', ['id'], 'cascade', 'pc_sources_candidate_idx'],
        ],
        'prospecting_candidate_channels' => [
            ['pc_channels_candidate_fk', ['prospecting_candidate_id'], 'prospecting_candidates', ['id'], 'cascade', 'pc_channels_candidate_idx'],
            ['pc_channels_source_fk', ['prospecting_candidate_source_id'], 'prospecting_candidate_sources', ['id'], 'set null', 'pc_channels_source_idx'],
        ],
        'prospecting_candidate_unit_matches' => [
            ['pc_unit_matches_candidate_fk', ['prospecting_candidate_id'], 'prospecting_candidates', ['id'], 'cascade', 'pc_unit_matches_candidate_idx'],
            ['pc_unit_matches_unit_fk', ['unit_id'], 'units', ['id'], 'restrict', 'pc_unit_matches_unit_idx'],
        ],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $this->assertExistingTableIsCompatible($table);
            }
        }

        $this->createMissingTables();

        foreach (self::TABLES as $table) {
            $this->reconcileIndexes($table);
            $this->reconcileForeignKeys($table);
            $this->assertTableIsComplete($table);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prospecting_candidate_unit_matches');
        Schema::dropIfExists('prospecting_candidate_channels');
        Schema::dropIfExists('prospecting_candidate_sources');
        Schema::dropIfExists('prospecting_candidates');
    }

    private function createMissingTables(): void
    {
        if (! Schema::hasTable('prospecting_candidates')) {
            $this->createCandidates();
        }
        if (! Schema::hasTable('prospecting_candidate_sources')) {
            $this->createSources();
        }
        if (! Schema::hasTable('prospecting_candidate_channels')) {
            $this->createChannels();
        }
        if (! Schema::hasTable('prospecting_candidate_unit_matches')) {
            $this->createUnitMatches();
        }
    }

    private function createCandidates(): void
    {
        Schema::create('prospecting_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id');
            $table->foreignId('prospecting_search_job_id')->nullable();
            $table->foreignId('prospecting_search_query_id')->nullable();
            $table->foreignId('ai_agent_run_id')->nullable();
            $table->string('purpose', 32);
            $table->string('lane', 24);
            $table->string('role_code', 32);
            $table->string('working_name', 255);
            $table->string('normalized_name', 255);
            $table->string('normalized_domain', 255)->nullable();
            $table->string('canonical_website', 2048)->nullable();
            $table->foreignId('country_id')->nullable();
            $table->foreignId('region_id')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->string('location_display', 255)->nullable();
            $table->string('public_activity_summary', 1000)->nullable();
            $table->string('relevance_summary', 1000)->nullable();
            $table->json('confidence_components')->nullable();
            $table->unsignedSmallInteger('source_count')->default(0);
            $table->char('fingerprint_hash', 64);
            $table->char('normalized_payload_hash', 64);
            $table->string('status', 32)->default('pending_resolution');
            $table->string('resolution_outcome', 32)->nullable();
            $table->foreignId('resolved_unit_id')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('resolution_reason_code', 96)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('anonymized_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique('public_id', 'pc_candidates_public_id_unique');
            $table->unique(['prospecting_search_job_id', 'fingerprint_hash'], 'prospecting_candidate_fingerprint_unique');
            $table->index(['lane', 'status', 'expires_at'], 'prospecting_candidate_review_idx');
            $table->index(['normalized_domain', 'status'], 'prospecting_candidate_domain_idx');
            $table->index(['resolved_unit_id', 'status'], 'prospecting_candidate_unit_idx');
            $table->index('prospecting_search_query_id', 'pc_candidates_search_query_idx');
            $table->index('ai_agent_run_id', 'pc_candidates_agent_run_idx');
            $table->index('country_id', 'pc_candidates_country_idx');
            $table->index('region_id', 'pc_candidates_region_idx');
            $table->index('city_id', 'pc_candidates_city_idx');
            $table->index('reviewed_by', 'pc_candidates_reviewer_idx');

            $this->addForeign($table, self::FOREIGN_KEYS['prospecting_candidates']);
        });
    }

    private function createSources(): void
    {
        Schema::create('prospecting_candidate_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_candidate_id');
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

            $this->addForeign($table, self::FOREIGN_KEYS['prospecting_candidate_sources']);
        });
    }

    private function createChannels(): void
    {
        Schema::create('prospecting_candidate_channels', function (Blueprint $table): void {
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

            $table->unique(['prospecting_candidate_id', 'channel_kind', 'normalized_hash'], 'prospecting_candidate_channel_unique');
            $table->index(['communication_state', 'data_classification'], 'prospecting_channel_policy_idx');
            $table->index('prospecting_candidate_source_id', 'pc_channels_source_idx');

            $this->addForeign($table, self::FOREIGN_KEYS['prospecting_candidate_channels']);
        });
    }

    private function createUnitMatches(): void
    {
        Schema::create('prospecting_candidate_unit_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_candidate_id');
            $table->foreignId('unit_id');
            $table->string('signal_code', 64);
            $table->unsignedTinyInteger('strength');
            $table->unsignedSmallInteger('rank')->default(1);
            $table->char('evidence_hash', 64);
            $table->string('evidence_reference', 512)->nullable();
            $table->string('review_status', 24)->default('suggested');
            $table->timestamps();

            $table->unique(['prospecting_candidate_id', 'unit_id', 'signal_code'], 'prospecting_candidate_unit_signal_unique');
            $table->index(['prospecting_candidate_id', 'rank'], 'prospecting_candidate_match_rank_idx');
            $table->index('unit_id', 'pc_unit_matches_unit_idx');

            $this->addForeign($table, self::FOREIGN_KEYS['prospecting_candidate_unit_matches']);
        });
    }

    private function assertExistingTableIsCompatible(string $table): void
    {
        $this->assertColumns($table);
        $this->assertTableOptions($table);

        $indexes = Schema::getIndexes($table);
        $primary = collect($indexes)->first(fn (array $index): bool => (bool) ($index['primary'] ?? false));
        if ($primary === null || ($primary['columns'] ?? []) !== ['id'] || ! (bool) ($primary['unique'] ?? false)) {
            $this->fail($table, 'primary_key');
        }

        foreach (self::INDEXES[$table] as [$name, $columns, $unique]) {
            $named = collect($indexes)->firstWhere('name', $name);
            if ($named !== null && ! $this->indexMatches($named, $columns, $unique)) {
                $this->fail($table, 'index_'.$name);
            }
        }

        $foreignKeys = Schema::getForeignKeys($table);
        foreach (self::FOREIGN_KEYS[$table] as $definition) {
            [$name, $columns, , , , $childIndex] = $definition;
            $namedChildIndex = collect($indexes)->firstWhere('name', $childIndex);
            if ($namedChildIndex !== null
                && array_slice($namedChildIndex['columns'] ?? [], 0, count($columns)) !== $columns) {
                $this->fail($table, 'foreign_index_'.$childIndex);
            }
            $named = collect($foreignKeys)->firstWhere('name', $name);
            if ($named !== null && ! $this->foreignKeyMatches($named, $definition)) {
                $this->fail($table, 'foreign_key_'.$name);
            }
            foreach ($foreignKeys as $foreignKey) {
                if (($foreignKey['columns'] ?? []) === $columns && ! $this->foreignKeyMatches($foreignKey, $definition)) {
                    $this->fail($table, 'foreign_key_columns_'.implode('_', $columns));
                }
            }
        }
    }

    private function assertColumns(string $table): void
    {
        $actual = collect(Schema::getColumns($table))->keyBy('name');
        $expected = self::COLUMNS[$table];
        if ($actual->keys()->values()->all() !== array_keys($expected)) {
            $this->fail($table, 'columns');
        }

        $driver = DB::connection()->getDriverName();
        foreach ($expected as $name => [$mysqlType, $nullable, $default, $autoIncrement]) {
            $column = $actual->get($name);
            $expectedType = $driver === 'sqlite' ? $this->sqliteType($mysqlType) : $mysqlType;
            $actualType = strtolower((string) ($column['type'] ?? $column['type_name'] ?? ''));
            if ($actualType !== $expectedType
                || (bool) ($column['nullable'] ?? false) !== $nullable
                || $this->normalizeDefault($column['default'] ?? null) !== $this->normalizeDefault($default)
                || (bool) ($column['auto_increment'] ?? false) !== $autoIncrement) {
                $this->fail($table, 'column_'.$name);
            }
        }
    }

    private function assertTableOptions(string $table): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $metadata = DB::selectOne(
            'SELECT ENGINE AS engine, TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );
        $expectedCollation = config('database.connections.'.DB::getDefaultConnection().'.collation');
        if ($metadata === null
            || strtolower((string) $metadata->engine) !== 'innodb'
            || ($expectedCollation !== null && (string) $metadata->collation !== (string) $expectedCollation)) {
            $this->fail($table, 'table_options');
        }
    }

    private function reconcileIndexes(string $table): void
    {
        foreach (self::INDEXES[$table] as [$name, $columns, $unique]) {
            $indexes = Schema::getIndexes($table);
            if (collect($indexes)->contains(fn (array $index): bool => $this->indexMatches($index, $columns, $unique))) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($name, $columns, $unique): void {
                $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name);
            });
        }
    }

    private function reconcileForeignKeys(string $table): void
    {
        foreach (self::FOREIGN_KEYS[$table] as $definition) {
            [$name, $columns, $foreignTable, $foreignColumns, , $childIndex] = $definition;
            $this->assertReferencedKey($table, $foreignTable, $foreignColumns);
            if (! $this->hasLeadingIndex($table, $columns)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $childIndex));
            }
            $foreignKeys = Schema::getForeignKeys($table);
            if (collect($foreignKeys)->contains(fn (array $foreignKey): bool => $this->foreignKeyMatches($foreignKey, $definition))) {
                continue;
            }
            Schema::table($table, fn (Blueprint $blueprint) => $this->addForeign($blueprint, [$definition]));
        }
    }

    private function assertReferencedKey(string $table, string $foreignTable, array $foreignColumns): void
    {
        if (! Schema::hasTable($foreignTable) || ! Schema::hasColumns($foreignTable, $foreignColumns)) {
            $this->fail($table, 'foreign_reference_'.$foreignTable);
        }
        $valid = collect(Schema::getIndexes($foreignTable))->contains(
            fn (array $index): bool => (bool) ($index['unique'] ?? false)
                && array_slice($index['columns'] ?? [], 0, count($foreignColumns)) === $foreignColumns,
        );
        if (! $valid) {
            $this->fail($table, 'foreign_reference_key_'.$foreignTable);
        }
    }

    private function assertTableIsComplete(string $table): void
    {
        $this->assertExistingTableIsCompatible($table);
        $indexes = Schema::getIndexes($table);
        foreach (self::INDEXES[$table] as [, $columns, $unique]) {
            if (! collect($indexes)->contains(fn (array $index): bool => $this->indexMatches($index, $columns, $unique))) {
                $this->fail($table, 'missing_index_'.implode('_', $columns));
            }
        }
        foreach (self::FOREIGN_KEYS[$table] as $definition) {
            if (! collect(Schema::getForeignKeys($table))->contains(
                fn (array $foreignKey): bool => $this->foreignKeyMatches($foreignKey, $definition),
            )) {
                $this->fail($table, 'missing_foreign_key_'.$definition[0]);
            }
            if (! $this->hasLeadingIndex($table, $definition[1])) {
                $this->fail($table, 'missing_foreign_index_'.$definition[0]);
            }
        }
        foreach (array_merge(Schema::getIndexes($table), Schema::getForeignKeys($table)) as $identifier) {
            $name = $identifier['name'] ?? null;
            if (is_string($name) && strlen($name) > 64) {
                $this->fail($table, 'identifier_length');
            }
        }
    }

    private function indexMatches(array $index, array $columns, bool $unique): bool
    {
        return ($index['columns'] ?? []) === $columns
            && (bool) ($index['unique'] ?? false) === $unique
            && ! (bool) ($index['primary'] ?? false);
    }

    private function foreignKeyMatches(array $foreignKey, array $definition): bool
    {
        [, $columns, $foreignTable, $foreignColumns, $onDelete] = $definition;

        return ($foreignKey['columns'] ?? []) === $columns
            && ($foreignKey['foreign_table'] ?? null) === $foreignTable
            && ($foreignKey['foreign_columns'] ?? []) === $foreignColumns
            && strtolower((string) ($foreignKey['on_update'] ?? '')) === 'no action'
            && strtolower((string) ($foreignKey['on_delete'] ?? '')) === $onDelete;
    }

    private function hasLeadingIndex(string $table, array $columns): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => array_slice($index['columns'] ?? [], 0, count($columns)) === $columns,
        );
    }

    private function addForeign(Blueprint $table, array $definitions): void
    {
        foreach ($definitions as [$name, $columns, $foreignTable, $foreignColumns, $onDelete]) {
            $table->foreign($columns, $name)
                ->references($foreignColumns)
                ->on($foreignTable)
                ->onUpdate('no action')
                ->onDelete($onDelete);
        }
    }

    private function sqliteType(string $mysqlType): string
    {
        return match (true) {
            str_contains($mysqlType, 'int') => 'integer',
            str_starts_with($mysqlType, 'varchar'), str_starts_with($mysqlType, 'char') => 'varchar',
            $mysqlType === 'timestamp' => 'datetime',
            $mysqlType === 'json' => 'text',
            default => $mysqlType,
        };
    }

    private function normalizeDefault(mixed $default): ?string
    {
        if ($default === null) {
            return null;
        }
        $value = (string) $default;
        if (strlen($value) >= 2 && $value[0] === "'" && $value[-1] === "'") {
            return str_replace("''", "'", substr($value, 1, -1));
        }

        return $value;
    }

    private function fail(string $table, string $reason): never
    {
        throw new RuntimeException('prospecting_candidate_migration_schema_mismatch:'.$table.':'.$reason);
    }
};
