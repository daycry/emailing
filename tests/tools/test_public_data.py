"""Regression cases for public repository checks; all inputs are synthetic."""

import importlib.util
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest


SCRIPT = Path(__file__).resolve().parents[2] / "scripts/check-public-data.py"
SPEC = importlib.util.spec_from_file_location("public_data", SCRIPT)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class PublicDataTest(unittest.TestCase):
    def test_machine_paths_are_detected_without_echoing_the_value(self):
        private = b"C:" + b"/Users/" + b"synthetic-profile/report.json"
        self.assertEqual(MODULE.inspect("report.json", private, set()), ["private machine path"])
        self.assertEqual(MODULE.inspect("readme.md", b"$env:USERPROFILE and <USER_HOME>/cache", set()), [])

    def test_private_environment_files_and_secrets_are_blocked(self):
        self.assertIn("private environment file", MODULE.inspect("nested/.env.production", b"", set()))
        self.assertEqual(MODULE.inspect(".env.example", b"APP_KEY=", set()), [])
        secret = b"synthetic" + b"-not-a-live-key-123456789"
        self.assertEqual(MODULE.inspect("config.php", secret, {secret}), ["local secret value"])

    def test_identity_numbers_in_file_names_are_also_detected(self):
        identity = "0" * 8 + "T"
        self.assertEqual(MODULE.inspect("reports/" + identity + ".txt", b"", set()), ["possible personal identity number"])

    def test_staged_check_reads_index_instead_of_sanitized_worktree(self):
        runtime = SCRIPT.parent.parent / "tests/.runtime"
        runtime.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(dir=runtime) as directory:
            root = Path(directory)
            self.assertTrue(root.resolve().is_relative_to(runtime.resolve()))
            subprocess.run(["git", "init", "--quiet", directory], check=True)
            file = root / "report.txt"
            private = "C:" + "/Users/" + "synthetic-profile/file.txt"
            file.write_text(private, encoding="utf-8")
            subprocess.run(["git", "-C", directory, "add", "report.txt"], check=True)
            file.write_text("<USER_HOME>/file.txt", encoding="utf-8")
            result = subprocess.run([sys.executable, str(SCRIPT), "--staged"], cwd=root, capture_output=True, text=True)
            self.assertEqual(result.returncode, 1)
            self.assertIn("private machine path", result.stdout)
            self.assertNotIn("synthetic-profile", result.stdout)
            result = subprocess.run([sys.executable, str(SCRIPT)], cwd=root, capture_output=True, text=True)
            self.assertEqual(result.returncode, 0)


if __name__ == "__main__":
    unittest.main()
