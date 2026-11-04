"""Public source and compiled-asset verification with synthetic exec responses."""
import base64
import json
from pathlib import Path
import sys
from types import SimpleNamespace
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts/browser_lab'))
import qualification


class QualificationTests(unittest.TestCase):
    def setUp(self):
        self.manifest = {name: 'a' * 64 for name in ('app/Models/User.php', 'composer.json', 'composer.lock', 'artisan')}
        self.assets = {'resources/js/app.js': {'file': 'assets/app-a.js', 'css': ['assets/app-a.css']}}
        self.php_calls, self.web_calls, self.receipts = [], [], {}
        self.wrong_application, self.wrong_assets = False, False
        self.profile = SimpleNamespace(exec_php=self.php, exec_service=self.web,
            io=SimpleNamespace(save=lambda name, value: self.receipts.update({name: value})))

    def php(self, label, arguments, **options):
        self.php_calls.append(label)
        if label == 'read-public-asset-manifest':
            return SimpleNamespace(stdout=json.dumps(self.assets).encode())
        names = json.loads(base64.b64decode(arguments[-1]))
        return SimpleNamespace(stdout=json.dumps({name: ('b' * 64 if self.wrong_application and name.startswith('app/') else 'a' * 64)
                                                  for name in names}).encode())

    def web(self, service, label, arguments):
        self.assertEqual(service, 'web')
        self.web_calls.append(arguments)
        names = arguments[arguments.index('--') + 1:]
        self.assertTrue(all(name.startswith('/var/www/emailing/public/build/') for name in names))
        digest = 'b' * 64 if self.wrong_assets else 'a' * 64
        return SimpleNamespace(stdout=''.join(digest + '  ' + name + '\n' for name in names).encode())

    def test_public_application_and_same_app_web_assets_are_verified(self):
        result = qualification.verify_images(self.profile, self.manifest)
        self.assertEqual(result['public_application_files'], 4)
        self.assertEqual(result['matching_app_web_asset_files'], 3)
        self.assertFalse(result['private_configuration_read'])
        self.assertIn('image-provenance.public.json', self.receipts)

    def test_application_difference_refuses_before_reading_asset_manifest(self):
        self.wrong_application = True
        with self.assertRaisesRegex(ValueError, 'application_differs'):
            qualification.verify_images(self.profile, self.manifest)
        self.assertNotIn('read-public-asset-manifest', self.php_calls)
        self.assertEqual(self.web_calls, [])

    def test_private_or_traversing_source_refuses_before_any_exec(self):
        for name in ('config/.env', 'config/local.private.env', '../app/User.php', 'app//User.php'):
            with self.subTest(name=name), self.assertRaises(ValueError):
                qualification.verify_images(self.profile, {**self.manifest, name: 'a' * 64})
        self.assertEqual(self.php_calls, [])

    def test_mismatched_web_assets_never_generate_qualified_receipt(self):
        self.wrong_assets = True
        with self.assertRaisesRegex(ValueError, 'web_assets_differ'):
            qualification.verify_images(self.profile, self.manifest)
        self.assertNotIn('image-provenance.public.json', self.receipts)

    def test_private_or_escaping_manifest_asset_is_rejected_before_web_hash(self):
        for filename in ('assets/../.env', '../other.css', 'assets/private.env', 'assets/a.css\n'):
            with self.subTest(filename=filename):
                self.assets = {'entry': {'file': filename}}
                with self.assertRaises(ValueError):
                    qualification.verify_images(self.profile, self.manifest)
        self.assertEqual(self.web_calls, [])


if __name__ == '__main__':
    unittest.main()
