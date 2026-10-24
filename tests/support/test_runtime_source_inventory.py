"""Exercise the CLI reporter using owned synthetic files, without Laravel or SQL."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import unittest
import uuid

BASE = Path(__file__).resolve().parent
REPORTER = BASE / 'runtime-source-inventory.php'


class ReporterTest(unittest.TestCase):
    def setUp(self):
        self.root = BASE / ('reporter-fixture-' + uuid.uuid4().hex)
        self.root.mkdir()
        self.addCleanup(self.remove_owned_fixture)
        self.inputs = {
            'composer.json': b'{"name":"example/synthetic-qa"}\n',
            'artisan': b'<?php // synthetic CLI entrypoint\n',
            'package.json': b'{"name":"synthetic-qa"}\n',
            'vite.config.js': b'// synthetic build configuration\n',
            'app/Synthetic.php': b'<?php // synthetic app, never executed\n',
            'bootstrap/app.php': b'<?php // synthetic bootstrap, never executed\n',
            'bootstrap/providers.php': b'<?php return [];\n',
            'config/synthetic.php': b'<?php return [];\n',
            'database/migrations/000001_synthetic.php': b'<?php // fixture migration\n',
            'routes/synthetic.php': b'<?php // fixture route\n',
            'lang/es/validation.php': b'<?php return [];\n',
            'resources/views/synthetic.blade.php': b'<p>Synthetic QA</p>\n',
            'public/index.php': b'<?php // fixture entrypoint, never executed\n',
            'public/build/manifest.json': b'{}\n',
            'public/build/synthetic.js': b'/* synthetic build */\n',
            'composer.lock': b'{"packages":[]}\n',
            'package-lock.json': b'{"packages":{}}\n',
            'vendor/composer/installed.json': b'[]\n',
        }
        for name, data in self.inputs.items():
            target = self.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
        target = self.root / 'tests/support/runtime-source-inventory.php'
        target.parent.mkdir(parents=True)
        target.write_bytes(REPORTER.read_bytes())
        self.command = ['php', '-n', str(target)]

    def remove_owned_fixture(self):
        assert self.root.parent.resolve() == BASE.resolve()
        assert self.root.name.startswith('reporter-fixture-')
        assert not self.root.is_symlink()
        shutil.rmtree(self.root)

    def run_reporter(self):
        return subprocess.run(self.command, capture_output=True, check=False)

    def assert_sanitized_failure(self, result):
        self.assertEqual(result.returncode, 1)
        self.assertEqual(result.stdout, b'')
        value = json.loads(result.stderr)
        self.assertEqual(set(value), {'error', 'exception_class'})
        self.assertEqual(value['error'], 'qa_source_inventory_failed')
        self.assertNotIn(str(self.root).encode(), result.stderr)

    def test_complete_allowlisted_inventory_matches_independent_fixture_bytes(self):
        # Environment and unrelated files exist but must never enter the inventory.
        (self.root / '.env').write_bytes(b'SYNTHETIC_PRIVATE=do-not-publish\n')
        (self.root / 'unrelated.txt').write_bytes(b'unrelated fixture\n')
        result = self.run_reporter()
        self.assertEqual(result.returncode, 0)
        self.assertEqual(result.stderr, b'')
        value = json.loads(result.stdout)
        self.assertEqual(value['kind'], 'runtime-visible-source-inventory')
        self.assertEqual(value['schema_version'], 1)
        expected = {name: hashlib.sha256(data).hexdigest()
                    for name, data in self.inputs.items()}
        self.assertEqual(value['files'], dict(sorted(expected.items())))
        self.assertEqual(value['reporter_sha256'], hashlib.sha256(REPORTER.read_bytes()).hexdigest())
        self.assertTrue(value['php_version'])
        self.assertTrue(value['platform'])
        self.assertNotIn(b'do-not-publish', result.stdout)

    def test_missing_project_marker_fails_without_path_or_warning(self):
        (self.root / 'composer.json').unlink()
        self.assert_sanitized_failure(self.run_reporter())

    def test_missing_migration_tree_is_not_reported_as_a_valid_empty_inventory(self):
        (self.root / 'database/migrations/000001_synthetic.php').unlink()
        (self.root / 'database/migrations').rmdir()
        self.assert_sanitized_failure(self.run_reporter())

    def test_missing_dependency_artifact_fails_without_paths(self):
        (self.root / 'vendor/composer/installed.json').unlink()
        self.assert_sanitized_failure(self.run_reporter())

    def test_missing_translation_tree_cannot_pass_as_complete(self):
        (self.root / 'lang/es/validation.php').unlink()
        self.assert_sanitized_failure(self.run_reporter())

    def test_changed_translation_is_observed_from_its_actual_bytes(self):
        before = json.loads(self.run_reporter().stdout)['files']
        changed = b'<?php return ["accepted" => "Confirmar"];\n'
        (self.root / 'lang/es/validation.php').write_bytes(changed)
        after = json.loads(self.run_reporter().stdout)['files']
        self.assertEqual(after['lang/es/validation.php'], hashlib.sha256(changed).hexdigest())
        self.assertEqual([name for name in before if before[name] != after[name]],
                         ['lang/es/validation.php'])

    def test_environment_filename_inside_allowlist_is_rejected_case_insensitively(self):
        (self.root / 'app/.EnV.private').write_bytes(b'SYNTHETIC_PRIVATE=do-not-publish\n')
        result = self.run_reporter()
        self.assert_sanitized_failure(result)
        self.assertNotIn(b'do-not-publish', result.stderr)

    def test_actual_changed_view_changes_only_its_digest(self):
        first = self.run_reporter()
        self.assertEqual(first.returncode, 0)
        before = json.loads(first.stdout)['files']
        (self.root / 'resources/views/synthetic.blade.php').write_bytes(b'<p>Changed fixture</p>\n')
        second = self.run_reporter()
        self.assertEqual(second.returncode, 0)
        after = json.loads(second.stdout)['files']
        self.assertEqual(set(before), set(after))
        self.assertEqual([name for name in before if before[name] != after[name]],
                         ['resources/views/synthetic.blade.php'])


if __name__ == '__main__':
    unittest.main(verbosity=2)
