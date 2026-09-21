#!/usr/bin/env python3
"""Provision one private backup repository. Run on the production host only.

The GitHub runner supplies a short-lived IAM token file; backup credentials and
the repository password never leave this host except for password escrow in
Yandex Lockbox. ``plan`` performs GET requests and does not read secret payloads.

API contracts: https://yandex.cloud/en/docs/storage/api-ref/Bucket/update
https://yandex.cloud/en/docs/iam/awscompatibility/api-ref/AccessKey/create
https://yandex.cloud/en/docs/lockbox/api-ref/Secret/create
"""

import argparse
import hmac
import json
import os
from pathlib import Path
import re
import secrets
import stat
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


STORAGE = "https://storage.api.cloud.yandex.net/storage/v1/buckets"
ACCOUNTS = "https://iam.api.cloud.yandex.net/iam/v1/serviceAccounts"
ACCESS_KEYS = "https://iam.api.cloud.yandex.net/iam/aws-compatibility/v1/accessKeys"
LOCKBOX = "https://lockbox.api.cloud.yandex.net/lockbox/v1/secrets"
PAYLOAD = "https://payload.lockbox.api.cloud.yandex.net/lockbox/v1/secrets"
OPERATIONS = "https://operation.api.cloud.yandex.net/operations"
LABELS = {"project": "pischeprom", "purpose": "production-backup", "managed-by": "github-actions"}
ACCOUNT_NAME = "pischeprom-backup-writer"
KEY_DESCRIPTION = "pischeprom-production-backup-writer"
ALLOWED_HOSTS = {urllib.parse.urlsplit(url).hostname for url in (STORAGE, ACCOUNTS, LOCKBOX, PAYLOAD, OPERATIONS)}


class ProvisionError(Exception):
    """Only fixed, non-secret messages may be raised with this exception."""


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise ProvisionError("Cloud API redirect refused.")


class CloudAPI:
    def __init__(self, token, readonly=False):
        self.token = token
        self.readonly = readonly
        self.opener = urllib.request.build_opener(NoRedirect())

    def request(self, method, url, body=None):
        parsed = urllib.parse.urlsplit(url)
        if parsed.scheme != "https" or parsed.hostname not in ALLOWED_HOSTS or parsed.port:
            raise ProvisionError("Untrusted cloud API endpoint refused.")
        if self.readonly and method != "GET":
            raise ProvisionError("Plan cannot change cloud resources.")
        headers = {"Authorization": "Bearer " + self.token, "Content-Type": "application/json"}
        data = None if body is None else json.dumps(body).encode()
        request = urllib.request.Request(url, data=data, headers=headers, method=method)
        for attempt in range(3):
            try:
                with self.opener.open(request, timeout=30) as response:
                    raw = response.read(4 * 1024 * 1024 + 1)
                if len(raw) > 4 * 1024 * 1024:
                    raise ProvisionError("Cloud API response exceeds the allowed size.")
                result = json.loads(raw)
                if not isinstance(result, dict):
                    raise ProvisionError("Cloud API returned an invalid object.")
                return result
            except urllib.error.HTTPError as error:
                # Do not print response bodies: some APIs echo submitted secrets.
                if method == "GET" and error.code in (429, 500, 502, 503, 504) and attempt < 2:
                    time.sleep(attempt + 1)
                    continue
                raise ProvisionError(f"Cloud API {method} on {parsed.hostname} failed with HTTP {error.code}.") from None
            except (urllib.error.URLError, TimeoutError, ValueError):
                raise ProvisionError("Cloud API transport or response validation failed.") from None

    def collection(self, url, key, **query):
        query["pageSize"] = 100
        items = []
        seen = set()
        while True:
            result = self.request("GET", url + "?" + urllib.parse.urlencode(query))
            page = result.get(key, [])
            if not isinstance(page, list) or any(not isinstance(item, dict) for item in page):
                raise ProvisionError("Cloud inventory has an invalid shape.")
            items.extend(page)
            token = result.get("nextPageToken")
            if not token:
                return items
            if not isinstance(token, str) or token in seen or len(seen) >= 100:
                raise ProvisionError("Cloud inventory pagination is invalid.")
            seen.add(token)
            query["pageToken"] = token

    def mutate(self, method, url, body):
        operation = self.request(method, url, body)
        for _ in range(90):
            if operation.get("error"):
                raise ProvisionError("Cloud operation failed; response details were suppressed.")
            if operation.get("done") is True:
                response = operation.get("response", {})
                if not isinstance(response, dict):
                    raise ProvisionError("Cloud operation returned invalid metadata.")
                return response
            operation_id = operation.get("id", "")
            if not re.fullmatch(r"[a-z0-9-]{10,64}", operation_id):
                raise ProvisionError("Cloud operation identifier is invalid.")
            time.sleep(1)
            operation = self.request("GET", OPERATIONS + "/" + operation_id)
        raise ProvisionError("Cloud operation timed out; inspect resources before retrying.")


