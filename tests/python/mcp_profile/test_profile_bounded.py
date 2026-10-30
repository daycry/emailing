"""The public-facing private candidate keeps dry-run credential-free and errors fixed."""
import contextlib
import importlib
import io
import json
import subprocess
import sys
import unittest
from pathlib import Path
from unittest.mock import patch

from profile_receipts import EvidenceError


class ProfileBoundedTests(unittest.TestCase):
    def test_dry_run_does_not_create_a_worker_or_load_private_inputs(self):
        core = importlib.import_module('profile_bounded')
        output = io.StringIO()
        with patch.object(core, 'run_bounded', side_effect=AssertionError('must not start')):
            with contextlib.redirect_stdout(output):
                code = core.main(['--profile', 'docker'])
        data = json.loads(output.getvalue())
        self.assertEqual(code, 0)
        self.assertEqual(data['mode'], 'dry-run')
        self.assertIs(data['credentials_loaded'], False)

    def test_unsafe_input_path_real_gated_child_fails_without_echo_or_application_boot(self):
        core = importlib.import_module('profile_bounded')
        result = subprocess.run([sys.executable, '-E', '-s', '-B', str(Path(core.__file__)),
                                 '--profile', 'docker', '--execute', '--inputs', '../fixture-private'],
                                capture_output=True, timeout=15)
        self.assertEqual(result.returncode, 2)
        self.assertEqual(json.loads(result.stdout)['error'], 'profile_probe_failed')
        self.assertEqual(result.stderr, b'')
        self.assertNotIn(b'fixture-private', result.stdout)

    def test_timeout_and_cleanup_errors_do_not_echo_diagnostics(self):
        core = importlib.import_module('profile_bounded')
        output = io.StringIO()
        with patch.object(core, 'run_bounded', side_effect=EvidenceError('fixture-private')):
            with contextlib.redirect_stdout(output):
                code = core.main(['--profile', 'laragon', '--execute'])
        self.assertEqual(code, 2)
        self.assertNotIn('fixture-private', output.getvalue())
        self.assertEqual(json.loads(output.getvalue())['error'], 'bounded_profile_probe_failed')


if __name__ == '__main__':
    unittest.main()
