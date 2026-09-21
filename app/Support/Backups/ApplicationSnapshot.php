<?php

namespace App\Support\Backups;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Standalone backup operations: never boots application providers or dispatches work. */
final class ApplicationSnapshot
{
    private const ENCRYPTED_COLUMNS = [
        'avito_chats' => ['payload'],
        'avito_messages' => ['content', 'quote', 'payload'],
        'avito_connections' => ['access_token', 'refresh_token', 'metadata'],
        'avito_contact_candidates' => ['metadata'],
        'avito_auto_reply_decisions' => ['message_excerpt', 'response_text', 'input_bundle', 'classifier_payload'],
        'avito_auto_reply_examples' => ['text'],
        'avito_message_template_usages' => ['rendered_body', 'context'],
        'avito_api_calls' => ['request_meta', 'response_meta'],
        'avito_webhook_events' => ['payload'],
        'avito_autoload_feeds' => ['access_token', 'defaults', 'profile_snapshot', 'last_upload_snapshot'],
        'avito_listing_good_transfers' => ['source_snapshot', 'remote_meta'],
        'avito_publications' => ['draft_payload', 'last_remote_report'],
        'avito_publication_revisions' => ['source_snapshot', 'payload_snapshot', 'remote_report'],
    ];

    public function __construct(private readonly string $appDirectory, private readonly array $config) {}

    public static function forApplication(string $directory): self
    {
        $directory = self::directory($directory);
        if (! is_file($directory.'/bootstrap/app.php')) {
            throw new RuntimeException('application_directory_invalid');
        }

        $app = new Application($directory);
        $app->bootstrapWith([LoadEnvironmentVariables::class, LoadConfiguration::class]);

        return new self($directory, $app['config']->all());
    }

    public function inspect(): array
    {
        $database = $this->databaseConfiguration();
        $pdo = self::connect($database);
        $engines = $this->engines($pdo, $database);
        $disks = $this->archiveDisks($pdo);
        $this->encrypter($this->recoveryKeys());

        return [
            'ok' => true,
            'database_driver' => $database['driver'],
            'database_connected' => true,
            'non_transactional_tables' => count(array_filter($engines, fn ($engine) => strtoupper($engine) !== 'INNODB')),
            'table_engines' => array_count_values($engines),
            'database_objects' => $this->databaseObjects($pdo, $database),
            'database_estimated_bytes' => $this->databaseEstimatedBytes($pdo, $database),
            'media_estimated_bytes' => $this->mediaEstimatedBytes($disks),
            'archive_disks' => count($disks),
            'archive_roots_present' => count(array_filter($disks, fn ($disk) => is_dir($disk['root']))),
            'application_key_valid' => true,
            'previous_key_count' => count($this->recoveryKeys()['previous_keys']),
            'pdo_mysql_available' => extension_loaded('pdo_mysql'),
            'pdo_sqlite_available' => extension_loaded('pdo_sqlite'),
            'dump_tool_available' => $this->dumpBinary() !== null,
            'archived_attachments' => $this->tableExists($pdo, 'avito_message_attachments')
                ? (int) $pdo->query('SELECT COUNT(*) FROM avito_message_attachments WHERE archived_at IS NOT NULL')->fetchColumn() : 0,
            'unarchived_attachments' => $this->tableExists($pdo, 'avito_message_attachments')
                ? (int) $pdo->query('SELECT COUNT(*) FROM avito_message_attachments WHERE archived_at IS NULL')->fetchColumn() : 0,
        ];
    }

