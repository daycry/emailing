"""Private CLI boundary: dry-run, owned inputs and bounded diagnostics."""
import contextlib
import importlib
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
from uuid import uuid4

from profile_receipts import EvidenceError

UNIT_ROOT = Path(__file__).resolve().parents[3] / 'storage/app/private/mcp-profile-unit-runs'


class ProfileCliTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_cli')
        UNIT_ROOT.mkdir(exist_ok=True)
        self.temp = tempfile.TemporaryDirectory(prefix='cli-', dir=UNIT_ROOT)
        self.project = Path(self.temp.name) / 'project'
        self.project.mkdir()
        self.prefix = self.project / 'storage/app/private/mcp-profile-input'
        self.prefix.mkdir(parents=True)
        self.name = str(uuid4()) + '.json'
        self.path = self.prefix / self.name
        self.raw = {'schema_version': 1, 'token': 'fixture-private-token', 'account_id': 71,
                    'user_id': 72, 'grant_public_id': str(uuid4()), 'contact_id': 73,
                    'denied_template_id': 74}
        self.path.write_text(json.dumps(self.raw), encoding='utf-8')

    def tearDown(self):
        if hasattr(self, 'temp'):
            assert Path(self.temp.name).resolve().is_relative_to(UNIT_ROOT.resolve())
            self.temp.cleanup()

    def invoke(self, argv):
        stream = io.StringIO()
        with contextlib.redirect_stdout(stream), contextlib.redirect_stderr(stream):
            code = self.core.main(argv, project_root=self.project)
        return code, stream.getvalue()

    def test_default_is_dry_run_without_loading_credentials_or_starting_transport(self):
        with (patch.object(self.core, 'read_inputs', side_effect=AssertionError('must not read')),
              patch.object(self.core, 'BoundHttpClient', side_effect=AssertionError('must not connect'))):
            code, output = self.invoke(['--profile', 'laragon'])
        self.assertEqual(code, 0)
        self.assertEqual(json.loads(output)['mode'], 'dry-run')
        self.assertIs(json.loads(output)['profile_qualified'], False)

    def test_execute_without_private_input_is_a_safe_nonzero_result(self):
        code, output = self.invoke(['--profile', 'docker', '--execute'])
        self.assertEqual(code, 2)
        self.assertNotIn('fixture-private', output)
        self.assertIs(json.loads(output)['profile_qualified'], False)

    def test_private_input_must_be_exact_uuid_json_below_the_owned_prefix(self):
        inputs = self.core.read_inputs(self.project, 'storage/app/private/mcp-profile-input/' + self.name)
        self.assertEqual(inputs.contact_id, 73)
        for name in ['.env', '../fixture-private.json', str(self.path),
                     'storage/app/private/mcp-profile-input/request.json',
                     'storage/app/private/mcp-profile-input/../' + self.name]:
            with self.assertRaises(EvidenceError):
                self.core.read_inputs(self.project, name)

    def test_oversized_and_missing_private_files_do_not_echo_paths_or_data(self):
        self.path.write_bytes(b'fixture-private' * 2000)
        for name in [self.name, str(uuid4()) + '.json']:
            with self.assertRaises(EvidenceError) as caught:
                self.core.read_inputs(self.project, 'storage/app/private/mcp-profile-input/' + name)
            self.assertNotIn('fixture-private', str(caught.exception))

    def test_bad_cli_values_do_not_echo_untrusted_arguments(self):
        for args in [['--profile', 'fixture-private'], ['--token', 'fixture-private'],
                     ['--profile', 'docker', '--timeout', 'fixture-private']]:
            code, output = self.invoke(args)
            self.assertEqual(code, 2)
            self.assertNotIn('fixture-private', output)

    def test_transport_construction_errors_emit_only_fixed_diagnostics(self):
        path = 'storage/app/private/mcp-profile-input/' + self.name
        with patch.object(self.core, 'BoundHttpClient', side_effect=OSError('fixture-private-path')):
            code, output = self.invoke(['--profile', 'docker', '--execute', '--inputs', path])
        self.assertEqual(code, 2)
        self.assertNotIn('fixture-private', output)
        self.assertEqual(json.loads(output)['error'], 'profile_probe_failed')


if __name__ == '__main__':
    unittest.main()