def private_read(path):
    """Reject symlinks and group/world-readable files, including supplied tokens."""
    try:
        descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
        with os.fdopen(descriptor, "r", encoding="utf-8") as stream:
            info = os.fstat(stream.fileno())
            if not stat.S_ISREG(info.st_mode) or info.st_mode & 0o077 or (os.geteuid() != 0 and info.st_uid != os.geteuid()):
                raise ProvisionError("A credential file has unsafe ownership or permissions.")
            text = stream.read(1024 * 1024 + 1)
            if len(text) > 1024 * 1024:
                raise ProvisionError("A credential file exceeds the allowed size.")
            return text
    except FileNotFoundError:
        return None
    except OSError:
        raise ProvisionError("A private configuration file could not be read safely.") from None


def private_json(path):
    text = private_read(path)
    if text is None:
        return {}
    try:
        value = json.loads(text)
        if not isinstance(value, dict):
            raise ValueError()
        return value
    except ValueError:
        raise ProvisionError("A private configuration file is invalid.") from None


def private_write(path, value):
    if path.is_symlink():
        raise ProvisionError("Writing through a configuration symlink is forbidden.")
    # Re-applying as root must not make an existing app-owned password unreadable
    # to the running timer if a later cloud operation fails.
    previous = path.stat() if path.exists() else None
    descriptor, temporary = tempfile.mkstemp(prefix=".backup-", dir=path.parent)
    try:
        with os.fdopen(descriptor, "w", encoding="utf-8") as stream:
            if previous is not None and os.geteuid() == 0:
                os.fchown(stream.fileno(), previous.st_uid, previous.st_gid)
            os.fchmod(stream.fileno(), 0o600)
            stream.write(value)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, path)
        descriptor = os.open(path.parent, os.O_RDONLY | os.O_DIRECTORY)
        try:
            os.fsync(descriptor)
        finally:
            os.close(descriptor)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def named_resource(items, name, folder, kind):
    matches = [item for item in items if item.get("name") == name]
    if len(matches) > 1:
        raise ProvisionError(f"More than one managed {kind} has the expected name.")
    if not matches:
        return None
    resource = matches[0]
    labels = resource.get("labels", {})
    if kind == "bucket":
        labels = {item.get("key"): item.get("value") for item in resource.get("tags", [])}
    if resource.get("folderId") != folder or any(labels.get(k) != v for k, v in LABELS.items()):
        raise ProvisionError(f"Existing {kind} is not owned by this backup provisioner.")
    return resource


def repository_policy(bucket, writer_id, manager_id):
    arn = "arn:aws:s3:::" + bucket
    writer = {"CanonicalUser": writer_id}
    return {"Version": "2012-10-17", "Statement": [
        {"Sid": "BackupManagement", "Effect": "Allow", "Principal": {"CanonicalUser": manager_id},
         "Action": "s3:*", "Resource": [arn, arn + "/*"]},
        {"Sid": "BackupList", "Effect": "Allow", "Principal": writer,
         "Action": ["s3:ListBucket", "s3:GetBucketLocation", "s3:ListBucketMultipartUploads"], "Resource": arn},
        {"Sid": "BackupReadWrite", "Effect": "Allow", "Principal": writer,
         "Action": ["s3:GetObject", "s3:PutObject", "s3:AbortMultipartUpload", "s3:ListMultipartUploadParts"],
         "Resource": arn + "/production/*"},
        {"Sid": "BackupUnlock", "Effect": "Allow", "Principal": writer,
         "Action": "s3:DeleteObject", "Resource": arn + "/production/locks/*"},
        {"Sid": "PreserveVersions", "Effect": "Deny", "Principal": writer,
         "Action": "s3:DeleteObjectVersion", "Resource": arn + "/*"},
        {"Sid": "PreserveBackupData", "Effect": "Deny", "Principal": writer,
         "Action": "s3:DeleteObject", "Resource": [arn + "/production/" + part for part in
             ("config", "data/*", "index/*", "keys/*", "snapshots/*")]},
        {"Sid": "RequireTLS", "Effect": "Deny", "Principal": "*", "Action": "s3:*",
         "Resource": [arn, arn + "/*"], "Condition": {"Bool": {"aws:SecureTransport": "false"}}},
    ]}