    public function export(string $staging): array
    {
        $staging = $this->privateStaging($staging);
        foreach (['recovery.json', 'media-manifest.jsonl', 'database.sql', 'database.sqlite'] as $name) {
            if (file_exists($staging.'/'.$name) || is_link($staging.'/'.$name)) {
                throw new RuntimeException('staging_not_empty');
            }
        }

        $database = $this->databaseConfiguration();
        $pdo = self::connect($database);
        $keys = $this->recoveryKeys();
        $this->encrypter($keys);
        $disks = $this->archiveDisks($pdo);
        $databaseObjects = $this->databaseObjects($pdo, $database);
        $requiredTables = array_values(array_filter(
            ['avito_chats', 'avito_messages', 'avito_message_attachments'],
            fn ($table) => $this->tableExists($pdo, $table),
        ));
        foreach ($this->engines($pdo, $database) as $engine) {
            if (strtoupper($engine) !== 'INNODB') {
                throw new RuntimeException('non_transactional_tables');
            }
        }

        // The database is captured first. Media files are written before their DB rows
        // become archived and are subsequently immutable. Restore verification queries
        // this exact DB snapshot, so a later manifest cannot hide a missing referenced file.
        $databaseFile = $database['driver'] === 'sqlite' ? 'database.sqlite' : 'database.sql';
        if ($database['driver'] === 'sqlite') {
            $pdo->exec('VACUUM INTO '.$pdo->quote($staging.'/'.$databaseFile));
            chmod($staging.'/'.$databaseFile, 0600);
        } else {
            $this->dumpMysql($database, $staging);
        }

        $files = $this->writeMediaManifest($staging.'/media-manifest.jsonl', $disks);
        $recovery = [
            'schema_version' => 1,
            'created_at' => gmdate('c'),
            'app' => $keys + ['git_sha' => $this->gitSha()],
            'database' => [
                'driver' => $database['driver'],
                'name' => $database['database'],
                'host' => $database['host'] ?? null,
                'file' => $databaseFile,
                'sha256' => hash_file('sha256', $staging.'/'.$databaseFile),
                'objects' => $databaseObjects,
                'required_archive_tables' => $requiredTables,
            ],
            'disks' => $disks,
            'media_manifest' => 'media-manifest.jsonl',
            'media_file_count' => $files,
        ];
        self::writePrivateJson($staging.'/recovery.json', $recovery);

        return ['ok' => true, 'database_driver' => $database['driver'], 'archive_disks' => count($disks), 'media_files' => $files];
    }

