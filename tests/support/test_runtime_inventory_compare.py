"""Isolated draft-tool regressions. These are not application/T16 acceptance."""

import contextlib
import copy
import io
import json
import shutil
import unittest
import uuid
from pathlib import Path

from runtime_inventory_compare import (
    InvalidInventory, compare, main, read_json,
)


@contextlib.contextmanager
def workspace_fixture():
    parent = Path(__file__).resolve().parent
    # Inherit the workspace ACL; Python 3.13 tempfile mode 0700 is inaccessible
    # to the restricted Windows execution token used by this environment.
    directory = parent / (".inventory-fixture-" + uuid.uuid4().hex)
    directory.mkdir()
    directory = directory.resolve()
    try:
        yield directory
    finally:
        # Validate the final absolute target before the Windows recursive cleanup.
        if directory.resolve() != directory or directory.parent != parent:
            raise RuntimeError("Fixture cleanup containment failed.")
        shutil.rmtree(directory)


class RuntimeComparisonTest(unittest.TestCase):
    def setUp(self):
        names = [
            "app/Models/User.php", "bootstrap/app.php", "bootstrap/providers.php",
            "config/app.php", "routes/web.php", "resources/views/welcome.blade.php", "lang/es/validation.php",
            "public/index.php", "public/build/manifest.json", "public/build/assets/app.js",
            "artisan", "composer.json", "composer.lock", "package.json", "package-lock.json", "vite.config.js", "vendor/composer/installed.json",
        ] + [f"database/migrations/{index:06d}_fixture.php" for index in range(15)]
        self.expected = {
            "schema_version": 1, "kind": "accepted-runtime-source-manifest",
            "php_version": "8.3.0", "platform": "Linux",
            "reporter_sha256": "a" * 64,
            "files": {name: "b" * 64 for name in names},
        }
        self.actual = copy.deepcopy(self.expected)
        self.actual["kind"] = "runtime-visible-source-inventory"

    def test_exact_artifact_passes(self):
        self.assertTrue(compare(self.expected, self.actual)["passed"])

    def test_old_image_ten_migrations_rejected(self):
        for index in range(10, 15):
            del self.actual["files"][f"database/migrations/{index:06d}_fixture.php"]
        result = compare(self.expected, self.actual)
        self.assertFalse(result["passed"])
        self.assertEqual(len(result["missing"]), 5)
        self.assertEqual(result["changed"], [])

    def test_extra_stale_migration_rejected(self):
        self.actual["files"]["database/migrations/old.php"] = "c" * 64
        self.assertEqual(compare(self.expected, self.actual)["unexpected"],
                         ["database/migrations/old.php"])

    def test_modified_view_and_asset_reported(self):
        names = ["resources/views/welcome.blade.php", "public/build/assets/app.js"]
        for name in names:
            self.actual["files"][name] = "c" * 64
        self.assertEqual(compare(self.expected, self.actual)["changed"], sorted(names))

    def test_dependency_artifact_must_match_profile(self):
        self.actual["files"]["vendor/composer/installed.json"] = "c" * 64
        self.assertEqual(compare(self.expected, self.actual)["changed"],
                         ["vendor/composer/installed.json"])

    def test_changed_translation_and_missing_language_tree_are_rejected(self):
        self.actual['files']['lang/es/validation.php'] = 'c' * 64
        self.assertEqual(compare(self.expected, self.actual)['changed'],
                         ['lang/es/validation.php'])
        del self.actual['files']['lang/es/validation.php']
        with self.assertRaisesRegex(InvalidInventory, 'required_tree_missing'):
            compare(self.expected, self.actual)

    def test_reporter_and_runtime_metadata_are_checked(self):
        self.actual["reporter_sha256"] = "d" * 64
        self.actual["php_version"] = "8.4.0"
        self.assertEqual(compare(self.expected, self.actual)["metadata_mismatch"],
                         ["php_version", "reporter_sha256"])

    def test_missing_required_tree_is_invalid(self):
        self.actual["files"] = {name: value for name, value in self.actual["files"].items()
                                if not name.startswith("routes/")}
        with self.assertRaisesRegex(InvalidInventory, "required_tree_missing"):
            compare(self.expected, self.actual)

    def test_unsafe_paths_rejected_without_echoing_input(self):
        for name in ("/private/user/file", "C:/personal/file", "app/../.env",
                     "app//file", "app/./file", "app\\file", "app/.env.local",
                     "app/nested/.env", "app/.ENV.production", "app/control\nname"):
            with self.subTest(name=name):
                actual = copy.deepcopy(self.actual)
                actual["files"][name] = "c" * 64
                with self.assertRaisesRegex(InvalidInventory, "invalid_path"):
                    compare(self.expected, actual)

    def test_unapproved_path_and_case_collision_rejected(self):
        self.actual["files"]["storage/logs/app.log"] = "c" * 64
        with self.assertRaisesRegex(InvalidInventory, "path_outside_allowlist"):
            compare(self.expected, self.actual)
        del self.actual["files"]["storage/logs/app.log"]
        self.actual["files"]["app/Models/user.php"] = "c" * 64
        with self.assertRaisesRegex(InvalidInventory, "case_colliding_paths"):
            compare(self.expected, self.actual)

    def test_duplicate_keys_cannot_override_observation(self):
        with workspace_fixture() as directory:
            path = Path(directory) / "duplicate.json"
            path.write_text('{"files":{"app/A.php":"first","app/A.php":"second"}}',
                            encoding="utf-8")
            with self.assertRaisesRegex(InvalidInventory, "duplicate_key"):
                read_json(path)

    def test_malformed_metadata_and_hashes_rejected(self):
        for field, value in (("schema_version", True), ("php_version", None),
                             ("reporter_sha256", "not-a-hash")):
            with self.subTest(field=field):
                actual = copy.deepcopy(self.actual)
                actual[field] = value
                with self.assertRaises(InvalidInventory):
                    compare(self.expected, actual)
        self.actual["files"]["composer.lock"] = "short"
        with self.assertRaisesRegex(InvalidInventory, "invalid_file_hash"):
            compare(self.expected, self.actual)

    def test_cli_status_and_receipt_bind_input_files(self):
        with workspace_fixture() as directory:
            expected_path = Path(directory) / "expected.json"
            actual_path = Path(directory) / "actual.json"
            expected_path.write_text(json.dumps(self.expected), encoding="utf-8")
            actual_path.write_text(json.dumps(self.actual), encoding="utf-8")
            output = io.StringIO()
            with contextlib.redirect_stdout(output):
                status = main(["--expected", str(expected_path), "--actual", str(actual_path)])
            self.assertEqual(status, 0)
            receipt = json.loads(output.getvalue())
            self.assertEqual(len(receipt["actual_sha256"]), 64)
            self.assertTrue(receipt["passed"])
            self.actual["files"]["public/build/assets/app.js"] = "c" * 64
            actual_path.write_text(json.dumps(self.actual), encoding="utf-8")
            output = io.StringIO()
            with contextlib.redirect_stdout(output):
                status = main(["--expected", str(expected_path), "--actual", str(actual_path)])
            self.assertEqual(status, 1)
            self.assertEqual(json.loads(output.getvalue())["changed"],
                             ["public/build/assets/app.js"])
            actual_path.write_text("not json", encoding="utf-8")
            output = io.StringIO()
            with contextlib.redirect_stdout(output):
                status = main(["--expected", str(expected_path), "--actual", str(actual_path)])
            self.assertEqual(status, 2)
            self.assertEqual(json.loads(output.getvalue())["reason"], "unreadable_json")
            self.assertNotIn(str(directory), output.getvalue())


if __name__ == "__main__":
    unittest.main()