def bucket_configuration_matches(bucket, expected):
    """Protobuf JSON may omit false flags and empty lists in GET responses."""
    return (not any(bucket.get("anonymousAccessFlags", {}).get(key, False)
                    for key in ("read", "list", "configRead"))
            and bucket.get("versioning") == expected["versioning"]
            and not bucket.get("acl", {}).get("grants")
            and not bucket.get("lifecycleRules")
            and bucket.get("policy") == expected["policy"]
            and str(bucket.get("maxSize", "0")) == expected["maxSize"])


class Provisioner:
    def __init__(self, api, folder, bucket, manager, config_dir, max_bytes=107374182400):
        if not re.fullmatch(r"[a-z0-9]{20}", folder) or not re.fullmatch(r"[a-z0-9]{20}", manager):
            raise ProvisionError("Cloud folder or management service-account ID is invalid.")
        bucket = bucket or "pischeprom-backups-" + folder[-8:]
        if not re.fullmatch(r"pischeprom-backups-[a-z0-9][a-z0-9-]{0,42}[a-z0-9]", bucket):
            raise ProvisionError("Backup bucket must use the dedicated pischeprom-backups- prefix.")
        self.api, self.folder, self.bucket, self.manager = api, folder, bucket, manager
        if not isinstance(max_bytes, int) or not 1073741824 <= max_bytes <= 1099511627776:
            raise ProvisionError("Backup bucket limit must be between 1 GiB and 1 TiB.")
        self.max_bytes = max_bytes
        self.directory = Path(config_dir)
        if not self.directory.is_absolute() or self.directory.is_symlink():
            raise ProvisionError("Configuration directory must be absolute and not a symlink.")
        if self.directory.exists():
            info = self.directory.stat()
            if not stat.S_ISDIR(info.st_mode) or info.st_mode & 0o077 or (os.geteuid() != 0 and info.st_uid != os.geteuid()):
                raise ProvisionError("Configuration directory must be private and owned by the current user.")
        self.cloud = private_json(self.directory / "cloud.json")
        self.runtime = private_json(self.directory / "runtime.json")
        self.password_path = self.directory / "restic-password"
        self.secret_name = bucket + "-repository-key"
        self.repository = "s3:https://storage.yandexcloud.net/" + bucket + "/production"
        if self.cloud and (self.cloud.get("folder_id") != folder or self.cloud.get("bucket") != bucket):
            raise ProvisionError("Existing backup configuration points to another repository.")

    def inventory(self):
        api = self.api
        account = named_resource(api.collection(ACCOUNTS, "serviceAccounts", folderId=self.folder),
                                 ACCOUNT_NAME, self.folder, "service account")
        bucket_list = api.collection(STORAGE, "buckets", folderId=self.folder)
        bucket_matches = [item for item in bucket_list if item.get("name") == self.bucket]
        bucket = None
        if bucket_matches:
            full = api.request("GET", STORAGE + "/" + self.bucket + "?view=VIEW_FULL")
            bucket = named_resource([full], self.bucket, self.folder, "bucket")
        secret = named_resource(api.collection(LOCKBOX, "secrets", folderId=self.folder),
                                self.secret_name, self.folder, "Lockbox secret")
        if self.cloud and not bucket:
            raise ProvisionError("A recorded repository bucket is missing; refusing to recreate it.")
        if self.cloud.get("lockbox_secret_id") and (not secret or secret.get("id") != self.cloud["lockbox_secret_id"]):
            raise ProvisionError("Recorded Lockbox escrow is missing or changed; recovery must be investigated.")
        if self.cloud.get("service_account_id") and (not account or account.get("id") != self.cloud["service_account_id"]):
            raise ProvisionError("Recorded backup service account is missing or changed.")
        if bucket and not secret:
            raise ProvisionError("An existing backup bucket has no password escrow; refusing a new password.")
        if account and account.get("id") == self.manager:
            raise ProvisionError("Backup writer must be separate from the provisioning service account.")
        if account and (account.get("suspended") is True or account.get("expiresAt")):
            raise ProvisionError("Backup service account is suspended or expires.")
        if secret and secret.get("status") not in (None, "ACTIVE"):
            raise ProvisionError("Backup password escrow is not active.")
        return account, bucket, secret

    def run(self, action):
        account, bucket, secret = self.inventory()
        report = {"action": action, "bucket": self.bucket, "bucket_exists": bucket is not None,
                  "writer_exists": account is not None, "escrow_exists": secret is not None,
                  "local_credentials_present": bool(self.runtime),
                  "local_password_present": self.password_path.is_file(),
                  "versioning_enabled": bool(bucket and bucket.get("versioning") == "VERSIONING_ENABLED"),
                  "anonymous_access_disabled": bool(bucket and not any(bucket.get("anonymousAccessFlags", {}).values())),
                  "configured_max_bytes": int(bucket.get("maxSize", 0)) if bucket else None,
                  "target_max_bytes": self.max_bytes,
                  "automatic_expiration": bool(bucket and bucket.get("lifecycleRules")),
                  "escrow_deletion_protection": bool(secret and secret.get("deletionProtection")),
                  "repository_initialized": bool(self.cloud.get("repository_initialized"))}
        if action == "plan":
            return report
        if action != "apply":
            raise ProvisionError("Unsupported provisioning action.")
        self.directory.mkdir(mode=0o700, parents=True, exist_ok=True)
        password = private_read(self.password_path)
        if password is not None:
            password = password.rstrip("\n")
            if not re.fullmatch(r"[A-Za-z0-9_-]{64}", password):
                raise ProvisionError("Stored repository password is invalid; it will not be replaced.")
        if secret:
            payload = self.api.request("GET", PAYLOAD + "/" + secret["id"] + "/payload")
            values = [entry.get("textValue", "") for entry in payload.get("entries", [])
                      if entry.get("key") == "restic-password"]
            if len(values) != 1 or not re.fullmatch(r"[A-Za-z0-9_-]{64}", values[0]):
                raise ProvisionError("Password escrow payload is invalid; it will not be replaced.")
            if password is not None and not hmac.compare_digest(password, values[0]):
                raise ProvisionError("Local repository password differs from escrow; refusing to overwrite either.")
            password = values[0]
            if not secret.get("deletionProtection"):
                self.api.mutate("PATCH", LOCKBOX + "/" + secret["id"],
                                {"updateMask": "deletionProtection", "deletionProtection": True})
        else:
            if self.cloud.get("repository_initialized") or self.runtime:
                raise ProvisionError("Existing credentials have no escrow; refusing to generate a replacement password.")
            password = password or secrets.token_urlsafe(48)
            # Persist before the API call so an interrupted create can be retried safely.
            private_write(self.password_path, password + "\n")
            secret = self.api.mutate("POST", LOCKBOX, {
                "folderId": self.folder, "name": self.secret_name, "labels": LABELS,
                "description": "Independent recovery password for the encrypted production backup repository.",
                "deletionProtection": True, "createVersion": True,
                "versionPayloadEntries": [{"key": "restic-password", "textValue": password}]})
            stored = self.api.request("GET", PAYLOAD + "/" + secret["id"] + "/payload")
            saved = [entry.get("textValue", "") for entry in stored.get("entries", [])
                     if entry.get("key") == "restic-password"]
            if len(saved) != 1 or not hmac.compare_digest(password, saved[0]):
                raise ProvisionError("New password escrow could not be verified; no bucket credentials were created.")
        private_write(self.password_path, password + "\n")
        if account is None:
            account = self.api.mutate("POST", ACCOUNTS, {"folderId": self.folder, "name": ACCOUNT_NAME,
                "description": "Dedicated backup writer; access is restricted to one backup bucket.", "labels": LABELS})
        for resource in (account, secret):
            if not re.fullmatch(r"[a-z0-9]{20}", resource.get("id", "")):
                raise ProvisionError("A created cloud resource returned an invalid identifier.")
        if bucket is None:
            bucket = self.api.mutate("POST", STORAGE, {
                "name": self.bucket, "folderId": self.folder, "defaultStorageClass": "STANDARD",
                "maxSize": str(self.max_bytes),
                "anonymousAccessFlags": {"read": False, "list": False, "configRead": False},
                "versioning": "VERSIONING_ENABLED", "acl": {"grants": []},
                "tags": [{"key": key, "value": value} for key, value in LABELS.items()]})
        policy = repository_policy(self.bucket, account["id"], self.manager)
        bindings = self.api.collection(STORAGE + "/" + self.bucket + ":listAccessBindings", "accessBindings")
        if any(binding.get("subject", {}).get("id") != self.manager for binding in bindings):
            raise ProvisionError("Dedicated backup bucket has unexpected IAM bindings; automatic changes refused.")
        expected = {"anonymousAccessFlags": {"read": False, "list": False, "configRead": False},
                    "versioning": "VERSIONING_ENABLED", "acl": {"grants": []},
                    "lifecycleRules": [], "policy": policy, "maxSize": str(self.max_bytes)}
        # Only a provisioner-owned, dedicated bucket reaches this update.
        if not bucket_configuration_matches(bucket, expected):
            self.api.mutate("PATCH", STORAGE + "/" + self.bucket,
                            {"updateMask": ",".join(expected), **expected})
        final_bucket = self.api.request("GET", STORAGE + "/" + self.bucket + "?view=VIEW_FULL")
        if not bucket_configuration_matches(final_bucket, expected):
            raise ProvisionError("Backup bucket security verification failed.")
        keys = self.api.collection(ACCESS_KEYS, "accessKeys", serviceAccountId=account["id"])
        env = self.runtime.get("env", {})
        if self.runtime:
            match = [key for key in keys if key.get("id") == self.runtime.get("access_key_id")
                     and key.get("keyId") == env.get("AWS_ACCESS_KEY_ID")]
            if (self.runtime.get("service_account_id") != account["id"] or len(match) != 1
                    or not env.get("AWS_SECRET_ACCESS_KEY") or env.get("RESTIC_REPOSITORY") != self.repository
                    or env.get("RESTIC_PASSWORD_FILE") != str(self.password_path)):
                raise ProvisionError("Existing writer credentials are inconsistent; automatic replacement is disabled.")
        else:
            if keys:
                raise ProvisionError("Writer keys exist without local credentials; investigate before creating another key.")
            created = self.api.request("POST", ACCESS_KEYS, {
                "serviceAccountId": account["id"], "description": KEY_DESCRIPTION})
            key = created.get("accessKey", {})
            if not key.get("id") or not key.get("keyId") or not created.get("secret"):
                raise ProvisionError("New writer credential response is invalid.")
            env = {"RESTIC_REPOSITORY": self.repository, "AWS_ACCESS_KEY_ID": key["keyId"],
                   "AWS_SECRET_ACCESS_KEY": created["secret"], "AWS_DEFAULT_REGION": "ru-central1",
                   "RESTIC_PASSWORD_FILE": str(self.password_path)}
            self.runtime = {"env": env, "service_account_id": account["id"], "access_key_id": key["id"]}
            private_write(self.directory / "runtime.json", json.dumps(self.runtime) + "\n")
        self.cloud.update({"folder_id": self.folder, "bucket": self.bucket, "service_account_id": account["id"],
                           "management_service_account_id": self.manager, "lockbox_secret_id": secret["id"],
                           "repository_initialized": bool(self.cloud.get("repository_initialized"))})
        private_write(self.directory / "cloud.json", json.dumps(self.cloud, indent=2) + "\n")
        return {"action": "apply", "bucket": self.bucket, "private": True, "versioning_enabled": True,
                "automatic_expiration": False, "password_escrow_verified": True,
                "writer_scoped_to_repository": True, "local_credentials_present": True}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--action", choices=("plan", "apply"), required=True)
    parser.add_argument("--iam-token-file", required=True)
    parser.add_argument("--folder-id", required=True)
    parser.add_argument("--bucket", default="")
    parser.add_argument("--management-service-account-id", required=True)
    parser.add_argument("--config-dir", default="/etc/pischeprom-backup")
    parser.add_argument("--max-bytes", type=int, default=107374182400)
    args = parser.parse_args()
    try:
        token = private_read(Path(args.iam_token_file))
        if not token or not re.fullmatch(r"[A-Za-z0-9._-]{20,8192}", token.strip()):
            raise ProvisionError("Temporary IAM token is missing or invalid.")
        api = CloudAPI(token.strip(), readonly=args.action == "plan")
        result = Provisioner(api, args.folder_id, args.bucket, args.management_service_account_id,
                             args.config_dir, args.max_bytes).run(args.action)
        print(json.dumps(result, sort_keys=True))
        return 0
    except ProvisionError as error:
        print("Backup provisioning failed: " + str(error), file=sys.stderr)
    except Exception:
        # Never expose tracebacks/HTTP payloads containing passwords or access keys.
        print("Backup provisioning failed unexpectedly; sensitive details were suppressed.", file=sys.stderr)
    return 1


if __name__ == "__main__":
    sys.exit(main())