    /** The optional connection file belongs to an already restored, isolated MySQL DB. */
    public static function verify(string $snapshot, string $restoreRoot, ?string $connectionFile = null): array
    {
        $snapshot = self::directory($snapshot);
        $restoreRoot = self::directory($restoreRoot);
        $recovery = self::readPrivateJson($snapshot.'/recovery.json');
        if (($recovery['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('recovery_format_unsupported');
        }
        $database = $recovery['database'] ?? [];
        $databaseFile = ($database['driver'] ?? null) === 'sqlite' ? 'database.sqlite' : 'database.sql';
        $databasePath = self::containedFile($snapshot, $databaseFile);
        if (! hash_equals((string) ($database['sha256'] ?? ''), hash_file('sha256', $databasePath))) {
            throw new RuntimeException('database_checksum_mismatch');
        }

        if (($database['driver'] ?? null) === 'sqlite') {
            $connection = ['driver' => 'sqlite', 'database' => $databasePath];
        } else {
            if ($connectionFile === null) {
                throw new RuntimeException('restore_database_connection_required');
            }
            $connection = self::readPrivateJson($connectionFile);
            if (! in_array($connection['driver'] ?? '', ['mysql', 'mariadb'], true)
                || ! preg_match('/\Abackup_verify_[a-zA-Z0-9_]+\z/', $connection['database'] ?? '')
                || ($connection['database'] ?? '') === ($database['name'] ?? '')) {
                throw new RuntimeException('restore_database_not_isolated');
            }
        }

        $pdo = self::connect($connection);
        if ($connection['driver'] === 'sqlite') {
            $pdo->exec('PRAGMA query_only = ON');
            if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
                throw new RuntimeException('database_integrity_failed');
            }
            if ($pdo->query('PRAGMA foreign_key_check')->fetch() !== false) {
                throw new RuntimeException('database_foreign_keys_failed');
            }
        } else {
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            $pdo->beginTransaction();
        }

        $helper = new self($snapshot, []);
        $expectedObjects = $database['objects'] ?? [];
        $restoredObjects = $helper->databaseObjects($pdo, $connection);
        ksort($expectedObjects);
        ksort($restoredObjects);
        if ($restoredObjects !== $expectedObjects) {
            throw new RuntimeException('restored_database_objects_mismatch');
        }
        foreach ($database['required_archive_tables'] ?? [] as $table) {
            if (! in_array($table, ['avito_chats', 'avito_messages', 'avito_message_attachments'], true)
                || ! $helper->tableExists($pdo, $table)) {
                throw new RuntimeException('restored_archive_table_missing');
            }
        }
        $encrypter = $helper->encrypter($recovery['app'] ?? []);
        $encryptedValues = 0;
        foreach (self::ENCRYPTED_COLUMNS as $table => $columns) {
            if (! $helper->tableExists($pdo, $table)) {
                continue;
            }
            $existingColumns = $helper->columns($pdo, $table);
            $columns = array_values(array_intersect($columns, $existingColumns));
            if ($columns === []) {
                continue;
            }
            $query = $pdo->query('SELECT '.implode(', ', array_map(fn ($column) => '`'.$column.'`', $columns)).' FROM `'.$table.'`');
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                foreach ($row as $value) {
                    if ($value === null) {
                        continue;
                    }
                    try {
                        $encrypter->decryptString($value);
                    } catch (\Throwable) {
                        throw new RuntimeException('archive_decryption_failed');
                    }
                    $encryptedValues++;
                }
            }
        }

        $manifest = self::containedFile($snapshot, 'media-manifest.jsonl');
        // An unnamed SQLite database uses a temporary file that is deleted on close.
        // Keep the index out of PHP memory even for decades of media references.
        $manifestIndex = new PDO('sqlite:');
        $manifestIndex->exec('CREATE TABLE files (disk TEXT NOT NULL, path TEXT NOT NULL, size INTEGER NOT NULL, sha256 TEXT NOT NULL, PRIMARY KEY(disk,path))');
        $insert = $manifestIndex->prepare('INSERT INTO files (disk,path,size,sha256) VALUES (?,?,?,?)');
        $stream = fopen($manifest, 'rb');
        $mediaFiles = 0;
        try {
            while (($line = fgets($stream)) !== false) {
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $disk = $recovery['disks'][$entry['disk'] ?? ''] ?? null;
                if (! is_array($disk) || ! is_string($disk['root'] ?? null) || ! str_starts_with($disk['root'], '/')) {
                    throw new RuntimeException('media_manifest_disk_invalid');
                }
                $mappedRoot = self::containedDirectory($restoreRoot, ltrim($disk['root'], '/'));
                $path = self::containedFile($mappedRoot, $entry['path'] ?? '');
                if (filesize($path) !== ($entry['size'] ?? null)
                    || ! hash_equals((string) ($entry['sha256'] ?? ''), hash_file('sha256', $path))) {
                    throw new RuntimeException('media_checksum_mismatch');
                }
                $insert->execute([$entry['disk'], $entry['path'], $entry['size'], $entry['sha256']]);
                $mediaFiles++;
            }
        } finally {
            fclose($stream);
        }
        if ($mediaFiles !== ($recovery['media_file_count'] ?? null)) {
            throw new RuntimeException('media_manifest_incomplete');
        }

        $archivedAttachments = 0;
        if ($helper->tableExists($pdo, 'avito_message_attachments')) {
            $find = $manifestIndex->prepare('SELECT size FROM files WHERE disk = ? AND path = ?');
            $query = $pdo->query('SELECT storage_disk, storage_path, size_bytes FROM avito_message_attachments WHERE archived_at IS NOT NULL');
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $find->execute([$row['storage_disk'], $row['storage_path']]);
                $entry = $find->fetch(PDO::FETCH_ASSOC);
                if ($entry === false || ($row['size_bytes'] !== null && (int) $entry['size'] !== (int) $row['size_bytes'])) {
                    throw new RuntimeException('archived_attachment_missing_or_inconsistent');
                }
                $archivedAttachments++;
            }
        }

        $counts = [];
        foreach (['avito_chats', 'avito_messages'] as $table) {
            $counts[$table] = $helper->tableExists($pdo, $table) ? (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn() : 0;
        }

        return ['ok' => true, 'encrypted_values_verified' => $encryptedValues, 'archived_attachments_verified' => $archivedAttachments, 'media_files_verified' => $mediaFiles, 'counts' => $counts];
    }

    private function databaseConfiguration(): array
    {
        $name = $this->config['database']['default'] ?? '';
        $configuration = (new ConfigurationUrlParser)->parseConfiguration($this->config['database']['connections'][$name] ?? []);
        if (! in_array($configuration['driver'] ?? '', ['sqlite', 'mysql', 'mariadb'], true)
            || ($configuration['prefix'] ?? '') !== '') {
            throw new RuntimeException('database_configuration_unsupported');
        }

        return $configuration;
    }

