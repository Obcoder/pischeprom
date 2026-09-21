"""Real isolated MySQL -> encrypted restic -> a second isolated MySQL server."""

import contextlib
import importlib.util
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[3]
SPEC = importlib.util.spec_from_file_location('mysql_roundtrip_runner', ROOT / 'scripts/backup/run-backup.py')
RUNTIME = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(RUNTIME)
RESTIC = os.environ.get('RESTIC_TEST_BINARY') or shutil.which('restic')
PHP = shutil.which('php')
MYSQL_SERVER = shutil.which('mysqld') or shutil.which('mariadbd')
MYSQL_CLIENT = shutil.which('mysql') or shutil.which('mariadb')
MYSQL_DUMP = shutil.which('mysqldump') or shutil.which('mariadb-dump')


@unittest.skipUnless(
    RESTIC and PHP and MYSQL_SERVER and MYSQL_CLIENT and MYSQL_DUMP and (ROOT / 'vendor/autoload.php').is_file(),
    'real restic/PHP/MySQL server, client and dump tools required',
)
class MysqlRoundtripTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        probe = subprocess.run([PHP, '-r', 'echo json_encode(PDO::getAvailableDrivers());'],
                               capture_output=True, check=False, timeout=30)
        if probe.returncode or not {'mysql', 'sqlite'}.issubset(json.loads(probe.stdout)):
            raise unittest.SkipTest('PHP pdo_mysql and pdo_sqlite extensions required')
        version = subprocess.run([MYSQL_SERVER, '--version'], capture_output=True, check=False, timeout=30)
        if b'mariadb' in version.stdout.lower() and not (shutil.which('mariadb-install-db') or shutil.which('mysql_install_db')):
            raise unittest.SkipTest('MariaDB initialization tool required')

    def test_recovers_full_database_media_and_rotated_keys_after_source_server_is_deleted(self):
        # Short socket paths also work with the macOS sockaddr_un length limit.
        with tempfile.TemporaryDirectory(prefix='backup-mysql-', dir='/tmp') as temporary:
            base = Path(temporary).resolve()
            app, config, work, source = (base / name for name in ('app', 'config', 'work', 'source'))
            for directory in [app / 'bootstrap/cache', app / 'storage/archive', app / 'storage/legacy',
                              app / '.git', app / 'scripts', config, work, source]:
                directory.mkdir(mode=0o700, parents=True, exist_ok=True)
            (app / '.git/pischeprom-deploy.lock').touch(mode=0o600)
            (app / 'scripts/backup').symlink_to(ROOT / 'scripts/backup', target_is_directory=True)
            password = config / 'restic-password'
            password.write_text(secrets.token_urlsafe(48))
            password.chmod(0o600)
            RUNTIME.atomic_json(config / 'runtime.json', {'env': {
                'RESTIC_REPOSITORY': str(base / 'repository'),
                'RESTIC_PASSWORD_FILE': str(password),
                'AWS_ACCESS_KEY_ID': 'synthetic', 'AWS_SECRET_ACCESS_KEY': 'synthetic',
                'AWS_DEFAULT_REGION': 'ru-central1',
            }})
            RUNTIME.atomic_json(config / 'cloud.json', {'repository_initialized': False})
            runner = RUNTIME.BackupRunner(app, config, work, restic=RESTIC, php=PHP)
            fixture_file = base / 'fixture.php'
            fixture_file.write_text(r'''<?php
require $argv[2].'/vendor/autoload.php';
$app = $argv[1]; $socket = $argv[3]; $database = $argv[4];
if (! preg_match('/\Abackup_verify_[a-z0-9]+\z/', $database)) { exit(2); }
$key = random_bytes(32); $oldKey = random_bytes(32);
$current = new Illuminate\Encryption\Encrypter($key, 'AES-256-CBC');
$previous = new Illuminate\Encryption\Encrypter($oldKey, 'AES-256-CBC');
$config = [
 'app' => ['key'=>'base64:'.base64_encode($key), 'previous_keys'=>['base64:'.base64_encode($oldKey)], 'cipher'=>'AES-256-CBC'],
 'database' => ['default'=>'mysql', 'connections'=>['mysql'=>[
   'driver'=>'mysql', 'host'=>'localhost', 'port'=>0, 'unix_socket'=>$socket,
   'database'=>$database, 'username'=>'root', 'password'=>'', 'charset'=>'utf8mb4', 'prefix'=>'',
 ]]],
 'filesystems' => ['disks'=>[
   'avito'=>['driver'=>'local','root'=>$app.'/storage/archive'],
   'legacy'=>['driver'=>'local','root'=>$app.'/storage/legacy'],
 ]],
 'avito' => ['messenger'=>['archive_disk'=>'avito']],
];
file_put_contents($app.'/bootstrap/app.php', '<?php throw new RuntimeException("Providers must not run");');
file_put_contents($app.'/bootstrap/cache/config.php', '<?php return '.var_export($config,true).';');
$pdo = new PDO('mysql:unix_socket='.$socket.';dbname='.$database.';charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE avito_chats (id BIGINT PRIMARY KEY, payload LONGTEXT) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE avito_messages (id BIGINT PRIMARY KEY, avito_chat_id BIGINT NOT NULL, content LONGTEXT, quote LONGTEXT, payload LONGTEXT, FOREIGN KEY(avito_chat_id) REFERENCES avito_chats(id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE avito_message_attachments (id BIGINT PRIMARY KEY, avito_message_id BIGINT NOT NULL, storage_disk VARCHAR(48), storage_path TEXT, size_bytes BIGINT, archived_at DATETIME, FOREIGN KEY(avito_message_id) REFERENCES avito_messages(id)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE ordinary_business_rows (id BIGINT PRIMARY KEY, binary_data BLOB NOT NULL) ENGINE=InnoDB');
$pdo->prepare('INSERT INTO ordinary_business_rows VALUES (1, ?)')->execute(["\x00\xff\x01full database\x00"]);
$pdo->prepare('INSERT INTO avito_chats VALUES (1, ?)')->execute([$previous->encryptString('{"history":"saved ten years ago"}')]);
$pdo->prepare('INSERT INTO avito_messages VALUES (1, 1, ?, NULL, ?)')->execute([
 $current->encryptString('{"text":"Customer already provided delivery address"}'),
 $previous->encryptString('{"type":"text","timestamp":"2016-01-01"}'),
]);
$pdo->prepare('INSERT INTO avito_messages VALUES (2, 1, ?, ?, NULL)')->execute([
 $current->encryptString('{"text":"Agreed earlier"}'),
 $previous->encryptString('{"quoted_message_id":1}'),
]);
file_put_contents($app.'/storage/archive/synthetic.txt', 'saved attachment');
file_put_contents($app.'/storage/legacy/voice.mp4', 'historical voice');
$pdo->exec("INSERT INTO avito_message_attachments VALUES (1, 1, 'avito', 'synthetic.txt', 16, '2020-01-01')");
$pdo->exec("INSERT INTO avito_message_attachments VALUES (2, 2, 'legacy', 'voice.mp4', 16, '2016-01-01')");
''')
            with runner.operation_lock():
                runner.initialize()
                with runner.isolated_mysql(source, 'synthetic_source_only') as (_, database, socket):
                    fixture = subprocess.run([PHP, str(fixture_file), str(app), str(ROOT), str(socket), database],
                                             capture_output=True, check=False, timeout=60, env=runner.environment)
                    self.assertEqual(0, fixture.returncode, fixture.stderr.decode(errors='replace'))
                    inspection = json.loads(runner.execute([
                        PHP, str(app / 'scripts/backup/export-application.php'), 'inspect', str(app),
                    ]))
                    self.assertEqual('mysql', inspection['database_driver'])
                    self.assertEqual(0, inspection['non_transactional_tables'])
                    self.assertEqual(2, inspection['archived_attachments'])
                    self.assertEqual({'views': 0, 'routines': 0, 'events': 0, 'triggers': 0}, inspection['database_objects'])
                    result = runner.backup()
                    self.assertEqual('ok', result['backup'])
                    self.assertFalse((work / 'current').exists(), 'plaintext dump and keys must be removed')

                # Destroy the source server and original keys/media before recovery.
                shutil.rmtree(source)
                (app / 'storage/archive/synthetic.txt').unlink()
                (app / 'storage/legacy/voice.mp4').unlink()
                (app / 'bootstrap/cache/config.php').unlink()
                fixture_file.unlink()
                original_isolated_mysql = runner.isolated_mysql
                restored_rows = []

                @contextlib.contextmanager
                def verify_full_database(check_dir, source_database):
                    with original_isolated_mysql(check_dir, source_database) as restored:
                        connection, restored_database, _ = restored
                        self.assertNotEqual(database, restored_database)
                        yield restored
                        restored_rows.append(runner.execute([
                            *connection, '--batch', '--skip-column-names', restored_database,
                            '--execute=SELECT CONCAT(id, ":", HEX(binary_data)) FROM ordinary_business_rows',
                        ]).strip())

                runner.isolated_mysql = verify_full_database
                self.assertEqual('ok', runner.verify()['restore_check'])
                expected = b'1:' + b'\x00\xff\x01full database\x00'.hex().upper().encode()
                self.assertEqual([expected], restored_rows, 'full dump must include tables outside Avito and binary values')
                self.assertTrue(runner.status()['healthy'])
                self.assertEqual(result['snapshot_id'], runner.state['verified_snapshot_id'])

                # Verify the helper actually checked every saved encrypted field/media.
                log = (work / 'last-operation.log').read_text(errors='replace')
                reports = []
                for line in log.splitlines():
                    if line.startswith('{'):
                        with contextlib.suppress(json.JSONDecodeError):
                            report = json.loads(line)
                            if 'encrypted_values_verified' in report:
                                reports.append(report)
                self.assertEqual(1, len(reports))
                self.assertEqual(5, reports[0]['encrypted_values_verified'])
                self.assertEqual(2, reports[0]['archived_attachments_verified'])
                self.assertEqual({'avito_chats': 1, 'avito_messages': 2}, reports[0]['counts'])
            self.assertFalse(list(work.glob('verify-*')), 'decrypted drill database and media must be removed')


if __name__ == '__main__':
    unittest.main()
