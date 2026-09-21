#!/usr/bin/env python3
"""Encrypted DB/Avito backups. Only aggregate status may leave the server."""

import argparse
import contextlib
import datetime as dt
import fcntl
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys
import tempfile
import time


class BackupError(Exception):
    pass


def utc_now():
    return dt.datetime.now(dt.timezone.utc).isoformat()


def atomic_json(filename, value):
    filename = Path(filename)
    descriptor, temporary = tempfile.mkstemp(prefix=".write-", dir=filename.parent)
    try:
        with os.fdopen(descriptor, "w") as stream:
            json.dump(value, stream, ensure_ascii=False)
            stream.write("\n")
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, filename)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def private_directory(directory):
    directory = Path(directory)
    if not directory.is_absolute() or directory == Path("/") or directory.is_symlink():
        raise BackupError("unsafe_private_directory")
    directory.mkdir(mode=0o700, parents=True, exist_ok=True)
    if directory.stat().st_mode & 0o077:
        raise BackupError("private_directory_permissions")
    return directory.resolve()


def private_json(filename):
    filename = Path(filename)
    if filename.is_symlink() or not filename.is_file() or filename.stat().st_mode & 0o077:
        raise BackupError("private_file_permissions")
    with filename.open() as stream:
        return json.load(stream)


