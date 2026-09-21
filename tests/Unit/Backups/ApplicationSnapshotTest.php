<?php

namespace Tests\Unit\Backups;

use App\Support\Backups\ApplicationSnapshot;
use Illuminate\Encryption\Encrypter;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ApplicationSnapshotTest extends TestCase
{
    private string $directory;

    private string $key;

    private string $oldKey;

    private array $configuration;

    private PDO $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = realpath(sys_get_temp_dir()).'/application-backup-test-'.bin2hex(random_bytes(8));
        foreach (['app/public', 'app/storage', 'media/accounts', 'historical-media', 'staging', 'restored'] as $path) {
            mkdir($this->directory.'/'.$path, 0700, true);
        }
        $this->key = random_bytes(32);
        $this->oldKey = random_bytes(32);
        $this->configuration = [
            'app' => ['key' => 'base64:'.base64_encode($this->key), 'previous_keys' => ['base64:'.base64_encode($this->oldKey)], 'cipher' => 'AES-256-CBC'],
            'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => $this->directory.'/source.sqlite']]],
            'avito' => ['messenger' => ['archive_disk' => 'avito']],
            'filesystems' => ['disks' => [
                'avito' => ['driver' => 'local', 'root' => $this->directory.'/media'],
                'legacy' => ['driver' => 'local', 'root' => $this->directory.'/historical-media'],
            ]],
        ];
        $this->database = new PDO('sqlite:'.$this->directory.'/source.sqlite');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('PRAGMA journal_mode=WAL');
        $this->database->exec('CREATE TABLE avito_chats (id INTEGER PRIMARY KEY, payload TEXT)');
        $this->database->exec('CREATE TABLE avito_messages (id INTEGER PRIMARY KEY, content TEXT, quote TEXT, payload TEXT)');
        $this->database->exec('CREATE TABLE avito_message_attachments (id INTEGER PRIMARY KEY, storage_disk TEXT, storage_path TEXT, size_bytes INTEGER, archived_at TEXT)');
        $this->database->prepare('INSERT INTO avito_chats VALUES (1, ?)')->execute([(new Encrypter($this->oldKey, 'AES-256-CBC'))->encryptString('{"customer":"historical"}')]);
        $this->database->prepare('INSERT INTO avito_messages VALUES (1, ?, NULL, ?)')->execute([
            (new Encrypter($this->key, 'AES-256-CBC'))->encryptString('{"text":"Saved ten years earlier"}'),
            (new Encrypter($this->oldKey, 'AES-256-CBC'))->encryptString('{"type":"text"}'),
        ]);
        file_put_contents($this->directory.'/media/accounts/message.jpg', 'archived-image');
        file_put_contents($this->directory.'/historical-media/voice.mp4', 'historical-voice');
        $this->database->exec("INSERT INTO avito_message_attachments VALUES (1, 'avito', 'accounts/message.jpg', 14, '2026-09-21')");
        $this->database->exec("INSERT INTO avito_message_attachments VALUES (2, 'legacy', 'voice.mp4', 16, '2020-09-21')");
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_exports_a_consistent_sqlite_snapshot_with_rotated_keys_and_historical_media_disks(): void
    {
        $result = $this->snapshot()->export($this->directory.'/staging');
        $this->assertSame(['ok' => true, 'database_driver' => 'sqlite', 'archive_disks' => 2, 'media_files' => 2], $result);
        foreach (['database.sqlite', 'recovery.json', 'media-manifest.jsonl'] as $file) {
            $this->assertSame(0600, fileperms($this->directory.'/staging/'.$file) & 0777);
        }
        // Source changes after capture must not change the restored conversation.
        $this->database->exec('DELETE FROM avito_messages');
        $this->copyMediaToRestoredRoot();
        $verified = ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored');
        $this->assertTrue($verified['ok']);
        $this->assertSame(3, $verified['encrypted_values_verified']);
        $this->assertSame(2, $verified['archived_attachments_verified']);
        $this->assertSame(['avito_chats' => 1, 'avito_messages' => 1], $verified['counts']);
    }

    public function test_inspection_does_not_expose_keys_paths_or_database_names(): void
    {
        $result = $this->snapshot()->inspect();
        $encoded = json_encode($result);
        $this->assertTrue($result['application_key_valid']);
        $this->assertSame(2, $result['archived_attachments']);
        $this->assertStringNotContainsString($this->directory, $encoded);
        $this->assertStringNotContainsString(base64_encode($this->key), $encoded);
        $this->assertStringNotContainsString('source.sqlite', $encoded);
    }

    public function test_inspection_estimates_wal_database_and_all_historical_media_files(): void
    {
        // Include an unreferenced media file too: restic captures the entire disk.
        file_put_contents($this->directory.'/historical-media/pending.bin', 'pending-file');
        $expectedDatabaseBytes = 0;
        foreach (['', '-wal', '-shm'] as $suffix) {
            $path = $this->directory.'/source.sqlite'.$suffix;
            if (is_file($path)) {
                $expectedDatabaseBytes += filesize($path);
            }
        }
        $result = $this->snapshot()->inspect();
        $this->assertSame($expectedDatabaseBytes, $result['database_estimated_bytes']);
        $this->assertGreaterThan(filesize($this->directory.'/source.sqlite'), $result['database_estimated_bytes']);
        $this->assertSame(14 + 16 + 12, $result['media_estimated_bytes']);
    }

    public function test_inspection_rejects_symlinks_instead_of_counting_external_files(): void
    {
        symlink($this->directory.'/historical-media/voice.mp4', $this->directory.'/media/link.mp4');
        $this->expectExceptionMessage('archive_file_type_invalid');
        $this->snapshot()->inspect();
    }

    public function test_standalone_cli_reads_cached_config_without_booting_application_code(): void
    {
        mkdir($this->directory.'/app/bootstrap/cache', 0700, true);
        file_put_contents($this->directory.'/app/bootstrap/app.php', '<?php throw new RuntimeException("APPLICATION_MUST_NOT_BOOT");');
        file_put_contents($this->directory.'/app/bootstrap/cache/config.php', '<?php return '.var_export($this->configuration, true).';');
        $script = dirname(__DIR__, 3).'/scripts/backup/export-application.php';
        $process = new Process([PHP_BINARY, $script, 'export', $this->directory.'/app', $this->directory.'/staging']);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertTrue(json_decode($process->getOutput(), true)['ok']);
        $this->assertStringNotContainsString(base64_encode($this->key), $process->getOutput().$process->getErrorOutput());
    }

    public function test_restoration_detects_corruption_of_media_files(): void
    {
        $this->snapshot()->export($this->directory.'/staging');
        $this->copyMediaToRestoredRoot();
        file_put_contents($this->directory.'/restored'.$this->directory.'/media/accounts/message.jpg', 'corrupt-image!');
        $this->expectExceptionMessage('media_checksum_mismatch');
        ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored');
    }

    public function test_restoration_detects_a_db_reference_missing_from_the_manifest(): void
    {
        $this->database->exec("INSERT INTO avito_message_attachments VALUES (3, 'avito', 'missing.jpg', 4, '2026-09-21')");
        $this->snapshot()->export($this->directory.'/staging');
        $this->copyMediaToRestoredRoot();
        $this->expectExceptionMessage('archived_attachment_missing_or_inconsistent');
        ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored');
    }

    public function test_restoration_detects_a_missing_previous_encryption_key(): void
    {
        $this->configuration['app']['previous_keys'] = [];
        $this->snapshot()->export($this->directory.'/staging');
        $this->copyMediaToRestoredRoot();
        $this->expectExceptionMessage('archive_decryption_failed');
        ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored');
    }

    public function test_restoration_rejects_an_empty_database_even_if_the_dump_checksum_matches(): void
    {
        $this->snapshot()->export($this->directory.'/staging');
        $databasePath = $this->directory.'/staging/database.sqlite';
        $restored = new PDO('sqlite:'.$databasePath);
        $restored->exec('DROP TABLE avito_messages');
        $restored = null;
        $recoveryPath = $this->directory.'/staging/recovery.json';
        $recovery = json_decode(file_get_contents($recoveryPath), true);
        $recovery['database']['sha256'] = hash_file('sha256', $databasePath);
        file_put_contents($recoveryPath, json_encode($recovery));
        $this->expectExceptionMessage('restored_archive_table_missing');
        ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored');
    }

    public function test_restoration_detects_an_omitted_database_view(): void
    {
        $this->database->exec('CREATE VIEW saved_chat_ids AS SELECT id FROM avito_chats');
        $this->snapshot()->export($this->directory.'/staging');
        $databasePath = $this->directory.'/staging/database.sqlite';
        $restored = new PDO('sqlite:'.$databasePath);
        $restored->exec('DROP VIEW saved_chat_ids');
        $restored = null;
        $recoveryPath = $this->directory.'/staging/recovery.json';
        $recovery = json_decode(file_get_contents($recoveryPath), true);
        $this->assertSame(1, $recovery['database']['objects']['views']);
        $recovery['database']['sha256'] = hash_file('sha256', $databasePath);
        file_put_contents($recoveryPath, json_encode($recovery));
        $this->expectExceptionMessage('restored_database_objects_mismatch');
        ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored');
    }

    public function test_restoration_rejects_a_manifest_path_that_escapes_the_disk_root(): void
    {
        $this->snapshot()->export($this->directory.'/staging');
        $this->copyMediaToRestoredRoot();
        $manifest = $this->directory.'/staging/media-manifest.jsonl';
        $record = json_decode(file($manifest)[0], true);
        $record['path'] = '../outside.jpg';
        file_put_contents($manifest, json_encode($record)."\n");
        $this->expectExceptionMessage('archive_path_invalid');
        ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored');
    }

    public function test_restoration_rejects_database_corruption_before_opening_it(): void
    {
        $this->snapshot()->export($this->directory.'/staging');
        file_put_contents($this->directory.'/staging/database.sqlite', 'corrupted');
        $this->expectExceptionMessage('database_checksum_mismatch');
        ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored');
    }

    public function test_export_rejects_symlinks_in_media_even_when_target_exists(): void
    {
        symlink($this->directory.'/historical-media/voice.mp4', $this->directory.'/media/leaked.mp4');
        $this->expectExceptionMessage('archive_file_type_invalid');
        $this->snapshot()->export($this->directory.'/staging');
    }

    public function test_export_rejects_public_or_storage_staging(): void
    {
        $this->expectExceptionMessage('staging_location_invalid');
        $this->snapshot()->export($this->directory.'/app/storage');
    }

    public function test_export_rejects_staging_readable_by_other_users(): void
    {
        chmod($this->directory.'/staging', 0750);
        $this->expectExceptionMessage('staging_permissions_invalid');
        $this->snapshot()->export($this->directory.'/staging');
    }

    public function test_restoration_rejects_production_database_as_verification_target(): void
    {
        $this->snapshot()->export($this->directory.'/staging');
        $recoveryPath = $this->directory.'/staging/recovery.json';
        $recovery = json_decode(file_get_contents($recoveryPath), true);
        $recovery['database']['driver'] = 'mysql';
        $recovery['database']['name'] = 'production';
        rename($this->directory.'/staging/database.sqlite', $this->directory.'/staging/database.sql');
        file_put_contents($recoveryPath, json_encode($recovery));
        $connection = $this->directory.'/connection.json';
        file_put_contents($connection, json_encode(['driver' => 'mysql', 'database' => 'production']));
        chmod($connection, 0600);
        $this->expectExceptionMessage('restore_database_not_isolated');
        ApplicationSnapshot::verify($this->directory.'/staging', $this->directory.'/restored', $connection);
    }

    private function snapshot(): ApplicationSnapshot
    {
        return new ApplicationSnapshot($this->directory.'/app', $this->configuration);
    }

    private function copyMediaToRestoredRoot(): void
    {
        foreach (['media/accounts/message.jpg', 'historical-media/voice.mp4'] as $relative) {
            $destination = $this->directory.'/restored'.$this->directory.'/'.$relative;
            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0700, true);
            }
            copy($this->directory.'/'.$relative, $destination);
        }
    }
}
