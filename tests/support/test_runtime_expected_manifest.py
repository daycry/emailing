"""Expected manifests use independent artifact bytes, never observed hashes."""
import json
from pathlib import Path
import stat
import unittest
import zipfile
from runtime_expected_manifest import collect, digest, produce
from runtime_inventory_compare import compare
from test_runtime_inventory_compare import workspace_fixture


class ExpectedArtifactTest(unittest.TestCase):
    def test_independent_artifacts_detect_a_runtime_dependency_change(self):
        with workspace_fixture() as directory:
            source = directory / 'source.zip'
            build = directory / 'build.zip'
            dependencies = directory / 'dependencies.zip'
            reporter = directory / 'reporter.php'
            reporter.write_bytes(b'<?php // independent fixture reporter')
            with zipfile.ZipFile(source, 'w') as archive:
                for name in ['app/Fixture.php', 'bootstrap/app.php', 'bootstrap/providers.php', 'config/app.php', 'database/migrations/001.php', 'routes/web.php', 'resources/views/fixture.php', 'lang/es/validation.php', 'public/index.php', 'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js']:
                    archive.writestr(name, b'source fixture')
                archive.writestr('.env.private', b'never included')
                archive.writestr('bootstrap/cache/services.php', b'stale source cache excluded')
            with zipfile.ZipFile(build, 'w') as archive:
                archive.writestr('public/build/manifest.json', b'{}')
                archive.writestr('public/build/assets/fixture.js', b'build fixture')
            with zipfile.ZipFile(dependencies, 'w') as archive:
                archive.writestr('vendor/composer/installed.json', b'[]')
                archive.writestr('vendor/synthetic/runtime.php', b'accepted dependency')
                archive.writestr('bootstrap/cache/services.php', b'accepted profile cache')
            expected = produce(source, build, dependencies, '8.4.0', 'Linux', reporter)
            self.assertNotIn('.env.private', expected['files'])
            self.assertEqual(expected['files']['lang/es/validation.php'], digest(b'source fixture'))
            self.assertEqual(expected['files']['bootstrap/cache/services.php'], digest(b'accepted profile cache'))
            actual = json.loads(json.dumps(expected))
            actual['kind'] = 'runtime-visible-source-inventory'
            self.assertTrue(compare(expected, actual)['passed'])
            actual['files']['vendor/synthetic/runtime.php'] = digest(b'runtime drift')
            self.assertEqual(compare(expected, actual)['changed'], ['vendor/synthetic/runtime.php'])

    def test_selected_symlink_and_traversal_members_are_rejected(self):
        with workspace_fixture() as directory:
            archive_path = directory / 'unsafe.zip'
            for name, mode in [('vendor/../private', 0), ('vendor/linked.php', stat.S_IFLNK | 0o777)]:
                with self.subTest(name=name):
                    with zipfile.ZipFile(archive_path, 'w') as archive:
                        entry = zipfile.ZipInfo(name)
                        entry.external_attr = mode << 16
                        archive.writestr(entry, b'unsafe fixture')
                    with self.assertRaisesRegex(ValueError, 'unsafe_artifact_member'):
                        collect(archive_path, lambda value: value.startswith('vendor/'))

    def test_missing_scope_cannot_generate_empty_expected_dependencies(self):
        with workspace_fixture() as directory:
            path = directory / 'empty.zip'
            with zipfile.ZipFile(path, 'w') as archive:
                archive.writestr('unrelated.txt', 'unrelated fixture')
            with self.assertRaisesRegex(ValueError, 'empty_artifact_scope'):
                collect(path, lambda value: value.startswith('vendor/'))