class BackupRunner:
    def __init__(self, app_dir, config_dir, work_dir, scripts_dir=None, restic="restic", php="php"):
        self.app_dir = Path(app_dir).resolve(strict=True)
        self.config_dir = private_directory(config_dir)
        self.work_dir = private_directory(work_dir)
        if self.work_dir == self.app_dir or self.app_dir in self.work_dir.parents:
            raise BackupError("work_directory_inside_application")
        self.scripts_dir = Path(scripts_dir or __file__).resolve()
        if self.scripts_dir.is_file():
            self.scripts_dir = self.scripts_dir.parent
        self.restic = restic
        self.php = php
        self.state_file = self.work_dir / "state.json"
        self.state = private_json(self.state_file) if self.state_file.exists() else {}
        self.phase = "configuration"
        self.environment = os.environ.copy()
        runtime = private_json(self.config_dir / "runtime.json")
        allowed = {"RESTIC_REPOSITORY", "RESTIC_PASSWORD_FILE", "AWS_ACCESS_KEY_ID", "AWS_SECRET_ACCESS_KEY", "AWS_DEFAULT_REGION"}
        if set(runtime.get("env", {})) != allowed or not all(isinstance(v, str) and v for v in runtime["env"].values()):
            raise BackupError("invalid_runtime_configuration")
        self.environment.update(runtime["env"])
        self.environment["MYSQL_TEST_LOGIN_FILE"] = "/dev/null"
        self.environment["RESTIC_CACHE_DIR"] = str(self.work_dir / "cache")
        password = Path(self.environment["RESTIC_PASSWORD_FILE"])
        if password.parent.resolve() != self.config_dir or password.is_symlink() or not password.is_file() or password.stat().st_mode & 0o077:
            raise BackupError("unsafe_password_file")
        self.cloud = private_json(self.config_dir / "cloud.json")
        self.host = "pischeprom-production"
        self.tag = "database-and-avito"

    def execute(self, command, timeout=7200, input_file=None):
        """Capture all subprocess output in a private log, never in Actions/journal."""
        logfile = self.work_dir / "last-operation.log"
        descriptor = os.open(logfile, os.O_WRONLY | os.O_CREAT | os.O_APPEND, 0o600)
        try:
            result = subprocess.run(command, stdin=input_file, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                    env=self.environment, timeout=timeout, check=False)
            with os.fdopen(descriptor, "ab") as log:
                descriptor = None
                log.write(("\nphase=" + self.phase + "\n").encode())
                log.write(result.stdout)
                log.write(result.stderr)
            if result.returncode:
                raise BackupError("command_failed_" + self.phase)
            return result.stdout
        finally:
            if descriptor is not None:
                os.close(descriptor)

    @contextlib.contextmanager
    def operation_lock(self):
        with (self.work_dir / "operation.lock").open("a") as lock:
            os.chmod(lock.name, 0o600)
            try:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError as error:
                raise BackupError("operation_already_running") from error
            logfile = self.work_dir / "last-operation.log"
            if logfile.is_symlink():
                raise BackupError("unsafe_log_file")
            with logfile.open("wb"):
                os.chmod(logfile, 0o600)
            yield

    @contextlib.contextmanager
    def deployment_lock(self):
        # Use the same inode as deploy-production.sh. Snapshot export and schema
        # migration cannot run together; live application writes remain enabled.
        with (self.app_dir / ".git" / "pischeprom-deploy.lock").open("r") as lock:
            try:
                fcntl.flock(lock, fcntl.LOCK_SH | fcntl.LOCK_NB)
            except BlockingIOError as error:
                raise BackupError("deployment_in_progress") from error
            yield

    def initialize(self):
        self.phase = "initialize"
        if not self.cloud.get("repository_initialized"):
            try:
                self.execute([self.restic, "cat", "config"])
            except BackupError:
                # Init refuses an existing repository, including one whose key
                # is wrong. Never overwrite it after an interrupted first run.
                self.execute([self.restic, "init", "--repository-version", "2"])
        self.execute([self.restic, "cat", "config"])
        self.cloud["repository_initialized"] = True
        atomic_json(self.config_dir / "cloud.json", self.cloud)
        return {"initialized": True}

    def source_roots(self, recovery):
        roots = []
        storage = (self.app_dir / "storage").resolve(strict=True)
        for disk in recovery["disks"].values():
            root = Path(disk["root"])
            if root.is_symlink() or any(parent.is_symlink() for parent in root.parents):
                raise BackupError("invalid_archive_root")
            if not root.exists():
                # The helper permits an empty, not-yet-created archive only
                # when the DB contains no archived references to that disk.
                continue
            root = root.resolve(strict=True)
            if root != storage and storage not in root.parents:
                raise BackupError("archive_root_outside_application_storage")
            if root.is_symlink() or not root.is_dir():
                raise BackupError("invalid_archive_root")
            roots.append(root)
        # Historical disks can overlap (local and avito). Store each file once.
        roots = sorted(set(roots), key=lambda value: len(value.parts))
        return [root for index, root in enumerate(roots) if not any(parent in root.parents for parent in roots[:index])]

    def backup(self):
        if not self.cloud.get("repository_initialized"):
            raise BackupError("repository_not_initialized")
        self.phase = "capacity_check"
        inspection = json.loads(self.execute([self.php, str(self.app_dir / "scripts/backup/export-application.php"), "inspect", str(self.app_dir)]))
        self.check_capacity(int(inspection["database_estimated_bytes"]) + int(inspection["media_estimated_bytes"]))
        self.phase = "export"
        stage = self.work_dir / "current"
        if stage.is_symlink():
            raise BackupError("unsafe_export_directory")
        if stage.exists():
            shutil.rmtree(stage)
        stage.mkdir(mode=0o700)
        with self.deployment_lock():
            self.execute([self.php, str(self.app_dir / "scripts/backup/export-application.php"), "export", str(self.app_dir), str(stage)])
            recovery = private_json(stage / "recovery.json")
            sources = self.source_roots(recovery)
        self.phase = "upload"
        output = self.execute([self.restic, "backup", "--json", "--host", self.host, "--tag", self.tag,
                               str(stage), *map(str, sources)], timeout=21600)
        summaries = [record for line in output.splitlines() if line.strip()
                     for record in [json.loads(line)] if record.get("message_type") == "summary"]
        snapshot = summaries[-1].get("snapshot_id") if summaries else None
        if not isinstance(snapshot, str) or not re.fullmatch(r"[0-9a-f]{8,64}", snapshot):
            raise BackupError("missing_snapshot_confirmation")
        self.phase = "repository_check"
        self.execute([self.restic, "check"], timeout=21600)
        self.state.update(last_backup_at=utc_now(), snapshot_id=snapshot, export_path=str(stage),
                          last_backup_status="ok", last_backup_error=None, last_error=None)
        atomic_json(self.state_file, self.state)
        # Decrypted SQL and APP_KEY are needed only while exporting/uploading.
        shutil.rmtree(stage)
        return {"backup": "ok", "snapshot_id": snapshot, "completed_at": self.state["last_backup_at"]}

    def check_capacity(self, estimated_bytes):
        if estimated_bytes < 0 or shutil.disk_usage(self.work_dir).free < estimated_bytes * 3 + 1024 ** 3:
            raise BackupError("insufficient_restore_space")

    @contextlib.contextmanager
    def isolated_mysql(self, check_dir, source_database):
        """A fresh socket-only server, never a database on the production instance."""
        server = shutil.which("mysqld") or shutil.which("mariadbd")
        client = shutil.which("mysql") or shutil.which("mariadb")
        if not server or not client:
            raise BackupError("isolated_mysql_tools_missing")
        datadir = check_dir / "mysql-data"
        datadir.mkdir(mode=0o700)
        socket = check_dir / "mysql.sock"
        version = self.execute([server, "--version"], timeout=30)
        self.phase = "initialize_restore_database"
        if b"mariadb" in version.lower():
            initializer = shutil.which("mariadb-install-db") or shutil.which("mysql_install_db")
            if not initializer:
                raise BackupError("mariadb_initializer_missing")
            self.execute([initializer, "--no-defaults", "--datadir=" + str(datadir),
                          "--auth-root-authentication-method=normal", "--skip-test-db"], timeout=180)
        else:
            self.execute([server, "--no-defaults", "--initialize-insecure", "--datadir=" + str(datadir)], timeout=180)
        server_log = (check_dir / "mysql-server.log").open("wb")
        process = subprocess.Popen([server, "--no-defaults", "--datadir=" + str(datadir), "--socket=" + str(socket),
                                    "--pid-file=" + str(check_dir / "mysql.pid"), "--skip-networking",
                                    "--event-scheduler=OFF", "--skip-log-bin", "--loose-mysqlx=OFF"], stdout=server_log, stderr=subprocess.STDOUT,
                                   env=self.environment)
        try:
            connection = [client, "--no-defaults", "--protocol=SOCKET", "--socket=" + str(socket), "--user=root",
                          "--default-character-set=utf8mb4", "--binary-mode"]
            deadline = time.monotonic() + 90
            while time.monotonic() < deadline:
                if process.poll() is not None:
                    raise BackupError("restore_database_failed_to_start")
                probe = subprocess.run([*connection, "--execute=SELECT 1"], stdout=subprocess.DEVNULL,
                                       stderr=subprocess.DEVNULL, timeout=5, env=self.environment)
                if probe.returncode == 0:
                    break
                time.sleep(0.5)
            else:
                raise BackupError("restore_database_start_timeout")
            database = "backup_verify_" + secrets.token_hex(8)
            if database == source_database or not re.fullmatch(r"backup_verify_[a-z0-9]+", database):
                raise BackupError("unsafe_restore_database_name")
            self.execute([*connection, "--execute=CREATE DATABASE `" + database + "` CHARACTER SET utf8mb4"])
            yield connection, database, socket
        finally:
            if process.poll() is None:
                process.terminate()
                try:
                    process.wait(timeout=30)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=10)
            server_log.close()
            # Preserve bounded diagnostics privately before the drill directory
            # is removed. Server output never reaches Actions or the journal.
            with (check_dir / "mysql-server.log").open("rb") as diagnostics:
                diagnostics.seek(max(0, os.fstat(diagnostics.fileno()).st_size - 65536))
                with (self.work_dir / "last-operation.log").open("ab") as log:
                    log.write(diagnostics.read())

    def verify(self):
        snapshot = self.state.get("snapshot_id", "")
        if not re.fullmatch(r"[0-9a-f]{8,64}", snapshot):
            raise BackupError("no_successful_snapshot")
        self.phase = "capacity_check"
        statistics = json.loads(self.execute([self.restic, "stats", snapshot, "--mode", "restore-size", "--json"]))
        self.check_capacity(int(statistics["total_size"]))
        self.phase = "restore"
        check_dir = Path(tempfile.mkdtemp(prefix="verify-", dir=self.work_dir))
        try:
            restore_root = check_dir / "files"
            restore_root.mkdir(mode=0o700)
            self.execute([self.restic, "restore", snapshot, "--target", str(restore_root), "--verify"], timeout=21600)
            original_export = Path(self.state["export_path"])
            if original_export != self.work_dir / "current":
                raise BackupError("invalid_snapshot_export_path")
            restored_stage = restore_root / str(original_export).lstrip("/")
            recovery = private_json(restored_stage / "recovery.json")
            command = [self.php, str(self.app_dir / "scripts/backup/verify-restored-application.php"), str(restored_stage), str(restore_root)]
            self.phase = "verify_restored_archive"
            if recovery["database"]["driver"] == "sqlite":
                self.execute(command)
            elif recovery["database"]["driver"] in {"mysql", "mariadb"}:
                with self.isolated_mysql(check_dir, recovery["database"]["name"]) as (connection, database, socket):
                    self.phase = "import_restore_database"
                    with (restored_stage / "database.sql").open("rb") as sql:
                        self.execute([*connection, database], input_file=sql, timeout=21600)
                    settings = check_dir / "database.json"
                    atomic_json(settings, {"driver": "mysql", "database": database, "username": "root", "password": "",
                                           "host": "localhost", "port": 0, "unix_socket": str(socket)})
                    self.phase = "verify_restored_archive"
                    self.execute([*command, str(settings)])
            else:
                raise BackupError("unsupported_restore_database")
            self.state.update(last_verified_at=utc_now(), verified_snapshot_id=snapshot, last_verify_status="ok", last_verify_error=None, last_error=None)
            atomic_json(self.state_file, self.state)
            return {"restore_check": "ok", "snapshot_id": snapshot, "completed_at": self.state["last_verified_at"]}
        finally:
            shutil.rmtree(check_dir)

    def status(self):
        now = dt.datetime.now(dt.timezone.utc)
        result = {"repository_initialized": bool(self.cloud.get("repository_initialized")),
                  "last_backup_at": self.state.get("last_backup_at"), "last_verified_at": self.state.get("last_verified_at"),
                  "last_error": self.state.get("last_error")}
        result["last_backup_error"] = self.state.get("last_backup_error")
        result["last_verify_error"] = self.state.get("last_verify_error")
        for key, limit in [("backup", 36 * 3600), ("verified", 9 * 86400)]:
            value = self.state.get("last_" + key + "_at")
            try:
                age = (now - dt.datetime.fromisoformat(value)).total_seconds()
                result[key + "_fresh"] = 0 <= age <= limit
            except (TypeError, ValueError):
                result[key + "_fresh"] = False
        result["healthy"] = (result["repository_initialized"] and result["backup_fresh"] and result["verified_fresh"]
                             and self.state.get("last_backup_status") == "ok" and self.state.get("last_verify_status") == "ok"
                             and not result["last_error"])
        return result

    def record_failure(self, reason, action=None):
        failure = {"at": utc_now(), "phase": self.phase, "code": reason}
        self.state.update(last_error=failure)
        label = {"run": "backup", "verify": "verify"}.get(action)
        if label:
            self.state.update({"last_" + label + "_status": "failed", "last_" + label + "_error": failure})
        atomic_json(self.state_file, self.state)


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser()
    parser.add_argument("action", choices=["init", "run", "verify", "status"])
    parser.add_argument("--app-dir", required=True)
    parser.add_argument("--config-dir", default="/etc/pischeprom-backup")
    parser.add_argument("--work-dir", default="/var/lib/pischeprom-backup")
    parser.add_argument("--scripts-dir")
    arguments = parser.parse_args()
    runner = None
    try:
        runner = BackupRunner(arguments.app_dir, arguments.config_dir, arguments.work_dir, arguments.scripts_dir)
        if arguments.action == "status":
            result = runner.status()
        else:
            with runner.operation_lock():
                try:
                    result = {"init": runner.initialize, "run": runner.backup, "verify": runner.verify}[arguments.action]()
                except Exception as error:
                    code = str(error) if isinstance(error, BackupError) else "operation_failed"
                    with contextlib.suppress(Exception):
                        runner.record_failure(code, arguments.action)
                    if arguments.action == "run":
                        stage = runner.work_dir / "current"
                        if stage.is_dir() and not stage.is_symlink():
                            shutil.rmtree(stage, ignore_errors=True)
                    raise
        print(json.dumps(result, ensure_ascii=False))
        return 0 if result.get("healthy", True) else 1
    except Exception as error:
        code = str(error) if isinstance(error, BackupError) else "operation_failed"
        print(json.dumps({"ok": False, "code": code}))
        return 1


if __name__ == "__main__":
    sys.exit(main())