    private static function connect(array $configuration): PDO
    {
        if (($configuration['driver'] ?? '') === 'sqlite') {
            if (! is_file($configuration['database'] ?? '') || is_link($configuration['database'])) {
                throw new RuntimeException('database_file_invalid');
            }
            $pdo = new PDO('sqlite:'.$configuration['database']);
        } elseif (in_array($configuration['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            foreach (['database', 'host', 'unix_socket', 'port'] as $key) {
                if (preg_match('/[;\x00\r\n]/', (string) ($configuration[$key] ?? ''))) {
                    throw new RuntimeException('database_connection_invalid');
                }
            }
            $dsn = ! empty($configuration['unix_socket'])
                ? 'mysql:unix_socket='.$configuration['unix_socket']
                : 'mysql:host='.($configuration['host'] ?? '127.0.0.1').';port='.($configuration['port'] ?? 3306);
            $pdo = new PDO($dsn.';dbname='.$configuration['database'].';charset=utf8mb4', $configuration['username'] ?? '', $configuration['password'] ?? '', $configuration['options'] ?? []);
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        } else {
            throw new RuntimeException('database_driver_unsupported');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function engines(PDO $pdo, array $database): array
    {
        if ($database['driver'] === 'sqlite') {
            return [];
        }
        $query = $pdo->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
        $query->execute([$database['database']]);

        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    private function databaseObjects(PDO $pdo, array $database): array
    {
        $counts = ['views' => 0, 'routines' => 0, 'events' => 0, 'triggers' => 0];
        if ($database['driver'] === 'sqlite') {
            foreach (['views' => 'view', 'triggers' => 'trigger'] as $type => $sqliteType) {
                $query = $pdo->prepare('SELECT COUNT(*) FROM sqlite_master WHERE type = ?');
                $query->execute([$sqliteType]);
                $counts[$type] = (int) $query->fetchColumn();
                $query->closeCursor();
            }

            return $counts;
        }
        foreach (['views' => 'TABLE_SCHEMA', 'routines' => 'ROUTINE_SCHEMA', 'events' => 'EVENT_SCHEMA', 'triggers' => 'TRIGGER_SCHEMA'] as $type => $column) {
            $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.'.strtoupper($type).' WHERE '.$column.' = ?');
            $query->execute([$database['database']]);
            $counts[$type] = (int) $query->fetchColumn();
            $query->closeCursor();
        }

        return $counts;
    }

    private function databaseEstimatedBytes(PDO $pdo, array $database): int
    {
        if ($database['driver'] === 'sqlite') {
            // Uncheckpointed WAL pages must be included in the free-space estimate.
            $bytes = 0;
            foreach (['', '-wal', '-shm'] as $suffix) {
                $path = $database['database'].$suffix;
                if (is_file($path) && ! is_link($path)) {
                    $bytes += filesize($path);
                }
            }

            return $bytes;
        }
        $query = $pdo->prepare("SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
        $query->execute([$database['database']]);

        return (int) $query->fetchColumn();
    }

    private function mediaEstimatedBytes(array $disks): int
    {
        $bytes = 0;
        foreach ($this->mediaFiles($disks) as $entry) {
            $bytes += filesize($entry['file']);
        }

        return $bytes;
    }

    private function archiveDisks(PDO $pdo): array
    {
        $names = [(string) ($this->config['avito']['messenger']['archive_disk'] ?? 'avito')];
        if ($this->tableExists($pdo, 'avito_message_attachments')) {
            $names = array_merge($names, $pdo->query('SELECT DISTINCT storage_disk FROM avito_message_attachments WHERE archived_at IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
        }
        $disks = [];
        foreach (array_unique($names) as $name) {
            $disk = $this->config['filesystems']['disks'][$name] ?? [];
            if (($disk['driver'] ?? '') !== 'local' || ! is_string($disk['root'] ?? null) || ! str_starts_with($disk['root'], '/')) {
                throw new RuntimeException('archive_disk_not_local');
            }
            $root = rtrim($disk['root'], '/');
            if ($root === '' || str_contains($root, '/..') || is_link($root)) {
                throw new RuntimeException('archive_disk_root_invalid');
            }
            if (is_dir($root) && realpath($root) !== $root) {
                throw new RuntimeException('archive_disk_root_symlink');
            }
            // A new installation may have no files yet; the orchestrator creates empty
            // disk roots before backing them up. Existing archived rows must never do so.
            if (! is_dir($root)) {
                if ($this->tableExists($pdo, 'avito_message_attachments')) {
                    $query = $pdo->prepare('SELECT COUNT(*) FROM avito_message_attachments WHERE archived_at IS NOT NULL AND storage_disk = ?');
                    $query->execute([$name]);
                    if ((int) $query->fetchColumn() > 0) {
                        throw new RuntimeException('archive_disk_missing');
                    }
                }
            }
            $disks[$name] = ['root' => $root];
        }

        return $disks;
    }

    private function recoveryKeys(): array
    {
        return [
            'key' => $this->config['app']['key'] ?? '',
            'previous_keys' => array_values($this->config['app']['previous_keys'] ?? []),
            'cipher' => $this->config['app']['cipher'] ?? 'AES-256-CBC',
        ];
    }

    private function encrypter(array $keys): Encrypter
    {
        $decode = static function ($key): string {
            if (! is_string($key) || $key === '') {
                throw new RuntimeException('application_key_invalid');
            }
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7), true);
                if ($key === false) {
                    throw new RuntimeException('application_key_invalid');
                }
            }

            return $key;
        };
        try {
            $encrypter = new Encrypter($decode($keys['key'] ?? null), $keys['cipher'] ?? 'AES-256-CBC');
            $previous = array_map($decode, $keys['previous_keys'] ?? []);
            foreach ($previous as $key) {
                if (! Encrypter::supported($key, $keys['cipher'] ?? 'AES-256-CBC')) {
                    throw new RuntimeException('application_key_invalid');
                }
            }

            return $encrypter->previousKeys($previous);
        } catch (\Throwable) {
            throw new RuntimeException('application_key_invalid');
        }
    }

    private function privateStaging(string $path): string
    {
        $path = self::directory($path);
        if ((fileperms($path) & 0777) !== 0700) {
            throw new RuntimeException('staging_permissions_invalid');
        }
        foreach ([$this->appDirectory.'/public', $this->appDirectory.'/storage'] as $forbidden) {
            $forbidden = realpath($forbidden) ?: $forbidden;
            if ($path === $forbidden || str_starts_with($path, $forbidden.'/')) {
                throw new RuntimeException('staging_location_invalid');
            }
        }

        return $path;
    }

    private function dumpBinary(): ?string
    {
        $finder = new ExecutableFinder;

        return $finder->find('mysqldump') ?? $finder->find('mariadb-dump');
    }

    private function dumpMysql(array $database, string $staging): void
    {
        $binary = $this->dumpBinary();
        if ($binary === null) {
            throw new RuntimeException('database_dump_tool_missing');
        }
        $options = ['user' => $database['username'] ?? '', 'password' => $database['password'] ?? '', 'host' => $database['host'] ?? '127.0.0.1', 'port' => $database['port'] ?? 3306];
        if (! empty($database['unix_socket'])) {
            $options['socket'] = $database['unix_socket'];
        }
        if (defined('PDO::MYSQL_ATTR_SSL_CA') && isset($database['options'][PDO::MYSQL_ATTR_SSL_CA])) {
            $options['ssl-ca'] = $database['options'][PDO::MYSQL_ATTR_SSL_CA];
        }
        $defaults = "[client]\n";
        foreach ($options as $key => $value) {
            $defaults .= $key.'="'.strtr((string) $value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r']).'"'."\n";
        }
        $credentials = $staging.'/.mysql-'.bin2hex(random_bytes(12)).'.cnf';
        self::writePrivate($credentials, $defaults);
        $dump = $staging.'/database.sql';
        self::writePrivate($dump, '');
        try {
            // Read only this credential file; a root user's unrelated ~/.my.cnf
            // must not silently select a different host or database account.
            $arguments = [$binary, '--defaults-file='.$credentials, '--single-transaction', '--quick', '--hex-blob', '--routines', '--events', '--triggers', '--no-tablespaces', '--default-character-set=utf8mb4', '--result-file='.$dump];
            $help = new Process([$binary, '--no-defaults', '--help']);
            $help->setTimeout(30)->run();
            if (! $help->isSuccessful()) {
                throw new RuntimeException('database_dump_tool_failed');
            }
            if (str_contains($help->getOutput(), '--no-login-paths')) {
                array_splice($arguments, 2, 0, ['--no-login-paths']);
            }
            foreach (['column-statistics' => '0', 'set-gtid-purged' => 'OFF'] as $option => $value) {
                if (str_contains($help->getOutput(), $option)) {
                    $arguments[] = '--'.$option.'='.$value;
                }
            }
            $arguments[] = '--';
            $arguments[] = $database['database'];
            $process = new Process($arguments);
            $process->setTimeout(3600)->disableOutput()->run();
            if (! $process->isSuccessful() || filesize($dump) === 0) {
                throw new RuntimeException('database_dump_failed');
            }
        } finally {
            unlink($credentials);
        }
    }

    private function writeMediaManifest(string $path, array $disks): int
    {
        self::writePrivate($path, '');
        $stream = fopen($path, 'ab');
        $count = 0;
        try {
            foreach ($this->mediaFiles($disks) as $entry) {
                $record = ['disk' => $entry['disk'], 'path' => $entry['path'], 'size' => filesize($entry['file']), 'sha256' => hash_file('sha256', $entry['file'])];
                if (fwrite($stream, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") === false) {
                    throw new RuntimeException('manifest_write_failed');
                }
                $count++;
            }
        } finally {
            fclose($stream);
        }

        return $count;
    }

    private function mediaFiles(array $disks): \Generator
    {
        foreach ($disks as $name => $disk) {
            if (! is_dir($disk['root'])) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($disk['root'], \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $entry) {
                if ($entry->isLink() || ! $entry->isFile()) {
                    throw new RuntimeException('archive_file_type_invalid');
                }
                $relative = substr($entry->getPathname(), strlen($disk['root']) + 1);
                yield ['disk' => $name, 'path' => $relative, 'file' => self::containedFile($disk['root'], $relative)];
            }
        }
    }

    private function gitSha(): ?string
    {
        $process = new Process(['git', '-C', $this->appDirectory, 'rev-parse', 'HEAD']);
        $process->setTimeout(10)->run();
        $sha = trim($process->getOutput());

        return $process->isSuccessful() && preg_match('/\A[a-f0-9]{40}\z/', $sha) ? $sha : null;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $query = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?")
            : $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $query->execute([$table]);

        return (int) $query->fetchColumn() > 0;
    }

    private function columns(PDO $pdo, string $table): array
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return array_column($pdo->query('PRAGMA table_info(`'.$table.'`)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        }
        $query = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $query->execute([$table]);

        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function directory(string $path): string
    {
        $real = realpath($path);
        if ($real === false || ! is_dir($real) || is_link($path) || ! str_starts_with($path, '/')) {
            throw new RuntimeException('directory_invalid');
        }

        return $real;
    }

    private static function containedDirectory(string $root, string $relative): string
    {
        $path = self::containedPath($root, $relative);
        if (! is_dir($path)) {
            throw new RuntimeException('archive_directory_missing');
        }

        return $path;
    }

    private static function containedFile(string $root, string $relative): string
    {
        $path = self::containedPath($root, $relative);
        if (! is_file($path)) {
            throw new RuntimeException('archive_file_missing');
        }

        return $path;
    }

    private static function containedPath(string $root, string $relative): string
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, "\0") || str_contains($relative, '\\')) {
            throw new RuntimeException('archive_path_invalid');
        }
        $path = rtrim($root, '/');
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('archive_path_invalid');
            }
            $path .= '/'.$segment;
            if (is_link($path)) {
                throw new RuntimeException('archive_symlink_not_allowed');
            }
        }

        return $path;
    }

    public static function readPrivateJson(string $path): array
    {
        if (! is_file($path) || is_link($path) || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('private_file_permissions_invalid');
        }
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('private_file_format_invalid');
        }

        return $data;
    }

    private static function writePrivateJson(string $path, array $data): void
    {
        self::writePrivate($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    private static function writePrivate(string $path, string $data): void
    {
        $previous = umask(0077);
        try {
            $stream = fopen($path, 'xb');
            if ($stream === false) {
                throw new RuntimeException('private_file_create_failed');
            }
            try {
                if (fwrite($stream, $data) !== strlen($data)) {
                    throw new RuntimeException('private_file_write_failed');
                }
            } finally {
                fclose($stream);
            }
        } finally {
            umask($previous);
        }
    }
}
