"""Pure filesystem/unit checks; no deployment, HTTP, DB, broker or client qualification."""
from hashlib import sha256
import importlib
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
from uuid import UUID, uuid4

UNIT_ROOT = Path(__file__).resolve().parents[3] / 'storage/app/private/mcp-profile-unit-runs'
UNIT_ROOT.mkdir(exist_ok=True)


class ProfileReceiptTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_receipts')
        self.temp = tempfile.TemporaryDirectory(prefix='receipt-unit-', dir=UNIT_ROOT)
        self.project = Path(self.temp.name) / 'project'
        self.project.mkdir()

    def tearDown(self):
        if hasattr(self, 'temp'):
            assert Path(self.temp.name).resolve().is_relative_to(UNIT_ROOT.resolve())
            self.temp.cleanup()

    def receipt(self, **changes):
        values = {'operation': 'allowed_call', 'method': 'POST', 'status': 200,
                  'elapsed_ns': 123, 'response_bytes': 456, 'error': None}
        values.update(changes)
        return self.core.HttpReceipt(**values)

    def store(self, profile='laragon', run_id=None):
        return self.core.ReceiptStore(self.project, profile, run_id or str(uuid4()))

    def test_public_schema_has_no_body_header_url_or_credential_channel(self):
        ref = self.store().write(self.receipt())
        data = json.loads((self.project / ref.relative_path).read_text(encoding='utf-8'))
        self.assertEqual(set(data), {
            'schema_version', 'kind', 'profile', 'run_id', 'observed_at_utc',
            'operation', 'method', 'status', 'elapsed_ns', 'response_bytes', 'error',
            'credentials_recorded', 'request_body_recorded', 'response_body_recorded',
            'headers_recorded', 'qualifies_profile',
        })
        for field in ['credentials_recorded', 'request_body_recorded', 'response_body_recorded',
                      'headers_recorded', 'qualifies_profile']:
            self.assertIs(data[field], False)
        self.assertEqual(data['kind'], 'mcp-profile-http-observation')

    def test_a_successful_http_status_never_qualifies_a_client_or_profile(self):
        for profile in ['laragon', 'docker']:
            ref = self.store(profile).write(self.receipt())
            data = json.loads((self.project / ref.relative_path).read_text(encoding='utf-8'))
            self.assertEqual(data['status'], 200)
            self.assertIs(data['qualifies_profile'], False)
            self.assertNotIn('passed', data)
            self.assertNotIn('client_authenticated', data)

    def test_untrusted_operation_and_error_text_are_rejected_without_echo(self):
        secret = 'not-a-real-secret-fixture'
        for changes in [{'operation': secret}, {'error': secret}, {'method': secret}]:
            with self.subTest(changes=tuple(changes)):
                with self.assertRaises(self.core.EvidenceError) as caught:
                    self.receipt(**changes)
                self.assertNotIn(secret, str(caught.exception))
                self.assertNotIn(secret, repr(caught.exception))

    def test_numeric_fields_reject_booleans_and_out_of_range_values(self):
        for changes in [{'status': True}, {'status': 99}, {'status': 600},
                        {'elapsed_ns': True}, {'elapsed_ns': -1},
                        {'response_bytes': False}, {'response_bytes': -1}]:
            with self.subTest(changes=changes):
                with self.assertRaises(self.core.EvidenceError):
                    self.receipt(**changes)

    def test_missing_status_requires_a_bounded_error_classification(self):
        with self.assertRaises(self.core.EvidenceError):
            self.receipt(status=None)
        ref = self.store().write(self.receipt(status=None, error='tls_error'))
        data = json.loads((self.project / ref.relative_path).read_text(encoding='utf-8'))
        self.assertIsNone(data['status'])
        self.assertEqual(data['error'], 'tls_error')
        self.assertIs(data['qualifies_profile'], False)

    def test_receipt_reference_is_private_relative_and_matches_exact_bytes(self):
        ref = self.store().write(self.receipt())
        target = self.project / ref.relative_path
        self.assertTrue(target.resolve().is_relative_to(self.project / 'storage/app/private'))
        self.assertFalse(Path(ref.relative_path).is_absolute())
        self.assertEqual(ref.sha256, sha256(target.read_bytes()).hexdigest())

    def test_existing_run_directory_cannot_be_reused_or_overwritten(self):
        run_id = str(uuid4())
        store = self.store(run_id=run_id)
        ref = store.write(self.receipt())
        before = (self.project / ref.relative_path).read_bytes()
        with self.assertRaises(self.core.EvidenceError):
            self.store(run_id=run_id)
        self.assertEqual(before, (self.project / ref.relative_path).read_bytes())

    def test_receipt_name_collision_preserves_the_previous_receipt(self):
        store = self.store()
        stable = UUID('00000000-0000-4000-8000-000000000001')
        with patch.object(self.core, 'uuid4', return_value=stable):
            ref = store.write(self.receipt())
            before = (self.project / ref.relative_path).read_bytes()
            with self.assertRaises(self.core.EvidenceError):
                store.write(self.receipt(status=401))
        self.assertEqual(before, (self.project / ref.relative_path).read_bytes())

    def test_non_directory_private_prefix_is_rejected_without_echoing_the_path(self):
        prefix = self.project / 'storage/app'
        prefix.mkdir(parents=True)
        (prefix / 'private').write_text('private fixture marker', encoding='utf-8')
        with self.assertRaises(self.core.EvidenceError) as caught:
            self.store()
        self.assertNotIn(str(self.project), str(caught.exception))
        self.assertEqual((prefix / 'private').read_text(encoding='utf-8'), 'private fixture marker')

    def test_write_failure_reports_a_code_without_private_filename_or_os_message(self):
        store = self.store()
        with patch.object(self.core.os, 'open', side_effect=OSError('private-path-fixture')):
            with self.assertRaises(self.core.EvidenceError) as caught:
                store.write(self.receipt())
        self.assertNotIn('private-path-fixture', str(caught.exception))
        self.assertNotIn(str(self.project), str(caught.exception))

    def test_invalid_profile_or_run_identifier_cannot_select_an_output_path(self):
        for profile, run_id in [('unknown', str(uuid4())), ('docker', '../escape'),
                               ('laragon', 'not-a-real-secret-fixture')]:
            with self.subTest(profile=profile):
                with self.assertRaises(self.core.EvidenceError):
                    self.core.ReceiptStore(self.project, profile, run_id)
        self.assertFalse((self.project / 'storage').exists())


if __name__ == '__main__':
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(ProfileReceiptTests)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    print(json.dumps({'classification': 'pure unit/filesystem tests, not runtime qualification',
                      'tests': result.testsRun, 'failures': len(result.failures),
                      'errors': len(result.errors), 'skipped': len(result.skipped),
                      'profile_qualified': False}), flush=True)
    raise SystemExit(0 if result.wasSuccessful() else 1)
