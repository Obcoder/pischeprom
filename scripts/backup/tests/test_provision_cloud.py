import copy
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
import urllib.error
import urllib.parse
from unittest.mock import patch


SPEC = importlib.util.spec_from_file_location("provision_backup", Path(__file__).parents[1] / "provision-yandex-backup.py")
cloud = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(cloud)
FOLDER = "b1g" + "f" * 17
MANAGER = "aje" + "m" * 17
WRITER = "aje" + "w" * 17
ESCROW = "e6q" + "s" * 17
BUCKET = "pischeprom-backups-" + FOLDER[-8:]


class MemoryAPI(cloud.CloudAPI):
    """Synthetic cloud state; no network calls or credentials are used."""
    def __init__(self):
        self.accounts = []
        self.buckets = []
        self.secrets = []
        self.keys = []
        self.payloads = {}
        self.calls = []
        self.bindings = []

    def request(self, method, url, body=None):
        self.calls.append((method, url, copy.deepcopy(body)))
        path = url.split("?", 1)[0]
        if method == "GET":
            collections = {
                cloud.ACCOUNTS: ("serviceAccounts", self.accounts),
                cloud.STORAGE: ("buckets", self.buckets),
                cloud.LOCKBOX: ("secrets", self.secrets),
                cloud.ACCESS_KEYS: ("accessKeys", self.keys),
            }
            if path in collections:
                key, values = collections[path]
                return {key: copy.deepcopy(values)}
            if path == cloud.STORAGE + "/" + BUCKET + ":listAccessBindings":
                return {"accessBindings": copy.deepcopy(self.bindings)}
            if path == cloud.STORAGE + "/" + BUCKET:
                return copy.deepcopy(self.buckets[0])
            if path.startswith(cloud.PAYLOAD + "/"):
                return {"entries": [{"key": "restic-password", "textValue": self.payloads[ESCROW]}]}
        if method == "POST" and path == cloud.ACCESS_KEYS:
            key = {"id": "aje" + "k" * 17, "keyId": "SYNTHETIC-ACCESS-ID", "serviceAccountId": WRITER,
                   "description": cloud.KEY_DESCRIPTION}
            self.keys.append(key)
            return {"accessKey": copy.deepcopy(key), "secret": "synthetic-secret-never-log"}
        if method == "POST":
            resource = copy.deepcopy(body)
            if path == cloud.LOCKBOX:
                resource["id"] = ESCROW
                resource["status"] = "ACTIVE"
                self.payloads[ESCROW] = resource.pop("versionPayloadEntries")[0]["textValue"]
                self.secrets.append(resource)
            elif path == cloud.ACCOUNTS:
                resource["id"] = WRITER
                self.accounts.append(resource)
            elif path == cloud.STORAGE:
                self.buckets.append(resource)
            else:
                raise AssertionError("Unexpected synthetic POST endpoint")
            return {"done": True, "response": copy.deepcopy(resource)}
        if method == "PATCH":
            if path == cloud.STORAGE + "/" + BUCKET:
                resource = self.buckets[0]
            elif path == cloud.LOCKBOX + "/" + ESCROW:
                resource = self.secrets[0]
            else:
                raise AssertionError("Unexpected synthetic PATCH endpoint")
            resource.update({key: value for key, value in body.items() if key != "updateMask"})
            return {"done": True, "response": copy.deepcopy(resource)}
        raise AssertionError("Unexpected synthetic request")

    def writes(self):
        return [call for call in self.calls if call[0] != "GET"]


class ProvisionCloudTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.directory = Path(self.temporary.name) / "private"
        self.api = MemoryAPI()

    def provisioner(self):
        return cloud.Provisioner(self.api, FOLDER, "", MANAGER, self.directory)

    def apply(self):
        return self.provisioner().run("apply")

    def test_plan_has_no_mutations_no_secret_payload_and_no_local_writes(self):
        report = self.provisioner().run("plan")
        self.assertFalse(report["bucket_exists"])
        self.assertEqual(107374182400, report["target_max_bytes"])
        self.assertFalse(self.directory.exists())
        self.assertEqual([], self.api.writes())
        self.assertFalse(any(url.startswith(cloud.PAYLOAD) for _, url, _ in self.api.calls))

    def test_apply_escrows_independent_password_before_bucket_and_credentials(self):
        report = self.apply()
        self.assertTrue(report["password_escrow_verified"])
        password = (self.directory / "restic-password").read_text().strip()
        self.assertEqual(64, len(password))
        self.assertEqual(password, self.api.payloads[ESCROW])
        self.assertTrue(self.api.secrets[0]["deletionProtection"])
        writes = [url for _, url, _ in self.api.writes()]
        self.assertLess(writes.index(cloud.LOCKBOX), writes.index(cloud.STORAGE))
        self.assertLess(writes.index(cloud.STORAGE), writes.index(cloud.ACCESS_KEYS))
        self.assertNotIn(password, json.dumps(report))
        self.assertNotIn("synthetic-secret", json.dumps(report))
        runtime = json.loads((self.directory / "runtime.json").read_text())
        self.assertEqual("s3:https://storage.yandexcloud.net/" + BUCKET + "/production", runtime["env"]["RESTIC_REPOSITORY"])
        for name in ("cloud.json", "runtime.json", "restic-password"):
            self.assertEqual(0o600, (self.directory / name).stat().st_mode & 0o777)
        self.assertEqual(0o700, self.directory.stat().st_mode & 0o777)

    def test_rerun_preserves_password_credentials_and_initialized_repository(self):
        self.apply()
        metadata = cloud.private_json(self.directory / "cloud.json")
        metadata["repository_initialized"] = True
        cloud.private_write(self.directory / "cloud.json", json.dumps(metadata))
        runtime = (self.directory / "runtime.json").read_bytes()
        password = (self.directory / "restic-password").read_bytes()
        self.api.calls.clear()
        self.apply()
        self.assertEqual([], self.api.writes())
        self.assertEqual(runtime, (self.directory / "runtime.json").read_bytes())
        self.assertEqual(password, (self.directory / "restic-password").read_bytes())
        self.assertTrue(cloud.private_json(self.directory / "cloud.json")["repository_initialized"])

    def test_plan_of_existing_repository_does_not_fetch_secret_payload(self):
        self.apply()
        self.api.calls.clear()
        report = self.provisioner().run("plan")
        self.assertTrue(report["bucket_exists"])
        self.assertTrue(report["local_credentials_present"])
        self.assertFalse(self.api.writes())
        self.assertFalse(any(url.startswith(cloud.PAYLOAD) for _, url, _ in self.api.calls))

    def test_proto_json_omitted_defaults_do_not_trigger_unnecessary_updates(self):
        self.apply()
        self.api.buckets[0]["anonymousAccessFlags"] = {}
        self.api.buckets[0]["acl"] = {}
        del self.api.buckets[0]["lifecycleRules"]
        self.api.calls.clear()
        self.apply()
        self.assertEqual([], self.api.writes())

    def test_writer_cannot_delete_backups_versions_or_change_bucket_settings(self):
        self.apply()
        bucket = self.api.buckets[0]
        self.assertEqual("VERSIONING_ENABLED", bucket["versioning"])
        self.assertFalse(any(bucket["anonymousAccessFlags"].values()))
        self.assertEqual([], bucket["lifecycleRules"])
        self.assertEqual("107374182400", bucket["maxSize"])
        statements = bucket["policy"]["Statement"]
        writer_allows = [s for s in statements if s["Effect"] == "Allow" and s["Principal"] == {"CanonicalUser": WRITER}]
        actions = [a for statement in writer_allows for a in
                   ([statement["Action"]] if isinstance(statement["Action"], str) else statement["Action"])]
        self.assertNotIn("s3:*", actions)
        self.assertNotIn("s3:PutBucketPolicy", actions)
        deletion = [s for s in writer_allows if s["Action"] == "s3:DeleteObject"]
        self.assertEqual(["arn:aws:s3:::" + BUCKET + "/production/locks/*"], [s["Resource"] for s in deletion])
        self.assertTrue(any(s["Effect"] == "Deny" and s["Action"] == "s3:DeleteObjectVersion" for s in statements))

    def test_existing_unmanaged_resource_is_never_adopted_or_modified(self):
        self.api.accounts.append({"id": WRITER, "name": cloud.ACCOUNT_NAME, "folderId": FOLDER, "labels": {}})
        with self.assertRaisesRegex(cloud.ProvisionError, "not owned"):
            self.apply()
        self.assertFalse(self.api.writes())

    def test_missing_escrow_fails_before_password_generation_or_cloud_writes(self):
        self.apply()
        self.api.secrets.clear()
        self.api.calls.clear()
        with self.assertRaisesRegex(cloud.ProvisionError, "escrow is missing"):
            self.apply()
        self.assertFalse(self.api.writes())

    def test_missing_initialized_bucket_is_not_recreated(self):
        self.apply()
        metadata = cloud.private_json(self.directory / "cloud.json")
        metadata["repository_initialized"] = True
        cloud.private_write(self.directory / "cloud.json", json.dumps(metadata))
        self.api.buckets.clear()
        self.api.calls.clear()
        with self.assertRaisesRegex(cloud.ProvisionError, "refusing to recreate"):
            self.apply()
        self.assertFalse(self.api.writes())

    def test_conflicting_escrow_password_never_overwrites_local_password(self):
        self.apply()
        password = (self.directory / "restic-password").read_bytes()
        self.api.payloads[ESCROW] = "A" * 64
        self.api.calls.clear()
        with self.assertRaisesRegex(cloud.ProvisionError, "differs from escrow"):
            self.apply()
        self.assertEqual(password, (self.directory / "restic-password").read_bytes())
        self.assertFalse(self.api.writes())

    def test_missing_local_password_is_recovered_from_original_escrow(self):
        self.apply()
        password = (self.directory / "restic-password").read_bytes()
        (self.directory / "restic-password").unlink()
        self.api.calls.clear()
        self.apply()
        self.assertEqual(password, (self.directory / "restic-password").read_bytes())
        self.assertFalse(self.api.writes())

    def test_missing_local_credentials_do_not_create_additional_static_keys(self):
        self.apply()
        (self.directory / "runtime.json").unlink()
        self.api.calls.clear()
        with self.assertRaisesRegex(cloud.ProvisionError, "keys exist without local credentials"):
            self.apply()
        self.assertFalse(self.api.writes())
        self.assertEqual(1, len(self.api.keys))

    def test_unexpected_bucket_access_is_not_silently_adopted(self):
        self.apply()
        self.api.bindings = [{"roleId": "storage.viewer", "subject": {"type": "system", "id": "allUsers"}}]
        self.api.calls.clear()
        with self.assertRaisesRegex(cloud.ProvisionError, "unexpected IAM bindings"):
            self.apply()
        self.assertFalse(self.api.writes())

    def test_managed_bucket_privacy_drift_is_repaired_without_changing_credentials(self):
        self.apply()
        self.api.buckets[0]["anonymousAccessFlags"]["read"] = True
        self.api.buckets[0]["lifecycleRules"] = [{"enabled": True, "expiration": {"days": "30"}}]
        self.api.calls.clear()
        self.apply()
        self.assertEqual(1, len(self.api.writes()))
        self.assertFalse(self.api.buckets[0]["anonymousAccessFlags"]["read"])
        self.assertEqual([], self.api.buckets[0]["lifecycleRules"])
        self.assertEqual(1, len(self.api.keys))

    def test_configuration_rejects_unsafe_paths_and_changed_destination(self):
        self.apply()
        with self.assertRaisesRegex(cloud.ProvisionError, "another repository"):
            cloud.Provisioner(self.api, FOLDER, "pischeprom-backups-another", MANAGER, self.directory)
        with self.assertRaisesRegex(cloud.ProvisionError, "dedicated"):
            cloud.Provisioner(self.api, FOLDER, "existing-public-gis", MANAGER, self.directory)
        link = Path(self.temporary.name) / "link"
        link.symlink_to(self.directory)
        with self.assertRaisesRegex(cloud.ProvisionError, "symlink"):
            cloud.Provisioner(self.api, FOLDER, "", MANAGER, link)

    def test_private_reader_rejects_group_readable_and_symlink_credentials(self):
        self.directory.mkdir(mode=0o700)
        secret = self.directory / "secret"
        secret.write_text("synthetic")
        secret.chmod(0o640)
        with self.assertRaisesRegex(cloud.ProvisionError, "permissions"):
            cloud.private_read(secret)
        secret.chmod(0o600)
        link = self.directory / "link"
        link.symlink_to(secret)
        with self.assertRaises(cloud.ProvisionError):
            cloud.private_read(link)

    def test_readonly_http_client_refuses_mutation_before_network(self):
        api = cloud.CloudAPI("synthetic", readonly=True)
        with patch.object(api.opener, "open") as request:
            with self.assertRaisesRegex(cloud.ProvisionError, "Plan cannot change"):
                api.request("POST", cloud.STORAGE, {"name": BUCKET})
            request.assert_not_called()

    def test_http_errors_and_untrusted_endpoints_do_not_leak_credentials(self):
        api = cloud.CloudAPI("synthetic-token")
        error = urllib.error.HTTPError(cloud.LOCKBOX, 403, "secret-password-value", {}, None)
        with patch.object(api.opener, "open", side_effect=error):
            with self.assertRaises(cloud.ProvisionError) as raised:
                api.request("POST", cloud.LOCKBOX, {"textValue": "secret-password-value"})
        self.assertNotIn("secret-password-value", str(raised.exception))
        self.assertIn("HTTP 403", str(raised.exception))
        with self.assertRaisesRegex(cloud.ProvisionError, "Untrusted"):
            api.request("GET", "https://example.org/token")

    def test_collection_follows_all_pages_and_rejects_repeated_tokens(self):
        api = cloud.CloudAPI("synthetic")
        with patch.object(api, "request", side_effect=[
            {"items": [{"id": "one"}], "nextPageToken": "next"}, {"items": [{"id": "two"}]},
        ]) as request:
            self.assertEqual([{"id": "one"}, {"id": "two"}], api.collection(cloud.LOCKBOX, "items", folderId=FOLDER))
            self.assertIn("pageToken=next", request.call_args_list[1].args[1])
        with patch.object(api, "request", return_value={"items": [], "nextPageToken": "same"}):
            with self.assertRaisesRegex(cloud.ProvisionError, "pagination"):
                api.collection(cloud.LOCKBOX, "items")


if __name__ == "__main__":
    unittest.main()
