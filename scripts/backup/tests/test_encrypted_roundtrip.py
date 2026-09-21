"""Real encrypted restic -> restore -> PHP verification using synthetic data only."""
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
SPEC = importlib.util.spec_from_file_location('roundtrip_runner', ROOT / 'scripts/backup/run-backup.py')
RUNTIME = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(RUNTIME)
RESTIC = os.environ.get('RESTIC_TEST_BINARY') or shutil.which('restic')


@unittest.skipUnless(RESTIC and shutil.which('php') and (ROOT / 'vendor/autoload.php').is_file(), 'real restic/PHP dependencies required')
class EncryptedRoundtripTest(unittest.TestCase):
    def test_restores_database_media_and_rotated_keys_after_source_files_are_lost(self):
        with tempfile.TemporaryDirectory(prefix='backup-roundtrip-') as temporary:
            base = Path(temporary).resolve()
            app, config, work = (base / name for name in ('app', 'config', 'work'))
            for directory in [app / 'bootstrap/cache', app / 'storage/archive', app / '.git', app / 'scripts', config, work]:
                directory.mkdir(mode=0o700, parents=True, exist_ok=True)
            (app / '.git/pischeprom-deploy.lock').touch(mode=0o600)
            (app / 'scripts/backup').symlink_to(ROOT / 'scripts/backup', target_is_directory=True)
            fixture = r'''<?php
require $argv[2].'/vendor/autoload.php';
$app = $argv[1];
$key = random_bytes(32); $oldKey = random_bytes(32);
$config = [
 'app' => ['key'=>'base64:'.base64_encode($key), 'previous_keys'=>['base64:'.base64_encode($oldKey)], 'cipher'=>'AES-256-CBC'],
 'database' => ['default'=>'sqlite', 'connections'=>['sqlite'=>['driver'=>'sqlite','database'=>$app.'/source.sqlite']]],
 'filesystems' => ['disks'=>['avito'=>['driver'=>'local','root'=>$app.'/storage/archive']]],
 'avito' => ['messenger'=>['archive_disk'=>'avito']],
];
file_put_contents($app.'/bootstrap/app.php', '<?php throw new RuntimeException("Providers must not run");');
file_put_contents($app.'/bootstrap/cache/config.php', '<?php return '.var_export($config,true).';');
$pdo = new PDO('sqlite:'.$app.'/source.sqlite');
$pdo->exec('CREATE TABLE avito_chats (id INTEGER PRIMARY KEY, payload TEXT)');
$pdo->exec('CREATE TABLE avito_messages (id INTEGER PRIMARY KEY, content TEXT)');
$pdo->exec('CREATE TABLE avito_message_attachments (id INTEGER PRIMARY KEY, storage_disk TEXT, storage_path TEXT, size_bytes INTEGER, archived_at TEXT)');
$pdo->prepare('INSERT INTO avito_chats VALUES (1, ?)')->execute([(new Illuminate\Encryption\Encrypter($oldKey,'AES-256-CBC'))->encryptString('{"history":"ten years"}')]);
$pdo->prepare('INSERT INTO avito_messages VALUES (1, ?)')->execute([(new Illuminate\Encryption\Encrypter($key,'AES-256-CBC'))->encryptString('{"text":"context"}')]);
file_put_contents($app.'/storage/archive/synthetic.txt', 'saved attachment');
$pdo->exec("INSERT INTO avito_message_attachments VALUES (1, 'avito', 'synthetic.txt', 16, '2020-01-01')");
'''
            fixture_file = base / 'fixture.php'
            fixture_file.write_text(fixture)
            subprocess.run(['php', str(fixture_file), str(app), str(ROOT)], check=True, capture_output=True)
            password = config / 'restic-password'
            password.write_text(secrets.token_urlsafe(48))
            password.chmod(0o600)
            RUNTIME.atomic_json(config / 'runtime.json', {'env': {
                'RESTIC_REPOSITORY': str(base / 'repository'),
                'RESTIC_PASSWORD_FILE': str(password),
                'AWS_ACCESS_KEY_ID': 'synthetic', 'AWS_SECRET_ACCESS_KEY': 'synthetic', 'AWS_DEFAULT_REGION': 'ru-central1',
            }})
            RUNTIME.atomic_json(config / 'cloud.json', {'repository_initialized': False})
            runner = RUNTIME.BackupRunner(app, config, work, restic=RESTIC)
            with runner.operation_lock():
                runner.initialize()
                result = runner.backup()
                self.assertEqual('ok', result['backup'])
                self.assertFalse((work / 'current').exists(), 'plaintext snapshot must be removed')
                # Lose the original data/key, then prove recovery uses only the snapshot.
                (app / 'source.sqlite').unlink()
                (app / 'storage/archive/synthetic.txt').unlink()
                (app / 'bootstrap/cache/config.php').unlink()
                self.assertEqual('ok', runner.verify()['restore_check'])
                self.assertTrue(runner.status()['healthy'])
            self.assertFalse(list(work.glob('verify-*')), 'decrypted drill data must be removed')


if __name__ == '__main__':
    unittest.main()
