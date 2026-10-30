"""Layout integration only: no application, private inputs, sockets or database."""
import contextlib
from hashlib import sha256
import importlib
import io
import json
from pathlib import Path
import shutil
import subprocess
import sys
from types import SimpleNamespace
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[3]
COMPONENTS = ROOT / 'scripts/mcp_profile'
sys.path.insert(0, str(COMPONENTS))


class Output:
    def __init__(self):
        self.buffer = io.BytesIO()

    def flush(self):
        pass


class InstalledLayoutTests(unittest.TestCase):
    def test_bounded_worker_uses_the_portable_project_root(self):
        core = importlib.import_module('profile_bounded')
        output = Output()
        with (patch.object(core, 'run_bounded', return_value=SimpleNamespace(exit_code=0, stdout=b'{}')) as run,
              patch.object(sys, 'stdout', output)):
            status = core.main(['--profile', 'docker', '--execute'])
        self.assertEqual(0, status)
        self.assertEqual(ROOT.resolve(), run.call_args.kwargs['cwd'])
        self.assertEqual(180, run.call_args.kwargs['timeout'])
        self.assertEqual(b'{}', output.buffer.getvalue())

    def test_probe_loads_owned_inputs_relative_to_the_installed_project(self):
        core = importlib.import_module('profile_cli')
        roots = []

        def observe(root, relative):
            roots.append(root)
            raise core.EvidenceError('stop_before_private_input_read')

        with (patch.object(core, 'read_inputs', side_effect=observe),
              patch.object(core, 'BoundHttpClient', side_effect=AssertionError('network must not start')),
              contextlib.redirect_stdout(io.StringIO())):
            status = core.main(['--profile', 'laragon', '--execute', '--inputs',
                                'storage/app/private/mcp-profile-input/00000000-0000-4000-8000-000000000001.json'])
        self.assertEqual(2, status)
        self.assertEqual([ROOT.resolve()], roots)

    def test_schema_validator_accepts_the_installed_project_root(self):
        core = importlib.import_module('profile_schema')
        validator = core.PhpSchemaValidator(ROOT)
        self.assertIsInstance(validator, core.PhpSchemaValidator)

    def test_php_bridge_uses_the_installed_vendor_and_real_contract_without_app_bootstrap(self):
        php = shutil.which('php')
        self.assertIsNotNone(php)
        result = subprocess.run([php, '-d', 'zend.exception_ignore_args=1',
                                 str(COMPONENTS / 'profile_schema_bridge.php')],
                                cwd=ROOT, input=b'{"operation":"connection_info","value":{}}',
                                capture_output=True, timeout=30)
        self.assertEqual(b'', result.stderr)
        self.assertEqual(0, result.returncode)
        self.assertEqual({'available': True, 'valid': False}, json.loads(result.stdout))

    def test_public_entrypoint_is_dry_run_from_an_unrelated_working_directory(self):
        for profile in ['laragon', 'docker']:
            with self.subTest(profile=profile):
                result = subprocess.run([sys.executable, '-E', '-s', '-B',
                                         str(ROOT / 'scripts/mcp-profile-evidence.py'),
                                         '--profile', profile], cwd=ROOT.parent,
                                        capture_output=True, timeout=10)
                self.assertEqual(0, result.returncode)
                self.assertEqual(b'', result.stderr)
                reply = json.loads(result.stdout)
                self.assertEqual('dry-run', reply['mode'])
                self.assertEqual(profile, reply['profile'])
                for flag in ['credentials_loaded', 'network_executed', 'profile_qualified', 'native_client_qualified']:
                    self.assertIs(False, reply[flag])

    def test_profile_source_manifest_includes_the_public_entrypoint(self):
        core = importlib.import_module('profile_cli')
        inputs = core.SmokeInputs.from_bytes(json.dumps({
            'schema_version': 1, 'token': 'fixture-private-token', 'account_id': 71,
            'user_id': 72, 'grant_public_id': '00000000-0000-4000-8000-000000000001',
            'contact_id': 73, 'denied_template_id': 74,
        }).encode())
        output = io.StringIO()
        with (patch.object(core, 'read_inputs', return_value=inputs),
              patch.object(core, 'BoundHttpClient'), patch.object(core, 'PhpSchemaValidator'),
              patch.object(core, 'ReceiptStore'), patch.object(core, 'EndpointChecks'),
              patch.object(core, 'ProtocolSession'), patch.object(core, 'ProfileSmoke') as smoke,
              contextlib.redirect_stdout(output)):
            smoke.return_value.run.return_value = {'synthetic': True}
            code = core.main(['--profile', 'laragon', '--execute', '--inputs',
                              'storage/app/private/mcp-profile-input/00000000-0000-4000-8000-000000000001.json'])
        self.assertEqual(0, code)
        reply = json.loads(output.getvalue())
        entry = ROOT / 'scripts/mcp-profile-evidence.py'
        self.assertEqual(sha256(entry.read_bytes()).hexdigest(), reply['runner_sources'].get(entry.name))
        self.assertNotIn('fixture-private-token', output.getvalue())
        self.assertIs(False, reply['profile_qualified'])
        self.assertIs(False, reply['native_client_qualified'])


if __name__ == '__main__':
    unittest.main()
