import contextlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import MagicMock, patch


SPEC = importlib.util.spec_from_file_location("production_backup_runtime", Path(__file__).parents[1] / "run-backup.py")
runtime = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(runtime)
SNAPSHOT = "abcdef0123456789"


class BackupRuntimeTest(unittest.TestCase):
    def setUp(self):
        original_umask = os.umask(0o077)
        self.addCleanup(os.umask, original_umask)
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.base = Path(self.temporary.name).resolve()
        self.app = self.base / "application"
        self.config = self.base / "private-config"
        self.work = self.base / "private-work"
        for directory in (self.app, self.config, self.work, self.app / ".git", self.app / "storage", self.app / "storage/avito"):
            directory.mkdir(mode=0o700)
        (self.app / ".git/pischeprom-deploy.lock").touch(mode=0o600)
        self.password = self.config / "restic-password"
        self.password.write_text("synthetic-independent-recovery-password\n")
        self.password.chmod(0o600)
        runtime.atomic_json(self.config / "runtime.json", {"env": {
            "RESTIC_REPOSITORY": "s3:https://storage.yandexcloud.net/synthetic-backup/production",
            "RESTIC_PASSWORD_FILE": str(self.password), "AWS_ACCESS_KEY_ID": "synthetic-key-id",
            "AWS_SECRET_ACCESS_KEY": "synthetic-access-secret", "AWS_DEFAULT_REGION": "ru-central1",
        }})
        runtime.atomic_json(self.config / "cloud.json", {"repository_initialized": True})
        self.recovery = {"database": {"driver": "sqlite", "name": "production_database"},
                         "disks": {"avito": {"root": str(self.app / "storage/avito")}}}
        capacity = patch.object(runtime.shutil, "disk_usage", return_value=MagicMock(free=100 * 1024 ** 3))
        capacity.start()
        self.addCleanup(capacity.stop)

    def runner(self):
        return runtime.BackupRunner(self.app, self.config, self.work)

    def seed_healthy_state(self):
        runtime.atomic_json(self.work / "state.json", {
            "last_backup_at": runtime.utc_now(), "last_verified_at": runtime.utc_now(),
            "last_backup_status": "ok", "last_verify_status": "ok",
            "snapshot_id": SNAPSHOT, "verified_snapshot_id": SNAPSHOT,
            "export_path": str(self.work / "current"), "last_error": None,
        })

    def write_export(self, stage):
        stage.mkdir(mode=0o700, parents=True, exist_ok=True)
        runtime.atomic_json(stage / "recovery.json", self.recovery)
        secret = stage / "application-keys.json"
        secret.write_text("synthetic-plaintext-application-key")
        secret.chmod(0o600)
        database = stage / "database.sql"
        database.write_text("synthetic-private-customer-record")
        database.chmod(0o600)

    def successful_execution(self, runner, command, **kwargs):
        if "inspect" in command:
            return b'{"database_estimated_bytes":1000,"media_estimated_bytes":1000}'
        if "stats" in command:
            return b'{"total_size":2000}'
        if "export" in command:
            self.write_export(Path(command[-1]))
        elif "backup" in command:
            return json.dumps({"message_type": "summary", "snapshot_id": SNAPSHOT}).encode() + b"\n"
        elif "restore" in command:
            restore_root = Path(command[command.index("--target") + 1])
            restored_stage = restore_root / str(self.work / "current").lstrip("/")
            self.write_export(restored_stage)
        return b"{}"

    def main(self, action):
        arguments = ["run-backup.py", action, "--app-dir", str(self.app), "--config-dir", str(self.config), "--work-dir", str(self.work)]
        output = io.StringIO()
        with patch.object(sys, "argv", arguments), contextlib.redirect_stdout(output):
            result = runtime.main()
        return result, output.getvalue()

    def test_private_config_and_work_directories_reject_public_permissions_or_symlinks(self):
        self.config.chmod(0o755)
        with self.assertRaisesRegex(runtime.BackupError, "private_directory_permissions"):
            self.runner()
        self.config.chmod(0o700)
        link = self.base / "linked-work"
        link.symlink_to(self.work)
        with self.assertRaisesRegex(runtime.BackupError, "unsafe_private_directory"):
            runtime.BackupRunner(self.app, self.config, link)
        with self.assertRaisesRegex(runtime.BackupError, "work_directory_inside_application"):
            runtime.BackupRunner(self.app, self.config, self.app / "private-stage")

    def test_password_cannot_be_symlink_or_outside_private_config(self):
        self.password.unlink()
        external = self.base / "outside-secret"
        external.write_text("synthetic")
        external.chmod(0o600)
        self.password.symlink_to(external)
        with self.assertRaisesRegex(runtime.BackupError, "unsafe_password_file"):
            self.runner()

    def test_archive_roots_reject_escape_and_symlink_and_deduplicate_nested_disks(self):
        runner = self.runner()
        with self.assertRaisesRegex(runtime.BackupError, "archive_root_outside_application_storage"):
            runner.source_roots({"disks": {"bad": {"root": str(self.base)}}})
        link = self.app / "storage/linked"
        link.symlink_to(self.base)
        with self.assertRaisesRegex(runtime.BackupError, "invalid_archive_root"):
            runner.source_roots({"disks": {"bad": {"root": str(link)}}})
        roots = runner.source_roots({"disks": {"local": {"root": str(self.app / "storage")},
                                              "avito": {"root": str(self.app / "storage/avito")}}})
        self.assertEqual([self.app / "storage"], roots)

    def test_backup_requires_explicit_snapshot_confirmation_before_success(self):
        runner = self.runner()
        def execute(command, **kwargs):
            if "backup" in command:
                return b'{"message_type":"summary","snapshot_id":"untrusted-value"}\n'
            return self.successful_execution(runner, command, **kwargs)
        with patch.object(runner, "execute", side_effect=execute):
            with self.assertRaisesRegex(runtime.BackupError, "missing_snapshot_confirmation"):
                runner.backup()
        self.assertNotIn("last_backup_at", runner.state)
        self.assertFalse((self.work / "state.json").exists())

    def test_repository_check_failure_does_not_record_successful_backup(self):
        runner = self.runner()
        def execute(command, **kwargs):
            if "check" in command:
                raise runtime.BackupError("repository_corruption")
            return self.successful_execution(runner, command, **kwargs)
        with patch.object(runner, "execute", side_effect=execute):
            with self.assertRaisesRegex(runtime.BackupError, "repository_corruption"):
                runner.backup()
        self.assertNotIn("last_backup_at", runner.state)

    def test_successful_backup_removes_decrypted_staging_files(self):
        runner = self.runner()
        with patch.object(runner, "execute", side_effect=lambda command, **kw: self.successful_execution(runner, command, **kw)):
            result = runner.backup()
        self.assertEqual(SNAPSHOT, result["snapshot_id"])
        self.assertFalse((self.work / "current").exists())
        state = runtime.private_json(self.work / "state.json")
        self.assertEqual("ok", state["last_backup_status"])
        self.assertNotIn("synthetic-plaintext", json.dumps(state))
        self.assertNotIn("synthetic-private-customer", json.dumps(state))

    def test_failed_verify_stays_unhealthy_after_a_later_successful_backup(self):
        self.seed_healthy_state()
        def fail_verify(runner):
            runner.phase = "verify_restored_archive"
            raise runtime.BackupError("recovered_application_key_invalid")
        with patch.object(runtime.BackupRunner, "verify", fail_verify):
            exit_code, _ = self.main("verify")
        self.assertEqual(1, exit_code)
        runner = self.runner()
        self.assertFalse(runner.status()["healthy"])
        with patch.object(runner, "execute", side_effect=lambda command, **kw: self.successful_execution(runner, command, **kw)):
            runner.backup()
        self.assertFalse(runner.status()["healthy"], "A new upload must not clear a failed recovery drill")
        self.assertNotEqual("ok", runner.state.get("last_verify_status"))
        with patch.object(runner, "execute", side_effect=lambda command, **kw: self.successful_execution(runner, command, **kw)):
            runner.verify()
        self.assertTrue(runner.status()["healthy"])
        self.assertFalse(any(self.work.glob("verify-*")))

    def test_main_failure_removes_its_plaintext_stage_and_prints_only_sanitized_error(self):
        def execute(runner, command, **kwargs):
            if "backup" in command:
                raise runtime.BackupError("upload_failed")
            return self.successful_execution(runner, command, **kwargs)
        with patch.object(runtime.BackupRunner, "execute", execute):
            exit_code, output = self.main("run")
        self.assertEqual(1, exit_code)
        self.assertFalse((self.work / "current").exists())
        self.assertNotIn("synthetic-private-customer-record", output)
        self.assertNotIn("synthetic-plaintext-application-key", output)
        self.assertNotIn("synthetic-access-secret", output)
        self.assertEqual("upload_failed", json.loads(output)["code"])

    def test_competing_run_does_not_delete_active_staging_or_overwrite_state(self):
        self.seed_healthy_state()
        self.write_export(self.work / "current")
        before = (self.work / "state.json").read_bytes()
        @contextlib.contextmanager
        def locked(runner):
            raise runtime.BackupError("operation_already_running")
            yield  # pragma: no cover - retain context manager protocol
        with patch.object(runtime.BackupRunner, "operation_lock", locked):
            exit_code, output = self.main("run")
        self.assertEqual(1, exit_code)
        self.assertEqual("operation_already_running", json.loads(output)["code"])
        self.assertTrue((self.work / "current/application-keys.json").is_file())
        self.assertEqual(before, (self.work / "state.json").read_bytes())

    def test_subprocess_errors_are_private_and_never_echo_raw_credentials(self):
        runner = self.runner()
        runner.phase = "upload"
        result = subprocess.CompletedProcess(["restic"], 1, b"private-customer-content", b"synthetic-access-secret")
        output = io.StringIO()
        with patch.object(runtime.subprocess, "run", return_value=result), contextlib.redirect_stdout(output):
            with self.assertRaisesRegex(runtime.BackupError, "command_failed_upload") as raised:
                runner.execute(["restic", "backup"])
        self.assertEqual("", output.getvalue())
        self.assertNotIn("synthetic-access-secret", str(raised.exception))
        self.assertEqual(0o600, (self.work / "last-operation.log").stat().st_mode & 0o777)

    def test_restore_uses_a_separate_mysql_datadir_socket_and_random_database_without_network(self):
        runner = self.runner()
        check_dir = self.work / "verify-isolation"
        check_dir.mkdir(mode=0o700)
        process = MagicMock()
        process.poll.return_value = None
        commands = []
        def execute(command, **kwargs):
            commands.append(command)
            return b"mysqld Ver 8.0" if "--version" in command else b""
        def which(name):
            return "/synthetic/bin/" + name if name in ("mysqld", "mysql") else None
        with patch.object(runtime.shutil, "which", side_effect=which), patch.object(runner, "execute", side_effect=execute), \
                patch.object(runtime.subprocess, "Popen", return_value=process) as launch, \
                patch.object(runtime.subprocess, "run", return_value=subprocess.CompletedProcess([], 0)) as probe:
            with runner.isolated_mysql(check_dir, "production_database") as (connection, database, socket):
                self.assertTrue(database.startswith("backup_verify_"))
                self.assertNotEqual("production_database", database)
                self.assertEqual(check_dir / "mysql.sock", socket)
                self.assertIn("--protocol=SOCKET", connection)
                self.assertIn("--socket=" + str(socket), connection)
                self.assertIn("--no-defaults", connection)
                self.assertFalse(any(arg.startswith(("--host=", "--port=")) for arg in connection))
        server_command = launch.call_args.args[0]
        self.assertIn("--skip-networking", server_command)
        self.assertIn("--no-defaults", server_command)
        self.assertIn("--event-scheduler=OFF", server_command)
        self.assertIn("--skip-log-bin", server_command)
        self.assertIn("--datadir=" + str(check_dir / "mysql-data"), server_command)
        self.assertTrue(any("--initialize-insecure" in command for command in commands))
        self.assertFalse(any("production_database" in arg for command in commands for arg in command))
        self.assertNotIn("--host=localhost", probe.call_args.args[0])
        process.terminate.assert_called_once()
        process.wait.assert_called_once()

    def test_failed_restore_cleans_extracted_plaintext(self):
        self.seed_healthy_state()
        runner = self.runner()
        def execute(command, **kwargs):
            if "verify-restored-application.php" in " ".join(command):
                raise runtime.BackupError("restored_archive_corrupt")
            return self.successful_execution(runner, command, **kwargs)
        with patch.object(runner, "execute", side_effect=execute):
            with self.assertRaisesRegex(runtime.BackupError, "restored_archive_corrupt"):
                runner.verify()
        self.assertFalse(any(self.work.glob("verify-*")))

    def test_low_disk_space_stops_before_exporting_decrypted_data(self):
        runner = self.runner()
        with patch.object(runtime.shutil, "disk_usage", return_value=MagicMock(free=512 * 1024 ** 2)), \
                patch.object(runner, "execute", return_value=b'{"database_estimated_bytes":1000,"media_estimated_bytes":1000}') as execute:
            with self.assertRaisesRegex(runtime.BackupError, "insufficient_restore_space"):
                runner.backup()
        self.assertEqual(1, execute.call_count)
        self.assertIn("inspect", execute.call_args.args[0])
        self.assertFalse((self.work / "current").exists())


if __name__ == "__main__":
    unittest.main()
