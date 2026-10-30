"""Ambient TLS key logging must not create files or expose bearer exchanges."""
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

from profile_receipts import EvidenceError
from profile_transport import BoundHttpClient

UNIT_ROOT = Path(__file__).resolve().parents[3] / 'storage/app/private/mcp-profile-unit-runs'


class TransportEnvironmentTests(unittest.TestCase):
    def test_enabled_ambient_key_logging_is_rejected_before_any_file_is_created(self):
        UNIT_ROOT.mkdir(exist_ok=True)
        with tempfile.TemporaryDirectory(prefix='keylog-', dir=UNIT_ROOT) as directory:
            assert Path(directory).resolve().is_relative_to(UNIT_ROOT.resolve())
            target = Path(directory) / 'fixture-private-keylog.txt'
            with patch.dict(os.environ, {'SSLKEYLOGFILE': str(target)}):
                with self.assertRaises(EvidenceError) as caught:
                    BoundHttpClient('https://localhost:18443')
                self.assertNotIn('fixture-private', str(caught.exception))
            self.assertFalse(target.exists())

    def test_empty_key_logging_setting_keeps_strict_verification_without_a_log(self):
        with patch.dict(os.environ, {'SSLKEYLOGFILE': ''}):
            client = BoundHttpClient('https://localhost:18443')
            self.assertIsNone(client._context.keylog_filename)
            self.assertTrue(client._context.check_hostname)


if __name__ == '__main__':
    unittest.main()
